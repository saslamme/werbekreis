<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\VoucherRedemption;
use Symfony\Component\Filesystem\Filesystem;

final class VoucherConcurrencyTest extends VoucherDatabaseTestCase
{
    public function testParallelFortyEuroRedemptionsCannotOverdrawFiftyEuroVoucher(): void
    {
        $results = $this->parallel('40.00', false);
        self::assertSame(1, count(array_filter($results, static fn ($r) => $r['ok'])));
        self::assertSame('10.00', static::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT remainingAmount FROM Voucher WHERE id = ?', [$this->voucher(1)->getId()]));
        self::assertSame(5, static::getContainer()->get('doctrine')->getRepository(VoucherRedemption::class)->count([]));
    }
    public function testParallelDuplicateConfirmationCommitsExactlyOnce(): void
    {
        $results = $this->parallel('10.00', true);
        self::assertTrue($results[0]['ok']); self::assertTrue($results[1]['ok']); self::assertSame($results[0]['id'], $results[1]['id']);
        self::assertSame('40.00', static::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT remainingAmount FROM Voucher WHERE id = ?', [$this->voucher(1)->getId()]));
        self::assertSame(5, static::getContainer()->get('doctrine')->getRepository(VoucherRedemption::class)->count([]));
    }
    private function parallel(string $amount, bool $duplicate): array
    {
        $actor = $this->login('redeemer'); $voucherId = $this->voucher(1)->getId(); $db = static::getContainer()->get('doctrine')->getConnection();
        $directory = sys_get_temp_dir().'/wk-voucher-race-'.bin2hex(random_bytes(8)); mkdir($directory, 0700); $processes = []; $pipes = []; $key = bin2hex(random_bytes(32));
        $db->beginTransaction(); $db->fetchOne('SELECT id FROM Voucher WHERE id = ? FOR UPDATE', [$voucherId]);
        try {
            for ($i = 0; $i < 2; ++$i) {
                $processes[$i] = proc_open([PHP_BINARY, dirname(__DIR__).'/Support/voucher-redeem-worker.php', (string) $voucherId, (string) $actor->getCompany()->getId(), (string) $actor->getId(), $amount, $duplicate ? $key : bin2hex(random_bytes(32)), $directory, (string) $i], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['file', $directory.'/error-'.$i, 'w']], $pipes[$i], dirname(__DIR__, 2));
                self::assertIsResource($processes[$i]); fclose($pipes[$i][0]);
            }
            $deadline = microtime(true) + 10;
            while (!is_file($directory.'/ready-0') || !is_file($directory.'/ready-1')) { if (microtime(true) > $deadline) { self::fail('Workers did not start'); } usleep(10000); }
            touch($directory.'/go'); usleep(250000); $db->commit();
            $results = [];
            foreach ($processes as $i => $process) {
                stream_set_blocking($pipes[$i][1], false); $output = ''; $deadline = microtime(true) + 12;
                while (proc_get_status($process)['running']) { $output .= stream_get_contents($pipes[$i][1]); if (microtime(true) > $deadline) { self::fail('Row-lock contention timed out'); } usleep(10000); }
                $output .= stream_get_contents($pipes[$i][1]); fclose($pipes[$i][1]); proc_close($process); unset($processes[$i]);
                self::assertNotSame('', $output, file_get_contents($directory.'/error-'.$i)); $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            }
            return $results;
        } finally {
            if ($db->isTransactionActive()) { $db->rollBack(); }
            foreach ($processes as $process) { proc_terminate($process); proc_close($process); }
            (new Filesystem())->remove($directory);
        }
    }
}

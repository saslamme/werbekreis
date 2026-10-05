<?php
/** Separate test-only connection/process for actual row-lock contention. No bearer codes in arguments/output. */
declare(strict_types=1);
use App\Kernel;
use App\Service\VoucherRedemptionService;
use Symfony\Component\Clock\{Clock, MockClock};
use Symfony\Component\Dotenv\Dotenv;
require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$clock = new MockClock('2030-05-01 12:00:00 UTC'); Clock::set($clock);
$kernel = new Kernel('test', false); $kernel->boot();
[$script, $voucher, $company, $actor, $amount, $key, $directory, $worker] = $argv;
file_put_contents($directory.'/ready-'.$worker, 'ready');
$deadline = microtime(true) + 12;
while (!is_file($directory.'/go')) { if (microtime(true) > $deadline) { exit(2); } usleep(10000); }
try {
    $service = new VoucherRedemptionService($kernel->getContainer()->get('doctrine'), $clock);
    $entry = $service->redeem((int) $voucher, (int) $company, $amount, (int) $actor, $key);
    echo json_encode(['ok' => true, 'id' => $entry->getId()], JSON_THROW_ON_ERROR);
} catch (\App\Service\VoucherException $exception) { echo json_encode(['ok' => false], JSON_THROW_ON_ERROR); }
finally { $kernel->shutdown(); }

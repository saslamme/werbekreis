<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\DataFixtures\DirectoryImageFixtures;
use App\Service\CompanyImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

final class FixtureArtworkTest extends TestCase
{
    private function artwork(): DirectoryImageFixtures
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        return new DirectoryImageFixtures(new CompanyImageStorage($em, new Filesystem(), new NullLogger(), sys_get_temp_dir()));
    }

    public function testPngIsDeterministicDecodableAndContainsDifferentMotifs(): void
    {
        $artwork = $this->artwork();
        $files = [];
        try {
            $hashes = [];
            for ($variant = 0; $variant < 4; ++$variant) {
                $file = $artwork->png([47, 107, 82], 80, 48, $variant);
                $files[] = $file->getPathname();
                $png = (string) file_get_contents($file->getPathname());
                self::assertSame('image/png', $file->getMimeType());
                self::assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
                self::assertSame([80, 48], array_slice(getimagesize($file->getPathname()), 0, 2));
                $offset = 8;
                $compressed = '';
                $types = [];
                while ($offset < strlen($png)) {
                    $length = unpack('N', substr($png, $offset, 4))[1];
                    $type = substr($png, $offset + 4, 4);
                    $payload = substr($png, $offset + 8, $length);
                    self::assertSame(pack('N', crc32($type.$payload)), substr($png, $offset + 8 + $length, 4));
                    if ($type === 'IDAT') {
                        $compressed .= $payload;
                    }
                    $types[] = $type;
                    $offset += $length + 12;
                }
                self::assertSame(['IHDR', 'IDAT', 'IEND'], $types);
                $raw = gzuncompress($compressed);
                self::assertNotFalse($raw);
                self::assertSame((1 + 80 * 3) * 48, strlen($raw));
                $colours = [];
                for ($y = 0; $y < 48; ++$y) {
                    $row = substr($raw, $y * 241, 241);
                    self::assertSame("\x00", $row[0]);
                    foreach (str_split(substr($row, 1), 3) as $colour) {
                        $colours[bin2hex($colour)] = true;
                    }
                }
                self::assertGreaterThan(20, count($colours), 'Artwork must include a gradient and contrasting shapes.');
                $hashes[] = hash('sha256', $png);
                $copy = $artwork->png([47, 107, 82], 80, 48, $variant);
                $files[] = $copy->getPathname();
                self::assertSame($png, file_get_contents($copy->getPathname()));
            }
            self::assertCount(4, array_unique($hashes));
        } finally {
            foreach ($files as $path) {
                unlink($path);
            }
        }
    }

    public static function invalidInputs(): iterable
    {
        yield 'empty width' => [[47, 107, 82], 0, 48, 0];
        yield 'negative height' => [[47, 107, 82], 80, -1, 0];
        yield 'too large' => [[47, 107, 82], 1601, 48, 0];
        yield 'negative variant' => [[47, 107, 82], 80, 48, -1];
        yield 'incomplete colour' => [[47, 107], 80, 48, 0];
        yield 'out of range colour' => [[256, 107, 82], 80, 48, 0];
        yield 'non-integer colour' => [[47.5, 107, 82], 80, 48, 0];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputsAreRejectedBeforeCreatingFiles(array $colour, int $width, int $height, int $variant): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->artwork()->png($colour, $width, $height, $variant);
    }
}

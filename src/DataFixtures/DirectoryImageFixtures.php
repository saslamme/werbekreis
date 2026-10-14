<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Company;
use App\Entity\CompanyImage;
use App\Service\CompanyImageStorage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Original, deterministic geometric demo artwork; no third-party images. Never load into production. */
final class DirectoryImageFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly CompanyImageStorage $storage)
    {
    }

    public function getDependencies(): array
    {
        return [DirectoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::data() as [$name, $type, $colour, $width, $height, $alt, $position, $variant]) {
            $image = (new CompanyImage())->setType($type)->setAltText($alt)->setPosition($position)->setCompany($this->getReference($name, Company::class));
            $this->storage->save($image, $this->png($colour, $width, $height, $variant));
        }
    }

    /** Data definitions only; no files or database access. */
    public static function data(): array
    {
        $colours = [[47, 107, 82], [162, 92, 47], [94, 107, 125], [56, 139, 144], [58, 102, 160], [136, 77, 120], [210, 150, 42], [183, 91, 105], [70, 119, 136], [78, 115, 87]];
        $rows = [];
        foreach (DirectoryFixtures::COMPANY_NAMES as $index => $name) {
            $entries = [['logo', 400, 400, 'Grafisches Logo für '.$name]];
            if ($index < 6 || $index === 9) {
                $entries[] = ['cover', 1200, 400, 'Grafisches Titelmotiv für '.$name];
            }
            if ($index < 4) {
                $entries[] = ['gallery', 640, 480, 'Geometrische Farbkomposition für '.$name];
                $entries[] = ['gallery', 480, 640, 'Gestaltetes Galeriemotiv für '.$name];
            }
            foreach ($entries as $position => [$type, $width, $height, $alt]) {
                $rows[] = [$name, $type, $colours[$index], $width, $height, $alt, $position, $index + $position];
            }
        }

        return $rows;
    }

    /**
     * Dependency-free RGB PNG with a gradient and alternating circles, panels, stripes or diamonds.
     * The same colour, size and variant always produce identical bytes; storage still assigns its own safe filenames.
     *
     * @param array{int, int, int} $rgb
     */
    public function png(array $rgb, int $width, int $height, int $variant = 0): UploadedFile
    {
        if ($width < 1 || $height < 1 || $width > 1600 || $height > 1600 || count($rgb) !== 3 || $variant < 0) {
            throw new \InvalidArgumentException('Invalid fixture artwork dimensions or variant.');
        }
        foreach ($rgb as $value) {
            if (!is_int($value) || $value < 0 || $value > 255) {
                throw new \InvalidArgumentException('Invalid fixture artwork colour.');
            }
        }
        $accent = array_map(static fn (int $value): int => 255 - intdiv($value, 2), $rgb);
        $pixels = '';
        for ($y = 0; $y < $height; ++$y) {
            $pixels .= "\x00";
            $blend = $y / max(1, $height - 1);
            $base = array_map(static fn (int $value): int => (int) round($value + (245 - $value) * $blend * 0.55), $rgb);
            for ($x = 0; $x < $width; ++$x) {
                $u = $x / $width;
                $v = $y / $height;
                $shape = match ($variant % 4) {
                    0 => ($u - 0.68) ** 2 + ($v - 0.45) ** 2 < 0.065,
                    1 => $u > 0.15 && $u < 0.85 && $v > 0.25 && $v < 0.70,
                    2 => (int) floor(($u + $v) * 6) % 3 === 0,
                    3 => abs($u - 0.5) + abs($v - 0.5) < 0.35,
                };
                // Narrow light stripe ties the motif together in square, portrait and wide formats.
                $colour = abs($u - $v * 0.3 - 0.12) < 0.018 ? [245, 241, 232] : ($shape ? $accent : $base);
                $pixels .= pack('C3', ...$colour);
            }
        }
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $compressed = gzcompress($pixels);
        if ($compressed === false) {
            throw new \RuntimeException('Could not compress fixture artwork.');
        }
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).$chunk('IDAT', $compressed).$chunk('IEND', '');
        $path = tempnam(sys_get_temp_dir(), 'wk-fixture-');
        if ($path === false) {
            throw new \RuntimeException('Could not create temporary fixture artwork.');
        }
        if (file_put_contents($path, $png) !== strlen($png)) {
            unlink($path);
            throw new \RuntimeException('Could not write fixture artwork.');
        }

        return new UploadedFile($path, 'fixture.png', 'image/png', test: true);
    }
}

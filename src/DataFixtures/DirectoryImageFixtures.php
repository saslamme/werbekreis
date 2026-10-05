<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CompanyImage;
use App\Repository\CompanyRepository;
use App\Service\CompanyImageStorage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Generated placeholder images (plain colour PNGs) for development/test only. Never load into production.
 * Kept separate from DirectoryFixtures so tests without files on disk stay unaffected.
 */
final class DirectoryImageFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly CompanyRepository $companies, private readonly CompanyImageStorage $storage)
    {
    }

    public function getDependencies(): array
    {
        return [DirectoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // Logo, cover and gallery; logo only; and companies without any image as fallback cases.
        $images = [
            'Musterladen Hasebogen' => [['logo', [47, 107, 82], 'Logo des Musterladens Hasebogen'], ['cover', [232, 237, 227], 'Schaufenster des Musterladens'], ['gallery', [214, 196, 168], 'Verkaufsraum mit Wohnaccessoires'], ['gallery', [181, 205, 190], 'Geschenkideen im Regal']],
            'Beispielcafé Uferpause' => [['logo', [227, 6, 19], 'Logo des Beispielcafés Uferpause']],
        ];
        foreach ($images as $name => $entries) {
            $company = $this->companies->findOneBy(['name' => $name]) ?? throw new \LogicException('Missing fixture company '.$name);
            foreach ($entries as $position => [$type, $colour, $alt]) {
                $image = (new CompanyImage())->setType($type)->setAltText($alt)->setPosition($position)->setCompany($company);
                $this->storage->save($image, $this->png($colour, $type === 'cover' ? 1200 : 400, $type === 'cover' ? 400 : 300));
            }
        }
    }

    /** @param array{int, int, int} $rgb */
    private function png(array $rgb, int $width, int $height): UploadedFile
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $row = "\x00".str_repeat(pack('C3', ...$rgb), $width);
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).$chunk('IDAT', (string) gzcompress(str_repeat($row, $height))).$chunk('IEND', '');
        $path = (string) tempnam(sys_get_temp_dir(), 'wk-fixture-');
        file_put_contents($path, $png);

        return new UploadedFile($path, 'fixture.png', 'image/png', test: true);
    }
}

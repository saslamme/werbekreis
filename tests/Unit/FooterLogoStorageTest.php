<?php

declare(strict_types=1);
namespace App\Tests\Unit;

use App\Entity\PortalSettings;
use App\Service\PortalAssetStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

final class FooterLogoStorageTest extends TestCase
{
    private string $directory;
    private array $temporary = [];
    protected function setUp(): void { $this->directory = sys_get_temp_dir().'/footer-test-'.bin2hex(random_bytes(8)); }
    protected function tearDown(): void { (new Filesystem())->remove([$this->directory, ...$this->temporary]); }
    private function upload(string $contents, string $name = 'footer.svg'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'footer-upload-');
        $this->temporary[] = $path;
        file_put_contents($path, $contents);
        return new UploadedFile($path, $name, test: true);
    }
    private function storage(EntityManagerInterface $em): PortalAssetStorage
    {
        return new PortalAssetStorage($em, new Filesystem(), Validation::createValidator(), new NullLogger(), $this->directory);
    }
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="100"><rect width="800" height="100" fill="white"/></svg>';

    public static function formats(): iterable { yield ['svg', 'image/svg+xml']; yield ['png', 'image/png']; yield ['jpeg', 'image/jpeg']; }
    #[DataProvider('formats')]
    public function testFooterUsesSecureStorage(string $format, string $mime): void
    {
        $contents = self::SVG;
        if ($format !== 'svg') {
            $image = imagecreatetruecolor(2, 2);
            ob_start();
            $format === 'png' ? imagepng($image) : imagejpeg($image);
            $contents = ob_get_clean();
        }
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $settings = new PortalSettings();
        $storage = $this->storage($em);
        $storage->save($settings, null, footerFile: $this->upload($contents, '../../footer.'.$format));
        $name = $settings->getFooterLogoFileName();
        self::assertMatchesRegularExpression(PortalSettings::LOGO_NAME_PATTERN, $name);
        self::assertSame($mime, $storage->mimeType($name));
        self::assertSame('footer.'.$format, $settings->getFooterLogoOriginalName());
        self::assertSame(0640, fileperms($storage->path($name)) & 0777);
        self::assertNull($settings->getLogoFileName());
    }
    public static function invalidUploads(): iterable
    {
        yield from PortalAssetStorageTest::invalidFiles();
        foreach (PortalAssetStorageTest::unsafeSvg() as [$body]) {
            yield ['<svg xmlns="http://www.w3.org/2000/svg">'.$body.'</svg>', 'footer.svg'];
        }
    }
    #[DataProvider('invalidUploads')]
    public function testInvalidFooterRollsBackStagedWebsiteUpload(string $contents, string $name): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $settings = new PortalSettings();
        try {
            $this->storage($em)->save($settings, $this->upload(self::SVG), footerFile: $this->upload($contents, $name));
            self::fail('Invalid footer accepted');
        } catch (\InvalidArgumentException $e) { self::assertSame(PortalAssetStorage::UPLOAD_ERROR, $e->getMessage()); }
        self::assertNull($settings->getLogoFileName());
        self::assertNull($settings->getFooterLogoFileName());
        self::assertSame([], glob($this->directory.'/*'));
    }
    public static function changes(): iterable
    {
        foreach ([false, true] as $fail) {
            foreach (['website', 'footer', 'both'] as $slot) {
                foreach ([false, true] as $remove) { yield [$fail, $slot, $remove]; }
            }
        }
    }
    #[DataProvider('changes')]
    public function testAtomicReplacementRemovalAndSlotIndependence(bool $fail, string $slot, bool $remove): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $storage = $this->storage($em);
        (new Filesystem())->mkdir($this->directory);
        $website = str_repeat('a', 32).'.svg';
        $footer = str_repeat('b', 32).'.svg';
        foreach ([$website, $footer] as $name) { file_put_contents($storage->path($name), self::SVG); }
        $settings = (new PortalSettings())->setLogoFileName($website)->setLogoOriginalName('website.svg')
            ->setFooterLogoFileName($footer)->setFooterLogoOriginalName('footer.svg')->setFooterLogoMaxWidth(180)->setFooterLogoMaxHeight(55);
        $em->expects(self::once())->method('flush')->willReturnCallback(function () use ($storage, $website, $footer, $fail): void {
            self::assertFileExists($storage->path($website));
            self::assertFileExists($storage->path($footer));
            if ($fail) { throw new \RuntimeException('DB failure'); }
        });
        $changeWebsite = $slot !== 'footer';
        $changeFooter = $slot !== 'website';
        try {
            // Removal also checked with uploads, proving upload precedence in both slots.
            $storage->save($settings, $changeWebsite && !$remove ? $this->upload(self::SVG) : null, $changeWebsite,
                $changeFooter && !$remove ? $this->upload(self::SVG) : null, $changeFooter);
            self::assertFalse($fail);
        } catch (\RuntimeException) { self::assertTrue($fail); }
        if ($fail) {
            self::assertSame($website, $settings->getLogoFileName());
            self::assertSame($footer, $settings->getFooterLogoFileName());
            self::assertSame('website.svg', $settings->getLogoOriginalName());
            self::assertSame('footer.svg', $settings->getFooterLogoOriginalName());
            self::assertCount(2, glob($this->directory.'/*'));
        } else {
            foreach ([[$website, $settings->getLogoFileName(), $changeWebsite], [$footer, $settings->getFooterLogoFileName(), $changeFooter]] as [$old, $new, $changed]) {
                self::assertSame(!$changed, is_file($storage->path($old)));
                if (!$changed) { self::assertSame($old, $new); }
                elseif ($remove) { self::assertNull($new); }
                else { self::assertNotSame($old, $new); self::assertFileExists($storage->path($new)); }
            }
        }
        self::assertSame(180, $settings->getFooterLogoMaxWidth());
        self::assertSame(55, $settings->getFooterLogoMaxHeight());
    }
}

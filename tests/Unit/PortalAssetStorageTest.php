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

final class PortalAssetStorageTest extends TestCase
{
    private string $directory;
    private array $temporary = [];
    protected function setUp(): void { $this->directory = sys_get_temp_dir().'/portal-test-'.bin2hex(random_bytes(8)); }
    protected function tearDown(): void { (new Filesystem())->remove([$this->directory, ...$this->temporary]); }

    private function upload(string $contents, string $name = 'logo.svg'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'portal-upload-');
        $this->temporary[] = $path;
        file_put_contents($path, $contents);
        return new UploadedFile($path, $name, 'image/png', test: true);
    }
    private function storage(EntityManagerInterface $em): PortalAssetStorage
    {
        return new PortalAssetStorage($em, new Filesystem(), Validation::createValidator(), new NullLogger(), $this->directory);
    }
    private function raster(string $format, int $width = 2): string
    {
        $image = imagecreatetruecolor($width, 2);
        ob_start();
        $format === 'png' ? imagepng($image) : imagejpeg($image);
        return ob_get_clean();
    }
    public static function formats(): iterable { yield ['png', 'image/png']; yield ['jpeg', 'image/jpeg']; yield ['svg', 'image/svg+xml']; }
    #[DataProvider('formats')]
    public function testValidFormatsAreStoredWithSafeNames(string $format, string $mime): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $storage = $this->storage($em);
        $settings = new PortalSettings();
        $contents = $format === 'svg' ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20" fill="#214d3b"/></svg>' : $this->raster($format);
        $storage->save($settings, $this->upload($contents, '../../original.'.$format));
        self::assertMatchesRegularExpression(PortalSettings::LOGO_NAME_PATTERN, $settings->getLogoFileName());
        self::assertSame($format === 'jpeg' ? 'jpg' : $format, pathinfo($settings->getLogoFileName(), PATHINFO_EXTENSION));
        self::assertSame('original.'.$format, $settings->getLogoOriginalName());
        self::assertSame($mime, $storage->mimeType($settings->getLogoFileName()));
        self::assertFileExists($storage->path($settings->getLogoFileName()));
        self::assertSame(0640, fileperms($storage->path($settings->getLogoFileName())) & 0777);
    }
    public static function unsafeSvg(): iterable
    {
        foreach ([
            '<script>alert(1)</script>', '<g onload="alert(1)"/>', '<path onclick="alert(1)"/>',
            '<use href="javascript:alert(1)"/>', '<foreignObject><div>HTML</div></foreignObject>',
            '<image href="https://example.com/tracker.png"/>', '<use href="https://example.com/logo.svg#x"/>',
            '<use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="//example.com/x"/>',
            '<path fill="u\\72l(https://example.com/x)"/>', '<path fill="url(https://example.com/x)"/>', '<style>@import "https://example.com/x";</style>',
            '<g style="fill:u\\72l(https://example.com/x)"/>', '<set attributeName="href" to="javascript:alert(1)"/>',
            '<g xml:base="https://example.com"><use href="#x"/></g>',
        ] as $body) { yield [$body]; }
    }
    #[DataProvider('unsafeSvg')]
    public function testActiveSvgIsRejected(string $body): void
    {
        $this->assertRejected('<svg xmlns="http://www.w3.org/2000/svg">'.$body.'</svg>');
    }
    private function assertRejected(string $contents, string $name = 'logo.svg'): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $settings = new PortalSettings();
        try { $this->storage($em)->save($settings, $this->upload($contents, $name)); self::fail('Unsafe upload accepted.'); }
        catch (\InvalidArgumentException $e) { self::assertSame(PortalAssetStorage::UPLOAD_ERROR, $e->getMessage()); }
        self::assertNull($settings->getLogoFileName());
        self::assertDirectoryDoesNotExist($this->directory);
    }
    public static function invalidFiles(): iterable
    {
        yield ['GIF89a'.str_repeat("\0", 100), 'logo.gif'];
        yield ['%PDF-1.4 test', 'logo.pdf'];
        yield ['<html><body>not svg</body></html>', 'logo.svg'];
        yield ['<?php echo "bad";', 'logo.png'];
        yield ['<root/>', 'logo.svg'];
        yield ['<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>', 'logo.svg'];
        yield ['<?xml-stylesheet href="https://example.com/x"?><svg xmlns="http://www.w3.org/2000/svg"/>', 'logo.svg'];
        yield ['<svg xmlns="http://www.w3.org/2000/svg">', 'logo.svg'];
        yield [str_repeat('x', 5_000_001), 'logo.png'];
    }
    #[DataProvider('invalidFiles')]
    public function testInvalidFilesAreRejected(string $contents, string $name): void { $this->assertRejected($contents, $name); }
    public function testCorruptAndOversizedDimensionsAreRejected(): void
    {
        $png = $this->raster('png');
        $this->assertRejected(substr($png, 0, 33), 'logo.png');
        $this->assertRejected($this->raster('png', 8001), 'logo.png');
    }
    #[DataProvider('failureModes')]
    public function testReplacementOrRemovalHonorsDatabaseCommit(bool $fail, bool $remove): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $storage = $this->storage($em);
        (new Filesystem())->mkdir($this->directory);
        $old = str_repeat('a', 32).'.png';
        file_put_contents($storage->path($old), 'previous');
        $settings = (new PortalSettings())->setLogoFileName($old)->setLogoOriginalName('previous.png');
        $em->expects(self::once())->method('flush')->willReturnCallback(function () use ($storage, $old, $fail): void {
            self::assertFileExists($storage->path($old));
            if ($fail) { throw new \RuntimeException('Simulated database failure'); }
        });
        try { $storage->save($settings, $remove ? null : $this->upload($this->raster('png'), 'new.png'), $remove); self::assertFalse($fail); }
        catch (\RuntimeException $e) { self::assertTrue($fail); }
        if ($fail) {
            self::assertSame($old, $settings->getLogoFileName());
            self::assertSame('previous.png', $settings->getLogoOriginalName());
            self::assertSame([$old], array_values(array_diff(scandir($this->directory), ['.', '..'])));
        } else {
            self::assertFileDoesNotExist($storage->path($old));
            $remove ? self::assertNull($settings->getLogoFileName()) : self::assertFileExists($storage->path($settings->getLogoFileName()));
        }
    }
    public static function failureModes(): iterable { foreach ([true, false] as $fail) { foreach ([true, false] as $remove) { yield [$fail, $remove]; } } }
    public function testStorageRejectsTraversalAndSymlinks(): void
    {
        $storage = $this->storage($this->createMock(EntityManagerInterface::class));
        foreach (['../.env', '/etc/passwd', str_repeat('a',32).'.php'] as $name) {
            try { $storage->path($name); self::fail('Invalid name accepted'); } catch (\InvalidArgumentException) {}
        }
        (new Filesystem())->mkdir($this->directory);
        symlink('/etc/passwd', $this->directory.'/'.str_repeat('b',32).'.png');
        $this->expectException(\InvalidArgumentException::class);
        $storage->path(str_repeat('b',32).'.png');
    }
}

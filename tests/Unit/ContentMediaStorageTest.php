<?php
declare(strict_types=1);
namespace App\Tests\Unit;
use App\Entity\{ContentElement, ContentPage};
use App\Service\ContentMediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;
final class ContentMediaStorageTest extends TestCase
{
    private string $directory; private array $temps=[];
    protected function setUp(): void { $this->directory=sys_get_temp_dir().'/content-test-'.bin2hex(random_bytes(8)); }
    protected function tearDown(): void { (new Filesystem())->remove([$this->directory,...$this->temps]); }
    private function upload(string $data): UploadedFile { $path=tempnam(sys_get_temp_dir(),'content-');$this->temps[]=$path;file_put_contents($path,$data);return new UploadedFile($path,'../../untrusted.png','image/png',test:true); }
    private function png(): string { $image=imagecreatetruecolor(2,2); ob_start();imagepng($image);return ob_get_clean(); }
    private function storage(bool $fail=false): ContentMediaStorage {
        $em=$this->createMock(EntityManagerInterface::class); $em->method('wrapInTransaction')->willReturnCallback(function($callback) use($fail) { if($fail){throw new \RuntimeException('commit failed');} $callback();return null; });
        return new ContentMediaStorage($em,Validation::createValidator(),new NullLogger(),$this->directory);
    }
    public function testUploadDuplicateAndDeleteHaveIndependentFiles(): void {
        $storage=$this->storage();$e=(new ContentElement('gallery'))->setPage(new ContentPage('about'));
        $storage->save($e,[],[$this->upload($this->png())],'Bild');$original=$e->getMedia()->first();
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/D',$original->getFileName());self::assertSame('untrusted.png',$original->getOriginalName());
        $copy=$storage->duplicate($e);$copyName=$copy->getMedia()->first()->getFileName();self::assertNotSame($original->getFileName(),$copyName);
        $storage->delete($copy);self::assertFileDoesNotExist($storage->path($copyName));self::assertFileExists($storage->verifiedPath($original));
    }
    public function testCommitFailureCleansStagedUploadAndRetainsOldFile(): void {
        $e=(new ContentElement('gallery'))->setPage(new ContentPage('about'));$ok=$this->storage();$ok->save($e,[],[$this->upload($this->png())],'Original');$original=$e->getMedia()->first();
        try{$this->storage(true)->save($e,[$original],[$this->upload($this->png())],'Neu');self::fail('Failure expected');}catch(\RuntimeException $error){self::assertSame('commit failed',$error->getMessage());}
        self::assertFileExists($ok->verifiedPath($original));self::assertCount(1,glob($this->directory.'/*'));
    }
    public function testJpegAndWebpAreDecodedAndStoredWithTheirActualMime(): void {
        foreach (['jpeg'=>'image/jpeg','webp'=>'image/webp'] as $format=>$mime) {
            $image=imagecreatetruecolor(2,2);ob_start();$format==='webp'?imagewebp($image):imagejpeg($image);$bytes=ob_get_clean();
            $element=(new ContentElement('image'))->setPage(new ContentPage('about'));$storage=$this->storage();$storage->save($element,[],[$this->upload($bytes)],'Bild');
            $media=$element->getMedia()->first();self::assertSame($mime,$media->getMimeType());self::assertFileExists($storage->verifiedPath($media));
        }
    }
    public function testDeleteFailureRetainsTheFile(): void {
        $element=(new ContentElement('image'))->setPage(new ContentPage('about'));$storage=$this->storage();$storage->save($element,[],[$this->upload($this->png())],'Bild');$media=$element->getMedia()->first();
        try{$this->storage(true)->delete($element);self::fail('Failure expected');}catch(\RuntimeException $error){self::assertSame('commit failed',$error->getMessage());}
        self::assertFileExists($storage->verifiedPath($media));
    }
    public function testSpoofedImageAndTraversalAreRejected(): void {
        $storage=$this->storage();$e=(new ContentElement('image'))->setPage(new ContentPage('about'));
        try{$storage->save($e,[],[$this->upload('<?php echo 1;')],'Bild');self::fail('Spoof accepted');}catch(\InvalidArgumentException){self::assertCount(0,$e->getMedia());}
        $this->expectException(\InvalidArgumentException::class);$storage->path('../outside.png');
    }
}

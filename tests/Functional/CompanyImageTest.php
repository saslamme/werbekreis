<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\CompanyImage;
use App\Entity\ContactPerson;
use App\Repository\CompanyRepository;
use App\Service\CompanyImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CompanyImageTest extends DirectoryDatabaseTestCase
{
    private string $uploadDirectory;
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadDirectory = dirname(__DIR__, 2).'/var/uploads/test_companies';
        (new Filesystem())->remove($this->uploadDirectory);
    }
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->uploadDirectory);
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function pngUpload(): UploadedFile
    {
        $chunk = static fn (string $type, string $bytes): string => pack('N', strlen($bytes)).$type.$bytes.pack('N', crc32($type.$bytes));
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0)).$chunk('IDAT', gzcompress("\x00\xff\x00\x00\xff")).$chunk('IEND', '');
        $path = tempnam(sys_get_temp_dir(), 'wk-image-');
        file_put_contents($path, $png);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, '../../unsafe-name.php', 'image/png', test: true);
    }

    private function uploadImage(int $companyId): CompanyImage
    {
        $this->client->request('GET', '/admin/companies/'.$companyId.'/images/new');
        self::assertResponseIsSuccessful();
        $form = $this->client->getCrawler()->selectButton('Bild speichern')->form();
        $values = $form->getPhpValues();
        $values['company_image']['altText'] = 'Ein fiktives Testbild';
        $this->client->request('POST', '/admin/companies/'.$companyId.'/images/new', $values, ['company_image' => ['file' => $this->pngUpload()]]);
        self::assertResponseStatusCodeSame(303);
        $image = static::getContainer()->get(EntityManagerInterface::class)->getRepository(CompanyImage::class)->findOneBy(['company' => $companyId]);
        self::assertInstanceOf(CompanyImage::class, $image);

        return $image;
    }

    public function testUploadReplaceServeAndDelete(): void
    {
        $this->login('editor');
        $companyId = $this->firstCompany()->getId();
        $image = $this->uploadImage($companyId);
        $imageId = $image->getId();
        $oldName = $image->getFileName();
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $oldName);
        self::assertFileExists($this->uploadDirectory.'/'.$oldName);
        $this->client->request('GET', '/admin/companies/'.$companyId.'/images/'.$imageId.'/file');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('private', $this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->request('GET', '/admin/companies/'.$companyId.'/images/'.$imageId.'/edit');
        $values = $this->client->getCrawler()->selectButton('Bild speichern')->form()->getPhpValues();
        $this->client->request('POST', '/admin/companies/'.$companyId.'/images/'.$imageId.'/edit', $values, ['company_image' => ['file' => $this->pngUpload()]]);
        self::assertResponseStatusCodeSame(303);
        $saved = static::getContainer()->get(EntityManagerInterface::class)->find(CompanyImage::class, $imageId);
        self::assertNotSame($oldName, $saved->getFileName());
        self::assertFileDoesNotExist($this->uploadDirectory.'/'.$oldName);
        $newName = $saved->getFileName();
        $this->client->request('POST', '/admin/companies/'.$companyId.'/images/'.$imageId.'/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/companies/'.$companyId.'/edit');
        $this->client->submitForm('Bild löschen');
        self::assertResponseStatusCodeSame(303);
        self::assertFileDoesNotExist($this->uploadDirectory.'/'.$newName);
        self::assertNull(static::getContainer()->get(EntityManagerInterface::class)->find(CompanyImage::class, $imageId));
    }

    public function testCompanyDeletionRemovesImageFiles(): void
    {
        $this->login('admin');
        $companyId = $this->firstCompany()->getId();
        $image = $this->uploadImage($companyId);
        $name = $image->getFileName();
        $this->client->request('GET', '/admin/companies/'.$companyId.'/edit');
        $this->client->submitForm('Unternehmen endgültig löschen');
        self::assertResponseStatusCodeSame(303);
        self::assertFileDoesNotExist($this->uploadDirectory.'/'.$name);
    }

    public function testInvalidFileTypeIsRejectedEvenWithPngFileName(): void
    {
        $this->login('editor');
        $id = $this->firstCompany()->getId();
        $path = tempnam(sys_get_temp_dir(), 'wk-invalid-');
        file_put_contents($path, '<?php echo "not an image";');
        $this->temporaryFiles[] = $path;
        $this->client->request('GET', '/admin/companies/'.$id.'/images/new');
        $values = $this->client->getCrawler()->selectButton('Bild speichern')->form()->getPhpValues();
        $values['company_image']['altText'] = 'Unzulässiger Upload';
        $file = new UploadedFile($path, 'pretend.png', 'image/png', test: true);
        $this->client->request('POST', '/admin/companies/'.$id.'/images/new', $values, ['company_image' => ['file' => $file]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, static::getContainer()->get(EntityManagerInterface::class)->getRepository(CompanyImage::class)->count([]));
    }

    public function testOversizedImageIsRejected(): void
    {
        $this->login('editor');
        $id = $this->firstCompany()->getId();
        $file = $this->pngUpload();
        file_put_contents($file->getPathname(), str_repeat('x', 5 * 1024 * 1024 + 1), FILE_APPEND);
        $this->client->request('GET', '/admin/companies/'.$id.'/images/new');
        $values = $this->client->getCrawler()->selectButton('Bild speichern')->form()->getPhpValues();
        $values['company_image']['altText'] = 'Zu großes Testbild';
        $this->client->request('POST', '/admin/companies/'.$id.'/images/new', $values, ['company_image' => ['file' => $file]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, static::getContainer()->get(EntityManagerInterface::class)->getRepository(CompanyImage::class)->count([]));
    }

    public function testImageCannotBeAccessedThroughAnotherCompany(): void
    {
        $this->login('editor');
        $companyId = $this->firstCompany()->getId();
        $image = $this->uploadImage($companyId);
        $other = static::getContainer()->get(CompanyRepository::class)->findOneBy(['name' => 'Beispielcafé Uferpause']);
        $this->client->request('GET', '/admin/companies/'.$other->getId().'/images/'.$image->getId().'/edit');
        self::assertResponseStatusCodeSame(404);
    }

    public function testMemberCannotReadUploadedImages(): void
    {
        $this->login('admin');
        $id = $this->firstCompany()->getId();
        $image = $this->uploadImage($id);
        $this->login('member');
        $this->client->request('GET', '/admin/companies/'.$id.'/images/'.$image->getId().'/file');
        self::assertResponseStatusCodeSame(403);
    }

    public function testCleanupRetainsReferencedAndRecentFiles(): void
    {
        $this->login('editor');
        $id = $this->firstCompany()->getId();
        $image = $this->uploadImage($id);
        $referenced = $image->getFileName();
        touch($this->uploadDirectory.'/'.$referenced, time() - 7200);
        $old = str_repeat('a', 32).'.png';
        $recent = str_repeat('b', 32).'.png';
        file_put_contents($this->uploadDirectory.'/'.$old, 'orphan');
        touch($this->uploadDirectory.'/'.$old, time() - 7200);
        file_put_contents($this->uploadDirectory.'/'.$recent, 'in-flight');
        $storage = static::getContainer()->get(CompanyImageStorage::class);
        self::assertSame([$old], $storage->cleanup());
        self::assertFileExists($this->uploadDirectory.'/'.$old);
        $storage->cleanup(true);
        self::assertFileDoesNotExist($this->uploadDirectory.'/'.$old);
        self::assertFileExists($this->uploadDirectory.'/'.$referenced);
        self::assertFileExists($this->uploadDirectory.'/'.$recent);
    }

    public function testContactCannotReferenceAnotherCompanyImage(): void
    {
        $this->login('editor');
        $ownerId = $this->firstCompany()->getId();
        $image = $this->uploadImage($ownerId);
        $other = static::getContainer()->get(CompanyRepository::class)->findOneBy(['name' => 'Beispielcafé Uferpause']);
        $this->client->request('GET', '/admin/companies/'.$other->getId().'/edit');
        $values = $this->client->getCrawler()->selectButton('Speichern')->form()->getPhpValues();
        $values['company']['contactPersons'][0]['image'] = $image->getId();
        $this->client->request('POST', '/admin/companies/'.$other->getId().'/edit', $values);
        self::assertResponseStatusCodeSame(422);
    }

    public function testDeletingImageNullsContactReference(): void
    {
        $this->login('editor');
        $companyId = $this->firstCompany()->getId();
        $image = $this->uploadImage($companyId);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contact = $em->getRepository(ContactPerson::class)->findOneBy(['company' => $companyId]);
        $contact->setImage($image);
        $em->flush();
        $contactId = $contact->getId();
        $this->client->request('GET', '/admin/companies/'.$companyId.'/edit');
        $this->client->submitForm('Bild löschen');
        self::assertResponseStatusCodeSame(303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertNull($em->find(ContactPerson::class, $contactId)->getImage());
    }

    public function testStorageRejectsTraversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        static::getContainer()->get(CompanyImageStorage::class)->path('../.env.local');
    }
}

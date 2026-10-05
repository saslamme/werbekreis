<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\DirectoryImageFixtures;
use App\Entity\Offer;
use App\Repository\OfferRepository;
use App\Service\CompanyImageStorage;
use App\Service\DirectorySlugger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AdminOfferTest extends OfferDatabaseTestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public static function managers(): iterable
    {
        yield ['admin'];
        yield ['editor'];
    }

    #[DataProvider('managers')]
    public function testAdminAndEditorCanCreateViewEditDisableAndDelete(string $role): void
    {
        $this->login($role);
        $companyId = $this->firstCompany()->getId();
        $this->client->request('GET', '/admin/offers/new?company='.$companyId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="offer[company]"] option[value="'.$companyId.'"][selected]');
        $this->client->submitForm('Speichern', ['offer[title]' => '20 Prozent auf Winterjacken', 'offer[regularPrice]' => '99,90', 'offer[offerPrice]' => '79,90', 'offer[discountText]' => '20 € sparen']);
        self::assertResponseStatusCodeSame(303);
        $offer = $this->offer('20-prozent-auf-winterjacken');
        $id = $offer->getId();
        self::assertSame('79.90', $offer->getOfferPrice());
        $this->client->request('GET', '/admin/offers/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', '20 Prozent auf Winterjacken');
        $this->client->request('GET', '/admin/offers/'.$id.'/edit');
        $this->client->submitForm('Speichern', ['offer[title]' => 'Neuer Titel', 'offer[active]' => false]);
        self::assertResponseStatusCodeSame(303);
        $offer = $this->offer('20-prozent-auf-winterjacken');
        self::assertSame('Neuer Titel', $offer->getTitle());
        self::assertFalse($offer->isActive());
        $this->client->request('GET', '/angebote/20-prozent-auf-winterjacken');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/offers/'.$id.'/edit');
        $this->client->submitForm('Angebot löschen');
        self::assertResponseStatusCodeSame(303);
        self::assertNull(static::getContainer()->get(OfferRepository::class)->find($id));
    }

    public function testMemberAndAnonymousCannotManageOffers(): void
    {
        $id = $this->offer()->getId();
        foreach (['/admin/offers', '/admin/offers/new', '/admin/offers/'.$id, '/admin/offers/'.$id.'/edit', '/admin/offers/'.$id.'/image'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/login');
        }
        $this->login('member');
        foreach (['/admin/offers', '/admin/offers/new', '/admin/offers/'.$id.'/edit', '/admin/offers/'.$id.'/image'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/admin/offers/'.$id.'/delete');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/angebote');
        self::assertResponseIsSuccessful();
    }

    public function testInvalidPricesPeriodAndDuplicateSlugAreFormErrors(): void
    {
        $this->login('editor');
        foreach ([
            ['regularPrice' => '10,00', 'offerPrice' => '10,01'],
            ['offerPrice' => '-1,00'],
            ['offerPrice' => '59,999'],
            ['startsAt' => '2030-05-03T12:00', 'endsAt' => '2030-05-02T12:00'],
            ['slug' => 'lieblingsstuecke-zum-aktionspreis'],
            ['externalUrl' => 'javascript:alert(1)'],
        ] as $fields) {
            $this->client->request('GET', '/admin/offers/new?company='.$this->firstCompany()->getId());
            $values = ['offer[title]' => 'Invalid'];
            foreach ($fields as $key => $value) {
                $values['offer['.$key.']'] = $value;
            }
            $this->client->submitForm('Speichern', $values);
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorExists('.invalid-feedback');
            self::assertNull(static::getContainer()->get(OfferRepository::class)->findOneBy(['title' => 'Invalid']));
        }
    }

    public function testSlugsCollideSafelyAndCanBeRegenerated(): void
    {
        $this->login('editor');
        for ($i = 0; $i < 2; ++$i) {
            $this->client->request('GET', '/admin/offers/new?company='.$this->firstCompany()->getId());
            $this->client->submitForm('Speichern', ['offer[title]' => 'Schöne Überraschung']);
            self::assertResponseStatusCodeSame(303);
        }
        $first = $this->offer('schoene-ueberraschung');
        $second = $this->offer('schoene-ueberraschung-2');
        self::assertNotSame($first->getId(), $second->getId());
        $this->client->request('GET', '/admin/offers/'.$second->getId().'/edit');
        $this->client->submitForm('Speichern', ['offer[title]' => 'Neuer Titel', 'offer[slug]' => '']);
        self::assertResponseStatusCodeSame(303);
        self::assertSame($second->getId(), $this->offer('neuer-titel')->getId());
    }

    public function testCsrfProtectsCreationAndDeletion(): void
    {
        $this->login('editor');
        $id = $this->offer()->getId();
        $this->client->request('POST', '/admin/offers/'.$id.'/delete', ['_token' => 'bad']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/offers/new?company='.$this->firstCompany()->getId());
        $values = $this->client->getCrawler()->selectButton('Speichern')->form()->getPhpValues();
        $values['offer']['title'] = 'CSRF attempt';
        $values['offer']['_token'] = 'bad';
        $this->client->request('POST', '/admin/offers/new', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertNull(static::getContainer()->get(OfferRepository::class)->findOneBy(['title' => 'CSRF attempt']));
    }

    public function testAdminFiltersIncludeFutureExpiredAndInactiveOffers(): void
    {
        $this->login('editor');
        $this->client->request('GET', '/admin/offers');
        self::assertSelectorTextContains('tbody', 'Saison-Ausblick');
        self::assertSelectorTextContains('tbody', 'Sommer-Rückblick');
        self::assertSelectorTextContains('tbody', 'Pausierte Aktion');
        self::assertSelectorTextContains('tbody', 'Geplant');
        self::assertSelectorTextContains('tbody', 'Abgelaufen');
        $id = $this->firstCompany()->getId();
        $this->client->request('GET', '/admin/offers?company='.$id.'&type=offer&active=1&featured=1&title=Lieblings');
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'Lieblingsstücke');
        $this->client->request('GET', '/admin/offers?active=0');
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'Pausierte Aktion');
    }

    public function testReassignmentPersistsWithoutDeletingOffer(): void
    {
        $this->login('editor');
        $offer = $this->offer();
        $id = $offer->getId();
        $other = $this->company('Beispielcafé Uferpause')->getId();
        $this->client->request('GET', '/admin/offers/'.$id.'/edit');
        $this->client->submitForm('Speichern', ['offer[company]' => $other]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame($other, $this->offer()->getCompany()->getId());
        $this->client->request('GET', '/angebote?unternehmen=beispielcafe-uferpause');
        self::assertContains('Lieblingsstücke zum Aktionspreis', $this->offerTitles());
    }

    public function testOfferImagesReuseSharedStorageAndCleanupPreservesLiveFiles(): void
    {
        $this->login('editor');
        $offer = $this->offer();
        $id = $offer->getId();
        $old = $offer->getImagePath();
        $directory = dirname(__DIR__, 2).'/var/uploads/test_companies/';
        $this->client->request('GET', '/admin/offers/'.$id.'/edit');
        $values = $this->client->getCrawler()->selectButton('Speichern')->form()->getPhpValues();
        $upload = static::getContainer()->get(DirectoryImageFixtures::class)->png([120, 120, 120], 100, 80);
        $this->client->request('POST', '/admin/offers/'.$id.'/edit', $values, ['offer' => ['file' => $upload]]);
        self::assertResponseStatusCodeSame(303);
        $new = $this->offer()->getImagePath();
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $new);
        self::assertNotSame($old, $new);
        self::assertFileDoesNotExist($directory.$old);
        self::assertFileExists($directory.$new);
        touch($directory.$new, time() - 7200);
        self::assertNotContains($new, static::getContainer()->get(CompanyImageStorage::class)->cleanup());
        $this->client->request('GET', '/admin/offers/'.$id.'/image');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('private', $this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->request('GET', '/admin/offers/'.$id.'/edit');
        $this->client->submitForm('Speichern', ['offer[removeImage]' => true]);
        self::assertResponseStatusCodeSame(303);
        self::assertNull($this->offer()->getImagePath());
        self::assertFileDoesNotExist($directory.$new);
    }

    public function testInvalidImageMimeIsRejectedWithoutSaving(): void
    {
        $this->login('editor');
        $id = $this->offer()->getId();
        $path = tempnam(sys_get_temp_dir(), 'offer-bad-');
        $this->temporaryFiles[] = $path;
        file_put_contents($path, '<?php echo "bad";');
        $this->client->request('GET', '/admin/offers/'.$id.'/edit');
        $values = $this->client->getCrawler()->selectButton('Speichern')->form()->getPhpValues();
        $old = $this->offer()->getImagePath();
        $this->client->request('POST', '/admin/offers/'.$id.'/edit', $values, ['offer' => ['file' => new UploadedFile($path, 'image.png', 'image/png', test: true)]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
        self::assertSame($old, $this->offer()->getImagePath());
    }

    public function testDeletingOfferAndCompanyRemovesOfferFiles(): void
    {
        $this->login('admin');
        $offer = $this->offer();
        $directory = dirname(__DIR__, 2).'/var/uploads/test_companies/';
        $file = $offer->getImagePath();
        $this->client->request('GET', '/admin/offers/'.$offer->getId().'/edit');
        $this->client->submitForm('Angebot löschen');
        self::assertResponseStatusCodeSame(303);
        self::assertFileDoesNotExist($directory.$file);
        $cafe = $this->company('Beispielcafé Uferpause');
        $offer = $this->offer('genuss-auszeit');
        $file = $offer->getImagePath();
        $offerId = $offer->getId();
        static::getContainer()->get(CompanyImageStorage::class)->deleteCompany($cafe);
        self::assertFileDoesNotExist($directory.$file);
        self::assertNull(static::getContainer()->get(OfferRepository::class)->find($offerId));
    }
    public function testOversizedImageIsRejectedAndOriginalRemains(): void
    {
        $this->login('editor');
        $offer = $this->offer();
        $id = $offer->getId();
        $old = $offer->getImagePath();
        $upload = static::getContainer()->get(DirectoryImageFixtures::class)->png([10, 20, 30], 10, 10);
        $this->temporaryFiles[] = $upload->getPathname();
        file_put_contents($upload->getPathname(), str_repeat('x', 6 * 1024 * 1024), FILE_APPEND);
        $this->client->request('GET', '/admin/offers/'.$id.'/edit');
        $values = $this->client->getCrawler()->selectButton('Speichern')->form()->getPhpValues();
        $this->client->request('POST', '/admin/offers/'.$id.'/edit', $values, ['offer' => ['file' => $upload]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
        self::assertSame($old, $this->offer()->getImagePath());
    }

    public function testFailedPersistenceRemovesNewUpload(): void
    {
        $directory = dirname(__DIR__, 2).'/var/uploads/test_companies/';
        $before = glob($directory.'*');
        $offer = (new Offer())->setCompany($this->firstCompany())->setTitle('Collision')->setSlug($this->offer()->getSlug());
        $upload = static::getContainer()->get(DirectoryImageFixtures::class)->png([10, 20, 30], 10, 10);
        try {
            static::getContainer()->get(CompanyImageStorage::class)->save($offer, $upload);
            self::fail('Duplicate slug must fail at the DB constraint.');
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            self::assertSame($before, glob($directory.'*'));
            self::assertNull($offer->getImagePath());
        }
    }

    public function testAdminPaginationKeepsFilters(): void
    {
        $company = $this->firstCompany();
        $id = $company->getId();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        for ($i = 0; $i < 28; ++$i) {
            $em->persist((new Offer())->setCompany($company)->setTitle('Adminpage '.$i)->setSlug('adminpage-'.$i));
        }
        $em->flush();
        $this->login('editor');
        $this->client->request('GET', '/admin/offers?title=Adminpage&company='.$id.'&type=offer&active=1&featured=0');
        self::assertSelectorCount(25, 'tbody tr');
        $url = $this->client->getCrawler()->selectLink('Weiter')->link()->getUri();
        parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
        self::assertSame('0', $parameters['featured']);
        self::assertSame('offer', $parameters['type']);
        self::assertSame('Adminpage', $parameters['title']);
        $this->client->request('GET', $url);
        self::assertSelectorCount(3, 'tbody tr');
    }
}

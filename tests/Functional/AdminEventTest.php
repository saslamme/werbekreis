<?php

declare(strict_types=1);
namespace App\Tests\Functional;

use App\DataFixtures\DirectoryImageFixtures;
use App\Entity\{Event, EventCategory};
use App\Enum\EventRecurrence;
use App\Repository\{EventRepository, EventCategoryRepository};
use App\Service\{EventSchedule, DirectorySlugger, CompanyImageStorage};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AdminEventTest extends EventDatabaseTestCase
{
    public static function managers(): iterable { yield ['admin']; yield ['editor']; }
    #[DataProvider('managers')]
    public function testAdminAndEditorEventCrudAndRecurrence(string $role): void
    {
        $this->login($role); $companyId = $this->firstCompany()->getId();
        $this->client->request('GET', '/admin/events/new?company='.$companyId); self::assertResponseIsSuccessful(); self::assertSelectorExists('select[name="event[company]"] option[value="'.$companyId.'"][selected]');
        $this->client->submitForm('Speichern', ['event[title]' => 'Wöchentlicher Beispieltreff', 'event[startsAt]' => '2030-05-01T18:00', 'event[endsAt]' => '2030-05-01T20:00', 'event[recurrenceType]' => 'weekly', 'event[recurrenceUntil]' => '2030-05-22']);
        self::assertResponseStatusCodeSame(303); $event = $this->event('woechentlicher-beispieltreff'); $id = $event->getId(); self::assertCount(4, $event->getOccurrences()); self::assertSame('16:00', $event->getStartsAt()->format('H:i'));
        $ids = $event->getOccurrences()->map(static fn ($o) => $o->getId())->getValues();
        $this->client->request('GET', '/admin/events/'.$id); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('h1', 'Wöchentlicher Beispieltreff');
        $this->client->request('GET', '/admin/events/'.$id.'/edit'); $this->client->submitForm('Speichern', ['event[title]' => 'Neuer Titel', 'event[endsAt]' => '2030-05-01T21:00', 'event[cancelled]' => true, 'event[cancellationNotice]' => 'Absage']); self::assertResponseStatusCodeSame(303);
        $event = $this->event('woechentlicher-beispieltreff'); self::assertSame($ids, $event->getOccurrences()->map(static fn ($o) => $o->getId())->getValues());
        $this->client->request('GET', '/veranstaltungen/woechentlicher-beispieltreff'); self::assertSelectorTextContains('.alert-warning', 'Absage');
        $this->client->request('GET', '/admin/events/'.$id.'/edit'); $this->client->submitForm('Speichern', ['event[active]' => false]); self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/veranstaltungen/woechentlicher-beispieltreff'); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/events/'.$id.'/edit'); $this->client->submitForm('Veranstaltung löschen'); self::assertResponseStatusCodeSame(303); self::assertNull(static::getContainer()->get(EventRepository::class)->find($id));
    }
    #[DataProvider('managers')]
    public function testAdminAndEditorEventCategoryCrudAndAttachedDeletionProtection(string $role): void
    {
        $this->login($role); $this->client->request('GET', '/admin/event-categories/new');
        $this->client->submitForm('Speichern', ['event_category[name]' => 'Neue Märkte', 'event_category[position]' => '10']); self::assertResponseStatusCodeSame(303);
        $em = static::getContainer()->get(EntityManagerInterface::class); $category = $em->getRepository(EventCategory::class)->findOneBy(['slug' => 'neue-maerkte']); self::assertNotNull($category); $id = $category->getId();
        $this->client->request('GET', '/admin/event-categories/'.$id.'/edit'); $this->client->submitForm('Speichern', ['event_category[name]' => 'Neuer Name']); self::assertResponseStatusCodeSame(303);
        $em = static::getContainer()->get(EntityManagerInterface::class); $category = $em->find(EventCategory::class, $id); $event = $this->event(); $event->addCategory($category); $em->flush();
        $this->client->request('GET', '/admin/event-categories/'.$id.'/edit'); $this->client->submitForm('Kategorie löschen'); self::assertResponseStatusCodeSame(303); self::assertNotNull(static::getContainer()->get(EventCategoryRepository::class)->find($id));
        $em = static::getContainer()->get(EntityManagerInterface::class); $this->event()->removeCategory($em->find(EventCategory::class, $id)); $em->flush();
        $this->client->request('GET', '/admin/event-categories/'.$id.'/edit'); $this->client->submitForm('Kategorie löschen'); self::assertResponseStatusCodeSame(303); self::assertNull(static::getContainer()->get(EventCategoryRepository::class)->find($id));
    }
    public function testAnonymousMemberAndCsrfRestrictions(): void
    {
        $id = $this->event()->getId();
        foreach (['/admin/events', '/admin/events/new', '/admin/events/'.$id, '/admin/events/'.$id.'/edit', '/admin/events/'.$id.'/image', '/admin/event-categories', '/admin/event-categories/new'] as $url) { $this->client->request('GET', $url); self::assertResponseRedirects('/login'); }
        $this->login('member'); foreach (['/admin/events', '/admin/events/new', '/admin/events/'.$id.'/edit', '/admin/event-categories', '/admin/event-categories/new'] as $url) { $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403); }
        foreach (['/admin/events/'.$id.'/delete', '/admin/events/new', '/admin/event-categories/new'] as $url) { $this->client->request('POST', $url); self::assertResponseStatusCodeSame(403); }
        $this->login('editor'); $this->client->request('POST', '/admin/events/'.$id.'/delete', ['_token' => 'bad']); self::assertResponseStatusCodeSame(403);
        $categoryId = static::getContainer()->get(EventCategoryRepository::class)->findOneBy(['slug' => 'musik'])->getId(); $this->client->request('POST', '/admin/event-categories/'.$categoryId.'/delete', ['_token' => 'bad']); self::assertResponseStatusCodeSame(403);
    }
    public static function invalidForms(): iterable
    {
        yield [['event[endsAt]' => '2030-04-30T20:00']];
        yield [['event[recurrenceType]' => 'daily', 'event[recurrenceUntil]' => '']];
        yield [['event[recurrenceType]' => 'daily', 'event[recurrenceUntil]' => '2032-05-01']];
        yield [['event[recurrenceType]' => 'weekly', 'event[recurrenceUntil]' => '2030-04-30']];
        yield [['event[organizerEmail]' => 'invalid']]; yield [['event[ticketUrl]' => 'javascript:alert(1)']];
        yield [['event[latitude]' => '91', 'event[longitude]' => '7']]; yield [['event[latitude]' => '52']];
        yield [['event[slug]' => 'beispielabend-heute']];
    }
    #[DataProvider('invalidForms')]
    public function testInvalidDataStaysOnFormWithoutDatabaseErrors(array $changes): void
    {
        $this->login('editor'); $this->client->request('GET', '/admin/events/new');
        $this->client->submitForm('Speichern', array_replace(['event[title]' => 'Ungültiges Event', 'event[startsAt]' => '2030-05-01T18:00', 'event[endsAt]' => '2030-05-01T20:00'], $changes));
        self::assertResponseStatusCodeSame(422); self::assertSelectorExists('.invalid-feedback, .alert-danger'); self::assertNull(static::getContainer()->get(EventRepository::class)->findOneBy(['title' => 'Ungültiges Event']));
    }
    public function testSlugsAndDomainValidation(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $slugs = static::getContainer()->get(DirectorySlugger::class); $schedule = static::getContainer()->get(EventSchedule::class);
        $event = (new Event())->setTitle('Beispielabend heute')->setStartsAt(new \DateTimeImmutable('2030-05-01 12:00Z'))->setEndsAt(new \DateTimeImmutable('2030-05-01 12:00Z'));
        $slugs->assign($event); self::assertSame('beispielabend-heute-2', $event->getSlug()); $schedule->synchronize($event); $em->persist($event); $em->flush();
        $category = (new EventCategory())->setName('Musik'); $slugs->assign($category); self::assertSame('musik-2', $category->getSlug());
        $event->setRecurrenceType(EventRecurrence::Daily)->setRecurrenceUntil(new \DateTimeImmutable('2032-01-01'));
        $errors = static::getContainer()->get('validator')->validate($event); self::assertGreaterThan(0, count($errors));
        $event->setRecurrenceType(EventRecurrence::None)->setEndsAt(new \DateTimeImmutable('2030-04-01')); self::assertGreaterThan(0, count(static::getContainer()->get('validator')->validate($event)));
    }
    public function testAdminFiltersStatusesCategoriesAndDashboard(): void
    {
        $this->login('editor');
        foreach (['past' => 'Vergangener Beispielabend', 'disabled' => 'Inaktive Beispielveranstaltung', 'cancelled' => 'Abgesagtes Beispielkonzert', 'running' => 'Langer offener Nachmittag', 'planned' => 'Familientag morgen'] as $status => $title) {
            $this->client->request('GET', '/admin/events?status='.$status); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('table', $title);
        }
        $this->client->request('GET', '/admin/events?active=0&featured=1'); self::assertSelectorTextContains('table', 'Inaktive Beispielveranstaltung'); self::assertSelectorExists('select[name="active"] option[value="0"][selected]');
        $categoryId = static::getContainer()->get(EventCategoryRepository::class)->findOneBy(['slug' => 'musik'])->getId();
        $this->client->request('GET', '/admin/events?category='.$categoryId.'&period=today&title=Beispielabend'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('table', 'Beispielabend heute'); self::assertSelectorTextNotContains('table', 'Familientag morgen');
        $this->client->request('GET', '/admin'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', 'Kommende Veranstaltungstermine');
    }
    public function testSharedImageReplacementRemovalCleanupAndEventDeletion(): void
    {
        $this->login('editor'); $event = $this->event(); $id = $event->getId(); $storage = static::getContainer()->get(CompanyImageStorage::class); $oldPath = $storage->path($event->getImagePath()); self::assertFileExists($oldPath);
        touch($oldPath, time() - 7200); self::assertNotContains($event->getImagePath(), $storage->cleanup());
        $upload = static::getContainer()->get(DirectoryImageFixtures::class)->png([20, 40, 80], 300, 200);
        $this->client->request('GET', '/admin/events/'.$id.'/edit'); $this->client->submitForm('Speichern', ['event[file]' => $upload]); self::assertResponseStatusCodeSame(303); self::assertFileDoesNotExist($oldPath);
        $event = $this->event(); $path = $storage->path($event->getImagePath()); self::assertFileExists($path);
        $this->client->request('GET', '/admin/events/'.$id.'/edit'); $this->client->submitForm('Speichern', ['event[removeImage]' => true]); self::assertResponseStatusCodeSame(303); self::assertFileDoesNotExist($path); self::assertNull($this->event()->getImagePath());
        $this->client->request('GET', '/admin/events/'.$id.'/edit'); $this->client->submitForm('Speichern', ['event[file]' => static::getContainer()->get(DirectoryImageFixtures::class)->png([1, 2, 3], 100, 100)]);
        $path = $storage->path($this->event()->getImagePath()); $this->client->request('GET', '/admin/events/'.$id.'/edit'); $this->client->submitForm('Veranstaltung löschen'); self::assertResponseStatusCodeSame(303); self::assertFileDoesNotExist($path);
    }
    public function testUnsafeImageUploadRejectedAndCompanyDeletionRetainsIndependentEvent(): void
    {
        $this->login('editor'); $path = tempnam(sys_get_temp_dir(), 'event-test-'); file_put_contents($path, '<?php echo "bad";');
        try {
            $this->client->request('GET', '/admin/events/'.$this->event()->getId().'/edit'); $this->client->submitForm('Speichern', ['event[file]' => new UploadedFile($path, 'bad.png', 'image/png', null, true)]); self::assertResponseStatusCodeSame(422);
        } finally { if (is_file($path)) { unlink($path); } }
        $em = static::getContainer()->get(EntityManagerInterface::class); $event = $this->event(); $company = $event->getCompany(); $id = $event->getId(); $image = $event->getImagePath();
        static::getContainer()->get(CompanyImageStorage::class)->deleteCompany($company); $em->clear(); $event = $em->find(Event::class, $id);
        self::assertNotNull($event); self::assertNull($event->getCompany()); self::assertSame($image, $event->getImagePath()); self::assertNotEmpty($event->getOccurrences());
        self::assertFileExists(static::getContainer()->get(CompanyImageStorage::class)->path($image));
    }
    public function testChangingSeriesTrimsOccurrencesAndKeepsRemainingUrls(): void
    {
        $this->login('editor'); $event = $this->event('woechentliche-ideenrunde'); $id = $event->getId(); $firstId = $event->getOccurrences()->first()->getId(); $lastId = $event->getOccurrences()->last()->getId();
        $this->client->request('GET', '/admin/events/'.$id.'/edit'); $this->client->submitForm('Speichern', ['event[recurrenceUntil]' => '2030-05-08']); self::assertResponseStatusCodeSame(303);
        $event = $this->event('woechentliche-ideenrunde'); self::assertCount(3, $event->getOccurrences()); self::assertSame($firstId, $event->getOccurrences()->first()->getId());
        $this->client->request('GET', '/veranstaltungen/woechentliche-ideenrunde/termine/'.$lastId); self::assertResponseStatusCodeSame(404);
    }
    public function testAdminPaginationRetainsFiltersAcrossCollectionJoins(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $schedule = static::getContainer()->get(EventSchedule::class);
        for ($i = 0; $i < 27; ++$i) { $event = (new Event())->setTitle('Pagination '.$i)->setSlug('pagination-'.$i)->setActive(false)->setStartsAt(new \DateTimeImmutable('2030-05-01'))->setEndsAt(new \DateTimeImmutable('2030-05-02')); $schedule->synchronize($event); $em->persist($event); }
        $em->flush(); $this->login('editor'); $this->client->request('GET', '/admin/events?title=Pagination&active=0'); self::assertResponseIsSuccessful(); self::assertCount(25, $this->client->getCrawler()->filter('tbody tr'));
        self::assertSelectorExists('a[href="/admin/events?title=Pagination&active=0&page=2"]'); $this->client->request('GET', '/admin/events?title=Pagination&active=0&page=2'); self::assertCount(2, $this->client->getCrawler()->filter('tbody tr'));
    }
}

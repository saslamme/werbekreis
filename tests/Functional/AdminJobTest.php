<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\JobPosting;
use App\Enum\NewsStatus;
use App\Repository\JobPostingRepository;
use App\Service\{CompanyImageStorage, DirectorySlugger};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminJobTest extends JobDatabaseTestCase
{
    public static function managers(): iterable { yield ['admin']; yield ['editor']; }
    #[DataProvider('managers')]
    public function testManagersCreateEditPublishScheduleDraftAndDelete(string $role): void
    {
        $this->login($role); $company = $this->firstCompany(); $this->client->request('GET', '/admin/jobs/new?company='.$company->getId()); self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="job_posting[company]"] option[value="'.$company->getId().'"][selected]'); self::assertSelectorExists('input[name="job_posting[city]"][value="Haselünne"]');
        $this->client->submitForm('Speichern', ['job_posting[title]' => 'Neue Stelle ohne Titelzusatz', 'job_posting[shortDescription]' => 'Kurzer Text', 'job_posting[description]' => 'Beschreibung', 'job_posting[applicationEmail]' => 'bewerbung@example.org']); self::assertResponseStatusCodeSame(303);
        $job = $this->job('neue-stelle-ohne-titelzusatz'); $id = $job->getId(); self::assertSame(NewsStatus::Draft, $job->getStatus()); self::assertSame('Neue Stelle ohne Titelzusatz', $job->getTitle());
        $this->client->request('GET', '/admin/jobs/'.$id); self::assertResponseIsSuccessful();
        $this->client->request('GET', '/jobs/neue-stelle-ohne-titelzusatz'); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/jobs/'.$id.'/edit'); $this->client->submitForm('Speichern', ['job_posting[status]' => 'published', 'job_posting[salaryMin]' => '2500,50', 'job_posting[salaryMax]' => '3200', 'job_posting[salaryPeriod]' => 'monthly', 'job_posting[validThrough]' => '2030-05-03']); self::assertResponseStatusCodeSame(303);
        $job = $this->job('neue-stelle-ohne-titelzusatz'); self::assertEquals($this->clock->now(), $job->getPublishedAt()); self::assertSame('2500.50', $job->getSalaryMin()); self::assertSame('2030-05-03 21:59:59', $job->getValidThrough()->format('Y-m-d H:i:s'));
        $this->client->request('GET', '/jobs/neue-stelle-ohne-titelzusatz'); self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/jobs/'.$id.'/edit'); $this->client->submitForm('Speichern', ['job_posting[title]' => 'Geänderter Titel', 'job_posting[status]' => 'scheduled', 'job_posting[publishedAt]' => '2030-05-02T18:00']); self::assertResponseStatusCodeSame(303);
        self::assertSame('16:00', $this->job('neue-stelle-ohne-titelzusatz')->getPublishedAt()->format('H:i')); $this->client->request('GET', '/jobs/neue-stelle-ohne-titelzusatz'); self::assertResponseStatusCodeSame(404);
        $this->clock->modify('+2 days'); $this->client->request('GET', '/admin/jobs/'.$id.'/edit'); $this->client->submitForm('Speichern', ['job_posting[description]' => 'Weiter bearbeitet']); self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/jobs/neue-stelle-ohne-titelzusatz'); self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/jobs/'.$id.'/edit'); $this->client->submitForm('Speichern', ['job_posting[status]' => 'draft']); self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/jobs/neue-stelle-ohne-titelzusatz'); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/jobs/'.$id.'/edit'); $this->client->submitForm('Stelle löschen'); self::assertResponseStatusCodeSame(303); self::assertNull(static::getContainer()->get(JobPostingRepository::class)->find($id));
    }
    public static function invalidForms(): iterable
    {
        yield [['job_posting[title]' => '']]; yield [['job_posting[company]' => '']]; yield [['job_posting[city]' => '']];
        yield [['job_posting[description]' => '']]; yield [['job_posting[shortDescription]' => str_repeat('a', 501)]];
        yield [['job_posting[applicationEmail]' => 'wrong']]; yield [['job_posting[applicationUrl]' => 'javascript:alert(1)']];
        yield [['job_posting[salaryMin]' => '-1']]; yield [['job_posting[salaryMax]' => '-1']]; yield [['job_posting[salaryMin]' => '1,001']];
        yield [['job_posting[salaryMin]' => '3000', 'job_posting[salaryMax]' => '2000', 'job_posting[salaryPeriod]' => 'monthly']];
        yield [['job_posting[salaryMin]' => '10', 'job_posting[salaryPeriod]' => '']];
        yield [['job_posting[status]' => 'scheduled', 'job_posting[publishedAt]' => '']];
        yield [['job_posting[status]' => 'scheduled', 'job_posting[publishedAt]' => '2030-04-01T18:00']];
        yield [['job_posting[publishedAt]' => '2030-05-02T18:00', 'job_posting[validThrough]' => '2030-05-01']];
        yield [['job_posting[status]' => 'published', 'job_posting[publishedAt]' => '', 'job_posting[validThrough]' => '2030-04-30']];
        yield [['job_posting[latitude]' => '91', 'job_posting[longitude]' => '7']]; yield [['job_posting[latitude]' => '52', 'job_posting[longitude]' => '181']];
        yield [['job_posting[latitude]' => '52', 'job_posting[longitude]' => '']]; yield [['job_posting[slug]' => 'verkaeufer-im-einzelhandel-m-w-d']];
    }
    #[DataProvider('invalidForms')]
    public function testInvalidInputsProduceFormErrors(array $changes): void
    {
        $this->login('editor'); $this->client->request('GET', '/admin/jobs/new?company='.$this->firstCompany()->getId());
        $this->client->submitForm('Speichern', array_replace(['job_posting[title]' => 'Neue Stelle', 'job_posting[shortDescription]' => 'Kurztext', 'job_posting[description]' => 'Text', 'job_posting[applicationEmail]' => 'job@example.org'], $changes));
        self::assertResponseStatusCodeSame(422); self::assertSelectorExists('.invalid-feedback, .alert-danger');
    }
    public function testSecurityCsrfAndExpiredAdminVisibility(): void
    {
        $id = $this->job('abgelaufene-beispielstelle')->getId(); $urls = ['/admin/jobs', '/admin/jobs/new', '/admin/jobs/'.$id, '/admin/jobs/'.$id.'/edit'];
        foreach ($urls as $url) { $this->client->request('GET', $url); self::assertResponseRedirects('/login'); }
        $this->login('member'); foreach ($urls as $url) { $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403); }
        $this->client->request('POST', '/admin/jobs/'.$id.'/delete'); self::assertResponseStatusCodeSame(403);
        $this->login('editor'); $this->client->request('POST', '/admin/jobs/'.$id.'/delete', ['_token' => 'bad']); self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/jobs/'.$id); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', 'Abgelaufen');
        $this->client->request('GET', '/admin/jobs/new'); $form = $this->client->getCrawler()->selectButton('Speichern')->form(); $values = $form->getPhpValues(); $values['job_posting']['_token'] = 'bad'; $this->client->request('POST', '/admin/jobs/new', $values); self::assertResponseStatusCodeSame(422);
    }
    public function testElapsedScheduleRetainsSecondsAndChangedPastScheduleFails(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $job = $this->job('automatisch-offene-stelle'); $date = $job->getPublishedAt()->modify('+37 seconds'); $job->setPublishedAt($date); $id = $job->getId(); $em->flush();
        $this->login('editor'); $this->client->request('GET', '/admin/jobs/'.$id.'/edit'); $this->client->submitForm('Speichern', ['job_posting[description]' => 'Edit']); self::assertResponseStatusCodeSame(303); self::assertEquals($date, $this->job('automatisch-offene-stelle')->getPublishedAt());
        $this->client->request('GET', '/admin/jobs/'.$id.'/edit'); $this->client->submitForm('Speichern', ['job_posting[publishedAt]' => '2030-04-01T18:00']); self::assertResponseStatusCodeSame(422);
    }
    public static function deadlines(): iterable
    {
        yield ['2030-03-31', '2030-03-31 21:59:59']; yield ['2030-10-27', '2030-10-27 22:59:59'];
    }
    #[DataProvider('deadlines')]
    public function testDeadlineIncludesWholeLocalDayAcrossDstAndRoundTrips(string $localDay, string $utcEnd): void
    {
        $this->login('editor'); $id = $this->job()->getId(); $this->client->request('GET', '/admin/jobs/'.$id.'/edit');
        $this->client->submitForm('Speichern', ['job_posting[status]' => 'draft', 'job_posting[publishedAt]' => '', 'job_posting[validThrough]' => $localDay]); self::assertResponseStatusCodeSame(303); self::assertSame($utcEnd, $this->job()->getValidThrough()->format('Y-m-d H:i:s'));
        $this->client->request('GET', '/admin/jobs/'.$id.'/edit'); self::assertSelectorExists('input[name="job_posting[validThrough]"][value="'.$localDay.'"]'); $this->client->submitForm('Speichern', ['job_posting[description]' => 'Unchanged deadline']); self::assertResponseStatusCodeSame(303); self::assertSame($utcEnd, $this->job()->getValidThrough()->format('Y-m-d H:i:s'));
    }
    public function testRemoteAndCompanyEmailFallbackAreAllowed(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $this->firstCompany()->setEmail('company@example.org'); $em->flush(); $this->login('editor');
        $this->client->request('GET', '/admin/jobs/new?company='.$this->firstCompany()->getId()); $this->client->submitForm('Speichern', ['job_posting[title]' => 'Remote Beispiel', 'job_posting[shortDescription]' => 'Kurztext', 'job_posting[description]' => 'Text', 'job_posting[workModel]' => 'remote', 'job_posting[city]' => '', 'job_posting[latitude]' => '', 'job_posting[longitude]' => '']); self::assertResponseStatusCodeSame(303); self::assertSame('company@example.org', $this->job('remote-beispiel')->getEffectiveApplicationEmail());
        $em = static::getContainer()->get(EntityManagerInterface::class); $this->firstCompany()->setEmail(null); $em->flush();
        $this->client->request('GET', '/admin/jobs/new?company='.$this->firstCompany()->getId()); $this->client->submitForm('Speichern', ['job_posting[title]' => 'Ohne Kontakt', 'job_posting[shortDescription]' => 'Kurztext', 'job_posting[description]' => 'Text']); self::assertResponseStatusCodeSame(422);
    }
    public function testFiltersDashboardSlugCollisionsAndCompanyReassignment(): void
    {
        $this->login('editor'); $this->client->request('GET', '/admin/jobs?title=Praktikum&employmentType=internship&workModel=hybrid&featured=0&company='.$this->firstCompany()->getId()); self::assertResponseIsSuccessful(); self::assertCount(1, $this->client->getCrawler()->filter('tbody tr')); self::assertSelectorTextContains('table', 'Praktikum im Stadtbüro'); self::assertSelectorExists('select[name="featured"] option[value="0"][selected]'); self::assertSelectorExists('nav[aria-label="Adminnavigation"] a[href="/admin/jobs"]');
        $this->client->request('GET', '/admin'); self::assertSelectorTextContains('body', 'Offene Stellen'); self::assertSelectorTextContains('body', 'Bald auslaufende Stellen');
        $new = (new JobPosting())->setTitle('Verkäufer im Einzelhandel (m/w/d)'); static::getContainer()->get(DirectorySlugger::class)->assign($new); self::assertSame('verkaeufer-im-einzelhandel-m-w-d-2', $new->getSlug());
        $id = $this->job()->getId(); $company = $this->company('Beispielcafé Uferpause'); $this->client->request('GET', '/admin/jobs/'.$id.'/edit'); $this->client->submitForm('Speichern', ['job_posting[company]' => (string) $company->getId(), 'job_posting[city]' => 'Meppen']); self::assertResponseStatusCodeSame(303);
        self::assertSame('Beispielcafé Uferpause', $this->job()->getCompany()->getName()); self::assertSame('Meppen', $this->job()->getCity());
    }
    public function testCompanyDeletionCascadesRequiredJobs(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $id = $this->job()->getId(); static::getContainer()->get(CompanyImageStorage::class)->deleteCompany($this->firstCompany()); $em->clear(); self::assertNull($em->find(JobPosting::class, $id));
    }
    public function testAdminPaginationKeepsAllFilters(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $company = $this->firstCompany();
        for ($i = 0; $i < 27; ++$i) { $job = (new JobPosting($this->clock->now()))->setCompany($company)->setTitle('Pagination '.$i)->setSlug('pagination-'.$i)->setShortDescription('Text')->setDescription('Text'); $em->persist($job); } $em->flush();
        $this->login('editor'); $this->client->request('GET', '/admin/jobs?title=Pagination&featured=0'); self::assertCount(25, $this->client->getCrawler()->filter('tbody tr')); self::assertSelectorExists('a[href="/admin/jobs?title=Pagination&featured=0&page=2"]');
        $this->client->request('GET', '/admin/jobs?title=Pagination&featured=0&page=2'); self::assertCount(2, $this->client->getCrawler()->filter('tbody tr'));
    }
}

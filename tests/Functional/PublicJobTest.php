<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\JobPosting;
use App\Enum\{EmploymentType, WorkModel, NewsStatus};
use App\Repository\JobPostingRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicJobTest extends JobDatabaseTestCase
{
    public function testOverviewListsCurrentJobsWithGermanLabelsAndRealNavigation(): void
    {
        $this->client->request('GET', '/jobs'); self::assertResponseIsSuccessful(); self::assertCount(8, $this->client->getCrawler()->filter('.job-card'));
        self::assertSame(['Verkäufer im Einzelhandel (m/w/d)', 'Service im Beispielcafé', 'Ausbildung im Beispielhandel'], array_slice($this->jobTitles(), 0, 3));
        foreach (['Entwurf einer Stelle', 'Geplante Stelle', 'Abgelaufene Beispielstelle', 'Zukünftige Veröffentlichung'] as $title) { self::assertNotContains($title, $this->jobTitles()); }
        self::assertSelectorTextContains('title', 'Jobs in Haselünne | Stellenangebote beim Werbekreis Haselünne'); self::assertSelectorExists('link[rel="canonical"][href="http://localhost/jobs"]');
        self::assertSelectorTextContains('body', 'Vollzeit'); self::assertSelectorTextContains('body', 'Hybrid'); self::assertSelectorExists('header a[href="/jobs"]'); self::assertSelectorExists('footer a[href="/jobs"]'); self::assertSelectorNotExists('a[href="/#jobs"]');
    }
    public static function details(): iterable
    {
        yield ['verkaeufer-im-einzelhandel-m-w-d', 200]; yield ['automatisch-offene-stelle', 200]; yield ['werkstudent-digitale-beispiele', 200];
        yield ['entwurf-einer-stelle', 404]; yield ['geplante-stelle', 404]; yield ['abgelaufene-beispielstelle', 404]; yield ['zukuenftige-veroeffentlichung', 404]; yield ['unknown', 404];
    }
    #[DataProvider('details')]
    public function testDetailPublicationAndExpiryPrivacy(string $slug, int $status): void
    {
        $this->client->request('GET', '/jobs/'.$slug); self::assertResponseStatusCodeSame($status);
    }
    public static function searches(): iterable
    {
        yield ['Einzelhandel', 'Verkäufer im Einzelhandel (m/w/d)'];
        yield ['Unternehmenstitel', 'Verkäufer im Einzelhandel (m/w/d)'];
        yield ['Suchkurztext', 'Verkäufer im Einzelhandel (m/w/d)'];
        yield ['Suchvolltext', 'Verkäufer im Einzelhandel (m/w/d)'];
        yield ['Meppen', 'Praktikum im Stadtbüro'];
    }
    #[DataProvider('searches')]
    public function testSearchCoversTitleShortDescriptionDescriptionCompanyAndCity(string $search, string $expected): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $job = $this->job();
        $job->setShortDescription('Suchkurztext')->setDescription('Suchvolltext'); $job->getCompany()->setName('Unternehmenstitel'); $em->flush();
        $this->client->request('GET', '/jobs?q='.rawurlencode($search)); self::assertResponseIsSuccessful(); self::assertContains($expected, $this->jobTitles());
        self::assertSelectorExists('meta[name="robots"][content="noindex,follow"]');
    }
    public function testCombinedFiltersAndEmptyState(): void
    {
        $this->client->request('GET', '/jobs?employmentType=internship&workModel=hybrid&unternehmen=musterladen-hasebogen&q=Meppen'); self::assertResponseIsSuccessful();
        self::assertSame(['Praktikum im Stadtbüro'], $this->jobTitles());
        foreach (['employmentType' => 'internship', 'workModel' => 'hybrid', 'unternehmen' => 'musterladen-hasebogen'] as $key => $value) { self::assertSelectorExists('select[name="'.$key.'"] option[value="'.$value.'"][selected]'); }
        $this->client->request('GET', '/jobs?q=keine-passende-stelle'); self::assertSelectorTextContains('[role="status"]', 'keine passenden Stellenangebote'); self::assertSelectorExists('a[href="/jobs"]');
        foreach (['/jobs?employmentType=wrong', '/jobs?workModel=wrong', '/jobs?unternehmen=beispielhotel-lindenhof', '/jobs?page=999'] as $url) { $this->client->request('GET', $url); self::assertResponseStatusCodeSame(404); }
        $this->client->request('GET', '/jobs?q[]=bad&employmentType[]=bad&page[]=bad'); self::assertResponseIsSuccessful();
        $this->client->request('GET', '/jobs?q=%25'); self::assertSame([], $this->jobTitles());
    }
    public function testHomepageCompanyAndFeaturedFallbackExcludeInvisibleJobs(): void
    {
        $this->client->request('GET', '/'); self::assertResponseIsSuccessful(); self::assertCount(3, $this->client->getCrawler()->filter('#jobs .job-card')); self::assertSelectorExists('#jobs a[href="/jobs"]');
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen'); self::assertResponseIsSuccessful(); self::assertCount(3, $this->client->getCrawler()->filter('#offene-stellen .job-card')); self::assertSelectorExists('a[href="/jobs?unternehmen=musterladen-hasebogen"]'); self::assertNotContains('Service im Beispielcafé', $this->jobTitles()); self::assertNotContains('Abgelaufene Beispielstelle', $this->jobTitles());
        $this->client->request('GET', '/unternehmen/demo-werkstatt-stadtblick'); self::assertSelectorNotExists('#offene-stellen');
        $em = static::getContainer()->get(EntityManagerInterface::class); $em->createQuery('UPDATE App\Entity\JobPosting job SET job.featured = false')->execute();
        $this->client->request('GET', '/'); self::assertCount(3, $this->client->getCrawler()->filter('#jobs .job-card')); self::assertSame('Automatisch offene Stelle', $this->jobTitles()[0]);
        $em = static::getContainer()->get(EntityManagerInterface::class); $em->createQuery("UPDATE App\Entity\JobPosting job SET job.status = 'draft'")->execute();
        $this->client->request('GET', '/'); self::assertSelectorTextContains('#jobs', 'keine offenen Stellen'); $this->client->request('GET', '/unternehmen/musterladen-hasebogen'); self::assertSelectorNotExists('#offene-stellen');
    }
    public function testClockPublishesAndExpiresAtExactBoundariesInEveryPublicQuery(): void
    {
        $job = $this->job('geplante-stelle'); $date = $job->getPublishedAt(); $this->clock->modify('+3 days'); self::assertEquals($date, $this->clock->now());
        $this->client->request('GET', '/jobs/geplante-stelle'); self::assertResponseIsSuccessful(); self::assertSame(NewsStatus::Scheduled, $this->job('geplante-stelle')->getStatus());
        $this->client->request('GET', '/jobs/automatisch-offene-stelle'); self::assertResponseIsSuccessful();
        $this->clock->modify('+1 second'); $this->client->request('GET', '/jobs/automatisch-offene-stelle'); self::assertResponseStatusCodeSame(404);
        $repo = static::getContainer()->get(JobPostingRepository::class); self::assertNotContains('Automatisch offene Stelle', array_column($repo->findForCompany($this->company('Beispielcafé Uferpause'), 20), 'title'));
    }
    public function testInactiveCompanyHidesJobs(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $this->firstCompany()->setActive(false); $em->flush();
        $this->client->request('GET', '/jobs/verkaeufer-im-einzelhandel-m-w-d'); self::assertResponseStatusCodeSame(404); $this->client->request('GET', '/jobs'); self::assertCount(3, $this->client->getCrawler()->filter('.job-card'));
    }
    public function testSeoJsonLdSalaryApplicationsAndLocationUseStoredFacts(): void
    {
        $this->loadImages(); $this->client->request('GET', '/jobs/verkaeufer-im-einzelhandel-m-w-d'); self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'Verkäufer im Einzelhandel (m/w/d) bei Musterladen Hasebogen | Jobs in Haselünne');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/jobs/verkaeufer-im-einzelhandel-m-w-d"]'); self::assertSelectorExists('meta[property="og:image"]');
        self::assertSelectorExists('a[href="https://example.org/bewerbung"][rel="noopener noreferrer"]'); self::assertSelectorTextContains('.job-salary', '2.500,00–3.200,00 € pro Monat');
        $data = $this->jsonLd(); self::assertSame('JobPosting', $data['@type']); self::assertSame('Verkäufer im Einzelhandel (m/w/d)', $data['title']); self::assertSame('Musterladen Hasebogen', $data['hiringOrganization']['name']); self::assertSame('FULL_TIME', $data['employmentType']);
        self::assertSame('2030-04-30T12:00:00+00:00', $data['datePosted']); self::assertSame('2030-05-31T12:00:00+00:00', $data['validThrough']); self::assertSame('Haselünne', $data['jobLocation']['address']['addressLocality']); self::assertSame('DE', $data['jobLocation']['address']['addressCountry']); self::assertSame('MONTH', $data['baseSalary']['value']['unitText']); self::assertEquals(2500, $data['baseSalary']['value']['minValue']); self::assertArrayNotHasKey('jobLocationType', $data); self::assertArrayNotHasKey('applicantLocationRequirements', $data);
        $this->client->request('GET', '/jobs/service-im-beispielcafe'); self::assertSelectorExists('a[href="mailto:bewerbung@example.org"]'); self::assertArrayNotHasKey('maxValue', $this->jsonLd()['baseSalary']['value']);
        $this->client->request('GET', '/jobs/werkstudent-digitale-beispiele'); $data = $this->jsonLd(); self::assertSame('TELECOMMUTE', $data['jobLocationType']); self::assertSame('PART_TIME', $data['employmentType']);
        foreach (['validThrough', 'baseSalary', 'jobLocation', 'applicantLocationRequirements'] as $key) { self::assertArrayNotHasKey($key, $data); }
        self::assertSelectorNotExists('[data-company-map]');
    }
    public function testStoredCoordinatesReuseConsentMapAndNoCoordinatesRenderNoEmptyMap(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $this->job()->setLatitude(52.67)->setLongitude(7.48); $em->flush();
        $this->client->request('GET', '/jobs/verkaeufer-im-einzelhandel-m-w-d'); self::assertSelectorExists('[data-company-map] [data-map-load]');
        $data = json_decode($this->client->getCrawler()->filter('[data-company-map]')->attr('data-map'), true, flags: JSON_THROW_ON_ERROR); self::assertSame('/jobs/verkaeufer-im-einzelhandel-m-w-d', $data['markers'][0]['url']); self::assertSame(['Vollzeit'], $data['markers'][0]['categories']);
        $this->client->request('GET', '/jobs/praktikum-im-stadtbuero'); self::assertSelectorNotExists('[data-company-map]'); self::assertSelectorTextContains('address', 'Meppen');
    }
    public function testHtmlAndJsonLdRemainSafe(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $this->job()->setTitle('</script><script>alert(1)</script>')->setDescription('<img src=x onerror=alert(2)>')->setRequirements('<script>alert(3)</script>'); $em->flush();
        $this->client->request('GET', '/jobs/verkaeufer-im-einzelhandel-m-w-d'); self::assertResponseIsSuccessful(); self::assertSelectorNotExists('.job-content img, .job-content script');
        self::assertCount(1, $this->client->getCrawler()->filter('script[type="application/ld+json"]')); self::assertSelectorTextContains('.job-content', '<img src=x onerror=alert(2)>');
        $data = $this->jsonLd(); self::assertSame('</script><script>alert(1)</script>', $data['title']); self::assertStringNotContainsString('<img src=x', $data['description']);
    }
    public function testRepositoryCardsPaginationAndCounts(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $em->clear(); $repo = static::getContainer()->get(JobPostingRepository::class);
        self::assertSame(8, $repo->countPublic()); self::assertSame(2, $repo->countUpcoming()); self::assertSame(3, $repo->countExpiring());
        foreach ($repo->findLatestPublic() as $row) { self::assertArrayNotHasKey('description', $row); self::assertArrayNotHasKey('requirements', $row); self::assertInstanceOf(EmploymentType::class, $row['employmentType']); }
        self::assertSame([], $em->getUnitOfWork()->getIdentityMap());
        $company = $this->firstCompany();
        for ($i = 0; $i < 15; ++$i) { $job = (new JobPosting($this->clock->now()))->setCompany($company)->setTitle('Pagination '.$i)->setSlug('pagination-'.$i)->setShortDescription('Pagination')->setDescription('Text')->setCity('Haselünne')->setStatus(NewsStatus::Published)->setEmploymentType(EmploymentType::Internship)->setWorkModel(WorkModel::Hybrid); $em->persist($job); }
        $em->flush(); $url = '/jobs?q=Pagination&employmentType=internship&workModel=hybrid&unternehmen=musterladen-hasebogen';
        $this->client->request('GET', $url); self::assertResponseIsSuccessful(); self::assertCount(12, $this->client->getCrawler()->filter('.job-card')); $first = $this->jobTitles(); self::assertSelectorExists('a[href="'.$url.'&page=2"]');
        $this->client->request('GET', $url.'&page=2'); self::assertCount(3, $this->client->getCrawler()->filter('.job-card')); self::assertSame([], array_intersect($first, $this->jobTitles()));
    }
    private function jsonLd(): array
    {
        return json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true, flags: JSON_THROW_ON_ERROR);
    }
}

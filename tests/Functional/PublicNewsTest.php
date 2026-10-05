<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\{NewsArticle, NewsCategory};
use App\Enum\NewsStatus;
use App\Repository\{NewsArticleRepository, NewsCategoryRepository};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicNewsTest extends NewsDatabaseTestCase
{
    public function testOverviewPublicationChronologyCategoryLabelsAndFallbacks(): void
    {
        $this->client->request('GET', '/aktuelles'); self::assertResponseIsSuccessful(); self::assertCount(9, $this->client->getCrawler()->filter('.news-card'));
        $titles = $this->newsTitles(); self::assertSame('Neuigkeit ohne Datumsangabe', $titles[0]); self::assertSame('Automatisch veröffentlichter Rückblick', $titles[1]);
        foreach (['Entwurf einer Neuigkeit', 'Geplanter Stadtblick', 'Veröffentlichung in der Zukunft'] as $title) { self::assertNotContains($title, $titles); }
        $times = $this->client->getCrawler()->filter('.news-meta time')->each(static fn ($node) => strtotime($node->attr('datetime'))); $sorted = $times; rsort($sorted); self::assertSame($sorted, $times);
        self::assertSelectorTextContains('title', 'Aktuelles aus Haselünne'); self::assertSelectorExists('link[rel="canonical"][href="http://localhost/aktuelles"]');
        self::assertSelectorNotExists('option[value="fiktive-inaktive-news-kategorie"]'); self::assertSelectorTextNotContains('.news-categories', 'Fiktive inaktive'); self::assertSelectorExists('.news-card-fallback');
    }
    public static function details(): iterable
    {
        yield ['gemeinsam-vor-ort', 200]; yield ['entwurf-einer-neuigkeit', 404]; yield ['geplanter-stadtblick', 404];
        yield ['veroeffentlichung-in-der-zukunft', 404]; yield ['automatisch-veroeffentlichter-rueckblick', 200]; yield ['neuigkeit-ohne-datumsangabe', 200]; yield ['aus-unserem-archiv', 200]; yield ['unbekannt', 404];
    }
    #[DataProvider('details')]
    public function testDetailVisibility(string $slug, int $status): void
    {
        $this->client->request('GET', '/aktuelles/'.$slug); self::assertResponseStatusCodeSame($status);
    }
    public function testCategoryCompanyAndCombinedFiltersPreserveState(): void
    {
        $this->client->request('GET', '/aktuelles?kategorie=handel&unternehmen=musterladen-hasebogen'); self::assertResponseIsSuccessful(); self::assertCount(4, $this->client->getCrawler()->filter('.news-card'));
        self::assertSelectorExists('select[name="kategorie"] option[value="handel"][selected]'); self::assertSelectorExists('input[name="unternehmen"][value="musterladen-hasebogen"]'); self::assertSelectorExists('meta[name="robots"][content="noindex,follow"]');
        $this->client->request('GET', '/aktuelles?kategorie=werbekreis'); self::assertSame(['Automatisch veröffentlichter Rückblick', 'Gemeinsam vor Ort'], $this->newsTitles());
        foreach (['/aktuelles?kategorie=unbekannt', '/aktuelles?kategorie=fiktive-inaktive-news-kategorie', '/aktuelles?unternehmen=beispielhotel-lindenhof'] as $url) { $this->client->request('GET', $url); self::assertResponseStatusCodeSame(404); }
        $this->client->request('GET', '/aktuelles?kategorie[]=bad&unternehmen[]=bad&page[]=bad'); self::assertResponseIsSuccessful();
    }
    public function testHomepageAndCompanyIntegrationExcludeHiddenNews(): void
    {
        $this->client->request('GET', '/'); self::assertResponseIsSuccessful(); self::assertCount(3, $this->client->getCrawler()->filter('#aktuelles .news-card'));
        self::assertSame(['Neuigkeit ohne Datumsangabe', 'Gemeinsam vor Ort', 'Neues aus dem Musterladen'], $this->newsTitles());
        self::assertSelectorExists('header a[href="/aktuelles"]'); self::assertSelectorExists('footer a[href="/aktuelles"]');
        $this->client->request('GET', '/unternehmen/musterladen-hasebogen'); self::assertResponseIsSuccessful(); self::assertCount(3, $this->client->getCrawler()->filter('.news-card')); self::assertSelectorTextContains('#news-heading', 'Aktuelles von Musterladen Hasebogen');
        self::assertSelectorExists('a[href="/aktuelles?unternehmen=musterladen-hasebogen"]'); self::assertNotContains('Fiktive Café-Geschichten', $this->newsTitles()); self::assertNotContains('Entwurf einer Neuigkeit', $this->newsTitles());
        $this->client->request('GET', '/unternehmen/demo-werkstatt-stadtblick'); self::assertSelectorNotExists('#news-heading');
    }
    public function testSeoOpenGraphStructuredDataUsesStoredFactsOnly(): void
    {
        $this->client->request('GET', '/aktuelles/gemeinsam-vor-ort'); self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'Gemeinsam vor Ort | Werbekreis Haselünne'); self::assertSelectorExists('link[rel="canonical"][href="http://localhost/aktuelles/gemeinsam-vor-ort"]'); self::assertSelectorExists('meta[property="og:type"][content="article"]'); self::assertSelectorExists('meta[property="og:image"]');
        $data = json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('NewsArticle', $data['@type']); self::assertSame('Gemeinsam vor Ort', $data['headline']); self::assertSame('Fiktive Redaktion', $data['author']['name']); self::assertSame('Werbekreis Haselünne', $data['publisher']['name']); self::assertSame('2030-04-30T12:00:00+00:00', $data['datePublished']); self::assertSame('2030-05-01T12:00:00+00:00', $data['dateModified']); self::assertSame('http://localhost/aktuelles/gemeinsam-vor-ort', $data['mainEntityOfPage']['@id']);
        $this->client->request('GET', '/aktuelles/fiktive-cafe-geschichten'); $data = json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true, flags: JSON_THROW_ON_ERROR); self::assertArrayNotHasKey('author', $data); self::assertArrayNotHasKey('image', $data);
    }
    public function testHtmlContentAndJsonLdCannotExecuteScripts(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $article = $this->article();
        $article->setTitle('Titel <script>alert(1)</script>')->setTeaser('</script><script>alert(2)</script>')->setContent('<img src=x onerror=alert(3)>'."\n".'<script>alert(4)</script>'); $em->flush();
        $this->client->request('GET', '/aktuelles/gemeinsam-vor-ort'); self::assertResponseIsSuccessful(); self::assertSelectorNotExists('.news-article-content img, .news-article-content script'); self::assertSelectorTextContains('.news-article-content', '<img src=x onerror=alert(3)>');
        self::assertCount(1, $this->client->getCrawler()->filter('script[type="application/ld+json"]')); self::assertStringNotContainsString('<script>alert(2)</script>', $this->client->getResponse()->getContent());
        $data = json_decode($this->client->getCrawler()->filter('script[type="application/ld+json"]')->text(), true, flags: JSON_THROW_ON_ERROR); self::assertSame('</script><script>alert(2)</script>', $data['description']);
    }
    public function testImagesFollowPublicationEligibilityAndSecureHeaders(): void
    {
        $article = $this->article(); $path = '/aktuelles/'.$article->getSlug().'/bilder/'.$article->getImagePath();
        $this->client->request('GET', $path); self::assertResponseIsSuccessful(); self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff'); self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        $em = static::getContainer()->get(EntityManagerInterface::class); $this->article()->setStatus(NewsStatus::Draft); $em->flush(); $this->client->request('GET', $path); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/aktuelles/neues-aus-dem-musterladen/bilder/'.basename($path)); self::assertResponseStatusCodeSame(404);
    }
    public function testClockAdvancesScheduledAndFuturePublishedAtExactBoundary(): void
    {
        $this->clock->modify('+2 days'); $this->client->request('GET', '/aktuelles/veroeffentlichung-in-der-zukunft'); self::assertResponseIsSuccessful();
        $this->client->request('GET', '/aktuelles/geplanter-stadtblick'); self::assertResponseStatusCodeSame(404);
        $this->clock->modify('+1 day'); $this->client->request('GET', '/aktuelles/geplanter-stadtblick'); self::assertResponseIsSuccessful(); self::assertSame(NewsStatus::Scheduled, $this->article('geplanter-stadtblick')->getStatus());
        $this->client->request('GET', '/aktuelles'); self::assertContains('Geplanter Stadtblick', $this->newsTitles());
    }
    public function testRepositoryCardsOmitContentAndManagedCompanyGraphs(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $em->clear(); $news = static::getContainer()->get(NewsArticleRepository::class);
        $rows = $news->findLatestPublic(); self::assertCount(3, $rows); foreach ($rows as $row) { self::assertArrayNotHasKey('content', $row); self::assertArrayHasKey('publicCategories', $row); }
        self::assertSame([], $em->getUnitOfWork()->getIdentityMap());
        $category = static::getContainer()->get(NewsCategoryRepository::class)->findPublicBySlug('handel'); self::assertSame(4, $news->findByCategory($category)['total']);
        $company = $this->firstCompany(); self::assertCount(4, $news->findForCompany($company)); self::assertCount(3, $news->findFeaturedPublic()); self::assertSame(9, $news->countPublic());
        $article = $news->findPublicBySlug('gemeinsam-vor-ort'); self::assertStringContainsString('fiktive', $article->getContent()); self::assertTrue($article->getCategories()->isInitialized());
    }
    public function testFeaturedFallbackAndEmptyState(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $em->createQuery('UPDATE App\Entity\NewsArticle article SET article.featured = false')->execute();
        $this->client->request('GET', '/'); self::assertCount(3, $this->client->getCrawler()->filter('#aktuelles .news-card')); self::assertContains('Automatisch veröffentlichter Rückblick', $this->newsTitles());
        $em = static::getContainer()->get(EntityManagerInterface::class); $em->createQuery("UPDATE App\Entity\NewsArticle article SET article.status = 'draft'")->execute();
        $this->client->request('GET', '/aktuelles'); self::assertSelectorTextContains('[role="status"]', 'keine Neuigkeiten'); $this->client->request('GET', '/'); self::assertSelectorTextContains('#aktuelles', 'keine Neuigkeiten');
    }
    public function testPaginationKeepsFiltersWithoutCategoryDuplicatesAndArchivesRemainReachable(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $categories = $em->getRepository(NewsCategory::class)->findBy(['active' => true], limit: 2);
        for ($i = 0; $i < 15; ++$i) {
            $article = (new NewsArticle($this->clock->now()))->setTitle('Archiv '.$i)->setSlug('archiv-'.$i)->setContent('Inhalt')->setStatus(NewsStatus::Published)->setPublishedAt($this->clock->now()->modify('-'.$i.' years'));
            foreach ($categories as $category) { $article->addCategory($category); } $em->persist($article);
        }
        $em->flush(); $slug = $categories[0]->getSlug(); $this->client->request('GET', '/aktuelles?kategorie='.$slug); self::assertResponseIsSuccessful(); self::assertCount(12, $this->client->getCrawler()->filter('.news-card')); $first = $this->newsTitles();
        self::assertSelectorExists('a[href="/aktuelles?kategorie='.$slug.'&page=2"]'); $this->client->request('GET', '/aktuelles?kategorie='.$slug.'&page=2'); self::assertResponseIsSuccessful(); self::assertSame([], array_intersect($first, $this->newsTitles()));
        $this->client->request('GET', '/aktuelles/archiv-14'); self::assertResponseIsSuccessful(); $this->client->request('GET', '/aktuelles?page=999'); self::assertResponseStatusCodeSame(404);
    }
    public function testInactiveCompanyDoesNotDisableIndependentArticleOrExposeCompanyLink(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $this->firstCompany()->setActive(false); $em->flush();
        $this->client->request('GET', '/aktuelles/neues-aus-dem-musterladen'); self::assertResponseIsSuccessful(); self::assertSelectorNotExists('.news-meta a[href="/unternehmen/musterladen-hasebogen"]');
    }
}

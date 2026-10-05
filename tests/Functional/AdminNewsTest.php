<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\DirectoryImageFixtures;
use App\Entity\{NewsArticle, NewsCategory};
use App\Enum\NewsStatus;
use App\Repository\{NewsArticleRepository, NewsCategoryRepository};
use App\Service\{CompanyImageStorage, DirectorySlugger};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AdminNewsTest extends NewsDatabaseTestCase
{
    public static function managers(): iterable { yield ['admin']; yield ['editor']; }
    #[DataProvider('managers')]
    public function testAdminAndEditorCreatePublishPlanDraftAndDelete(string $role): void
    {
        $this->login($role); $companyId = $this->firstCompany()->getId(); $this->client->request('GET', '/admin/news/new?company='.$companyId); self::assertResponseIsSuccessful(); self::assertSelectorExists('select[name="news_article[company]"] option[value="'.$companyId.'"][selected]');
        $this->client->submitForm('Speichern', ['news_article[title]' => 'Langer Einkaufsabend im Oktober', 'news_article[content]' => 'Fiktiver Inhalt']); self::assertResponseStatusCodeSame(303);
        $article = $this->article('langer-einkaufsabend-im-oktober'); $id = $article->getId(); self::assertSame(NewsStatus::Draft, $article->getStatus());
        $this->client->request('GET', '/admin/news/'.$id); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('h1', 'Langer Einkaufsabend');
        $this->client->request('GET', '/aktuelles/'.$article->getSlug()); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[status]' => 'published']); self::assertResponseStatusCodeSame(303); self::assertEquals($this->clock->now(), $this->article('langer-einkaufsabend-im-oktober')->getPublishedAt());
        $this->client->request('GET', '/aktuelles/langer-einkaufsabend-im-oktober'); self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[title]' => 'Neuer Titel', 'news_article[status]' => 'scheduled', 'news_article[publishedAt]' => '2030-05-02T18:00']); self::assertResponseStatusCodeSame(303);
        $article = $this->article('langer-einkaufsabend-im-oktober'); self::assertSame('Neuer Titel', $article->getTitle()); self::assertSame('16:00', $article->getPublishedAt()->format('H:i')); $this->client->request('GET', '/aktuelles/langer-einkaufsabend-im-oktober'); self::assertResponseStatusCodeSame(404);
        $this->clock->modify('+2 days'); $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[title]' => 'Weiter bearbeitet']); self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/aktuelles/langer-einkaufsabend-im-oktober'); self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[status]' => 'draft']); self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/aktuelles/langer-einkaufsabend-im-oktober'); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Beitrag löschen'); self::assertResponseStatusCodeSame(303); self::assertNull(static::getContainer()->get(NewsArticleRepository::class)->find($id));
    }
    #[DataProvider('managers')]
    public function testNewsCategoryCrudAndAttachedDeletionProtection(string $role): void
    {
        $this->login($role); $this->client->request('GET', '/admin/news-categories/new'); $this->client->submitForm('Speichern', ['news_category[name]' => 'Neue Stadtideen', 'news_category[position]' => '10']); self::assertResponseStatusCodeSame(303);
        $category = static::getContainer()->get(NewsCategoryRepository::class)->findOneBy(['slug' => 'neue-stadtideen']); self::assertNotNull($category); $id = $category->getId();
        $this->client->request('GET', '/admin/news-categories/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_category[name]' => 'Neuer Name']); self::assertResponseStatusCodeSame(303);
        $em = static::getContainer()->get(EntityManagerInterface::class); $category = $em->find(NewsCategory::class, $id); $this->article()->addCategory($category); $em->flush();
        $this->client->request('GET', '/admin/news-categories/'.$id.'/edit'); $this->client->submitForm('Kategorie löschen'); self::assertResponseStatusCodeSame(303); self::assertNotNull(static::getContainer()->get(NewsCategoryRepository::class)->find($id));
        $em = static::getContainer()->get(EntityManagerInterface::class); $this->article()->removeCategory($em->find(NewsCategory::class, $id)); $em->flush();
        $this->client->request('GET', '/admin/news-categories/'.$id.'/edit'); $this->client->submitForm('Kategorie löschen'); self::assertResponseStatusCodeSame(303); self::assertNull(static::getContainer()->get(NewsCategoryRepository::class)->find($id));
    }
    public static function invalidCategories(): iterable
    {
        yield [['news_category[name]' => '']];
        yield [['news_category[position]' => '-1']];
        yield [['news_category[slug]' => 'werbekreis']];
        yield [['news_category[slug]' => 'Invalid Slug']];
    }
    #[DataProvider('invalidCategories')]
    public function testInvalidNewsCategoriesProduceFormErrors(array $changes): void
    {
        $this->login('editor'); $this->client->request('GET', '/admin/news-categories/new');
        $this->client->submitForm('Speichern', array_replace(['news_category[name]' => 'Neue Kategorie', 'news_category[position]' => '1'], $changes));
        self::assertResponseStatusCodeSame(422); self::assertSelectorExists('.invalid-feedback, .alert-danger');
    }

    public function testAnonymousMemberAndCsrfRestrictions(): void
    {
        $id = $this->article()->getId(); $urls = ['/admin/news', '/admin/news/new', '/admin/news/'.$id, '/admin/news/'.$id.'/edit', '/admin/news/'.$id.'/image', '/admin/news-categories', '/admin/news-categories/new'];
        foreach ($urls as $url) { $this->client->request('GET', $url); self::assertResponseRedirects('/login'); }
        $this->login('member'); foreach ($urls as $url) { $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403); }
        foreach (['/admin/news/'.$id.'/delete', '/admin/news/new', '/admin/news-categories/new'] as $url) { $this->client->request('POST', $url); self::assertResponseStatusCodeSame(403); }
        $this->login('editor'); $this->client->request('POST', '/admin/news/'.$id.'/delete', ['_token' => 'bad']); self::assertResponseStatusCodeSame(403);
        $categoryId = static::getContainer()->get(NewsCategoryRepository::class)->findOneBy(['slug' => 'werbekreis'])->getId(); $this->client->request('POST', '/admin/news-categories/'.$categoryId.'/delete', ['_token' => 'bad']); self::assertResponseStatusCodeSame(403);
    }
    public static function invalidForms(): iterable
    {
        yield [['news_article[title]' => '']]; yield [['news_article[content]' => '']]; yield [['news_article[teaser]' => str_repeat('x', 501)]];
        yield [['news_article[status]' => 'scheduled', 'news_article[publishedAt]' => '']];
        yield [['news_article[status]' => 'scheduled', 'news_article[publishedAt]' => '2030-04-01T18:00']];
        yield [['news_article[slug]' => 'gemeinsam-vor-ort']]; yield [['news_article[externalUrl]' => 'javascript:alert(1)']];
    }
    #[DataProvider('invalidForms')]
    public function testInvalidContentAndSchedulingProduceFormErrors(array $changes): void
    {
        $this->login('editor'); $this->client->request('GET', '/admin/news/new'); $this->client->submitForm('Speichern', array_replace(['news_article[title]' => 'Ungültiger Beitrag', 'news_article[content]' => 'Inhalt'], $changes));
        self::assertResponseStatusCodeSame(422); self::assertSelectorExists('.invalid-feedback, .alert-danger');
    }
    public function testElapsedScheduledArticleRemainsEditableButChangedPastDateFails(): void
    {
        $this->login('editor'); $id = $this->article('automatisch-veroeffentlichter-rueckblick')->getId();
        $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[teaser]' => 'Bearbeiteter Rückblick']); self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[publishedAt]' => '2030-04-01T18:00']); self::assertResponseStatusCodeSame(422); self::assertSelectorTextContains('.invalid-feedback', 'zukünftigen');
    }
    public function testElapsedSchedulePreservesStoredSecondsOnContentOnlyEdit(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $article = $this->article('automatisch-veroeffentlichter-rueckblick');
        $date = $this->clock->now()->modify('-1 hour')->modify('+37 seconds'); $article->setPublishedAt($date); $id = $article->getId(); $em->flush();
        $this->login('editor'); $this->client->request('GET', '/admin/news/'.$id.'/edit');
        $this->client->submitForm('Speichern', ['news_article[teaser]' => 'Inhalt geändert']); self::assertResponseStatusCodeSame(303);
        self::assertEquals($date, $this->article('automatisch-veroeffentlichter-rueckblick')->getPublishedAt());
    }

    public function testFiltersDashboardAndSlugCollisions(): void
    {
        $this->login('editor'); $this->client->request('GET', '/admin/news?status=draft&featured=1'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('table', 'Entwurf einer Neuigkeit'); self::assertSelectorTextNotContains('table', 'Gemeinsam vor Ort');
        $categoryId = static::getContainer()->get(NewsCategoryRepository::class)->findOneBy(['slug' => 'handel'])->getId(); $companyId = $this->firstCompany()->getId();
        $this->client->request('GET', '/admin/news?category='.$categoryId.'&company='.$companyId.'&featured=0&title=Ideen'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('table', 'Ideen aus dem Handel'); self::assertSelectorExists('select[name="featured"] option[value="0"][selected]');
        $this->client->request('GET', '/admin'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', 'Öffentliche News');
        $slugs = static::getContainer()->get(DirectorySlugger::class); $article = (new NewsArticle())->setTitle('Gemeinsam vor Ort'); $slugs->assign($article); self::assertSame('gemeinsam-vor-ort-2', $article->getSlug());
        $category = (new NewsCategory())->setName('Werbekreis'); $slugs->assign($category); self::assertSame('werbekreis-2', $category->getSlug());
    }
    public function testImagesReplaceRemoveCleanupAndDelete(): void
    {
        $this->login('editor'); $id = $this->article()->getId(); $storage = static::getContainer()->get(CompanyImageStorage::class); $oldName = $this->article()->getImagePath(); $oldPath = $storage->path($oldName); touch($oldPath, time() - 7200); self::assertNotContains($oldName, $storage->cleanup());
        $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[file]' => static::getContainer()->get(DirectoryImageFixtures::class)->png([10, 30, 90], 200, 150)]); self::assertResponseStatusCodeSame(303); self::assertFileDoesNotExist($oldPath);
        $path = $storage->path($this->article()->getImagePath()); self::assertFileExists($path); $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[removeImage]' => true]); self::assertResponseStatusCodeSame(303); self::assertFileDoesNotExist($path); self::assertNull($this->article()->getImagePath());
        $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[file]' => static::getContainer()->get(DirectoryImageFixtures::class)->png([1, 2, 3], 100, 100)]); self::assertResponseStatusCodeSame(303);
        $path = $storage->path($this->article()->getImagePath()); $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Beitrag löschen'); self::assertResponseStatusCodeSame(303); self::assertFileDoesNotExist($path);
    }
    public function testUnsafeAndOversizedImagesAreRejected(): void
    {
        $this->login('editor'); $path = tempnam(sys_get_temp_dir(), 'news-upload-');
        try {
            file_put_contents($path, '<?php echo "bad";'); $id = $this->article()->getId();
            $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[file]' => new UploadedFile($path, 'bad.png', 'image/png', null, true)]); self::assertResponseStatusCodeSame(422);
            $image = static::getContainer()->get(DirectoryImageFixtures::class)->png([1, 2, 3], 100, 100); file_put_contents($path, file_get_contents($image->getPathname()).str_repeat('x', 6 * 1024 * 1024)); unlink($image->getPathname());
            $this->client->request('GET', '/admin/news/'.$id.'/edit'); $this->client->submitForm('Speichern', ['news_article[file]' => new UploadedFile($path, 'large.png', 'image/png', null, true)]); self::assertResponseStatusCodeSame(422);
        } finally { if (is_file($path)) { unlink($path); } }
    }
    public function testCompanyDeletionKeepsArticleAndItsImage(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $article = $this->article('neues-aus-dem-musterladen'); $id = $article->getId(); $image = $article->getImagePath();
        static::getContainer()->get(CompanyImageStorage::class)->deleteCompany($article->getCompany()); $em->clear(); $article = $em->find(NewsArticle::class, $id); self::assertNotNull($article); self::assertNull($article->getCompany()); self::assertSame($image, $article->getImagePath()); self::assertFileExists(static::getContainer()->get(CompanyImageStorage::class)->path($image));
    }
    public function testAdminPaginationWithCategoriesRetainsZeroFeaturedFilter(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class); $categories = $em->getRepository(NewsCategory::class)->findBy(['active' => true], limit: 2);
        for ($i = 0; $i < 27; ++$i) { $article = (new NewsArticle($this->clock->now()))->setTitle('Pagination '.$i)->setSlug('pagination-'.$i)->setContent('Inhalt'); foreach ($categories as $category) { $article->addCategory($category); } $em->persist($article); }
        $em->flush(); $this->login('editor'); $this->client->request('GET', '/admin/news?title=Pagination&featured=0'); self::assertResponseIsSuccessful(); self::assertCount(25, $this->client->getCrawler()->filter('tbody tr')); self::assertSelectorExists('a[href="/admin/news?title=Pagination&featured=0&page=2"]');
        $this->client->request('GET', '/admin/news?title=Pagination&featured=0&page=2'); self::assertCount(2, $this->client->getCrawler()->filter('tbody tr'));
    }
}

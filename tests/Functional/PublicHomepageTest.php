<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PublicHomepageTest extends PublicDirectoryTestCase
{
    public function testFeaturedCompaniesComeFromTheDatabase(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        // Two featured companies first, filled up with further active ones; the inactive hotel never appears.
        self::assertSame(['Beispielcafé Uferpause', 'Musterladen Hasebogen', 'Demo-Werkstatt Stadtblick', 'Musterpraxis Wohlgefühl'], $this->cardNames());
        self::assertSelectorTextNotContains('main', 'Beispielhotel Lindenhof');
        self::assertSelectorNotExists('#unternehmen .demo-note');
        self::assertSelectorExists('#unternehmen a[href="/unternehmen/beispielcafe-uferpause"]');
        self::assertSelectorExists('#unternehmen a[href="/unternehmen"]');
    }

    public function testCategoriesComeFromTheDatabase(): void
    {
        $this->client->request('GET', '/');
        $names = $this->client->getCrawler()->filter('#entdecken a.category .category-name')->each(static fn ($node): string => trim($node->text()));
        self::assertSame(['Handel', 'Gastronomie', 'Handwerk', 'Dienstleistungen', 'Gesundheit', 'Freizeit', 'Industrie', 'Tourismus'], $names);
        self::assertSelectorExists('#entdecken a.category[href="/unternehmen?kategorie=gastronomie"]');
        self::assertSelectorTextNotContains('#entdecken', 'Archiv');
        // Counts only active companies: Tourismus only holds the inactive hotel.
        self::assertSelectorTextContains('#entdecken a[href="/unternehmen?kategorie=tourismus"] .category-count', '0 Unternehmen');
        self::assertSelectorTextContains('#entdecken a[href="/unternehmen?kategorie=handel"] .category-count', '1 Unternehmen');
    }

    public function testCategoryChangesAreReflected(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['slug' => 'industrie']);
        self::assertInstanceOf(Category::class, $category);
        $category->setActive(false);
        $em->flush();
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('a[href="/unternehmen?kategorie=industrie"]');
    }

    public function testHeroSearchSubmitsToDirectory(): void
    {
        $crawler = $this->client->request('GET', '/');
        $form = $crawler->filter('#portal-search')->closest('form');
        self::assertNotNull($form);
        self::assertSame('/unternehmen', $form->attr('action'));
        self::assertSame('get', strtolower((string) $form->attr('method')));
        self::assertSelectorExists('#portal-search[name="q"]');
        self::assertSelectorExists('label[for="portal-search"]');
        $this->client->submit($form->form(), ['q' => 'Kuchen']);
        self::assertResponseIsSuccessful();
        self::assertSame('http://localhost/unternehmen?q=Kuchen', $this->client->getRequest()->getUri());
        self::assertSame(['Beispielcafé Uferpause'], $this->cardNames());
    }

    public function testHeaderAndFooterLinkToDirectory(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorExists('header a.btn[href="/unternehmen"]');
        self::assertSelectorExists('footer a[href="/unternehmen"]');
    }

    public function testListingQueriesLoadOnlyCardData(): void
    {
        $companies = static::getContainer()->get(CompanyRepository::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        foreach ([$companies->findFeaturedPublic(), iterator_to_array($companies->publicDirectoryPage('', null, 1))] as $list) {
            self::assertNotEmpty($list);
            foreach ($list as $company) {
                self::assertTrue($company->getCategories()->isInitialized(), 'Categories are fetch-joined (no N+1).');
                self::assertTrue($company->getImages()->isInitialized(), 'Images are fetch-joined (no N+1).');
                self::assertFalse($company->getOpeningHours()->isInitialized(), 'Opening hours are not needed on cards.');
                self::assertFalse($company->getContactPersons()->isInitialized(), 'Contacts are not needed on cards.');
            }
            $em->clear();
        }
        self::assertCount(1, $companies->findFeaturedPublic(1));
    }
}

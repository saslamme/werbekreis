<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Company;
use App\Repository\CategoryRepository;
use App\Service\DirectorySlugger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicDirectoryTest extends PublicDirectoryTestCase
{
    public function testDirectoryIsPublicAndListsOnlyActiveCompaniesFeaturedFirst(): void
    {
        $this->client->request('GET', '/unternehmen');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Unternehmen in Haselünne');
        self::assertSame(['Beispielcafé Uferpause', 'Musterladen Hasebogen', 'Demo-Werkstatt Stadtblick', 'Musterpraxis Wohlgefühl'], $this->cardNames());
        self::assertSelectorTextNotContains('main', 'Beispielhotel Lindenhof');
        self::assertSelectorTextContains('#results-heading', '4 Unternehmen');
        self::assertSelectorExists('a[href="/unternehmen/musterladen-hasebogen"]');
        self::assertSelectorCount(2, '.company-card.is-featured');
    }

    public function testInactiveCategoriesAreNeverShown(): void
    {
        $this->client->request('GET', '/unternehmen');
        // "Archiv" is assigned to an active company but is inactive itself.
        self::assertSelectorTextNotContains('main', 'Archiv');
        self::assertSelectorNotExists('option[value="archiv"]');
        $this->client->request('GET', '/unternehmen?kategorie=archiv');
        self::assertResponseStatusCodeSame(404);
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function searches(): iterable
    {
        yield 'name' => ['Hasebogen', ['Musterladen Hasebogen']];
        yield 'short description, case-insensitive' => ['KUCHEN', ['Beispielcafé Uferpause']];
        yield 'description' => ['automatisierten Tests', ['Beispielcafé Uferpause', 'Musterladen Hasebogen', 'Demo-Werkstatt Stadtblick', 'Musterpraxis Wohlgefühl']];
        yield 'category name' => ['gesundheit', ['Musterpraxis Wohlgefühl']];
        yield 'trimmed input' => ['   Werkstatt   ', ['Demo-Werkstatt Stadtblick']];
        yield 'inactive company is not found' => ['Lindenhof', []];
        yield 'inactive category name is not searched' => ['Archiv', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('searches')]
    public function testSearch(string $query, array $expected): void
    {
        $this->client->request('GET', '/unternehmen', ['q' => $query]);
        self::assertResponseIsSuccessful();
        self::assertSame($expected, $this->cardNames());
        self::assertInputValueSame('q', trim($query));
    }

    public function testSearchWithoutResultsShowsEmptyState(): void
    {
        $this->client->request('GET', '/unternehmen?q=Nichtvorhanden');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.directory-empty', 'Für diese Suche wurden keine Unternehmen gefunden.');
        self::assertSelectorExists('.directory-empty a[href="/unternehmen"]');
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    public function testSpecialCharactersAreSafeAndMatchLiterally(): void
    {
        foreach (["%", "_", "100%", "'\" OR 1=1 --", '<script>alert(1)</script>', 'ä\\'] as $query) {
            $this->client->request('GET', '/unternehmen', ['q' => $query]);
            self::assertResponseIsSuccessful();
            self::assertSame([], $this->cardNames(), 'Wildcards and quotes must not match everything: '.$query);
        }
        self::assertInputValueSame('q', 'ä\\');
        $this->client->request('GET', '/unternehmen', ['q' => '<script>alert(1)</script>']);
        self::assertStringNotContainsString('<script>alert(1)</script>', (string) $this->client->getResponse()->getContent());
    }

    public function testEmptySearchShowsAllActiveCompanies(): void
    {
        $this->client->request('GET', '/unternehmen?q=++&kategorie=');
        self::assertResponseIsSuccessful();
        self::assertCount(4, $this->cardNames());
    }

    public function testCategoryFilter(): void
    {
        $this->client->request('GET', '/unternehmen?kategorie=handwerk');
        self::assertResponseIsSuccessful();
        self::assertSame(['Demo-Werkstatt Stadtblick'], $this->cardNames());
        self::assertSelectorTextContains('h1', 'Handwerk in Haselünne');
        self::assertSelectorExists('option[value="handwerk"][selected]');
        self::assertSelectorExists('link[rel="canonical"][href="http://localhost/unternehmen?kategorie=handwerk"]');
        // Only active companies: Tourismus only holds the inactive hotel.
        $this->client->request('GET', '/unternehmen?kategorie=tourismus');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->cardNames());
        self::assertSelectorTextContains('.directory-empty', 'keine Unternehmen');
    }

    public function testUnknownCategoryIsNotFound(): void
    {
        $this->client->request('GET', '/unternehmen?kategorie=gibt-es-nicht');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSearchAndCategoryCombine(): void
    {
        $this->client->request('GET', '/unternehmen?q=caf&kategorie=gastronomie');
        self::assertSame(['Beispielcafé Uferpause'], $this->cardNames());
        $this->client->request('GET', '/unternehmen?q=Hasebogen&kategorie=gastronomie');
        self::assertSame([], $this->cardNames());
        // Category links keep the search term.
        $this->client->request('GET', '/unternehmen?q=e');
        self::assertSelectorExists('a.category-chip[href="/unternehmen?kategorie=handel&q=e"]');
    }

    public function testPaginationKeepsFilters(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['slug' => 'handel']);
        self::assertInstanceOf(Category::class, $category);
        for ($i = 1; $i <= 14; ++$i) {
            $company = (new Company())->setName(sprintf('Testgeschäft %02d', $i))->setCity('Haselünne')->addCategory($category);
            static::getContainer()->get(DirectorySlugger::class)->assign($company);
            $em->persist($company);
            $em->flush();
        }
        $this->client->request('GET', '/unternehmen?q=Testgesch%C3%A4ft&kategorie=handel');
        self::assertResponseIsSuccessful();
        self::assertCount(12, $this->cardNames());
        self::assertSelectorTextContains('#results-heading', '14 Unternehmen');
        $next = $this->client->getCrawler()->selectLink('Weiter')->link()->getUri();
        self::assertSame('http://localhost/unternehmen?q=Testgesch%C3%A4ft&kategorie=handel&page=2', $next);
        $this->client->request('GET', $next);
        self::assertSame(['Testgeschäft 13', 'Testgeschäft 14'], $this->cardNames());
        self::assertSelectorExists('a[href="/unternehmen?q=Testgesch%C3%A4ft&kategorie=handel&page=1"]');
        $this->client->request('GET', '/unternehmen?q=Testgesch%C3%A4ft&kategorie=handel&page=3');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/unternehmen?page=abc');
        self::assertResponseIsSuccessful();
    }

    public function testPublicPagesNeedNoLoginWhileAdminStaysProtected(): void
    {
        foreach (['/', '/unternehmen', '/unternehmen/musterladen-hasebogen'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
        }
        $this->client->request('GET', '/admin/companies');
        self::assertResponseRedirects('/login');
        $this->login('member');
        $this->client->request('GET', '/unternehmen');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/companies');
        self::assertResponseStatusCodeSame(403);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\{ContentPage, ContentPageSection, BoardMember};
use App\Repository\{ContentPageRepository, BoardMemberRepository};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class CmsAdminTest extends DatabaseTestCase
{
    private array $snapshot;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        foreach (['ContentPage', 'ContentPageSection', 'BoardMember'] as $table) {
            $this->snapshot[$table] = $connection->fetchAllAssociative('SELECT * FROM '.$table);
        }
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        foreach (['ContentPageSection', 'ContentPage', 'BoardMember'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        foreach ($this->snapshot as $table => $rows) {
            foreach ($rows as $row) { $connection->insert($table, $row); }
        }
        $em->clear();
        parent::tearDown();
    }

    public static function permissions(): iterable
    {
        foreach (['editor', 'admin'] as $role) {
            foreach (array_keys(ContentPage::SYSTEM_PAGES) as $code) {
                yield $role.' '.$code => [$role, $code, $role === 'editor' && in_array($code, ['imprint', 'privacy'], true) ? 403 : 200];
            }
        }
    }

    #[DataProvider('permissions')]
    public function testPagePermissionsOnGetAndPost(string $role, string $code, int $status): void
    {
        $this->login($role);
        $this->client->request('GET', '/admin/pages');
        self::assertResponseIsSuccessful();
        if ($role === 'editor') {
            self::assertSelectorNotExists('a[href="/admin/pages/imprint/edit"], a[href="/admin/pages/privacy/edit"]');
        }
        $this->client->request('GET', '/admin/pages/'.$code.'/edit');
        self::assertResponseStatusCodeSame($status);
        if ($status === 403) {
            $this->client->request('POST', '/admin/pages/'.$code.'/edit', ['content_page' => ['title' => 'Forbidden']]);
            self::assertResponseStatusCodeSame(403);
        } else {
            self::assertSelectorExists('.admin-nav-link.is-active[href="/admin/pages"]');
            self::assertSelectorNotExists('[name="content_page[code]"], [name="content_page[slug]"]');
        }
    }

    public function testAdminTitleAndSectionEditsAppearPubliclyWithEscapingAndSeoFallbacks(): void
    {
        $this->login('admin');
        $this->client->request('GET', '/admin/pages/about/edit');
        $this->client->submitForm('Speichern', [
            'content_page[title]' => 'Geänderter Titel <script>alert(1)</script>',
            'content_page[sections][0][heading]' => '<img src=x onerror=alert(1)>',
            'content_page[sections][0][body]' => "Neue Zeile\n<script>alert(2)</script> {{ 7 * 7 }} {organization}",
            'content_page[metaTitle]' => '',
            'content_page[metaDescription]' => '',
        ]);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/werbekreis/ueber-uns');
        self::assertSelectorTextSame('h1', 'Geänderter Titel <script>alert(1)</script>');
        self::assertSelectorTextContains('main', '<script>alert(2)</script> {{ 7 * 7 }} Werbekreis Haselünne e.V.');
        self::assertSelectorTextSame('title', 'Geänderter Titel <script>alert(1)</script> | Werbekreis Haselünne');
        self::assertSelectorExists('meta[name="description"][content="Informationen und Kontakt zum Werbekreis Haselünne."]');
        self::assertSelectorNotExists('main script, main img');
        self::assertSelectorExists('main br');
    }

    public function testCollectionAddRemoveSortAndLastModifiedTimestamp(): void
    {
        $this->login('editor');
        $page = static::getContainer()->get(ContentPageRepository::class)->findOneByCode('about');
        $removedId = $page->getSections()->first()->getId();
        $this->client->request('GET', '/admin/pages/about/edit');
        $form = $this->client->getCrawler()->selectButton('Speichern')->form();
        $values = $form->getPhpValues();
        unset($values['content_page']['sections'][0]);
        $values['content_page']['sections'][8] = ['heading' => 'Neuer Hinweis', 'body' => 'Hinweistext', 'style' => 'notice', 'position' => '0'];
        $this->client->request('POST', '/admin/pages/about/edit', $values);
        self::assertResponseStatusCodeSame(303);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($em->find(ContentPageSection::class, $removedId));
        $page = static::getContainer()->get(ContentPageRepository::class)->findOneByCode('about');
        self::assertCount(5, $page->getSections());
        self::assertSame('Neuer Hinweis', $page->getSections()->first()->getHeading());
        self::assertGreaterThanOrEqual($this->snapshot['ContentPage'][0]['updatedAt'], $page->getUpdatedAt()->format('Y-m-d H:i:s'));
        $this->client->request('GET', '/werbekreis/ueber-uns');
        self::assertSelectorTextContains('main .text-secondary', 'Hinweistext');
        self::assertSelectorTextNotContains('main', 'Gemeinsam für den Standort');
    }

    public static function managers(): iterable { yield ['editor']; yield ['admin']; }

    #[DataProvider('managers')]
    public function testBoardCrudVisibilityAndOrdering(string $role): void
    {
        $this->login($role);
        $this->client->request('GET', '/admin/pages/board/members');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.admin-nav-link.is-active[href="/admin/pages"]');
        $this->client->clickLink('Mitglied hinzufügen');
        $this->client->submitForm('Speichern', ['board_member[name]' => 'CMS Testperson', 'board_member[role]' => 'Testfunktion', 'board_member[position]' => '0']);
        self::assertResponseStatusCodeSame(303);
        $member = static::getContainer()->get(BoardMemberRepository::class)->findOneBy(['name' => 'CMS Testperson']);
        self::assertInstanceOf(BoardMember::class, $member);
        $id = $member->getId();
        $this->client->request('GET', '/werbekreis/vorstand');
        self::assertSelectorTextSame('.page-card:nth-child(1) h2', 'Andreas Walburg');
        $names = $this->client->getCrawler()->filter('.page-card h2')->each(static fn ($node) => $node->text());
        self::assertSame(['Andreas Walburg', 'CMS Testperson', 'Norbert Schnakenberg'], array_slice($names, 0, 3));
        $this->client->request('GET', '/admin/pages/board/members/'.$id.'/edit');
        $this->client->submitForm('Speichern', ['board_member[active]' => false]);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/werbekreis/vorstand');
        self::assertSelectorTextNotContains('main', 'CMS Testperson');
        $this->client->request('GET', '/admin/pages/board/members');
        self::assertSelectorTextContains('table', 'CMS Testperson');
        self::assertSelectorTextContains('table', 'Inaktiv');
    }

    public function testAnonymousMemberCsrfInvalidFormsAndFixedSystemRoutes(): void
    {
        $urls = ['/admin/pages', '/admin/pages/about/edit', '/admin/pages/board/members', '/admin/pages/board/members/new'];
        foreach ($urls as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/login');
        }
        $this->login('member');
        foreach ($urls as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403);
        }
        $this->login('editor');
        $this->client->request('POST', '/admin/pages/about/edit', ['content_page' => ['title' => 'CSRF attempt', '_token' => 'bad']]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('GET', '/admin/pages/about/edit');
        $this->client->submitForm('Speichern', ['content_page[title]' => '', 'content_page[sections][0][position]' => '-1']);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('GET', '/admin/pages/board/members/new');
        $this->client->submitForm('Speichern', ['board_member[name]' => '', 'board_member[role]' => '', 'board_member[position]' => '-1']);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('GET', '/admin/pages/new');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/admin/pages/about/delete');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/free-custom-url');
        self::assertResponseStatusCodeSame(404);
    }
}

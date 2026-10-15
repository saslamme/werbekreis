<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\{CategoryRepository, CompanyRepository, EventRepository, JobPostingRepository, NewsArticleRepository, UserRepository};
use App\Enum\NewsStatus;
use App\Service\VoucherDashboard;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminInterfaceTest extends DirectoryDatabaseTestCase
{
    #[DataProvider('roles')]
    public function testIndependentShellAndRoleNavigation(string $role, bool $admin): void
    {
        $this->login($role);
        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'h1');
        self::assertSelectorNotExists('.utility-bar, .navbar, footer');
        self::assertSelectorNotExists('a[href="/mitglied-werden"]');
        self::assertSelectorExists('.admin-topbar a[href="/"]');
        self::assertSelectorExists('.admin-topbar form[action="/logout"][method="post"] input[name="_csrf_token"]');
        self::assertNotSame('', $crawler->filter('.admin-topbar input[name="_csrf_token"]')->attr('value'));
        self::assertSelectorTextContains('.admin-topbar', $role.'@example.local');
        self::assertSelectorExists('button[aria-controls="admin-navigation"][aria-expanded="false"]');
        self::assertSelectorCount(4, '.admin-sidebar-group');
        self::assertSelectorExists('.admin-sidebar a[href="/admin/voucher-products"]');
        foreach (['/admin/users', '/admin/vouchers', '/admin/vouchers/redeem'] as $path) {
            self::assertSame($admin ? 1 : 0, $crawler->filter('.admin-sidebar a[href="'.$path.'"]')->count());
        }
        self::assertSelectorCount(4, '.admin-kpi-card');
        self::assertSelectorCount(4, '.admin-summary-card');
        self::assertSelectorCount(5, 'section[aria-labelledby="dashboard-reviews"] dl > div');
        foreach (['Freigaben', 'Inhalte', 'Benutzer'] as $heading) {
            self::assertSelectorTextContains('main', $heading);
        }
        self::assertSelectorCount($admin ? 1 : 0, '#dashboard-vouchers');
        self::assertSelectorCount($admin ? 1 : 0, '.admin-kpi-card a[href="/admin/users"]');
        self::assertSelectorExists('.admin-panel .table-responsive');
    }

    public static function roles(): array
    {
        return [['admin', true], ['editor', false]];
    }

    #[DataProvider('pages')]
    public function testActiveNavigationAndConsistentPageHeader(string $path, string $active): void
    {
        $this->login('admin');
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'h1');
        self::assertSelectorExists('.admin-page-header h1');
        self::assertSelectorCount(1, '.admin-sidebar [aria-current="page"]');
        self::assertSelectorExists('.admin-sidebar a.is-active[aria-current="page"][href="'.$active.'"]');
    }

    public static function pages(): array
    {
        $paths = ['/admin', '/admin/companies', '/admin/categories', '/admin/offers', '/admin/events', '/admin/event-categories', '/admin/news', '/admin/news-categories', '/admin/jobs', '/admin/reviews', '/admin/users', '/admin/voucher-products', '/admin/vouchers', '/admin/vouchers/redeem'];
        $cases = array_map(static fn (string $path): array => [$path, $path], $paths);
        foreach (['companies', 'categories', 'offers', 'events', 'event-categories', 'news', 'news-categories', 'jobs', 'users', 'voucher-products'] as $resource) {
            $cases[] = ['/admin/'.$resource.'/new', '/admin/'.$resource];
        }
        return $cases;
    }

    public function testDedicatedRedeemerSeesOnlyPermittedNavigation(): void
    {
        $user = $this->login('editor');
        $user->setRoles(['ROLE_VOUCHER_REDEEMER']);
        static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/vouchers/redeem');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.admin-shell');
        self::assertSelectorNotExists('footer, .utility-bar, .navbar');
        self::assertSelectorCount(1, '.admin-sidebar a');
        self::assertSelectorExists('.admin-sidebar a[href="/admin/vouchers/redeem"][aria-current="page"]');
        self::assertSelectorExists('.admin-topbar-brand a[href="/admin/vouchers/redeem"]');
    }

    public function testPendingReviewIsCountedAndHighlighted(): void
    {
        $editor = $this->login('editor');
        $revision = new \App\Entity\ContentRevision($this->firstCompany(), ['name' => 'Zur Prüfung'], [], 'test', new \DateTimeImmutable());
        $revision->submit($editor, new \DateTimeImmutable());
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->persist($revision);
        $em->flush();

        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSame('1', $crawler->filter('.admin-kpi-attention strong')->text());
        self::assertSelectorExists('.admin-kpi-attention a[href="/admin/reviews"]');
        self::assertSame(['1', '0', '0', '0', '0'], $crawler->filter('section[aria-labelledby="dashboard-reviews"] dd')->each(static fn ($node): string => $node->text()));
    }

    public function testLogoutStillUsesTheExistingCsrfProtectedPost(): void
    {
        $this->login('admin');
        $this->client->request('GET', '/admin');
        $this->client->submitForm('Abmelden');
        self::assertResponseRedirects('/');
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/login');
    }

    public function testAllDashboardStatisticsKeepTheirValues(): void
    {
        $this->login('admin');
        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        $container = static::getContainer();
        $companies = $container->get(CompanyRepository::class)->statistics();
        $users = $container->get(UserRepository::class);
        $news = $container->get(NewsArticleRepository::class);
        $events = $container->get(EventRepository::class);
        $jobs = $container->get(JobPostingRepository::class);
        $expected = [
            'Unternehmen' => $companies['total'], 'Aktive Unternehmen' => $companies['active'],
            'Hervorgehobene Unternehmen' => $companies['featured'], 'Kategorien' => $container->get(CategoryRepository::class)->countAll(),
            'Benutzer gesamt' => $users->countAll(), 'Admins' => $users->countByRole('ROLE_ADMIN'),
            'Editoren' => $users->countByRole('ROLE_EDITOR'), 'Mitglieder' => $users->countByRole('ROLE_MEMBER'),
            'Öffentliche News' => $news->countPublic(), 'News-Entwürfe' => $news->count(['status' => NewsStatus::Draft]),
            'News mit Planung' => $news->count(['status' => NewsStatus::Scheduled]),
            'Kommende Veranstaltungstermine' => $events->upcomingCount(),
            'Veranstaltungstermine diesen Monat' => $events->upcomingCount(month: true),
            'Hervorgehobene Veranstaltungstermine' => $events->upcomingCount(featured: true),
            'Offene Stellen' => $jobs->countPublic(), 'Geplante Stellen' => $jobs->countUpcoming(), 'Bald auslaufende Stellen' => $jobs->countExpiring(),
        ] + $container->get(VoucherDashboard::class)->counts();
        $actual = [];
        $crawler->filter('main dl > div')->each(static function ($stat) use (&$actual): void {
            $actual[trim($stat->filter('dt')->text())] = trim($stat->filter('dd')->text());
        });
        foreach ($expected as $label => $value) {
            self::assertSame((string) $value, $actual[$label] ?? null, $label);
        }
        $reviewTotal = array_sum($crawler->filter('section[aria-labelledby="dashboard-reviews"] dl dd')->each(static fn ($node): int => (int) $node->text()));
        self::assertSelectorTextContains('.admin-kpi-card:first-child strong', (string) $reviewTotal);
        foreach (['Unternehmen', 'Angebote', 'Veranstaltungen', 'News', 'Jobs'] as $label) {
            self::assertSelectorTextContains('section[aria-labelledby="dashboard-reviews"] dl', $label);
        }
    }
}

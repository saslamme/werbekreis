<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\{ContentType, ModerationStatus, NewsStatus};
use App\Repository\{CategoryRepository, CompanyRepository, ContentRevisionRepository, EventRepository, JobPostingRepository, NewsArticleRepository, UserRepository};
use App\Service\VoucherDashboard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(UserRepository $users, CompanyRepository $companies, CategoryRepository $categories, EventRepository $events, NewsArticleRepository $news, JobPostingRepository $jobs, VoucherDashboard $voucherDashboard, ContentRevisionRepository $revisions): Response
    {
        $reviews = [];
        foreach (ContentType::cases() as $type) {
            $reviews[$type->label()] = 0;
        }
        foreach ($revisions->counts() as $row) {
            if ($row['status'] === ModerationStatus::PendingReview) {
                $reviews[$row['type']->label()] = (int) $row['amount'];
            }
        }

        $directory = $companies->statistics();
        $openJobs = $jobs->countPublic();
        $userStats = [
            'Benutzer gesamt' => $users->countAll(),
            'Admins' => $users->countByRole('ROLE_ADMIN'),
            'Editoren' => $users->countByRole('ROLE_EDITOR'),
            'Mitglieder' => $users->countByRole('ROLE_MEMBER'),
        ];

        return $this->render('admin/dashboard/index.html.twig', [
            'overview' => [
                ['label' => 'Offene Freigaben', 'value' => array_sum($reviews), 'icon' => 'circle-check', 'route' => 'admin_reviews', 'attention' => array_sum($reviews) > 0],
                ['label' => 'Aktive Unternehmen', 'value' => $directory['active'], 'icon' => 'building', 'route' => 'admin_companies'],
                ['label' => 'Mitglieder', 'value' => $userStats['Mitglieder'], 'icon' => 'users', 'route' => $this->isGranted('ROLE_ADMIN') ? 'admin_users' : null],
                ['label' => 'Offene Stellen', 'value' => $openJobs, 'icon' => 'briefcase', 'route' => 'admin_jobs'],
            ],
            'reviews' => $reviews,
            'content_stats' => [
                ['title' => 'Unternehmen', 'icon' => 'building', 'route' => 'admin_companies', 'stats' => [
                    'Unternehmen' => $directory['total'], 'Aktive Unternehmen' => $directory['active'],
                    'Hervorgehobene Unternehmen' => $directory['featured'], 'Kategorien' => $categories->countAll(),
                ]],
                ['title' => 'News', 'icon' => 'newspaper', 'route' => 'admin_news', 'stats' => [
                    'Öffentliche News' => $news->countPublic(), 'News-Entwürfe' => $news->count(['status' => NewsStatus::Draft]),
                    'News mit Planung' => $news->count(['status' => NewsStatus::Scheduled]),
                ]],
                ['title' => 'Veranstaltungen', 'icon' => 'calendar-days', 'route' => 'admin_events', 'stats' => [
                    'Kommende Veranstaltungstermine' => $events->upcomingCount(),
                    'Veranstaltungstermine diesen Monat' => $events->upcomingCount(month: true),
                    'Hervorgehobene Veranstaltungstermine' => $events->upcomingCount(featured: true),
                ]],
                ['title' => 'Jobs', 'icon' => 'briefcase', 'route' => 'admin_jobs', 'stats' => [
                    'Offene Stellen' => $openJobs, 'Geplante Stellen' => $jobs->countUpcoming(),
                    'Bald auslaufende Stellen' => $jobs->countExpiring(),
                ]],
            ],
            'voucher_stats' => $this->isGranted('ROLE_ADMIN') ? $voucherDashboard->counts() : [],
            'user_stats' => $userStats,
            'latest' => $users->findLatest(),
        ]);
    }
}

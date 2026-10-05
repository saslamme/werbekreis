<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use App\Repository\CompanyRepository;
use App\Repository\CategoryRepository;
use App\Repository\EventRepository;
use App\Repository\NewsArticleRepository;
use App\Enum\NewsStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(UserRepository $users, CompanyRepository $companies, CategoryRepository $categories, EventRepository $events, NewsArticleRepository $news): Response
    {
        $directory = $companies->statistics();
        return $this->render('admin/dashboard/index.html.twig', [
            'counts' => ['Öffentliche News' => $news->countPublic(), 'News-Entwürfe' => $news->count(['status' => NewsStatus::Draft]), 'News mit Planung' => $news->count(['status' => NewsStatus::Scheduled]), 'Kommende Veranstaltungstermine' => $events->upcomingCount(), 'Veranstaltungstermine diesen Monat' => $events->upcomingCount(month: true), 'Hervorgehobene Veranstaltungstermine' => $events->upcomingCount(featured: true), 'Benutzer gesamt' => $users->countAll(), 'Admins' => $users->countByRole('ROLE_ADMIN'), 'Editoren' => $users->countByRole('ROLE_EDITOR'), 'Mitglieder' => $users->countByRole('ROLE_MEMBER'), 'Unternehmen' => $directory['total'], 'Aktive Unternehmen' => $directory['active'], 'Kategorien' => $categories->countAll(), 'Hervorgehobene Unternehmen' => $directory['featured']],
            'latest' => $users->findLatest(),
        ]);
    }
}

<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/dashboard/index.html.twig', [
            'counts' => ['Benutzer gesamt' => $users->countAll(), 'Admins' => $users->countByRole('ROLE_ADMIN'), 'Editoren' => $users->countByRole('ROLE_EDITOR'), 'Mitglieder' => $users->countByRole('ROLE_MEMBER')],
            'latest' => $users->findLatest(),
        ]);
    }
}

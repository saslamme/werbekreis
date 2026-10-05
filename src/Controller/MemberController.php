<?php
declare(strict_types=1);
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class MemberController extends AbstractController
{
    #[Route('/member', name: 'member_dashboard', methods: ['GET'])]
    public function index(): Response { return $this->render('member/dashboard.html.twig'); }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\{ContentPageRepository, BoardMemberRepository};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    public function __construct(private readonly ContentPageRepository $pages, private readonly BoardMemberRepository $members)
    {
    }

    private function renderPage(string $code, array $extra = []): Response
    {
        $page = $this->pages->findOneByCode($code) ?? throw $this->createNotFoundException('Systemseite fehlt. Bitte Migration ausführen.');

        return $this->render('frontend/page/'.$code.'.html.twig', ['page' => $page] + $extra);
    }

    #[Route('/werbekreis/ueber-uns', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->renderPage('about');
    }

    #[Route('/werbekreis/vorstand', name: 'board', methods: ['GET'])]
    public function board(): Response
    {
        return $this->renderPage('board', ['members' => $this->members->findPublic()]);
    }

    #[Route('/mitglied-werden', name: 'membership', methods: ['GET'])]
    public function membership(): Response
    {
        return $this->renderPage('membership');
    }

    #[Route('/kontakt', name: 'contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->renderPage('contact');
    }

    #[Route('/impressum', name: 'imprint', methods: ['GET'])]
    public function imprint(): Response
    {
        return $this->renderPage('imprint');
    }

    #[Route('/datenschutz', name: 'privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->renderPage('privacy');
    }
}

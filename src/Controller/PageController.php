<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route('/werbekreis/ueber-uns', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('frontend/page/about.html.twig');
    }

    #[Route('/werbekreis/vorstand', name: 'board', methods: ['GET'])]
    public function board(): Response
    {
        return $this->render('frontend/page/board.html.twig');
    }

    #[Route('/mitglied-werden', name: 'membership', methods: ['GET'])]
    public function membership(): Response
    {
        return $this->render('frontend/page/membership.html.twig');
    }

    #[Route('/kontakt', name: 'contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('frontend/page/contact.html.twig');
    }

    #[Route('/impressum', name: 'imprint', methods: ['GET'])]
    public function imprint(): Response
    {
        return $this->render('frontend/page/imprint.html.twig');
    }

    #[Route('/datenschutz', name: 'privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('frontend/page/privacy.html.twig');
    }
}

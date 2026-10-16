<?php
declare(strict_types=1);
namespace App\Controller;
use App\Repository\ContentPageRepository;
use App\Service\ContentElementViewFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(ContentPageRepository $pages, ContentElementViewFactory $views): Response {
        $page = $pages->findOneByCode('home') ?? throw $this->createNotFoundException('Systemseite fehlt. Bitte Migration ausführen.');
        return $this->render('frontend/page/_layout.html.twig', ['page' => $page, 'elementViews' => $views->forPage($page)]);
    }
}

<?php
declare(strict_types=1);
namespace App\Controller;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class HomeController extends AbstractController
{
    public const FEATURED_LIMIT = 6;
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(CompanyRepository $companies, CategoryRepository $categories): Response
    {
        return $this->render('frontend/home/index.html.twig', [
            'featured_companies' => $companies->findFeaturedPublic(self::FEATURED_LIMIT),
            'categories' => $categories->findActiveWithPublicCompanyCount(),
        ]);
    }
}

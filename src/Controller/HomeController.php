<?php
declare(strict_types=1);
namespace App\Controller;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use App\Repository\OfferRepository;
use App\Repository\EventRepository;
use App\Repository\NewsArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class HomeController extends AbstractController
{
    public const FEATURED_LIMIT = 6;
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(CompanyRepository $companies, CategoryRepository $categories, OfferRepository $offers, EventRepository $events, NewsArticleRepository $news): Response
    {
        return $this->render('frontend/home/index.html.twig', [
            'featured_news' => $news->findFeaturedPublic(3),
            'upcoming_events' => $events->findUpcomingFeatured(3),
            'featured_offers' => $offers->findCurrentFeatured(3),
            'featured_companies' => $companies->findFeaturedPublic(self::FEATURED_LIMIT),
            'categories' => $categories->findActiveWithPublicCompanyCount(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Geo\DirectoryLocation;
use App\Geo\DirectoryMap;
use App\Geo\GeocodingServiceInterface;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use App\Repository\OfferRepository;
use App\Repository\EventRepository;
use App\Repository\NewsArticleRepository;
use App\Service\CompanyImageStorage;
use App\Service\CompanyStructuredData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Public company directory. Inactive companies and categories are treated as non-existent (404). */
#[Route('/unternehmen')]
final class CompanyDirectoryController extends AbstractController
{
    private const SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    #[Route('', name: 'company_index', methods: ['GET'])]
    public function index(Request $request, CompanyRepository $companies, CategoryRepository $categories, GeocodingServiceInterface $geocoder, DirectoryMap $map): Response
    {
        $search = mb_substr(trim($request->query->getString('q')), 0, 100);
        $categorySlug = trim($request->query->getString('kategorie'));
        $category = null;
        if ($categorySlug !== '') {
            $category = $categories->findPublicBySlug($categorySlug) ?? throw $this->createNotFoundException('Unbekannte Kategorie.');
        }
        // Invalid page values from hand-edited URLs fall back to the first page instead of a 400 error.
        $page = max(1, (int) filter_var($request->query->getString('page', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $location = DirectoryLocation::fromRequest($request, $geocoder);
        $results = $companies->publicDirectoryPage($search, $category, $page, $location->point, $location->radius);
        $total = count($results);
        $pages = max(1, (int) ceil($total / CompanyRepository::PUBLIC_PAGE_SIZE));
        if ($page > $pages) {
            throw $this->createNotFoundException('Diese Seite existiert nicht.');
        }

        $pageCompanies = iterator_to_array($results, false);

        return $this->render('frontend/company/index.html.twig', [
            'companies' => $pageCompanies, 'location' => $location, 'radii' => DirectoryLocation::RADII,
            'map_data' => $map->data($pageCompanies), 'distances' => $map->distances($pageCompanies, $location->point), 'total' => $total, 'page' => $page, 'pages' => $pages,
            'search' => $search, 'category' => $category, 'categories' => $categories->findActiveOrdered(),
            'parameters' => array_filter(['q' => $search, 'kategorie' => $category?->getSlug()]) + $location->parameters(),
        ]);
    }

    #[Route('/{slug}', name: 'company_show', requirements: ['slug' => self::SLUG], methods: ['GET'])]
    public function show(string $slug, CompanyRepository $companies, CompanyStructuredData $structuredData, DirectoryMap $map, OfferRepository $offers, EventRepository $events, NewsArticleRepository $news): Response
    {
        $company = $companies->findPublicBySlug($slug) ?? throw $this->createNotFoundException('Unbekanntes Unternehmen.');

        $companyNews = $news->findForCompany($company, 4);
        $upcomingEvents = $events->findUpcomingForCompany($company, 4);
        $currentOffers = $offers->findCurrentForCompany($company, 4);

        return $this->render('frontend/company/show.html.twig', ['news_articles' => array_slice($companyNews, 0, 3), 'more_news' => count($companyNews) > 3, 'events' => array_slice($upcomingEvents, 0, 3), 'more_events' => count($upcomingEvents) > 3, 'offers' => array_slice($currentOffers, 0, 3), 'more_offers' => count($currentOffers) > 3, 'company' => $company, 'map_data' => $map->data([$company]), 'structured_data' => $structuredData->forCompany($company)]);
    }

    #[Route('/{slug}/bilder/{fileName}', name: 'company_image', requirements: ['slug' => self::SLUG, 'fileName' => '[a-f0-9]{32}\.(?:jpg|png|webp)'], methods: ['GET'])]
    public function image(string $slug, string $fileName, CompanyRepository $companies, CompanyImageStorage $storage): BinaryFileResponse
    {
        $image = $companies->findPublicImage($slug, $fileName) ?? throw $this->createNotFoundException();
        $path = $storage->path($image->getFileName());
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($path, autoEtag: true, autoLastModified: true);
        $response->setPublic();
        $response->setMaxAge(86400);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}

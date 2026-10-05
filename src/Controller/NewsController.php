<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\{CompanyRepository, NewsArticleRepository, NewsCategoryRepository};
use App\Service\{CompanyImageStorage, NewsStructuredData};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[Route('/aktuelles')]
final class NewsController extends AbstractController
{
    #[Route('', name: 'news_index', methods: ['GET'])]
    public function index(Request $request, NewsArticleRepository $news, NewsCategoryRepository $categories, CompanyRepository $companies): Response
    {
        $query = $request->query->all(); $scalar = static fn (string $key): string => is_scalar($query[$key] ?? null) ? (string) $query[$key] : '';
        $category = $scalar('kategorie') !== '' ? ($categories->findPublicBySlug($scalar('kategorie')) ?? throw $this->createNotFoundException()) : null;
        $company = $scalar('unternehmen') !== '' ? ($companies->findPublicBySlug($scalar('unternehmen')) ?? throw $this->createNotFoundException()) : null;
        $page = max(1, (int) filter_var($scalar('page') ?: '1', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $results = $news->publicPage($page, $category, $company); $pages = max(1, (int) ceil($results['total'] / NewsArticleRepository::PAGE_SIZE));
        if ($page > $pages) { throw $this->createNotFoundException(); }
        return $this->render('frontend/news/index.html.twig', ['articles' => $results['rows'], 'total' => $results['total'], 'page' => $page, 'pages' => $pages,
            'categories' => $categories->findActiveOrdered(), 'category' => $category, 'company' => $company,
            'parameters' => array_filter(['kategorie' => $category?->getSlug(), 'unternehmen' => $company?->getSlug()])]);
    }
    #[Route('/{slug}', name: 'news_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(string $slug, NewsArticleRepository $news, NewsStructuredData $structured): Response
    {
        $article = $news->findPublicBySlug($slug) ?? throw $this->createNotFoundException();
        return $this->render('frontend/news/show.html.twig', ['article' => $article, 'structured_data' => $structured->forArticle($article)]);
    }
    #[Route('/{slug}/bilder/{fileName}', name: 'news_image', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'fileName' => '[a-f0-9]{32}\.(?:jpg|png|webp)'], methods: ['GET'])]
    public function image(string $slug, string $fileName, NewsArticleRepository $news, CompanyImageStorage $storage): BinaryFileResponse
    {
        $article = $news->findPublicBySlug($slug);
        if ($article === null || $article->getImagePath() !== $fileName || !is_file($path = $storage->path($fileName))) { throw $this->createNotFoundException(); }
        $response = new BinaryFileResponse($path); $response->headers->set('Cache-Control', 'no-store'); $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}

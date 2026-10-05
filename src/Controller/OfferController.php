<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\OfferType;
use App\Repository\CompanyRepository;
use App\Repository\OfferRepository;
use App\Service\CompanyImageStorage;
use App\Service\OfferStructuredData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/angebote')]
final class OfferController extends AbstractController
{
    #[Route('', name: 'offer_index', methods: ['GET'])]
    public function index(Request $request, OfferRepository $offers, CompanyRepository $companies): Response
    {
        $query = $request->query->all();
        $scalar = static fn (string $key): string => is_scalar($query[$key] ?? null) ? (string) $query[$key] : '';
        $typeValue = $scalar('typ');
        $type = OfferType::tryFrom($typeValue);
        $companySlug = $scalar('unternehmen');
        $company = $companySlug !== '' ? ($companies->findPublicBySlug($companySlug) ?? throw $this->createNotFoundException()) : null;
        $page = max(1, (int) filter_var($scalar('page') ?: '1', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $results = $offers->publicPage($page, $type, $company);
        $total = count($results);
        $pages = max(1, (int) ceil($total / OfferRepository::PAGE_SIZE));
        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render('frontend/offer/index.html.twig', ['offers' => $results, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'types' => OfferType::cases(), 'type' => $type, 'company' => $company,
            'invalid_type' => $typeValue !== '' && $type === null,
            'parameters' => array_filter(['typ' => $type?->value, 'unternehmen' => $company?->getSlug()])]);
    }

    #[Route('/{slug}', name: 'offer_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(string $slug, OfferRepository $offers, OfferStructuredData $structuredData): Response
    {
        $offer = $offers->findPublicBySlug($slug) ?? throw $this->createNotFoundException();

        return $this->render('frontend/offer/show.html.twig', ['offer' => $offer, 'structured_data' => $structuredData->forOffer($offer)]);
    }

    #[Route('/{slug}/bilder/{fileName}', name: 'offer_image', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'fileName' => '[a-f0-9]{32}\.(?:jpg|png|webp)'], methods: ['GET'])]
    public function image(string $slug, string $fileName, OfferRepository $offers, CompanyImageStorage $storage): BinaryFileResponse
    {
        $offer = $offers->findPublicBySlug($slug);
        if ($offer === null || $offer->getImagePath() !== $fileName || !is_file($path = $storage->path($fileName))) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($path);
        // A previously cached image must not outlive an offer's public eligibility.
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}

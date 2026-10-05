<?php

declare(strict_types=1);
namespace App\Controller;

use App\Entity\EventOccurrence;
use App\Geo\DirectoryMap;
use App\Repository\CompanyRepository;
use App\Repository\EventCategoryRepository;
use App\Repository\EventRepository;
use App\Service\CompanyImageStorage;
use App\Service\EventDateRangeResolver;
use App\Service\EventStructuredData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, BinaryFileResponse};
use Symfony\Component\Routing\Attribute\Route;

#[Route('/veranstaltungen')]
final class EventController extends AbstractController
{
    #[Route('', name: 'event_index', methods: ['GET'])]
    #[Route('/kalender', name: 'event_calendar', methods: ['GET'])]
    public function index(Request $request, EventRepository $events, EventCategoryRepository $categories, CompanyRepository $companies, EventDateRangeResolver $dates): Response
    {
        $query = $request->query->all(); $scalar = static fn (string $key): string => is_scalar($query[$key] ?? null) ? (string) $query[$key] : '';
        $category = $scalar('kategorie') !== '' ? ($categories->findPublicBySlug($scalar('kategorie')) ?? throw $this->createNotFoundException()) : null;
        $company = $scalar('unternehmen') !== '' ? ($companies->findPublicBySlug($scalar('unternehmen')) ?? throw $this->createNotFoundException()) : null;
        $period = array_key_exists($scalar('zeitraum'), EventDateRangeResolver::LABELS) ? $scalar('zeitraum') : 'upcoming';
        $parameters = array_filter(['kategorie' => $category?->getSlug(), 'unternehmen' => $company?->getSlug(), 'zeitraum' => $period === 'upcoming' ? null : $period]);
        $calendar = $request->attributes->get('_route') === 'event_calendar';
        $month = $dates->month($scalar('month'));
        $page = max(1, (int) filter_var($scalar('page') ?: '1', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $rows = $calendar ? $events->findForMonth($month, $category, $company) : $events->publicPage($page, $period, $category, $company);
        $pages = $calendar ? 1 : max(1, (int) ceil(count($rows) / EventRepository::PAGE_SIZE));
        if (!$calendar && $page > $pages) { throw $this->createNotFoundException(); }
        return $this->render('frontend/event/index.html.twig', ['occurrences' => $rows, 'page' => $page, 'pages' => $pages, 'parameters' => $parameters,
            'period' => $period, 'periods' => EventDateRangeResolver::LABELS, 'category' => $category, 'categories' => $categories->findActiveOrdered(), 'company' => $company,
            'calendar' => $calendar, 'month' => $month, 'days' => $calendar ? $dates->calendar($month, $rows) : [], 'now' => $dates->now()]);
    }

    #[Route('/{slug}', name: 'event_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'], priority: -1)]
    #[Route('/{slug}/termine/{id}', name: 'event_occurrence', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'id' => '\d+'], methods: ['GET'])]
    public function show(string $slug, EventRepository $events, EventDateRangeResolver $dates, EventStructuredData $structured, DirectoryMap $map, ?int $id = null): Response
    {
        $event = $events->findPublicBySlug($slug) ?? throw $this->createNotFoundException();
        $occurrence = $id === null ? $event->representativeOccurrence($dates->now()) : null;
        foreach ($event->getOccurrences() as $item) {
            if ($id !== null && $item->getId() === $id) { $occurrence = $item; break; }
        }
        if (!$occurrence instanceof EventOccurrence) { throw $this->createNotFoundException(); }
        return $this->render('frontend/event/show.html.twig', ['event' => $event, 'occurrence' => $occurrence, 'now' => $dates->now(), 'map_data' => $map->data([$event]), 'structured_data' => $structured->forOccurrence($occurrence)]);
    }

    #[Route('/{slug}/bilder/{fileName}', name: 'event_image', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'fileName' => '[a-f0-9]{32}\.(?:jpg|png|webp)'], methods: ['GET'])]
    public function image(string $slug, string $fileName, EventRepository $events, CompanyImageStorage $storage): BinaryFileResponse
    {
        $event = $events->findPublicBySlug($slug);
        if ($event === null || $event->getImagePath() !== $fileName || !is_file($path = $storage->path($fileName))) { throw $this->createNotFoundException(); }
        $response = new BinaryFileResponse($path); $response->headers->set('Cache-Control', 'no-store'); $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}

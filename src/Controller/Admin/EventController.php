<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Repository\EventCategoryRepository;
use App\Service\EventSchedule;
use App\Service\EventDateRangeResolver;
use App\Form\EventType;
use App\Repository\CompanyRepository;
use App\Repository\EventRepository;
use App\Service\CompanyImageStorage;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/events')]
final class EventController extends AbstractController
{
    public function __construct(private readonly ClockInterface $clock, private readonly EventSchedule $schedule)
    {
    }

    #[Route('', name: 'admin_events', methods: ['GET'])]
    public function index(Request $request, EventRepository $events, CompanyRepository $companies, EventCategoryRepository $categories): Response
    {
        $filters = [];
        foreach (['title', 'company', 'category', 'active', 'featured', 'status', 'period'] as $name) {
            $value = $request->query->all()[$name] ?? ''; $filters[$name] = is_scalar($value) ? (string) $value : '';
        }
        $value = $request->query->all()['page'] ?? 1;
        $page = max(1, (int) filter_var(is_scalar($value) ? $value : 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $rows = $events->adminPage($filters, $page);
        $pages = max(1, (int) ceil(count($rows) / EventRepository::ADMIN_PAGE_SIZE));
        if ($page > $pages) { throw $this->createNotFoundException(); }
        return $this->render('admin/event/index.html.twig', ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'parameters' => array_filter($filters, static fn ($v): bool => $v !== ''),
            'filters' => $filters, 'companies' => $companies->findBy([], ['name' => 'ASC']), 'categories' => $categories->findBy([], ['position' => 'ASC', 'name' => 'ASC']), 'periods' => EventDateRangeResolver::LABELS, 'now' => $this->clock->now()]);
    }

    #[Route('/new', name: 'admin_event_new', methods: ['GET', 'POST'])]
    public function create(Request $request, CompanyImageStorage $storage, CompanyRepository $companies): Response
    {
        $event = new Event();
        $company = filter_var($request->query->getString('company'), FILTER_VALIDATE_INT);
        if ($company !== false) {
            $event->setCompany($companies->find($company));
        }

        return $this->save($event, $request, $storage);
    }

    #[Route('/{id}', name: 'admin_event_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Event $event): Response
    {
        return $this->render('admin/event/show.html.twig', ['event' => $event, 'now' => $this->clock->now()]);
    }

    #[Route('/{id}/edit', name: 'admin_event_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Event $event, Request $request, CompanyImageStorage $storage): Response
    {
        return $this->save($event, $request, $storage);
    }

    #[Route('/{id}/image', name: 'admin_event_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function image(Event $event, CompanyImageStorage $storage): BinaryFileResponse
    {
        if ($event->getImagePath() === null || !is_file($path = $storage->path($event->getImagePath()))) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($path);
        $response->setPrivate();
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    #[Route('/{id}/delete', name: 'admin_event_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Event $event, Request $request, CompanyImageStorage $storage): Response
    {
        if (!$this->isCsrfTokenValid('delete_event_'.$event->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $storage->deleteImage($event);
        $this->addFlash('success', 'Veranstaltung gelöscht.');

        return $this->redirectToRoute('admin_events', status: Response::HTTP_SEE_OTHER);
    }

    private function save(Event $event, Request $request, CompanyImageStorage $storage): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->schedule->synchronize($event);
                $event->touch();
                $file = $form->get('file')->getData();
                $storage->save($event, $file instanceof UploadedFile ? $file : null, $form->get('removeImage')->getData() === true);
                $this->addFlash('success', 'Veranstaltung gespeichert.');

                return $this->redirectToRoute('admin_events', status: Response::HTTP_SEE_OTHER);
            } catch (UniqueConstraintViolationException) {
                $form->addError(new FormError('Der Slug wurde inzwischen vergeben. Bitte einen anderen Slug wählen oder das Feld leeren.'));
            } catch (FileException|IOExceptionInterface) {
                $form->get('file')->addError(new FormError('Das Bild konnte nicht gespeichert werden. Bitte Schreibrechte und freien Speicher prüfen.'));
            }
        }

        return $this->render('admin/event/form.html.twig', ['form' => $form, 'event' => $event]);
    }
}

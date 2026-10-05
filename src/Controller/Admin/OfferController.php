<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Offer;
use App\Enum\OfferType as OfferKind;
use App\Form\OfferType;
use App\Repository\CompanyRepository;
use App\Repository\OfferRepository;
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
#[Route('/admin/offers')]
final class OfferController extends AbstractController
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    #[Route('', name: 'admin_offers', methods: ['GET'])]
    public function index(Request $request, OfferRepository $offers, CompanyRepository $companies): Response
    {
        $filters = [];
        foreach (['title', 'company', 'type', 'active', 'featured'] as $name) {
            $value = $request->query->all()[$name] ?? '';
            $filters[$name] = is_scalar($value) ? (string) $value : '';
        }
        $page = max(1, (int) filter_var($request->query->getString('page', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $boolean = static fn (string $value): ?bool => match ($value) { '1' => true, '0' => false, default => null };
        $rows = $offers->adminPage(mb_substr(trim($filters['title']), 0, 180), filter_var($filters['company'], FILTER_VALIDATE_INT) ?: null,
            OfferKind::tryFrom($filters['type']), $boolean($filters['active']), $boolean($filters['featured']), $page);
        $pages = max(1, (int) ceil(count($rows) / OfferRepository::ADMIN_PAGE_SIZE));
        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/offer/index.html.twig', ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'parameters' => array_filter($filters, static fn ($value): bool => $value !== ''),
            'filters' => $filters, 'companies' => $companies->findBy([], ['name' => 'ASC']), 'types' => OfferKind::cases(), 'now' => $this->clock->now()]);
    }

    #[Route('/new', name: 'admin_offer_new', methods: ['GET', 'POST'])]
    public function create(Request $request, CompanyImageStorage $storage, CompanyRepository $companies): Response
    {
        $offer = new Offer();
        $company = filter_var($request->query->getString('company'), FILTER_VALIDATE_INT);
        if ($company !== false) {
            $offer->setCompany($companies->find($company));
        }

        return $this->save($offer, $request, $storage);
    }

    #[Route('/{id}', name: 'admin_offer_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Offer $offer): Response
    {
        return $this->render('admin/offer/show.html.twig', ['offer' => $offer, 'now' => $this->clock->now()]);
    }

    #[Route('/{id}/edit', name: 'admin_offer_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Offer $offer, Request $request, CompanyImageStorage $storage): Response
    {
        return $this->save($offer, $request, $storage);
    }

    #[Route('/{id}/image', name: 'admin_offer_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function image(Offer $offer, CompanyImageStorage $storage): BinaryFileResponse
    {
        if ($offer->getImagePath() === null || !is_file($path = $storage->path($offer->getImagePath()))) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($path);
        $response->setPrivate();
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    #[Route('/{id}/delete', name: 'admin_offer_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Offer $offer, Request $request, CompanyImageStorage $storage): Response
    {
        if (!$this->isCsrfTokenValid('delete_offer_'.$offer->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $storage->deleteImage($offer);
        $this->addFlash('success', 'Angebot gelöscht.');

        return $this->redirectToRoute('admin_offers', status: Response::HTTP_SEE_OTHER);
    }

    private function save(Offer $offer, Request $request, CompanyImageStorage $storage): Response
    {
        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $offer->touch();
                $file = $form->get('file')->getData();
                $storage->save($offer, $file instanceof UploadedFile ? $file : null, $form->get('removeImage')->getData() === true);
                $this->addFlash('success', 'Angebot gespeichert.');

                return $this->redirectToRoute('admin_offers', status: Response::HTTP_SEE_OTHER);
            } catch (UniqueConstraintViolationException) {
                $form->addError(new FormError('Der Slug wurde inzwischen vergeben. Bitte einen anderen Slug wählen oder das Feld leeren.'));
            } catch (FileException|IOExceptionInterface) {
                $form->get('file')->addError(new FormError('Das Bild konnte nicht gespeichert werden. Bitte Schreibrechte und freien Speicher prüfen.'));
            }
        }

        return $this->render('admin/offer/form.html.twig', ['form' => $form, 'offer' => $offer]);
    }
}

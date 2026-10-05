<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EventCategory;
use App\Form\EventCategoryType;
use App\Repository\EventCategoryRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/event-categories')]
final class EventCategoryController extends AbstractController
{
    #[Route('', name: 'admin_event_categories', methods: ['GET'])]
    public function index(Request $request, EventCategoryRepository $categories): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        return $this->render('admin/event_category/index.html.twig', ['rows' => $categories->adminPage($page), 'page' => $page, 'pages' => max(1, (int) ceil($categories->countAll() / EventCategoryRepository::PAGE_SIZE))]);
    }

    #[Route('/new', name: 'admin_event_category_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        return $this->save(new EventCategory(), $request, $em);
    }

    #[Route('/{id}/edit', name: 'admin_event_category_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(EventCategory $category, Request $request, EntityManagerInterface $em): Response
    {
        return $this->save($category, $request, $em);
    }

    #[Route('/{id}/delete', name: 'admin_event_category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(EventCategory $category, Request $request, EntityManagerInterface $em, EventCategoryRepository $categories): Response
    {
        if (!$this->isCsrfTokenValid('delete_event_category_'.$category->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        if ($categories->countEvents($category) > 0) {
            $this->addFlash('error', 'Zugeordnete Kategorien können nicht gelöscht werden. Bitte zunächst die Veranstaltungszuordnungen ändern.');
        } else {
            try {
                $em->remove($category);
                $em->flush();
                $this->addFlash('success', 'Kategorie gelöscht.');
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('error', 'Die Kategorie wurde inzwischen einer Veranstaltung zugeordnet und konnte nicht gelöscht werden.');
            }
        }

        return $this->redirectToRoute('admin_event_categories', status: Response::HTTP_SEE_OTHER);
    }

    private function save(EventCategory $category, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EventCategoryType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $category->touch();
                $em->persist($category);
                $em->flush();
                $this->addFlash('success', 'Kategorie gespeichert.');

                return $this->redirectToRoute('admin_event_categories', status: Response::HTTP_SEE_OTHER);
            } catch (UniqueConstraintViolationException) {
                $form->addError(new FormError('Der Slug wurde inzwischen vergeben. Bitte einen anderen Slug wählen oder das Feld leeren.'));
            }
        }

        return $this->render('admin/event_category/form.html.twig', ['form' => $form, 'category' => $category]);
    }
}

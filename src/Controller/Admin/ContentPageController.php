<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ContentPage;
use App\Form\ContentPageType;
use App\Repository\ContentPageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/pages')]
final class ContentPageController extends AbstractController
{
    #[Route('', name: 'admin_pages', methods: ['GET'])]
    public function index(ContentPageRepository $pages): Response
    {
        $rows = [];
        foreach (array_keys(ContentPage::SYSTEM_PAGES) as $code) {
            $rows[] = $pages->findOneByCode($code) ?? throw $this->createNotFoundException('Systemseite fehlt. Bitte Migration ausführen.');
        }

        return $this->render('admin/page/index.html.twig', ['pages' => $rows]);
    }

    #[Route('/{code}/edit', name: 'admin_pages_edit', requirements: ['code' => 'home|about|board|membership|contact|imprint|privacy'], methods: ['GET', 'POST'])]
    public function edit(string $code, Request $request, ContentPageRepository $pages, EntityManagerInterface $em): Response
    {
        // Check before loading or binding the form, including direct POST requests.
        if (in_array($code, ['imprint', 'privacy'], true)) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }
        $page = $pages->findOneByCode($code) ?? throw $this->createNotFoundException('Systemseite fehlt. Bitte Migration ausführen.');
        $form = $this->createForm(ContentPageType::class, $page);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Section-only edits must also update the page's last-modified date.
            $page->touch();
            $em->flush();
            $this->addFlash('success', 'Seiteninhalt gespeichert.');

            return $this->redirectToRoute('admin_pages_edit', ['code' => $code], status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/page/form.html.twig', ['page' => $page, 'form' => $form]);
    }
}

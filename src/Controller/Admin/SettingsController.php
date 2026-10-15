<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\PortalSettingsType;
use App\Repository\PortalSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SettingsController extends AbstractController
{
    #[Route('/admin/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function index(Request $request, PortalSettingsRepository $settings, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PortalSettingsType::class, $settings->getSettings());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Einstellungen gespeichert.');
            return $this->redirectToRoute('admin_settings', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/settings/index.html.twig', ['form' => $form]);
    }
}

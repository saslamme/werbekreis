<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\PortalSettingsType;
use App\Repository\PortalSettingsRepository;
use App\Service\PortalAssetStorage;
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SettingsController extends AbstractController
{
    #[Route('/admin/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function index(Request $request, PortalSettingsRepository $settings, PortalAssetStorage $assets): Response
    {
        $form = $this->createForm(PortalSettingsType::class, $settings->getSettings());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $assets->save(
                    $form->getData(),
                    $form->get('logoFile')->getData(),
                    $form->has('removeLogo') && $form->get('removeLogo')->getData(),
                    $form->get('footerLogoFile')->getData(),
                    $form->has('removeFooterLogo') && $form->get('removeFooterLogo')->getData(),
                );
            } catch (\InvalidArgumentException $exception) {
                foreach (['logoFile', 'footerLogoFile'] as $field) {
                    if ($form->get($field)->getData() !== null) {
                        $form->get($field)->addError(new FormError($exception->getMessage()));
                    }
                }
            }
            if ($form->isValid()) {
                $this->addFlash('success', 'Einstellungen gespeichert.');
                return $this->redirectToRoute('admin_settings', status: Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/settings/index.html.twig', ['form' => $form]);
    }
}

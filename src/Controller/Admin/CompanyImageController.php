<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Entity\CompanyImage;
use App\Form\CompanyImageType;
use App\Service\CompanyImageStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
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
#[Route('/admin/companies/{company}/images', requirements: ['company' => '\d+'])]
final class CompanyImageController extends AbstractController
{
    #[Route('/new', name: 'admin_company_image_new', methods: ['GET', 'POST'])]
    public function create(#[MapEntity(id: 'company')] Company $company, Request $request, CompanyImageStorage $storage): Response
    {
        return $this->save((new CompanyImage())->setCompany($company), $request, $storage, true);
    }

    #[Route('/{image}/edit', name: 'admin_company_image_edit', requirements: ['image' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(#[MapEntity(id: 'company')] Company $company, #[MapEntity(id: 'image')] CompanyImage $image, Request $request, CompanyImageStorage $storage): Response
    {
        $this->checkCompany($company, $image);

        return $this->save($image, $request, $storage, false);
    }

    #[Route('/{image}/file', name: 'admin_company_image_file', requirements: ['image' => '\d+'], methods: ['GET'])]
    public function download(#[MapEntity(id: 'company')] Company $company, #[MapEntity(id: 'image')] CompanyImage $image, CompanyImageStorage $storage): BinaryFileResponse
    {
        $this->checkCompany($company, $image);
        $path = $storage->path($image->getFileName());
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($path);
        $response->setPrivate();
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    #[Route('/{image}/delete', name: 'admin_company_image_delete', requirements: ['image' => '\d+'], methods: ['POST'])]
    public function delete(#[MapEntity(id: 'company')] Company $company, #[MapEntity(id: 'image')] CompanyImage $image, Request $request, CompanyImageStorage $storage): Response
    {
        $this->checkCompany($company, $image);
        if (!$this->isCsrfTokenValid('delete_image_'.$image->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $storage->deleteImage($image);
        $this->addFlash('success', 'Bild gelöscht.');

        return $this->redirectToRoute('admin_company_edit', ['id' => $company->getId()], Response::HTTP_SEE_OTHER);
    }

    private function checkCompany(Company $company, CompanyImage $image): void
    {
        if ($image->getCompany()?->getId() !== $company->getId()) {
            throw $this->createNotFoundException();
        }
    }

    private function save(CompanyImage $image, Request $request, CompanyImageStorage $storage, bool $new): Response
    {
        $form = $this->createForm(CompanyImageType::class, $image, ['is_new' => $new]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $file = $form->get('file')->getData();
                $storage->save($image, $file instanceof UploadedFile ? $file : null);
                $this->addFlash('success', 'Bild gespeichert.');

                return $this->redirectToRoute('admin_company_edit', ['id' => $image->getCompany()->getId()], Response::HTTP_SEE_OTHER);
            } catch (FileException|IOExceptionInterface) {
                $form->get('file')->addError(new FormError('Das Bild konnte nicht gespeichert werden. Bitte Schreibrechte und freien Speicher prüfen.'));
            }
        }

        return $this->render('admin/company/image_form.html.twig', ['form' => $form, 'company' => $image->getCompany()]);
    }
}

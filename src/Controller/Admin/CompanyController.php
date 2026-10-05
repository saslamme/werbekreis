<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Form\CompanyType;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use App\Service\CompanyImageStorage;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/companies')]
final class CompanyController extends AbstractController
{
    #[Route('', name: 'admin_companies', methods: ['GET'])]
    public function index(Request $request, CompanyRepository $companies, CategoryRepository $categories): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $filters = ['name' => trim($request->query->getString('name')), 'category' => $request->query->getInt('category') ?: null, 'active' => $request->query->getString('active'), 'featured' => $request->query->getString('featured'), 'sort' => $request->query->getString('sort', 'name')];
        $boolean = static fn (string $value): ?bool => match ($value) {
            '1' => true, '0' => false, default => null
        };
        $rows = $companies->adminPage($filters['name'], $filters['category'], $boolean($filters['active']), $boolean($filters['featured']), $page, $filters['sort']);

        return $this->render('admin/company/index.html.twig', ['companies' => $rows, 'categories' => $categories->findBy([], ['position' => 'ASC', 'name' => 'ASC']), 'filters' => $filters, 'page' => $page, 'pages' => max(1, (int) ceil(count($rows) / CompanyRepository::PAGE_SIZE))]);
    }

    #[Route('/new', name: 'admin_company_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em, #[Autowire('%portal.city%')] string $city): Response
    {
        return $this->save((new Company())->setCity($city), $request, $em);
    }

    #[Route('/{id}/edit', name: 'admin_company_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Company $company, Request $request, EntityManagerInterface $em): Response
    {
        return $this->save($company, $request, $em);
    }

    #[Route('/{id}', name: 'admin_company_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Company $company): Response
    {
        return $this->render('admin/company/show.html.twig', ['company' => $company]);
    }

    #[Route('/{id}/delete', name: 'admin_company_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Company $company, Request $request, CompanyImageStorage $images): Response
    {
        if (!$this->isCsrfTokenValid('delete_company_'.$company->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        try {
            $images->deleteCompany($company);
        } catch (\App\Service\VoucherException $exception) {
            $this->addFlash('error', $exception->getMessage());
            return $this->redirectToRoute('admin_company_show', ['id' => $company->getId()], Response::HTTP_SEE_OTHER);
        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException) {
            // A redemption may commit after the earlier history check; the FK remains authoritative.
            $this->addFlash('error', 'Unternehmen mit verknüpfter Historie können nicht gelöscht werden. Bitte stattdessen deaktivieren.');
            return $this->redirectToRoute('admin_company_show', ['id' => $company->getId()], Response::HTTP_SEE_OTHER);
        }
        $this->addFlash('success', 'Unternehmen gelöscht.');

        return $this->redirectToRoute('admin_companies', status: Response::HTTP_SEE_OTHER);
    }

    private function save(Company $company, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $company->touch();
                $em->persist($company);
                $em->flush();
                $this->addFlash('success', 'Unternehmen gespeichert.');

                return $this->redirectToRoute('admin_company_edit', ['id' => $company->getId()], Response::HTTP_SEE_OTHER);
            } catch (UniqueConstraintViolationException) {
                // Also cover a concurrent insert after UniqueEntity validation.
                $form->addError(new FormError('Der Slug wurde inzwischen vergeben. Bitte einen anderen Slug wählen oder das Feld für eine neue automatische Vergabe leeren.'));
            }
        }

        return $this->render('admin/company/form.html.twig', ['form' => $form, 'company' => $company]);
    }
}

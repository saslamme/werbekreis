<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\JobPosting;
use App\Enum\{EmploymentType, WorkModel, NewsStatus};
use App\Form\JobPostingType;
use App\Repository\{CompanyRepository, JobPostingRepository};
use App\Service\NewsPublishing;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/jobs')]
final class JobController extends AbstractController
{
    public function __construct(private readonly NewsPublishing $publishing) {}

    #[Route('', name: 'admin_jobs', methods: ['GET'])]
    public function index(Request $request, JobPostingRepository $jobs, CompanyRepository $companies): Response
    {
        $query = $request->query->all(); $filters = [];
        foreach (['title', 'company', 'employmentType', 'workModel', 'status', 'featured'] as $key) { $filters[$key] = is_scalar($query[$key] ?? null) ? (string) $query[$key] : ''; }
        $value = $query['page'] ?? '1'; $page = max(1, (int) filter_var(is_scalar($value) ? $value : '1', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $rows = $jobs->adminPage($filters, $page); $pages = max(1, (int) ceil(count($rows) / JobPostingRepository::ADMIN_PAGE_SIZE));
        if ($page > $pages) { throw $this->createNotFoundException(); }
        return $this->render('admin/job/index.html.twig', ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'filters' => $filters,
            'parameters' => array_filter($filters, static fn ($value): bool => $value !== ''), 'companies' => $companies->findBy([], ['name' => 'ASC']),
            'employment_types' => EmploymentType::cases(), 'work_models' => WorkModel::cases(), 'statuses' => NewsStatus::cases(), 'now' => $this->publishing->now()]);
    }

    #[Route('/new', name: 'admin_job_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em, CompanyRepository $companies): Response
    {
        $job = new JobPosting($this->publishing->now());
        $value = $request->query->all()['company'] ?? null; $id = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
        if ($id !== false && ($company = $companies->find($id)) !== null) { $job->setCompany($company)->copyLocationFromCompany($company); }
        return $this->save($job, $request, $em);
    }
    #[Route('/{id}', name: 'admin_job_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(JobPosting $job): Response
    {
        return $this->render('admin/job/show.html.twig', ['job' => $job, 'now' => $this->publishing->now()]);
    }
    #[Route('/{id}/edit', name: 'admin_job_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(JobPosting $job, Request $request, EntityManagerInterface $em): Response { return $this->save($job, $request, $em); }

    #[Route('/{id}/delete', name: 'admin_job_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(JobPosting $job, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_job_'.$job->getId(), $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $em->remove($job); $em->flush(); $this->addFlash('success', 'Stelle gelöscht.');
        return $this->redirectToRoute('admin_jobs', status: Response::HTTP_SEE_OTHER);
    }
    private function save(JobPosting $job, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(JobPostingType::class, $job); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $job->touch(); $em->persist($job); $em->flush(); $this->addFlash('success', 'Stelle gespeichert.');
                return $this->redirectToRoute('admin_jobs', status: Response::HTTP_SEE_OTHER);
            } catch (UniqueConstraintViolationException) {
                $form->addError(new FormError('Der Slug wurde inzwischen vergeben. Bitte einen anderen wählen oder das Feld leeren.'));
            }
        }
        return $this->render('admin/job/form.html.twig', ['job' => $job, 'form' => $form]);
    }
}

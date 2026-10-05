<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\{EmploymentType, WorkModel};
use App\Geo\DirectoryMap;
use App\Repository\{CompanyRepository, JobPostingRepository};
use App\Service\JobStructuredData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[Route('/jobs')]
final class JobController extends AbstractController
{
    #[Route('', name: 'job_index', methods: ['GET'])]
    public function index(Request $request, JobPostingRepository $jobs, CompanyRepository $companies): Response
    {
        $query = $request->query->all(); $scalar = static fn (string $key): string => is_scalar($query[$key] ?? null) ? trim((string) $query[$key]) : '';
        $filters = ['q' => mb_substr($scalar('q'), 0, 100), 'employmentType' => $scalar('employmentType'), 'workModel' => $scalar('workModel'), 'unternehmen' => $scalar('unternehmen')];
        foreach (['employmentType' => EmploymentType::class, 'workModel' => WorkModel::class] as $key => $class) {
            if ($filters[$key] !== '' && $class::tryFrom($filters[$key]) === null) { throw $this->createNotFoundException('Unbekannter Filter.'); }
        }
        $company = $filters['unternehmen'] !== '' ? ($companies->findPublicBySlug($filters['unternehmen']) ?? throw $this->createNotFoundException()) : null;
        $page = max(1, (int) filter_var($scalar('page') ?: '1', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $results = $jobs->publicPage($filters, $page, $company); $pages = max(1, (int) ceil($results['total'] / JobPostingRepository::PAGE_SIZE));
        if ($page > $pages) { throw $this->createNotFoundException(); }
        return $this->render('frontend/job/index.html.twig', ['jobs' => $results['rows'], 'total' => $results['total'], 'page' => $page, 'pages' => $pages, 'filters' => $filters,
            'parameters' => array_filter($filters, static fn ($value): bool => $value !== ''), 'companies' => $companies->findBy(['active' => true], ['name' => 'ASC']), 'employment_types' => EmploymentType::cases(), 'work_models' => WorkModel::cases()]);
    }
    #[Route('/{slug}', name: 'job_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(string $slug, JobPostingRepository $jobs, JobStructuredData $structured, DirectoryMap $map): Response
    {
        $job = $jobs->findPublicBySlug($slug) ?? throw $this->createNotFoundException();
        return $this->render('frontend/job/show.html.twig', ['job' => $job, 'structured_data' => $structured->forJob($job), 'map_data' => $map->data([$job])]);
    }
}

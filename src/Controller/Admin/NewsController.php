<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NewsArticle;
use App\Enum\NewsStatus;
use App\Repository\NewsCategoryRepository;
use App\Service\NewsPublishing;
use App\Form\NewsArticleType;
use App\Repository\CompanyRepository;
use App\Repository\NewsArticleRepository;
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
#[Route('/admin/news')]
final class NewsController extends AbstractController
{
    public function __construct(private readonly ClockInterface $clock, private readonly NewsPublishing $publishing)
    {
    }

    #[Route('', name: 'admin_news', methods: ['GET'])]
    public function index(Request $request, NewsArticleRepository $news, CompanyRepository $companies, NewsCategoryRepository $categories): Response
    {
        $filters = [];
        foreach (['title', 'company', 'category', 'status', 'featured'] as $name) {
            $value = $request->query->all()[$name] ?? ''; $filters[$name] = is_scalar($value) ? (string) $value : '';
        }
        $value = $request->query->all()['page'] ?? 1;
        $page = max(1, (int) filter_var(is_scalar($value) ? $value : 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $rows = $news->adminPage($filters, $page); $pages = max(1, (int) ceil(count($rows) / NewsArticleRepository::ADMIN_PAGE_SIZE));
        if ($page > $pages) { throw $this->createNotFoundException(); }
        return $this->render('admin/news/index.html.twig', ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'parameters' => array_filter($filters, static fn ($v): bool => $v !== ''),
            'filters' => $filters, 'companies' => $companies->findBy([], ['name' => 'ASC']), 'categories' => $categories->findBy([], ['position' => 'ASC', 'name' => 'ASC']), 'statuses' => NewsStatus::cases(), 'now' => $this->clock->now()]);
    }

    #[Route('/new', name: 'admin_news_new', methods: ['GET', 'POST'])]
    public function create(Request $request, CompanyImageStorage $storage, CompanyRepository $companies): Response
    {
        $article = new NewsArticle($this->clock->now());
        $company = filter_var($request->query->getString('company'), FILTER_VALIDATE_INT);
        if ($company !== false) {
            $article->setCompany($companies->find($company));
        }

        return $this->save($article, $request, $storage);
    }

    #[Route('/{id}', name: 'admin_news_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(NewsArticle $article): Response
    {
        return $this->render('admin/news/show.html.twig', ['article' => $article, 'now' => $this->clock->now()]);
    }

    #[Route('/{id}/edit', name: 'admin_news_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(NewsArticle $article, Request $request, CompanyImageStorage $storage): Response
    {
        return $this->save($article, $request, $storage);
    }

    #[Route('/{id}/image', name: 'admin_news_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function image(NewsArticle $article, CompanyImageStorage $storage): BinaryFileResponse
    {
        if ($article->getImagePath() === null || !is_file($path = $storage->path($article->getImagePath()))) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($path);
        $response->setPrivate();
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    #[Route('/{id}/delete', name: 'admin_news_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(NewsArticle $article, Request $request, CompanyImageStorage $storage): Response
    {
        if (!$this->isCsrfTokenValid('delete_news_'.$article->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $storage->deleteImage($article);
        $this->addFlash('success', 'Beitrag gelöscht.');

        return $this->redirectToRoute('admin_news', status: Response::HTTP_SEE_OTHER);
    }

    private function save(NewsArticle $article, Request $request, CompanyImageStorage $storage): Response
    {
        $form = $this->createForm(NewsArticleType::class, $article);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->publishing->prepareForSave($article);
                $article->touch();
                $file = $form->get('file')->getData();
                $storage->save($article, $file instanceof UploadedFile ? $file : null, $form->get('removeImage')->getData() === true);
                $this->addFlash('success', 'Beitrag gespeichert.');

                return $this->redirectToRoute('admin_news', status: Response::HTTP_SEE_OTHER);
            } catch (UniqueConstraintViolationException) {
                $form->addError(new FormError('Der Slug wurde inzwischen vergeben. Bitte einen anderen Slug wählen oder das Feld leeren.'));
            } catch (FileException|IOExceptionInterface) {
                $form->get('file')->addError(new FormError('Das Bild konnte nicht gespeichert werden. Bitte Schreibrechte und freien Speicher prüfen.'));
            }
        }

        return $this->render('admin/news/form.html.twig', ['form' => $form, 'article' => $article]);
    }
}

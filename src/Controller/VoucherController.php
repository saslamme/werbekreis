<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\VoucherLookupType;
use App\Repository\{CategoryRepository, CompanyRepository, VoucherProductRepository, VoucherRepository};
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gutschein')]
final class VoucherController extends AbstractController
{
    #[Route('', name: 'voucher_index', methods: ['GET'])]
    public function index(VoucherProductRepository $products): Response
    {
        return $this->render('frontend/voucher/index.html.twig', ['products' => $products->findPublic(), 'acceptance_count' => $products->acceptanceCount(), 'sellers' => $products->publicSellers()]);
    }
    #[Route('/akzeptanzstellen', name: 'voucher_acceptance', methods: ['GET'])]
    public function acceptance(Request $request, VoucherProductRepository $products, CompanyRepository $companies, CategoryRepository $categories): Response
    {
        $query = $request->query->all(); $scalar = static fn (string $name): string => is_scalar($query[$name] ?? null) ? trim((string) $query[$name]) : '';
        $product = $scalar('produkt') !== '' ? ($products->findPublicBySlug($scalar('produkt')) ?? throw $this->createNotFoundException()) : null;
        $category = $scalar('kategorie') !== '' ? ($categories->findPublicBySlug($scalar('kategorie')) ?? throw $this->createNotFoundException()) : null;
        $search = mb_substr($scalar('q'), 0, 100); $page = max(1, (int) filter_var($scalar('page') ?: '1', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $builder = $companies->createPublicDirectoryQueryBuilder($search, $category)->innerJoin('company.acceptedVoucherProducts', 'product')->andWhere('product.active = true');
        if ($product !== null) { $builder->andWhere('product.id = :product')->setParameter('product', $product->getId()); }
        $rows = new Paginator($builder->setFirstResult(($page - 1) * 12)->setMaxResults(12)); $pages = max(1, (int) ceil(count($rows) / 12)); if ($page > $pages) { throw $this->createNotFoundException(); }
        return $this->render('frontend/voucher/acceptance.html.twig', ['companies' => $rows, 'products' => $products->findPublic(), 'product' => $product, 'categories' => $categories->findActiveOrdered(), 'category' => $category, 'search' => $search, 'page' => $page, 'pages' => $pages,
            'parameters' => array_filter(['produkt' => $product?->getSlug(), 'kategorie' => $category?->getSlug(), 'q' => $search])]);
    }
    #[Route('/pruefen', name: 'voucher_check', methods: ['GET', 'POST'])]
    public function check(Request $request, VoucherRepository $vouchers, ClockInterface $clock, #[Autowire(service: 'limiter.voucher_check')] RateLimiterFactory $limiter): Response
    {
        $form = $this->createForm(VoucherLookupType::class); $form->handleRequest($request); $result = null; $message = null; $status = 200;
        if ($request->isMethod('POST')) {
            $limit = $limiter->create('public:'.hash('sha256', $request->getClientIp() ?? 'unknown'))->consume();
            if (!$limit->isAccepted()) { $message = 'Zu viele Prüfungen. Bitte versuchen Sie es später erneut.'; $status = 429; }
            elseif ($form->isSubmitted() && $form->isValid()) {
                $voucher = $vouchers->findByCode($form->getData()['code']);
                if ($voucher === null) { $message = 'Der Gutschein konnte nicht geprüft werden.'; }
                else { $result = ['usable' => $voucher->isUsable($clock->now()), 'status' => $voucher->publicStatusLabel($clock->now()), 'remaining' => $voucher->getRemainingAmount(), 'until' => $voucher->getValidUntil(), 'product' => $voucher->getProduct()->getName()]; }
                $form = $this->createForm(VoucherLookupType::class); // Do not echo the submitted bearer code in results.
            } else { $status = 422; }
        }
        $response = $this->render('frontend/voucher/check.html.twig', ['form' => $form, 'result' => $result, 'message' => $message], new Response(status: $status));
        $response->headers->set('Cache-Control', 'no-store'); $response->headers->set('X-Robots-Tag', 'noindex, nofollow'); $response->headers->set('Referrer-Policy', 'no-referrer');
        if ($status === 429) { $response->headers->set('Retry-After', '900'); }
        return $response;
    }
}

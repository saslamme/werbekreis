<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\VoucherProduct;
use App\Form\VoucherProductType;
use App\Repository\VoucherProductRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
#[Route('/admin/voucher-products')]
final class VoucherProductController extends AbstractController
{
    #[Route('', name: 'admin_voucher_products', methods: ['GET'])]
    public function index(Request $request, VoucherProductRepository $products): Response
    {
        $page = max(1, $request->query->getInt('page', 1)); $rows = $products->adminPage($page); $pages = max(1, (int) ceil(count($rows) / 25));
        if ($page > $pages) { throw $this->createNotFoundException(); }
        return $this->render('admin/voucher_product/index.html.twig', ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'parameters' => []]);
    }
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/new', name: 'admin_voucher_product_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response { return $this->save(new VoucherProduct(), $request, $em); }
    #[Route('/{id}/edit', name: 'admin_voucher_product_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(VoucherProduct $product, Request $request, EntityManagerInterface $em): Response { return $this->save($product, $request, $em); }
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/delete', name: 'admin_voucher_product_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(VoucherProduct $product, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_voucher_product_'.$product->getId(), $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        $db = $em->getConnection(); $db->beginTransaction();
        try {
            $em->lock($product, LockMode::PESSIMISTIC_WRITE);
            if ($em->createQueryBuilder()->select('voucher.id')->from(\App\Entity\Voucher::class, 'voucher')->where('voucher.product = :product')->setParameter('product', $product)->setMaxResults(1)->getQuery()->setLockMode(LockMode::PESSIMISTIC_READ)->getScalarResult() !== []) { $this->addFlash('error', 'Ausgegebene Gutscheine benötigen dieses Produkt. Bitte stattdessen deaktivieren.'); }
            else { $em->remove($product); $em->flush(); $this->addFlash('success', 'Produkt gelöscht.'); }
            $db->commit();
        } catch (\Throwable $exception) { if ($db->isTransactionActive()) { $db->rollBack(); } throw $exception; }
        return $this->redirectToRoute('admin_voucher_products', status: 303);
    }
    private function save(VoucherProduct $product, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(VoucherProductType::class, $product, ['financial_admin' => $this->isGranted('ROLE_ADMIN')]); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $db = $em->getConnection(); $db->beginTransaction();
            try {
                if ($product->getId() !== null) { $em->lock($product, LockMode::PESSIMISTIC_WRITE); }
                $product->touch(); $em->persist($product); $em->flush(); $db->commit(); $this->addFlash('success', 'Produkt gespeichert.'); return $this->redirectToRoute('admin_voucher_products', status: 303);
            } catch (UniqueConstraintViolationException) { if ($db->isTransactionActive()) { $db->rollBack(); } $form->addError(new FormError('Der Slug wurde inzwischen vergeben. Bitte einen anderen wählen.')); }
        }
        return $this->render('admin/voucher_product/form.html.twig', ['product' => $product, 'form' => $form]);
    }
}

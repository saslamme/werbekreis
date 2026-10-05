<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Voucher;
use App\Enum\VoucherStatus;
use App\Form\VoucherIssueType;
use App\Repository\{VoucherProductRepository, VoucherRepository, VoucherRedemptionRepository};
use App\Service\{VoucherException, VoucherLifecycle};
use Doctrine\DBAL\Exception as DatabaseException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\EntityManagerInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/vouchers')]
final class VoucherController extends AbstractController
{
    #[Route('', name: 'admin_vouchers', methods: ['GET', 'POST'])]
    public function index(Request $request, VoucherRepository $vouchers, VoucherProductRepository $products, ClockInterface $clock): Response
    {
        // Codes never become URL parameters. The exact-code filter is a CSRF-protected POST.
        $query = $request->query->all(); $filters = [];
        foreach (['product', 'status', 'validity'] as $key) { $filters[$key] = is_scalar($query[$key] ?? null) ? (string) $query[$key] : ''; }
        $filterForm = $this->createForm(\App\Form\VoucherLookupType::class); $filterForm->handleRequest($request);
        $filters['code'] = $filterForm->isSubmitted() && $filterForm->isValid() ? $filterForm->getData()['code'] : '';
        $value = $query['page'] ?? '1'; $page = max(1, (int) filter_var(is_scalar($value) ? $value : '1', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
        $rows = $vouchers->adminPage($filters, $page); $pages = max(1, (int) ceil(count($rows) / 25)); if ($page > $pages) { throw $this->createNotFoundException(); }
        return $this->render('admin/voucher/index.html.twig', ['rows' => $rows, 'filters' => $filters, 'code_form' => $filterForm, 'products' => $products->findAll(), 'statuses' => VoucherStatus::cases(), 'now' => $clock->now(), 'page' => $page, 'pages' => $pages, 'parameters' => array_filter(array_diff_key($filters, ['code' => true]))]);
    }
    #[Route('/new', name: 'admin_voucher_new', methods: ['GET', 'POST'])]
    public function create(Request $request, VoucherLifecycle $lifecycle): Response
    {
        $form = $this->createForm(VoucherIssueType::class); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $voucher = $lifecycle->issue($data['product']->getId(), $data['amount'], $this->getUser()->getId(), $data['activate'], $data['validFrom'], $data['note']);
                return $this->redirectToRoute('admin_voucher_show', ['id' => $voucher->getId()], 303);
            } catch (VoucherException) { $form->addError(new FormError('Bitte Gutscheinprodukt, Wert und Gültigkeitsbeginn prüfen.')); }
            catch (DatabaseException) { $form->addError(new FormError('Der Gutschein konnte nicht gespeichert werden. Bitte erneut versuchen.')); }
        }
        return $this->render('admin/voucher/new.html.twig', ['form' => $form]);
    }
    #[Route('/{id}', name: 'admin_voucher_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Voucher $voucher, Request $request, VoucherRedemptionRepository $history, ClockInterface $clock): Response
    {
        $page = max(1, $request->query->getInt('page', 1)); $rows = $history->historyForVoucher($voucher, $page); $pages = max(1, (int) ceil(count($rows) / 25));
        if ($page > $pages) { throw $this->createNotFoundException(); }
        return $this->render('admin/voucher/show.html.twig', ['voucher' => $voucher, 'history' => $rows, 'now' => $clock->now(), 'page' => $page, 'pages' => $pages, 'parameters' => ['id' => $voucher->getId()]]);
    }
    #[Route('/{id}/edit', name: 'admin_voucher_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Voucher $voucher, Request $request, EntityManagerInterface $em): Response
    {
        // Metadata only. Code, product, values, balances, statuses and audit timestamps are never form-mapped.
        $form = $this->createFormBuilder($voucher)->add('note', TextareaType::class, ['label' => 'Interne Notiz', 'required' => false, 'constraints' => [new Assert\Length(max: 2000)]])->getForm(); $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { $em->flush(); return $this->redirectToRoute('admin_voucher_show', ['id' => $voucher->getId()], 303); }
        return $this->render('admin/voucher/edit.html.twig', ['form' => $form, 'voucher' => $voucher]);
    }
    #[Route('/{id}/{action}', name: 'admin_voucher_action', requirements: ['id' => '\d+', 'action' => 'activate|block|unblock|delete'], methods: ['POST'])]
    public function action(int $id, string $action, Request $request, VoucherLifecycle $lifecycle): Response
    {
        if (!$this->isCsrfTokenValid('voucher_'.$action.'_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        try { $lifecycle->change($id, $action, $this->getUser()->getId()); $this->addFlash('success', 'Gutscheinaktion ausgeführt.'); }
        catch (VoucherException $exception) { $this->addFlash('error', $exception->getMessage()); return $this->redirectToRoute('admin_voucher_show', ['id' => $id], 303); }
        catch (DatabaseException) { $this->addFlash('error', 'Die Aktion konnte nicht abgeschlossen werden. Bitte erneut prüfen.'); return $this->redirectToRoute('admin_voucher_show', ['id' => $id], 303); }
        return $action === 'delete' ? $this->redirectToRoute('admin_vouchers', status: 303) : $this->redirectToRoute('admin_voucher_show', ['id' => $id], 303);
    }
}

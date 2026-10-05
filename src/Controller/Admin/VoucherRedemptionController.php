<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\{Company, User, Voucher, VoucherRedemption};
use App\Form\{VoucherLookupType, VoucherRedemptionType, VoucherConfirmationType};
use App\Repository\VoucherRepository;
use App\Service\{DecimalAmount, VoucherException, VoucherRedemptionService};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_VOUCHER_REDEEMER')]
final class VoucherRedemptionController extends AbstractController
{
    public function __construct(private readonly ClockInterface $clock) {}

    #[Route('/admin/vouchers/redeem', name: 'voucher_redeem', methods: ['GET', 'POST'])]
    public function redeem(Request $request, VoucherRepository $vouchers, EntityManagerInterface $em, VoucherRedemptionService $service): Response
    {
        $actor = $this->getUser(); if (!$actor instanceof User) { throw $this->createAccessDeniedException(); }
        $session = $request->getSession(); $lookups = $session->get('voucher.lookups', []); $confirmations = $session->get('voucher.confirmations', []); $now = $this->clock->now()->getTimestamp();
        $lookups = array_filter($lookups, static fn (array $context): bool => $context['expires'] >= $now);
        $confirmations = array_filter($confirmations, static fn (array $context): bool => $context['expires'] >= $now);
        $lookup = $this->createForm(VoucherLookupType::class); $entryForm = null; $confirmation = null; $voucher = null; $preview = null; $error = null;
        $body = $request->request->all();
        if (isset($body['voucher_confirmation'])) {
            $confirmation = $this->createForm(VoucherConfirmationType::class); $confirmation->handleRequest($request);
            $submittedKey = is_array($body['voucher_confirmation']) ? ($body['voucher_confirmation']['requestKey'] ?? null) : null;
            $context = is_string($submittedKey) ? ($confirmations[$submittedKey] ?? null) : null;
            if ($context !== null && $context['actor'] === $actor->getId()) { $preview = $context; $voucher = $em->find(Voucher::class, $context['voucher']); }
            else { throw $this->createAccessDeniedException('Diese Bestätigung ist nicht mehr gültig.'); }
            if ($confirmation->isSubmitted() && $confirmation->isValid()) {
                $key = $confirmation->getData()['requestKey']; $context = $confirmations[$key] ?? null;
                if ($context === null || $context['actor'] !== $actor->getId()) { throw $this->createAccessDeniedException('Diese Bestätigung ist nicht mehr gültig.'); }
                try {
                    $entry = $service->redeem($context['voucher'], $context['company'], $context['amount'], $actor->getId(), $key, $context['reference'], $context['note']);
                    return $this->redirectToRoute('voucher_redemption_receipt', ['id' => $entry->getId()], 303);
                } catch (VoucherException $exception) { $error = $exception->getMessage(); }
            }
        } elseif (isset($body['voucher_redemption'])) {
            $keyValue = is_array($body['voucher_redemption']) ? ($body['voucher_redemption']['lookupKey'] ?? null) : null;
            $context = is_string($keyValue) ? ($lookups[$keyValue] ?? null) : null;
            if ($context === null || $context['actor'] !== $actor->getId()) { throw $this->createAccessDeniedException('Bitte den Gutschein erneut prüfen.'); }
            $voucher = $em->find(Voucher::class, $context['voucher']) ?? throw $this->createNotFoundException();
            $entryForm = $this->createForm(VoucherRedemptionType::class, null, ['companies' => $this->companies($voucher, $actor)]); $entryForm->handleRequest($request);
            if ($entryForm->isSubmitted() && $entryForm->isValid()) {
                $data = $entryForm->getData();
                try {
                    $amount = DecimalAmount::fromMinor(DecimalAmount::toMinor($data['amount']));
                    if (!$voucher->isUsable($this->clock->now()) || DecimalAmount::toMinor($amount) <= 0 || DecimalAmount::toMinor($amount) > DecimalAmount::toMinor($voucher->getRemainingAmount())) { throw new VoucherException('Bitte Gültigkeit und Restguthaben prüfen.'); }
                    $requestKey = bin2hex(random_bytes(32));
                    $preview = ['voucher' => $voucher->getId(), 'actor' => $actor->getId(), 'amount' => $amount, 'company' => $data['company']->getId(), 'companyName' => $data['company']->getName(), 'reference' => $data['reference'], 'note' => $data['note'], 'expires' => $now + 1800];
                    $confirmations[$requestKey] = $preview;
                    $confirmation = $this->createForm(VoucherConfirmationType::class, ['requestKey' => $requestKey]); $entryForm = null;
                } catch (VoucherException $exception) { $entryForm->addError(new FormError($exception->getMessage())); }
            }
        } elseif (isset($body['voucher_lookup'])) {
            $lookup->handleRequest($request);
            if ($lookup->isSubmitted() && $lookup->isValid()) {
                $voucher = $vouchers->findByCode($lookup->getData()['code']);
                if ($voucher === null || $this->companies($voucher, $actor) === []) { $lookup->addError(new FormError('Der Gutschein ist für diese Akzeptanzstelle nicht verfügbar.')); $voucher = null; }
                else {
                    $key = bin2hex(random_bytes(32)); $lookups[$key] = ['voucher' => $voucher->getId(), 'actor' => $actor->getId(), 'expires' => $now + 1800];
                    $entryForm = $this->createForm(VoucherRedemptionType::class, ['lookupKey' => $key, 'company' => $this->isGranted('ROLE_ADMIN') ? null : $actor->getCompany()], ['companies' => $this->companies($voucher, $actor)]);
                    $lookup = $this->createForm(VoucherLookupType::class);
                }
            }
        }
        $session->set('voucher.lookups', array_slice($lookups, -30, null, true)); $session->set('voucher.confirmations', array_slice($confirmations, -30, null, true));
        $response = $this->render('voucher_redeem/index.html.twig', ['lookup' => $lookup, 'entry_form' => $entryForm, 'confirmation' => $confirmation, 'voucher' => $voucher, 'preview' => $preview, 'error' => $error, 'now' => $this->clock->now()]);
        $response->headers->set('Cache-Control', 'no-store'); $response->headers->set('X-Robots-Tag', 'noindex, nofollow'); return $response;
    }
    #[Route('/admin/vouchers/redemptions/{id}', name: 'voucher_redemption_receipt', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function receipt(VoucherRedemption $entry): Response
    {
        $actor = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && (!$actor instanceof User || $actor->getCompany()?->getId() !== $entry->getCompany()->getId() || $entry->getPerformedBy()?->getId() !== $actor->getId())) { throw $this->createAccessDeniedException(); }
        $response = $this->render('voucher_redeem/receipt.html.twig', ['entry' => $entry]); $response->headers->set('Cache-Control', 'no-store'); $response->headers->set('X-Robots-Tag', 'noindex, nofollow'); return $response;
    }
    private function companies(Voucher $voucher, User $actor): array
    {
        return $voucher->getProduct()->getAcceptingCompanies()->filter(fn (Company $company): bool => $company->isActive() && ($this->isGranted('ROLE_ADMIN') || ($actor->isActive() && $actor->getCompany()?->getId() === $company->getId())))->getValues();
    }
}

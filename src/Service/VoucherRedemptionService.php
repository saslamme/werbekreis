<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Company, User, Voucher, VoucherProduct, VoucherRedemption};
use Doctrine\DBAL\Exception as DatabaseException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockInterface;

final readonly class VoucherRedemptionService
{
    public function __construct(private ManagerRegistry $registry, private ClockInterface $clock) {}

    /** Locked fresh authorization, membership, balance and immutable ledger are committed together. */
    public function redeem(int $voucherId, int $companyId, string $amount, int $actorId, string $requestKey, ?string $reference = null, ?string $note = null): VoucherRedemption
    {
        $minor = DecimalAmount::toMinor($amount);
        if ($minor <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $requestKey) || mb_strlen($reference ?? '') > 180 || mb_strlen($note ?? '') > 2000) { throw new VoucherException('Bitte Einlösebetrag und Eingaben prüfen.'); }
        $reference = trim($reference ?? ''); $note = trim($note ?? '');
        $reference = $reference === '' ? null : $reference; $note = $note === '' ? null : $note;
        $normalized = DecimalAmount::fromMinor($minor);
        $fingerprint = hash('sha256', json_encode([$voucherId, $companyId, $normalized, $actorId, $reference, $note], JSON_THROW_ON_ERROR));
        $keyHash = hash('sha256', $requestKey);
        /** @var EntityManagerInterface $em */ $em = $this->registry->getManager(); $db = $em->getConnection(); $db->beginTransaction();
        try {
            $actor = $em->find(User::class, $actorId); if ($actor === null) { throw new VoucherException('Keine Berechtigung für diese Einlösung.'); }
            $em->refresh($actor, LockMode::PESSIMISTIC_READ);
            $admin = in_array('ROLE_ADMIN', $actor->getRoles(), true);
            if (!$actor->isActive() || (!$admin && (!in_array('ROLE_VOUCHER_REDEEMER', $actor->getRoles(), true) || $actor->getCompany()?->getId() !== $companyId))) { throw new VoucherException('Keine Berechtigung für diese Akzeptanzstelle.'); }
            $voucher = $em->find(Voucher::class, $voucherId); if ($voucher === null) { throw new VoucherException('Der Gutschein ist ungültig.'); }
            // Refresh is essential: the lookup page or a previous operation may have populated the identity map.
            $em->refresh($voucher, LockMode::PESSIMISTIC_WRITE);
            $existing = $em->createQueryBuilder()->select('entry')->from(VoucherRedemption::class, 'entry')->where('entry.idempotencyHash = :key')->setParameter('key', $keyHash)->getQuery()->setLockMode(LockMode::PESSIMISTIC_READ)->getOneOrNullResult();
            if ($existing !== null) {
                if (!hash_equals($existing->getRequestFingerprint(), $fingerprint)) { throw new VoucherException('Diese Bestätigung wurde bereits für eine andere Einlösung verwendet.'); }
                $db->commit(); return $existing;
            }
            $product = $voucher->getProduct(); $em->refresh($product, LockMode::PESSIMISTIC_READ);
            $company = $em->find(Company::class, $companyId); if ($company === null) { throw new VoucherException('Diese Akzeptanzstelle ist nicht verfügbar.'); }
            $em->refresh($company, LockMode::PESSIMISTIC_READ);
            // Query the owning relationship freshly instead of using a possibly initialized stale collection.
            $accepts = $em->createQueryBuilder()->select('product.id')->from(VoucherProduct::class, 'product')->innerJoin('product.acceptingCompanies', 'acceptingCompany')->where('product.id = :product', 'acceptingCompany.id = :company')->setParameter('product', $product->getId())->setParameter('company', $companyId)->getQuery()->setLockMode(LockMode::PESSIMISTIC_READ)->getScalarResult();
            if (!$company->isActive() || $accepts === []) { throw new VoucherException('Der Gutschein ist bei diesem Unternehmen nicht einlösbar.'); }
            $now = $this->clock->now(); $before = $voucher->getRemainingAmount(); $voucher->redeem($normalized, $now);
            $entry = new VoucherRedemption($voucher, $company, $actor, $normalized, $before, $voucher->getRemainingAmount(), $now, $keyHash, $fingerprint, $reference, $note);
            $em->persist($entry); $em->flush(); $db->commit(); return $entry;
        } catch (\Throwable $exception) {
            if ($db->isTransactionActive()) { $db->rollBack(); }
            if ($em->isOpen()) { $em->clear(); } else { $this->registry->resetManager(); }
            if ($exception instanceof DatabaseException) { throw new VoucherException('Die Einlösung konnte nicht abgeschlossen werden. Bitte den Gutschein erneut prüfen.', previous: $exception); }
            throw $exception;
        }
    }
}

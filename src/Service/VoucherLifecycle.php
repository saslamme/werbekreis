<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{User, Voucher, VoucherProduct};
use App\Enum\VoucherStatus;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockInterface;

final readonly class VoucherLifecycle
{
    public function __construct(private ManagerRegistry $registry, private ClockInterface $clock, private VoucherCodeGeneratorInterface $codes) {}
    public function issue(int $productId, ?string $amount, int $actorId, bool $activate = false, ?\DateTimeImmutable $validFrom = null, ?string $note = null): Voucher
    {
        if (mb_strlen($note ?? '') > 2000) { throw new VoucherException('Die Notiz ist zu lang.'); }
        // DB uniqueness is the final authority, including a race after generator pre-checks.
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            /** @var EntityManagerInterface $em */ $em = $this->registry->getManager(); $db = $em->getConnection(); $db->beginTransaction();
            try {
                $this->admin($em, $actorId);
                $product = $em->find(VoucherProduct::class, $productId); if ($product === null) { throw new VoucherException('Das Gutscheinprodukt ist nicht verfügbar.'); }
                $em->refresh($product, LockMode::PESSIMISTIC_READ);
                $voucher = new Voucher($product, $this->codes->generate(), $product->amountForIssue($amount), $this->clock->now(), $validFrom);
                $voucher->setNote($note); if ($activate) { $voucher->activate($this->clock->now()); }
                $em->persist($voucher); $em->flush(); $db->commit(); return $voucher;
            } catch (\Throwable $exception) {
                if ($db->isTransactionActive()) { $db->rollBack(); }
                if ($em->isOpen()) { $em->clear(); } else { $this->registry->resetManager(); }
                if ($exception instanceof UniqueConstraintViolationException) { continue; }
                throw $exception;
            }
        }
        throw new VoucherException('Der Gutschein konnte nicht eindeutig erstellt werden. Bitte erneut versuchen.');
    }
    public function change(int $voucherId, string $action, int $actorId): void
    {
        /** @var EntityManagerInterface $em */ $em = $this->registry->getManager(); $db = $em->getConnection(); $db->beginTransaction();
        try {
            $this->admin($em, $actorId);
            $voucher = $em->find(Voucher::class, $voucherId); if ($voucher === null) { throw new VoucherException('Der Gutschein ist nicht verfügbar.'); }
            $em->refresh($voucher, LockMode::PESSIMISTIC_WRITE);
            if ($action === 'activate') { $em->refresh($voucher->getProduct(), LockMode::PESSIMISTIC_READ); }
            $now = $this->clock->now();
            match ($action) {
                'activate' => $voucher->activate($now), 'block' => $voucher->block($now), 'unblock' => $voucher->unblock($now),
                'delete' => $voucher->getStatus() === VoucherStatus::Created ? $em->remove($voucher) : throw new VoucherException('Nur noch nicht aktivierte Gutscheine können gelöscht werden.'),
                default => throw new VoucherException('Ungültige Gutscheinaktion.'),
            };
            $em->flush(); $db->commit();
        } catch (\Throwable $exception) {
            if ($db->isTransactionActive()) { $db->rollBack(); }
            if ($em->isOpen()) { $em->clear(); } else { $this->registry->resetManager(); }
            throw $exception;
        }
    }
    private function admin(EntityManagerInterface $em, int $id): void
    {
        $actor = $em->find(User::class, $id);
        if ($actor !== null) { $em->refresh($actor, LockMode::PESSIMISTIC_READ); }
        if ($actor === null || !$actor->isActive() || !in_array('ROLE_ADMIN', $actor->getRoles(), true)) { throw new VoucherException('Nur Admins dürfen Gutscheine verwalten.'); }
    }
}

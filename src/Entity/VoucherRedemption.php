<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VoucherRedemptionRepository;
use Doctrine\ORM\Mapping as ORM;

/** Immutable ledger entry. No update/delete routes, setters or cascading financial-history deletion. */
#[ORM\Entity(repositoryClass: VoucherRedemptionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'redemption_date', columns: ['redeemedAt'])]
final class VoucherRedemption
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Voucher $voucher;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Company $company;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $performedBy;
    #[ORM\Column(length: 180)]
    private string $actorIdentifier;
    #[ORM\Column(length: 180)]
    private string $companyName;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $balanceBefore;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $balanceAfter;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $redeemedAt;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $reference;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;
    #[ORM\Column(length: 64, unique: true)]
    private string $idempotencyHash;
    #[ORM\Column(length: 64)]
    private string $requestFingerprint;

    public function __construct(Voucher $voucher, Company $company, User $actor, string $amount, string $before, string $after, \DateTimeImmutable $now, string $keyHash, string $fingerprint, ?string $reference = null, ?string $note = null)
    {
        $this->voucher = $voucher; $this->company = $company; $this->performedBy = $actor;
        $this->actorIdentifier = $actor->getUserIdentifier(); $this->companyName = $company->getName();
        $this->amount = $amount; $this->balanceBefore = $before; $this->balanceAfter = $after;
        $this->redeemedAt = $this->createdAt = $now; $this->idempotencyHash = $keyHash; $this->requestFingerprint = $fingerprint;
        $this->reference = $reference; $this->note = $note;
    }
    #[ORM\PreUpdate, ORM\PreRemove]
    public function preventChanges(): void { throw new \LogicException('Redemption ledger entries cannot be changed or removed.'); }
    public function getId(): ?int { return $this->id; }
    public function getVoucher(): Voucher { return $this->voucher; }
    public function getCompany(): Company { return $this->company; }
    public function getPerformedBy(): ?User { return $this->performedBy; }
    public function getActorIdentifier(): string { return $this->actorIdentifier; }
    public function getCompanyName(): string { return $this->companyName; }
    public function getResultStatus(): \App\Enum\VoucherStatus { return \App\Service\DecimalAmount::toMinor($this->balanceAfter) === 0 ? \App\Enum\VoucherStatus::Redeemed : \App\Enum\VoucherStatus::PartiallyRedeemed; }
    public function getAmount(): string { return $this->amount; }
    public function getBalanceBefore(): string { return $this->balanceBefore; }
    public function getBalanceAfter(): string { return $this->balanceAfter; }
    public function getRedeemedAt(): \DateTimeImmutable { return $this->redeemedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getReference(): ?string { return $this->reference; }
    public function getNote(): ?string { return $this->note; }
    public function getRequestFingerprint(): string { return $this->requestFingerprint; }
}

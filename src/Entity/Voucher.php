<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\TimestampedTrait;
use App\Enum\VoucherStatus;
use App\Repository\VoucherRepository;
use App\Service\{DecimalAmount, VoucherCodeGenerator, VoucherException};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VoucherRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'voucher_validity', columns: ['status', 'validFrom', 'validUntil'])]
#[UniqueEntity(fields: ['code'], message: 'Der Code konnte nicht vergeben werden. Bitte erneut versuchen.')]
final class Voucher
{
    use TimestampedTrait;
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private VoucherProduct $product;
    #[ORM\Column(length: 40, unique: true)]
    #[Assert\Regex(VoucherCodeGenerator::PATTERN)]
    private string $code;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $initialAmount;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $remainingAmount;
    #[ORM\Column(length: 24, enumType: VoucherStatus::class)]
    private VoucherStatus $status = VoucherStatus::Created;
    #[ORM\Column(length: 24, enumType: VoucherStatus::class, nullable: true)]
    private ?VoucherStatus $statusBeforeBlock = null;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $issuedAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $activatedAt = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $redeemedAt = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $blockedAt = null;
    #[ORM\Column(nullable: true)]
    private ?int $validityMonths;
    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $note = null;
    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $externalReference = null;

    public function __construct(VoucherProduct $product, string $code, string $amount, \DateTimeImmutable $issuedAt, ?\DateTimeImmutable $validFrom = null)
    {
        $this->product = $product; $this->code = VoucherCodeGenerator::normalize($code);
        $minor = DecimalAmount::toMinor($amount);
        if ($minor <= 0 || !preg_match(VoucherCodeGenerator::PATTERN, $this->code)) { throw new VoucherException('Der Gutschein konnte nicht erstellt werden.'); }
        $this->initialAmount = $this->remainingAmount = DecimalAmount::fromMinor($minor);
        $this->issuedAt = $this->createdAt = $this->updatedAt = $issuedAt;
        $this->validFrom = $validFrom; $this->validityMonths = $product->getValidityMonths();
    }
    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = Clock::get()->now(); }
    public function getId(): ?int { return $this->id; }
    public function getProduct(): VoucherProduct { return $this->product; }
    public function getCode(): string { return $this->code; }
    public function getMaskedCode(): string { return 'WK-****-****-****-'.substr($this->code, -4); }
    public function getInitialAmount(): string { return $this->initialAmount; }
    public function getRemainingAmount(): string { return $this->remainingAmount; }
    public function getStatus(): VoucherStatus { return $this->status; }
    public function getIssuedAt(): \DateTimeImmutable { return $this->issuedAt; }
    public function getValidFrom(): ?\DateTimeImmutable { return $this->validFrom; }
    public function getValidUntil(): ?\DateTimeImmutable { return $this->validUntil; }
    public function getActivatedAt(): ?\DateTimeImmutable { return $this->activatedAt; }
    public function getRedeemedAt(): ?\DateTimeImmutable { return $this->redeemedAt; }
    public function getBlockedAt(): ?\DateTimeImmutable { return $this->blockedAt; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $value): self { $this->note = $value; return $this; }
    public function getExternalReference(): ?string { return $this->externalReference; }
    public function setExternalReference(?string $value): self { $this->externalReference = $value; return $this; }
    public function isExpired(\DateTimeImmutable $now): bool { return $this->validUntil !== null && $this->validUntil < $now; }
    public function effectiveStatus(\DateTimeImmutable $now): VoucherStatus
    {
        return $this->isExpired($now) && !in_array($this->status, [VoucherStatus::Redeemed, VoucherStatus::Blocked], true) ? VoucherStatus::Expired : $this->status;
    }
    public function publicStatusLabel(\DateTimeImmutable $now): string
    {
        return in_array($this->status->value, VoucherStatus::usableValues(), true) && !$this->isExpired($now) && $this->validFrom !== null && $this->validFrom > $now ? 'Noch nicht gültig' : $this->effectiveStatus($now)->label();
    }
    public function isUsable(\DateTimeImmutable $now): bool
    {
        return in_array($this->status->value, VoucherStatus::usableValues(), true) && !$this->isExpired($now)
            && ($this->validFrom === null || $this->validFrom <= $now) && DecimalAmount::toMinor($this->remainingAmount) > 0;
    }
    public function activate(\DateTimeImmutable $now): void
    {
        if ($this->status !== VoucherStatus::Created || !$this->product->isActive()) { throw new VoucherException('Dieser Gutschein kann nicht aktiviert werden.'); }
        $until = null;
        if ($this->validityMonths !== null) {
            $month = $now->modify('first day of this month')->modify('+'.$this->validityMonths.' months');
            $until = $month->setDate((int) $month->format('Y'), (int) $month->format('m'), min((int) $now->format('d'), (int) $month->format('t')));
            if ($this->validFrom !== null && $this->validFrom > $until) { throw new VoucherException('Der Gültigkeitsbeginn liegt nach dem Ablaufdatum.'); }
        }
        $this->status = VoucherStatus::Active; $this->activatedAt = $now; $this->validUntil = $until; $this->updatedAt = $now;
    }
    public function block(\DateTimeImmutable $now): void
    {
        if (!in_array($this->status, [VoucherStatus::Created, VoucherStatus::Active, VoucherStatus::PartiallyRedeemed], true)) { throw new VoucherException('Dieser Gutschein kann nicht gesperrt werden.'); }
        $this->statusBeforeBlock = $this->status; $this->status = VoucherStatus::Blocked; $this->blockedAt = $this->updatedAt = $now;
    }
    public function unblock(\DateTimeImmutable $now): void
    {
        if ($this->status !== VoucherStatus::Blocked || $this->isExpired($now) || DecimalAmount::toMinor($this->remainingAmount) === 0) { throw new VoucherException('Dieser Gutschein kann nicht entsperrt werden.'); }
        $this->status = $this->statusBeforeBlock ?? VoucherStatus::Created; $this->statusBeforeBlock = null; $this->updatedAt = $now;
    }
    /** Called only under the redemption service's database lock. No balance setters exist. */
    public function redeem(string $amount, \DateTimeImmutable $now): void
    {
        $minor = DecimalAmount::toMinor($amount); $balance = DecimalAmount::toMinor($this->remainingAmount);
        if (!$this->isUsable($now)) {
            throw new VoucherException(match ($this->effectiveStatus($now)) {
                VoucherStatus::Blocked => 'Der Gutschein ist gesperrt.', VoucherStatus::Expired => 'Der Gutschein ist abgelaufen.',
                VoucherStatus::Redeemed => 'Der Gutschein ist bereits eingelöst.', default => 'Der Gutschein ist derzeit nicht gültig.',
            });
        }
        if ($minor <= 0) { throw new VoucherException('Der Einlösebetrag muss größer als null sein.'); }
        if ($minor > $balance) { throw new VoucherException('Das Restguthaben reicht für diesen Betrag nicht aus.'); }
        $this->remainingAmount = DecimalAmount::fromMinor($balance - $minor);
        $this->status = $balance === $minor ? VoucherStatus::Redeemed : VoucherStatus::PartiallyRedeemed;
        if ($balance === $minor) { $this->redeemedAt = $now; }
        $this->updatedAt = $now;
    }
}

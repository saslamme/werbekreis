<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\{VoucherRepository, VoucherRedemptionRepository};
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class VoucherDashboard
{
    public function __construct(private VoucherRepository $vouchers, private VoucherRedemptionRepository $redemptions, private ClockInterface $clock, #[Autowire('%portal.timezone%')] private string $timezone) {}
    public function counts(): array
    {
        $stats = $this->vouchers->statistics(); $zone = new \DateTimeZone($this->timezone); $utc = new \DateTimeZone('UTC');
        $today = $this->clock->now()->setTimezone($zone)->setTime(0, 0); $month = $today->modify('first day of this month');
        return ['Aktive Gutscheine' => $stats['active'], 'Offenes Gutschein-Guthaben' => DecimalAmount::format($stats['openBalance']), 'Heute eingelöst' => DecimalAmount::format($this->redemptions->sumBetween($today->setTimezone($utc), $today->modify('+1 day')->setTimezone($utc))),
            'Diesen Monat eingelöst' => DecimalAmount::format($this->redemptions->sumBetween($month->setTimezone($utc), $month->modify('+1 month')->setTimezone($utc))), 'Anzahl Einlösungen' => $this->redemptions->count([]), 'Gesperrte Gutscheine' => $stats['blocked']];
    }
}

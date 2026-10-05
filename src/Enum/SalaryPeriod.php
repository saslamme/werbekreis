<?php

declare(strict_types=1);

namespace App\Enum;

enum SalaryPeriod: string
{
    case Hourly = 'hourly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'pro Stunde',
            self::Monthly => 'pro Monat',
            self::Yearly => 'pro Jahr',
        };
    }

    public function schemaValue(): string
    {
        return match ($this) {
            self::Hourly => 'HOUR',
            self::Monthly => 'MONTH',
            self::Yearly => 'YEAR',
        };
    }
}

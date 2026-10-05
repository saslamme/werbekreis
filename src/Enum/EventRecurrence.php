<?php

declare(strict_types=1);
namespace App\Enum;

enum EventRecurrence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) { self::None => 'Keine Wiederholung', self::Daily => 'Täglich', self::Weekly => 'Wöchentlich', self::Monthly => 'Monatlich' };
    }
}

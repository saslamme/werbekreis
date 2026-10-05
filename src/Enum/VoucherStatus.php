<?php

declare(strict_types=1);

namespace App\Enum;

enum VoucherStatus: string
{
    case Created = 'created';
    case Active = 'active';
    case PartiallyRedeemed = 'partially_redeemed';
    case Redeemed = 'redeemed';
    case Blocked = 'blocked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Erstellt', self::Active => 'Aktiv', self::PartiallyRedeemed => 'Teilweise eingelöst',
            self::Redeemed => 'Eingelöst', self::Blocked => 'Gesperrt', self::Expired => 'Abgelaufen',
        };
    }
    public static function usableValues(): array { return [self::Active->value, self::PartiallyRedeemed->value]; }
}

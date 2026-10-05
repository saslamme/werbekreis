<?php

declare(strict_types=1);

namespace App\Enum;

enum OfferType: string
{
    case Offer = 'offer';
    case Promotion = 'promotion';
    case Discount = 'discount';
    case NewProduct = 'new_product';

    public function label(): string
    {
        return match ($this) {
            self::Offer => 'Angebot', self::Promotion => 'Aktion', self::Discount => 'Rabatt', self::NewProduct => 'Neuheit',
        };
    }
}

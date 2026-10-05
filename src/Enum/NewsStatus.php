<?php

declare(strict_types=1);

namespace App\Enum;

enum NewsStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) { self::Draft => 'Entwurf', self::Scheduled => 'Geplant', self::Published => 'Veröffentlicht' };
    }

    /** @return list<string> */
    public static function publishableValues(): array
    {
        return [self::Published->value, self::Scheduled->value];
    }
}

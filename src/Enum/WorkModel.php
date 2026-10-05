<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkModel: string
{
    case Onsite = 'onsite';
    case Hybrid = 'hybrid';
    case Remote = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::Onsite => 'Vor Ort',
            self::Hybrid => 'Hybrid',
            self::Remote => 'Remote',
        };
    }
}

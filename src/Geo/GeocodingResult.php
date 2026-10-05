<?php

declare(strict_types=1);

namespace App\Geo;

final readonly class GeocodingResult
{
    public function __construct(public ?GeoPoint $point = null, public bool $unavailable = false)
    {
    }
}

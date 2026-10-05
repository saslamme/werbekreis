<?php

declare(strict_types=1);

namespace App\Geo;

interface GeocodingServiceInterface
{
    public function search(string $location): GeocodingResult;
}

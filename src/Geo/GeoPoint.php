<?php

declare(strict_types=1);

namespace App\Geo;

final readonly class GeoPoint
{
    public function __construct(public float $latitude, public float $longitude, public string $displayName = '')
    {
        if (!is_finite($latitude) || !is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
            throw new \InvalidArgumentException('Invalid coordinates.');
        }
    }

    public function distanceTo(self $point): float
    {
        $a = sin(deg2rad($point->latitude - $this->latitude) / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($point->latitude))
            * sin(deg2rad($point->longitude - $this->longitude) / 2) ** 2;

        return 6371.0088 * 2 * asin(sqrt(min(1.0, max(0.0, $a))));
    }
}

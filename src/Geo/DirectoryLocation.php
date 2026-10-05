<?php

declare(strict_types=1);

namespace App\Geo;

use Symfony\Component\HttpFoundation\Request;

final readonly class DirectoryLocation
{
    public const RADII = [1, 5, 10, 25, 50, 100];

    public function __construct(public string $location, public int $radius, public ?GeoPoint $point, public ?string $message, public string $view)
    {
    }

    public static function fromRequest(Request $request, GeocodingServiceInterface $geocoder): self
    {
        $data = $request->query->all();
        $scalar = static fn (string $key, string $default = ''): string => is_scalar($data[$key] ?? null) ? (string) $data[$key] : $default;
        $location = mb_substr(trim($scalar('ort')), 0, 150);
        $radius = filter_var(array_key_exists('radius', $data) ? $scalar('radius') : '10', FILTER_VALIDATE_INT);
        $message = null;
        if (!in_array($radius, self::RADII, true)) {
            $radius = 10;
            $message = 'Ungültiger Radius. Es werden 10 km verwendet.';
        }
        $point = null;
        if (array_key_exists('lat', $data) || array_key_exists('lng', $data)) {
            $lat = filter_var($scalar('lat'), FILTER_VALIDATE_FLOAT);
            $lng = filter_var($scalar('lng'), FILTER_VALIDATE_FLOAT);
            if ($lat !== false && $lng !== false && is_finite($lat) && is_finite($lng) && abs($lat) <= 90 && abs($lng) <= 180) {
                $point = new GeoPoint($lat, $lng);
            } else {
                $message = 'Ungültige Standortkoordinaten. Bitte geben Sie einen Ort ein.';
            }
        } elseif ($location !== '') {
            $result = $geocoder->search($location);
            $point = $result->point;
            if ($point === null) {
                $message = $result->unavailable ? 'Die Standortsuche ist derzeit nicht verfügbar. Bitte verwenden Sie die anderen Filter.' : 'Der eingegebene Ort konnte nicht gefunden werden.';
            }
        }

        return new self($location, $radius, $point, $message, $scalar('ansicht') === 'karte' ? 'karte' : 'liste');
    }

    /** @return array<string, string|int|float> */
    public function parameters(): array
    {
        $parameters = $this->location !== '' ? ['ort' => $this->location, 'radius' => $this->radius] : [];
        if ($this->point !== null) {
            $parameters += ['lat' => $this->point->latitude, 'lng' => $this->point->longitude, 'radius' => $this->radius];
        }
        if ($this->view === 'karte') {
            $parameters['ansicht'] = 'karte';
        }

        return $parameters;
    }
}

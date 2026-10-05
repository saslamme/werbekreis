<?php

declare(strict_types=1);

namespace App\Geo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Search-only geocoding. A shared filesystem gate caps this instance at one request per second. */
final class NominatimGeocodingService implements GeocodingServiceInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        #[Autowire(service: 'cache.app')] private readonly CacheInterface $cache,
        #[Autowire('%env(GEOCODING_BASE_URL)%')] private readonly string $baseUrl,
        #[Autowire('%env(GEOCODING_USER_AGENT)%')] private readonly string $userAgent,
        #[Autowire('%env(float:GEOCODING_TIMEOUT)%')] private readonly float $timeout,
        #[Autowire('%env(int:GEOCODING_CACHE_TTL)%')] private readonly int $ttl,
        #[Autowire('%kernel.cache_dir%/geocoding-rate.lock')] private readonly string $rateLock,
    ) {
    }

    public function search(string $location): GeocodingResult
    {
        $location = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $location) ?? $location));
        if ($location === '') {
            return new GeocodingResult();
        }
        try {
            return $this->cache->get('geocoding.'.hash('sha256', $this->baseUrl.'|'.$location), function (ItemInterface $item) use ($location): GeocodingResult {
                $item->expiresAfter(max(60, $this->ttl));
                $lock = fopen($this->rateLock, 'c+');
                if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
                    if (is_resource($lock)) {
                        fclose($lock);
                    }
                    $item->expiresAfter(1);

                    return new GeocodingResult(unavailable: true);
                }
                try {
                    $last = (float) stream_get_contents($lock);
                    if (microtime(true) - $last < 1.0) {
                        $item->expiresAfter(1);

                        return new GeocodingResult(unavailable: true);
                    }
                    rewind($lock);
                    ftruncate($lock, 0);
                    fwrite($lock, (string) microtime(true));
                    fflush($lock);
                    $rows = $this->client->request('GET', rtrim($this->baseUrl, '/').'/search', [
                        'headers' => ['User-Agent' => $this->userAgent, 'Accept' => 'application/json'],
                        'query' => ['q' => $location, 'format' => 'jsonv2', 'limit' => 1],
                        'timeout' => max(0.1, $this->timeout), 'max_duration' => max(0.1, $this->timeout),
                        'max_redirects' => 0,
                    ])->toArray();
                    if ($rows === []) {
                        return new GeocodingResult();
                    }
                    $row = $rows[0] ?? [];
                    if (!is_numeric($row['lat'] ?? null) || !is_numeric($row['lon'] ?? null)) {
                        throw new \UnexpectedValueException('Invalid geocoding response.');
                    }

                    return new GeocodingResult(new GeoPoint((float) $row['lat'], (float) $row['lon'], (string) ($row['display_name'] ?? '')));
                } catch (\Throwable) {
                    $item->expiresAfter(30);

                    return new GeocodingResult(unavailable: true);
                } finally {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
            });
        } catch (\Throwable) {
            return new GeocodingResult(unavailable: true);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Geo\NominatimGeocodingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NominatimGeocodingServiceTest extends TestCase
{
    private string $lock;

    protected function setUp(): void
    {
        $this->lock = tempnam(sys_get_temp_dir(), 'geo-test-');
    }

    protected function tearDown(): void
    {
        unlink($this->lock);
    }

    public function testSuccessfulResultIsValidatedAndNormalizedCacheIsReused(): void
    {
        $requests = 0;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            ++$requests;
            self::assertSame('GET', $method);
            self::assertStringContainsString('/search?', $url);
            self::assertContains('User-Agent: Werbekreis-test/1.0', $options['headers']);
            self::assertSame(2.0, $options['timeout']);

            return new MockResponse('[{"lat":"52.674","lon":"7.484","display_name":"Haselünne"}]');
        });
        $service = $this->service($client);
        $first = $service->search('  Haselünne  ');
        self::assertNotNull($first->point);
        self::assertSame(52.674, $first->point->latitude);
        self::assertSame('Haselünne', $first->point->displayName);
        self::assertEquals($first, $service->search('HASELÜNNE'));
        self::assertSame(1, $requests);
    }

    public function testNoResultIsDifferentFromUnavailable(): void
    {
        $service = $this->service(new MockHttpClient(new MockResponse('[]')));
        $result = $service->search('Missing');
        self::assertNull($result->point);
        self::assertFalse($result->unavailable);
        self::assertFalse($service->search('Missing')->unavailable);
        self::assertFalse($service->search('  ')->unavailable);
    }

    public function testHttpFailureAndInvalidCoordinatesAreUnavailable(): void
    {
        foreach ([new MockResponse('unavailable', ['http_code' => 503]), new MockResponse('[{"lat":999,"lon":7}]'), new MockResponse('not JSON')] as $response) {
            file_put_contents($this->lock, '0');
            $client = new MockHttpClient($response);
            $service = $this->service($client);
            $result = $service->search('Failure');
            self::assertNull($result->point);
            self::assertTrue($result->unavailable);
            self::assertTrue($service->search('Failure')->unavailable);
            self::assertSame(1, $client->getRequestsCount());
        }
    }

    public function testRateGatePreventsDistinctBackToBackRequests(): void
    {
        $client = new MockHttpClient(new MockResponse('[]'));
        $service = $this->service($client);
        self::assertFalse($service->search('First')->unavailable);
        self::assertTrue($service->search('Second')->unavailable);
        self::assertSame(1, $client->getRequestsCount());
    }

    private function service(MockHttpClient $client): NominatimGeocodingService
    {
        return new NominatimGeocodingService($client, new ArrayAdapter(), 'https://nominatim.example', 'Werbekreis-test/1.0', 2.0, 86400, $this->lock);
    }
}

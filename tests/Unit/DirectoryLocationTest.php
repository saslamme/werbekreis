<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Geo\DirectoryLocation;
use App\Geo\GeocodingResult;
use App\Geo\GeocodingServiceInterface;
use App\Geo\GeoPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DirectoryLocationTest extends TestCase
{
    public function testZeroCoordinatesAreValidAndBypassGeocoding(): void
    {
        $geocoder = $this->createMock(GeocodingServiceInterface::class);
        $geocoder->expects(self::never())->method('search');
        $location = DirectoryLocation::fromRequest(Request::create('/?lat=0&lng=0&ort=Elsewhere&radius=1&ansicht=karte'), $geocoder);
        self::assertNotNull($location->point);
        self::assertSame(0.0, $location->point->latitude);
        self::assertSame(1, $location->radius);
        self::assertSame('karte', $location->view);
        self::assertSame(0.0, $location->parameters()['lng']);
    }

    public function testLocationIsTrimmedAndProviderResultUsed(): void
    {
        $geocoder = $this->createMock(GeocodingServiceInterface::class);
        $geocoder->expects(self::once())->method('search')->with('Haselünne')->willReturn(new GeocodingResult(new GeoPoint(52.674, 7.484)));
        $location = DirectoryLocation::fromRequest(Request::create('/?ort=%20Hasel%C3%BCnne%20&radius=25'), $geocoder);
        self::assertSame('Haselünne', $location->location);
        self::assertSame(25, $location->radius);
        self::assertSame(52.674, $location->parameters()['lat']);
    }

    public function testInvalidRadiusAndArrayViewUseSafeDefaults(): void
    {
        $geocoder = $this->createMock(GeocodingServiceInterface::class);
        $geocoder->expects(self::never())->method('search');
        $location = DirectoryLocation::fromRequest(Request::create('/?radius=2&ansicht[]=karte&ort[]=foo'), $geocoder);
        self::assertSame(10, $location->radius);
        self::assertSame('liste', $location->view);
        self::assertNull($location->point);
        self::assertNotNull($location->message);
    }
}

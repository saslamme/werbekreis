<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Company;
use App\Geo\GeoPoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GeoPointTest extends TestCase
{
    public function testDistanceIsSymmetricAndUsesKilometres(): void
    {
        $origin = new GeoPoint(0, 0);
        $east = new GeoPoint(0, 1);
        self::assertSame(0.0, $origin->distanceTo($origin));
        self::assertEqualsWithDelta(111.195, $origin->distanceTo($east), 0.001);
        self::assertEqualsWithDelta($east->distanceTo($origin), $origin->distanceTo($east), 0.000001);
        self::assertEqualsWithDelta(20015.114, $origin->distanceTo(new GeoPoint(0, 180)), 0.001);
        self::assertLessThan(23, (new GeoPoint(0, 179.9))->distanceTo(new GeoPoint(0, -179.9)));
    }

    public static function invalidCoordinates(): iterable
    {
        yield [91, 0];
        yield [-91, 0];
        yield [0, 181];
        yield [0, -181];
        yield [INF, 0];
        yield [NAN, 0];
    }

    #[DataProvider('invalidCoordinates')]
    public function testInvalidCoordinatesAreRejected(float $latitude, float $longitude): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GeoPoint($latitude, $longitude);
    }

    public function testMissingAndInvalidCompanyCoordinatesAreNotMappable(): void
    {
        $company = new Company();
        self::assertFalse($company->hasCoordinates());
        $company->setLatitude(0);
        self::assertFalse($company->hasCoordinates());
        $company->setLongitude(0);
        self::assertTrue($company->hasCoordinates());
        $company->setLongitude(INF);
        self::assertFalse($company->hasCoordinates());
    }
}

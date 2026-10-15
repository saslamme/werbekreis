<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\PortalSettings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\{Clock, MockClock};
use Symfony\Component\Validator\Validation;

final class PortalSettingsTest extends TestCase
{
    public function testSingletonNullableUrlsAndTimestamps(): void
    {
        $previous = Clock::get();
        $clock = new MockClock('2026-10-15 12:00:00Z');
        Clock::set($clock);
        try {
            $settings = new PortalSettings();
            self::assertSame(1, $settings->getId());
            self::assertNull($settings->getFacebookUrl());
            self::assertNull($settings->getInstagramUrl());
            self::assertEquals($clock->now(), $settings->getCreatedAt());
            $created = $settings->getCreatedAt();
            $settings->setFacebookUrl(' https://example.com/facebook ')->setInstagramUrl('http://example.com/instagram');
            self::assertSame('https://example.com/facebook', $settings->getFacebookUrl());
            self::assertSame('http://example.com/instagram', $settings->getInstagramUrl());
            $clock->modify('+1 hour');
            $settings->touch();
            self::assertEquals($clock->now(), $settings->getUpdatedAt());
            self::assertSame($created, $settings->getCreatedAt());
            $settings->setFacebookUrl(' ')->setInstagramUrl(null);
            self::assertNull($settings->getFacebookUrl());
            self::assertNull($settings->getInstagramUrl());
        } finally { Clock::set($previous); }
    }

    public static function logoDimensions(): iterable
    {
        foreach ([[null, null], [240, 60], [300, 100], [1000, 400], [40, 20], [320, null], [null, 90]] as $values) {
            yield [...$values, null];
        }
        foreach ([39, 1001, 0, -1] as $width) { yield [$width, 60, 'logoMaxWidth']; }
        foreach ([19, 401, 0, -1] as $height) { yield [240, $height, 'logoMaxHeight']; }
    }

    #[DataProvider('logoDimensions')]
    public function testLogoDimensionValidation(?int $width, ?int $height, ?string $invalidField): void
    {
        $settings = (new PortalSettings())->setLogoMaxWidth($width)->setLogoMaxHeight($height);
        self::assertSame($width, $settings->getLogoMaxWidth());
        self::assertSame($height, $settings->getLogoMaxHeight());
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $violations = $validator->validate($settings);
        self::assertCount($invalidField === null ? 0 : 1, $violations);
        if ($invalidField !== null) { self::assertSame($invalidField, $violations[0]->getPropertyPath()); }
    }

    public function testValidation(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $settings = new PortalSettings();
        foreach ([null, '', 'http://example.com', 'https://example.com/profile'] as $url) {
            $settings->setFacebookUrl($url)->setInstagramUrl($url);
            self::assertCount(0, $validator->validate($settings));
        }
        foreach (['facebook', 'javascript:alert(1)', 'ftp://example.com', '//example.com', 'https://example.com/'.str_repeat('a', 2048)] as $url) {
            $settings->setFacebookUrl($url)->setInstagramUrl($url);
            self::assertGreaterThanOrEqual(2, count($validator->validate($settings)));
        }
    }
}

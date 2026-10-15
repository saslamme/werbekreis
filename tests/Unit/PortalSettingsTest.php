<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\PortalSettings;
use PHPUnit\Framework\TestCase;
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

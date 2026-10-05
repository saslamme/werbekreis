<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Company;
use App\Entity\Offer;
use App\Enum\OfferType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Validation;

final class OfferTest extends TestCase
{
    public static function schedules(): iterable
    {
        yield 'open' => [true, true, null, null, true, 'Aktuell'];
        yield 'deactivated' => [false, true, null, null, false, 'Deaktiviert'];
        yield 'company inactive' => [true, false, null, null, false, 'Unternehmen inaktiv'];
        yield 'starts at now' => [true, true, '2030-05-01 12:00:00Z', null, true, 'Aktuell'];
        yield 'ends at now' => [true, true, null, '2030-05-01 12:00:00Z', true, 'Aktuell'];
        yield 'single instant' => [true, true, '2030-05-01 12:00:00Z', '2030-05-01 12:00:00Z', true, 'Aktuell'];
        yield 'future' => [true, true, '2030-05-01 12:00:01Z', null, false, 'Geplant'];
        yield 'expired' => [true, true, null, '2030-05-01 11:59:59Z', false, 'Abgelaufen'];
    }

    #[DataProvider('schedules')]
    public function testScheduling(bool $active, bool $companyActive, ?string $start, ?string $end, bool $visible, string $status): void
    {
        $clock = new MockClock('2030-05-01 12:00:00Z');
        $offer = (new Offer())->setCompany((new Company())->setActive($companyActive))->setActive($active)
            ->setStartsAt($start !== null ? new \DateTimeImmutable($start) : null)->setEndsAt($end !== null ? new \DateTimeImmutable($end) : null);
        self::assertSame($visible, $offer->isCurrentlyActive($clock->now()));
        self::assertSame($status, $offer->statusAt($clock->now()));
    }

    public function testClockAdvancementChangesVisibilityWithoutWrites(): void
    {
        $clock = new MockClock('2030-05-01 12:00:00Z');
        $offer = (new Offer())->setCompany(new Company())->setEndsAt($clock->now());
        self::assertTrue($offer->isCurrentlyActive($clock->now()));
        $clock->sleep(1);
        self::assertFalse($offer->isCurrentlyActive($clock->now()));
    }

    public function testDecimalStringsAndFormattingPreserveCents(): void
    {
        $offer = (new Offer())->setRegularPrice('99999999.99')->setOfferPrice('59.9');
        self::assertSame('59.90', $offer->getOfferPrice());
        self::assertSame('59,90 €', $offer->getFormattedOfferPrice());
        self::assertSame('99.999.999,99 €', $offer->getFormattedRegularPrice());
        self::assertSame('0,00 €', $offer->setOfferPrice('0')->getFormattedOfferPrice());
        self::assertNull($offer->setOfferPrice('')->getOfferPrice());
        self::assertNull($offer->getFormattedOfferPrice());
        self::assertSame('59.999', $offer->setOfferPrice('59.999')->getOfferPrice(), 'Invalid precision must not silently round.');
    }

    public function testInvalidPeriodAndHigherPriceProduceFieldErrors(): void
    {
        $offer = (new Offer())->setStartsAt(new \DateTimeImmutable('2030-05-02'))->setEndsAt(new \DateTimeImmutable('2030-05-01'))
            ->setRegularPrice('10.01')->setOfferPrice('10.02');
        $violations = Validation::createValidator()->validate($offer, new Callback('validate'));
        self::assertCount(2, $violations);
        self::assertSame(['endsAt', 'offerPrice'], array_map(static fn ($violation): string => $violation->getPropertyPath(), iterator_to_array($violations)));
    }

    public static function invalidPrices(): iterable
    {
        foreach (['-0.01', '1.234', '100000000.00', 'NaN', '1e2', 'bad'] as $value) {
            yield [$value];
        }
    }

    #[DataProvider('invalidPrices')]
    public function testInvalidMoneyIsRejected(string $price): void
    {
        $offer = (new Offer())->setOfferPrice($price);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        self::assertCount(1, $validator->validateProperty($offer, 'offerPrice'));
        self::assertNull($offer->getFormattedOfferPrice());
    }

    public function testCompanyHelpersMaintainBothSidesIncludingReassignment(): void
    {
        $first = new Company();
        $second = new Company();
        $offer = new Offer();
        $first->addOffer($offer)->addOffer($offer);
        self::assertSame($first, $offer->getCompany());
        self::assertCount(1, $first->getOffers());
        $offer->setCompany($second);
        self::assertCount(0, $first->getOffers());
        self::assertTrue($second->getOffers()->contains($offer));
        $second->removeOffer($offer);
        self::assertNull($offer->getCompany());
    }

    public function testEnumLabelsAreCentral(): void
    {
        self::assertSame(['Angebot', 'Aktion', 'Rabatt', 'Neuheit'], array_map(static fn ($type): string => $type->label(), OfferType::cases()));
    }
}

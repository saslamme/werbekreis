<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\ModerationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModerationStatusTest extends TestCase
{
    #[DataProvider('validTransitions')]
    public function testValidTransitions(ModerationStatus $from, ModerationStatus $to): void
    {
        self::assertTrue($from->canTransitionTo($to));
    }

    public static function validTransitions(): array
    {
        return [
            [ModerationStatus::Draft, ModerationStatus::PendingReview],
            [ModerationStatus::ChangesRequested, ModerationStatus::PendingReview],
            [ModerationStatus::Rejected, ModerationStatus::PendingReview],
            [ModerationStatus::PendingReview, ModerationStatus::Approved],
            [ModerationStatus::PendingReview, ModerationStatus::ChangesRequested],
            [ModerationStatus::PendingReview, ModerationStatus::Rejected],
        ];
    }

    public function testEveryOtherTransitionIsRejected(): void
    {
        $valid = array_map(
            static fn (array $row): string => $row[0]->value.'>'.$row[1]->value,
            self::validTransitions(),
        );

        foreach (ModerationStatus::cases() as $from) {
            foreach (ModerationStatus::cases() as $to) {
                self::assertSame(
                    in_array($from->value.'>'.$to->value, $valid, true),
                    $from->canTransitionTo($to),
                    $from->value.' -> '.$to->value,
                );
            }
        }
    }

    public function testLabelsAreUserFacingAndEditableStatesAreExplicit(): void
    {
        self::assertSame('In Prüfung', ModerationStatus::PendingReview->label());
        self::assertSame('Änderungen erforderlich', ModerationStatus::ChangesRequested->label());
        self::assertTrue(ModerationStatus::Draft->isEditable());
        self::assertTrue(ModerationStatus::Rejected->isEditable());
        self::assertFalse(ModerationStatus::PendingReview->isEditable());
        self::assertFalse(ModerationStatus::Approved->isEditable());
    }
}

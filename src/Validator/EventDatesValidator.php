<?php

declare(strict_types=1);
namespace App\Validator;
use App\Entity\Event;
use App\Service\EventSchedule;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
final class EventDatesValidator extends ConstraintValidator
{
    public function __construct(private readonly EventSchedule $schedule) {}
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof Event || $value->getStartsAt() === null || $value->getEndsAt() === null) { return; }
        try { $this->schedule->dates($value); }
        catch (\InvalidArgumentException $e) { $this->context->buildViolation($e->getMessage())->addViolation(); }
    }
}

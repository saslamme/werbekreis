<?php

declare(strict_types=1);
namespace App\Validator;
use Symfony\Component\Validator\Constraint;
#[\Attribute(\Attribute::TARGET_CLASS)]
final class EventDates extends Constraint
{
    public function getTargets(): string { return self::CLASS_CONSTRAINT; }
}

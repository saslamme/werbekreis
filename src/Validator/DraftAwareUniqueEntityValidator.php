<?php

declare(strict_types=1);
namespace App\Validator;
use App\Moderation\ModeratedContent;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Symfony\Component\Validator\Constraint;
/** UniqueEntity normally compares object identity; detached server-created revision copies retain their source slug. */
final class DraftAwareUniqueEntityValidator extends UniqueEntityValidator
{
    public function __construct(private readonly ManagerRegistry $registry) { parent::__construct($registry); }
    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($value instanceof ModeratedContent && $value->isRevisionShadow() && $value->getId() !== null) {
            $source=$this->registry->getRepository($value::class)->find($value->getId());
            if ($source !== null && $source->getSlug() === $value->getSlug()) { return; }
        }
        parent::validate($value,$constraint);
    }
}

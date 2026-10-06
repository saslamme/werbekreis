<?php

declare(strict_types=1);
namespace App\Form;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
final class MemberJobType extends AbstractType
{
    public function getParent(): string { return JobPostingType::class; }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['company', 'slug', 'active', 'featured', 'status', 'publishedAt'] as $field) { if ($builder->has($field)) { $builder->remove($field); } }
    }
}

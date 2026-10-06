<?php

declare(strict_types=1);
namespace App\Form;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
final class MemberContactPersonType extends AbstractType
{
    public function getParent(): string { return ContactPersonType::class; }
    public function buildForm(FormBuilderInterface $builder, array $options): void { $builder->remove('active'); }
}

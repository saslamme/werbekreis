<?php

declare(strict_types=1);
namespace App\Form;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
final class MemberCompanyType extends AbstractType
{
    public function getParent(): string { return CompanyType::class; }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['company', 'slug', 'active', 'featured', 'status', 'publishedAt'] as $field) { if ($builder->has($field)) { $builder->remove($field); } }
        $builder->add('images', \Symfony\Component\Form\Extension\Core\Type\CollectionType::class, ['label' => 'Bilder', 'entry_type' => CompanyImageType::class, 'entry_options' => ['label' => false], 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false]);
        $builder->add('contactPersons', \Symfony\Component\Form\Extension\Core\Type\CollectionType::class, ['label' => 'Ansprechpartner', 'entry_type' => MemberContactPersonType::class, 'entry_options' => ['label' => false, 'company' => $builder->getData()], 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false]);
    }
}

<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BoardMember;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BoardMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Name', 'empty_data' => ''])
            ->add('role', TextType::class, ['label' => 'Funktion', 'empty_data' => ''])
            ->add('position', IntegerType::class, ['label' => 'Position', 'attr' => ['min' => 0]])
            ->add('active', CheckboxType::class, ['label' => 'Aktiv', 'required' => false, 'help' => 'Ehemalige Vorstandsmitglieder deaktivieren.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => BoardMember::class]);
    }
}

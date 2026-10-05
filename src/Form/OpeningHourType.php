<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\OpeningHour;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OpeningHourType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('dayOfWeek', ChoiceType::class, ['label' => 'Wochentag', 'choices' => ['Montag' => 1, 'Dienstag' => 2, 'Mittwoch' => 3, 'Donnerstag' => 4, 'Freitag' => 5, 'Samstag' => 6, 'Sonntag' => 7]])
            ->add('opensAt', TimeType::class, ['label' => 'Öffnet um', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('closesAt', TimeType::class, ['label' => 'Schließt um', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('closed', CheckboxType::class, ['label' => 'Geschlossen (Uhrzeiten leer lassen)', 'required' => false])
            ->add('position', IntegerType::class, ['label' => 'Reihenfolge']);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OpeningHour::class]);
    }
}

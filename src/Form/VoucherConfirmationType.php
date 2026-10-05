<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, HiddenType};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class VoucherConfirmationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('requestKey', HiddenType::class, ['constraints' => [new Assert\Regex('/^[a-f0-9]{64}$/D')]])
            ->add('confirm', CheckboxType::class, ['label' => 'Ich bestätige Betrag und Akzeptanzstelle.', 'constraints' => [new Assert\IsTrue(message: 'Bitte die Einlösung ausdrücklich bestätigen.')]]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => null]); }
}

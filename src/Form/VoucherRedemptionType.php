<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Company;
use App\Service\DecimalAmount;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{HiddenType, TextareaType, TextType};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class VoucherRedemptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('lookupKey', HiddenType::class, ['constraints' => [new Assert\Regex('/^[a-f0-9]{64}$/D')]])
            ->add('amount', DecimalAmountType::class, ['label' => 'Einlösebetrag', 'required' => true, 'constraints' => [new Assert\NotBlank(), new Assert\Regex(DecimalAmount::PATTERN)]])
            ->add('company', EntityType::class, ['class' => Company::class, 'choice_label' => 'name', 'label' => 'Akzeptanzstelle', 'choices' => $options['companies'], 'placeholder' => 'Bitte wählen', 'constraints' => [new Assert\NotNull()]])
            ->add('reference', TextType::class, ['label' => 'Referenz', 'required' => false, 'constraints' => [new Assert\Length(max: 180)]])
            ->add('note', TextareaType::class, ['label' => 'Interne Notiz', 'required' => false, 'constraints' => [new Assert\Length(max: 2000)]]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => null, 'companies' => []]); $resolver->setAllowedTypes('companies', 'array'); }
}

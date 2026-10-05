<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\{AbstractType, CallbackTransformer, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DecimalAmountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?string $value): ?string => $value !== null ? str_replace('.', ',', $value) : null,
            static fn (?string $value): ?string => $value !== null ? str_replace(',', '.', trim($value)) : null,
        ));
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['required' => false, 'attr' => ['inputmode' => 'decimal'], 'help' => 'Eurobetrag, zum Beispiel 12,50; ohne Tausendertrennzeichen.']); }
    public function getParent(): string { return TextType::class; }
}

<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class VoucherLookupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, ['label' => 'Gutscheincode', 'empty_data' => '', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 64)], 'attr' => ['autocomplete' => 'off', 'spellcheck' => 'false', 'autocapitalize' => 'characters']]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => null]); }
}

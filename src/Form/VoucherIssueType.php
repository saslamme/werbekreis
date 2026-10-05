<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\VoucherProduct;
use App\Service\DecimalAmount;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, DateTimeType, TextareaType};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class VoucherIssueType extends AbstractType
{
    public function __construct(#[Autowire('%portal.timezone%')] private readonly string $timezone) {}
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('product', EntityType::class, ['class' => VoucherProduct::class, 'choice_label' => 'name', 'label' => 'Gutscheinprodukt', 'placeholder' => 'Bitte wählen', 'constraints' => [new Assert\NotNull()], 'query_builder' => static fn ($repo) => $repo->createQueryBuilder('product')->where('product.active = true')->orderBy('product.position', 'ASC')])
            ->add('amount', DecimalAmountType::class, ['label' => 'Gutscheinwert', 'constraints' => [new Assert\Regex(DecimalAmount::PATTERN)], 'help' => 'Bei einem festen Produktwert leer lassen; dessen Wert kann nicht geändert werden.'])
            ->add('validFrom', DateTimeType::class, ['label' => 'Gültig ab', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => $this->timezone])
            ->add('note', TextareaType::class, ['label' => 'Interne Notiz', 'required' => false, 'constraints' => [new Assert\Length(max: 2000)]])
            ->add('activate', CheckboxType::class, ['label' => 'Sofort aktivieren', 'required' => false]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => null]); }
}

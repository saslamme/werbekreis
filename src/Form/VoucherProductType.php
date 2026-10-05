<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\{Company, VoucherProduct};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, IntegerType, TextareaType, TextType};
use Symfony\Component\OptionsResolver\OptionsResolver;

final class VoucherProductType extends AbstractType
{
    public function __construct(private readonly DirectorySlugSubscriber $slugs) {}
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'Name', 'empty_data' => ''])->add('slug', TextType::class, ['label' => 'Slug', 'required' => false, 'empty_data' => '', 'help' => 'Leer lassen für automatische Vergabe.'])
            ->add('description', TextareaType::class, ['label' => 'Beschreibung', 'empty_data' => '', 'help' => 'Klartext, HTML wird als Text ausgegeben.'])
            ->add('terms', TextareaType::class, ['label' => 'Hinweise', 'required' => false])->add('featured', CheckboxType::class, ['label' => 'Hervorgehoben', 'required' => false])
            ->add('position', IntegerType::class, ['label' => 'Sortierung']);
        if ($options['financial_admin']) {
            $builder->add('active', CheckboxType::class, ['label' => 'Aktiv', 'required' => false])->add('validityMonths', IntegerType::class, ['label' => 'Gültigkeit ab Aktivierung (Monate)', 'required' => false]);
            foreach (['minimumAmount' => 'Mindestwert', 'maximumAmount' => 'Höchstwert', 'fixedAmount' => 'Fester Wert'] as $field => $label) { $builder->add($field, DecimalAmountType::class, ['label' => $label]); }
            foreach (['acceptingCompanies' => 'Akzeptanzstellen', 'sellingCompanies' => 'Verkaufsstellen'] as $field => $label) {
                $builder->add($field, EntityType::class, ['class' => Company::class, 'choice_label' => 'name', 'label' => $label, 'multiple' => true, 'required' => false, 'by_reference' => false,
                    'query_builder' => static fn ($repo) => $repo->createQueryBuilder('company')->orderBy('company.name', 'ASC')]);
            }
        }
        $builder->addEventSubscriber($this->slugs);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => VoucherProduct::class, 'financial_admin' => false]); $resolver->setAllowedTypes('financial_admin', 'bool'); }
}

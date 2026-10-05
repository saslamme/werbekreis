<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Company;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CompanyType extends AbstractType
{
    public function __construct(private readonly DirectorySlugSubscriber $slugs)
    {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', options: ['label' => 'Name'])
            ->add('slug', options: ['label' => 'Slug', 'required' => false, 'empty_data' => '', 'help' => 'Automatisch bei leerem Feld; beim Bearbeiten standardmäßig unverändert.'])
            ->add('shortDescription', TextareaType::class, ['label' => 'Kurzbeschreibung', 'required' => false])
            ->add('description', TextareaType::class, ['label' => 'Beschreibung', 'required' => false, 'attr' => ['rows' => 6]])
            ->add('categories', EntityType::class, ['class' => Category::class, 'choice_label' => 'name', 'label' => 'Kategorien', 'required' => false, 'multiple' => true, 'by_reference' => false, 'query_builder' => static fn ($repository) => $repository->createQueryBuilder('category')->orderBy('category.position', 'ASC')->addOrderBy('category.name', 'ASC'), 'help' => 'Für aktive Unternehmen mindestens eine Kategorie wählen.'])
            ->add('active', CheckboxType::class, ['label' => 'Aktiv', 'required' => false])
            ->add('featured', CheckboxType::class, ['label' => 'Hervorgehoben', 'required' => false]);
        foreach (['street' => 'Straße', 'houseNumber' => 'Hausnummer', 'postalCode' => 'PLZ', 'city' => 'Ort', 'phone' => 'Telefon'] as $name => $label) {
            $builder->add($name, options: ['label' => $label, 'required' => false]);
        }
        $builder->add('email', EmailType::class, ['label' => 'E-Mail', 'required' => false]);
        foreach (['website' => 'Website', 'facebookUrl' => 'Facebook', 'instagramUrl' => 'Instagram'] as $name => $label) {
            $builder->add($name, UrlType::class, ['label' => $label, 'required' => false, 'default_protocol' => null]);
        }
        foreach (['latitude' => 'Breitengrad', 'longitude' => 'Längengrad'] as $name => $label) {
            $builder->add($name, NumberType::class, ['label' => $label, 'required' => false, 'scale' => 7]);
        }
        $builder->add('openingHours', CollectionType::class, ['label' => false, 'entry_type' => OpeningHourType::class, 'entry_options' => ['label' => false], 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false])
            ->add('contactPersons', CollectionType::class, ['label' => false, 'entry_type' => ContactPersonType::class, 'entry_options' => ['label' => false, 'company' => $builder->getData()], 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false])
            ->addEventSubscriber($this->slugs);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Company::class]);
    }
}

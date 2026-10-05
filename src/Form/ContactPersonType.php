<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Company;
use App\Entity\CompanyImage;
use App\Entity\ContactPerson;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactPersonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('firstName', options: ['label' => 'Vorname'])->add('lastName', options: ['label' => 'Nachname'])
            ->add('position', options: ['label' => 'Funktion', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'E-Mail', 'required' => false])
            ->add('phone', options: ['label' => 'Telefon', 'required' => false])->add('mobile', options: ['label' => 'Mobil', 'required' => false])
            ->add('image', EntityType::class, ['class' => CompanyImage::class, 'choice_label' => 'altText', 'label' => 'Bild', 'required' => false, 'query_builder' => static function (EntityRepository $repository) use ($options) {
                $query = $repository->createQueryBuilder('image')->orderBy('image.position', 'ASC');
                return $options['company']?->getId() !== null ? $query->where('image.company = :company')->setParameter('company', $options['company']) : $query->where('1 = 0');
            }])
            ->add('primaryContact', CheckboxType::class, ['label' => 'Primärer Kontakt', 'required' => false])
            ->add('sortOrder', IntegerType::class, ['label' => 'Reihenfolge'])
            ->add('active', CheckboxType::class, ['label' => 'Aktiv', 'required' => false]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContactPerson::class, 'company' => null]);
        $resolver->setAllowedTypes('company', [Company::class, 'null']);
    }
}

<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CompanyImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CompanyImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $constraints = [new Assert\Image(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], maxWidth: 8000, maxHeight: 8000)];
        if ($options['is_new']) {
            $constraints[] = new Assert\NotNull();
        }
        $builder->add('file', FileType::class, ['label' => 'Bilddatei', 'mapped' => false, 'required' => $options['is_new'], 'constraints' => $constraints, 'help' => 'JPEG, PNG oder WebP; maximal 5 MB und 8000 × 8000 Pixel. Beim Bearbeiten leer lassen, um das Bild zu behalten.'])
            ->add('altText', options: ['label' => 'Alternativtext'])
            ->add('title', options: ['label' => 'Titel', 'required' => false])
            ->add('type', ChoiceType::class, ['label' => 'Typ', 'choices' => ['Logo' => 'logo', 'Titelbild' => 'cover', 'Galerie' => 'gallery']])
            ->add('position', IntegerType::class, ['label' => 'Reihenfolge']);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CompanyImage::class, 'is_new' => false]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}

<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContentPageSection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContentPageSectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('heading', TextType::class, ['label' => 'Überschrift', 'required' => false])
            ->add('body', TextareaType::class, ['label' => 'Text', 'empty_data' => '', 'help' => 'Klartext, kein HTML. Zeilenumbrüche bleiben erhalten.'])
            ->add('style', ChoiceType::class, ['label' => 'Darstellung', 'choices' => ['Standard' => 'default', 'Feature-Karte' => 'feature', 'Hinweis' => 'notice']])
            ->add('position', IntegerType::class, ['label' => 'Position', 'attr' => ['min' => 0]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContentPageSection::class]);
    }
}

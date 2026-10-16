<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContentPage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContentPageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titel', 'empty_data' => ''])
            ->add('lead', TextareaType::class, ['label' => 'Einleitung', 'required' => false])
            ->add('metaTitle', TextType::class, ['label' => 'Meta-Titel', 'required' => false])
            ->add('metaDescription', TextareaType::class, ['label' => 'Meta-Beschreibung', 'required' => false])
            ->add('showDefaultHeader', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, ['label' => 'Standard-Seitenkopf anzeigen', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContentPage::class]);
    }
}

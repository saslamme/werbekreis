<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\EventCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EventCategoryType extends AbstractType
{
    public function __construct(private readonly DirectorySlugSubscriber $slugs)
    {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', options: ['label' => 'Name'])
            ->add('slug', options: ['label' => 'Slug', 'required' => false, 'empty_data' => '', 'help' => 'Leer lassen für automatische Vergabe. Bestehende Slugs bleiben bei Namensänderungen erhalten.'])
            ->add('description', TextareaType::class, ['label' => 'Beschreibung', 'required' => false])
            ->add('icon', options: ['label' => 'Font-Awesome-Icon', 'required' => false, 'help' => 'Zum Beispiel fa-bag-shopping.'])
            ->add('position', IntegerType::class, ['label' => 'Sortierung'])
            ->add('active', CheckboxType::class, ['label' => 'Aktiv', 'required' => false])
            ->addEventSubscriber($this->slugs);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => EventCategory::class]);
    }
}

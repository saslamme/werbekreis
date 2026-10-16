<?php
declare(strict_types=1);
namespace App\Form;
use App\Entity\ContentMedia;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{TextType, TextareaType, CheckboxType, IntegerType};
use Symfony\Component\OptionsResolver\OptionsResolver;
final class ContentMediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $builder->add('altText', TextType::class, ['label' => 'Alt-Text', 'required' => false, 'empty_data' => ''])
            ->add('decorative', CheckboxType::class, ['label' => 'Dekoratives Bild', 'required' => false])
            ->add('caption', TextareaType::class, ['label' => 'Bildunterschrift', 'required' => false])
            ->add('position', IntegerType::class, ['label' => 'Bildposition']);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => ContentMedia::class]); }
}

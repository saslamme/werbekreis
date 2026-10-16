<?php
declare(strict_types=1);
namespace App\Form;
use App\Entity\{ContentElement, Company, Event};
use App\Content\ElementTypes;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{FormType, TextType, TextareaType, CheckboxType, ChoiceType, IntegerType, FileType, CollectionType};
use Symfony\Component\OptionsResolver\OptionsResolver;
final class ContentElementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $element = $options['data'];
        $builder->add('active', CheckboxType::class, ['label' => 'Aktiv', 'required' => false])
            ->add('anchor', TextType::class, ['label' => 'Anker', 'required' => false]);
        $config = $builder->create('data', FormType::class, ['label' => 'Inhalt', 'data_class' => null]);
        foreach (ElementTypes::fields($element->getType()) as $key => [$label, $kind, $default]) {
            $field = ElementTypes::fields($element->getType())[$key];
            $type = match ($kind) { 'bool' => CheckboxType::class, 'choice' => ChoiceType::class, 'integer' => IntegerType::class, 'textarea' => TextareaType::class, default => TextType::class };
            $opts = ['label' => $label, 'required' => in_array($kind, ['integer', 'choice', 'video'], true)];
            if ($kind === 'choice') { $opts['choices'] = array_combine($field[3], $field[3]); }
            if (in_array($kind, ['text', 'textarea', 'url', 'email', 'phone'], true)) { $opts['empty_data'] = ''; }
            $config->add($key, $type, $opts);
        }
        $builder->add($config);
        if ($element->getType() === 'event') { $builder->add('linkedEvent', EntityType::class, ['class' => Event::class, 'choice_label' => 'title', 'label' => 'Veranstaltung (auch nicht öffentliche)', 'placeholder' => 'Bitte wählen']); }
        if ($element->getType() === 'company') { $builder->add('linkedCompany', EntityType::class, ['class' => Company::class, 'choice_label' => 'name', 'label' => 'Unternehmen (auch nicht öffentliche)', 'placeholder' => 'Bitte wählen']); }
        if (in_array($element->getType(), ['hero','image','gallery','image_slider','contact_person','cta','video'], true)) {
            $builder->add('media', CollectionType::class, ['entry_type' => ContentMediaType::class, 'allow_delete' => true, 'by_reference' => false, 'label' => 'Vorhandene Bilder']);
            $builder->add('uploads', FileType::class, ['mapped' => false, 'multiple' => true, 'required' => false, 'label' => 'Bilder hinzufügen (JPEG/PNG/WebP, je maximal 5 MB)', 'attr' => ['accept' => 'image/jpeg,image/png,image/webp']]);
            $builder->add('uploadAlt', TextType::class, ['mapped' => false, 'required' => false, 'label' => 'Alt-Text für neue Bilder (danach einzeln editierbar)', 'constraints' => [new \Symfony\Component\Validator\Constraints\Length(max: 255)]]);
            $builder->add('uploadDecorative', CheckboxType::class, ['mapped' => false, 'required' => false, 'label' => 'Neue Bilder sind dekorativ']);
        }
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => ContentElement::class]); }
}

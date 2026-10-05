<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Company;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\Entity\Offer;
use App\Enum\OfferType as OfferKind;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OfferType extends AbstractType
{
    public function __construct(private readonly DirectorySlugSubscriber $slugs, #[Autowire('%portal.timezone%')] private readonly string $timezone)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('company', EntityType::class, ['class' => Company::class, 'choice_label' => 'name', 'label' => 'Unternehmen', 'placeholder' => 'Bitte wählen', 'query_builder' => static fn ($repository) => $repository->createQueryBuilder('company')->orderBy('company.name', 'ASC')])
            ->add('title', TextType::class, ['label' => 'Titel'])
            ->add('slug', TextType::class, ['label' => 'Slug', 'required' => false, 'empty_data' => '', 'help' => 'Leer lassen für automatische Vergabe. Titeländerungen verändern bestehende URLs nicht.'])
            ->add('type', EnumType::class, ['class' => OfferKind::class, 'choice_label' => static fn (OfferKind $type): string => $type->label(), 'label' => 'Typ'])
            ->add('shortDescription', TextareaType::class, ['label' => 'Kurzbeschreibung', 'required' => false])
            ->add('description', TextareaType::class, ['label' => 'Beschreibung', 'required' => false, 'attr' => ['rows' => 5]])
            ->add('active', CheckboxType::class, ['label' => 'Aktiv', 'required' => false])
            ->add('featured', CheckboxType::class, ['label' => 'Hervorgehoben', 'required' => false]);
        foreach (['startsAt' => 'Beginn', 'endsAt' => 'Ende'] as $field => $label) {
            $builder->add($field, DateTimeType::class, ['label' => $label, 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => $this->timezone, 'help' => 'Ortszeit '.$this->timezone.'. Leer lassen für einen offenen Zeitraum; die Grenze selbst ist eingeschlossen.']);
        }
        foreach (['regularPrice' => 'Regulärer Preis (€)', 'offerPrice' => 'Angebotspreis (€)'] as $field => $label) {
            $builder->add($field, TextType::class, ['label' => $label, 'required' => false, 'attr' => ['inputmode' => 'decimal'], 'help' => 'Zum Beispiel 59,90; ohne Tausendertrennzeichen. Preise sind optional.']);
            // Preserve decimal strings end to end: no floating point money conversion or silent rounding.
            $builder->get($field)->addModelTransformer(new CallbackTransformer(
                static fn (?string $value): ?string => $value !== null ? str_replace('.', ',', $value) : null,
                static fn (?string $value): ?string => $value !== null ? str_replace(',', '.', trim($value)) : null,
            ));
        }
        $builder->add('discountText', TextType::class, ['label' => 'Rabatttext', 'required' => false])
            ->add('file', FileType::class, ['label' => 'Angebotsbild', 'mapped' => false, 'required' => false, 'constraints' => CompanyImageType::imageConstraints(), 'help' => 'JPEG, PNG, WebP; maximal 5 MB und 8000 × 8000 Pixel. Leer lassen, um das bestehende Bild zu behalten.'])
            ->add('imageAlt', TextType::class, ['label' => 'Bild-Alternativtext', 'required' => false, 'help' => 'Ohne Angabe wird der Angebotstitel verwendet.'])
            ->add('removeImage', CheckboxType::class, ['label' => 'Vorhandenes Bild entfernen', 'mapped' => false, 'required' => false])
            ->add('externalUrl', UrlType::class, ['label' => 'Externe URL', 'required' => false, 'default_protocol' => null])
            ->add('terms', TextareaType::class, ['label' => 'Bedingungen', 'required' => false])
            ->addEventSubscriber($this->slugs);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Offer::class]);
    }
}

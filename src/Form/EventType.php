<?php

declare(strict_types=1);
namespace App\Form;

use App\Entity\Company;
use App\Entity\Event;
use App\Entity\EventCategory;
use App\Enum\EventRecurrence;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, DateTimeType, DateType, EnumType, FileType, NumberType, TextareaType, TextType, EmailType, UrlType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EventType extends AbstractType
{
    public function __construct(private readonly DirectorySlugSubscriber $slugs, #[Autowire('%portal.timezone%')] private readonly string $timezone) {}
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, ['label' => 'Titel'])
            ->add('slug', TextType::class, ['label' => 'Slug', 'required' => false, 'empty_data' => '', 'help' => 'Leer lassen für automatische Vergabe. Titeländerungen erhalten bestehende URLs.'])
            ->add('shortDescription', TextareaType::class, ['label' => 'Kurzbeschreibung', 'required' => false])
            ->add('description', TextareaType::class, ['label' => 'Beschreibung', 'required' => false])
            ->add('company', EntityType::class, ['class' => Company::class, 'choice_label' => 'name', 'label' => 'Unternehmen', 'required' => false, 'placeholder' => 'Ohne Unternehmen', 'query_builder' => static fn ($r) => $r->createQueryBuilder('company')->orderBy('company.name', 'ASC')])
            ->add('categories', EntityType::class, ['class' => EventCategory::class, 'choice_label' => 'name', 'label' => 'Veranstaltungskategorien', 'multiple' => true, 'required' => false, 'by_reference' => false, 'query_builder' => static fn ($r) => $r->createQueryBuilder('category')->orderBy('category.position', 'ASC')->addOrderBy('category.name', 'ASC')]);
        foreach (['active' => 'Aktiv', 'featured' => 'Hervorgehoben', 'allDay' => 'Ganztägig', 'freeAdmission' => 'Eintritt frei', 'cancelled' => 'Abgesagt'] as $field => $label) { $builder->add($field, CheckboxType::class, ['label' => $label, 'required' => false]); }
        foreach (['startsAt' => 'Beginn', 'endsAt' => 'Ende'] as $field => $label) {
            $builder->add($field, DateTimeType::class, ['label' => $label, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => $this->timezone, 'help' => 'Ortszeit '.$this->timezone.'. Bei ganztägigen Veranstaltungen zählt nur das Datum.']);
        }
        $builder->add('recurrenceType', EnumType::class, ['class' => EventRecurrence::class, 'choice_label' => static fn (EventRecurrence $type): string => $type->label(), 'label' => 'Wiederholung'])
            ->add('recurrenceUntil', DateType::class, ['label' => 'Letzter Wiederholungstag', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false, 'model_timezone' => 'UTC', 'view_timezone' => 'UTC', 'help' => 'Pflicht bei Serien, höchstens ein Jahr. Monatlich: nicht vorhandene Monatstage werden übersprungen.'])
            ->add('file', FileType::class, ['label' => 'Veranstaltungsbild', 'mapped' => false, 'required' => false, 'constraints' => CompanyImageType::imageConstraints(), 'help' => 'JPEG, PNG oder WebP; maximal 5 MB und 8000 × 8000 Pixel.'])
            ->add('removeImage', CheckboxType::class, ['label' => 'Vorhandenes Bild entfernen', 'mapped' => false, 'required' => false]);
        foreach (['organizerName' => 'Veranstaltername', 'organizerEmail' => 'Veranstalter E-Mail', 'organizerPhone' => 'Veranstalter Telefon', 'organizerWebsite' => 'Veranstalter Website', 'locationName' => 'Name des Veranstaltungsorts', 'street' => 'Straße', 'houseNumber' => 'Hausnummer', 'postalCode' => 'PLZ', 'city' => 'Ort', 'imageAlt' => 'Bild-Alternativtext', 'admissionText' => 'Eintrittstext', 'externalUrl' => 'Externe URL', 'ticketUrl' => 'Externer Ticketlink', 'cancellationNotice' => 'Absagehinweis'] as $field => $label) {
            $type = match ($field) { 'organizerEmail' => EmailType::class, 'organizerWebsite', 'externalUrl', 'ticketUrl' => UrlType::class, 'cancellationNotice' => TextareaType::class, default => TextType::class };
            $fieldOptions = ['label' => $label, 'required' => false];
            if ($type === UrlType::class) { $fieldOptions['default_protocol'] = null; }
            $builder->add($field, $type, $fieldOptions);
        }
        foreach (['latitude' => 'Breitengrad', 'longitude' => 'Längengrad'] as $field => $label) { $builder->add($field, NumberType::class, ['label' => $label, 'required' => false, 'scale' => 7]); }
        $builder->addEventSubscriber($this->slugs);

    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => Event::class]); }
}

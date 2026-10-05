<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\{Company, JobPosting};
use App\Enum\{EmploymentType, WorkModel, SalaryPeriod, NewsStatus};
use App\Service\NewsPublishing;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\{AbstractType, CallbackTransformer, FormBuilderInterface, FormEvent, FormEvents, FormError};
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, CountryType, DateTimeType, DateType, EnumType, EmailType, NumberType, TextareaType, TextType, UrlType};
use Symfony\Component\OptionsResolver\OptionsResolver;

final class JobPostingType extends AbstractType
{
    public function __construct(private readonly DirectorySlugSubscriber $slugs, private readonly NewsPublishing $publishing, #[Autowire('%portal.timezone%')] private readonly string $timezone) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $job = $options['data'] ?? null;
        $originalStatus = $job instanceof JobPosting ? $job->getStatus() : NewsStatus::Draft;
        $originalDate = $job instanceof JobPosting ? $job->getPublishedAt() : null;
        $builder->add('company', EntityType::class, ['class' => Company::class, 'choice_label' => 'name', 'label' => 'Unternehmen', 'placeholder' => 'Bitte wählen', 'query_builder' => static fn ($r) => $r->createQueryBuilder('company')->orderBy('company.name', 'ASC')])
            ->add('title', TextType::class, ['label' => 'Titel', 'empty_data' => ''])
            ->add('slug', TextType::class, ['label' => 'Slug', 'required' => false, 'empty_data' => '', 'help' => 'Leer lassen für automatische Vergabe. Titeländerungen behalten bestehende URLs.'])
            ->add('shortDescription', TextareaType::class, ['label' => 'Kurzbeschreibung', 'empty_data' => '', 'help' => 'Maximal 500 Zeichen.'])
            ->add('description', TextareaType::class, ['label' => 'Beschreibung', 'empty_data' => '', 'attr' => ['rows' => 10], 'help' => 'Klartext mit Absätzen. HTML wird als Text ausgegeben.'])
            ->add('requirements', TextareaType::class, ['label' => 'Anforderungen', 'required' => false])
            ->add('benefits', TextareaType::class, ['label' => 'Benefits', 'required' => false])
            ->add('featured', CheckboxType::class, ['label' => 'Hervorgehoben', 'required' => false]);
        foreach (['employmentType' => [EmploymentType::class, 'Beschäftigungsart'], 'workModel' => [WorkModel::class, 'Arbeitsmodell'], 'status' => [NewsStatus::class, 'Status'], 'salaryPeriod' => [SalaryPeriod::class, 'Gehaltszeitraum']] as $field => [$class, $label]) {
            $builder->add($field, EnumType::class, ['class' => $class, 'choice_label' => static fn ($enum): string => $enum->label(), 'label' => $label, 'required' => $field !== 'salaryPeriod', 'placeholder' => $field === 'salaryPeriod' ? 'Keine Gehaltsangabe' : false]);
        }
        foreach (['locationName' => 'Bezeichnung des Arbeitsorts', 'street' => 'Straße', 'houseNumber' => 'Hausnummer', 'postalCode' => 'Postleitzahl', 'city' => 'Ort', 'contactName' => 'Ansprechpartner', 'contactPhone' => 'Telefon', 'referenceNumber' => 'Referenznummer'] as $field => $label) {
            $builder->add($field, TextType::class, ['label' => $label, 'required' => false] + ($field === 'city' ? ['empty_data' => '', 'help' => 'Pflicht bei Vor Ort und Hybrid.'] : []));
        }
        foreach (['latitude' => 'Breitengrad', 'longitude' => 'Längengrad'] as $field => $label) {
            $builder->add($field, NumberType::class, ['label' => $label, 'required' => false, 'scale' => 8, 'html5' => true, 'attr' => ['step' => 'any'], 'help' => 'Beide Koordinaten angeben oder beide leer lassen.']);
        }
        $builder->add('addressCountry', CountryType::class, ['label' => 'Land des Arbeitsorts', 'required' => false, 'placeholder' => 'Keine Länderangabe', 'help' => 'Tatsächliches Land für strukturierte Job-Daten; keine Einschränkung für Remote-Bewerber.']);
        $builder->add('applicationEmail', EmailType::class, ['label' => 'Bewerbungs-E-Mail', 'required' => false, 'help' => 'Ohne Bewerbungs-URL oder eigene E-Mail wird die E-Mail des Unternehmens verwendet.'])
            ->add('applicationUrl', UrlType::class, ['label' => 'Externe Bewerbungs-URL', 'required' => false, 'default_protocol' => null]);
        $builder->add('publishedAt', DateTimeType::class, ['label' => 'Veröffentlichungszeitpunkt', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => $this->timezone,
            'help' => 'Ortszeit '.$this->timezone.'. Neue Planungen müssen in der Zukunft liegen. Beim Veröffentlichen ohne Datum wird die aktuelle Zeit gesetzt.']);
        $builder->add('validThrough', DateType::class, ['label' => 'Letzter Bewerbungstag', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => $this->timezone,
            'help' => 'Bis zum Ende dieses Tages in '.$this->timezone.' sichtbar; leer lassen für eine offene Frist.']);
        // Explicit day conversion handles short/long DST days and round-trips existing deadlines.
        $builder->get('validThrough')->addModelTransformer(new CallbackTransformer(
            fn (?\DateTimeImmutable $value): ?\DateTimeImmutable => $value?->setTimezone(new \DateTimeZone($this->timezone))->setTime(0, 0)->setTimezone(new \DateTimeZone('UTC')),
            fn (?\DateTimeImmutable $value): ?\DateTimeImmutable => $value?->setTimezone(new \DateTimeZone($this->timezone))->setTime(23, 59, 59)->setTimezone(new \DateTimeZone('UTC')),
        ));
        $builder->add('startsAt', DateType::class, ['label' => 'Geplanter Arbeitsbeginn', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => $this->timezone]);
        foreach (['salaryMin' => 'Mindestgehalt (€)', 'salaryMax' => 'Höchstgehalt (€)'] as $field => $label) {
            $builder->add($field, TextType::class, ['label' => $label, 'required' => false, 'attr' => ['inputmode' => 'decimal'], 'help' => 'Zum Beispiel 2500,50; ohne Tausendertrennzeichen.'])
                ->get($field)->addModelTransformer(new CallbackTransformer(
                    static fn (?string $value): ?string => $value !== null ? str_replace('.', ',', $value) : null,
                    static fn (?string $value): ?string => $value !== null ? str_replace(',', '.', trim($value)) : null,
                ));
        }
        $builder->addEventSubscriber($this->slugs)->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($originalStatus, $originalDate): void {
            $form = $event->getForm(); $job = $form->getData();
            if (!$form->isSynchronized() || !$job instanceof JobPosting) { return; }
            if ($originalDate !== null && $job->getPublishedAt() !== null) {
                $zone = new \DateTimeZone($this->timezone);
                if ($originalDate->setTimezone($zone)->format('Y-m-d H:i') === $job->getPublishedAt()->setTimezone($zone)->format('Y-m-d H:i')) { $job->setPublishedAt($originalDate); }
            }
            if ($this->publishing->invalidScheduleChange($job, $originalStatus, $originalDate)) { $form->get('publishedAt')->addError(new FormError('Bitte einen zukünftigen Veröffentlichungszeitpunkt wählen.')); }
            // Validate the implicit publication timestamp before persistence, including deadline consistency.
            $this->publishing->prepareForSave($job);
        }, 5);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => JobPosting::class]); }
}

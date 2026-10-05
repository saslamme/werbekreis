<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\{Company, NewsArticle, NewsCategory};
use App\Enum\NewsStatus;
use App\Service\NewsPublishing;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, DateTimeType, EnumType, FileType, TextareaType, TextType, UrlType};
use Symfony\Component\Form\{FormBuilderInterface, FormEvent, FormEvents, FormError};
use Symfony\Component\OptionsResolver\OptionsResolver;

final class NewsArticleType extends AbstractType
{
    public function __construct(private readonly DirectorySlugSubscriber $slugs, private readonly NewsPublishing $publishing, #[Autowire('%portal.timezone%')] private readonly string $timezone) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $article = $options['data'] ?? null;
        $originalStatus = $article instanceof NewsArticle ? $article->getStatus() : NewsStatus::Draft;
        $originalDate = $article instanceof NewsArticle ? $article->getPublishedAt() : null;
        $builder->add('title', TextType::class, ['label' => 'Titel', 'empty_data' => ''])
            ->add('slug', TextType::class, ['label' => 'Slug', 'required' => false, 'empty_data' => '', 'help' => 'Leer lassen für automatische Vergabe. Bestehende URLs bleiben bei Titeländerungen erhalten.'])
            ->add('teaser', TextareaType::class, ['label' => 'Kurztext', 'required' => false, 'empty_data' => '', 'help' => 'Maximal 500 Zeichen.'])
            ->add('content', TextareaType::class, ['label' => 'Inhalt', 'empty_data' => '', 'attr' => ['rows' => 12], 'help' => 'Klartext mit Absätzen. HTML wird als Text ausgegeben.'])
            ->add('categories', EntityType::class, ['class' => NewsCategory::class, 'choice_label' => 'name', 'label' => 'News-Kategorien', 'multiple' => true, 'required' => false, 'by_reference' => false, 'query_builder' => static fn ($r) => $r->createQueryBuilder('category')->orderBy('category.position', 'ASC')->addOrderBy('category.name', 'ASC')])
            ->add('company', EntityType::class, ['class' => Company::class, 'choice_label' => 'name', 'label' => 'Unternehmen', 'required' => false, 'placeholder' => 'Allgemeine Neuigkeit', 'query_builder' => static fn ($r) => $r->createQueryBuilder('company')->orderBy('company.name', 'ASC')])
            ->add('featured', CheckboxType::class, ['label' => 'Hervorgehoben', 'required' => false])
            ->add('status', EnumType::class, ['class' => NewsStatus::class, 'choice_label' => static fn (NewsStatus $status): string => $status->label(), 'label' => 'Status'])
            ->add('publishedAt', DateTimeType::class, ['label' => 'Veröffentlichungszeitpunkt', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => $this->timezone,
                'help' => 'Ortszeit '.$this->timezone.'. Bei neuer Planung ist ein zukünftiger Zeitpunkt erforderlich. Beim Veröffentlichen ohne Zeitpunkt wird die aktuelle Zeit gesetzt.'])
            ->add('file', FileType::class, ['label' => 'Titelbild', 'mapped' => false, 'required' => false, 'constraints' => CompanyImageType::imageConstraints(), 'help' => 'JPEG, PNG oder WebP; maximal 5 MB und 8000 × 8000 Pixel.'])
            ->add('imageAltText', TextType::class, ['label' => 'Bild-Alternativtext', 'required' => false])
            ->add('removeImage', CheckboxType::class, ['label' => 'Vorhandenes Bild entfernen', 'mapped' => false, 'required' => false])
            ->add('authorName', TextType::class, ['label' => 'Autor', 'required' => false])
            ->add('externalUrl', UrlType::class, ['label' => 'Externe URL', 'required' => false, 'default_protocol' => null])
            ->addEventSubscriber($this->slugs);
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($originalStatus, $originalDate): void {
            $form = $event->getForm(); $article = $form->getData();
            // A minute-precision input must not alter stored seconds (or an ambiguous DST hour) on a content-only edit.
            if ($form->isSynchronized() && $article instanceof NewsArticle && $originalDate !== null && $article->getPublishedAt() !== null) {
                $zone = new \DateTimeZone($this->timezone);
                if ($originalDate->setTimezone($zone)->format('Y-m-d H:i') === $article->getPublishedAt()->setTimezone($zone)->format('Y-m-d H:i')) {
                    $article->setPublishedAt($originalDate);
                }
            }
            if ($form->isSynchronized() && $article instanceof NewsArticle && $this->publishing->invalidScheduleChange($article, $originalStatus, $originalDate)) {
                $form->get('publishedAt')->addError(new FormError('Bitte einen zukünftigen Veröffentlichungszeitpunkt wählen.'));
            }
        }, 5);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => NewsArticle::class]); }
}

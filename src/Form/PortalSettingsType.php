<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PortalSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\{UrlType, FileType, CheckboxType, IntegerType};
use Symfony\Component\Form\{FormBuilderInterface, FormError, FormEvent, FormEvents};
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PortalSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('logoFile', FileType::class, [
                'label' => 'Website-Logo', 'mapped' => false, 'required' => false,
                'attr' => ['accept' => '.svg,.png,.jpg,.jpeg,image/svg+xml,image/png,image/jpeg'],
                'help' => 'SVG, PNG oder JPG/JPEG, maximal 5 MB. Ein neuer Upload hat Vorrang vor dem Entfernen.',
                'invalid_message' => \App\Service\PortalAssetStorage::UPLOAD_ERROR,
            ])
            ->add('logoMaxWidth', IntegerType::class, [
                'label' => 'Maximale Breite', 'required' => false,
                'help' => 'Maximale Breite des Logos in Pixeln. Leer lassen für den Standardwert.',
                'attr' => ['min' => 40, 'max' => 1000, 'step' => 1],
                'invalid_message' => 'Bitte eine ganze Zahl in Pixeln eingeben.',
            ])
            ->add('logoMaxHeight', IntegerType::class, [
                'label' => 'Maximale Höhe', 'required' => false,
                'help' => 'Maximale Höhe des Logos in Pixeln. Leer lassen für den Standardwert.',
                'attr' => ['min' => 20, 'max' => 400, 'step' => 1],
                'invalid_message' => 'Bitte eine ganze Zahl in Pixeln eingeben.',
            ])
            ->add('facebookUrl', UrlType::class, [
                'label' => 'Facebook', 'required' => false, 'default_protocol' => null,
                'help' => 'Vollständige URL zur Facebook-Seite. Leer lassen, um Facebook im Footer auszublenden.',
                'attr' => ['maxlength' => 2048],
            ])
            ->add('instagramUrl', UrlType::class, [
                'label' => 'Instagram', 'required' => false, 'default_protocol' => null,
                'help' => 'Vollständige URL zum Instagram-Profil. Leer lassen, um Instagram im Footer auszublenden.',
                'attr' => ['maxlength' => 2048],
            ]);
        // IntegerType's locale parser also accepts comma fractions and exponent notation.
        // Reject these before parsing so dimensions are never silently rounded.
        foreach (['logoMaxWidth', 'logoMaxHeight'] as $name) {
            $builder->get($name)->addViewTransformer(new CallbackTransformer(
                static fn ($value) => $value,
                static function ($value) {
                    if ($value !== null && $value !== '' && (!is_string($value) || !preg_match('/^-?[0-9]+$/D', $value))) {
                        throw new TransformationFailedException('Expected an integer pixel value.');
                    }
                    return $value;
                },
            ));
        }
        // Normalize PHP upload-limit/transport errors to the same actionable message.
        $builder->get('logoFile')->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $field = $event->getForm();
            if (count($field->getErrors()) > 0) {
                $field->clearErrors();
                $field->addError(new FormError(\App\Service\PortalAssetStorage::UPLOAD_ERROR));
            }
        });
        if ($options['data']?->getLogoFileName() !== null) {
            $builder->add('removeLogo', CheckboxType::class, [
                'label' => 'Vorhandenes Logo entfernen', 'mapped' => false, 'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PortalSettings::class]);
    }
}

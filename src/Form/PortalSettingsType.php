<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\PortalSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{UrlType, FileType, CheckboxType};
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

<?php

declare(strict_types=1);
namespace App\Form;
use App\Enum\ModerationStatus;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{EnumType, HiddenType, TextareaType};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
final class ReviewDecisionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('version',HiddenType::class,['constraints'=>[new Assert\Regex('/^\d+$/D')]])
            ->add('decision',EnumType::class,['class'=>ModerationStatus::class,'choices'=>[ModerationStatus::Approved,ModerationStatus::ChangesRequested,ModerationStatus::Rejected],'choice_label'=>static fn ($s)=>$s->label(),'label'=>'Entscheidung','placeholder'=>'Bitte wählen'])
            ->add('note',TextareaType::class,['label'=>'Prüfkommentar','required'=>false,'constraints'=>[new Assert\Length(max:2000)],'help'=>'Bei Ablehnung oder Änderungswunsch erforderlich. Für das Mitglied sichtbar.']);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class'=>null]); }
}

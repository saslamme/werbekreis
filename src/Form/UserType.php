<?php
declare(strict_types=1);
namespace App\Form;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $constraints = [new Assert\Length(min: 8, max: 4096)];
        if ($options['is_new']) { $constraints[] = new Assert\NotBlank(); }
        $builder->add('firstName', options: ['label' => 'Vorname'])
            ->add('lastName', options: ['label' => 'Nachname'])
            ->add('email', EmailType::class, ['label' => 'E-Mail'])
            ->add('plainPassword', PasswordType::class, ['label' => 'Passwort', 'mapped' => false, 'required' => $options['is_new'], 'constraints' => $constraints, 'help' => $options['is_new'] ? 'Mindestens 8 Zeichen.' : 'Leer lassen, um das bestehende Passwort beizubehalten.'])
            ->add('roles', ChoiceType::class, ['label' => 'Rollen', 'choices' => ['Admin' => 'ROLE_ADMIN', 'Editor' => 'ROLE_EDITOR', 'Mitglied' => 'ROLE_MEMBER'], 'expanded' => true, 'multiple' => true])
            ->add('active', CheckboxType::class, ['label' => 'Aktiv', 'required' => false]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'is_new' => false]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}

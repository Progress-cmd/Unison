<?php

namespace App\Admin\Form;

use App\Account\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

// No password here: the person chooses it from the link sent by email.
class UserCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Nom d\'utilisateur',
                // An empty field gives '' instead of null: the setter only accepts a string.
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: 'Choisis un nom d\'utilisateur.'),
                    new Assert\Length(max: 50, maxMessage: 'Le nom ne doit pas dépasser 50 caractères.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: 'Indique un email.'),
                    new Assert\Email(message: 'Cet email n\'est pas valide.'),
                    new Assert\Length(max: 180),
                ],
            ])
            // Not mapped: the controller turns it into ROLE_ADMIN.
            ->add('admin', CheckboxType::class, [
                'label' => 'Administrateur',
                'mapped' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

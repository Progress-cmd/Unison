<?php

namespace App\Account\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

// "Mot de passe oublié ?": the address of the account, the link goes there.
class ForgotPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['autocomplete' => 'email', 'autofocus' => true],
                'constraints' => [
                    new Assert\NotBlank(message: 'Indique ton adresse e-mail.'),
                    new Assert\Email(message: 'Cette adresse n\'est pas valide.'),
                ],
            ])
        ;
    }
}

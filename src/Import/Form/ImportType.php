<?php

namespace App\Import\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Ce champ ne peut pas être vide.'),
                    new Assert\Regex(pattern: '#^https://((www|music)\.)?(youtube\.com|youtu\.be)/#', message: 'Ce lien n\'est pas un lien YouTube.'),
                ],
                'label' => 'Lien YouTube',
            ])
        ;
    }
}

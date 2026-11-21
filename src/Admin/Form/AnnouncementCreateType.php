<?php

namespace App\Admin\Form;

use App\Announcement\Enum\AnnouncementKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

// Not mapped on the entity (its constructor takes everything): the controller builds it.
class AnnouncementCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('kind', EnumType::class, [
                'class' => AnnouncementKind::class,
                'expanded' => true,
                'choice_label' => fn (AnnouncementKind $kind) => $kind->label(),
                'label' => 'Type',
                'data' => AnnouncementKind::Feature,
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'empty_data' => '',
                'attr' => ['maxlength' => 120, 'placeholder' => 'Ex. : Les annonces arrivent'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Donne un titre à l\'annonce.'),
                    new Assert\Length(max: 120, maxMessage: 'Le titre ne doit pas dépasser 120 caractères.'),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Message',
                'empty_data' => '',
                'attr' => ['rows' => 5, 'maxlength' => 2000, 'placeholder' => 'Ce que tu veux leur dire. Les retours à la ligne sont gardés.'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Écris le message.'),
                    new Assert\Length(max: 2000, maxMessage: 'Le message ne doit pas dépasser 2000 caractères.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}

<?php

namespace App\Admin\Form;

use App\Account\Entity\User;
use App\Account\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

// Deleting an account: who gets its tracks, albums and playlists. Same idea as ArtistMergeType.
class UserDeleteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('heir', EntityType::class, [
                'label' => 'Donner son contenu à',
                'class' => User::class,
                'choice_label' => 'username',
                'placeholder' => 'Choisir un compte',
                // Every account but the one being deleted.
                'query_builder' => fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->andWhere('u != :exclude')
                    ->setParameter('exclude', $options['exclude'])
                    ->orderBy('u.username', 'ASC'),
                'constraints' => [
                    new Assert\NotNull(message: 'Choisis le compte qui reçoit le contenu.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('exclude');
        $resolver->setAllowedTypes('exclude', User::class);
    }
}

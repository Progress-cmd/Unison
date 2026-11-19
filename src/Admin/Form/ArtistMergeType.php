<?php

namespace App\Admin\Form;

use App\Library\Entity\Artist;
use App\Library\Repository\ArtistRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ArtistMergeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('target', EntityType::class, [
                'label' => 'Fusionner dans',
                'class' => Artist::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir l\'artiste à garder',
                'query_builder' => fn (ArtistRepository $repository) => $repository->createQueryBuilder('a')
                    ->andWhere('a != :exclude')
                    ->setParameter('exclude', $options['exclude'])
                    ->orderBy('a.name', 'ASC'),
                'constraints' => [
                    new Assert\NotNull(message: 'Choisis l\'artiste à garder.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('exclude');
        $resolver->setAllowedTypes('exclude', Artist::class);
    }
}


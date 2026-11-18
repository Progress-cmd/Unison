<?php

namespace App\Admin\Form;

use App\Library\Entity\Artist;
use App\Library\Entity\Track;
use App\Library\Repository\ArtistRepository;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TrackEditType extends AbstractType
{
    public function __construct(
        private readonly ArtistRepository $artistRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('artists', TextType::class, [
                'label' => 'Artistes',
                'help' => 'Séparés par des virgules.',
                'by_reference' => false,
                'constraints' => [
                    new Assert\Count(min: 1, minMessage: 'Indique au moins un artiste.'),
                ],
            ])
        ;

        $builder->get('artists')->addModelTransformer(new CallbackTransformer(
            fn (Collection $artists): string => implode(', ', $artists->map(fn (Artist $artist) => $artist->getName())->toArray()),
            fn (?string $text): array => array_map(
                $this->artistRepository->findOrCreate(...),
                array_values(array_unique(array_filter(array_map('trim', explode(',', $text ?? ''))))),
            ),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Track::class,
        ]);
    }
}

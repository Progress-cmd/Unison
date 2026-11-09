<?php

namespace App\Import\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('urls', TextareaType::class, [
                'constraints' => [
                    new Assert\Count(min: 1, max: 50, minMessage: 'Ajoute au moins un lien.', maxMessage: 'Pas plus de {{ limit }} liens à la fois.'),
                    new Assert\All(constraints: [new Assert\Regex(pattern: '#^https://((www|music)\.)?(youtube\.com|youtu\.be)/#', message: 'Ce lien n\'est pas un lien YouTube : {{ value }}')]),
                ],
                'label' => 'Liens YouTube',
                'help' => 'Un lien par ligne. Les playlists sont acceptées.',
                'attr' => ['rows' => 6],
            ])
        ;
        $builder->get('urls')->addModelTransformer(new CallbackTransformer(
            fn (?array $urls): string => implode("\n", $urls ?? []),
            fn (?string $text): array => array_values(array_unique(array_filter(array_map('trim', explode("\n", $text ?? '')))))
        ));
    }
}

<?php

namespace App\Form;

use App\Entity\Album;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Range;

class AlbumType extends AbstractType
{
    private const GENRES = [
        'Rock' => 'Rock',
        'Pop' => 'Pop',
        'Hip Hop' => 'Hip Hop',
        'Jazz' => 'Jazz',
        'Classical' => 'Classical',
        'Electronic' => 'Electronic',
        'R&B' => 'R&B',
        'Country' => 'Country',
        'Metal' => 'Metal',
        'Indie' => 'Indie',
        'Alternative' => 'Alternative',
        'Folk' => 'Folk',
        'Reggae' => 'Reggae',
        'Blues' => 'Blues',
        'Punk' => 'Punk',
        'Other' => 'Other',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Album Title',
            ])
            ->add('artist', TextType::class, [
                'label' => 'Artist Name',
            ])
            ->add('genre', ChoiceType::class, [
                'choices' => self::GENRES,
                'placeholder' => 'Select a genre',
            ])
            ->add('releaseYear', IntegerType::class, [
                'label' => 'Release Year',
                'required' => false,
                'constraints' => [
                    new Range(['min' => 1900, 'max' => date('Y') + 1]),
                ],
            ])
            ->add('trackList', TextareaType::class, [
                'label' => 'Track List (one per line)',
                'required' => false,
                'attr' => ['rows' => 10],
            ])
            ->add('coverImageFile', FileType::class, [
                'label' => 'Album Cover',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, or WebP)',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Album::class,
        ]);
    }
}

<?php

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Review Title',
                'required' => false,
            ])
            ->add('rating', ChoiceType::class, [
                'choices' => array_combine(range(1, 10), range(1, 10)),
                'label' => 'Rating (1-10)',
                'placeholder' => 'Select rating',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Your Review',
                'attr' => ['rows' => 8],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}

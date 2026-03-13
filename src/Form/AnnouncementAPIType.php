<?php

namespace App\Form;

use App\Entity\Announcement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for creating/validating Announcements via the API.
 * Same fields as AnnouncementType but with CSRF protection disabled,
 * since API clients (e.g. mobile apps, other services) don't use CSRF tokens.
 */
class AnnouncementAPIType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('message', TextType::class, [
            'label' => 'Message',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Announcement::class,
            'csrf_protection' => false,
        ]);
    }
}

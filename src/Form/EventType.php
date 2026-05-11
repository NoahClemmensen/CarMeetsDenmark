<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\SaveEventDTO;
use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('description')
            ->add('imageFile', FileType::class, [
                'label' => 'Banner image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '4M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        mimeTypesMessage: 'Please upload a valid image file (JPEG, PNG, GIF, or WEBP) with a maximum size of 4MB.',
                    ),
                ],
            ])
            ->add('removeImage', CheckboxType::class, [
                'label' => 'Remove current banner',
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SaveEventDTO::class,
        ]);
    }
}

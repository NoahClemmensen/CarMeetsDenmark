<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\SavePostDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('body', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Share something about this event...',
                    'rows' => 3,
                ],
            ])
            ->add('link', TextType::class, [
                'label' => 'Link (optional)',
                'required' => false,
                'attr' => ['placeholder' => 'https://...'],
            ])
            ->add('embedUrl', TextType::class, [
                'label' => 'YouTube or Instagram URL (optional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://youtube.com/... or https://instagram.com/p/...',
                    'data-post-composer-target' => 'embedInput',
                ],
            ])
            ->add('imageFiles', FileType::class, [
                'label' => 'Images (up to 4)',
                'multiple' => true,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '12M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        mimeTypesMessage: 'Please upload a valid image file (JPEG, PNG, GIF, or WEBP) with a maximum size of 4MB.',
                    ),
                ],
                'attr' => [
                    'accept' => 'image/*',
                    'data-post-composer-target' => 'imageInput',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SavePostDTO::class,
            'attr' => ['data-controller' => 'post-composer'],
        ]);
    }
}

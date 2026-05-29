<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\SaveTeamDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class TeamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bannerFile', FileType::class, [
                'label' => 'Banner image',
                'mapped' => false,
                'required' => false,
                'constraints' => [new File(
                    maxSize: '12M',
                    mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                    mimeTypesMessage: 'Please upload a JPEG, PNG, GIF, or WEBP image.',
                )],
            ])
            ->add('removeBanner', CheckboxType::class, [
                'label' => 'Remove current banner',
                'mapped' => false,
                'required' => false,
            ])
            ->add('profilePictureFile', FileType::class, [
                'label' => 'Profile picture',
                'mapped' => false,
                'required' => false,
                'constraints' => [new File(
                    maxSize: '4M',
                    mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                    mimeTypesMessage: 'Please upload a JPEG, PNG, GIF, or WEBP image.',
                )],
            ])
            ->add('removeProfilePicture', CheckboxType::class, [
                'label' => 'Remove current profile picture',
                'mapped' => false,
                'required' => false,
            ])
            ->add('name')
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SaveTeamDTO::class,
        ]);
    }
}

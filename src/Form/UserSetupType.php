<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\UserSetupDTO;
use App\Enum\UserRole;
use App\Form\Type\IconTextType;
use App\Form\Type\SegmentedChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class UserSetupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile picture',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '4M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        mimeTypesMessage: 'Please upload a valid image (JPEG, PNG, GIF, or WEBP) up to 4MB.',
                    ),
                ],
            ])
            ->add('removeAvatar', CheckboxType::class, [
                'label' => 'Remove current profile picture',
                'mapped' => false,
                'required' => false,
            ])
            ->add('name', TextType::class, [
                'label' => 'Your name',
                'required' => true,
                'attr' => ['placeholder' => 'e.g. Jonas Andersen'],
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'A few words about yourself: what you drive, what you love about car meets...',
                    'rows' => 3,
                ],
            ])
            ->add('instagramUrl', IconTextType::class, [
                'label' => 'Instagram',
                'required' => false,
                'attr' => ['placeholder' => 'instagram.com/yourprofile'],
                'icon' => 'instagram',
            ])
            ->add('youtubeUrl', IconTextType::class, [
                'label' => 'YouTube',
                'required' => false,
                'attr' => ['placeholder' => 'youtube.com/@yourchannel'],
                'icon' => 'youtube',
            ])
            ->add('facebookUrl', IconTextType::class, [
                'label' => 'Facebook',
                'required' => false,
                'attr' => ['placeholder' => 'facebook.com/yourprofile'],
                'icon' => 'facebook',
            ])
            ->add('websiteUrl', IconTextType::class, [
                'label' => 'Website',
                'required' => false,
                'attr' => ['placeholder' => 'yourwebsite.com'],
                'icon' => 'link',
            ])
            ->add('role', SegmentedChoiceType::class, [
                'class' => UserRole::class,
                'label' => false,
                'required' => false,
                // "Default" is the no-creator-role option; picking it clears any
                // previously selected role (handled in UserService).
                'placeholder' => 'Default',
                'choice_label' => fn (UserRole $role) => $role->label(),
            ])
            ->add('timezone', HiddenType::class, [
                'attr' => ['data-user-setup-target' => 'timezoneInput'],
            ])
            ->add('language', HiddenType::class, [
                'attr' => ['data-user-setup-target' => 'languageInput'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['data-controller' => 'user-setup'],
            'data_class' => UserSetupDTO::class,
        ]);
    }
}

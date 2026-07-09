<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\UserSetupDTO;
use App\Enum\UserRole;
use App\Form\Type\IconTextType;
use App\Form\Type\SegmentedChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserSetupType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_avatar']) {
            $builder
                ->add('avatarFile', FileType::class, [
                    'label' => 'form.profile.avatar',
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
                    'label' => 'form.profile.remove_avatar',
                    'mapped' => false,
                    'required' => false,
                ]);
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'form.profile.name',
                'required' => true,
                'attr' => ['placeholder' => $this->translator->trans('form.profile.name_placeholder')],
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('form.profile.description_placeholder'),
                    'rows' => 3,
                ],
            ])
            ->add('instagramUrl', IconTextType::class, [
                'label' => 'form.profile.instagram',
                'required' => false,
                'attr' => ['placeholder' => $this->translator->trans('form.profile.instagram_placeholder')],
                'icon' => 'instagram',
            ])
            ->add('youtubeUrl', IconTextType::class, [
                'label' => 'form.profile.youtube',
                'required' => false,
                'attr' => ['placeholder' => $this->translator->trans('form.profile.youtube_placeholder')],
                'icon' => 'youtube',
            ])
            ->add('facebookUrl', IconTextType::class, [
                'label' => 'form.profile.facebook',
                'required' => false,
                'attr' => ['placeholder' => $this->translator->trans('form.profile.facebook_placeholder')],
                'icon' => 'facebook',
            ])
            ->add('websiteUrl', IconTextType::class, [
                'label' => 'form.profile.website',
                'required' => false,
                'attr' => ['placeholder' => $this->translator->trans('form.profile.website_placeholder')],
                'icon' => 'link',
            ])
            ->add('role', SegmentedChoiceType::class, [
                'class' => UserRole::class,
                'label' => false,
                'required' => false,
                // "Default" is the no-creator-role option; picking it clears any
                // previously selected role (handled in UserService).
                'placeholder' => 'form.profile.role_default',
                'choice_label' => fn (UserRole $role) => $this->translator->trans($role->label()),
            ])
            ->add('timezone', HiddenType::class, [
                'attr' => ['data-user-setup-target' => 'timezoneInput'],
            ]);

        if ($options['select_language']) {
            $builder->add('language', ChoiceType::class, [
                'label' => 'settings.language.label',
                'choices' => ['English' => 'en', 'Dansk' => 'da'],
                'placeholder' => false,
                'required' => true,
            ]);
        } else {
            $builder->add('language', HiddenType::class, [
                'attr' => ['data-user-setup-target' => 'languageInput'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['data-controller' => 'user-setup'],
            'data_class' => UserSetupDTO::class,
            'include_avatar' => true,
            'select_language' => false,
        ]);

        $resolver->setAllowedTypes('include_avatar', 'bool');
        $resolver->setAllowedTypes('select_language', 'bool');
    }
}

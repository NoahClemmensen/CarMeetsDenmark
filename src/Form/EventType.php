<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\SaveEventDTO;
use App\Enum\EventRepeatFrequency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imageFile', FileType::class, [
                'label' => 'form.event.banner_image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '12M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        mimeTypesMessage: 'Please upload a valid image file (JPEG, PNG, GIF, or WEBP) with a maximum size of 4MB.',
                    ),
                ],
            ])
            ->add('removeImage', CheckboxType::class, [
                'label' => 'form.event.remove_banner',
                'mapped' => false,
                'required' => false,
            ])
            ->add('name')
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('form.event.description_placeholder'),
                    'rows' => 3,
                ],
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'form.event.starts',
                'widget' => 'single_text',
            ])
            ->add('location', TextType::class, [
                'label' => 'form.event.location',
                'attr' => ['placeholder' => $this->translator->trans('form.event.location_placeholder')],
            ])
            ->add('private', CheckboxType::class, [
                'label' => 'form.event.private',
                'required' => false,
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'form.event.ends',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('repeatFrequency', EnumType::class, [
                'class' => EventRepeatFrequency::class,
                'label' => 'form.event.frequency',
                'required' => false,
                'placeholder' => 'form.event.frequency_none',
                'choice_label' => fn (EventRepeatFrequency $f) => $this->translator->trans($f->label()),
            ])
            ->add('repeatAmount', IntegerType::class, [
                'label' => 'form.event.every',
                'required' => false,
                'attr' => ['min' => 1, 'placeholder' => '1'],
            ])
            ->add('timezone', TextType::class, [
                'label' => 'form.event.timezone',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Europe/Copenhagen',
                    'data-timezone-detect-target' => 'input',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['data-controller' => 'timezone-detect'],
            'data_class' => SaveEventDTO::class,
            'is_new' => false,
            'validation_groups' => static function (\Symfony\Component\Form\FormInterface $form): array {
                $groups = ['Default'];
                if ($form->getConfig()->getOption('is_new') === true) {
                    $groups[] = 'create';
                }

                return $groups;
            },
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}

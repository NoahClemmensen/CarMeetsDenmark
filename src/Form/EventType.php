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

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imageFile', FileType::class, [
                'label' => 'Banner image',
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
                'label' => 'Remove current banner',
                'mapped' => false,
                'required' => false,
            ])
            ->add('name')
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'A few words about the meet. What to expect, who it\'s for, anything to bring...',
                    'rows' => 3,
                ],
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Starts',
                'widget' => 'single_text',
            ])
            ->add('location', TextType::class, [
                'label' => 'Location',
                'attr' => ['placeholder' => 'e.g. Amager Strandpark, Copenhagen'],
            ])
            ->add('private', CheckboxType::class, [
                'label' => 'Private event (not listed publicly)',
                'required' => false,
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'Ends',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('repeatFrequency', EnumType::class, [
                'class' => EventRepeatFrequency::class,
                'label' => 'Frequency',
                'required' => false,
                'placeholder' => 'Doesn\'t repeat',
                'choice_label' => fn (EventRepeatFrequency $f) => $f->label(),
            ])
            ->add('repeatAmount', IntegerType::class, [
                'label' => 'Every',
                'required' => false,
                'attr' => ['min' => 1, 'placeholder' => '1'],
            ])
            ->add('timezone', TextType::class, [
                'label' => 'Timezone',
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
        ]);
    }
}

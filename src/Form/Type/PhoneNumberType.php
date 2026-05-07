<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PhoneNumberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('countryCode', TextType::class, [
                'attr' => [
                    'placeholder' => '45',
                    'inputmode' => 'numeric',
                    'autocomplete' => 'tel-country-code',
                    'class' => 'text-center',
                ],
                'label' => false,
            ])
            ->add('phone', TelType::class, [
                'attr' => [
                    'placeholder' => '234 567 890',
                    'inputmode' => 'tel',
                    'autocomplete' => 'tel-national',
                ],
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inherit_data' => true,
            'label' => 'Phone',
            'required' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'phone_number';
    }
}

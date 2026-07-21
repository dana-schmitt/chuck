<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Url;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, [
                'label' => 'Display name',
                'required' => false,
                'attr' => ['placeholder' => 'How should we call you?'],
                'constraints' => [
                    new Length(max: 60, maxMessage: 'Your display name should be at most {{ limit }} characters.'),
                ],
            ])
            ->add('avatarUrl', UrlType::class, [
                'label' => 'Avatar URL',
                'required' => false,
                'attr' => ['placeholder' => 'https://example.com/me.png'],
                'constraints' => [
                    new Url(message: 'Please enter a valid URL.'),
                    new Length(max: 500, maxMessage: 'The URL is too long.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

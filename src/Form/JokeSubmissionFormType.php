<?php

namespace App\Form;

use App\Entity\Joke;
use App\Enum\JokeCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class JokeSubmissionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('joke', TextareaType::class, [
                'label' => 'Your joke',
                'attr' => [
                    'placeholder' => 'Chuck Norris can divide by zero.',
                    'rows' => 4,
                ],
                'constraints' => [
                    new NotBlank(message: 'Please write a joke.'),
                    new Length(min: 10, max: 500, minMessage: 'Your joke should be at least {{ limit }} characters.'),
                ],
            ])
            ->add('category', EnumType::class, [
                'class' => JokeCategory::class,
                'label' => 'Category (optional)',
                'placeholder' => 'No category',
                'required' => false,
                'mapped' => false,
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
                /** @var Joke $joke */
                $joke = $event->getData();
                /** @var JokeCategory|null $category */
                $category = $event->getForm()->get('category')->getData();

                $joke->setCategories($category !== null ? [$category->value] : []);
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Joke::class,
        ]);
    }
}

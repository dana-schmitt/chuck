<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CommentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('body', TextareaType::class, [
            'label' => 'Add a comment',
            'attr' => [
                'placeholder' => 'What did you think?',
                'rows' => 3,
            ],
            'constraints' => [
                new NotBlank(message: 'Please write a comment.'),
                new Length(max: 1000, maxMessage: 'Comments can be at most {{ limit }} characters.'),
            ],
        ]);
    }
}

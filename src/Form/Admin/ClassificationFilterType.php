<?php

namespace App\Form\Admin;

use App\Controller\Admin\Filter\ClassificationFilter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ClassificationFilterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => [
                'Sans album' => ClassificationFilter::WITHOUT_ALBUM,
                'Sans tag' => ClassificationFilter::WITHOUT_TAG,
                'Sans jeu' => ClassificationFilter::WITHOUT_GAME,
                'Jamais passée par l’IA' => ClassificationFilter::NEVER_TAGGED_BY_AI,
            ],
            'expanded' => true,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}

<?php

namespace App\Form\Admin;

use App\Repository\TagRepository;
use App\Tagging\TagBranch;
use App\Tagging\TagTree;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TagTreeFilterType extends AbstractType
{
    public function __construct(private readonly TagRepository $tags)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tree = $this->tree();
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (mixed $branch): ?int => $branch instanceof TagBranch ? $branch->rootId() : null,
            static fn (mixed $tagId): ?TagBranch => null === $tagId ? null : new TagBranch($tree->subtreeIds((int) $tagId) ?: [(int) $tagId]),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => $this->tree()->labelledChoices(),
            'placeholder' => 'Choisir un tag',
            'attr' => ['data-ea-widget' => 'ea-autocomplete'],
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    private function tree(): TagTree
    {
        return TagTree::fromRows($this->tags->findTreeRows());
    }
}

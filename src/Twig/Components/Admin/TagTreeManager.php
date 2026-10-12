<?php

namespace App\Twig\Components\Admin;

use App\Repository\TagRepository;
use App\Tagging\TagTree;
use App\Tagging\TagTreeEditor;
use App\Tagging\TagTreeException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent(route: 'APP_ADMIN_LIVE_COMPONENT')]
final class TagTreeManager
{
    use DefaultActionTrait;

    private const int ROOT = 0;

    #[LiveProp(writable: true)]
    public string $query = '';

    /**
     * @var list<int>
     */
    #[LiveProp]
    public array $expanded = [];

    #[LiveProp]
    public ?int $editingId = null;

    #[LiveProp]
    public ?int $addingTo = null;

    #[LiveProp(writable: true)]
    public string $draft = '';

    public ?string $notice = null;

    public bool $noticeIsError = false;

    public function __construct(
        private readonly TagRepository $tags,
        private readonly TagTreeEditor $editor,
    ) {
    }

    #[PostMount]
    public function expandRoots(): void
    {
        $this->expanded = array_map(static fn ($root): int => $root->id, $this->fullTree()->roots());
    }

    public function getTree(): TagTree
    {
        return $this->fullTree()->filteredBy(trim($this->query));
    }

    public function isExpanded(int $tagId): bool
    {
        return '' !== trim($this->query) || in_array($tagId, $this->expanded, true);
    }

    #[LiveAction]
    public function toggle(#[LiveArg] int $tagId): void
    {
        $this->expanded = in_array($tagId, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$tagId]))
            : [...$this->expanded, $tagId];
    }

    #[LiveAction]
    public function expandAll(): void
    {
        $this->expanded = $this->fullTree()->branchIds();
    }

    #[LiveAction]
    public function collapseAll(): void
    {
        $this->expanded = [];
    }

    #[LiveAction]
    public function startEdit(#[LiveArg] int $tagId, #[LiveArg] string $name): void
    {
        $this->addingTo = null;
        $this->editingId = $tagId;
        $this->draft = $name;
    }

    #[LiveAction]
    public function startAdd(#[LiveArg] int $parentId): void
    {
        $this->editingId = null;
        $this->addingTo = $parentId;
        $this->draft = '';
        if (self::ROOT !== $parentId && !in_array($parentId, $this->expanded, true)) {
            $this->expanded[] = $parentId;
        }
    }

    #[LiveAction]
    public function cancel(): void
    {
        $this->editingId = null;
        $this->addingTo = null;
        $this->draft = '';
    }

    #[LiveAction]
    public function save(): void
    {
        $this->attempt(function (): string {
            $tag = null !== $this->editingId
                ? $this->editor->rename($this->editingId, $this->draft)
                : $this->editor->add(self::ROOT === $this->addingTo ? null : $this->addingTo, $this->draft);
            $verb = null !== $this->editingId ? 'renommé' : 'créé';
            $this->cancel();

            return sprintf('« %s » %s.', $tag->getLabel(), $verb);
        });
    }

    #[LiveAction]
    public function move(#[LiveArg] int $tagId, #[LiveArg] int $parentId): void
    {
        $this->attempt(function () use ($tagId, $parentId): string {
            $tag = $this->editor->move($tagId, self::ROOT === $parentId ? null : $parentId);
            if (self::ROOT !== $parentId && !in_array($parentId, $this->expanded, true)) {
                $this->expanded[] = $parentId;
            }

            return sprintf('« %s » déplacé vers %s.', $tag->getName(), null === $tag->getParent() ? 'la racine' : '« '.$tag->getParent()->getLabel().' »');
        });
    }

    #[LiveAction]
    public function delete(#[LiveArg] int $tagId): void
    {
        $this->attempt(fn (): string => sprintf('« %s » supprimé.', $this->editor->delete($tagId)));
    }

    private function attempt(callable $operation): void
    {
        try {
            $this->notice = $operation();
            $this->noticeIsError = false;
        } catch (TagTreeException $exception) {
            $this->notice = $exception->getMessage();
            $this->noticeIsError = true;
        }
    }

    private function fullTree(): TagTree
    {
        return TagTree::fromRows($this->tags->findTreeRows());
    }
}

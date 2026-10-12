<?php

namespace App\Tagging;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TagTreeEditor
{
    public function __construct(
        private TagRepository $tags,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function add(?int $parentId, string $name): Tag
    {
        $parent = null === $parentId ? null : $this->find($parentId);
        $tag = (new Tag())->setName($this->validName($name))->setParent($parent);
        $this->assertFreeName($parent, $tag->getName(), $tag);
        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $tag;
    }

    public function rename(int $tagId, string $name): Tag
    {
        $tag = $this->find($tagId);
        $this->assertFreeName($tag->getParent(), $this->validName($name), $tag);
        $tag->setName($this->validName($name));
        $this->entityManager->flush();

        return $tag;
    }

    public function move(int $tagId, ?int $parentId): Tag
    {
        $tag = $this->find($tagId);
        $parent = null === $parentId ? null : $this->find($parentId);
        if (null !== $parent && ($parent === $tag || in_array($tag, $parent->getAncestors(), true))) {
            throw new TagTreeException(sprintf('Impossible de ranger « %s » dans lui-même ou l\'un de ses sous-tags.', $tag->getName()));
        }

        $this->assertFreeName($parent, (string) $tag->getName(), $tag);
        $tag->setParent($parent);
        $this->entityManager->flush();

        return $tag;
    }

    public function delete(int $tagId): string
    {
        $tag = $this->find($tagId);
        foreach ($this->tags->findBy(['parent' => $tag]) as $child) {
            $this->assertFreeName($tag->getParent(), (string) $child->getName(), $child);
            $child->setParent($tag->getParent());
        }
        $this->entityManager->remove($tag);
        $this->entityManager->flush();

        return (string) $tag->getName();
    }

    private function find(int $tagId): Tag
    {
        return $this->tags->find($tagId) ?? throw new TagTreeException('Ce tag n\'existe plus, la page va se mettre à jour.');
    }

    private function validName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || str_contains($name, Tag::PATH_SEPARATOR) || mb_strlen($name) > 100) {
            throw new TagTreeException('Le nom doit faire entre 1 et 100 caractères, sans « / ».');
        }

        return $name;
    }

    private function assertFreeName(?Tag $parent, string $name, Tag $tag): void
    {
        $sibling = $this->tags->findOneBy(['parent' => $parent, 'name' => $name]);
        if (null !== $sibling && $sibling !== $tag) {
            throw new TagTreeException(sprintf('Un tag « %s » existe déjà %s.', $name, null === $parent ? 'à la racine' : 'dans « '.$parent->getLabel().' »'));
        }
    }
}

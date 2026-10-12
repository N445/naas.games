<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\UniqueConstraint(fields: ['parent', 'name'])]
#[UniqueEntity(fields: ['parent', 'name'], ignoreNull: false, message: 'Ce tag existe déjà à cet endroit de l\'arborescence.')]
class Tag
{
    public const string PATH_SEPARATOR = '/';
    private const string LABEL_SEPARATOR = ' › ';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(pattern: '#/#', match: false, message: 'Le nom ne peut pas contenir « / », utilisez le parent pour ranger le tag.')]
    private ?string $name = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Tag $parent = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => \SortDirection::Ascending])]
    private Collection $children;

    /**
     * @var Collection<int, Screenshot>
     */
    #[ORM\ManyToMany(targetEntity: Screenshot::class, mappedBy: 'tags')]
    private Collection $screenshots;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->screenshots = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getParent(): ?Tag
    {
        return $this->parent;
    }

    public function setParent(?Tag $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return list<Tag>
     */
    public function getAncestors(): array
    {
        return null === $this->parent ? [] : [...$this->parent->getAncestors(), $this->parent];
    }

    public function getPath(): string
    {
        return $this->joinLineage(self::PATH_SEPARATOR);
    }

    public function getLabel(): string
    {
        return $this->joinLineage(self::LABEL_SEPARATOR);
    }

    public function countScreenshots(): int
    {
        return $this->screenshots->count();
    }

    public function countChildren(): int
    {
        return $this->children->count();
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }

    private function joinLineage(string $separator): string
    {
        return implode($separator, [...array_map(static fn (Tag $ancestor): string => (string) $ancestor->getName(), $this->getAncestors()), (string) $this->name]);
    }
}

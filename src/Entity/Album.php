<?php

namespace App\Entity;

use App\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AlbumRepository::class)]
class Album
{
    use TimestampableEntity;
    use BlameableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['title'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $published = true;

    #[ORM\Column]
    private int $viewCount = 0;

    #[ORM\ManyToOne(targetEntity: Screenshot::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Screenshot $cover = null;

    /**
     * @var Collection<int, Screenshot>
     */
    #[ORM\ManyToMany(targetEntity: Screenshot::class, mappedBy: 'albums', fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['takenAt' => \SortDirection::Descending])]
    private Collection $screenshots;

    public function __construct()
    {
        $this->screenshots = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function getCover(): ?Screenshot
    {
        return $this->cover ?? $this->screenshots->slice(0, 1)[0] ?? null;
    }

    public function hasCover(Screenshot $screenshot): bool
    {
        return $this->getCover() === $screenshot;
    }

    public function useAsCover(Screenshot $screenshot): static
    {
        $screenshot->addAlbum($this);
        $this->cover = $screenshot;

        return $this;
    }

    public function attach(Screenshot $screenshot): void
    {
        if (!$this->screenshots->contains($screenshot)) {
            $this->screenshots->add($screenshot);
        }
    }

    public function detach(Screenshot $screenshot): void
    {
        $this->screenshots->removeElement($screenshot);
        if ($this->cover === $screenshot) {
            $this->cover = null;
        }
    }

    /**
     * @return Collection<int, Screenshot>
     */
    public function getScreenshots(): Collection
    {
        return $this->screenshots;
    }

    public function countScreenshots(): int
    {
        return $this->screenshots->count();
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }
}

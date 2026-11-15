<?php

namespace App\Entity;

use App\Repository\ScreenshotRepository;
use App\Screenshot\Original\OriginalName;
use App\Screenshot\Palette;
use App\Vision\Embedding;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ScreenshotRepository::class)]
#[ORM\Index(fields: ['published'])]
#[ORM\Index(fields: ['takenAt'])]
#[ORM\Index(columns: ['file_hash'])]
#[ORM\Index(columns: ['match_key'])]
class Screenshot
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

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $takenAt = null;

    #[ORM\Column]
    private bool $published = true;

    #[ORM\Column]
    private int $viewCount = 0;

    #[ORM\Column]
    private int $downloadCount = 0;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Game $game = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $aiTaggedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $aiRequestedAt = null;

    /**
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiSuggestedTags = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $embedding = null;

    /**
     * @var list<array{hex: string, share: int}>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $palette = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $matchKey = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $perceptualHash = null;

    /**
     * Screenshots an admin confirmed are not duplicates of this one, despite looking alike.
     *
     * @var list<int>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $distinctFrom = null;

    #[ORM\ManyToOne(targetEntity: ScreenshotOriginal::class, inversedBy: 'finals')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ScreenshotOriginal $original = null;

    /**
     * @var Collection<int, Album>
     */
    #[ORM\ManyToMany(targetEntity: Album::class, inversedBy: 'screenshots')]
    private Collection $albums;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'screenshots')]
    #[ORM\OrderBy(['name' => \SortDirection::Ascending])]
    private Collection $tags;

    public function __construct(
        #[ORM\Embedded(columnPrefix: 'file_')]
        private StoredImage $file,
    ) {
        $this->albums = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->matchKey = OriginalName::key($file->getOriginalName());
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

    public function getTakenAt(): ?\DateTimeImmutable
    {
        return $this->takenAt;
    }

    public function setTakenAt(?\DateTimeImmutable $takenAt): static
    {
        $this->takenAt = $takenAt;

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

    public function getDownloadCount(): int
    {
        return $this->downloadCount;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getAiTaggedAt(): ?\DateTimeImmutable
    {
        return $this->aiTaggedAt;
    }

    public function requestAiTagging(\DateTimeImmutable $requestedAt): static
    {
        $this->aiRequestedAt = $requestedAt;

        return $this;
    }

    public function isAwaitingAiTagging(\DateTimeImmutable $now): bool
    {
        if (null === $this->aiRequestedAt || $this->aiRequestedAt < $now->modify('-3 minutes')) {
            return false;
        }

        return null === $this->aiTaggedAt || $this->aiTaggedAt < $this->aiRequestedAt;
    }

    public function hasTags(): bool
    {
        return !$this->tags->isEmpty();
    }

    /**
     * @param list<string> $suggestedTags
     */
    public function markAiTagged(\DateTimeImmutable $taggedAt, array $suggestedTags): static
    {
        $this->aiTaggedAt = $taggedAt;
        $this->aiSuggestedTags = $suggestedTags;

        return $this;
    }

    public function hasReviewedTags(): bool
    {
        $names = self::normalize($this->tagPaths());
        if ([] === $names) {
            return false;
        }

        if (null === $this->aiTaggedAt) {
            return true;
        }

        return null !== $this->aiSuggestedTags && $names !== self::normalize($this->aiSuggestedTags);
    }

    /**
     * @return list<string>
     */
    public function tagPaths(): array
    {
        return array_values($this->tags->map(static fn (Tag $tag): string => $tag->getPath())->toArray());
    }

    public function embedding(): ?Embedding
    {
        return null === $this->embedding ? null : Embedding::decode($this->embedding);
    }

    public function embedWith(Embedding $embedding): void
    {
        $this->embedding = $embedding->encode();
    }

    public function getPalette(): ?Palette
    {
        return null === $this->palette ? null : Palette::fromArray($this->palette);
    }

    public function usePalette(Palette $palette): void
    {
        $this->palette = $palette->toArray();
    }

    public function rememberHash(string $hash): void
    {
        $this->file = $this->file->withHash($hash);
    }

    public function getMatchKey(): string
    {
        return $this->matchKey ?? OriginalName::key($this->file->getOriginalName());
    }

    public function rememberMatchKey(): void
    {
        $this->matchKey = OriginalName::key($this->file->getOriginalName());
    }

    public function getPerceptualHash(): ?string
    {
        return $this->perceptualHash;
    }

    public function rememberPerceptualHash(?string $perceptualHash): void
    {
        $this->perceptualHash = $perceptualHash;
    }

    public function isPanoramic(): bool
    {
        return OriginalName::isPanoramic($this->file->getWidth(), $this->file->getHeight());
    }

    public function getOriginal(): ?ScreenshotOriginal
    {
        return $this->original;
    }

    public function hasOriginal(): bool
    {
        return null !== $this->original;
    }

    public function linkOriginal(ScreenshotOriginal $original): void
    {
        if ($this->original === $original) {
            return;
        }
        $this->original?->detachFinal($this);
        $this->original = $original;
        $original->attachFinal($this);
    }

    public function unlinkOriginal(): void
    {
        $this->original?->detachFinal($this);
        $this->original = null;
    }

    public function isDistinctFrom(self $other): bool
    {
        return in_array($other->getId(), $this->distinctFrom ?? [], true) || in_array($this->getId(), $other->distinctFrom ?? [], true);
    }

    public function markDistinctFrom(self $other): void
    {
        $this->distinctFrom = array_values(array_unique([...$this->distinctFrom ?? [], (int) $other->getId()]));
    }

    /**
     * Folds a duplicate into this screenshot before it is deleted: classification, audience and
     * whatever metadata this one lacks are kept, so nothing is lost by keeping the other file.
     */
    public function absorb(self $duplicate): void
    {
        foreach ($duplicate->getTags() as $tag) {
            $this->addTag($tag);
        }
        foreach ($duplicate->getAlbums()->toArray() as $album) {
            if ($album->hasCover($duplicate)) {
                $album->useAsCover($this);
            }
            $duplicate->removeAlbum($album);
            $this->addAlbum($album);
        }
        $this->viewCount += $duplicate->viewCount;
        $this->downloadCount += $duplicate->downloadCount;
        $this->published = $this->published || $duplicate->published;
        $this->description ??= $duplicate->description;
        $this->takenAt ??= $duplicate->takenAt;
        $this->game ??= $duplicate->game;
        $this->palette ??= $duplicate->palette;
        $this->embedding ??= $duplicate->embedding;
        $this->distinctFrom = array_values(array_unique([...$this->distinctFrom ?? [], ...$duplicate->distinctFrom ?? []])) ?: null;
        if (null === $this->original && null !== $duplicate->original) {
            $this->linkOriginal($duplicate->original);
        }
        $duplicate->unlinkOriginal();
    }

    public function getFile(): StoredImage
    {
        return $this->file;
    }

    public function getPath(): string
    {
        return $this->file->getPath();
    }

    /**
     * @return Collection<int, Album>
     */
    public function getAlbums(): Collection
    {
        return $this->albums;
    }

    public function addAlbum(Album $album): static
    {
        if (!$this->albums->contains($album)) {
            $this->albums->add($album);
            $album->attach($this);
        }

        return $this;
    }

    public function removeAlbum(Album $album): static
    {
        $this->albums->removeElement($album);
        $album->detach($this);

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function belongsTo(Album $album): bool
    {
        return $this->albums->contains($album);
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    /**
     * @param array<string> $names
     *
     * @return list<string>
     */
    private static function normalize(array $names): array
    {
        $normalized = array_map(static fn (string $name): string => mb_strtolower($name), $names);
        sort($normalized);

        return array_values(array_unique($normalized));
    }
}

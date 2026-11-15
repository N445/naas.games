<?php

namespace App\Entity;

use App\Repository\ScreenshotOriginalRepository;
use App\Screenshot\Original\OriginalName;
use App\Vision\Embedding;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * Untouched 32:9 capture a published final was cropped or retouched from. Never shown as a screenshot
 * on its own: it only exists through the finals it is linked to (or waits for one, when uploaded first).
 */
#[ORM\Entity(repositoryClass: ScreenshotOriginalRepository::class)]
#[ORM\Index(columns: ['match_key'])]
#[ORM\Index(columns: ['file_hash'])]
class ScreenshotOriginal
{
    use TimestampableEntity;
    use BlameableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $matchKey;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $takenAt = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $perceptualHash = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $embedding = null;

    #[ORM\Column]
    private bool $downloadable = true;

    /**
     * @var Collection<int, Screenshot>
     */
    #[ORM\OneToMany(targetEntity: Screenshot::class, mappedBy: 'original')]
    private Collection $finals;

    public function __construct(
        #[ORM\Embedded(columnPrefix: 'file_')]
        private StoredImage $file,
    ) {
        $this->matchKey = OriginalName::key($file->getOriginalName());
        $this->finals = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFile(): StoredImage
    {
        return $this->file;
    }

    public function getPath(): string
    {
        return $this->file->getPath();
    }

    public function getMatchKey(): string
    {
        return $this->matchKey;
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

    public function getPerceptualHash(): ?string
    {
        return $this->perceptualHash;
    }

    public function rememberPerceptualHash(?string $perceptualHash): void
    {
        $this->perceptualHash = $perceptualHash;
    }

    public function embedding(): ?Embedding
    {
        return null === $this->embedding ? null : Embedding::decode($this->embedding);
    }

    public function embedWith(Embedding $embedding): void
    {
        $this->embedding = $embedding->encode();
    }

    public function isDownloadable(): bool
    {
        return $this->downloadable;
    }

    public function setDownloadable(bool $downloadable): static
    {
        $this->downloadable = $downloadable;

        return $this;
    }

    /**
     * @return Collection<int, Screenshot>
     */
    public function getFinals(): Collection
    {
        return $this->finals;
    }

    public function countFinals(): int
    {
        return $this->finals->count();
    }

    public function isPending(): bool
    {
        return $this->finals->isEmpty();
    }

    /**
     * @internal use Screenshot::linkOriginal()
     */
    public function attachFinal(Screenshot $final): void
    {
        if (!$this->finals->contains($final)) {
            $this->finals->add($final);
        }
    }

    /**
     * @internal use Screenshot::unlinkOriginal()
     */
    public function detachFinal(Screenshot $final): void
    {
        $this->finals->removeElement($final);
    }

    public function __toString(): string
    {
        return $this->file->getOriginalName();
    }
}

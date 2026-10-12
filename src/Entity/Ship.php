<?php

namespace App\Entity;

use App\Repository\ShipRepository;
use App\Vision\Embedding;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipRepository::class)]
class Ship
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 150)]
    private string $manufacturer;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $focus = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $size = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $embedding = null;

    public function __construct(
        #[ORM\Column(unique: true)]
        private int $rsiId,
    ) {
    }

    public function describe(string $name, string $manufacturer, ?string $focus, ?string $size, ?string $imageUrl): static
    {
        if ($imageUrl !== $this->imageUrl) {
            $this->embedding = null;
        }

        $this->name = $name;
        $this->manufacturer = $manufacturer;
        $this->focus = $focus;
        $this->size = null === $size ? null : ucfirst(strtolower($size));
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getManufacturer(): string
    {
        return $this->manufacturer;
    }

    public function getFullName(): string
    {
        return str_starts_with($this->name, explode(' ', $this->manufacturer)[0]) ? $this->name : $this->manufacturer.' '.$this->name;
    }

    public function getFocus(): ?string
    {
        return $this->focus;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function needsEmbedding(): bool
    {
        return null === $this->embedding && null !== $this->imageUrl;
    }

    public function embedding(): ?Embedding
    {
        return null === $this->embedding ? null : Embedding::decode($this->embedding);
    }

    public function embedWith(Embedding $embedding): void
    {
        $this->embedding = $embedding->encode();
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}

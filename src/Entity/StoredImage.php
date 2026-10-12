<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class StoredImage
{
    public function __construct(
        #[ORM\Column(length: 255)]
        private string $path,
        #[ORM\Column(length: 255)]
        private string $originalName,
        #[ORM\Column(length: 100)]
        private string $mimeType,
        #[ORM\Column]
        private int $size,
        #[ORM\Column]
        private int $width,
        #[ORM\Column]
        private int $height,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getDimensions(): string
    {
        return sprintf('%d × %d', $this->width, $this->height);
    }

    public function getExtension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }
}

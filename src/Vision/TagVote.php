<?php

namespace App\Vision;

final readonly class TagVote
{
    public function __construct(
        public string $name,
        public int $images,
        public float $weight,
    ) {
    }

    public function add(float $similarity): self
    {
        return new self($this->name, $this->images + 1, $this->weight + $similarity);
    }

    public function describe(): string
    {
        return sprintf('%s (%d image%s)', $this->name, $this->images, $this->images > 1 ? 's' : '');
    }
}

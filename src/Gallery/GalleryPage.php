<?php

namespace App\Gallery;

use App\Entity\Screenshot;

/**
 * @implements \IteratorAggregate<int, Screenshot>
 */
final readonly class GalleryPage implements \IteratorAggregate, \Countable
{
    /**
     * @param list<Screenshot> $screenshots
     */
    public function __construct(
        private array $screenshots,
        private int $total,
        private int $number,
        private int $size,
    ) {
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->screenshots);
    }

    public function count(): int
    {
        return count($this->screenshots);
    }

    public function total(): int
    {
        return $this->total;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function offset(): int
    {
        return ($this->number - 1) * $this->size;
    }

    public function hasMore(): bool
    {
        return $this->offset() + count($this->screenshots) < $this->total;
    }

    public function nextNumber(): int
    {
        return $this->number + 1;
    }
}

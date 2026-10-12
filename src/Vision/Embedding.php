<?php

namespace App\Vision;

final readonly class Embedding
{
    /**
     * @param list<float> $vector
     */
    private function __construct(private array $vector)
    {
    }

    /**
     * @param list<float> $vector
     */
    public static function fromVector(array $vector): self
    {
        $norm = sqrt(array_sum(array_map(static fn (float $value): float => $value * $value, $vector))) ?: 1.0;

        return new self(array_map(static fn (float $value): float => $value / $norm, $vector));
    }

    public static function decode(string $encoded): self
    {
        return new self(array_values(unpack('g*', base64_decode($encoded))));
    }

    public function encode(): string
    {
        return base64_encode(pack('g*', ...$this->vector));
    }

    public function similarity(self $other): float
    {
        $dot = 0.0;
        foreach ($this->vector as $index => $value) {
            $dot += $value * $other->vector[$index];
        }

        return $dot;
    }
}

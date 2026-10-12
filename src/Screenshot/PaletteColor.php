<?php

namespace App\Screenshot;

final readonly class PaletteColor
{
    public function __construct(
        private string $hex,
        private int $share,
    ) {
    }

    /**
     * @param array{hex: string, share: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['hex'], $data['share']);
    }

    /**
     * @return array{hex: string, share: int}
     */
    public function toArray(): array
    {
        return ['hex' => $this->hex, 'share' => $this->share];
    }

    public function hex(): string
    {
        return $this->hex;
    }

    public function share(): int
    {
        return $this->share;
    }

    public function deviationFrom(int $idealShare): int
    {
        return abs($this->share - $idealShare);
    }
}

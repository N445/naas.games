<?php

namespace App\Gallery;

use Symfony\Component\HttpFoundation\InputBag;

final readonly class GalleryFilters
{
    public const array KEYS = ['q', 'album', 'tag', 'game', 'year', 'month', 'from', 'to', 'color', 'sort'];
    private const int MAXIMUM_SEARCH_LENGTH = 100;
    private const string SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]{0,99}$/';

    /**
     * @param array<string, string> $values
     */
    private function __construct(private array $values)
    {
    }

    /**
     * @param InputBag<string> $query
     */
    public static function fromQuery(InputBag $query): self
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = self::sanitize($key, $query->getString($key));
        }

        return self::fromValues($values);
    }

    public function value(string $key): string
    {
        return $this->values[$key] ?? '';
    }

    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }

    public function is(string $key, string $value): bool
    {
        return $this->value($key) === $value;
    }

    public function with(string $key, string $value): self
    {
        return self::fromValues([...$this->values, $key => self::sanitize($key, $value)]);
    }

    public function without(string ...$keys): self
    {
        return self::fromValues(array_diff_key($this->values, array_flip($keys)));
    }

    public function toggle(string $key, string $value): self
    {
        $toggled = $this->is($key, $value) ? $this->without($key) : $this->with($key, $value);

        return 'year' === $key ? $toggled->without('month') : $toggled;
    }

    public function cleared(): self
    {
        return new self(array_intersect_key($this->values, ['sort' => true]));
    }

    /**
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return $this->values;
    }

    /**
     * @return list<string>
     */
    public function activeKeys(): array
    {
        return array_values(array_diff(array_keys($this->values), ['sort']));
    }

    public function isEmpty(): bool
    {
        return [] === $this->activeKeys();
    }

    public function sort(): GallerySort
    {
        return GallerySort::tryFrom($this->value('sort')) ?? GallerySort::LatestTaken;
    }

    public function color(): ?ColorBucket
    {
        return ColorBucket::tryFrom($this->value('color'));
    }

    public function period(): TakenPeriod
    {
        return TakenPeriod::fromFilters($this);
    }

    /**
     * @return list<string>
     */
    public function searchTerms(): array
    {
        return array_values(array_unique(array_filter(preg_split('/\s+/u', $this->value('q')) ?: [])));
    }

    /**
     * @param array<string, string> $values
     */
    private static function fromValues(array $values): self
    {
        $values = array_filter($values, static fn (string $value): bool => '' !== $value);

        return new self(isset($values['year']) ? $values : array_diff_key($values, ['month' => true]));
    }

    private static function sanitize(string $key, string $value): string
    {
        $value = trim($value);

        return self::isValid($key, $value) ? $value : '';
    }

    private static function isValid(string $key, string $value): bool
    {
        return match ($key) {
            'q' => mb_strlen($value) <= self::MAXIMUM_SEARCH_LENGTH,
            'album', 'game', 'tag' => 1 === preg_match(self::SLUG_PATTERN, $value),
            'year' => 1 === preg_match('/^(19|20)\d{2}$/', $value),
            'month' => 1 === preg_match('/^([1-9]|1[0-2])$/', $value),
            'from', 'to' => null !== TakenPeriod::parseDay($value),
            'color' => null !== ColorBucket::tryFrom($value),
            'sort' => null !== GallerySort::tryFrom($value) && !GallerySort::from($value)->isDefault(),
            default => false,
        };
    }
}

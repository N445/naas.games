<?php

namespace App\Gallery;

final readonly class TakenPeriod
{
    private function __construct(
        private ?\DateTimeImmutable $start,
        private ?\DateTimeImmutable $end,
    ) {
    }

    public static function fromFilters(GalleryFilters $filters): self
    {
        $period = new self(self::parseDay($filters->value('from')), self::parseDay($filters->value('to'))?->modify('+1 day'));

        return $period->intersect(self::calendar($filters->value('year'), $filters->value('month')));
    }

    public static function parseDay(string $value): ?\DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $day && $day->format('Y-m-d') === $value ? $day : null;
    }

    public function start(): ?\DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): ?\DateTimeImmutable
    {
        return $this->end;
    }

    private static function calendar(string $year, string $month): self
    {
        if ('' === $year) {
            return new self(null, null);
        }
        $unit = '' === $month ? 'year' : 'month';
        $start = new \DateTimeImmutable(sprintf('%s-%02d-01', $year, '' === $month ? 1 : (int) $month));

        return new self($start, $start->modify("+1 $unit"));
    }

    private function intersect(self $other): self
    {
        $starts = array_filter([$this->start, $other->start]);
        $ends = array_filter([$this->end, $other->end]);

        return new self([] === $starts ? null : max($starts), [] === $ends ? null : min($ends));
    }
}

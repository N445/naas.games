<?php

namespace App\Screenshot\Duplicate;

use App\Entity\Screenshot;
use App\Repository\ScreenshotRepository;
use App\Screenshot\Original\OriginalName;
use App\Screenshot\PerceptualHash;

/**
 * Exact duplicates share a file hash; probable ones have close perceptual hashes and the same framing.
 * A 32:9 original and its cropped final never match: originals are not screenshots, and a crop changes
 * both the aspect ratio and the hash.
 */
final readonly class DuplicateFinder
{
    public const int MAX_DISTANCE = 6;
    private const float MAX_RATIO_GAP = 0.03;

    public function __construct(private ScreenshotRepository $screenshots)
    {
    }

    /**
     * @return list<DuplicateGroup> identical files first
     */
    public function groups(): array
    {
        $groups = [];
        $grouped = [];
        foreach ($this->groupByHash($this->screenshots->findSharingHash()) as $members) {
            $groups[] = new DuplicateGroup($members, true, 0);
            foreach ($members as $member) {
                $grouped[(int) $member->getId()] = true;
            }
        }

        $candidates = array_values(array_filter($this->screenshots->findPerceptuallyHashed(), static fn (Screenshot $screenshot): bool => !isset($grouped[(int) $screenshot->getId()])));

        return [...$groups, ...$this->clusterLookalikes($candidates)];
    }

    /**
     * @return list<Screenshot>
     */
    public function lookalikesOf(Screenshot $screenshot): array
    {
        if (null === $screenshot->getPerceptualHash()) {
            return [];
        }

        return array_values(array_filter(
            $this->screenshots->findPerceptuallyHashed(),
            fn (Screenshot $other): bool => $other !== $screenshot && null !== $this->distance($screenshot, $other),
        ));
    }

    /**
     * @param list<Screenshot> $screenshots
     *
     * @return list<list<Screenshot>>
     */
    private function groupByHash(array $screenshots): array
    {
        $byHash = [];
        foreach ($screenshots as $screenshot) {
            $byHash[(string) $screenshot->getFile()->getHash()][] = $screenshot;
        }

        return array_values($byHash);
    }

    /**
     * @param list<Screenshot> $screenshots
     *
     * @return list<DuplicateGroup>
     */
    private function clusterLookalikes(array $screenshots): array
    {
        $parent = range(0, count($screenshots) - 1);
        $find = static function (int $index) use (&$parent): int {
            while ($parent[$index] !== $index) {
                $index = $parent[$index] = $parent[$parent[$index]];
            }

            return $index;
        };
        $hashes = array_map(static fn (Screenshot $screenshot): array => PerceptualHash::halves((string) $screenshot->getPerceptualHash()), $screenshots);
        $ratios = array_map(self::ratio(...), $screenshots);
        $distances = [];
        foreach ($screenshots as $left => $screenshot) {
            for ($right = $left + 1, $count = count($screenshots); $right < $count; ++$right) {
                if (abs($ratios[$left] - $ratios[$right]) > self::MAX_RATIO_GAP * $ratios[$left]) {
                    continue;
                }
                $distance = PerceptualHash::popcount($hashes[$left][0] ^ $hashes[$right][0]) + PerceptualHash::popcount($hashes[$left][1] ^ $hashes[$right][1]);
                if ($distance > self::MAX_DISTANCE || $screenshot->isDistinctFrom($screenshots[$right]) || self::areVersions($screenshot, $screenshots[$right])) {
                    continue;
                }
                $parent[$find($right)] = $find($left);
                $distances[$left] = max($distances[$left] ?? 0, $distance);
            }
        }

        $clusters = [];
        foreach (array_keys($screenshots) as $index) {
            $clusters[$find($index)][] = $index;
        }

        $groups = [];
        foreach ($clusters as $members) {
            if (count($members) < 2) {
                continue;
            }
            $groups[] = new DuplicateGroup(
                array_map(static fn (int $index): Screenshot => $screenshots[$index], $members),
                false,
                max(array_map(static fn (int $index): int => $distances[$index] ?? 0, $members)),
            );
        }

        return $groups;
    }

    private function distance(Screenshot $left, Screenshot $right): ?int
    {
        if (abs(self::ratio($left) - self::ratio($right)) > self::MAX_RATIO_GAP * self::ratio($left) || $left->isDistinctFrom($right) || self::areVersions($left, $right)) {
            return null;
        }
        $distance = PerceptualHash::distance((string) $left->getPerceptualHash(), (string) $right->getPerceptualHash());

        return $distance <= self::MAX_DISTANCE ? $distance : null;
    }

    /**
     * `name_01` and `name_02` are two deliberate edits of the same capture, not duplicates.
     */
    private static function areVersions(Screenshot $left, Screenshot $right): bool
    {
        return $left->getMatchKey() === $right->getMatchKey()
            && OriginalName::version($left->getFile()->getOriginalName()) !== OriginalName::version($right->getFile()->getOriginalName());
    }

    private static function ratio(Screenshot $screenshot): float
    {
        return $screenshot->getFile()->getWidth() / max($screenshot->getFile()->getHeight(), 1);
    }
}

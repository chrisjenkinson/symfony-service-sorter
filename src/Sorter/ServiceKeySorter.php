<?php

declare(strict_types=1);

namespace App\Sorter;

use App\Parser\ServiceChunk;
use App\Parser\ServiceGroup;

final class ServiceKeySorter
{
    public function __construct(
        private readonly ServiceKeyNormalizer $normalizer,
    ) {
    }

    /**
     * @param list<ServiceChunk> $chunks
     * @return list<ServiceChunk>
     * @throws DuplicateServiceKeyException If duplicate service keys are found
     */
    public function sortChunks(array $chunks): array
    {
        $this->assertNoDuplicateKeys(array_map(fn (ServiceChunk $chunk): string => $chunk->key, $chunks));

        return $this->sortUniqueChunks($chunks);
    }

    /**
     * @param list<ServiceGroup> $groups
     * @return list<ServiceGroup>
     * @throws DuplicateServiceKeyException If duplicate service keys are found
     */
    public function sortGroups(array $groups): array
    {
        $allKeys = [];
        foreach ($groups as $group) {
            foreach ($group->chunks as $chunk) {
                $allKeys[] = $chunk->key;
            }
        }
        $this->assertNoDuplicateKeys($allKeys);

        $sortedGroups = array_map(
            fn (ServiceGroup $group): ServiceGroup => new ServiceGroup(
                $group->boundaryComment,
                $this->sortUniqueChunks($group->chunks),
            ),
            $groups,
        );

        usort($sortedGroups, function (ServiceGroup $a, ServiceGroup $b): int {
            $aFirstChunk = $a->chunks[0] ?? null;
            $bFirstChunk = $b->chunks[0] ?? null;
            $aFirstKey = $aFirstChunk !== null ? $aFirstChunk->key : '';
            $bFirstKey = $bFirstChunk !== null ? $bFirstChunk->key : '';

            $aNormalized = $this->normalizer->normalize($aFirstKey);
            $bNormalized = $this->normalizer->normalize($bFirstKey);

            $aUnderscore = str_starts_with($aNormalized, '_');
            $bUnderscore = str_starts_with($bNormalized, '_');

            if ($aUnderscore !== $bUnderscore) {
                return $aUnderscore ? -1 : 1;
            }

            return strcmp($aNormalized, $bNormalized);
        });

        return $sortedGroups;
    }

    /**
     * @param list<string> $keys
     * @return list<string>
     * @throws DuplicateServiceKeyException If duplicate service keys are found
     */
    public function sortKeys(array $keys): array
    {
        $this->assertNoDuplicateKeys($keys);

        $underscore = [];
        $named = [];

        foreach ($keys as $key) {
            if (str_starts_with($key, '_')) {
                $underscore[] = $key;
            } else {
                $named[] = $key;
            }
        }

        $sortedUnderscore = $underscore;
        usort($sortedUnderscore, fn (string $a, string $b): int => strcmp(
            $this->normalizer->normalize($a),
            $this->normalizer->normalize($b),
        ));

        $sortedNamed = $named;
        usort($sortedNamed, fn (string $a, string $b): int => strcmp(
            $this->normalizer->normalize($a),
            $this->normalizer->normalize($b),
        ));

        return array_merge($sortedUnderscore, $sortedNamed);
    }

    /**
     * @param list<string> $keys
     * @throws DuplicateServiceKeyException
     */
    private function assertNoDuplicateKeys(array $keys): void
    {
        $counts = array_count_values($keys);
        foreach ($counts as $key => $count) {
            if ($count > 1) {
                throw new DuplicateServiceKeyException(sprintf(
                    'Duplicate service key found: "%s"',
                    $key,
                ));
            }
        }
    }

    /**
     * @param list<ServiceChunk> $chunks
     * @return list<ServiceChunk>
     */
    private function sortUniqueChunks(array $chunks): array
    {
        $underscore = [];
        $named = [];

        foreach ($chunks as $chunk) {
            if (str_starts_with($chunk->key, '_')) {
                $underscore[] = $chunk;
            } else {
                $named[] = $chunk;
            }
        }

        return array_merge($this->stableSort($underscore), $this->stableSort($named));
    }

    /**
     * @param list<ServiceChunk> $chunks
     * @return list<ServiceChunk>
     */
    private function stableSort(array $chunks): array
    {
        $decorated = [];
        foreach ($chunks as $index => $chunk) {
            $decorated[] = [
                'key' => $this->normalizer->normalize($chunk->key),
                'index' => $index,
                'chunk' => $chunk,
            ];
        }

        usort($decorated, function (array $a, array $b): int {
            $cmp = strcmp($a['key'], $b['key']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a['index'] <=> $b['index'];
        });

        return array_map(fn ($item): ServiceChunk => $item['chunk'], $decorated);
    }
}

<?php

declare(strict_types=1);

namespace App\Sorter;

use App\Parser\ServiceChunk;

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
        $seen = [];
        foreach ($keys as $key) {
            if (isset($seen[$key])) {
                throw new DuplicateServiceKeyException(sprintf(
                    'Duplicate service key found: "%s"',
                    $key,
                ));
            }
            $seen[$key] = true;
        }
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

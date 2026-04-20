<?php

declare(strict_types=1);

namespace App\Sorter;

use App\Parser\ParsedFile;
use App\Parser\ServiceChunk;

final class ServiceOrderChecker
{
    public function __construct(
        private readonly ServiceKeySorter $keySorter,
    ) {
    }

    /**
     * @return list<OutOfOrderEntry>
     */
    public function check(ParsedFile $parsedFile): array
    {
        $originalKeys = array_map(fn (ServiceChunk $chunk): string => $chunk->key, $parsedFile->chunks);

        if ($originalKeys === []) {
            return [];
        }

        $sortedKeys = $this->keySorter->sortKeys($originalKeys);
        $sortedPosition = array_flip($sortedKeys);

        $predecessorInSorted = [];
        for ($i = 1; $i < count($sortedKeys); $i++) {
            $predecessorInSorted[$sortedKeys[$i]] = $sortedKeys[$i - 1];
        }

        $originalPosition = array_flip($originalKeys);

        $outOfOrder = [];
        foreach ($originalKeys as $key) {
            if (!isset($predecessorInSorted[$key])) {
                continue;
            }

            $predecessor = $predecessorInSorted[$key];

            if ($originalPosition[$key] !== $originalPosition[$predecessor] && $originalPosition[$key] < $originalPosition[$predecessor]) {
                $outOfOrder[] = new OutOfOrderEntry(
                    $key,
                    $predecessor,
                    $this->countSubsequentServicesInDisplacedRun(
                        $originalKeys,
                        $originalPosition[$key],
                        $originalPosition[$predecessor],
                        $sortedPosition,
                    ),
                );
            }
        }

        return $outOfOrder;
    }

    /**
     * @param list<string> $originalKeys
     * @param array<string, int> $sortedPosition
     */
    private function countSubsequentServicesInDisplacedRun(
        array $originalKeys,
        int $startIndex,
        int $predecessorIndex,
        array $sortedPosition,
    ): int {
        $count = 0;
        $previousSortedIndex = $sortedPosition[$originalKeys[$startIndex]];

        for ($i = $startIndex + 1; $i < $predecessorIndex; $i++) {
            $currentSortedIndex = $sortedPosition[$originalKeys[$i]];

            if ($currentSortedIndex !== $previousSortedIndex + 1) {
                break;
            }

            $count++;
            $previousSortedIndex = $currentSortedIndex;
        }

        return $count;
    }
}

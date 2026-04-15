<?php

declare(strict_types=1);

namespace App\Sorter;

use App\Parser\ParsedFile;
use App\Parser\ServiceChunk;

final class ServiceOrderChecker
{
    public function __construct(
        private readonly ServiceKeyNormalizer $normalizer,
    ) {
    }

    /**
     * @return list<OutOfOrderEntry>
     */
    public function check(ParsedFile $parsedFile): array
    {
        $originalKeys = array_map(fn (ServiceChunk $chunk): string => $chunk->key, $parsedFile->chunks);

        if (count($originalKeys) <= 1) {
            return [];
        }

        $sortedKeys = $originalKeys;
        usort($sortedKeys, fn (string $a, string $b): int => strcmp(
            $this->normalizer->normalize($a),
            $this->normalizer->normalize($b),
        ));

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

            if ($originalPosition[$key] < $originalPosition[$predecessor]) {
                $outOfOrder[] = new OutOfOrderEntry($key, $predecessor);
            }
        }

        return $outOfOrder;
    }
}

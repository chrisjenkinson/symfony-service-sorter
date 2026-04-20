<?php

declare(strict_types=1);

namespace App\Sorter;

use App\Parser\ParsedFile;
use App\Parser\ServiceChunk;
use App\Parser\ServiceGroup;
use App\Sorter\ServiceKeyNormalizer;

final class ServicesSorter
{
    public function __construct(
        private readonly ServiceKeySorter $keySorter,
        private readonly ServiceKeyNormalizer $normalizer,
    ) {
    }

    public function sort(ParsedFile $parsedFile): string
    {
        if ($parsedFile->servicesHeader === '') {
            return implode('', $parsedFile->preamble);
        }

        $parts = [];
        $parts[] = implode('', $parsedFile->preamble);
        $parts[] = $parsedFile->servicesHeader;

        if ($parsedFile->groups !== []) {
            $sortedGroups = $this->sortGroups($parsedFile->groups);
            $first = true;
            foreach ($sortedGroups as $group) {
                if (!$first) {
                    $parts[] = "\n";
                }
                $first = false;

                if ($group->boundaryComment !== null) {
                    $parts[] = $group->boundaryComment->line . "\n";
                }

                $groupChunks = array_map(
                    fn (ServiceChunk $chunk): ServiceChunk => $this->normalizeChunk($chunk),
                    $group->chunks,
                );

                foreach ($groupChunks as $i => $chunk) {
                    if ($i > 0) {
                        $parts[] = "\n";
                    }
                    $parts[] = implode('', $chunk->lines);
                }
            }
        } else {
            $sorted = $this->keySorter->sortChunks($parsedFile->chunks);
            $normalized = array_map(fn (ServiceChunk $chunk): ServiceChunk => $this->normalizeChunk($chunk), $sorted);

            foreach ($normalized as $i => $chunk) {
                if ($i > 0) {
                    $parts[] = "\n";
                }
                $parts[] = implode('', $chunk->lines);
            }
        }

        $parts[] = implode('', $parsedFile->remainder);

        $result = implode('', $parts);

        if ($result !== '' && !str_ends_with($result, "\n")) {
            $result .= "\n";
        }

        return $result;
    }

    /**
     * @param list<ServiceGroup> $groups
     * @return list<ServiceGroup>
     */
    private function sortGroups(array $groups): array
    {
        $sortedGroups = array_map(
            fn (ServiceGroup $group): ServiceGroup => new ServiceGroup(
                $group->boundaryComment,
                $this->keySorter->sortChunks($group->chunks),
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

    private function normalizeChunk(ServiceChunk $chunk): ServiceChunk
    {
        $lines = $chunk->lines;
        while ($lines !== [] && trim(reset($lines)) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim(end($lines)) === '') {
            array_pop($lines);
        }

        return new ServiceChunk($chunk->key, $lines);
    }
}

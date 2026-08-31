<?php

declare(strict_types=1);

namespace App\Sorter;

use App\Parser\ParsedFile;
use App\Parser\ServiceChunk;

final class ServicesSorter
{
    public function __construct(
        private readonly ServiceKeySorter $keySorter,
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
            $sortedGroups = $this->keySorter->sortGroups($parsedFile->groups);
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

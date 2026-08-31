<?php

declare(strict_types=1);

namespace ChrisJenkinson\SymfonyServiceSorter\Sorter;

use ChrisJenkinson\SymfonyServiceSorter\Parser\ParsedFile;
use ChrisJenkinson\SymfonyServiceSorter\Parser\ServiceChunk;
use ChrisJenkinson\SymfonyServiceSorter\Parser\ServiceGroup;

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

                [$boundaryLines, $chunks] = $this->detachBoundaryLines($group);
                if ($boundaryLines !== []) {
                    $parts[] = implode('', $boundaryLines);
                }

                $groupChunks = array_map(
                    fn (ServiceChunk $chunk): ServiceChunk => $this->normalizeChunk($chunk),
                    $chunks,
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
     * @return array{list<string>, list<ServiceChunk>}
     */
    private function detachBoundaryLines(ServiceGroup $group): array
    {
        if ($group->boundaryComment === null || $group->boundaryComment->nextServiceKey === null) {
            return [[], $group->chunks];
        }

        $chunks = $group->chunks;
        foreach ($chunks as $chunkIndex => $chunk) {
            if ($chunk->key !== $group->boundaryComment->nextServiceKey) {
                continue;
            }

            foreach ($chunk->lines as $lineIndex => $line) {
                $key = rtrim(rtrim(ltrim($line, " \t")), ':');
                if ($key !== $chunk->key) {
                    continue;
                }

                $boundaryLines = array_slice($chunk->lines, 0, $lineIndex);
                $chunks[$chunkIndex] = new ServiceChunk(
                    $chunk->key,
                    array_slice($chunk->lines, $lineIndex),
                );

                return [$boundaryLines, $chunks];
            }
        }

        return [[$group->boundaryComment->line . "\n"], $chunks];
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

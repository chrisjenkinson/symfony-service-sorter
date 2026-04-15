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

        $sorted = $this->keySorter->sortChunks($parsedFile->chunks);
        $normalized = array_map(fn (ServiceChunk $chunk): ServiceChunk => $this->normalizeChunk($chunk), $sorted);

        $parts = [];
        $parts[] = implode('', $parsedFile->preamble);
        $parts[] = $parsedFile->servicesHeader;

        foreach ($normalized as $i => $chunk) {
            if ($i > 0) {
                $parts[] = "\n";
            }
            $parts[] = implode('', $chunk->lines);
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

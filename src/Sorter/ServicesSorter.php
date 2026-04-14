<?php

declare(strict_types=1);

namespace App\Sorter;

use App\Parser\ParsedFile;
use App\Parser\ServiceChunk;

final class ServicesSorter
{
    public function sort(ParsedFile $parsedFile): string
    {
        if ($parsedFile->servicesHeader === '') {
            return implode('', $parsedFile->preamble);
        }

        $underscore = [];
        $named = [];

        foreach ($parsedFile->chunks as $chunk) {
            if (str_starts_with($chunk->key, '_')) {
                $underscore[] = $chunk;
            } else {
                $named[] = $chunk;
            }
        }

        usort($underscore, fn (ServiceChunk $a, ServiceChunk $b) => strcmp(
            $this->normalizeKeyForSort($a->key),
            $this->normalizeKeyForSort($b->key),
        ));
        usort($named, fn (ServiceChunk $a, ServiceChunk $b) => strcmp(
            $this->normalizeKeyForSort($a->key),
            $this->normalizeKeyForSort($b->key),
        ));

        $sorted = array_merge($underscore, $named);
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

        return implode('', $parts);
    }

    private function normalizeKeyForSort(string $key): string
    {
        $key = str_replace('\\', '.', $key);
        $key = preg_replace('/([a-z])([A-Z])/', '$1_$2', $key) ?? $key;
        return strtolower($key);
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

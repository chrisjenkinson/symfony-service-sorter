<?php

declare(strict_types=1);

namespace App\Sorter;

use App\Parser\ParsedFile;
use App\Parser\ServiceChunk;

final class ServicesSorter
{
    public function __construct(
        private readonly ServiceKeyNormalizer $normalizer,
    ) {
    }

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

        $underscore = $this->stableSort($underscore);
        $named = $this->stableSort($named);

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

        $result = implode('', $parts);

        if ($result !== '' && !str_ends_with($result, "\n")) {
            $result .= "\n";
        }

        return $result;
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

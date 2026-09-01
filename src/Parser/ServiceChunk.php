<?php

declare(strict_types=1);

namespace ChrisJenkinson\SymfonyServiceSorter\Parser;

final class ServiceChunk
{
    /**
     * @param list<string> $lines Raw lines including preceding blank/comment lines and the key line and body
     */
    public function __construct(
        public readonly string $key,
        public readonly array $lines,
    ) {
    }
}

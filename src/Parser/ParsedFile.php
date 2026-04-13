<?php

declare(strict_types=1);

namespace App\Parser;

final class ParsedFile
{
    /**
     * @param list<string> $preamble        Lines before the services: key
     * @param string       $servicesHeader  The "services:\n" line itself
     * @param list<ServiceChunk> $chunks
     * @param list<string> $remainder       Lines after the services block
     */
    public function __construct(
        public readonly array $preamble,
        public readonly string $servicesHeader,
        public readonly array $chunks,
        public readonly array $remainder,
    ) {
    }
}

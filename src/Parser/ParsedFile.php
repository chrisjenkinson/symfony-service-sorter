<?php

declare(strict_types=1);

namespace App\Parser;

/**
 * Represents a parsed YAML file. When no `services:` key is present,
 * $servicesHeader is an empty string and $chunks is empty.
 */
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
        /** @var list<ServiceChunk> */
        public readonly array $chunks,
        public readonly array $remainder,
    ) {
    }
}

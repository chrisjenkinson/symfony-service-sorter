<?php

declare(strict_types=1);

namespace ChrisJenkinson\SymfonyServiceSorter\Sorter;

final class OutOfOrderEntry
{
    public function __construct(
        public readonly string $key,
        public readonly string $predecessor,
        public readonly int $subsequentCount = 0,
    ) {
    }
}

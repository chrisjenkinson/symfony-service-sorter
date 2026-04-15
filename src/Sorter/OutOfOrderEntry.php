<?php

declare(strict_types=1);

namespace App\Sorter;

final class OutOfOrderEntry
{
    public function __construct(
        public readonly string $key,
        public readonly string $predecessor,
    ) {
    }
}

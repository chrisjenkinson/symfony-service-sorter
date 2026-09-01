<?php

declare(strict_types=1);

namespace ChrisJenkinson\SymfonyServiceSorter\Sorter;

final class ServiceKeyNormalizer
{
    public function normalize(string $key): string
    {
        $key = str_replace('\\', '.', $key);
        $key = preg_replace('/([a-z])([A-Z])/', '$1_$2', $key) ?? $key;
        $key = preg_replace('/([A-Z])([A-Z][a-z])/', '$1_$2', $key) ?? $key;
        return strtolower($key);
    }
}

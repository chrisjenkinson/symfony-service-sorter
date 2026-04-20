<?php

declare(strict_types=1);

namespace App\Parser\Region;

final class ServiceBlockLineClassifier
{
    public function classify(string $line): LineType
    {
        if (preg_match('/^[^\\s#][^:]*:\\s*$/', rtrim($line)) === 1) {
            if (trim($line) === 'services:') {
                return LineType::ServicesHeader;
            }

            return LineType::TopLevelSibling;
        }

        $trimmed = trim($line);

        if ($trimmed === '') {
            return LineType::Blank;
        }

        if (str_starts_with($trimmed, '#')) {
            return LineType::Comment;
        }

        return LineType::Service;
    }
}

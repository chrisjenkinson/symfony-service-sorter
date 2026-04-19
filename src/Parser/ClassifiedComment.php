<?php

declare(strict_types=1);

namespace App\Parser;

final class ClassifiedComment
{
    public function __construct(
        public readonly CommentType $type,
        public readonly string $line,
        public readonly ?string $prevServiceKey,
        public readonly ?string $nextServiceKey,
        public readonly int $blankLinesBefore = 0,
        public readonly int $blankLinesAfter = 0,
    ) {
    }
}

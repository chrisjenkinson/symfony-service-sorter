<?php

declare(strict_types=1);

namespace App\Parser\Extraction;

use App\Parser\ServiceChunk;

final class ChunkDescription
{
    /**
     * @param list<int> $leadingCommentLineIndices
     * @param array<int, string> $serviceKeyByDetectorLine
     */
    public function __construct(
        public readonly ServiceChunk $chunk,
        public readonly int $keyLineIndex,
        public readonly ?string $firstLeadingComment,
        public readonly array $leadingCommentLineIndices,
        public readonly array $serviceKeyByDetectorLine,
        public readonly int $blankLinesBefore,
        public readonly int $blankLinesAfter,
    ) {
    }
}

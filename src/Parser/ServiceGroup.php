<?php

declare(strict_types=1);

namespace App\Parser;

final class ServiceGroup
{
    /**
     * @param ClassifiedComment|null $boundaryComment The boundary comment that anchors this group
     * @param list<ServiceChunk> $chunks Services in this group
     */
    public function __construct(
        public readonly ?ClassifiedComment $boundaryComment,
        public readonly array $chunks,
    ) {
    }
}

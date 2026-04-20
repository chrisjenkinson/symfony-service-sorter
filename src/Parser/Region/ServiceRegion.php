<?php

declare(strict_types=1);

namespace App\Parser\Region;

final class ServiceRegion
{
    public ?int $boundaryCommentIndex = null;
    public ?int $startingBoundaryCommentIndex = null;
    public bool $startsAfterBoundary = false;

    /** @var list<int> */
    public array $serviceIndices = [];

    public function __construct(
    ) {
    }
}

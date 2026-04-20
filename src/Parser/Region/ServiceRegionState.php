<?php

declare(strict_types=1);

namespace App\Parser\Region;

enum ServiceRegionState
{
    case Start;
    case InServicesPreamble;
    case InService;
    case InPostServiceGap;
    case InComment;
    case InBoundaryGap;
    case End;
}

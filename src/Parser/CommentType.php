<?php

declare(strict_types=1);

namespace ChrisJenkinson\SymfonyServiceSorter\Parser;

enum CommentType
{
    case Boundary;
    case ImmediatelyBefore;
    case ImmediatelyAfter;
    case Ambiguous;
}

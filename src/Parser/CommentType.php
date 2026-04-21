<?php

declare(strict_types=1);

namespace App\Parser;

enum CommentType
{
    case Boundary;
    case ImmediatelyBefore;
    case ImmediatelyAfter;
    case Ambiguous;
}

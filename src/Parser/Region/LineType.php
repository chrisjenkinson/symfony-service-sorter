<?php

declare(strict_types=1);

namespace App\Parser\Region;

enum LineType
{
    case ServicesHeader;
    case Blank;
    case Comment;
    case Service;
    case TopLevelSibling;
}

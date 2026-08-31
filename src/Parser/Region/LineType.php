<?php

declare(strict_types=1);

namespace ChrisJenkinson\SymfonyServiceSorter\Parser\Region;

enum LineType
{
    case ServicesHeader;
    case Blank;
    case Comment;
    case Service;
    case TopLevelSibling;
}

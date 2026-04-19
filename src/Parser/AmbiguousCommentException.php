<?php

declare(strict_types=1);

namespace App\Parser;

final class AmbiguousCommentException extends \RuntimeException
{
    public function __construct(
        string $prevServiceKey,
        string $nextServiceKey,
    ) {
        parent::__construct(sprintf(
            "Comment between '%s' and '%s' has no blank lines - add a blank line to clarify",
            $prevServiceKey,
            $nextServiceKey,
        ));
    }
}

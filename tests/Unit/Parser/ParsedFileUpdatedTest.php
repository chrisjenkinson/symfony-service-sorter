<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\ParsedFile;
use PHPUnit\Framework\TestCase;

final class ParsedFileUpdatedTest extends TestCase
{
    public function testParsedFileIncludesGroups(): void
    {
        $file = new ParsedFile([], 'services:', [], []);
        self::assertSame([], $file->groups);
    }
}
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\Extraction\ServiceChunkExtractor;
use PHPUnit\Framework\TestCase;

final class ServiceChunkExtractorTest extends TestCase
{
    public function testExtractsChunksRemainderAndDescriptions(): void
    {
        $extractor = new ServiceChunkExtractor();

        $result = $extractor->extract(
            [
                "    App\\Foo:\n",
                "        autowire: true\n",
                "\n",
                "    # boundary comment\n",
                "    App\\Bar:\n",
                "        autowire: true\n",
                "\n",
                "parameters:\n",
                "    locale: en\n",
            ],
            '    ',
        );

        self::assertCount(2, $result['chunks']);
        self::assertSame('App\\Foo', $result['chunks'][0]->key);
        self::assertSame('App\\Bar', $result['chunks'][1]->key);
        self::assertSame(["\n", "parameters:\n", "    locale: en\n"], $result['remainder']);

        self::assertCount(2, $result['descriptions']);
        self::assertSame(1, $result['descriptions'][0]->keyLineIndex);
        self::assertNull($result['descriptions'][0]->firstLeadingComment);

        self::assertSame(5, $result['descriptions'][1]->keyLineIndex);
        self::assertSame('    # boundary comment', $result['descriptions'][1]->firstLeadingComment);
        self::assertSame([3], $result['descriptions'][1]->leadingCommentLineIndices);
        self::assertSame([4 => 'App\\Bar', 5 => 'App\\Bar'], $result['descriptions'][1]->serviceKeyByDetectorLine);
        self::assertSame(0, $result['descriptions'][1]->blankLinesBefore);
        self::assertSame(0, $result['descriptions'][1]->blankLinesAfter);
    }
}

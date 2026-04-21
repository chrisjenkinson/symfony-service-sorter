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

    public function testPreservesPendingBlankLinesInsideChunkAndBeforeRemainder(): void
    {
        $extractor = new ServiceChunkExtractor();

        $result = $extractor->extract(
            [
                "    App\\Alpha:\n",
                "        autowire: true\n",
                "\n",
                "        # nested comment\n",
                "        bind:\n",
                "            \$foo: bar\n",
                "\n",
                "parameters:\n",
                "    locale: en\n",
            ],
            '    ',
        );

        self::assertSame(
            [
                "    App\\Alpha:\n",
                "        autowire: true\n",
                "\n",
                "        # nested comment\n",
                "        bind:\n",
                "            \$foo: bar\n",
            ],
            $result['chunks'][0]->lines,
        );
        self::assertSame(["\n", "parameters:\n", "    locale: en\n"], $result['remainder']);
        self::assertSame(0, $result['descriptions'][0]->blankLinesBefore);
        self::assertSame(0, $result['descriptions'][0]->blankLinesAfter);
    }

    public function testTracksLeadingCommentBlockAndDetectorLinesWithoutTreatingNestedCommentsAsLeading(): void
    {
        $extractor = new ServiceChunkExtractor();

        $result = $extractor->extract(
            [
                "    # alpha comment\n",
                "    # bravo comment\n",
                "\n",
                "    App\\Bravo:\n",
                "        factory:\n",
                "            # nested comment\n",
                "            - '@App\\Factory'\n",
            ],
            '    ',
        );

        self::assertCount(1, $result['chunks']);
        self::assertSame('    # alpha comment', $result['descriptions'][0]->firstLeadingComment);
        self::assertSame([0, 1], $result['descriptions'][0]->leadingCommentLineIndices);
        self::assertSame([3 => 'App\\Bravo', 4 => 'App\\Bravo', 5 => 'App\\Bravo', 6 => 'App\\Bravo'], $result['descriptions'][0]->serviceKeyByDetectorLine);
        self::assertSame(0, $result['descriptions'][0]->blankLinesBefore);
        self::assertSame(1, $result['descriptions'][0]->blankLinesAfter);
    }

    public function testPreservesPendingBlankLinesBeforeNextServiceAndAtEndOfFile(): void
    {
        $extractor = new ServiceChunkExtractor();

        $result = $extractor->extract(
            [
                "    App\\Alpha:\n",
                "        autowire: true\n",
                "\n",
                "    App\\Bravo:\n",
                "        autowire: true\n",
                "\n",
            ],
            '    ',
        );

        self::assertCount(2, $result['chunks']);
        self::assertSame(
            [
                "    App\\Alpha:\n",
                "        autowire: true\n",
                "\n",
            ],
            $result['chunks'][0]->lines,
        );
        self::assertSame(
            [
                "    App\\Bravo:\n",
                "        autowire: true\n",
                "\n",
            ],
            $result['chunks'][1]->lines,
        );
        self::assertSame([], $result['remainder']);
    }

    public function testEmptyServiceBlockPreservesPendingLinesAsRemainder(): void
    {
        $extractor = new ServiceChunkExtractor();

        $result = $extractor->extract(
            [
                "\n",
                "    # note\n",
                "\n",
                "parameters:\n",
                "    locale: en\n",
            ],
            '    ',
        );

        self::assertSame([], $result['chunks']);
        self::assertSame(
            [
                "\n",
                "    # note\n",
                "\n",
                "parameters:\n",
                "    locale: en\n",
            ],
            $result['remainder'],
        );
        self::assertSame([], $result['descriptions']);
    }

    public function testBlankLinesAfterLeadingCommentStopAtFirstNonBlankServiceLine(): void
    {
        $extractor = new ServiceChunkExtractor();

        $result = $extractor->extract(
            [
                "    # alpha comment\n",
                "\n",
                "    App\\Alpha:\n",
                "        autowire: true\n",
                "\n",
            ],
            '    ',
        );

        self::assertCount(1, $result['descriptions']);
        self::assertSame(1, $result['descriptions'][0]->blankLinesAfter);
    }

    public function testCommentOnlyTailBecomesRemainderAtEndOfExtraction(): void
    {
        $extractor = new ServiceChunkExtractor();

        $result = $extractor->extract(
            [
                "    App\\Alpha:\n",
                "        autowire: true\n",
                "\n",
                "    # trailing note\n",
                "\n",
            ],
            '    ',
        );

        self::assertCount(1, $result['chunks']);
        self::assertSame(
            [
                "    # trailing note\n",
                "\n",
            ],
            $result['remainder'],
        );
    }
}

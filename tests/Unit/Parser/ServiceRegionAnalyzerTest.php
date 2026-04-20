<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\AmbiguousCommentException;
use App\Parser\CommentType;
use App\Parser\Extraction\ChunkDescription;
use App\Parser\Region\ServiceBlockLineClassifier;
use App\Parser\Region\ServiceRegionAnalyzer;
use App\Parser\Region\ServiceRegionDetector;
use App\Parser\ServiceChunk;
use PHPUnit\Framework\TestCase;

final class ServiceRegionAnalyzerTest extends TestCase
{
    public function testAnalyzeClassifiesBoundaryAndImmediateCommentsWithServiceKeys(): void
    {
        $analyzer = new ServiceRegionAnalyzer(
            new ServiceBlockLineClassifier(),
            new ServiceRegionDetector(),
        );

        $chunks = [
            new ServiceChunk('App\Alpha', [
                "    # First comment\n",
                "    App\\Alpha:\n",
                "        autowire: true\n",
            ]),
            new ServiceChunk('App\Bravo', [
                "\n",
                "    # Boundary comment\n",
                "\n",
                "    App\\Bravo:\n",
                "        autowire: true\n",
            ]),
        ];

        $descriptions = [
            new ChunkDescription($chunks[0], 1, '    # First comment', [0], [1 => 'App\Alpha', 2 => 'App\Alpha'], 0, 0),
            new ChunkDescription($chunks[1], 6, '    # Boundary comment', [4], [6 => 'App\Bravo', 7 => 'App\Bravo'], 1, 1),
        ];

        $result = $analyzer->analyze(
            "services:\n",
            [
                "    # First comment\n",
                "    App\\Alpha:\n",
                "        autowire: true\n",
                "\n",
                "    # Boundary comment\n",
                "\n",
                "    App\\Bravo:\n",
                "        autowire: true\n",
            ],
            '    ',
            $chunks,
            $descriptions,
        );

        self::assertCount(2, $result['classifiedComments']);
        self::assertSame(CommentType::ImmediatelyAfter, $result['classifiedComments'][0]->type);
        self::assertSame('App\Alpha', $result['classifiedComments'][0]->nextServiceKey);
        self::assertSame(null, $result['classifiedComments'][0]->prevServiceKey);
        self::assertSame(0, $result['classifiedComments'][0]->blankLinesBefore);
        self::assertSame(0, $result['classifiedComments'][0]->blankLinesAfter);
        self::assertSame(CommentType::Boundary, $result['classifiedComments'][1]->type);
        self::assertSame('App\Alpha', $result['classifiedComments'][1]->prevServiceKey);
        self::assertSame('App\Bravo', $result['classifiedComments'][1]->nextServiceKey);
        self::assertSame(1, $result['classifiedComments'][1]->blankLinesBefore);
        self::assertSame(1, $result['classifiedComments'][1]->blankLinesAfter);
        self::assertCount(2, $result['groups']);
        self::assertNull($result['groups'][0]->boundaryComment);
        self::assertSame('App\Alpha', $result['groups'][0]->chunks[0]->key);
        self::assertSame('    # Boundary comment', $result['groups'][1]->boundaryComment?->line);
        self::assertSame('App\Bravo', $result['groups'][1]->chunks[0]->key);
    }

    public function testAnalyzeMapsAmbiguousCommentToServiceKeys(): void
    {
        $analyzer = new ServiceRegionAnalyzer(
            new ServiceBlockLineClassifier(),
            new ServiceRegionDetector(),
        );

        $chunks = [
            new ServiceChunk('App\Bar', ["    App\\Bar:\n", "        autowire: true\n"]),
            new ServiceChunk('App\Foo', ["    # Ambiguous comment\n", "    App\\Foo:\n", "        autowire: true\n"]),
        ];

        $descriptions = [
            new ChunkDescription($chunks[0], 0, null, [], [0 => 'App\Bar', 1 => 'App\Bar'], 0, 0),
            new ChunkDescription($chunks[1], 3, '    # Ambiguous comment', [2], [3 => 'App\Foo', 4 => 'App\Foo'], 0, 0),
        ];

        $this->expectException(AmbiguousCommentException::class);
        $this->expectExceptionMessage("Comment between 'App\\Bar' and 'App\\Foo' has no blank lines");

        $analyzer->analyze(
            "services:\n",
            [
                "    App\\Bar:\n",
                "        autowire: true\n",
                "    # Ambiguous comment\n",
                "    App\\Foo:\n",
                "        autowire: true\n",
            ],
            '    ',
            $chunks,
            $descriptions,
        );
    }
}

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

    public function testAnalyzeDoesNotTreatImmediateBeforeCommentAsBoundary(): void
    {
        $analyzer = new ServiceRegionAnalyzer(
            new ServiceBlockLineClassifier(),
            new ServiceRegionDetector(),
        );

        $chunks = [
            new ServiceChunk('App\Alpha', ["    App\\Alpha:\n", "        autowire: true\n"]),
            new ServiceChunk('App\Bravo', [
                "\n",
                "    # note\n",
                "    App\\Bravo:\n",
                "        autowire: true\n",
            ]),
        ];

        $descriptions = [
            new ChunkDescription($chunks[0], 0, null, [], [0 => 'App\Alpha', 1 => 'App\Alpha'], 0, 0),
            new ChunkDescription($chunks[1], 4, '    # note', [3], [4 => 'App\Bravo', 5 => 'App\Bravo'], 1, 0),
        ];

        $result = $analyzer->analyze(
            "services:\n",
            [
                "    App\\Alpha:\n",
                "        autowire: true\n",
                "\n",
                "    # note\n",
                "    App\\Bravo:\n",
                "        autowire: true\n",
            ],
            '    ',
            $chunks,
            $descriptions,
        );

        self::assertCount(1, $result['classifiedComments']);
        self::assertSame(CommentType::ImmediatelyBefore, $result['classifiedComments'][0]->type);
        self::assertSame('App\Alpha', $result['classifiedComments'][0]->prevServiceKey);
        self::assertSame('App\Bravo', $result['classifiedComments'][0]->nextServiceKey);
    }

    public function testAnalyzeSortsGroupsByFirstChunkKey(): void
    {
        $analyzer = new ServiceRegionAnalyzer(
            new ServiceBlockLineClassifier(),
            new ServiceRegionDetector(),
        );

        $chunks = [
            new ServiceChunk('Zulu', [
                "\n",
                "    # zulu boundary\n",
                "\n",
                "    Zulu:\n",
                "        autowire: true\n",
            ]),
            new ServiceChunk('Beta', [
                "\n",
                "    # beta boundary\n",
                "\n",
                "    Beta:\n",
                "        autowire: true\n",
            ]),
            new ServiceChunk('_defaults', [
                "    _defaults:\n",
                "        autowire: true\n",
            ]),
        ];

        $descriptions = [
            new ChunkDescription($chunks[0], 3, '    # zulu boundary', [1], [3 => 'Zulu', 4 => 'Zulu'], 1, 1),
            new ChunkDescription($chunks[1], 8, '    # beta boundary', [6], [8 => 'Beta', 9 => 'Beta'], 1, 1),
            new ChunkDescription($chunks[2], 10, null, [], [10 => '_defaults', 11 => '_defaults'], 0, 0),
        ];

        $result = $analyzer->analyze(
            "services:\n",
            [
                "\n",
                "    # zulu boundary\n",
                "\n",
                "    Zulu:\n",
                "        autowire: true\n",
                "\n",
                "    # beta boundary\n",
                "\n",
                "    Beta:\n",
                "        autowire: true\n",
                "    _defaults:\n",
                "        autowire: true\n",
            ],
            '    ',
            $chunks,
            $descriptions,
        );

        self::assertCount(2, $result['groups']);
        self::assertSame('Beta', $result['groups'][0]->chunks[0]->key);
        self::assertSame('_defaults', $result['groups'][0]->chunks[1]->key);
        self::assertSame('Zulu', $result['groups'][1]->chunks[0]->key);
        self::assertSame('    # beta boundary', $result['groups'][0]->boundaryComment?->line);
        self::assertSame('    # zulu boundary', $result['groups'][1]->boundaryComment?->line);
    }

    public function testAnalyzeTreatsCommentAsNonBoundaryWhenNoMatchingRegionExists(): void
    {
        $analyzer = new ServiceRegionAnalyzer(
            new ServiceBlockLineClassifier(),
            new ServiceRegionDetector(),
        );

        $chunks = [
            new ServiceChunk('App\Alpha', [
                "    # orphan comment\n",
                "    App\\Alpha:\n",
                "        autowire: true\n",
            ]),
        ];

        $descriptions = [
            new ChunkDescription($chunks[0], 99, '    # orphan comment', [0], [1 => 'App\Alpha', 2 => 'App\Alpha'], 0, 0),
        ];

        $result = $analyzer->analyze(
            "services:\n",
            [
                "    # orphan comment\n",
                "    App\\Alpha:\n",
                "        autowire: true\n",
            ],
            '    ',
            $chunks,
            $descriptions,
        );

        self::assertCount(1, $result['classifiedComments']);
        self::assertSame(CommentType::ImmediatelyAfter, $result['classifiedComments'][0]->type);
    }

    public function testAnalyzeRequiresBoundaryCommentIndexToMatchLeadingCommentLines(): void
    {
        $analyzer = new ServiceRegionAnalyzer(
            new ServiceBlockLineClassifier(),
            new ServiceRegionDetector(),
        );

        $chunks = [
            new ServiceChunk('App\Alpha', ["    App\\Alpha:\n", "        autowire: true\n"]),
            new ServiceChunk('App\Bravo', [
                "\n",
                "    # not-mapped-as-boundary\n",
                "\n",
                "    App\\Bravo:\n",
                "        autowire: true\n",
            ]),
        ];

        $descriptions = [
            new ChunkDescription($chunks[0], 0, null, [], [0 => 'App\Alpha', 1 => 'App\Alpha'], 0, 0),
            new ChunkDescription($chunks[1], 4, '    # not-mapped-as-boundary', [], [4 => 'App\Bravo', 5 => 'App\Bravo'], 1, 1),
        ];

        $result = $analyzer->analyze(
            "services:\n",
            [
                "    App\\Alpha:\n",
                "        autowire: true\n",
                "\n",
                "    # not-mapped-as-boundary\n",
                "\n",
                "    App\\Bravo:\n",
                "        autowire: true\n",
            ],
            '    ',
            $chunks,
            $descriptions,
        );

        self::assertCount(1, $result['classifiedComments']);
        self::assertSame(CommentType::ImmediatelyBefore, $result['classifiedComments'][0]->type);
    }
}

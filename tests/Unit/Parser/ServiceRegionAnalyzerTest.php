<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\AmbiguousCommentException;
use App\Parser\Extraction\ChunkDescription;
use App\Parser\Region\ServiceBlockLineClassifier;
use App\Parser\Region\ServiceRegionAnalyzer;
use App\Parser\Region\ServiceRegionDetector;
use App\Parser\ServiceChunk;
use PHPUnit\Framework\TestCase;

final class ServiceRegionAnalyzerTest extends TestCase
{
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

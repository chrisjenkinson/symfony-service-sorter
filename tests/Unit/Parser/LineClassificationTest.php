<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\Region\LineType;
use App\Parser\Region\ServiceBlockLineClassifier;
use App\Parser\Region\ServiceRegionDetector;
use PHPUnit\Framework\TestCase;

final class LineClassificationTest extends TestCase
{
    public function testClassifiesBoundaryCommentsFixture(): void
    {
        $yaml = file_get_contents(__DIR__ . '/../../fixtures/boundary-comments/input.yaml');
        self::assertNotFalse($yaml);

        $lines = explode("\n", $yaml);

        $classifier = new ServiceBlockLineClassifier();

        echo "+-----+------------------------------------------+--------+\n";
        echo "| #   | Line                                   | Type   |\n";
        echo "+-----+------------------------------------------+--------+\n";

        $lineTypes = [];
        foreach ($lines as $i => $line) {
            $lineNum = str_pad((string)($i + 1), 3, ' ', STR_PAD_LEFT);
            $displayLine = substr($line, 0, 36);
            $displayLine = str_pad($displayLine, 36);

            $type = $classifier->classify($line);
            $lineTypes[] = $type;

            $type = str_pad($type->name, 6);
            echo "|{$lineNum}|{$displayLine}|{$type}|\n";
        }

        echo "+-----+------------------------------------------+--------+\n\n";

        $detector = new ServiceRegionDetector();
        $regions = $detector->detect($lineTypes);

        echo "=== REGIONS ===\n";
        echo "+--------+---------------------------+---------------------------+\n";
        echo "| Region | Boundary (line #)         | Services (line #s)        |\n";
        echo "+--------+---------------------------+---------------------------+\n";

        foreach ($regions as $i => $region) {
            $boundary = $region->boundaryCommentIndex !== null
                ? (string)($region->boundaryCommentIndex + 1)
                : 'none';
            $services = implode(',', array_map(
                fn ($i) => (string)($i + 1),
                $region->serviceIndices
            ));

            $boundary = str_pad($boundary, 23);
            $services = substr($services, 0, 27);

            echo '| ' . str_pad((string)$i, 5) . " | {$boundary} | {$services} |\n";
        }

        echo "+--------+---------------------------+---------------------------+\n";

        self::assertCount(3, $regions);
    }
}

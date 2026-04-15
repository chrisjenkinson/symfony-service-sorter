<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Parser\YamlServiceParser;
use App\Sorter\ServiceKeyNormalizer;
use App\Sorter\ServicesSorter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SortServicesIntegrationTest extends TestCase
{
    private YamlServiceParser $parser;
    private ServicesSorter $sorter;

    protected function setUp(): void
    {
        $this->parser = new YamlServiceParser();
        $this->sorter = new ServicesSorter(new ServiceKeyNormalizer());
    }

    #[DataProvider('fixtureProvider')]
    public function testFixture(string $fixtureName): void
    {
        $fixtureDir = __DIR__ . '/../fixtures/' . $fixtureName;
        $input = file_get_contents($fixtureDir . '/input.yaml');
        $expected = file_get_contents($fixtureDir . '/expected.yaml');

        self::assertNotFalse($input, "Could not read input fixture: $fixtureName");
        self::assertNotFalse($expected, "Could not read expected fixture: $fixtureName");

        $parsedFile = $this->parser->parse($input);
        $actual = $this->sorter->sort($parsedFile);

        self::assertSame($expected, $actual, "Fixture '$fixtureName' did not produce expected output");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fixtureProvider(): array
    {
        return [
            'basic' => ['basic'],
            'comments' => ['comments'],
            'underscore-pinned' => ['underscore-pinned'],
            'empty-services' => ['empty-services'],
            'no-services-key' => ['no-services-key'],
            'multiline-values' => ['multiline-values'],
            'case-insensitive' => ['case-insensitive'],
        ];
    }
}

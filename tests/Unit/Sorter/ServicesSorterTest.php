<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sorter;

use App\Parser\ParsedFile;
use App\Parser\ServiceChunk;
use App\Sorter\ServicesSorter;
use PHPUnit\Framework\TestCase;

final class ServicesSorterTest extends TestCase
{
    private ServicesSorter $sorter;

    protected function setUp(): void
    {
        $this->sorter = new ServicesSorter();
    }

    public function testSortsChunksAlphabetically(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('App\\Zebra', ["    App\\Zebra:\n", "        autowire: true\n"]),
                new ServiceChunk('App\\Alpha', ["    App\\Alpha:\n", "        autowire: true\n"]),
            ],
            remainder: [],
        );

        $output = $this->sorter->sort($parsedFile);

        $zebraPos = strpos($output, 'App\\Zebra');
        $alphaPos = strpos($output, 'App\\Alpha');
        self::assertGreaterThan($alphaPos, $zebraPos, 'Alpha should come before Zebra');
    }

    public function testUnderscorePrefixedChunksPinnedFirst(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('App\\Foo', ["    App\\Foo:\n", "        autowire: true\n"]),
                new ServiceChunk('_defaults', ["    _defaults:\n", "        autowire: true\n"]),
            ],
            remainder: [],
        );

        $output = $this->sorter->sort($parsedFile);

        $defaultsPos = strpos($output, '_defaults');
        $fooPos = strpos($output, 'App\\Foo');
        self::assertGreaterThan($defaultsPos, $fooPos, '_defaults should come before App\\Foo');
    }

    public function testSortNormalizesNamespaceSeparatorsAndPascalCase(): void
    {
        // Normalisation: replace '\' with '.', PascalCase→snake_case, lowercase.
        // App\Other    → "app.other"
        // App\ReadModel → "app.read_model"  (ReadModel → Read_Model)
        // app.zebra    → "app.zebra"
        // Sort order: app.other < app.read_model < app.zebra
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('app.zebra', ["    app.zebra:\n", "        class: Foo\n"]),
                new ServiceChunk('App\\ReadModel', ["    App\\ReadModel:\n", "        class: Bar\n"]),
                new ServiceChunk('App\\Other', ["    App\\Other:\n", "        class: Baz\n"]),
            ],
            remainder: [],
        );

        $output = $this->sorter->sort($parsedFile);

        $otherPos = strpos($output, 'App\\Other');
        $readModelPos = strpos($output, 'App\\ReadModel');
        $zebraPos = strpos($output, 'app.zebra');

        self::assertGreaterThan($otherPos, $readModelPos, 'App\\Other should come before App\\ReadModel');
        self::assertGreaterThan($readModelPos, $zebraPos, 'App\\ReadModel should come before app.zebra');
    }

    public function testPreambleAndRemainderArePreserved(): void
    {
        $parsedFile = new ParsedFile(
            preamble: ["parameters:\n", "    locale: 'en'\n", "\n"],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('App\\Foo', ["    App\\Foo:\n", "        autowire: true\n"]),
            ],
            remainder: ["\nwhen@prod:\n", "    parameters:\n", "        foo: bar\n"],
        );

        $output = $this->sorter->sort($parsedFile);

        self::assertStringContainsString("parameters:\n", $output);
        self::assertStringContainsString("when@prod:", $output);
        $parametersPos = strpos($output, 'parameters:');
        $servicesPos = strpos($output, 'services:');
        $whenPos = strpos($output, 'when@prod:');
        self::assertGreaterThan($parametersPos, $servicesPos);
        self::assertGreaterThan($servicesPos, $whenPos);
    }

    public function testNoServicesKeyReturnsFileUnchanged(): void
    {
        $parsedFile = new ParsedFile(
            preamble: ["parameters:\n", "    locale: 'en'\n"],
            servicesHeader: '',
            chunks: [],
            remainder: [],
        );

        $output = $this->sorter->sort($parsedFile);

        self::assertStringContainsString('parameters:', $output);
        self::assertStringNotContainsString('services:', $output);
    }

    public function testEmptyServicesBlockReturnsFileUnchanged(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [],
            remainder: [],
        );

        $output = $this->sorter->sort($parsedFile);

        self::assertStringContainsString("services:\n", $output);
    }

    public function testMultipleUnderscoreEntriesSortedAmongThemselves(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('_instanceof', ["    _instanceof:\n", "        foo: bar\n"]),
                new ServiceChunk('_defaults', ["    _defaults:\n", "        autowire: true\n"]),
            ],
            remainder: [],
        );

        $output = $this->sorter->sort($parsedFile);

        $defaultsPos = strpos($output, '_defaults');
        $instanceofPos = strpos($output, '_instanceof');
        self::assertGreaterThan($defaultsPos, $instanceofPos, '_defaults should come before _instanceof');
    }
}

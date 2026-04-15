<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sorter;

use App\Parser\ParsedFile;
use App\Parser\ServiceChunk;
use App\Sorter\OutOfOrderEntry;
use App\Sorter\ServiceKeyNormalizer;
use App\Sorter\ServiceKeySorter;
use App\Sorter\ServiceOrderChecker;
use PHPUnit\Framework\TestCase;

final class ServiceOrderCheckerTest extends TestCase
{
    private ServiceOrderChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new ServiceOrderChecker(new ServiceKeySorter(new ServiceKeyNormalizer()));
    }

    public function testReturnsEmptyForAlreadySortedServices(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('App\\Alpha', ["    App\\Alpha:\n", "        autowire: true\n"]),
                new ServiceChunk('App\\Zebra', ["    App\\Zebra:\n", "        autowire: true\n"]),
            ],
            remainder: [],
        );

        $result = $this->checker->check($parsedFile);

        self::assertSame([], $result);
    }

    public function testDetectsSingleOutOfOrderService(): void
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

        $result = $this->checker->check($parsedFile);

        self::assertCount(1, $result);
        self::assertSame('App\\Zebra', $result[0]->key);
        self::assertSame('App\\Alpha', $result[0]->predecessor);
    }

    public function testCbtScenario(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('C', ["    C:\n"]),
                new ServiceChunk('A', ["    A:\n"]),
                new ServiceChunk('B', ["    B:\n"]),
            ],
            remainder: [],
        );

        $result = $this->checker->check($parsedFile);

        self::assertCount(1, $result);
        self::assertSame('C', $result[0]->key);
        self::assertSame('B', $result[0]->predecessor);
    }

    public function testZyxAscenario(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('Z', ["    Z:\n"]),
                new ServiceChunk('Y', ["    Y:\n"]),
                new ServiceChunk('X', ["    X:\n"]),
                new ServiceChunk('A', ["    A:\n"]),
            ],
            remainder: [],
        );

        $result = $this->checker->check($parsedFile);

        self::assertCount(3, $result);
        self::assertSame('Z', $result[0]->key);
        self::assertSame('Y', $result[0]->predecessor);
        self::assertSame('Y', $result[1]->key);
        self::assertSame('X', $result[1]->predecessor);
        self::assertSame('X', $result[2]->key);
        self::assertSame('A', $result[2]->predecessor);
    }

    public function testSingleSwapAcbd(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('A', ["    A:\n"]),
                new ServiceChunk('C', ["    C:\n"]),
                new ServiceChunk('B', ["    B:\n"]),
                new ServiceChunk('D', ["    D:\n"]),
            ],
            remainder: [],
        );

        $result = $this->checker->check($parsedFile);

        self::assertCount(1, $result);
        self::assertSame('C', $result[0]->key);
        self::assertSame('B', $result[0]->predecessor);
    }

    public function testTwoSeparateSwapsBadc(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('B', ["    B:\n"]),
                new ServiceChunk('A', ["    A:\n"]),
                new ServiceChunk('D', ["    D:\n"]),
                new ServiceChunk('C', ["    C:\n"]),
            ],
            remainder: [],
        );

        $result = $this->checker->check($parsedFile);

        self::assertCount(2, $result);
        self::assertSame('B', $result[0]->key);
        self::assertSame('A', $result[0]->predecessor);
        self::assertSame('D', $result[1]->key);
        self::assertSame('C', $result[1]->predecessor);
    }

    public function testUnderscorePrefixedKeysAreAlsoChecked(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('_instanceof', ["    _instanceof:\n"]),
                new ServiceChunk('_defaults', ["    _defaults:\n"]),
                new ServiceChunk('App\\Zebra', ["    App\\Zebra:\n"]),
                new ServiceChunk('App\\Alpha', ["    App\\Alpha:\n"]),
            ],
            remainder: [],
        );

        $result = $this->checker->check($parsedFile);

        self::assertCount(2, $result);
        self::assertSame('_instanceof', $result[0]->key);
        self::assertSame('_defaults', $result[0]->predecessor);
        self::assertSame('App\\Zebra', $result[1]->key);
        self::assertSame('App\\Alpha', $result[1]->predecessor);
    }

    public function testEmptyChunksReturnsEmpty(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [],
            remainder: [],
        );

        $result = $this->checker->check($parsedFile);

        self::assertSame([], $result);
    }

    public function testSingleServiceReturnsEmpty(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('App\\Foo', ["    App\\Foo:\n"]),
            ],
            remainder: [],
        );

        $result = $this->checker->check($parsedFile);

        self::assertSame([], $result);
    }

    public function testFirstServiceIsNeverFlagged(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('Z', ["    Z:\n"]),
                new ServiceChunk('A', ["    A:\n"]),
            ],
            remainder: [],
        );

        $result = $this->checker->check($parsedFile);

        self::assertCount(1, $result);
        self::assertSame('Z', $result[0]->key);
        self::assertSame('A', $result[0]->predecessor);
    }

    public function testSingleServiceIsNotFlaggedEvenWithoutGuard(): void
    {
        $parsedFile = new ParsedFile(
            preamble: [],
            servicesHeader: "services:\n",
            chunks: [
                new ServiceChunk('App\\Foo', ["    App\\Foo:\n"]),
            ],
            remainder: [],
        );

        $result = $this->checker->check($parsedFile);

        self::assertSame([], $result);
    }
}

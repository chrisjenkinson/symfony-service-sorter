<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sorter;

use App\Parser\ServiceChunk;
use App\Sorter\DuplicateServiceKeyException;
use App\Sorter\ServiceKeyNormalizer;
use App\Sorter\ServiceKeySorter;
use PHPUnit\Framework\TestCase;

final class ServiceKeySorterTest extends TestCase
{
    private ServiceKeySorter $keySorter;

    protected function setUp(): void
    {
        $this->keySorter = new ServiceKeySorter(new ServiceKeyNormalizer());
    }

    public function testSortKeysAlphabetically(): void
    {
        $result = $this->keySorter->sortKeys(['Z', 'A', 'M']);

        self::assertSame(['A', 'M', 'Z'], $result);
    }

    public function testSortKeysUnderscoreFirst(): void
    {
        $result = $this->keySorter->sortKeys(['App\\Foo', '_defaults', '_instanceof']);

        self::assertSame(['_defaults', '_instanceof', 'App\\Foo'], $result);
    }

    public function testSortKeysThrowsOnDuplicates(): void
    {
        $this->expectException(DuplicateServiceKeyException::class);
        $this->expectExceptionMessage('Duplicate service key found: "App\\Foo"');

        $this->keySorter->sortKeys(['App\\Foo', 'App\\Bar', 'App\\Foo']);
    }

    public function testSortKeysNoDuplicates(): void
    {
        $result = $this->keySorter->sortKeys(['App\\Bar', 'App\\Foo']);

        self::assertSame(['App\\Bar', 'App\\Foo'], $result);
    }

    public function testSortChunksStableSortsWithEqualNormalizedKeys(): void
    {
        $chunks = [
            new ServiceChunk('app.read_model', ["    app.read_model:\n", "        class: Foo\n"]),
            new ServiceChunk('App\\ReadModel', ["    App\\ReadModel:\n", "        class: Bar\n"]),
        ];

        $result = $this->keySorter->sortChunks($chunks);

        self::assertSame('app.read_model', $result[0]->key);
        self::assertSame('App\\ReadModel', $result[1]->key);
    }
}

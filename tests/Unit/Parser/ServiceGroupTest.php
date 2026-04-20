<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\ServiceChunk;
use App\Parser\ServiceGroup;
use PHPUnit\Framework\TestCase;

final class ServiceGroupTest extends TestCase
{
    public function testServiceGroupCanBeCreated(): void
    {
        $group = new ServiceGroup(null, []);
        self::assertNull($group->boundaryComment);
        self::assertSame([], $group->chunks);
    }

    public function testServiceGroupCanBeCreatedWithChunks(): void
    {
        $chunks = [
            new ServiceChunk('App\Foo', ['App\Foo:']),
            new ServiceChunk('App\Bar', ['App\Bar:']),
        ];
        $group = new ServiceGroup(null, $chunks);
        self::assertNull($group->boundaryComment);
        self::assertCount(2, $group->chunks);
    }
}

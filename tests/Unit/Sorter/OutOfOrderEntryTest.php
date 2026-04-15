<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sorter;

use App\Sorter\OutOfOrderEntry;
use PHPUnit\Framework\TestCase;

final class OutOfOrderEntryTest extends TestCase
{
    public function testHoldsKeyAndPredecessor(): void
    {
        $entry = new OutOfOrderEntry('App\\ZebraService', 'App\\AlphaService');

        self::assertSame('App\\ZebraService', $entry->key);
        self::assertSame('App\\AlphaService', $entry->predecessor);
    }
}

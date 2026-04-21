<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\AmbiguousCommentException;
use App\Parser\Region\LineType;
use App\Parser\Region\ServiceRegionDetector;
use PHPUnit\Framework\TestCase;

final class ServiceRegionDetectorTest extends TestCase
{
    public function testCommentAfterBlankAttachesToFollowingServiceInSameRegion(): void
    {
        $detector = new ServiceRegionDetector();

        $regions = $detector->detect([
            LineType::ServicesHeader,
            LineType::Service,
            LineType::Blank,
            LineType::Comment,
            LineType::Service,
        ]);

        self::assertCount(1, $regions);
        self::assertSame(3, $regions[0]->boundaryCommentIndex);
        self::assertSame([1, 4], $regions[0]->serviceIndices);
    }

    public function testCommentFollowedByBlankStartsNextRegion(): void
    {
        $detector = new ServiceRegionDetector();

        $regions = $detector->detect([
            LineType::ServicesHeader,
            LineType::Service,
            LineType::Blank,
            LineType::Comment,
            LineType::Blank,
            LineType::Service,
        ]);

        self::assertCount(2, $regions);
        self::assertNull($regions[0]->boundaryCommentIndex);
        self::assertSame([1], $regions[0]->serviceIndices);
        self::assertSame(3, $regions[1]->boundaryCommentIndex);
        self::assertSame([5], $regions[1]->serviceIndices);
    }

    public function testCommentImmediatelyAfterServiceRaisesAmbiguityException(): void
    {
        $detector = new ServiceRegionDetector();

        $this->expectException(AmbiguousCommentException::class);

        $detector->detect([
            LineType::ServicesHeader,
            LineType::Service,
            LineType::Comment,
            LineType::Service,
        ]);
    }

    public function testTopLevelBlockExitEndsDetection(): void
    {
        $detector = new ServiceRegionDetector();

        $regions = $detector->detect([
            LineType::ServicesHeader,
            LineType::Service,
            LineType::Blank,
            LineType::TopLevelSibling,
            LineType::Service,
        ]);

        self::assertCount(1, $regions);
        self::assertSame([1], $regions[0]->serviceIndices);
    }
}

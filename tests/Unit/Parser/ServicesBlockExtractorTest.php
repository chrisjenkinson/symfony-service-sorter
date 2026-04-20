<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\Extraction\ServicesBlockExtractor;
use PHPUnit\Framework\TestCase;

final class ServicesBlockExtractorTest extends TestCase
{
    public function testExtractsServicesBlockMetadata(): void
    {
        $extractor = new ServicesBlockExtractor();

        $result = $extractor->extract("parameters:\n    locale: en\n\nservices:\n    App\\Foo:\n        autowire: true\n");

        self::assertSame("services:\n", $result['servicesHeader']);
        self::assertSame(["parameters:\n", "    locale: en\n", "\n"], $result['preamble']);
        self::assertSame(["    App\\Foo:\n", "        autowire: true\n"], $result['blockLines']);
    }
}

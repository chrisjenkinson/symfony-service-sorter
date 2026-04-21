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

    public function testNoServicesKeyReturnsWholeFileAsPreamble(): void
    {
        $extractor = new ServicesBlockExtractor();

        $result = $extractor->extract("parameters:\n    locale: en\n");

        self::assertSame(["parameters:\n", "    locale: en\n"], $result['preamble']);
        self::assertSame('', $result['servicesHeader']);
        self::assertSame([], $result['blockLines']);
        self::assertNull($result['blockIndent']);
        self::assertSame([], $result['emptyBlockRemainder']);
    }

    public function testEmptyServicesBlockSplitsTopLevelRemainder(): void
    {
        $extractor = new ServicesBlockExtractor();

        $result = $extractor->extract("services:\n\n    # note\n\nparameters:\n    locale: en\n");

        self::assertSame("services:\n", $result['servicesHeader']);
        self::assertNull($result['blockIndent']);
        self::assertSame(["\n", "    # note\n", "\n", "parameters:\n", "    locale: en\n"], $result['blockLines']);
        self::assertSame(["\n", "    # note\n", "\n", "parameters:\n", "    locale: en\n"], $result['emptyBlockRemainder']);
    }

    public function testOnlyMatchesTopLevelServicesHeaderLine(): void
    {
        $extractor = new ServicesBlockExtractor();

        $result = $extractor->extract("parameters:\n    services: foo\n    something: else\n");

        self::assertSame('', $result['servicesHeader']);
        self::assertSame(["parameters:\n", "    services: foo\n", "    something: else\n"], $result['preamble']);
    }

    public function testMatchesServicesHeaderWithTrailingWhitespace(): void
    {
        $extractor = new ServicesBlockExtractor();

        $result = $extractor->extract("services:   \n    App\\Foo:\n        autowire: true\n");

        self::assertSame("services:   \n", $result['servicesHeader']);
        self::assertSame(["    App\\Foo:\n", "        autowire: true\n"], $result['blockLines']);
    }

    public function testDoesNotMatchServicesSuffixOrInlineValue(): void
    {
        $extractor = new ServicesBlockExtractor();

        $result = $extractor->extract("foo services:\nservices: false\nparameters:\n");

        self::assertSame('', $result['servicesHeader']);
        self::assertSame(["foo services:\n", "services: false\n", "parameters:\n"], $result['preamble']);
    }
}

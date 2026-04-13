<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\YamlServiceParser;
use PHPUnit\Framework\TestCase;

final class YamlServiceParserTest extends TestCase
{
    private YamlServiceParser $parser;

    protected function setUp(): void
    {
        $this->parser = new YamlServiceParser();
    }

    public function testPreambleIsPreserved(): void
    {
        $yaml = "parameters:\n    locale: 'en'\n\nservices:\n    App\\Foo:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertStringContainsString('parameters:', implode('', $result->preamble));
        self::assertCount(1, $result->chunks);
    }

    public function testSingleServiceChunkHasCorrectKey(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(1, $result->chunks);
        self::assertSame('App\\Foo', $result->chunks[0]->key);
    }

    public function testMultipleServiceChunksAreExtracted(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n    App\\Bar:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(2, $result->chunks);
        self::assertSame('App\\Foo', $result->chunks[0]->key);
        self::assertSame('App\\Bar', $result->chunks[1]->key);
    }

    public function testCommentTravelsWithFollowingChunk(): void
    {
        $yaml = "services:\n    # This is Foo\n    App\\Foo:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(1, $result->chunks);
        self::assertStringContainsString('# This is Foo', implode('', $result->chunks[0]->lines));
    }

    public function testBlankLineTravelsWithFollowingChunk(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n\n    App\\Bar:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(2, $result->chunks);
        self::assertStringContainsString("\n", implode('', $result->chunks[1]->lines));
    }

    public function testRemainderAfterServicesBlockIsPreserved(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n\nwhen@prod:\n    parameters:\n        foo: bar\n";

        $result = $this->parser->parse($yaml);

        self::assertStringContainsString('when@prod:', implode('', $result->remainder));
    }

    public function testEmptyServicesBlockProducesNoChunks(): void
    {
        $yaml = "services:\n\nparameters:\n    foo: bar\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(0, $result->chunks);
        self::assertStringContainsString('parameters:', implode('', $result->remainder));
    }

    public function testNoServicesKeyProducesEmptyParsedFile(): void
    {
        $yaml = "parameters:\n    locale: 'en'\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(0, $result->chunks);
        self::assertSame('', $result->servicesHeader);
        self::assertStringContainsString('parameters:', implode('', $result->preamble));
    }

    public function testFlowSequenceBodyStaysWithItsChunk(): void
    {
        $yaml = "services:\n    App\\Foo:\n        factory:\n            [\n                '@App\\Bar',\n                'create',\n            ]\n    App\\Baz:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(2, $result->chunks);
        self::assertStringContainsString('factory:', implode('', $result->chunks[0]->lines));
        self::assertStringContainsString('@App\\Bar', implode('', $result->chunks[0]->lines));
    }

    public function testUnderscorePrefixedEntryIsExtracted(): void
    {
        $yaml = "services:\n    _defaults:\n        autowire: true\n    App\\Foo:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(2, $result->chunks);
        self::assertSame('_defaults', $result->chunks[0]->key);
    }
}

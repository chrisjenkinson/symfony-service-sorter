<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\AmbiguousCommentException;
use App\Parser\CommentType;
use App\Parser\Extraction\ServiceChunkExtractor;
use App\Parser\Extraction\ServicesBlockExtractor;
use App\Parser\Region\ServiceBlockLineClassifier;
use App\Parser\Region\ServiceRegionAnalyzer;
use App\Parser\Region\ServiceRegionDetector;
use App\Parser\YamlServiceParser;
use PHPUnit\Framework\TestCase;

final class YamlServiceParserTest extends TestCase
{
    private YamlServiceParser $parser;

    protected function setUp(): void
    {
        $this->parser = new YamlServiceParser(
            new ServicesBlockExtractor(),
            new ServiceChunkExtractor(),
            new ServiceRegionAnalyzer(
                new ServiceBlockLineClassifier(),
                new ServiceRegionDetector(),
            ),
        );
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
        $yaml = "services:\n\n    # This is Foo\n    App\\Foo:\n        autowire: true\n";

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

    public function testFindServicesLineRequiresStartOfLineAnchor(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertSame("services:\n", $result->servicesHeader);
    }

    public function testFindServicesLineDoesNotMatchInlineServicesKey(): void
    {
        $yaml = "parameters:\n    locale: services: en\n";

        $result = $this->parser->parse($yaml);

        self::assertSame('', $result->servicesHeader);
    }

    public function testFindServicesLineMatchesWithTrailingWhitespace(): void
    {
        $yaml = "services:   \n    App\\Foo:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertNotEmpty($result->chunks);
    }

    public function testBlankLineWithinServiceChunkIsPreserved(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n\n        tags: [foo]\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(1, $result->chunks);
        self::assertSame(
            ["    App\\Foo:\n", "        autowire: true\n", "\n", "        tags: [foo]\n"],
            $result->chunks[0]->lines,
        );
    }

    public function testCommentAtBlockIndentBetweenChunksIsSeparated(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n\n    # A comment\n    App\\Bar:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(2, $result->chunks);
        self::assertSame('App\\Foo', $result->chunks[0]->key);
        self::assertSame('App\\Bar', $result->chunks[1]->key);
        self::assertContains("\n", $result->chunks[0]->lines, 'blank line should be in foo chunk');
        self::assertContains("    # A comment\n", $result->chunks[1]->lines);
    }

    public function testNewlineOnlyLineInBlockIsSkipped(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n\n    App\\Bar:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(2, $result->chunks);
    }

    public function testCommentAtDifferentIndentStaysWithCurrentChunk(): void
    {
        $yaml = "services:\n    App\\Foo:\n        # inline comment\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(1, $result->chunks);
        $chunkText = implode('', $result->chunks[0]->lines);
        self::assertStringContainsString('# inline comment', $chunkText);
    }

    public function testTrailingPendingLinesBecomeRemainder(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n\n\nwhen@prod:\n    parameters:\n        foo: bar\n";

        $result = $this->parser->parse($yaml);

        $remainder = implode('', $result->remainder);
        self::assertStringContainsString('when@prod:', $remainder);
        self::assertStringContainsString('foo: bar', $remainder);
    }

    public function testPendingLinesMergedIntoLastChunkAndRemainder(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n\n\nwhen@prod:\n    foo: bar\n";

        $result = $this->parser->parse($yaml);

        self::assertSame(
            ["    App\\Foo:\n", "        autowire: true\n"],
            $result->chunks[0]->lines,
        );
        self::assertContains("\n", $result->remainder);
        self::assertContains("when@prod:\n", $result->remainder);
    }

    public function testIndentedCommentPreservesPendingLinesInChunk(): void
    {
        $yaml = "services:\n    App\\Foo:\n        autowire: true\n        # nested comment\n        bar: baz\n";

        $result = $this->parser->parse($yaml);

        self::assertCount(1, $result->chunks);
        $chunkText = implode('', $result->chunks[0]->lines);
        self::assertStringContainsString('autowire: true', $chunkText);
        self::assertStringContainsString('# nested comment', $chunkText);
        self::assertStringContainsString('bar: baz', $chunkText);
    }

    public function testFindServicesLineDoesNotMatchMidLine(): void
    {
        $yaml = "# services: is not a services key\nservices:\n    App\\Foo:\n        autowire: true\n";

        $result = $this->parser->parse($yaml);

        self::assertNotEmpty($result->chunks);
    }

    public function testTrackBlankLinesAroundComment(): void
    {
        $yaml = <<<YAML
services:
    # A comment

    App\Foo:
        autowire: true

    # Another comment


    App\Bar:
        autowire: true
YAML;

        $result = $this->parser->parse($yaml);

        self::assertCount(2, $result->chunks);
        $firstChunk = implode('', $result->chunks[0]->lines);
        self::assertStringContainsString('# A comment', $firstChunk);
        self::assertStringContainsString('# Another comment', implode('', $result->chunks[1]->lines));
    }

    public function testCommentWithBlanksBeforeAndAfterIsBoundary(): void
    {
        $yaml = <<<YAML
services:

    # Boundary comment

    App\Foo:
        autowire: true
YAML;

        $result = $this->parser->parse($yaml);

        self::assertCount(1, $result->classifiedComments);
        self::assertSame(CommentType::Boundary, $result->classifiedComments[0]->type);
    }

    public function testCommentWithBlankBeforeOnlyIsImmediatelyBefore(): void
    {
        $yaml = <<<YAML
services:
    App\Bar:
        autowire: true

    # Immediately before comment
    App\Foo:
        autowire: true
YAML;

        $result = $this->parser->parse($yaml);

        self::assertCount(1, $result->classifiedComments);
        self::assertSame(CommentType::ImmediatelyBefore, $result->classifiedComments[0]->type);
    }

    public function testLeadingCommentWithoutBlankAttachesAsImmediatelyAfter(): void
    {
        $yaml = <<<YAML
services:
    # Leading comment
    App\Foo:
        autowire: true
YAML;

        $result = $this->parser->parse($yaml);

        self::assertCount(1, $result->classifiedComments);
        self::assertSame(CommentType::ImmediatelyAfter, $result->classifiedComments[0]->type);
    }

    public function testCommentWithBlankAfterOnlyIsBoundary(): void
    {
        $yaml = <<<YAML
services:
    # Immediately after comment

    App\Foo:
        autowire: true
YAML;

        $result = $this->parser->parse($yaml);

        self::assertCount(1, $result->classifiedComments);
        self::assertSame(CommentType::Boundary, $result->classifiedComments[0]->type);
    }

    public function testMultipleClassifiedComments(): void
    {
        $yaml = <<<YAML
services:
    App\Foo:
        autowire: true
    # After comment

    App\Bar:
        autowire: true

    # Boundary comment

    App\Baz:
        autowire: true
YAML;

        $result = $this->parser->parse($yaml);

        self::assertCount(2, $result->classifiedComments);
        self::assertSame(CommentType::Boundary, $result->classifiedComments[0]->type);
        self::assertSame(CommentType::Boundary, $result->classifiedComments[1]->type);
    }

    public function testCommentImmediatelyAfterServiceWithoutBlankRaisesAmbiguousException(): void
    {
        $yaml = <<<YAML
services:
    App\Bar:
        autowire: true
    # Ambiguous comment
    App\Foo:
        autowire: true
YAML;

        $this->expectException(AmbiguousCommentException::class);
        $this->expectExceptionMessage("Comment between 'App\\Bar' and 'App\\Foo' has no blank lines");

        $this->parser->parse($yaml);
    }

    public function testClassifiedCommentStoresServiceKeys(): void
    {
        $yaml = <<<YAML
services:

    # Boundary comment

    App\Foo:
        autowire: true
YAML;

        $result = $this->parser->parse($yaml);

        self::assertSame(null, $result->classifiedComments[0]->prevServiceKey);
        self::assertSame('App\Foo', $result->classifiedComments[0]->nextServiceKey);
    }

    public function testClassifiedCommentStoresBlankLineCounts(): void
    {
        $yaml = <<<YAML
services:

    # Boundary comment

    App\Foo:
        autowire: true
YAML;

        $result = $this->parser->parse($yaml);

        self::assertSame(1, $result->classifiedComments[0]->blankLinesBefore);
        self::assertSame(1, $result->classifiedComments[0]->blankLinesAfter);
    }

    public function testConsecutiveCommentsWithBlankBeforeAndAfterIsBoundary(): void
    {
        $yaml = <<<YAML
services:
    App\Zebra:
        autowire: true

    # comment one
    # comment two

    App\Alpha:
        autowire: true
YAML;

        $result = $this->parser->parse($yaml);

        self::assertCount(1, $result->classifiedComments);
        self::assertSame(CommentType::Boundary, $result->classifiedComments[0]->type);
    }

}

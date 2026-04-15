<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\CheckCommand;
use App\Command\FixCommand;
use App\IO\FileIO;
use App\Parser\YamlServiceParser;
use App\Sorter\ServiceKeyNormalizer;
use App\Sorter\ServiceOrderChecker;
use App\Sorter\ServicesSorter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CLIIntegrationTest extends TestCase
{
    private YamlServiceParser $parser;
    private ServicesSorter $sorter;
    private ServiceOrderChecker $checker;
    private FileIO&\PHPUnit\Framework\MockObject\MockObject $fileIO;

    protected function setUp(): void
    {
        $this->parser = new YamlServiceParser();
        $normalizer = new ServiceKeyNormalizer();
        $this->sorter = new ServicesSorter($normalizer);
        $this->checker = new ServiceOrderChecker($normalizer);
        $this->fileIO = $this->createMock(FileIO::class);
    }

    private function readFixture(string $path): string
    {
        $content = file_get_contents(__DIR__ . '/../fixtures/' . $path);
        self::assertNotFalse($content, "Could not read fixture: $path");
        return $content;
    }

    private function createCheckCommandTester(): CommandTester
    {
        return new CommandTester(new CheckCommand($this->parser, $this->checker, $this->fileIO));
    }

    private function createFixCommandTester(): CommandTester
    {
        return new CommandTester(new FixCommand($this->parser, $this->sorter, $this->fileIO));
    }

    public function testCheckBasicSortedPasses(): void
    {
        $this->fileIO->method('read')->willReturn($this->readFixture('basic/expected.yaml'));

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => 'basic/expected.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('All services are in order', $tester->getDisplay());
    }

    public function testCheckUnderscorePinnedSortedPasses(): void
    {
        $this->fileIO->method('read')->willReturn($this->readFixture('underscore-pinned/expected.yaml'));

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => 'underscore-pinned/expected.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('All services are in order', $tester->getDisplay());
    }

    public function testCheckCaseInsensitiveSortedPasses(): void
    {
        $this->fileIO->method('read')->willReturn($this->readFixture('case-insensitive/expected.yaml'));

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => 'case-insensitive/expected.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('All services are in order', $tester->getDisplay());
    }

    public function testCheckBasicUnsortedReportsOutOfOrder(): void
    {
        $this->fileIO->method('read')->willReturn($this->readFixture('basic/input.yaml'));

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => 'basic/input.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('should come after', $tester->getErrorOutput());
    }

    public function testCheckUnsortedFixtureReportsOutOfOrder(): void
    {
        $this->fileIO->method('read')->willReturn($this->readFixture('unsorted/input.yaml'));

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => 'unsorted/input.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('App\\Zebra', $tester->getErrorOutput());
        self::assertStringContainsString('should come after', $tester->getErrorOutput());
    }

    public function testCheckUnderscorePinnedUnsortedReportsOutOfOrder(): void
    {
        $this->fileIO->method('read')->willReturn($this->readFixture('underscore-pinned/input.yaml'));

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => 'underscore-pinned/input.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        $error = $tester->getErrorOutput();
        self::assertStringContainsString('should come after', $error);
    }

    public function testCheckCaseInsensitiveUnsortedReportsOutOfOrder(): void
    {
        $this->fileIO->method('read')->willReturn($this->readFixture('case-insensitive/input.yaml'));

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => 'case-insensitive/input.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('should come after', $tester->getErrorOutput());
    }

    public function testCheckNoServicesKeyPassesWithWarning(): void
    {
        $this->fileIO->method('read')->willReturn($this->readFixture('no-services-key/input.yaml'));

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => 'no-services-key/input.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('no services: key found', $tester->getErrorOutput());
    }

    public function testCheckEmptyServicesPasses(): void
    {
        $this->fileIO->method('read')->willReturn($this->readFixture('empty-services/input.yaml'));

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => 'empty-services/input.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('All services are in order', $tester->getDisplay());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fixtureProvider')]
    public function testFixStdoutMatchesExpected(string $fixtureName): void
    {
        $input = $this->readFixture($fixtureName . '/input.yaml');
        $expected = $this->readFixture($fixtureName . '/expected.yaml');
        $this->fileIO->method('read')->willReturn($input);

        $tester = $this->createFixCommandTester();
        $tester->execute(['file' => $fixtureName . '/input.yaml', '--stdout' => true], ['capture_stderr_separately' => true]);

        self::assertSame($expected, $tester->getDisplay());
    }

    public function testFixInPlaceWritesSortedContent(): void
    {
        $input = $this->readFixture('basic/input.yaml');
        $expected = $this->readFixture('basic/expected.yaml');
        $this->fileIO->method('read')->willReturn($input);
        $this->fileIO->expects(self::once())->method('write')->with('/path/to/services.yaml', $expected);

        $tester = $this->createFixCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml']);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testFixNoServicesKeyWithStdout(): void
    {
        $input = $this->readFixture('no-services-key/input.yaml');
        $this->fileIO->method('read')->willReturn($input);

        $tester = $this->createFixCommandTester();
        $tester->execute(['file' => 'no-services-key/input.yaml', '--stdout' => true], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('no services: key found', $tester->getErrorOutput());
        self::assertStringContainsString('parameters:', $tester->getDisplay());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fixtureProvider(): array
    {
        return [
            'basic' => ['basic'],
            'comments' => ['comments'],
            'underscore-pinned' => ['underscore-pinned'],
            'empty-services' => ['empty-services'],
            'no-services-key' => ['no-services-key'],
            'multiline-values' => ['multiline-values'],
            'case-insensitive' => ['case-insensitive'],
        ];
    }
}

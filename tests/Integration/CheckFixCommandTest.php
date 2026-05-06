<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\CheckCommand;
use App\Command\FixCommand;
use App\IO\NativeFileIO;
use App\Parser\Extraction\ServiceChunkExtractor;
use App\Parser\Extraction\ServicesBlockExtractor;
use App\Parser\Region\ServiceBlockLineClassifier;
use App\Parser\Region\ServiceRegionAnalyzer;
use App\Parser\Region\ServiceRegionDetector;
use App\Parser\YamlServiceParser;
use App\Sorter\ServiceKeyNormalizer;
use App\Sorter\ServiceKeySorter;
use App\Sorter\ServiceOrderChecker;
use App\Sorter\ServicesSorter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckFixCommandTest extends TestCase
{
    private YamlServiceParser $parser;
    private ServicesSorter $sorter;
    private ServiceOrderChecker $checker;
    private NativeFileIO $fileIO;
    /** @var list<string> */
    private array $tempFiles = [];

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
        $keySorter = new ServiceKeySorter(new ServiceKeyNormalizer());
        $normalizer = new ServiceKeyNormalizer();
        $this->sorter = new ServicesSorter($keySorter, $normalizer);
        $this->checker = new ServiceOrderChecker($keySorter);
        $this->fileIO = new NativeFileIO();
    }

    private function readFixture(string $path): string
    {
        return $this->fileIO->read(__DIR__ . '/../fixtures/' . $path);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    private function createTempFixtureFile(string $fixturePath): string
    {
        return $this->createTempFileWithContent($this->readFixture($fixturePath));
    }

    private function createTempFileWithContent(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'svc-sorter-');
        self::assertNotFalse($path, 'Could not create temp file');
        $this->tempFiles[] = $path;
        $this->fileIO->write($path, $content);

        return $path;
    }

    private function createCheckCommandTester(): CommandTester
    {
        return new CommandTester(new CheckCommand($this->parser, $this->checker, $this->fileIO));
    }

    private function createFixCommandTester(): CommandTester
    {
        return new CommandTester(new FixCommand($this->parser, $this->sorter, $this->fileIO));
    }

    #[DataProvider('sortedFixtureProvider')]
    public function testCheckSortedFixturesPass(string $fixtureName): void
    {
        $path = $this->createTempFixtureFile($fixtureName . '/expected.yaml');

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => $path], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('All services are in order', $tester->getDisplay());
    }

    #[DataProvider('unsortedFixtureProvider')]
    public function testCheckUnsortedFixturesReportOutOfOrder(string $fixtureName): void
    {
        $path = $this->createTempFixtureFile($fixtureName . '/input.yaml');

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => $path], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('should come after', $tester->getErrorOutput());
        if ($fixtureName === 'unsorted') {
            self::assertStringContainsString('App\\Zebra', $tester->getErrorOutput());
        }
    }

    public function testCheckNoServicesKeyPassesWithWarning(): void
    {
        $path = $this->createTempFixtureFile('no-services-key/input.yaml');

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => $path], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('no services: key found', $tester->getErrorOutput());
    }

    public function testCheckEmptyServicesPasses(): void
    {
        $path = $this->createTempFixtureFile('empty-services/input.yaml');

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => $path], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('All services are in order', $tester->getDisplay());
    }

    public function testCheckMultipleFilesReportsPerFileResults(): void
    {
        $sortedPath = $this->createTempFixtureFile('basic/expected.yaml');
        $noServicesPath = $this->createTempFixtureFile('no-services-key/input.yaml');
        $unsortedPath = $this->createTempFixtureFile('basic/input.yaml');

        $tester = $this->createCheckCommandTester();
        $tester->execute(['file' => [$sortedPath, $noServicesPath, $unsortedPath]], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString($sortedPath, $tester->getDisplay());
        self::assertStringContainsString($noServicesPath, $tester->getErrorOutput());
        self::assertStringContainsString($unsortedPath, $tester->getErrorOutput());
    }

    #[DataProvider('fixtureProvider')]
    public function testFixStdoutMatchesExpected(string $fixtureName): void
    {
        $path = $this->createTempFixtureFile($fixtureName . '/input.yaml');
        $expected = $this->readFixture($fixtureName . '/expected.yaml');

        $tester = $this->createFixCommandTester();
        $tester->execute(['file' => $path, '--stdout' => true], ['capture_stderr_separately' => true]);

        self::assertSame($expected, $tester->getDisplay());
    }

    public function testFixInPlaceWritesSortedContent(): void
    {
        $path = $this->createTempFixtureFile('basic/input.yaml');
        $expected = $this->readFixture('basic/expected.yaml');

        $tester = $this->createFixCommandTester();
        $tester->execute(['file' => $path]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame($expected, $this->fileIO->read($path));
    }

    public function testFixMultipleFilesWritesAllFiles(): void
    {
        $firstPath = $this->createTempFixtureFile('basic/input.yaml');
        $secondPath = $this->createTempFixtureFile('underscore-pinned/input.yaml');
        $firstExpected = $this->readFixture('basic/expected.yaml');
        $secondExpected = $this->readFixture('underscore-pinned/expected.yaml');

        $tester = $this->createFixCommandTester();
        $tester->execute(['file' => [$firstPath, $secondPath]], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString($firstPath, $tester->getDisplay());
        self::assertStringContainsString($secondPath, $tester->getDisplay());
        self::assertSame($firstExpected, $this->fileIO->read($firstPath));
        self::assertSame($secondExpected, $this->fileIO->read($secondPath));
    }

    public function testFixNoServicesKeyWithStdout(): void
    {
        $path = $this->createTempFixtureFile('no-services-key/input.yaml');

        $tester = $this->createFixCommandTester();
        $tester->execute(['file' => $path, '--stdout' => true], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('no services: key found', $tester->getErrorOutput());
        self::assertStringContainsString('parameters:', $tester->getDisplay());
    }

    public function testFixStdoutWithMultipleFilesFailsImmediately(): void
    {
        $firstPath = $this->createTempFixtureFile('basic/input.yaml');
        $secondPath = $this->createTempFixtureFile('comments/input.yaml');

        $tester = $this->createFixCommandTester();
        $tester->execute(['file' => [$firstPath, $secondPath], '--stdout' => true], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('single file', $tester->getErrorOutput());
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

    /**
     * @return array<string, array{string}>
     */
    public static function sortedFixtureProvider(): array
    {
        return [
            'basic' => ['basic'],
            'underscore-pinned' => ['underscore-pinned'],
            'case-insensitive' => ['case-insensitive'],
            'empty-services' => ['empty-services'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsortedFixtureProvider(): array
    {
        return [
            'basic' => ['basic'],
            'unsorted' => ['unsorted'],
            'underscore-pinned' => ['underscore-pinned'],
            'case-insensitive' => ['case-insensitive'],
        ];
    }
}

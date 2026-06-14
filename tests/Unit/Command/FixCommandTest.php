<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\FixCommand;
use App\IO\FileIO;
use App\IO\FileIOException;
use App\Parser\Extraction\ServiceChunkExtractor;
use App\Parser\Extraction\ServicesBlockExtractor;
use App\Parser\Region\ServiceBlockLineClassifier;
use App\Parser\Region\ServiceRegionAnalyzer;
use App\Parser\Region\ServiceRegionDetector;
use App\Parser\YamlServiceParser;
use App\Sorter\ServiceKeyNormalizer;
use App\Sorter\ServiceKeySorter;
use App\Sorter\ServicesSorter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class FixCommandTest extends TestCase
{
    private YamlServiceParser $parser;
    private ServicesSorter $sorter;
    private FileIO $fileIO;

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
        $this->sorter = new ServicesSorter(new ServiceKeySorter(new ServiceKeyNormalizer()), new ServiceKeyNormalizer());
        $this->fileIO = TestFileIO::reads('');
    }

    private function createCommandTester(): CommandTester
    {
        $command = new FixCommand($this->parser, $this->sorter, $this->fileIO);
        return new CommandTester($command);
    }

    public function testFixWritesInPlaceByDefault(): void
    {
        $input = "services:\n    App\\ZuluService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO = TestFileIO::reads($input);

        $expectedSorted = "services:\n    App\\AlphaService:\n        autowire: true\n\n    App\\ZuluService:\n        autowire: true\n";

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame([['path' => '/path/to/services.yaml', 'content' => $expectedSorted]], $this->fileIO->writes);
    }

    public function testFixWithStdoutOutputsToStdout(): void
    {
        $input = "services:\n    App\\ZuluService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO = TestFileIO::reads($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml', '--stdout' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('App\\AlphaService', $output);
        self::assertStringContainsString('App\\ZuluService', $output);
        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(1, preg_match('/App\\\\AlphaService.*App\\\\ZuluService/s', $output));
    }

    public function testFixWithStdoutDoesNotWriteFile(): void
    {
        $input = "services:\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO = TestFileIO::reads($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml', '--stdout' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame([], $this->fileIO->writes);
    }

    public function testReadFailureReturnsFailure(): void
    {
        $this->fileIO = TestFileIO::readsWith(static function (): string {
            throw new FileIOException('File not found: /missing.yaml');
        });

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/missing.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('File not found', $tester->getErrorOutput());
    }

    public function testWriteFailureReturnsFailure(): void
    {
        $input = "services:\n    App\\ZuluService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO = TestFileIO::reads($input)->withWriter(static function (): void {
            throw new FileIOException('Could not write to file: /readonly.yaml');
        });

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/readonly.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Could not write to file', $tester->getErrorOutput());
    }

    public function testNoServicesKeyOutputsWarningAndDoesNotWriteUnchangedFile(): void
    {
        $input = "parameters:\n    locale: en\n";
        $this->fileIO = TestFileIO::reads($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/file.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame([], $this->fileIO->writes);
        self::assertStringContainsString('no services: key found', $tester->getErrorOutput());
        self::assertStringContainsString('Unchanged: /path/to/file.yaml', $tester->getDisplay());
    }

    public function testNoServicesKeyWithStdoutOutputsUnchangedToStdout(): void
    {
        $input = "parameters:\n    locale: en\n";
        $this->fileIO = TestFileIO::reads($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/file.yaml', '--stdout' => true], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('parameters:', $tester->getDisplay());
        self::assertStringContainsString('no services: key found', $tester->getErrorOutput());
    }

    public function testFixWritesMultipleFilesInOrder(): void
    {
        $first = "services:\n    App\\ZuluService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $second = "services:\n    App\\BravoService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";

        $this->fileIO = TestFileIO::readsMap([
            '/path/one.yaml' => $first,
            '/path/two.yaml' => $second,
        ]);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => ['/path/one.yaml', '/path/two.yaml']], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertCount(2, $this->fileIO->writes);
        self::assertStringContainsString('/path/one.yaml', $tester->getDisplay());
        self::assertStringContainsString('/path/two.yaml', $tester->getDisplay());
    }

    public function testFixReportsUnchangedFileWithoutWriting(): void
    {
        $input = "services:\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO = TestFileIO::reads($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/sorted.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame([], $this->fileIO->writes);
        self::assertStringContainsString('Unchanged: /path/sorted.yaml', $tester->getDisplay());
        self::assertStringNotContainsString('Fixed: /path/sorted.yaml', $tester->getDisplay());
    }

    public function testWriteFailureDoesNotStopLaterFiles(): void
    {
        $input = "services:\n    App\\ZuluService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";

        $this->fileIO = TestFileIO::reads($input)->withWriter(
            static function (string $path, string $content): void {
                if ($path === '/readonly.yaml') {
                    throw new FileIOException('Could not write to file: /readonly.yaml');
                }
            },
        );

        $tester = $this->createCommandTester();
        $tester->execute(['file' => ['/readonly.yaml', '/ok.yaml']], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('/readonly.yaml', $tester->getErrorOutput());
        self::assertStringContainsString('/ok.yaml', $tester->getDisplay());
    }

    public function testReadFailureDoesNotStopLaterFiles(): void
    {
        $sorted = "services:\n    App\\AlphaService:\n        autowire: true\n";

        $this->fileIO = TestFileIO::readsWith(
            static function (string $path) use ($sorted): string {
                if ($path === '/missing.yaml') {
                    throw new FileIOException('File not found: /missing.yaml');
                }

                return $sorted;
            },
        );

        $tester = $this->createCommandTester();
        $tester->execute(['file' => ['/missing.yaml', '/ok.yaml']], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame([], $this->fileIO->writes);
        self::assertStringContainsString('/missing.yaml', $tester->getErrorOutput());
        self::assertStringContainsString('Unchanged: /ok.yaml', $tester->getDisplay());
    }

    public function testDuplicateServiceKeyDoesNotStopLaterFiles(): void
    {
        $duplicate = "services:\n    App\\AlphaService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $sorted = "services:\n    App\\BravoService:\n        autowire: true\n";

        $this->fileIO = TestFileIO::readsMap([
            '/path/duplicate.yaml' => $duplicate,
            '/path/sorted.yaml' => $sorted,
        ]);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => ['/path/duplicate.yaml', '/path/sorted.yaml']], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame([], $this->fileIO->writes);
        self::assertStringContainsString('/path/duplicate.yaml', $tester->getErrorOutput());
        self::assertStringContainsString('Duplicate service key', $tester->getErrorOutput());
        self::assertStringContainsString('Unchanged: /path/sorted.yaml', $tester->getDisplay());
    }

    public function testFixStdoutRejectsMultipleFiles(): void
    {
        $this->fileIO = TestFileIO::readsWith(static function (): string {
            self::fail('File should not be read when --stdout is used with multiple files.');
        });

        $tester = $this->createCommandTester();
        $tester->execute(
            ['file' => ['/path/one.yaml', '/path/two.yaml'], '--stdout' => true],
            ['capture_stderr_separately' => true],
        );

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('single file', $tester->getErrorOutput());
    }

    public function testDuplicateServiceKeyExitsOne(): void
    {
        $input = "services:\n    App\\AlphaService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO = TestFileIO::reads($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Duplicate service key', $tester->getErrorOutput());
    }
}

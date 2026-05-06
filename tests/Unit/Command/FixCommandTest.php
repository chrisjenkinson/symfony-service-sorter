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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class FixCommandTest extends TestCase
{
    private YamlServiceParser $parser;
    private ServicesSorter $sorter;
    private FileIO&MockObject $fileIO;

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
        $this->fileIO = $this->createMock(FileIO::class);
    }

    private function createCommandTester(): CommandTester
    {
        $command = new FixCommand($this->parser, $this->sorter, $this->fileIO);
        return new CommandTester($command);
    }

    public function testFixWritesInPlaceByDefault(): void
    {
        $input = "services:\n    App\\ZuluService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO->method('read')->willReturn($input);

        $expectedSorted = "services:\n    App\\AlphaService:\n        autowire: true\n\n    App\\ZuluService:\n        autowire: true\n";
        $this->fileIO->expects(self::once())->method('write')->with('/path/to/services.yaml', $expectedSorted);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml']);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testFixWithStdoutOutputsToStdout(): void
    {
        $input = "services:\n    App\\ZuluService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO->method('read')->willReturn($input);

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
        $this->fileIO->method('read')->willReturn($input);
        $this->fileIO->expects(self::never())->method('write');

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml', '--stdout' => true]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testReadFailureReturnsFailure(): void
    {
        $this->fileIO->method('read')->willThrowException(new FileIOException('File not found: /missing.yaml'));

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/missing.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('File not found', $tester->getErrorOutput());
    }

    public function testWriteFailureReturnsFailure(): void
    {
        $input = "services:\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO->method('read')->willReturn($input);
        $this->fileIO->method('write')->willThrowException(new FileIOException('Could not write to file: /readonly.yaml'));

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/readonly.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Could not write to file', $tester->getErrorOutput());
    }

    public function testNoServicesKeyOutputsWarningAndWritesInPlace(): void
    {
        $input = "parameters:\n    locale: en\n";
        $this->fileIO->method('read')->willReturn($input);
        $this->fileIO->expects(self::once())->method('write')->with('/path/to/file.yaml', $input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/file.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('no services: key found', $tester->getErrorOutput());
    }

    public function testNoServicesKeyWithStdoutOutputsUnchangedToStdout(): void
    {
        $input = "parameters:\n    locale: en\n";
        $this->fileIO->method('read')->willReturn($input);

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

        $this->fileIO->method('read')->willReturnMap([
            ['/path/one.yaml', $first],
            ['/path/two.yaml', $second],
        ]);

        $this->fileIO->expects(self::exactly(2))->method('write');

        $tester = $this->createCommandTester();
        $tester->execute(['file' => ['/path/one.yaml', '/path/two.yaml']], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('/path/one.yaml', $tester->getDisplay());
        self::assertStringContainsString('/path/two.yaml', $tester->getDisplay());
    }

    public function testWriteFailureDoesNotStopLaterFiles(): void
    {
        $input = "services:\n    App\\AlphaService:\n        autowire: true\n";

        $this->fileIO->method('read')->willReturn($input);
        $this->fileIO
            ->method('write')
            ->willReturnCallback(static function (string $path, string $content): void {
                if ($path === '/readonly.yaml') {
                    throw new FileIOException('Could not write to file: /readonly.yaml');
                }
            });

        $tester = $this->createCommandTester();
        $tester->execute(['file' => ['/readonly.yaml', '/ok.yaml']], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('/readonly.yaml', $tester->getErrorOutput());
        self::assertStringContainsString('/ok.yaml', $tester->getDisplay());
    }

    public function testReadFailureDoesNotStopLaterFiles(): void
    {
        $sorted = "services:\n    App\\AlphaService:\n        autowire: true\n";

        $this->fileIO
            ->method('read')
            ->willReturnCallback(static function (string $path) use ($sorted): string {
                if ($path === '/missing.yaml') {
                    throw new FileIOException('File not found: /missing.yaml');
                }

                return $sorted;
            });

        $this->fileIO->expects(self::once())->method('write')->with('/ok.yaml', $sorted);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => ['/missing.yaml', '/ok.yaml']], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('/missing.yaml', $tester->getErrorOutput());
        self::assertStringContainsString('/ok.yaml', $tester->getDisplay());
    }

    public function testDuplicateServiceKeyDoesNotStopLaterFiles(): void
    {
        $duplicate = "services:\n    App\\AlphaService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $sorted = "services:\n    App\\BravoService:\n        autowire: true\n";

        $this->fileIO->method('read')->willReturnMap([
            ['/path/duplicate.yaml', $duplicate],
            ['/path/sorted.yaml', $sorted],
        ]);

        $this->fileIO->expects(self::once())->method('write')->with('/path/sorted.yaml', $sorted);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => ['/path/duplicate.yaml', '/path/sorted.yaml']], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('/path/duplicate.yaml', $tester->getErrorOutput());
        self::assertStringContainsString('Duplicate service key', $tester->getErrorOutput());
        self::assertStringContainsString('/path/sorted.yaml', $tester->getDisplay());
    }

    public function testFixStdoutRejectsMultipleFiles(): void
    {
        $this->fileIO->expects(self::never())->method('read');
        $this->fileIO->expects(self::never())->method('write');

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
        $this->fileIO->method('read')->willReturn($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Duplicate service key', $tester->getErrorOutput());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\FixCommand;
use App\IO\FileIO;
use App\IO\FileIOException;
use App\Parser\YamlServiceParser;
use App\Sorter\ServiceKeyNormalizer;
use App\Sorter\ServicesSorter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class FixCommandTest extends TestCase
{
    private YamlServiceParser $parser;
    private ServicesSorter $sorter;
    private FileIO&\PHPUnit\Framework\MockObject\MockObject $fileIO;

    protected function setUp(): void
    {
        $this->parser = new YamlServiceParser();
        $this->sorter = new ServicesSorter(new ServiceKeyNormalizer());
        $this->fileIO = $this->createMock(FileIO::class);
    }

    private function createCommandTester(): CommandTester
    {
        $command = new FixCommand($this->parser, $this->sorter, $this->fileIO);
        return new CommandTester($command);
    }

    public function testFixWritesInPlaceByDefault(): void
    {
        $input = "services:\n    App\\ZebraService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO->method('read')->willReturn($input);

        $expectedSorted = "services:\n    App\\AlphaService:\n        autowire: true\n\n    App\\ZebraService:\n        autowire: true\n";
        $this->fileIO->expects(self::once())->method('write')->with('/path/to/services.yaml', $expectedSorted);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml']);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testFixWithStdoutOutputsToStdout(): void
    {
        $input = "services:\n    App\\ZebraService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO->method('read')->willReturn($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml', '--stdout' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('App\\AlphaService', $output);
        self::assertStringContainsString('App\\ZebraService', $output);
        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(1, preg_match('/App\\\\AlphaService.*App\\\\ZebraService/s', $output));
    }

    public function testFixWithStdoutDoesNotWriteFile(): void
    {
        $input = "services:\n    App\\Foo:\n        autowire: true\n";
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
        $input = "services:\n    App\\Foo:\n        autowire: true\n";
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
}

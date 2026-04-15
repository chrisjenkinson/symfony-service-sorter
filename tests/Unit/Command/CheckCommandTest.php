<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CheckCommand;
use App\IO\FileIO;
use App\IO\FileIOException;
use App\Parser\YamlServiceParser;
use App\Sorter\DuplicateServiceKeyException;
use App\Sorter\ServiceKeyNormalizer;
use App\Sorter\ServiceKeySorter;
use App\Sorter\ServiceOrderChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckCommandTest extends TestCase
{
    private YamlServiceParser $parser;
    private ServiceOrderChecker $checker;
    private FileIO&MockObject $fileIO;

    protected function setUp(): void
    {
        $this->parser = new YamlServiceParser();
        $this->checker = new ServiceOrderChecker(new ServiceKeySorter(new ServiceKeyNormalizer()));
        $this->fileIO = $this->createMock(FileIO::class);
    }

    private function createCommandTester(): CommandTester
    {
        $command = new CheckCommand($this->parser, $this->checker, $this->fileIO);
        return new CommandTester($command);
    }

    public function testSortedFileExitsZero(): void
    {
        $input = "services:\n    App\\AlphaService:\n        autowire: true\n    App\\ZebraService:\n        autowire: true\n";
        $this->fileIO->method('read')->willReturn($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('All services are in order', $tester->getDisplay());
    }

    public function testUnsortedFileReportsOutOfOrder(): void
    {
        $input = "services:\n    App\\ZebraService:\n        autowire: true\n    App\\AlphaService:\n        autowire: true\n";
        $this->fileIO->method('read')->willReturn($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('App\\ZebraService', $tester->getErrorOutput());
        self::assertStringContainsString('App\\AlphaService', $tester->getErrorOutput());
        self::assertStringContainsString('should come after', $tester->getErrorOutput());
    }

    public function testFileNotFoundExitsOne(): void
    {
        $this->fileIO->method('read')->willThrowException(new FileIOException('File not found: /missing.yaml'));

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/missing.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('File not found', $tester->getErrorOutput());
    }

    public function testNoServicesKeyExitsZeroWithWarning(): void
    {
        $input = "parameters:\n    locale: en\n";
        $this->fileIO->method('read')->willReturn($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/file.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('no services: key found', $tester->getErrorOutput());
    }

    public function testEmptyServicesBlockExitsZero(): void
    {
        $input = "services:\n\nparameters:\n    locale: en\n";
        $this->fileIO->method('read')->willReturn($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/file.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('All services are in order', $tester->getDisplay());
    }

    public function testDuplicateServiceKeyExitsOne(): void
    {
        $input = "services:\n    App\\Foo:\n        autowire: true\n    App\\Foo:\n        autowire: true\n";
        $this->fileIO->method('read')->willReturn($input);

        $tester = $this->createCommandTester();
        $tester->execute(['file' => '/path/to/services.yaml'], ['capture_stderr_separately' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Duplicate service key', $tester->getErrorOutput());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\IO;

use App\IO\FileIOException;
use App\IO\NativeFileIO;
use PHPUnit\Framework\TestCase;

final class NativeFileIOTest extends TestCase
{
    private NativeFileIO $fileIO;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->fileIO = new NativeFileIO();
        $this->tmpDir = sys_get_temp_dir() . '/native_file_io_test_' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testReadReturnsFileContents(): void
    {
        $path = $this->tmpDir . '/test.yaml';
        file_put_contents($path, "services:\n    App\\Foo:\n        autowire: true\n");

        $content = $this->fileIO->read($path);

        self::assertSame("services:\n    App\\Foo:\n        autowire: true\n", $content);
    }

    public function testReadThrowsForMissingFile(): void
    {
        $this->expectException(FileIOException::class);
        $this->expectExceptionMessage('File not found:');

        $this->fileIO->read($this->tmpDir . '/nonexistent.yaml');
    }

    public function testWriteSucceeds(): void
    {
        $path = $this->tmpDir . '/output.yaml';
        $content = "services:\n    App\\Foo:\n        autowire: true\n";

        $this->fileIO->write($path, $content);

        self::assertSame($content, file_get_contents($path));
    }

    public function testWriteThrowsForUnwritablePath(): void
    {
        $this->expectException(FileIOException::class);
        $this->expectExceptionMessage('Could not write to file:');

        $this->fileIO->write('/nonexistent/directory/output.yaml', 'content');
    }
}
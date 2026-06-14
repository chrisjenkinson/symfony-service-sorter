<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\IO\FileIO;

final class TestFileIO implements FileIO
{
    /**
     * @var list<array{path: string, content: string}>
     */
    public array $writes = [];

    /**
     * @param callable(string): string $reader
     * @param null|callable(string, string): void $writer
     */
    public function __construct(
        private readonly mixed $reader,
        private readonly mixed $writer = null,
    ) {
    }

    public static function reads(string $content): self
    {
        return new self(static fn (): string => $content);
    }

    /**
     * @param array<string, string> $contentByPath
     */
    public static function readsMap(array $contentByPath): self
    {
        return new self(static fn (string $path): string => $contentByPath[$path]);
    }

    /**
     * @param callable(string): string $reader
     */
    public static function readsWith(callable $reader): self
    {
        return new self($reader);
    }

    public function withWriter(callable $writer): self
    {
        return new self($this->reader, $writer);
    }

    public function read(string $path): string
    {
        return ($this->reader)($path);
    }

    public function write(string $path, string $content): void
    {
        $this->writes[] = ['path' => $path, 'content' => $content];

        if ($this->writer !== null) {
            ($this->writer)($path, $content);
        }
    }
}

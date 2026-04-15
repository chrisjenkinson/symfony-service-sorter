<?php

declare(strict_types=1);

namespace App\IO;

final class NativeFileIO implements FileIO
{
    public function read(string $path): string
    {
        if (!file_exists($path)) {
            throw new FileIOException(sprintf('File not found: %s', $path));
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            throw new FileIOException(sprintf('Could not read file: %s', $path));
        }

        return $content;
    }

    public function write(string $path, string $content): void
    {
        $result = @file_put_contents($path, $content);

        if ($result === false) {
            throw new FileIOException(sprintf('Could not write to file: %s', $path));
        }
    }
}

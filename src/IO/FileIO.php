<?php

declare(strict_types=1);

namespace ChrisJenkinson\SymfonyServiceSorter\IO;

interface FileIO
{
    /**
     * @throws FileIOException
     */
    public function read(string $path): string;

    /**
     * @throws FileIOException
     */
    public function write(string $path, string $content): void;
}

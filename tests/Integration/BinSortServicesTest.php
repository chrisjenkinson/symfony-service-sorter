<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class BinSortServicesTest extends TestCase
{
    public function testExecutableBootsWithoutFatalError(): void
    {
        $command = [PHP_BINARY, __DIR__ . '/../../bin/sort-services'];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);

        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, $stdout . $stderr);
        self::assertStringNotContainsString('Fatal error', $stdout . $stderr);
        self::assertStringContainsString('sort-services', $stdout . $stderr);
    }

    public function testExecutableUsesComposerProxyAutoloader(): void
    {
        $packageDirectory = sys_get_temp_dir() . '/symfony-service-sorter-' . bin2hex(random_bytes(8));
        $binDirectory = $packageDirectory . '/bin';
        $binary = $binDirectory . '/sort-services';

        try {
            self::assertTrue(mkdir($binDirectory, 0777, true));
            self::assertTrue(copy(__DIR__ . '/../../bin/sort-services', $binary));

            $bootstrap = sprintf(
                '$_composer_autoload_path = %s; require %s;',
                var_export(__DIR__ . '/../../vendor/autoload.php', true),
                var_export($binary, true),
            );
            $command = [PHP_BINARY, '-r', $bootstrap, '--', '--version'];
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptorSpec, $pipes);

            self::assertIsResource($process);

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            self::assertSame(0, $exitCode, $stdout . $stderr);
            self::assertStringContainsString('sort-services', $stdout . $stderr);
        } finally {
            if (is_file($binary)) {
                unlink($binary);
            }
            if (is_dir($binDirectory)) {
                rmdir($binDirectory);
            }
            if (is_dir($packageDirectory)) {
                rmdir($packageDirectory);
            }
        }
    }
}

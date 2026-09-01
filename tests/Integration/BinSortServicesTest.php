<?php

declare(strict_types=1);

namespace ChrisJenkinson\SymfonyServiceSorter\Tests\Integration;

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

    public function testExecutableCannotBeShadowedByHostAppClass(): void
    {
        $hostDirectory = sys_get_temp_dir() . '/symfony-service-sorter-host-' . bin2hex(random_bytes(8));
        $commandDirectory = $hostDirectory . '/src/Command';
        $hostClass = $commandDirectory . '/CheckCommand.php';

        try {
            self::assertTrue(mkdir($commandDirectory, 0777, true));
            self::assertNotFalse(file_put_contents(
                $hostClass,
                <<<'PHP'
                    <?php

                    declare(strict_types=1);

                    namespace App\Command;

                    final class CheckCommand
                    {
                    }
                    PHP,
            ));

            $bootstrap = sprintf(
                'require %s;
                spl_autoload_register(static function (string $class): void {
                    if ($class === %s) {
                        require %s;
                    }
                }, true, true);
                $_composer_autoload_path = %s;
                require %s;',
                var_export(__DIR__ . '/../../vendor/autoload.php', true),
                var_export('App\Command\CheckCommand', true),
                var_export($hostClass, true),
                var_export(__DIR__ . '/../../vendor/autoload.php', true),
                var_export(__DIR__ . '/../../bin/sort-services', true),
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
            if (is_file($hostClass)) {
                unlink($hostClass);
            }
            if (is_dir($commandDirectory)) {
                rmdir($commandDirectory);
            }
            if (is_dir($hostDirectory . '/src')) {
                rmdir($hostDirectory . '/src');
            }
            if (is_dir($hostDirectory)) {
                rmdir($hostDirectory);
            }
        }
    }
}

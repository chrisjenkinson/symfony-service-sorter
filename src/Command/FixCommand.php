<?php

declare(strict_types=1);

namespace App\Command;

use App\IO\FileIO;
use App\IO\FileIOException;
use App\Parser\AmbiguousCommentException;
use App\Parser\YamlServiceParser;
use App\Sorter\DuplicateServiceKeyException;
use App\Sorter\ServicesSorter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fix',
    description: 'Sorts services in a YAML file alphabetically and writes the result in-place',
)]
final class FixCommand extends Command
{
    public function __construct(
        private readonly YamlServiceParser $parser,
        private readonly ServicesSorter $sorter,
        private readonly FileIO $fileIO,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Path to one or more YAML files')
            ->addOption('stdout', null, InputOption::VALUE_NONE, 'Write sorted YAML to stdout instead of modifying the file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;

        $fileArgument = $input->getArgument('file');
        /** @var list<string> $filePaths */
        $filePaths = is_array($fileArgument) ? $fileArgument : [$fileArgument];

        if ($input->getOption('stdout')) {
            if (count($filePaths) !== 1) {
                $errorOutput->writeln('<error>The --stdout option can only be used with a single file.</error>');
                return Command::FAILURE;
            }

            return $this->writeSortedOutputToStdout($filePaths[0], $output, $errorOutput);
        }

        $hadFailure = false;

        foreach ($filePaths as $filePath) {
            try {
                $content = $this->fileIO->read($filePath);
            } catch (FileIOException $e) {
                $errorOutput->writeln(sprintf('<error>%s (%s)</error>', $e->getMessage(), $filePath));
                $hadFailure = true;
                continue;
            }

            try {
                $parsedFile = $this->parser->parse($content);
            } catch (AmbiguousCommentException $e) {
                $errorOutput->writeln(sprintf('<error>%s (%s)</error>', $e->getMessage(), $filePath));
                $hadFailure = true;
                continue;
            }

            if ($parsedFile->servicesHeader === '') {
                $errorOutput->writeln(sprintf(
                    '<comment>Warning: no services: key found in %s</comment>',
                    $filePath,
                ));
            }

            try {
                $sorted = $this->sorter->sort($parsedFile);
            } catch (DuplicateServiceKeyException $e) {
                $errorOutput->writeln(sprintf('<error>%s (%s)</error>', $e->getMessage(), $filePath));
                $hadFailure = true;
                continue;
            }

            try {
                $this->fileIO->write($filePath, $sorted);
            } catch (FileIOException $e) {
                $errorOutput->writeln(sprintf('<error>%s (%s)</error>', $e->getMessage(), $filePath));
                $hadFailure = true;
                continue;
            }

            $output->writeln(sprintf('Fixed: %s', $filePath));
        }

        return $hadFailure ? Command::FAILURE : Command::SUCCESS;
    }

    private function writeSortedOutputToStdout(
        string $filePath,
        OutputInterface $output,
        OutputInterface $errorOutput,
    ): int {
        try {
            $content = $this->fileIO->read($filePath);
        } catch (FileIOException $e) {
            $errorOutput->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        try {
            $parsedFile = $this->parser->parse($content);
        } catch (AmbiguousCommentException $e) {
            $errorOutput->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        if ($parsedFile->servicesHeader === '') {
            $errorOutput->writeln(sprintf(
                '<comment>Warning: no services: key found in %s</comment>',
                $filePath,
            ));
        }

        try {
            $sorted = $this->sorter->sort($parsedFile);
        } catch (DuplicateServiceKeyException $e) {
            $errorOutput->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        $output->write($sorted, false, OutputInterface::OUTPUT_RAW);
        return Command::SUCCESS;
    }
}

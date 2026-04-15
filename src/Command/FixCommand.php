<?php

declare(strict_types=1);

namespace App\Command;

use App\IO\FileIO;
use App\IO\FileIOException;
use App\Parser\YamlServiceParser;
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
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the YAML file')
            ->addOption('stdout', null, InputOption::VALUE_NONE, 'Write sorted YAML to stdout instead of modifying the file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;

        /** @var string $filePath */
        $filePath = $input->getArgument('file');

        try {
            $content = $this->fileIO->read($filePath);
        } catch (FileIOException $e) {
            $errorOutput->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        $parsedFile = $this->parser->parse($content);

        if ($parsedFile->servicesHeader === '') {
            $errorOutput->writeln(sprintf(
                '<comment>Warning: no services: key found in %s</comment>',
                $filePath,
            ));
        }

        $sorted = $this->sorter->sort($parsedFile);

        if ($input->getOption('stdout')) {
            $output->write($sorted, false, OutputInterface::OUTPUT_RAW);
            return Command::SUCCESS;
        }

        try {
            $this->fileIO->write($filePath, $sorted);
        } catch (FileIOException $e) {
            $errorOutput->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
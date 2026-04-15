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
    name: 'sort-services',
    description: 'Sorts entries in the services: key of a Symfony YAML file alphabetically',
)]
final class SortServicesCommand extends Command
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
            ->addOption('write', 'w', InputOption::VALUE_NONE, 'Write sorted output back to the file instead of stdout');
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

        if ($input->getOption('write')) {
            try {
                $this->fileIO->write($filePath, $sorted);
            } catch (FileIOException $e) {
                $errorOutput->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                return Command::FAILURE;
            }
            return Command::SUCCESS;
        }

        $output->write($sorted, false, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}

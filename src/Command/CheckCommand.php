<?php

declare(strict_types=1);

namespace App\Command;

use App\IO\FileIO;
use App\IO\FileIOException;
use App\Parser\YamlServiceParser;
use App\Sorter\ServiceOrderChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'check',
    description: 'Checks whether services in a YAML file are sorted alphabetically',
)]
final class CheckCommand extends Command
{
    public function __construct(
        private readonly YamlServiceParser $parser,
        private readonly ServiceOrderChecker $checker,
        private readonly FileIO $fileIO,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the YAML file');
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
            return Command::SUCCESS;
        }

        $outOfOrder = $this->checker->check($parsedFile);

        if ($outOfOrder === []) {
            $output->writeln('All services are in order.');
            return Command::SUCCESS;
        }

        $errorOutput->writeln('The following services are not in alphabetical order:');
        foreach ($outOfOrder as $entry) {
            $errorOutput->writeln(sprintf('  - %s should come after %s', $entry->key, $entry->predecessor));
        }

        return Command::FAILURE;
    }
}
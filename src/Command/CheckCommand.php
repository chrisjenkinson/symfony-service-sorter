<?php

declare(strict_types=1);

namespace App\Command;

use App\IO\FileIO;
use App\IO\FileIOException;
use App\Parser\AmbiguousCommentException;
use App\Parser\YamlServiceParser;
use App\Sorter\DuplicateServiceKeyException;
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
        $this->addArgument('file', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Path to one or more YAML files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;

        $fileArgument = $input->getArgument('file');
        /** @var list<string> $filePaths */
        $filePaths = is_array($fileArgument) ? $fileArgument : [$fileArgument];
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
                continue;
            }

            try {
                $outOfOrder = $this->checker->check($parsedFile);
            } catch (DuplicateServiceKeyException $e) {
                $errorOutput->writeln(sprintf('<error>%s (%s)</error>', $e->getMessage(), $filePath));
                $hadFailure = true;
                continue;
            }

            if ($outOfOrder === []) {
                $output->writeln(sprintf('All services are in order: %s', $filePath));
                continue;
            }

            $hadFailure = true;
            $errorOutput->writeln(sprintf('The following services are not in alphabetical order: %s', $filePath));
            foreach ($outOfOrder as $entry) {
                $errorOutput->writeln(sprintf(
                    '  - %s%s should come after %s',
                    $entry->key,
                    $entry->subsequentCount > 0
                        ? sprintf(
                            ' (and %d subsequent service%s)',
                            $entry->subsequentCount,
                            $entry->subsequentCount === 1 ? '' : 's',
                        )
                        : '',
                    $entry->predecessor,
                ));
            }
        }

        return $hadFailure ? Command::FAILURE : Command::SUCCESS;
    }
}

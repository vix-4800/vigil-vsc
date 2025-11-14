<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vix\ExceptionInspector\Analyzer;

/**
 * Command to analyze PHP files for @throws documentation
 */
final class AnalyzeCommand extends Command
{
    /**
     * Default command name
     *
     * @var string
     */
    private static string $defaultName = 'analyze';

    /**
     * Default command description
     *
     * @var string
     */
    private static string $defaultDescription = 'Analyze @throws tags in PHP code';

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('analyze')
            ->setDescription('Analyze @throws tags in PHP code')
            ->setHelp(
                "This command analyzes PHP files for throw statements and checks if they are properly
                documented in @throws tags. \n\n By default, when analyzing a single file, the tool will automatically
                detect the project root (by looking for composer.json) and scan the entire project to build a complete
                context of all methods and their @throws declarations. This enables accurate cross-file analysis."
            )
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'File or directory to analyze'
            )
            ->addOption(
                'no-project-scan',
                null,
                InputOption::VALUE_NONE,
                'Disable automatic project-wide scanning (faster but less accurate for single files)'
            )
            ->addOption(
                'exclude-pattern',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Regex patterns to exclude files/directories (can be specified multiple times)'
            );
    }

    /**
     * Execute the command
     *
     * @param InputInterface  $input  Input interface
     * @param OutputInterface $output Output interface
     *
     * @return int Exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');

        if (!is_string($path)) {
            $error = [
                'error' => [
                    'message' => 'Path argument must be a string',
                ],
            ];
            $output->writeln(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return Command::FAILURE;
        }

        if (!file_exists($path)) {
            $error = [
                'error' => [
                    'message' => "Path does not exist: {$path}",
                ],
            ];
            $output->writeln(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return Command::FAILURE;
        }

        try {
            $analyzer = new Analyzer();
            $useProjectScan = !$input->getOption('no-project-scan');
            $excludePatterns = $input->getOption('exclude-pattern');

            if (!is_array($excludePatterns)) {
                $excludePatterns = [];
            }

            $results = $analyzer->analyze($path, $useProjectScan, $excludePatterns);

            $output->writeln(json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $results['summary']['total_errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (Exception $exception) {
            $error = [
                'error' => [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
            ];
            $output->writeln(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return Command::INVALID;
        }
    }
}

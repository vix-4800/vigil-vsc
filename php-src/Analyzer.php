<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector;

use InvalidArgumentException;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Class Analyzer
 *
 * Analyzes PHP files for throw statements and documents exceptions thrown.
 */
class Analyzer
{
    /**
     * Analysis results
     *
     * @var array<string, mixed>
     */
    private array $results = [];

    /**
     * Global map of all method signatures to their declared @throws
     * Format: ['Namespace\ClassName::methodName' => ['ExceptionType1', 'ExceptionType2']]
     *
     * @var array<string, string[]>
     */
    private array $globalMethodThrows = [];

    /**
     * List of all files to analyze
     *
     * @var string[]
     */
    private array $filesToAnalyze = [];

    /**
     * Project root directory (for auto-detection)
     *
     * @var string|null
     */
    private ?string $projectRoot = null;

    /**
     * Whether to use project-wide analysis
     *
     * @var bool
     */
    private bool $useProjectWideAnalysis = true;

    /**
     * Analyze a file or directory with optional project-wide context
     *
     * @param string $path                   File or directory path
     * @param bool   $useProjectWideAnalysis Whether to scan entire project for context
     *
     * @return array<string, mixed> Analysis results
     */
    public function analyze(string $path, bool $useProjectWideAnalysis = true): array
    {
        $this->results = [
            'files' => [],
            'summary' => [
                'total_files' => 0,
                'files_with_errors' => 0,
                'total_errors' => 0,
            ],
        ];
        $this->globalMethodThrows = [];
        $this->filesToAnalyze = [];
        $this->useProjectWideAnalysis = $useProjectWideAnalysis;

        // Collect all files
        if (is_file($path)) {
            $this->filesToAnalyze[] = $path;

            // Auto-detect project root and scan for context
            if ($useProjectWideAnalysis) {
                $this->projectRoot = $this->findProjectRoot($path);

                if ($this->projectRoot !== null) {
                    $this->collectProjectFiles($this->projectRoot);
                }
            }
        } elseif (is_dir($path)) {
            $this->collectFiles($path);
        } else {
            throw new InvalidArgumentException("Path does not exist: {$path}");
        }

        // Pass 1: Collect all method signatures and their @throws
        foreach ($this->filesToAnalyze as $file) {
            $this->collectMethodThrows($file);
        }

        // Pass 2: Analyze each file with full context
        // Only analyze the originally requested path
        if (is_file($path)) {
            $this->analyzeFile($path);
        } else {
            foreach ($this->filesToAnalyze as $file) {
                $this->analyzeFile($file);
            }
        }

        return $this->results;
    }

    /**
     * Find project root by looking for composer.json
     *
     * @param string $startPath Starting file or directory path
     *
     * @return string|null Project root path or null if not found
     */
    private function findProjectRoot(string $startPath): ?string
    {
        $dir = is_file($startPath) ? dirname($startPath) : $startPath;
        $maxDepth = 10; // Prevent infinite loop
        $depth = 0;

        while ($depth < $maxDepth) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }

            $parentDir = dirname($dir);

            if ($parentDir === $dir) {
                // Reached filesystem root
                break;
            }

            $dir = $parentDir;
            $depth++;
        }

        return null;
    }

    /**
     * Collect PHP files from project for context building
     *
     * @param string $projectRoot Project root directory
     *
     * @return void
     */
    private function collectProjectFiles(string $projectRoot): void
    {
        $dirsToScan = $this->detectSourceDirectories($projectRoot);

        if ($dirsToScan !== []) {
            // Scan detected directories
            foreach ($dirsToScan as $dir) {
                if (is_dir($dir)) {
                    $this->collectFiles($dir);
                }
            }
        } else {
            // Fallback: scan whole project excluding vendor/cache
            $this->collectFilesExcludingVendor($projectRoot);
        }
    }

    /**
     * Detect source directories from composer.json autoload configuration
     *
     * @param string $projectRoot Project root directory
     *
     * @return string[] List of absolute paths to source directories
     */
    private function detectSourceDirectories(string $projectRoot): array
    {
        $composerFile = $projectRoot . '/composer.json';

        if (!file_exists($composerFile)) {
            return [];
        }

        $composerData = json_decode(file_get_contents($composerFile), true);

        if (!is_array($composerData)) {
            return [];
        }

        $directories = [];

        // PSR-4 autoload
        if (isset($composerData['autoload']['psr-4']) && is_array($composerData['autoload']['psr-4'])) {
            foreach ($composerData['autoload']['psr-4'] as $namespace => $paths) {
                $pathList = is_array($paths) ? $paths : [$paths];

                foreach ($pathList as $path) {
                    $fullPath = $projectRoot . '/' . rtrim($path, '/');

                    if (is_dir($fullPath)) {
                        $directories[] = $fullPath;
                    }
                }
            }
        }

        // PSR-0 autoload
        if (isset($composerData['autoload']['psr-0']) && is_array($composerData['autoload']['psr-0'])) {
            foreach ($composerData['autoload']['psr-0'] as $namespace => $paths) {
                $pathList = is_array($paths) ? $paths : [$paths];

                foreach ($pathList as $path) {
                    $fullPath = $projectRoot . '/' . rtrim($path, '/');

                    if (is_dir($fullPath)) {
                        $directories[] = $fullPath;
                    }
                }
            }
        }

        // Classmap
        if (isset($composerData['autoload']['classmap']) && is_array($composerData['autoload']['classmap'])) {
            foreach ($composerData['autoload']['classmap'] as $path) {
                $fullPath = $projectRoot . '/' . rtrim($path, '/');

                if (is_dir($fullPath)) {
                    $directories[] = $fullPath;
                }
            }
        }

        // Files (usually single files, but may contain directories)
        if (isset($composerData['autoload']['files']) && is_array($composerData['autoload']['files'])) {
            foreach ($composerData['autoload']['files'] as $path) {
                $fullPath = $projectRoot . '/' . rtrim($path, '/');

                if (is_dir($fullPath)) {
                    $directories[] = $fullPath;
                } elseif (is_file($fullPath)) {
                    // Add individual file
                    $this->filesToAnalyze[] = $fullPath;
                }
            }
        }

        // Also check autoload-dev for development classes
        if (isset($composerData['autoload-dev']['psr-4']) && is_array($composerData['autoload-dev']['psr-4'])) {
            foreach ($composerData['autoload-dev']['psr-4'] as $namespace => $paths) {
                $pathList = is_array($paths) ? $paths : [$paths];

                foreach ($pathList as $path) {
                    $fullPath = $projectRoot . '/' . rtrim($path, '/');

                    if (is_dir($fullPath)) {
                        $directories[] = $fullPath;
                    }
                }
            }
        }

        return array_unique($directories);
    }

    /**
     * Collect PHP files excluding vendor and cache directories
     *
     * @param string $directory Directory path
     *
     * @return void
     */
    private function collectFilesExcludingVendor(string $directory): void
    {
        $excludeDirs = ['vendor', 'node_modules', 'cache', 'var', 'storage', 'temp'];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();

                // Skip if in excluded directory
                $skip = false;

                foreach ($excludeDirs as $excludeDir) {
                    if (str_contains($path, '/' . $excludeDir . '/')) {
                        $skip = true;

                        break;
                    }
                }

                if (!$skip) {
                    $this->filesToAnalyze[] = $path;
                }
            }
        } catch (\Exception) {
            // Ignore errors during file collection
        }
    }

    /**
     * Collect all PHP files in a directory
     *
     * @param string $directory Directory path
     *
     * @return void
     */
    private function collectFiles(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i');

        foreach ($phpFiles as $file) {
            $this->filesToAnalyze[] = $file->getPathname();
        }
    }

    /**
     * First pass: collect method signatures and their @throws declarations
     *
     * @param string $filePath File path
     *
     * @return void
     */
    private function collectMethodThrows(string $filePath): void
    {
        $parser = (new ParserFactory())->createForHostVersion();

        try {
            $code = file_get_contents($filePath);
            $ast = $parser->parse($code);

            if ($ast === null) {
                return;
            }

            $visitor = new ThrowsVisitor($filePath, $this->globalMethodThrows);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            // Merge collected method throws into global map
            $this->globalMethodThrows = array_merge(
                $this->globalMethodThrows,
                $visitor->getMethodThrows()
            );
        } catch (Error) {
            // Ignore parse errors in first pass
        }
    }

    /**
     * Analyze a single PHP file
     *
     * @param string $filePath File path
     *
     * @return void
     */
    private function analyzeFile(string $filePath): void
    {
        $this->results['summary']['total_files']++;

        $parser = (new ParserFactory())->createForHostVersion();

        try {
            $code = file_get_contents($filePath);
            $ast = $parser->parse($code);

            if ($ast === null) {
                return;
            }

            $visitor = new ThrowsVisitor($filePath, $this->globalMethodThrows);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            $errors = $visitor->getErrors();

            if ($errors !== []) {
                $this->results['files'][] = [
                    'file' => $filePath,
                    'errors' => $errors,
                ];
                $this->results['summary']['files_with_errors']++;
                $this->results['summary']['total_errors'] += count($errors);
            }
        } catch (Error $error) {
            $this->results['files'][] = [
                'file' => $filePath,
                'errors' => [
                    [
                        'line' => $error->getStartLine(),
                        'type' => 'parse_error',
                        'message' => 'Parse error: ' . $error->getMessage(),
                    ],
                ],
            ];
            $this->results['summary']['files_with_errors']++;
            $this->results['summary']['total_errors']++;
        }
    }
}

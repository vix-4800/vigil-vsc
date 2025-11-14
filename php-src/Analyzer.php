<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector;

use Exception;
use InvalidArgumentException;
use JsonException;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class Analyzer
 *
 * Analyzes PHP files for throw statements and documents exceptions thrown.
 */
final class Analyzer
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
     * Cache manager for storing analysis results
     *
     * @var CacheManager|null
     */
    private ?CacheManager $cacheManager = null;

    /**
     * Performance statistics
     *
     * @var array{cache_hits: int, cache_misses: int, files_scanned: int, analysis_time_ms: float}
     */
    private array $perfStats = [
        'cache_hits' => 0,
        'cache_misses' => 0,
        'files_scanned' => 0,
        'analysis_time_ms' => 0,
    ];

    /**
     * Analyze a file or directory with optional project-wide context
     *
     * @param string $path                   File or directory path
     * @param bool   $useProjectWideAnalysis Whether to scan entire project for context
     *
     * @return array<string, mixed> Analysis results
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function analyze(string $path, bool $useProjectWideAnalysis = true): array
    {
        $startTime = microtime(true);

        $this->results = [
            'files' => [],
            'summary' => [
                'total_files' => 0,
                'files_with_errors' => 0,
                'total_errors' => 0,
            ],
            'performance' => [],
        ];
        $this->globalMethodThrows = [];
        $this->filesToAnalyze = [];
        $this->perfStats = [
            'cache_hits' => 0,
            'cache_misses' => 0,
            'files_scanned' => 0,
            'analysis_time_ms' => 0,
        ];

        if (is_file($path)) {
            $this->filesToAnalyze[] = $path;

            if ($useProjectWideAnalysis) {
                $this->projectRoot = $this->findProjectRoot($path);

                if ($this->projectRoot !== null) {
                    $this->cacheManager = new CacheManager($this->projectRoot);
                    $this->cacheManager->load();
                    $this->collectProjectFiles($this->projectRoot);
                }
            }
        } elseif (is_dir($path)) {
            $this->projectRoot = $path;
            $this->cacheManager = new CacheManager($this->projectRoot);
            $this->cacheManager->load();
            $this->collectFiles($path);
        } else {
            throw new InvalidArgumentException("Path does not exist: {$path}");
        }

        foreach ($this->filesToAnalyze as $file) {
            $this->collectMethodThrows($file);
        }

        if ($this->cacheManager !== null) {
            $this->cacheManager->setGlobalMethodThrows($this->globalMethodThrows);
            $this->cacheManager->save();
        }

        if (is_file($path)) {
            $this->analyzeFile($path);
        } else {
            foreach ($this->filesToAnalyze as $file) {
                $this->analyzeFile($file);
            }
        }

        $endTime = microtime(true);
        $this->perfStats['analysis_time_ms'] = ($endTime - $startTime) * 1000;

        $this->results['performance'] = $this->perfStats;

        if ($this->cacheManager !== null) {
            $cacheStats = $this->cacheManager->getStats();
            $this->results['performance']['cache_stats'] = $cacheStats;
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
        $maxDepth = 10;
        $depth = 0;

        while ($depth < $maxDepth) {
            if (file_exists("{$dir}/composer.json")) {
                return $dir;
            }

            $parentDir = dirname($dir);

            if ($parentDir === $dir) {
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
     *
     * @throws JsonException
     */
    private function collectProjectFiles(string $projectRoot): void
    {
        $dirsToScan = $this->detectSourceDirectories($projectRoot);

        if ($dirsToScan !== []) {
            foreach ($dirsToScan as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }

                $this->collectFiles($dir);
            }
        } else {
            $this->collectFilesExcludingVendor($projectRoot);
        }
    }

    /**
     * Detect source directories from composer.json autoload configuration
     *
     * @param string $projectRoot Project root directory
     *
     * @return string[] List of absolute paths to source directories
     *
     * @throws JsonException
     */
    private function detectSourceDirectories(string $projectRoot): array
    {
        $composerFile = "{$projectRoot}/composer.json";

        if (!file_exists($composerFile)) {
            return [];
        }

        $composerData = json_decode(file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($composerData)) {
            return [];
        }

        $directories = [];

        if (isset($composerData['autoload']['psr-4']) && is_array($composerData['autoload']['psr-4'])) {
            foreach ($composerData['autoload']['psr-4'] as $paths) {
                $pathList = is_array($paths) ? $paths : [$paths];

                foreach ($pathList as $path) {
                    $fullPath = $projectRoot . '/' . rtrim((string) $path, '/');

                    if (!is_dir($fullPath)) {
                        continue;
                    }

                    $directories[] = $fullPath;
                }
            }
        }

        if (isset($composerData['autoload']['psr-0']) && is_array($composerData['autoload']['psr-0'])) {
            foreach ($composerData['autoload']['psr-0'] as $paths) {
                $pathList = is_array($paths) ? $paths : [$paths];

                foreach ($pathList as $path) {
                    $fullPath = $projectRoot . '/' . rtrim((string) $path, '/');

                    if (!is_dir($fullPath)) {
                        continue;
                    }

                    $directories[] = $fullPath;
                }
            }
        }

        if (isset($composerData['autoload']['classmap']) && is_array($composerData['autoload']['classmap'])) {
            foreach ($composerData['autoload']['classmap'] as $path) {
                $fullPath = $projectRoot . '/' . rtrim((string) $path, '/');

                if (!is_dir($fullPath)) {
                    continue;
                }

                $directories[] = $fullPath;
            }
        }

        if (isset($composerData['autoload']['files']) && is_array($composerData['autoload']['files'])) {
            foreach ($composerData['autoload']['files'] as $path) {
                $fullPath = $projectRoot . '/' . rtrim((string) $path, '/');

                if (is_dir($fullPath)) {
                    $directories[] = $fullPath;
                } elseif (is_file($fullPath)) {
                    $this->filesToAnalyze[] = $fullPath;
                }
            }
        }

        if (isset($composerData['autoload-dev']['psr-4']) && is_array($composerData['autoload-dev']['psr-4'])) {
            foreach ($composerData['autoload-dev']['psr-4'] as $paths) {
                $pathList = is_array($paths) ? $paths : [$paths];

                foreach ($pathList as $path) {
                    $fullPath = $projectRoot . '/' . rtrim((string) $path, '/');

                    if (!is_dir($fullPath)) {
                        continue;
                    }

                    $directories[] = $fullPath;
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
            $directoryIterator = new RecursiveDirectoryIterator(
                $directory,
                RecursiveDirectoryIterator::SKIP_DOTS
            );

            // Filter directories at iterator level - more efficient than checking each file
            $filteredIterator = new RecursiveCallbackFilterIterator(
                $directoryIterator,
                static function (SplFileInfo $file) use ($excludeDirs): bool {
                    // Always allow files
                    if ($file->isFile()) {
                        return $file->getExtension() === 'php';
                    }

                    // Check if directory should be excluded
                    $basename = $file->getBasename();

                    return !in_array($basename, $excludeDirs, true);
                }
            );

            $iterator = new RecursiveIteratorIterator($filteredIterator);

            foreach ($iterator as $file) {
                $this->filesToAnalyze[] = $file->getPathname();
            }
        } catch (Exception) {
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
        $directoryIterator = new RecursiveDirectoryIterator(
            $directory,
            RecursiveDirectoryIterator::SKIP_DOTS
        );

        // Filter at iterator level - more efficient than RegexIterator
        $filteredIterator = new RecursiveCallbackFilterIterator(
            $directoryIterator,
            static function (SplFileInfo $file): bool {
                if ($file->isFile()) {
                    return $file->getExtension() === 'php';
                }

                // Allow all directories
                return true;
            }
        );

        $iterator = new RecursiveIteratorIterator($filteredIterator);

        foreach ($iterator as $file) {
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
        $this->perfStats['files_scanned']++;

        if ($this->cacheManager !== null) {
            $cached = $this->cacheManager->getMethodThrows($filePath);

            if ($cached['found']) {
                $this->perfStats['cache_hits']++;
                $this->globalMethodThrows = array_merge(
                    $this->globalMethodThrows,
                    $cached['methodThrows']
                );

                return;
            }

            $this->perfStats['cache_misses']++;
        }

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

            $methodThrows = $visitor->getMethodThrows();

            $this->globalMethodThrows = array_merge(
                $this->globalMethodThrows,
                $methodThrows
            );

            if ($this->cacheManager !== null) {
                $this->cacheManager->setMethodThrows($filePath, $methodThrows);
            }
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

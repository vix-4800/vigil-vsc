<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector;

use JsonException;

/**
 * Manages caching of analysis results to improve performance
 */
final class CacheManager
{
    /**
     * Cache file path
     *
     * @var string
     */
    private readonly string $cacheFilePath;

    /**
     * Cache data structure
     *
     * @var array{version: int, files: array<string, array{hash: string, methodThrows: array<string, string[]>, timestamp: int}>, globalMethodThrows: array<string, string[]>}
     */
    private array $cache = [];

    /**
     * Whether cache has been loaded from disk
     *
     * @var bool
     */
    private bool $cacheLoaded = false;

    /**
     * Whether cache has been modified and needs saving
     *
     * @var bool
     */
    private bool $cacheModified = false;

    /**
     * Constructor
     *
     * @param string $projectRoot Project root directory where cache will be stored
     */
    public function __construct(string $projectRoot)
    {
        $this->cacheFilePath = $projectRoot . '/.php-exception-inspector-cache.json';
    }

    /**
     * Load cache from disk if it exists
     *
     * @return void
     *
     * @throws JsonException
     */
    public function load(): void
    {
        if ($this->cacheLoaded) {
            return;
        }

        $this->cacheLoaded = true;

        if (!file_exists($this->cacheFilePath)) {
            $this->cache = [
                'version' => 1,
                'files' => [],
                'globalMethodThrows' => [],
            ];

            return;
        }

        try {
            $content = file_get_contents($this->cacheFilePath);

            if ($content === false) {
                $this->cache = [
                    'version' => 1,
                    'files' => [],
                    'globalMethodThrows' => [],
                ];

                return;
            }

            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data) || !isset($data['version']) || $data['version'] !== 1) {
                $this->cache = [
                    'version' => 1,
                    'files' => [],
                    'globalMethodThrows' => [],
                ];
                $this->cacheModified = true;

                return;
            }

            $this->cache = $data;
        } catch (JsonException) {
            $this->cache = [
                'version' => 1,
                'files' => [],
                'globalMethodThrows' => [],
            ];
            $this->cacheModified = true;
        }
    }

    /**
     * Save cache to disk
     *
     * @return void
     *
     * @throws JsonException
     */
    public function save(): void
    {
        if (!$this->cacheModified) {
            return;
        }

        $json = json_encode($this->cache, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        file_put_contents($this->cacheFilePath, $json);
        $this->cacheModified = false;
    }

    /**
     * Get cached method throws if file hasn't changed
     *
     * @param string $filePath File path
     *
     * @return array{methodThrows: array<string, string[]>, found: bool}
     */
    public function getMethodThrows(string $filePath): array
    {
        if (!$this->cacheLoaded) {
            $this->load();
        }

        if (!isset($this->cache['files'][$filePath])) {
            return ['methodThrows' => [], 'found' => false];
        }

        $cachedFile = $this->cache['files'][$filePath];
        $currentHash = $this->calculateFileHash($filePath);

        if ($cachedFile['hash'] !== $currentHash) {
            return ['methodThrows' => [], 'found' => false];
        }

        return [
            'methodThrows' => $cachedFile['methodThrows'] ?? [],
            'found' => true,
        ];
    }

    /**
     * Store method throws for a file
     *
     * @param string                  $filePath     File path
     * @param array<string, string[]> $methodThrows Method throws map
     *
     * @return void
     */
    public function setMethodThrows(string $filePath, array $methodThrows): void
    {
        if (!$this->cacheLoaded) {
            $this->load();
        }

        $hash = $this->calculateFileHash($filePath);

        $this->cache['files'][$filePath] = [
            'hash' => $hash,
            'methodThrows' => $methodThrows,
            'timestamp' => time(),
        ];

        $this->cacheModified = true;
    }

    /**
     * Get cached global method throws
     *
     * @return array<string, string[]>
     */
    public function getGlobalMethodThrows(): array
    {
        if (!$this->cacheLoaded) {
            $this->load();
        }

        return $this->cache['globalMethodThrows'] ?? [];
    }

    /**
     * Set global method throws in cache
     *
     * @param array<string, string[]> $globalMethodThrows Global method throws map
     *
     * @return void
     */
    public function setGlobalMethodThrows(array $globalMethodThrows): void
    {
        if (!$this->cacheLoaded) {
            $this->load();
        }

        $this->cache['globalMethodThrows'] = $globalMethodThrows;
        $this->cacheModified = true;
    }

    /**
     * Invalidate cache entry for a specific file
     *
     * @param string $filePath File path
     *
     * @return void
     */
    public function invalidateFile(string $filePath): void
    {
        if (!$this->cacheLoaded) {
            $this->load();
        }

        if (!isset($this->cache['files'][$filePath])) {
            return;
        }

        unset($this->cache['files'][$filePath]);
        $this->cacheModified = true;
    }

    /**
     * Check if a file has been modified since last cache
     *
     * @param string $filePath File path
     *
     * @return bool True if file was modified
     */
    public function isFileModified(string $filePath): bool
    {
        if (!$this->cacheLoaded) {
            $this->load();
        }

        if (!isset($this->cache['files'][$filePath])) {
            return true;
        }

        $cachedHash = $this->cache['files'][$filePath]['hash'];
        $currentHash = $this->calculateFileHash($filePath);

        return $cachedHash !== $currentHash;
    }

    /**
     * Clear all cache
     *
     * @return void
     */
    public function clear(): void
    {
        $this->cache = [
            'version' => 1,
            'files' => [],
            'globalMethodThrows' => [],
        ];
        $this->cacheModified = true;
        $this->cacheLoaded = true;
    }

    /**
     * Get cache statistics
     *
     * @return array{total_files: int, cache_size_bytes: int}
     */
    public function getStats(): array
    {
        if (!$this->cacheLoaded) {
            $this->load();
        }

        $cacheSize = 0;

        if (file_exists($this->cacheFilePath)) {
            $cacheSize = filesize($this->cacheFilePath);
        }

        return [
            'total_files' => count($this->cache['files'] ?? []),
            'cache_size_bytes' => $cacheSize !== false ? $cacheSize : 0,
        ];
    }

    /**
     * Calculate hash of file content and modification time
     *
     * @param string $filePath File path
     *
     * @return string Hash string
     */
    private function calculateFileHash(string $filePath): string
    {
        if (!file_exists($filePath)) {
            return '';
        }

        $mtime = filemtime($filePath);
        $size = filesize($filePath);

        return md5("{$mtime}:{$size}");
    }
}

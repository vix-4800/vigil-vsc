<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vix\ExceptionInspector\Analyzer;

/**
 * Test performance improvements with caching
 */
final class CachePerformanceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/php-exception-inspector-perf-' . uniqid();
        mkdir($this->tempDir);

        for ($i = 1; $i <= 10; $i++) {
            $content = "<?php\n\nnamespace Test;\n\nclass TestClass{$i}\n{\n";
            $content .= "    /**\n     * @throws \Exception\n     */\n";
            $content .= "    public function method1(): void\n    {\n        throw new \Exception('Error');\n    }\n\n";
            $content .= "    /**\n     * @throws \RuntimeException\n     */\n";
            $content .= "    public function method2(): void\n    {\n        throw new \RuntimeException('Error');\n    }\n";
            $content .= "}\n";

            file_put_contents($this->tempDir . "/TestClass{$i}.php", $content);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $files = glob("{$this->tempDir}/*");

        if ($files !== false) {
            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }

                unlink($file);
            }
        }

        $cacheFile = "{$this->tempDir}/.php-exception-inspector-cache.json";

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        if (!file_exists($this->tempDir) || !is_dir($this->tempDir)) {
            return;
        }

        rmdir($this->tempDir);
    }

    public function testFirstRunPerformance(): void
    {
        $analyzer = new Analyzer();

        $startTime = microtime(true);
        $result1 = $analyzer->analyze($this->tempDir, true);
        $firstRunTime = microtime(true) - $startTime;

        $this->assertArrayHasKey('performance', $result1);
        $this->assertArrayHasKey('cache_misses', $result1['performance']);
        $this->assertGreaterThan(0, $result1['performance']['cache_misses']);
        $this->assertSame(0, $result1['performance']['cache_hits']);

        echo "\n=== First Run (No Cache) ===\n";
        echo 'Time: ' . round($firstRunTime * 1000, 2) . "ms\n";
        echo "Files scanned: {$result1['performance']['files_scanned']}\n";
        echo "Cache hits: {$result1['performance']['cache_hits']}\n";
        echo "Cache misses: {$result1['performance']['cache_misses']}\n";
    }

    public function testSecondRunPerformanceWithCache(): void
    {
        $analyzer = new Analyzer();

        $result1 = $analyzer->analyze($this->tempDir, true);

        $this->assertArrayHasKey('performance', $result1);

        $analyzer2 = new Analyzer();
        $startTime = microtime(true);
        $result2 = $analyzer2->analyze($this->tempDir, true);
        $secondRunTime = microtime(true) - $startTime;

        $this->assertArrayHasKey('performance', $result2);
        $this->assertArrayHasKey('cache_hits', $result2['performance']);
        $this->assertGreaterThan(0, $result2['performance']['cache_hits']);

        $cacheHitRate = $result2['performance']['cache_hits'] / ($result2['performance']['cache_hits'] + $result2['performance']['cache_misses']) * 100;

        echo "\n=== Second Run (With Cache) ===\n";
        echo 'Time: ' . round($secondRunTime * 1000, 2) . "ms\n";
        echo "Files scanned: {$result2['performance']['files_scanned']}\n";
        echo "Cache hits: {$result2['performance']['cache_hits']}\n";
        echo "Cache misses: {$result2['performance']['cache_misses']}\n";
        echo 'Cache hit rate: ' . round($cacheHitRate, 1) . "%\n";

        if (isset($result2['performance']['cache_stats'])) {
            echo "Cached files: {$result2['performance']['cache_stats']['total_files']}\n";
            echo 'Cache size: ' . round($result2['performance']['cache_stats']['cache_size_bytes'] / 1024, 2) . " KB\n";
        }

        $this->assertGreaterThan(50, $cacheHitRate, 'Cache hit rate should be > 50% on second run');
    }

    public function testPerformanceComparison(): void
    {
        $analyzer = new Analyzer();

        $startTime1 = microtime(true);
        $result1 = $analyzer->analyze($this->tempDir, true);
        $firstRunTime = (microtime(true) - $startTime1) * 1000;

        $analyzer2 = new Analyzer();
        $startTime2 = microtime(true);
        $result2 = $analyzer2->analyze($this->tempDir, true);
        $secondRunTime = (microtime(true) - $startTime2) * 1000;

        echo "\n=== Performance Comparison ===\n";
        echo 'First run (no cache): ' . round($firstRunTime, 2) . "ms\n";
        echo 'Second run (cached): ' . round($secondRunTime, 2) . "ms\n";

        if ($firstRunTime > 0) {
            $improvement = ($firstRunTime - $secondRunTime) / $firstRunTime * 100;
            echo 'Performance improvement: ' . round($improvement, 1) . "%\n";

            if ($improvement > 0) {
                $speedup = $firstRunTime / $secondRunTime;
                echo 'Speedup: ' . round($speedup, 2) . "x faster\n";
            }
        }

        echo "\n";

        $this->assertSame(
            $result1['summary']['total_files'],
            $result2['summary']['total_files'],
            'Total files should be same in both runs'
        );
    }
}

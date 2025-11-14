<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vix\ExceptionInspector\CacheManager;

/**
 * Test cache manager functionality
 */
final class CacheManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/php-exception-inspector-test-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $cacheFile = $this->tempDir . '/.php-exception-inspector-cache.json';

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testCacheManagerInitialization(): void
    {
        $cache = new CacheManager($this->tempDir);
        $cache->load();

        $stats = $cache->getStats();

        $this->assertSame(0, $stats['total_files']);
        $this->assertSame(0, $stats['cache_size_bytes']);
    }

    public function testCacheStoresAndRetrievesMethodThrows(): void
    {
        $cache = new CacheManager($this->tempDir);
        $cache->load();

        $testFile = "{$this->tempDir}/test.php";
        file_put_contents($testFile, '<?php class Test {}');

        $methodThrows = [
            'Test::method1' => ['Exception', 'RuntimeException'],
            'Test::method2' => ['InvalidArgumentException'],
        ];

        $cache->setMethodThrows($testFile, $methodThrows);
        $cache->save();

        $cache2 = new CacheManager($this->tempDir);
        $cache2->load();

        $result = $cache2->getMethodThrows($testFile);

        $this->assertTrue($result['found']);
        $this->assertSame($methodThrows, $result['methodThrows']);

        unlink($testFile);
    }

    public function testCacheInvalidatesOnFileChange(): void
    {
        $cache = new CacheManager($this->tempDir);
        $cache->load();

        $testFile = "{$this->tempDir}/test.php";
        file_put_contents($testFile, '<?php class Test {}');

        $methodThrows = ['Test::method' => ['Exception']];
        $cache->setMethodThrows($testFile, $methodThrows);
        $cache->save();

        sleep(1);
        file_put_contents($testFile, '<?php class Test { public function test() {} }');

        $cache2 = new CacheManager($this->tempDir);
        $cache2->load();

        $result = $cache2->getMethodThrows($testFile);

        $this->assertFalse($result['found'], 'Cache should be invalidated after file modification');

        unlink($testFile);
    }

    public function testCacheInvalidateFile(): void
    {
        $cache = new CacheManager($this->tempDir);
        $cache->load();

        $testFile = "{$this->tempDir}/test.php";
        file_put_contents($testFile, '<?php class Test {}');

        $methodThrows = ['Test::method' => ['Exception']];
        $cache->setMethodThrows($testFile, $methodThrows);

        $cache->invalidateFile($testFile);

        $result = $cache->getMethodThrows($testFile);

        $this->assertFalse($result['found']);

        unlink($testFile);
    }

    public function testCacheClear(): void
    {
        $cache = new CacheManager($this->tempDir);
        $cache->load();

        $testFile = "{$this->tempDir}/test.php";
        file_put_contents($testFile, '<?php class Test {}');

        $methodThrows = ['Test::method' => ['Exception']];
        $cache->setMethodThrows($testFile, $methodThrows);
        $cache->save();

        $cache->clear();
        $cache->save();

        $cache2 = new CacheManager($this->tempDir);
        $cache2->load();

        $stats = $cache2->getStats();

        $this->assertSame(0, $stats['total_files']);

        unlink($testFile);
    }

    public function testGlobalMethodThrowsCaching(): void
    {
        $cache = new CacheManager($this->tempDir);
        $cache->load();

        $globalThrows = [
            'Class1::method1' => ['Exception'],
            'Class2::method2' => ['RuntimeException'],
        ];

        $cache->setGlobalMethodThrows($globalThrows);
        $cache->save();

        $cache2 = new CacheManager($this->tempDir);
        $cache2->load();

        $result = $cache2->getGlobalMethodThrows();

        $this->assertSame($globalThrows, $result);
    }

    public function testIsFileModified(): void
    {
        $cache = new CacheManager($this->tempDir);
        $cache->load();

        $testFile = "{$this->tempDir}/test.php";
        file_put_contents($testFile, '<?php class Test {}');

        $methodThrows = ['Test::method' => ['Exception']];
        $cache->setMethodThrows($testFile, $methodThrows);
        $cache->save();

        $this->assertFalse($cache->isFileModified($testFile));

        sleep(1);
        file_put_contents($testFile, '<?php class Modified {}');

        $cache2 = new CacheManager($this->tempDir);
        $cache2->load();

        $this->assertTrue($cache2->isFileModified($testFile));

        unlink($testFile);
    }

    public function testCacheStats(): void
    {
        $cache = new CacheManager($this->tempDir);
        $cache->load();

        $testFile1 = "{$this->tempDir}/test1.php";
        $testFile2 = "{$this->tempDir}/test2.php";

        file_put_contents($testFile1, '<?php class Test1 {}');
        file_put_contents($testFile2, '<?php class Test2 {}');

        $cache->setMethodThrows($testFile1, ['Test1::method' => ['Exception']]);
        $cache->setMethodThrows($testFile2, ['Test2::method' => ['RuntimeException']]);
        $cache->save();

        $stats = $cache->getStats();

        $this->assertSame(2, $stats['total_files']);
        $this->assertGreaterThan(0, $stats['cache_size_bytes']);

        unlink($testFile1);
        unlink($testFile2);
    }
}

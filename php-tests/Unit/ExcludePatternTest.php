<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vix\ExceptionInspector\Analyzer;

/**
 * Test exclude pattern functionality
 */
final class ExcludePatternTest extends TestCase
{
    /**
     * Test that default exclude patterns work correctly
     *
     * @return void
     */
    public function testDefaultExcludePatterns(): void
    {
        $analyzer = new Analyzer();
        $reflection = new ReflectionClass($analyzer);
        $method = $reflection->getMethod('shouldExcludeFile');
        $method->setAccessible(true);

        $this->assertTrue(
            $method->invoke($analyzer, '/path/to/project/vendor/package/src/File.php'),
            'Should exclude vendor directory'
        );

        $this->assertTrue(
            $method->invoke($analyzer, '/path/to/project/node_modules/package/index.php'),
            'Should exclude node_modules directory'
        );

        $this->assertTrue(
            $method->invoke($analyzer, '/path/to/project/.git/hooks/pre-commit.php'),
            'Should exclude .git directory'
        );

        $this->assertTrue(
            $method->invoke($analyzer, '/path/to/project/.idea/config.php'),
            'Should exclude .idea directory'
        );

        $this->assertTrue(
            $method->invoke($analyzer, '/path/to/project/.vscode/settings.json.php'),
            'Should exclude .vscode directory'
        );

        $this->assertFalse(
            $method->invoke($analyzer, '/path/to/project/src/MyClass.php'),
            'Should not exclude normal source files'
        );

        $this->assertFalse(
            $method->invoke($analyzer, '/path/to/project/file.php'),
            'Should not exclude .php files'
        );
    }

    /**
     * Test custom exclude patterns
     *
     * @return void
     */
    public function testCustomExcludePatterns(): void
    {
        $testDir = sys_get_temp_dir() . '/php-exception-inspector-test-' . uniqid();
        mkdir($testDir);

        $srcDir = $testDir . '/src';
        mkdir($srcDir);
        file_put_contents($srcDir . '/NormalFile.php', '<?php class NormalFile {}');

        $testFile = $testDir . '/TestFile.php';
        file_put_contents($testFile, '<?php class TestFile {}');

        $generatedDir = $testDir . '/generated';
        mkdir($generatedDir);
        file_put_contents($generatedDir . '/GeneratedFile.php', '<?php class GeneratedFile {}');

        try {
            $analyzer = new Analyzer();

            $customPatterns = [
                '/generated/',
                '/Test/',
            ];

            $result = $analyzer->analyze($testDir, false, $customPatterns);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('summary', $result);
            $this->assertEquals(1, $result['summary']['total_files'], 'Should scan 1 file');

            if (isset($result['files'][0])) {
                $this->assertStringContainsString('NormalFile.php', $result['files'][0]['file']);
            }
        } finally {
            array_map('unlink', glob($srcDir . '/*'));
            array_map('unlink', glob($generatedDir . '/*'));
            rmdir($srcDir);
            rmdir($generatedDir);
            unlink($testFile);
            rmdir($testDir);
        }
    }

    /**
     * Test exclude patterns with regex
     *
     * @return void
     */
    public function testRegexExcludePatterns(): void
    {
        $analyzer = new Analyzer();
        $reflection = new ReflectionClass($analyzer);
        $method = $reflection->getMethod('shouldExcludeFile');
        $method->setAccessible(true);

        $customPatterns = [
            '/\\.test\\.php$/', // Exclude files ending with .test.php
            '/^.*_generated\\.php$/', // Exclude files ending with _generated.php
            '/migrations/', // Exclude migrations directory
        ];

        $analyzeMethod = $reflection->getMethod('analyze');
        $analyzeMethod->invoke($analyzer, __FILE__, false, $customPatterns);

        $this->assertTrue(
            $method->invoke($analyzer, '/path/to/SomeClass.test.php'),
            'Should exclude .test.php files'
        );

        $this->assertTrue(
            $method->invoke($analyzer, '/path/to/Model_generated.php'),
            'Should exclude _generated.php files'
        );

        $this->assertTrue(
            $method->invoke($analyzer, '/path/to/database/migrations/2024_01_01_create_table.php'),
            'Should exclude migrations directory'
        );

        $this->assertFalse(
            $method->invoke($analyzer, '/path/to/src/Controller.php'),
            'Should not exclude normal files'
        );
    }

    /**
     * Test that empty patterns array uses defaults
     *
     * @return void
     */
    public function testEmptyPatternsUsesDefaults(): void
    {
        $analyzer = new Analyzer();
        $reflection = new ReflectionClass($analyzer);

        $analyzeMethod = $reflection->getMethod('analyze');
        $analyzeMethod->invoke($analyzer, __FILE__, false, []);

        $shouldExcludeMethod = $reflection->getMethod('shouldExcludeFile');
        $shouldExcludeMethod->setAccessible(true);

        $this->assertTrue(
            $shouldExcludeMethod->invoke($analyzer, '/project/vendor/package/File.php'),
            'Should still use default patterns when empty array is provided'
        );
    }

    /**
     * Test Windows path separators are handled correctly
     *
     * @return void
     */
    public function testWindowsPathSeparators(): void
    {
        $analyzer = new Analyzer();
        $reflection = new ReflectionClass($analyzer);
        $method = $reflection->getMethod('shouldExcludeFile');
        $method->setAccessible(true);

        $this->assertTrue(
            $method->invoke($analyzer, 'C:\\Users\\Project\\vendor\\package\\File.php'),
            'Should handle Windows path separators'
        );

        $this->assertTrue(
            $method->invoke($analyzer, 'C:\\Users\\Project\\.git\\config.php'),
            'Should handle Windows path separators for hidden directories'
        );
    }

    /**
     * Test that directly specified vendor files are excluded
     *
     * @return void
     */
    public function testDirectlySpecifiedVendorFileIsExcluded(): void
    {
        $testDir = sys_get_temp_dir() . '/php-exception-inspector-test-' . uniqid();
        mkdir($testDir);

        $vendorDir = $testDir . '/vendor/package';
        mkdir($vendorDir, 0777, true);

        $vendorFile = $vendorDir . '/VendorClass.php';
        file_put_contents($vendorFile, '<?php
namespace Vendor\Package;

class VendorClass {
    public function method() {
        throw new \Exception("test");
    }
}
');

        try {
            $analyzer = new Analyzer();
            $result = $analyzer->analyze($vendorFile, false);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('summary', $result);
            $this->assertEquals(0, $result['summary']['total_files'], 'Should not analyze vendor files even when directly specified');
            $this->assertEmpty($result['files'], 'Should have no files in results');
        } finally {
            unlink($vendorFile);
            rmdir($vendorDir);
            rmdir(dirname($vendorDir));
            rmdir($testDir);
        }
    }
}

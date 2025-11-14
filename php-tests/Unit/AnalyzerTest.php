<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vix\ExceptionInspector\Analyzer;

class AnalyzerTest extends TestCase
{
    private Analyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer();
    }

    public function testAnalyzeCleanCodeWithNoErrors(): void
    {
        $filePath = __DIR__ . '/../Fixtures/CleanCode.php';
        $results = $this->analyzer->analyze($filePath);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('files', $results);
        $this->assertArrayHasKey('summary', $results);

        // Clean code should have no errors
        $this->assertCount(0, $results['files']);
        $this->assertEquals(0, $results['summary']['total_errors']);
        $this->assertEquals(0, $results['summary']['files_with_errors']);
        $this->assertEquals(1, $results['summary']['total_files']);
    }

    public function testAnalyzeFileWithUndocumentedThrows(): void
    {
        $filePath = __DIR__ . '/../Fixtures/UndocumentedThrows.php';
        $results = $this->analyzer->analyze($filePath);

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, $results['summary']['total_errors']);
        $this->assertEquals(1, $results['summary']['files_with_errors']);
        $this->assertEquals(1, $results['summary']['total_files']);

        // Should have errors for undocumented throws
        $this->assertCount(1, $results['files']);
        $this->assertArrayHasKey('errors', $results['files'][0]);
        $this->assertGreaterThan(0, count($results['files'][0]['errors']));
    }

    public function testAnalyzeNonExistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Path does not exist');

        $this->analyzer->analyze('/non/existent/file.php');
    }

    public function testAnalyzeDirectory(): void
    {
        $dirPath = __DIR__ . '/../Fixtures';
        $results = $this->analyzer->analyze($dirPath);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('summary', $results);

        // Should analyze multiple files in the directory
        $this->assertGreaterThanOrEqual(2, $results['summary']['total_files']);
    }

    public function testErrorStructure(): void
    {
        $filePath = __DIR__ . '/../Fixtures/UndocumentedThrows.php';
        $results = $this->analyzer->analyze($filePath);

        $this->assertCount(1, $results['files']);

        $fileResult = $results['files'][0];
        $this->assertArrayHasKey('file', $fileResult);
        $this->assertArrayHasKey('errors', $fileResult);
        $this->assertEquals($filePath, $fileResult['file']);

        foreach ($fileResult['errors'] as $error) {
            $this->assertIsInt($error['line']);
            $this->assertIsString($error['type']);
            $this->assertIsString($error['exception']);
            $this->assertIsString($error['message']);
        }
    }

    public function testSummaryStructure(): void
    {
        $filePath = __DIR__ . '/../Fixtures/CleanCode.php';
        $results = $this->analyzer->analyze($filePath);

        $summary = $results['summary'];

        $this->assertArrayHasKey('total_files', $summary);
        $this->assertArrayHasKey('files_with_errors', $summary);
        $this->assertArrayHasKey('total_errors', $summary);

        $this->assertIsInt($summary['total_files']);
        $this->assertIsInt($summary['files_with_errors']);
        $this->assertIsInt($summary['total_errors']);
    }

    public function testAnalyzeFileWithUnnecessaryThrows(): void
    {
        $filePath = __DIR__ . '/../Fixtures/OverdocumentedThrows.php';
        $results = $this->analyzer->analyze($filePath);

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, $results['summary']['total_errors']);
        $this->assertEquals(1, $results['summary']['files_with_errors']);
        $this->assertEquals(1, $results['summary']['total_files']);

        // Should have errors for unnecessary @throws
        $this->assertCount(1, $results['files']);
        $fileResult = $results['files'][0];
        $this->assertArrayHasKey('errors', $fileResult);

        // Check that we found unnecessary_throws errors
        $unnecessaryErrors = array_filter(
            $fileResult['errors'],
            fn($error) => $error['type'] === 'unnecessary_throws'
        );
        $this->assertGreaterThan(0, count($unnecessaryErrors));

        // Verify error structure
        foreach ($unnecessaryErrors as $error) {
            $this->assertArrayHasKey('line', $error);
            $this->assertArrayHasKey('type', $error);
            $this->assertArrayHasKey('exception', $error);
            $this->assertArrayHasKey('function', $error);
            $this->assertArrayHasKey('message', $error);
            $this->assertEquals('unnecessary_throws', $error['type']);
        }
    }
}

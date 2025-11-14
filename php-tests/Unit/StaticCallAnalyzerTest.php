<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vix\ExceptionInspector\Analyzer;

/**
 * Test static method call analysis
 */
class StaticCallAnalyzerTest extends TestCase
{
    private Analyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer();
    }

    public function testStaticCallWithDocumentedThrows(): void
    {
        $results = $this->analyzer->analyze(
            __DIR__ . '/../Fixtures/StaticCallThrows.php'
        );

        $this->assertArrayHasKey('files', $results);
        $this->assertCount(1, $results['files']);

        $file = reset($results['files']);
        $errors = $file['errors'];

        // Find the getCategories method - should have NO errors
        // because InvalidConfigException is properly documented
        $getCategoriesErrors = array_filter($errors, function ($error) {
            return $error['function'] === 'getCategories';
        });

        $this->assertEmpty(
            $getCategoriesErrors,
            'getCategories() should have no errors as InvalidConfigException is properly documented'
        );
    }

    public function testStaticCallWithoutDocumentedThrows(): void
    {
        $results = $this->analyzer->analyze(
            __DIR__ . '/../Fixtures/StaticCallThrows.php'
        );

        $file = reset($results['files']);
        $errors = $file['errors'];

        // Find the getCategoriesWithoutThrows method - should have an error
        $getCategoriesWithoutThrowsErrors = array_filter($errors, function ($error) {
            return $error['function'] === 'getCategoriesWithoutThrows'
                && $error['type'] === 'undeclared_throw_from_call';
        });

        $this->assertNotEmpty(
            $getCategoriesWithoutThrowsErrors,
            'getCategoriesWithoutThrows() should have an error for missing InvalidConfigException'
        );

        $error = reset($getCategoriesWithoutThrowsErrors);
        $this->assertEquals('InvalidConfigException', $error['exception']);
        $this->assertEquals('find', $error['called_method']);
    }

    public function testSelfStaticCall(): void
    {
        $results = $this->analyzer->analyze(
            __DIR__ . '/../Fixtures/StaticCallThrows.php'
        );

        $file = reset($results['files']);
        $errors = $file['errors'];

        // Find the testSelfCall method - should have NO errors
        // because InvalidConfigException from helperMethod is properly documented
        $testSelfCallErrors = array_filter($errors, function ($error) {
            return $error['function'] === 'testSelfCall'
                && $error['type'] === 'undeclared_throw_from_call';
        });

        $this->assertEmpty(
            $testSelfCallErrors,
            'testSelfCall() should have no errors as InvalidConfigException is properly documented'
        );
    }
}

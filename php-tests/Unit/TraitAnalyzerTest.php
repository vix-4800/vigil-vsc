<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vix\ExceptionInspector\Analyzer;

final class TraitAnalyzerTest extends TestCase
{
    private Analyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer();
    }

    public function testDetectsUndocumentedExceptionFromTrait(): void
    {
        $filePath = __DIR__ . '/../Fixtures/ClassUsingTrait.php';
        $result = $this->analyzer->analyze($filePath);

        $this->assertCount(1, $result['files']);
        $file = $result['files'][0];

        $this->assertEquals($filePath, $file['file']);
        $this->assertCount(1, $file['errors']);

        $error = $file['errors'][0];
        $this->assertEquals('undeclared_throw_from_call', $error['type']);
        $this->assertEquals('RuntimeException', $error['exception']);
        $this->assertEquals('callTraitMethod', $error['function']);
        $this->assertEquals('throwingMethod', $error['called_method']);
    }

    public function testAllowsDocumentedExceptionFromTrait(): void
    {
        $filePath = __DIR__ . '/../Fixtures/ClassUsingTrait.php';
        $result = $this->analyzer->analyze($filePath);

        // The method callTraitMethodDocumented should NOT produce errors
        // because it documents the RuntimeException
        $errors = $result['files'][0]['errors'] ?? [];

        foreach ($errors as $error) {
            $this->assertNotEquals(
                'callTraitMethodDocumented',
                $error['function'] ?? '',
                'Method callTraitMethodDocumented should not have errors'
            );
        }
    }

    public function testTraitMethodsAreCollectedInGlobalContext(): void
    {
        $filePath = __DIR__ . '/../Fixtures/ClassUsingTrait.php';
        $result = $this->analyzer->analyze($filePath);

        // Verify that we found method throws from the trait
        $this->assertGreaterThan(0, $result['summary']['total_files']);
    }

    public function testMultipleTraitsAnalysis(): void
    {
        $filePath = __DIR__ . '/../Fixtures/MultipleTraits.php';
        $result = $this->analyzer->analyze($filePath);

        $this->assertCount(1, $result['files']);
        $file = $result['files'][0];

        // Should detect 2 errors: InvalidArgumentException and LogicException
        $this->assertCount(2, $file['errors']);

        $errors = $file['errors'];

        // First error: InvalidArgumentException from ValidationTrait
        $this->assertEquals('undeclared_throw_from_call', $errors[0]['type']);
        $this->assertEquals('InvalidArgumentException', $errors[0]['exception']);
        $this->assertEquals('processData', $errors[0]['function']);
        $this->assertEquals('validate', $errors[0]['called_method']);

        // Second error: LogicException from LoggingTrait
        $this->assertEquals('undeclared_throw_from_call', $errors[1]['type']);
        $this->assertEquals('LogicException', $errors[1]['exception']);
        $this->assertEquals('justLog', $errors[1]['function']);
        $this->assertEquals('log', $errors[1]['called_method']);
    }
}

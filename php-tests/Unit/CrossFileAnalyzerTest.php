<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vix\ExceptionInspector\Analyzer;

class CrossFileAnalyzerTest extends TestCase
{
    private Analyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer();
    }

    public function testCrossFileAnalysis(): void
    {
        $dirPath = __DIR__ . '/../Fixtures/CrossFile';
        $results = $this->analyzer->analyze($dirPath);

        $this->assertIsArray($results);
        $this->assertEquals(2, $results['summary']['total_files']);
        $this->assertGreaterThan(0, $results['summary']['total_errors']);

        // Find UserService.php errors
        $userServiceErrors = null;
        foreach ($results['files'] as $file) {
            if (str_contains($file['file'], 'UserService.php')) {
                $userServiceErrors = $file['errors'];
                break;
            }
        }

        $this->assertNotNull($userServiceErrors, 'UserService.php should have errors');
        $this->assertGreaterThan(0, count($userServiceErrors));

        // Check that we detect undeclared exceptions from cross-file method calls
        $hasUndeclaredFromCall = false;
        foreach ($userServiceErrors as $error) {
            if ($error['type'] === 'undeclared_throw_from_call') {
                $hasUndeclaredFromCall = true;

                // Verify error structure for cross-file calls
                $this->assertArrayHasKey('called_method', $error);
                $this->assertArrayHasKey('called_class', $error);
                $this->assertArrayHasKey('exception', $error);
                break;
            }
        }

        $this->assertTrue($hasUndeclaredFromCall, 'Should detect exceptions from cross-file method calls');
    }

    public function testGetUserMissingThrows(): void
    {
        $dirPath = __DIR__ . '/../Fixtures/CrossFile';
        $results = $this->analyzer->analyze($dirPath);

        // Find errors for getUser method
        $getUserErrors = [];
        foreach ($results['files'] as $file) {
            if (str_contains($file['file'], 'UserService.php')) {
                foreach ($file['errors'] as $error) {
                    if ($error['function'] === 'getUser') {
                        $getUserErrors[] = $error;
                    }
                }
            }
        }

        // getUser should have 2 errors: InvalidArgumentException and RuntimeException
        $this->assertEquals(2, count($getUserErrors));

        $exceptions = array_column($getUserErrors, 'exception');
        $this->assertContains('InvalidArgumentException', $exceptions);
        $this->assertContains('RuntimeException', $exceptions);
    }

    public function testGetUserProperlyNoErrors(): void
    {
        $dirPath = __DIR__ . '/../Fixtures/CrossFile';
        $results = $this->analyzer->analyze($dirPath);

        // Find errors for getUserProperly method
        $getUserProperlyErrors = [];
        foreach ($results['files'] as $file) {
            if (str_contains($file['file'], 'UserService.php')) {
                foreach ($file['errors'] as $error) {
                    if ($error['function'] === 'getUserProperly') {
                        $getUserProperlyErrors[] = $error;
                    }
                }
            }
        }

        // getUserProperly should have NO errors because it documents the exceptions
        $this->assertEquals(0, count($getUserProperlyErrors));
    }

    public function testCreateUserMissingThrows(): void
    {
        $dirPath = __DIR__ . '/../Fixtures/CrossFile';
        $results = $this->analyzer->analyze($dirPath);

        // Find errors for createUser method
        $createUserErrors = [];
        foreach ($results['files'] as $file) {
            if (str_contains($file['file'], 'UserService.php')) {
                foreach ($file['errors'] as $error) {
                    if ($error['function'] === 'createUser') {
                        $createUserErrors[] = $error;
                    }
                }
            }
        }

        // createUser should have 1 error: InvalidArgumentException
        $this->assertEquals(1, count($createUserErrors));
        $this->assertEquals('InvalidArgumentException', $createUserErrors[0]['exception']);
        $this->assertEquals('save', $createUserErrors[0]['called_method']);
    }
}

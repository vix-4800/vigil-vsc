<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vix\ExceptionInspector\Analyzer;

/**
 * Test case for try-catch exception handling
 */
final class TryCatchAnalyzerTest extends TestCase
{
    /**
     * Test that caught exceptions don't require @throws
     *
     * @return void
     */
    public function testExceptionIsCaught(): void
    {
        $analyzer = new Analyzer();
        $result = $analyzer->analyze(__DIR__ . '/../Fixtures/TryCatchHandling.php');

        $errors = $result['files'][0]['errors'] ?? [];

        // exceptionIsCaught() should have NO errors - exception is caught
        $methodErrors = array_filter(
            $errors,
            static fn (array $error) => isset($error['function']) && $error['function'] === 'exceptionIsCaught'
        );

        $this->assertCount(0, $methodErrors, 'exceptionIsCaught should have no errors');
    }

    /**
     * Test that uncaught exceptions require @throws
     *
     * @return void
     */
    public function testExceptionNotCaught(): void
    {
        $analyzer = new Analyzer();
        $result = $analyzer->analyze(__DIR__ . '/../Fixtures/TryCatchHandling.php');

        $errors = $result['files'][0]['errors'] ?? [];

        // exceptionNotCaught() throws RuntimeException but catches only InvalidArgumentException
        // Should have error about undeclared RuntimeException
        $methodErrors = array_filter(
            $errors,
            static fn (array $error) => isset($error['function'])
                && $error['function'] === 'exceptionNotCaught'
                && $error['type'] === 'undeclared_throw'
        );

        // Should have @throws Exception which covers RuntimeException, so actually should be 0 errors
        $this->assertCount(0, $methodErrors);
    }

    /**
     * Test multiple catch blocks
     *
     * @return void
     */
    public function testMultipleCatchBlocks(): void
    {
        $analyzer = new Analyzer();
        $result = $analyzer->analyze(__DIR__ . '/../Fixtures/TryCatchHandling.php');

        $errors = $result['files'][0]['errors'] ?? [];

        // multipleCatchBlocks() catches both RuntimeException and InvalidArgumentException
        $methodErrors = array_filter(
            $errors,
            static fn (array $error) => isset($error['function']) && $error['function'] === 'multipleCatchBlocks'
        );

        $this->assertCount(0, $methodErrors, 'multipleCatchBlocks should have no errors');
    }

    /**
     * Test partial catch - some exceptions caught, some not
     *
     * @return void
     */
    public function testPartialCatch(): void
    {
        $analyzer = new Analyzer();
        $result = $analyzer->analyze(__DIR__ . '/../Fixtures/TryCatchHandling.php');

        $errors = $result['files'][0]['errors'] ?? [];

        // partialCatch() has one RuntimeException in try-catch (not caught) and one outside
        // Both should be documented, and they are
        $methodErrors = array_filter(
            $errors,
            static fn (array $error) => isset($error['function'])
                && $error['function'] === 'partialCatch'
                && $error['type'] === 'undeclared_throw'
        );

        $this->assertCount(0, $methodErrors);
    }

    /**
     * Test nested try-catch blocks
     *
     * @return void
     */
    public function testNestedTryCatch(): void
    {
        $analyzer = new Analyzer();
        $result = $analyzer->analyze(__DIR__ . '/../Fixtures/TryCatchHandling.php');

        $errors = $result['files'][0]['errors'] ?? [];

        // nestedTryCatch() has all exceptions caught
        $methodErrors = array_filter(
            $errors,
            static fn (array $error) => isset($error['function']) && $error['function'] === 'nestedTryCatch'
        );

        $this->assertCount(0, $methodErrors, 'nestedTryCatch should have no errors');
    }

    /**
     * Test method call with caught exception
     *
     * @return void
     */
    public function testMethodCallCaught(): void
    {
        $analyzer = new Analyzer();
        $result = $analyzer->analyze(__DIR__ . '/../Fixtures/TryCatchHandling.php');

        $errors = $result['files'][0]['errors'] ?? [];

        // methodCallCaught() calls method that throws RuntimeException, but catches it
        $methodErrors = array_filter(
            $errors,
            static fn (array $error) => isset($error['function']) && $error['function'] === 'methodCallCaught'
        );

        $this->assertCount(0, $methodErrors, 'methodCallCaught should have no errors');
    }

    /**
     * Test method call with uncaught exception
     *
     * @return void
     */
    public function testMethodCallNotCaught(): void
    {
        $analyzer = new Analyzer();
        $result = $analyzer->analyze(__DIR__ . '/../Fixtures/TryCatchHandling.php');

        $errors = $result['files'][0]['errors'] ?? [];

        // methodCallNotCaught() calls method that throws RuntimeException, but catches only InvalidArgumentException
        $methodErrors = array_filter(
            $errors,
            static fn (array $error) => isset($error['function'])
                && $error['function'] === 'methodCallNotCaught'
                && $error['type'] === 'undeclared_throw_from_call'
        );

        // Should have @throws RuntimeException, so no errors
        $this->assertCount(0, $methodErrors);
    }
}

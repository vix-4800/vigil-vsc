<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Vix\ExceptionInspector\ThrowsVisitor;

class ThrowsVisitorTest extends TestCase
{
    public function testDetectsUndocumentedThrow(): void
    {
        $code = <<<'PHP'
<?php
class TestClass {
    public function test(): void {
        throw new \Exception('test');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $errors = $visitor->getErrors();

        $this->assertCount(1, $errors);
        $this->assertEquals('undeclared_throw', $errors[0]['type']);
        $this->assertEquals('\Exception', $errors[0]['exception']);
    }

    public function testNoErrorsForDocumentedThrow(): void
    {
        $code = <<<'PHP'
<?php
class TestClass {
    /**
     * @throws \Exception
     */
    public function test(): void {
        throw new \Exception('test');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $errors = $visitor->getErrors();

        $this->assertCount(0, $errors);
    }

    public function testDetectsMultipleUndocumentedThrows(): void
    {
        $code = <<<'PHP'
<?php
class TestClass {
    public function test(): void {
        throw new \InvalidArgumentException('test');
        throw new \RuntimeException('test');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $errors = $visitor->getErrors();

        $this->assertCount(2, $errors);
    }

    public function testPartiallyDocumentedThrows(): void
    {
        $code = <<<'PHP'
<?php
class TestClass {
    /**
     * @throws \InvalidArgumentException
     */
    public function test(): void {
        throw new \InvalidArgumentException('test');
        throw new \RuntimeException('test');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $errors = $visitor->getErrors();

        // Only RuntimeException should be reported
        $this->assertCount(1, $errors);
        $this->assertEquals('\RuntimeException', $errors[0]['exception']);
    }

    public function testFunctionWithoutDocblock(): void
    {
        $code = <<<'PHP'
<?php
function test(): void {
    throw new \Exception('test');
}
PHP;

        $visitor = $this->analyzeCode($code);
        $errors = $visitor->getErrors();

        $this->assertCount(1, $errors);
    }

    public function testNoThrowsInCode(): void
    {
        $code = <<<'PHP'
<?php
class TestClass {
    public function test(): void {
        return;
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $errors = $visitor->getErrors();

        $this->assertCount(0, $errors);
    }

    public function testErrorContainsAllRequiredFields(): void
    {
        $code = <<<'PHP'
<?php
class TestClass {
    public function testMethod(): void {
        throw new \Exception('test');
    }
}
PHP;

        $visitor = $this->analyzeCode($code);
        $errors = $visitor->getErrors();

        $this->assertCount(1, $errors);
        $error = $errors[0];

        $this->assertArrayHasKey('line', $error);
        $this->assertArrayHasKey('type', $error);
        $this->assertArrayHasKey('exception', $error);
        $this->assertArrayHasKey('function', $error);
        $this->assertArrayHasKey('message', $error);

        $this->assertEquals('testMethod', $error['function']);
        $this->assertEquals('undeclared_throw', $error['type']);
    }

    private function analyzeCode(string $code): ThrowsVisitor
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($code);

        $visitor = new ThrowsVisitor('test.php');
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor;
    }
}

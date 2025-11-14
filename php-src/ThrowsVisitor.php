<?php

declare(strict_types=1);

namespace Vix\ExceptionInspector;

use Exception;
use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor to analyze @throws declarations and thrown exceptions
 */
final class ThrowsVisitor extends NodeVisitorAbstract
{
    /**
     * Error records
     *
     * @var string[]
     */
    private array $errors = [];

    /**
     * DocBlock factory instance
     *
     * @var DocBlockFactory|null
     */
    private ?DocBlockFactory $docBlockFactory = null;

    /**
     * Current function or method name
     *
     * @var string|null
     */
    private ?string $currentFunction = null;

    /**
     * Current class name
     *
     * @var string|null
     */
    private ?string $currentClass = null;

    /**
     * Declared @throws for the current function/method
     *
     * @var string[]
     */
    private array $declaredThrows = [];

    /**
     * Actually thrown exceptions in the current function/method
     *
     * @var string[]
     */
    private array $actuallyThrown = [];

    /**
     * Map of method signatures to their declared @throws
     * Format: ['ClassName::methodName' => ['ExceptionType1', 'ExceptionType2']]
     *
     * @var array<string, string[]>
     */
    private array $methodThrows = [];

    /**
     * Current namespace
     *
     * @var string|null
     */
    private ?string $currentNamespace = null;

    /**
     * Map of property names to their types in current class
     * Format: ['propertyName' => 'Namespace\ClassName']
     *
     * @var array<string, string>
     */
    private array $propertyTypes = [];

    /**
     * Use imports for current file
     * Format: ['Alias' => 'Fully\\Qualified\\Class']
     *
     * @var array<string, string>
     */
    private array $useImports = [];

    /**
     * Constructor
     *
     * @param string                  $filePath           File path being analyzed
     * @param array<string, string[]> $globalMethodThrows Global method throws map
     *
     * @return void
     */
    public function __construct(private readonly string $filePath, private array $globalMethodThrows = [])
    {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * Get collected errors
     *
     * @return string[] Collected error records
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get collected method throws map
     *
     * @return array<string, string[]> Method throws map
     */
    public function getMethodThrows(): array
    {
        return $this->methodThrows;
    }

    /**
     * Enter node callback
     *
     * @param Node $node Current node
     *
     * @return void
     */
    public function enterNode(Node $node): void
    {
        if ($node instanceof Namespace_ && $node->name !== null) {
            $this->currentNamespace = $node->name->toString();
            $this->useImports = [];

            foreach ($node->stmts as $stmt) {
                if (!$stmt instanceof Use_) {
                    continue;
                }

                foreach ($stmt->uses as $use) {
                    $alias = $use->alias?->toString() ?? $use->name->getLast();
                    $this->useImports[$alias] = $use->name->toString();
                }
            }
        }

        if ($node instanceof Class_ || $node instanceof Trait_) {
            $className = $node->name?->toString();

            if ($className !== null) {
                $this->currentClass = $this->currentNamespace !== null
                    ? "{$this->currentNamespace}\\{$className}"
                    : $className;
                $this->propertyTypes = [];
            }
        }

        if ($node instanceof Function_ || $node instanceof ClassMethod) {
            $this->currentFunction = $node->name->toString();
            $this->declaredThrows = [];
            $this->actuallyThrown = [];

            $docComment = $node->getDocComment();

            if ($docComment !== null) {
                $this->parseDocBlock($docComment);
            }

            if ($this->currentClass !== null && $node instanceof ClassMethod) {
                $methodSignature = "{$this->currentClass}::{$this->currentFunction}";
                $this->methodThrows[$methodSignature] = $this->declaredThrows;
            }

            if ($node instanceof ClassMethod && $node->name->toString() === '__construct') {
                foreach ($node->getParams() as $param) {
                    if ($param->flags === 0) {
                        continue;
                    }

                    if (!$param->type instanceof Name) {
                        continue;
                    }

                    $propertyName = $param->var->name;
                    $typeName = $param->type->toString();

                    $qualifiedNamespace = $this->currentNamespace !== null ? "{$this->currentNamespace}\\{$typeName}" : $typeName;

                    $fullTypeName = $param->type->isFullyQualified()
                        ? $typeName
                        : $qualifiedNamespace;

                    $this->propertyTypes[$propertyName] = $fullTypeName;
                }
            }
        }

        if ($node instanceof Throw_) {
            $this->analyzeThrow($node);
        }

        if ($node instanceof MethodCall) {
            $this->analyzeMethodCall($node);
        }

        if ($node instanceof StaticCall) {
            $this->analyzeStaticCall($node);
        }
    }

    /**
     * Leave node callback
     *
     * @param Node $node Current node
     *
     * @return void
     */
    public function leaveNode(Node $node): void
    {
        if ($node instanceof Class_ || $node instanceof Trait_) {
            $this->currentClass = null;
        }

        if (!$node instanceof Function_ && !$node instanceof ClassMethod) {
            return;
        }

        foreach ($this->declaredThrows as $declared) {
            $isActuallyThrown = false;

            foreach ($this->actuallyThrown as $thrown) {
                if ($this->isExceptionMatching($thrown, $declared)) {
                    $isActuallyThrown = true;

                    break;
                }
            }

            if ($isActuallyThrown) {
                continue;
            }

            $this->errors[] = [
                'line' => $node->getStartLine(),
                'type' => 'unnecessary_throws',
                'exception' => $declared,
                'function' => $this->currentFunction,
                'message' => "Exception '{$declared}' is documented in @throws but never thrown",
            ];
        }

        $this->currentFunction = null;
        $this->declaredThrows = [];
        $this->actuallyThrown = [];
    }

    /**
     * Parse DocBlock for @throws tags
     *
     * @param Doc $docComment DocBlock comment
     *
     * @return void
     */
    private function parseDocBlock(Doc $docComment): void
    {
        try {
            $docBlock = $this->docBlockFactory->create($docComment->getText());

            foreach ($docBlock->getTagsByName('throws') as $tag) {
                $type = $tag->getType();

                if ($type === null) {
                    return;
                }

                $typeName = (string) $type;

                $typeName = ltrim($typeName, '\\');
                $this->declaredThrows[] = $typeName;
            }
        } catch (Exception) {
            // Ignore DocBlock parsing errors
        }
    }

    /**
     * Analyze a throw statement
     *
     * @param Throw_ $node Throw node
     *
     * @return void
     */
    private function analyzeThrow(Throw_ $node): void
    {
        $exceptionType = $this->getExceptionType($node->expr);

        if ($exceptionType === null) {
            return;
        }

        $this->actuallyThrown[] = $exceptionType;

        $isDocumented = false;

        foreach ($this->declaredThrows as $declared) {
            if ($this->isExceptionMatching($exceptionType, $declared)) {
                $isDocumented = true;

                break;
            }
        }

        if ($isDocumented) {
            return;
        }

        $this->errors[] = [
            'line' => $node->getStartLine(),
            'type' => 'undeclared_throw',
            'exception' => $exceptionType,
            'function' => $this->currentFunction,
            'message' => "Exception '{$exceptionType}' is thrown but not declared in @throws tag",
        ];
    }

    /**
     * Analyze a method call
     *
     * @param MethodCall $node Method call node
     *
     * @return void
     */
    private function analyzeMethodCall(MethodCall $node): void
    {
        if (!$node->name instanceof Identifier) {
            return;
        }

        $methodName = $node->name->toString();

        $calledClass = null;

        if ($node->var instanceof Variable && $node->var->name === 'this') {
            $calledClass = $this->currentClass;
        } elseif ($node->var instanceof PropertyFetch) {
            $calledClass = $this->resolvePropertyType($node->var);
        }

        if ($calledClass === null) {
            return;
        }

        $this->checkMethodCallThrows(
            $node,
            $calledClass,
            $methodName,
            "{$methodName}()"
        );
    }

    /**
     * Analyze a static method call
     *
     * @param StaticCall $node Static call node
     *
     * @return void
     */
    private function analyzeStaticCall(StaticCall $node): void
    {
        if (!$node->name instanceof Identifier) {
            return;
        }

        $methodName = $node->name->toString();

        $calledClass = null;

        if ($node->class instanceof Name) {
            $className = $node->class->toString();

            if (in_array($className, ['self', 'static'], true)) {
                $calledClass = $this->currentClass;
            } elseif ($className === 'parent') {
                return;
            } elseif ($node->class->isFullyQualified()) {
                $calledClass = $className;
            } elseif (isset($this->useImports[$className])) {
                $calledClass = $this->useImports[$className];
            } else {
                $calledClass = $this->currentNamespace !== null
                    ? "{$this->currentNamespace}\\{$className}"
                    : $className;
            }
        }

        if ($calledClass === null) {
            return;
        }

        $this->checkMethodCallThrows(
            $node,
            $calledClass,
            $methodName,
            "{$calledClass}::{$methodName}()"
        );
    }

    /**
     * Check method call for undocumented throws
     *
     * @param Node   $node                   Method or static call node
     * @param string $calledClass            Called class name
     * @param string $methodName             Called method name
     * @param string $displayMethodSignature Method signature for error message
     *
     * @return void
     */
    private function checkMethodCallThrows(
        Node $node,
        string $calledClass,
        string $methodName,
        string $displayMethodSignature,
    ): void {
        $methodSignature = "{$calledClass}::{$methodName}";

        $calledMethodThrows = $this->methodThrows[$methodSignature] ?? null;

        if ($calledMethodThrows === null) {
            $calledMethodThrows = $this->globalMethodThrows[$methodSignature] ?? null;
        }

        if (in_array($calledMethodThrows, [null, []], true)) {
            return;
        }

        foreach ($calledMethodThrows as $thrownException) {
            $isDocumented = false;

            foreach ($this->declaredThrows as $declared) {
                if ($this->isExceptionMatching($thrownException, $declared)) {
                    $isDocumented = true;

                    break;
                }
            }

            $this->actuallyThrown[] = $thrownException;

            if ($isDocumented) {
                continue;
            }

            $this->errors[] = [
                'line' => $node->getStartLine(),
                'type' => 'undeclared_throw_from_call',
                'exception' => $thrownException,
                'function' => $this->currentFunction,
                'called_method' => $methodName,
                'called_class' => $calledClass,
                'message' => "Exception '{$thrownException}' can be thrown by '{$displayMethodSignature}'"
                    . ' but is not declared in @throws tag',
            ];
        }
    }

    /**
     * Resolve property type from PropertyFetch node
     *
     * @param PropertyFetch $node Property fetch node
     *
     * @return string|null Resolved class name or null
     */
    private function resolvePropertyType(PropertyFetch $node): ?string
    {
        if (!$node->var instanceof Variable || $node->var->name !== 'this') {
            return null;
        }

        if (!$node->name instanceof Identifier) {
            return null;
        }

        $propertyName = $node->name->toString();

        return $this->propertyTypes[$propertyName] ?? null;
    }

    /**
     * Get exception type from throw expression
     *
     * @param Expr $expr Throw expression
     *
     * @return string|null Exception type or null if undeterminable
     */
    private function getExceptionType(Expr $expr): ?string
    {
        if ($expr instanceof New_ && $expr->class instanceof Name) {
            $name = $expr->class->toString();

            if ($expr->class->isFullyQualified()) {
                return '\\' . ltrim($name, '\\');
            }

            return $name;
        }

        if ($expr instanceof Variable) {
            return null;
        }

        return null;
    }

    /**
     * Check if thrown exception matches declared exception
     *
     * @param string $thrown   Thrown exception type
     * @param string $declared Declared exception type
     *
     * @return bool True if matches, false otherwise
     */
    private function isExceptionMatching(string $thrown, string $declared): bool
    {
        if ($thrown === $declared) {
            return true;
        }

        $thrownShort = mb_substr($thrown, mb_strrpos($thrown, '\\') + 1);
        $declaredShort = mb_substr($declared, mb_strrpos($declared, '\\') + 1);

        if ($thrownShort === $declaredShort) {
            return true;
        }

        $thrownNormalized = '\\' . ltrim($thrown, '\\');
        $declaredNormalized = '\\' . ltrim($declared, '\\');

        return $thrownNormalized === $declaredNormalized;
    }
}

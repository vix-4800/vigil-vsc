<?php

declare(strict_types=1);

namespace Test;

use RuntimeException;

class ClassUsingTrait
{
    use TestTrait;

    /**
     * Method that calls a trait method which throws an exception
     * This should produce an error because RuntimeException is not documented here
     */
    public function callTraitMethod(): void
    {
        $this->throwingMethod(); // Should detect RuntimeException from trait
    }

    /**
     * Method that calls a trait method and documents the exception
     * This should NOT produce an error
     *
     * @throws RuntimeException
     */
    public function callTraitMethodDocumented(): void
    {
        $this->throwingMethod(); // Should be OK
    }

    /**
     * Method that calls another trait method
     * This should produce an error
     */
    public function callUndocumentedTraitCaller(): void
    {
        $this->undocumentedCallerMethod(); // Should detect RuntimeException
    }
}

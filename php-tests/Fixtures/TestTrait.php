<?php

declare(strict_types=1);

namespace Test;

use Exception;
use RuntimeException;

trait TestTrait
{
    /**
     * Method that throws an exception
     *
     * @throws RuntimeException
     */
    protected function throwingMethod(): void
    {
        throw new RuntimeException('Error');
    }

    /**
     * Method that calls throwingMethod and should document the exception
     *
     * @throws RuntimeException
     */
    protected function callerMethod(): void
    {
        $this->throwingMethod();
    }

    /**
     * Method that calls throwingMethod but doesn't document the exception
     * This should produce an error
     */
    protected function undocumentedCallerMethod(): void
    {
        $this->throwingMethod();
    }
}

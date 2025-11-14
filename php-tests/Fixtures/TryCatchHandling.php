<?php

namespace Vix\ExceptionInspector\Tests\Fixtures;

use Exception;
use RuntimeException;
use InvalidArgumentException;

class TryCatchHandling
{
    /**
     * Exception is caught - should NOT require @throws
     */
    public function exceptionIsCaught(): void
    {
        try {
            throw new RuntimeException('Error');
        } catch (RuntimeException $e) {
            // Handle exception
        }
    }

    /**
     * @throws Exception
     */
    public function exceptionNotCaught(): void
    {
        try {
            throw new RuntimeException('Error');
        } catch (InvalidArgumentException $e) {
            // Different exception type - RuntimeException is not caught
        }
    }

    /**
     * Multiple catch blocks
     */
    public function multipleCatchBlocks(): void
    {
        try {
            if (rand(0, 1)) {
                throw new RuntimeException('Runtime error');
            } else {
                throw new InvalidArgumentException('Invalid argument');
            }
        } catch (RuntimeException $e) {
            // Handle RuntimeException
        } catch (InvalidArgumentException $e) {
            // Handle InvalidArgumentException
        }
    }

    /**
     * @throws RuntimeException
     */
    public function partialCatch(): void
    {
        try {
            throw new RuntimeException('Error 1');
        } catch (InvalidArgumentException $e) {
            // RuntimeException is not caught
        }

        // This one is outside try-catch
        throw new RuntimeException('Error 2');
    }

    /**
     * Nested try-catch
     */
    public function nestedTryCatch(): void
    {
        try {
            try {
                throw new RuntimeException('Inner error');
            } catch (RuntimeException $e) {
                // Caught in inner try-catch
            }

            // This is still in outer try-catch but not caught
            throw new InvalidArgumentException('Outer error');
        } catch (InvalidArgumentException $e) {
            // Now caught in outer catch
        }
    }

    /**
     * @throws Exception
     */
    public function catchParentException(): void
    {
        try {
            throw new RuntimeException('Error');
        } catch (Exception $e) {
            // RuntimeException extends Exception, so it's caught
        }
    }

    /**
     * Method call that throws - caught
     */
    public function methodCallCaught(): void
    {
        try {
            $this->throwsRuntimeException();
        } catch (RuntimeException $e) {
            // Exception from method call is caught
        }
    }

    /**
     * @throws RuntimeException
     */
    public function methodCallNotCaught(): void
    {
        try {
            $this->throwsRuntimeException();
        } catch (InvalidArgumentException $e) {
            // RuntimeException is not caught
        }
    }

    /**
     * @throws RuntimeException
     */
    private function throwsRuntimeException(): void
    {
        throw new RuntimeException('Error');
    }
}

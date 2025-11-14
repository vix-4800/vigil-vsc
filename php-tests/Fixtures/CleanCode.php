<?php

declare(strict_types=1);

namespace Fixtures;

class CleanCode
{
    /**
     * Method with properly documented exception.
     *
     * @throws \InvalidArgumentException When value is empty
     */
    public function validMethod(string $value): void
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Value cannot be empty');
        }
    }

    /**
     * Method with multiple documented exceptions.
     *
     * @throws \InvalidArgumentException When value is invalid
     * @throws \RuntimeException When processing fails
     */
    public function multipleExceptions(string $value): void
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Value cannot be empty');
        }

        if ($value === 'fail') {
            throw new \RuntimeException('Processing failed');
        }
    }

    /**
     * Method that doesn't throw anything.
     */
    public function noExceptions(): void
    {
        // Just a regular method
    }
}

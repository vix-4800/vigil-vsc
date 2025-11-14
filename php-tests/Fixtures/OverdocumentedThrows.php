<?php

declare(strict_types=1);

namespace Fixtures;

use InvalidArgumentException;
use RuntimeException;
use LogicException;

class OverdocumentedThrows
{
    /**
     * Method with documented exception that is never thrown
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException This is documented but never thrown
     */
    public function overdocumentedMethod(string $value): void
    {
        if ($value === '') {
            throw new InvalidArgumentException('Value cannot be empty');
        }
        // RuntimeException is documented but never thrown
    }

    /**
     * Method with multiple unnecessary @throws
     *
     * @throws LogicException Never thrown
     * @throws RuntimeException Never thrown
     * @throws InvalidArgumentException Never thrown
     */
    public function allUnnecessary(): void
    {
        // No exceptions thrown at all
    }

    /**
     * Method with correct documentation
     *
     * @throws InvalidArgumentException
     */
    public function correctlyDocumented(string $value): void
    {
        if ($value === '') {
            throw new InvalidArgumentException('Value cannot be empty');
        }
    }
}

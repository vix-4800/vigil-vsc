<?php

declare(strict_types=1);

namespace Fixtures;

class UndocumentedThrows
{
    /**
     * Method missing @throws tag.
     */
    public function missingThrows(string $value): void
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Value cannot be empty');
        }
    }

    /**
     * Method with no docblock at all.
     */
    public function noDocblock(): void
    {
        throw new \RuntimeException('Error');
    }
}

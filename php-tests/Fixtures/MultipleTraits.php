<?php

declare(strict_types=1);

namespace Test;

use InvalidArgumentException;
use LogicException;

trait ValidationTrait
{
    /**
     * Validates input
     *
     * @throws InvalidArgumentException
     */
    protected function validate(string $input): void
    {
        if (empty($input)) {
            throw new InvalidArgumentException('Input cannot be empty');
        }
    }
}

trait LoggingTrait
{
    /**
     * Logs a message
     *
     * @throws LogicException
     */
    protected function log(string $message): void
    {
        if (strlen($message) > 1000) {
            throw new LogicException('Message too long');
        }
    }
}

final class ServiceWithMultipleTraits
{
    use ValidationTrait;
    use LoggingTrait;

    /**
     * Process data - missing @throws for InvalidArgumentException
     */
    public function processData(string $data): void
    {
        $this->validate($data); // Should detect missing InvalidArgumentException
    }

    /**
     * Process and log - properly documented
     *
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function processAndLog(string $data): void
    {
        $this->validate($data);
        $this->log("Processing: {$data}");
    }

    /**
     * Just log - missing @throws for LogicException
     */
    public function justLog(string $message): void
    {
        $this->log($message); // Should detect missing LogicException
    }
}

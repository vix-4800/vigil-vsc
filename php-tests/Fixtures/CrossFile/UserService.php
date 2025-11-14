<?php

declare(strict_types=1);

namespace Fixtures\CrossFile;

use InvalidArgumentException;
use RuntimeException;

class UserService
{
    public function __construct(
        private readonly UserRepository $repository
    ) {
    }

    /**
     * Get user by ID
     *
     * This method SHOULD document InvalidArgumentException and RuntimeException
     * but doesn't - that's the error we want to detect
     *
     * @param int $id User ID
     *
     * @return array User data
     */
    public function getUser(int $id): array
    {
        // Calling method that throws exceptions
        return $this->repository->findById($id);
    }

    /**
     * Get user by ID with proper documentation
     *
     * @param int $id User ID
     *
     * @return array User data
     *
     * @throws InvalidArgumentException If ID is invalid
     * @throws RuntimeException If user not found
     */
    public function getUserProperly(int $id): array
    {
        return $this->repository->findById($id);
    }

    /**
     * Create user - missing @throws
     *
     * @param string $name User name
     *
     * @return void
     */
    public function createUser(string $name): void
    {
        $this->repository->save(['name' => $name]);
    }

    /**
     * Create user - with proper documentation
     *
     * @param string $name User name
     *
     * @return void
     *
     * @throws InvalidArgumentException If name is invalid
     */
    public function createUserProperly(string $name): void
    {
        $this->repository->save(['name' => $name]);
    }
}

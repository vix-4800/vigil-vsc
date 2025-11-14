<?php

declare(strict_types=1);

namespace Fixtures\CrossFile;

use InvalidArgumentException;
use RuntimeException;

class UserRepository
{
    /**
     * Find user by ID
     *
     * @param int $id User ID
     *
     * @return array User data
     *
     * @throws InvalidArgumentException If ID is invalid
     * @throws RuntimeException If user not found
     */
    public function findById(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid user ID');
        }

        if ($id === 999) {
            throw new RuntimeException('User not found');
        }

        return ['id' => $id, 'name' => 'Test User'];
    }

    /**
     * Save user
     *
     * @param array $data User data
     *
     * @return void
     *
     * @throws InvalidArgumentException If data is invalid
     */
    public function save(array $data): void
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Name is required');
        }
    }
}

<?php

namespace Vix\ExceptionInspector\Tests\Fixtures;

use yii\base\InvalidConfigException;

/**
 * Mock ActiveRecord class that simulates Yii2 behavior
 */
class MockActiveRecord
{
    /**
     * Returns a query object that can be used to retrieve records.
     *
     * @return MockQuery
     *
     * @throws InvalidConfigException
     */
    public static function find(): MockQuery
    {
        // This method throws InvalidConfigException according to its docs
        return new MockQuery();
    }
}

/**
 * Mock Query class
 */
class MockQuery
{
    /**
     * Executes the query and returns all results as an array.
     *
     * @return array
     */
    public function all(): array
    {
        return [];
    }
}

/**
 * Service that uses static method calls
 */
class ServiceWithStaticCalls
{
    /**
     * Get all categories using static call to find()
     *
     * This method correctly documents InvalidConfigException
     * because find() can throw it according to its @throws
     *
     * @return array
     *
     * @throws InvalidConfigException
     */
    public function getCategories(): array
    {
        return MockActiveRecord::find()->all();
    }

    /**
     * Get all categories but MISSING @throws documentation
     *
     * This should trigger an error because find() throws InvalidConfigException
     *
     * @return array
     */
    public function getCategoriesWithoutThrows(): array
    {
        return MockActiveRecord::find()->all();
    }

    /**
     * Method using self static call
     *
     * @return string
     *
     * @throws InvalidConfigException
     */
    public static function testSelfCall(): string
    {
        return self::helperMethod();
    }

    /**
     * Helper method that throws exception
     *
     * @return string
     *
     * @throws InvalidConfigException
     */
    private static function helperMethod(): string
    {
        throw new InvalidConfigException('Test');
    }
}

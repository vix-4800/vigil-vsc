<?php

namespace backend\services\Menu;

use yii\base\InvalidConfigException;

/**
 * Mock Yii2 ActiveRecord class
 */
class MenuCategory
{
    /**
     * {@inheritdoc}
     *
     * @return CacheActiveQuery
     *
     * @throws InvalidConfigException
     */
    public static function find(): CacheActiveQuery
    {
        return new CacheActiveQuery();
    }
}

/**
 * Mock CacheActiveQuery class
 */
class CacheActiveQuery
{
    /**
     * @return MenuCategory[]
     */
    public function all(): array
    {
        return [];
    }
}

/**
 * MenuService from real Yii2 project
 */
class MenuService
{
    /**
     * Get menu categories
     *
     * This method calls MenuCategory::find() which declares @throws InvalidConfigException
     * Previously Inspector would report this as missing, but now it should recognize it
     *
     * @return array
     *
     * @throws InvalidConfigException
     */
    public function getCategories(): array
    {
        $categories = MenuCategory::find()->all();

        return array_map(function($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
            ];
        }, $categories);
    }

    /**
     * Get menu categories WITHOUT proper @throws documentation
     *
     * This SHOULD trigger an error because find() can throw InvalidConfigException
     *
     * @return array
     */
    public function getCategoriesWrong(): array
    {
        $categories = MenuCategory::find()->all();

        return array_map(function($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
            ];
        }, $categories);
    }
}

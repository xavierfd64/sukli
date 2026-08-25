<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Admin-manageable list of expense categories, backing the Expenses
 * form's Category dropdown. expense_records.category stays a free-text
 * column for zero-risk compatibility with existing reports/grouping
 * queries — this table is just the curated list of allowed values.
 */
class ExpenseCategoryService
{
    private const DEFAULTS = ['Restock / Supplies', 'Utilities', 'Rent', 'Transportation', 'Salary', 'Other'];

    /** @return array Rows {id, name} for the management screen. */
    public static function all(int $storeId): array
    {
        return Database::all("SELECT id, name FROM expense_categories WHERE store_id = ? ORDER BY name", [$storeId]);
    }

    /** @return string[] Category names for the Expenses form dropdown. */
    public static function names(int $storeId): array
    {
        $names = array_column(self::all($storeId), 'name');
        return $names ?: self::DEFAULTS;
    }

    public static function create(int $storeId, string $name): void
    {
        Database::execute(
            "INSERT INTO expense_categories (store_id, name) VALUES (?, ?)",
            [$storeId, $name]
        );
    }

    public static function delete(int $storeId, int $id): void
    {
        Database::execute("DELETE FROM expense_categories WHERE id = ? AND store_id = ?", [$id, $storeId]);
    }
}

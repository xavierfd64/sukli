<?php

declare(strict_types=1);

namespace Sukli\Services;

/**
 * Shared "search by first name / last name / full name / contact number"
 * behavior used everywhere a customer combobox appears: POS (Utang),
 * E-Load, GCash, the Utang module, Customers, and Reports.
 */
class CustomerSearchService
{
    public static function whereFragment(string $alias = 'c'): string
    {
        $prefix = $alias === '' ? '' : "{$alias}.";
        return "({$prefix}first_name LIKE ? OR {$prefix}last_name LIKE ? OR CONCAT({$prefix}first_name, ' ', COALESCE({$prefix}last_name, '')) LIKE ? OR {$prefix}contact_number LIKE ?)";
    }

    /** @return string[] */
    public static function params(string $term): array
    {
        $like = "%{$term}%";
        return [$like, $like, $like, $like];
    }

    public static function fullName(array $customer): string
    {
        return trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    }
}

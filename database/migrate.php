<?php
/**
 * CLI helper to (re)build the database from schema.sql, optionally seeding
 * demo data. Most Z.com shared-hosting accounts only offer phpMyAdmin, so
 * schema.sql / seed.sql can also be imported there directly — this script
 * is a convenience for local development and hosts that do offer SSH+PHP CLI.
 *
 * Usage:
 *   php database/migrate.php            # apply schema.sql only
 *   php database/migrate.php --seed      # apply schema.sql then seed.sql
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script may only be run from the command line.');
}

require __DIR__ . '/../app/Core/Env.php';

Sukli\Core\Env::load(__DIR__ . '/../.env');

$host = Sukli\Core\Env::get('DB_HOST', '127.0.0.1');
$port = Sukli\Core\Env::get('DB_PORT', '3306');
$database = Sukli\Core\Env::get('DB_DATABASE', 'sukli');
$username = Sukli\Core\Env::get('DB_USERNAME', 'root');
$password = Sukli\Core\Env::get('DB_PASSWORD', '');

$dsn = "mysql:host={$host};port={$port};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $database . '`');
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

function runSqlFile(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        fwrite(STDERR, "Missing SQL file: {$path}\n");
        exit(1);
    }

    $sql = file_get_contents($path);
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
        $statement = trim($statement);
        if ($statement === '' || str_starts_with($statement, '--')) {
            continue;
        }
        $pdo->exec($statement);
    }
    echo "Applied " . basename($path) . "\n";
}

runSqlFile($pdo, __DIR__ . '/schema.sql');

if (in_array('--seed', $argv, true)) {
    runSqlFile($pdo, __DIR__ . '/seed.sql');
}

echo "Done.\n";

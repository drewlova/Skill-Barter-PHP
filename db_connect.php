<?php
/**
 * Database connection for SkillSwap (XAMPP / MySQL)
 *
 * Default XAMPP MySQL credentials are host=localhost, user=root, password=''.
 * Adjust below if you've changed your XAMPP MySQL settings.
 *
 * Include this at the top of backend.php with:
 *   require_once 'db_connect.php';
 * It provides a ready-to-use PDO instance in $pdo.
 */

$db_host = 'localhost';
$db_name = 'skillswap';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // In production you'd log this instead of exposing it directly.
    die('Database connection failed: ' . $e->getMessage());
}

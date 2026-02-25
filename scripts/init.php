<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Connect to DB
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'],
    $_ENV['DB_PORT'],
    $_ENV['DB_DATABASE']
);
$pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "Connected to database {$_ENV['DB_DATABASE']}.\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `$table`");
    echo "Dropped table: $table\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");


// DROP EVERYTHING 
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `$table`");
    echo "Dropped table: $table\n";
}

// Use init.sql to reinitialize
$schema = file_get_contents(__DIR__ . '/../sql/init.sql');
$pdo->exec($schema);
echo "Database schema initialized.\n";

// === Creating Admin Account ===

// Make sure we don't forget admin_users
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  totp_secret VARCHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// This is your default admin account creds
$adminUsername = 'admin';
$adminPassword = 'admin123';
$adminSecret   = \ParagonIE\ConstantTime\Base32::encodeUpper(random_bytes(10)); // Keep this untouched

// Register Admin Account
$hash = password_hash($adminPassword, PASSWORD_ARGON2ID);
$stmt = $pdo->prepare("INSERT INTO admin_users (username,password_hash,totp_secret) VALUES (?,?,?)");
$stmt->execute([$adminUsername, $hash, $adminSecret]);

// Echo admin creds
echo "Admin user created:\n";
echo "  Username: {$adminUsername}\n";
echo "  Password: {$adminPassword}\n";
echo "  TOTP Secret (add to Google Authenticator): {$adminSecret}\n";

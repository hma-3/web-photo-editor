<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function parseDsnValue(string $dsn, string $key): ?string
{
    if (preg_match('/' . preg_quote($key, '/') . '=([^;]+)/', $dsn, $m)) {
        return $m[1];
    }
    return null;
}

$host = parseDsnValue($DB_DSN, 'host') ?? '127.0.0.1';
$dbName = parseDsnValue($DB_DSN, 'dbname');

if (!$dbName) {
    exit("DB name is missing in config/database.php\n");
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
    exit("Unsafe database name\n");
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $serverPdo = new PDO("mysql:host={$host};charset=utf8mb4", $DB_USER, $DB_PASSWORD, $options);
    $serverPdo->exec(
        "CREATE DATABASE IF NOT EXISTS `{$dbName}`
         CHARACTER SET utf8mb4
         COLLATE utf8mb4_unicode_ci"
    );

    $pdo = new PDO($DB_DSN, $DB_USER, $DB_PASSWORD, $options);

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (
        [
            'comments',
            'likes',
            'images',
            'email_verification_tokens',
            'password_reset_tokens',
            'users',
        ] as $table
    ) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            notify_comments TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_verification_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL UNIQUE,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (token_hash),
            CONSTRAINT fk_email_tokens_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL UNIQUE,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (token_hash),
            CONSTRAINT fk_reset_tokens_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            original_path VARCHAR(255) NOT NULL,
            overlay_path VARCHAR(255) NOT NULL,
            final_path VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (created_at),
            CONSTRAINT fk_images_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS likes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            image_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_like (user_id, image_id),
            INDEX (image_id),
            CONSTRAINT fk_likes_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_likes_image
                FOREIGN KEY (image_id) REFERENCES images(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            image_id INT UNSIGNED NOT NULL,
            content VARCHAR(500) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (image_id),
            CONSTRAINT fk_comments_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_comments_image
                FOREIGN KEY (image_id) REFERENCES images(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Database created successfully.\n";
} catch (Throwable $e) {
    echo "Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

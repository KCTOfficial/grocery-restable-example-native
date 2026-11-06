<?php

declare(strict_types=1);

/**
 * Demo: enable the Grocery Restable user login (password) on a "users" table.
 *
 *   php bin/setup-user-login.php
 *
 * Creates the table when missing, adds demo users (passwords hashed by the library) and applies the same settings
 * as Settings > User login in the admin GUI. Safe to run again. Demo only: never ship these passwords.
 */

use GroceryRestable\GroceryRestable;

require __DIR__ . '/../vendor/autoload.php';

$api = GroceryRestable::create(require __DIR__ . '/../config/grocery-restable.php');
$db = $api->connections()->get();
if ($db->driver() !== 'mysql') {
    fwrite(STDERR, "This demo script expects the MySQL connection of the demo.\n");
    exit(1);
}

$db->execute(<<<'SQL'
    CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(191) NOT NULL UNIQUE,
        username VARCHAR(50) NULL UNIQUE,
        password VARCHAR(255) NULL,
        name VARCHAR(100) NULL,
        avatar VARCHAR(255) NULL,
        role VARCHAR(30) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        google_id VARCHAR(64) NULL UNIQUE,
        created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    SQL);

// email, username, password, name, role (NULL = jwt_default_role "authenticated"), active
$users = [
    ['demo@example.com', 'demo', 'demo12345', 'Demo User', null, 1],
    ['editor@example.com', 'editor', 'editor12345', 'Eddie Editor', 'editor', 1],
    ['disabled@example.com', 'disabled', 'disabled12345', 'Disabled User', null, 0],
];
foreach ($users as [$email, $username, $password, $name, $role, $active]) {
    if ($db->select('SELECT id FROM users WHERE email = ?', [$email]) !== []) {
        continue;
    }
    $db->execute(
        'INSERT INTO users (email, username, password, name, role, active) VALUES (?, ?, ?, ?, ?, ?)',
        [$email, $username, $api->auth()->hashPassword($password), $name, $role, $active],
    );
    echo "Created {$email} / {$password}\n";
}

$store = $api->store();
$settings = [
    // user tokens must be accepted ("none" would make every request service_role)
    'auth_mode' => 'any',
    'auth_password_enabled' => true,
    // demo roles: "authenticated" (demo user) reads, "editor" (role column) writes
    'default_permissions' => ['anon' => [], 'authenticated' => ['select'], 'editor' => ['select', 'insert', 'update', 'delete']],
    'auth_users_table' => 'users',
    'auth_users_id_column' => 'id',
    'auth_users_identity_columns' => ['email', 'username'],
    'auth_users_password_column' => 'password',
    'auth_users_role_column' => 'role',
    'auth_users_active_column' => 'active',
    'auth_token_strategy' => 'jwt',
    // Google sign-in: add your OAuth web client id in the GUI (Settings > User login > Google) and enable it.
    'auth_google_id_column' => 'google_id',
    'auth_google_email_column' => 'email',
    'auth_google_signup_fields' => ['name' => 'name', 'avatar' => 'picture'],
];
if ((string) $store->setting('jwt_secret', '') === '') {
    $settings['jwt_secret'] = bin2hex(random_bytes(32));
}
$store->saveSettings($settings);
$api->schema()->refresh();

echo "User login enabled: POST /api/auth/login, try it on /sdk-demo.html\n";

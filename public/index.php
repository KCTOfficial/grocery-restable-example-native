<?php

declare(strict_types=1);

use GroceryRestable\Auth\AuthContext;
use GroceryRestable\GroceryRestable;
use GroceryRestable\Http\NativeServer;
use GroceryRestable\Resource\Conditions;

require __DIR__ . '/../vendor/autoload.php';

$api = GroceryRestable::create(require __DIR__ . '/../config/grocery-restable.php');

// Optional: resources in code (take precedence over the GUI and auto expose).
// $api->resource('employees')->setRelation('officeCode', 'offices', '{city} ({country})');
// $api->resource('notes')->setIdGenerator('uuid7');   // UUID v7 ids for new rows
// $api->resource('orders')->setRateLimit(120, 60);    // own rate limit (global: GUI > Settings > Rate limiting)

// User login demo (php bin/setup-user-login.php): POST /api/auth/login with demo@example.com / demo12345.
// The users table is defined in code so auto expose never publishes it: every signed in user only sees and
// edits their own row, the password is hashed on write and never returned, role / active cannot be changed.
$api->resource('users')
    ->setSubject('User')
    ->setPermissions('authenticated', ['select', 'update'])
    ->setPermissions('editor', ['select', 'update'])
    ->scope(fn (Conditions $q, AuthContext $auth) => $q->where('id', (int) $auth->userId()))
    ->fieldType('password', 'password')
    ->fieldType('role', 'readonly')
    ->fieldType('active', 'readonly')
    ->fieldType('google_id', 'readonly')
    ->fieldType('created_at', 'readonly')
    ->unsetDelete();

// Customize the login flow, e.g. extra token claims:
// $api->auth()->claims(fn (array $user) => ['name' => $user['name']]);

// The JavaScript SDK is served at /api/sdk.js; try it on /sdk-demo.html

if (! NativeServer::serve($api)) {
    if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/') {
        header('Location: /grocery-restable');
    } else {
        http_response_code(404);
    }
}

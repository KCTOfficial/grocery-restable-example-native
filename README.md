# Grocery Restable example: plain PHP (no framework)

This is an example project. It shows how to use the
[Grocery Restable](https://github.com/KCTOfficial/grocery-restable) library in plain PHP, without a framework such as
CodeIgniter or Laravel. Feel free to run it, read it and change it. It is set up for learning, so read
[Before going to production](#11-before-going-to-production) before you reuse any of it on a real server.

Looking for the CodeIgniter 4 version? See the [`grocery-restable-ci4`](https://github.com/KCTOfficial/grocery-restable-example-codeigniter4) project.

## Contents

1. [What is Grocery Restable?](#1-what-is-grocery-restable)
2. [What is in this example?](#2-what-is-in-this-example)
3. [Requirements](#3-requirements)
4. [Installation](#4-installation)
5. [Setup wizard](#5-setup-wizard)
6. [Trying the REST API](#6-trying-the-rest-api)
7. [Trying the JavaScript SDK](#7-trying-the-javascript-sdk)
8. [User login demo](#8-user-login-demo)
9. [Defining endpoints in code](#9-defining-endpoints-in-code)
10. [Cleaning up unused files](#10-cleaning-up-unused-files)
11. [Before going to production](#11-before-going-to-production)
12. [Troubleshooting](#12-troubleshooting)
13. [Further reading](#13-further-reading)

## 1. What is Grocery Restable?

Grocery Restable turns a database (MySQL / MariaDB, PostgreSQL or SQLite) into a REST API, and gives you an admin
interface in the style of phpMyAdmin to manage it.

For example, if your database has an `employees` table, once Grocery Restable is installed you can call:

```
GET  /api/employees            list employees
GET  /api/employees/1002       read one employee
POST /api/employees            add an employee
```

You do not write controllers, models or SQL queries for each table.

## 2. What is in this example?

Once it is running you get:

| URL | What it is |
|---|---|
| `/grocery-restable` | The admin GUI: database, endpoints, API keys, users and settings |
| `/api` | The REST API for your tables |
| `/api/openapi.json` | An OpenAPI 3.1 description of the API (works with Swagger UI or Postman) |
| `/api/sdk.js` | The JavaScript SDK, ready to `import` in a browser |
| `/sdk-demo.html` | A sample page that uses the SDK (sign in, browse, search, paging) |
| `/` | Redirects to `/grocery-restable` |

Folder layout:

```
grocery-restable-native/
  bin/
    cleanup.php              removes unused uploaded files (run it from cron)
    setup-user-login.php     creates a users table with 3 demo users and turns on user login
  config/
    grocery-restable.php     bootstrap settings (storage path, URLs, encryption key)
  public/                    the only folder the web server should expose
    .htaccess                sends every request to index.php (Apache)
    index.php                front controller
    sdk-demo.html            JavaScript SDK sample page
  writable/                  created at runtime, holds Grocery Restable data (never make it public)
    grocery-restable/
      grocery-restable.sqlite    everything you configure in the GUI is stored here
      logs/                      API request logs, one .jsonl file per day
  composer.json
```

Settings made in the admin GUI are stored in the SQLite file
`writable/grocery-restable/grocery-restable.sqlite`, not in your own database. Your database (for example
`classicmodels`) only holds your data.

## 3. Requirements

- PHP 8.2 or newer with the `pdo`, `pdo_sqlite`, `sodium`, `fileinfo` and `json` extensions
- A database driver: `pdo_mysql` or `mysqli` for MySQL / MariaDB, `pdo_pgsql` for PostgreSQL
- [Composer](https://getcomposer.org)
- A database. The examples below use MySQL with the `classicmodels` sample database (see step 4.3)
- A web server. On Windows, [Laragon](https://laragon.org) is the easiest option. Apache, nginx or PHP's built-in
  server also work

Check your PHP version and extensions:

```bash
php -v
php -m
```

`pdo_sqlite`, `sodium` and `fileinfo` must appear in the `php -m` output. If one is missing, enable it in `php.ini`
(in Laragon: right click the tray icon, then PHP > Extensions).

## 4. Installation

The steps assume Laragon on Windows. Notes for other setups are included where they differ.

### 4.1 Put the project in place

Copy the folder to `C:\laragon\www\grocery-restable-native`, or to the web root of your own server.

### 4.2 Install the library

Open a terminal in the project folder and run:

```bash
cd C:\laragon\www\grocery-restable-native
composer install
```

`composer.json` contains a `"repositories"` block that points to a local copy of the library on the developer's
machine (`C:/Users/.../grocery-restable`). If that folder does not exist on your machine, `composer install` fails.
In that case, delete the whole `"repositories": { ... }` block from `composer.json` and run:

```bash
composer update
```

Composer then downloads the library from [Packagist](https://packagist.org/packages/kctofficial/grocery-restable).
When it finishes you have a `vendor/` folder.

### 4.3 Create the sample database

The examples use `classicmodels`, a free sample database of a scale model car shop (`offices`, `employees`,
`customers`, `orders`, `products` and more).

1. Download it from
   [mysqltutorial.org](https://www.mysqltutorial.org/getting-started-with-mysql/mysql-sample-database/) and unzip
   it. You get `mysqlsampledatabase.sql`.
2. Start MySQL (in Laragon: Start All).
3. Import the file, either:
   - with HeidiSQL (comes with Laragon): click Database, then File > Run SQL file... and pick the file, or
   - from a terminal:

     ```bash
     mysql -u root < mysqlsampledatabase.sql
     ```

The script creates a database named `classicmodels`.

You can use your own database instead. Just replace the table names in the examples.

### 4.4 Review the configuration

Open `config/grocery-restable.php`:

```php
return [
    'framework' => 'standalone',
    'storagePath' => __DIR__ . '/../writable/grocery-restable',
    'apiUrl' => '/api',
    'adminUrl' => '/grocery-restable',
    'debug' => true,
    'encryptionKey' => 'base64:....',
];
```

| Option | Meaning |
|---|---|
| `framework` | Keep `standalone` for plain PHP |
| `storagePath` | Where Grocery Restable keeps its SQLite file, logs and uploads. It must be outside `public/` |
| `apiUrl` | URL prefix of the API. `/api` gives `http://your-site/api/...` |
| `adminUrl` | URL prefix of the admin GUI |
| `debug` | `true` adds error details to API responses. Set it to `false` in production |
| `encryptionKey` | Key used to encrypt secrets stored in SQLite, such as database passwords |

The key in this file is public, since it is part of the example. Generate your own before you run the setup wizard:

```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

Paste the output into `encryptionKey`. Do not change the key after the setup: values that were encrypted with the
old key can no longer be read. Keep a copy of it somewhere safe.

### 4.5 Start the web server

Option A, Laragon (recommended):

1. Click Start All.
2. Laragon creates `http://grocery-restable-native.test` automatically and points it at the `public/` folder. If
   the address does not work yet, reload Apache from the Laragon menu or restart Laragon.
3. Open `http://grocery-restable-native.test` in your browser.

Option B, PHP's built-in server:

```bash
php -S 127.0.0.1:8080 -t public
```

Then open `http://127.0.0.1:8080`. This is fine for a quick try, but it handles one request at a time, so the admin
GUI is slower.

Option C, your own Apache or nginx: set the document root to `public/`, not to the project folder. For Apache,
`public/.htaccess` is already there (`mod_rewrite` must be enabled). For nginx:

```nginx
location / {
    try_files $uri /index.php$is_args$args;
}
```

The rest of this README uses `http://grocery-restable-native.test`. With option B, use `http://127.0.0.1:8080`
instead.

## 5. Setup wizard

The first time you open the site, you are sent to the setup wizard. It has five steps:

| Step | What to enter |
|---|---|
| 1. Framework | No framework (native PHP) |
| 2. Database | Engine: MySQL / MariaDB. Driver: PDO or MySQLi. Host `127.0.0.1`, port `3306`, database `classicmodels`, user `root`, empty password (Laragon defaults). Use Test connection to check it |
| 3. Admin account | A username (3 characters or more) and a password (8 characters or more) for the admin GUI |
| 4. API options | Application name, authentication mode (keep the default `api_key`) and auto expose. For this demo, turn auto expose on so every table becomes an endpoint |
| 5. Done | Two API keys are shown: `anon` and `service_role` |

Copy both keys at step 5. They are shown only once. If you lose them, create new ones in the admin GUI under
API > API keys.

| Key | Meant for | Access |
|---|---|---|
| `anon` | Public clients (websites, mobile apps) | Only what the permissions allow. Nothing by default |
| `service_role` | Your own trusted servers | Everything. Never put it in browser or mobile app code |

About the wizard:

- It only opens from the local machine (`127.0.0.1`). On a remote server, add `'setupToken' => 'some-secret'` to the
  config and open `/grocery-restable/setup?token=some-secret`.
- Once the setup is done, the wizard is closed for good. Everything else is changed in the admin GUI.

### The admin GUI at a glance

After signing in at `/grocery-restable`, the left rail has these areas:

| Area | Purpose |
|---|---|
| Overview | Summary, a getting started checklist, configuration warnings and code samples |
| Data | Works like phpMyAdmin: browse, search, add, edit and delete rows |
| API | Which tables are endpoints and how: fields, relations, permissions, uploads, API keys, docs and a console to try requests |
| Storage | Buckets and files, organized like S3 |
| Logs | API request and admin action logs |
| Settings | Account, authentication, user login, rate limiting, CORS, uploads, database connections, other admins |

API > Console lets you try requests from the browser without curl.

## 6. Trying the REST API

The examples use `curl`. On Windows, run them in Git Bash or the Laragon terminal (Cmder) so the quoting works as
written.

Store the base URL and your `service_role` key in variables first:

```bash
BASE=http://grocery-restable-native.test/api
KEY=gr_xxxxxxxxxxxxxxxx      # your service_role key
```

### 6.1 Reading

```bash
# First 5 employees
curl "$BASE/employees?limit=5" -H "apikey: $KEY"

# Only some columns
curl "$BASE/employees?select=employeeNumber,lastName,jobTitle&limit=5" -H "apikey: $KEY"

# One row by primary key
curl "$BASE/employees/1002" -H "apikey: $KEY"

# Include a related table (the office of each employee)
curl "$BASE/employees?select=lastName,offices(city,country)&limit=5" -H "apikey: $KEY"
```

Lists come back as a JSON array:

```json
[{ "employeeNumber": 1002, "lastName": "Murphy", "jobTitle": "President" }]
```

### 6.2 Filtering, sorting, paging and search

Filters use the form `column=operator.value`.

```bash
# Employees of office 1
curl "$BASE/employees?officeCode=eq.1" -H "apikey: $KEY"

# Products with a buy price of 50 or more, most expensive first
curl "$BASE/products?buyPrice=gte.50&order=buyPrice.desc&limit=10" -H "apikey: $KEY"

# Page 2, 10 rows per page
curl "$BASE/customers?page=2&per_page=10" -H "apikey: $KEY"

# Text search across text columns
curl "$BASE/customers?q=mini" -H "apikey: $KEY"

# Customers from several countries
curl "$BASE/customers?country=in.(France,Germany)" -H "apikey: $KEY"

# Download as CSV
curl "$BASE/customers?format=csv" -H "apikey: $KEY" -o customers.csv
```

Operators:

| Operator | Meaning | Example |
|---|---|---|
| `eq` | equal | `status=eq.Shipped` |
| `neq` | not equal | `status=neq.Cancelled` |
| `gt`, `gte` | greater than, greater or equal | `price=gte.10` |
| `lt`, `lte` | less than, less or equal | `price=lt.100` |
| `like`, `ilike` | pattern match (`*` is a wildcard), `ilike` ignores case | `name=ilike.*ford*` |
| `in` | one of a list | `id=in.(1,2,3)` |
| `is` | null, true or false | `deleted_at=is.null` |
| `not.` | negates the operator after it | `status=not.in.(Cancelled,Disputed)` |

### 6.3 Creating, updating and deleting

```bash
# Add an employee (employeeNumber is not auto increment in classicmodels, so it is sent explicitly)
curl -X POST "$BASE/employees" -H "apikey: $KEY" -H "Content-Type: application/json" \
     -d '{"employeeNumber":1800,"lastName":"Doe","firstName":"Jane","email":"jane@example.com","extension":"x1","officeCode":"1","jobTitle":"Sales Rep"}'

# Change some columns
curl -X PATCH "$BASE/employees/1800" -H "apikey: $KEY" -H "Content-Type: application/json" \
     -d '{"jobTitle":"Sales Manager"}'

# Delete
curl -X DELETE "$BASE/employees/1800" -H "apikey: $KEY"
```

In PowerShell, JSON quoting tends to break. Put the JSON in a file such as `body.json` and use `curl.exe` (not the
`curl` alias) with `-d "@body.json"`, or use Postman or API > Console in the admin GUI.

### 6.4 Common status codes

| Code | Meaning | What to check |
|---|---|---|
| `200`, `201` | Success | |
| `400` | Bad request (unknown column, malformed filter) | Column names and filter syntax |
| `401` | Invalid key or token | The key exists and was copied correctly |
| `403` | Not allowed | The role's permissions under API > (table) > Permissions |
| `404` | Unknown endpoint or row | The table is exposed and the id is correct |
| `422` | Validation failed | The response lists the invalid fields |
| `429` | Rate limit reached | Wait, or change Settings > Rate limiting |

The full list of endpoints available to you is at `http://grocery-restable-native.test/api/openapi.json`.

## 7. Trying the JavaScript SDK

Open `http://grocery-restable-native.test/sdk-demo.html`. The page loads the SDK straight from `/api/sdk.js`, with
no build step.

1. Enter an API key and click Connect. For this demo, create a key in the admin GUI under API > API keys with the
   role `authenticated` (that role can read data by default). Do not use the `service_role` key in a browser.
2. Pick a table in Endpoint and try Search, Prev and Next.
3. The panel on the right shows the SDK code for what you are doing.

Using the SDK in your own page:

```html
<script type="module">
    import GroceryRestable from 'http://grocery-restable-native.test/api/sdk.js';

    const gr = new GroceryRestable('http://grocery-restable-native.test/api', { apiKey: 'gr_xxx' });

    // Page 1, 10 rows, employees of office 1, sorted by last name descending
    const { items, totalItems } = await gr.collection('employees').getList(1, 10, {
        filter: { officeCode: '1' },
        sort: '-lastName',
    });
    console.log(totalItems, items);

    // await gr.collection('employees').create({ ... });
    // await gr.collection('employees').update(1800, { jobTitle: 'Sales Manager' });
    // await gr.collection('employees').delete(1800);
</script>
```

If your page runs on a different domain, allow that domain in Settings > CORS.

## 8. User login demo

Grocery Restable can sign users in against a users table in your own database. This example includes a script that
prepares everything. It works with MySQL only and needs a completed setup wizard.

```bash
php bin/setup-user-login.php
```

The script:

1. Creates a `users` table if it does not exist.
2. Adds three demo users (passwords are stored hashed):

   | Email | Username | Password | Role | Result |
   |---|---|---|---|---|
   | `demo@example.com` | `demo` | `demo12345` | `authenticated` | Can read data |
   | `editor@example.com` | `editor` | `editor12345` | `editor` | Can read, create, update and delete |
   | `disabled@example.com` | `disabled` | `disabled12345` | | Inactive, login is refused |

3. Turns on user login (the same as filling in Settings > User login in the GUI), switches the authentication mode
   to `any` (API keys and user tokens are both accepted) and sets the default permissions: `anon` gets nothing,
   `authenticated` can read, `editor` can do everything.

Running it again is safe: existing users are skipped.

### Signing in

In the browser: open `/sdk-demo.html`, enter `demo@example.com` / `demo12345` and click Sign in. Try `editor` and
`disabled` too and compare.

With curl:

```bash
curl -X POST "$BASE/auth/login" -H "Content-Type: application/json" \
     -d '{"identity":"demo@example.com","password":"demo12345"}'
```

The response contains an `access_token` (valid for 15 minutes by default) and a `refresh_token`, which gets a new
access token through `POST /api/auth/refresh`. The SDK refreshes automatically. Send the access token with later
requests:

```bash
TOKEN=eyJhbGciOi...      # the access_token from the login response

curl "$BASE/employees?limit=3" -H "Authorization: Bearer $TOKEN"
curl "$BASE/auth/me" -H "Authorization: Bearer $TOKEN"     # the signed in user
```

### How the users table is protected

The `users` endpoint is defined in `public/index.php`, so auto expose can never publish it with open permissions:

- A user can only read and update their own row (`scope()` filters on the user id).
- `password` is hashed on write and never returned.
- `role`, `active`, `google_id` and `created_at` are read only.
- Users cannot delete their account through the API.

Sign in with Google is supported as well. Add your OAuth web client id in Settings > User login > Google and enable
it; the Google button then shows up on `/sdk-demo.html`.

## 9. Defining endpoints in code

A table can become an endpoint in three ways. When more than one applies to the same table, the first one in this
list wins:

1. Code, in `public/index.php`
2. The GUI (the API area), stored in SQLite
3. Auto expose, generated from the database schema

The GUI is the easiest place to start. When an endpoint looks right, the PHP code tab of its editor shows the
equivalent PHP, which you can move into `public/index.php`.

`public/index.php` has a few commented examples. Remove the `//` to enable them:

```php
// Show the office as "City (Country)" for officeCode
$api->resource('employees')->setRelation('officeCode', 'offices', '{city} ({country})');

// UUID v7 ids for new rows
$api->resource('notes')->setIdGenerator('uuid7');

// Own rate limit for orders: 120 requests per 60 seconds
$api->resource('orders')->setRateLimit(120, 60);
```

A fuller example:

```php
$api->resource('employees')
    ->setSubject('Employee')
    ->setRelation('officeCode', 'offices', '{city} ({country})')   // belongs_to relation
    ->requiredFields('lastName', 'email')
    ->setRules('email', 'required|valid_email')
    ->setPermissions('anon', ['select']);                           // anyone may read
```

The full method list is in the Resources page of the documentation.

## 10. Cleaning up unused files

Uploaded files that are no longer referenced (an old photo after it was replaced, for example) can be cleaned up:

```bash
php bin/cleanup.php          # report only, nothing is removed
php bin/cleanup.php --run    # clean up
```

Removed files go to a trash folder first, so they can still be restored until the trash is emptied.

To run it daily:

- Linux cron: `0 3 * * * php /var/www/grocery-restable-native/bin/cleanup.php --run`
- Windows Task Scheduler: a daily task that runs
  `C:\laragon\bin\php\<php-version>\php.exe C:\laragon\www\grocery-restable-native\bin\cleanup.php --run`

The same cleanup is available in the admin GUI under Settings > Storage cleanup (also linked from the Storage
area). There you can review the files before removing them, or turn on a daily cleanup without cron.

## 11. Before going to production

This example is deliberately permissive. Before using it for real:

1. Set `'debug' => false` in `config/grocery-restable.php`.
2. Use your own `encryptionKey` and read it from an environment variable rather than a committed file:
   `'encryptionKey' => getenv('GROCERY_RESTABLE_KEY') ?: ''`.
3. Make sure the document root is `public/` and that `writable/` cannot be reached from a browser.
4. Remove the demo users (`demo`, `editor`, `disabled`).
5. Never ship the `service_role` key in frontend or mobile code.
6. Restrict allowed origins in Settings > CORS.
7. Turn on Settings > Rate limiting.
8. Serve the site over HTTPS.
9. Back up `writable/grocery-restable/grocery-restable.sqlite` and the encryption key.

## 12. Troubleshooting

### `/api` or `/grocery-restable` returns an Apache 404

The document root must point to `public/`, and `mod_rewrite` must be enabled so `public/.htaccess` is applied.

### "No input file specified."

Usually PHP runs as FastCGI and the rewrite rule does not suit it. Make sure `public/.htaccess` matches the one in
this project (`RewriteRule ^ index.php [QSA,L]`).

### Requests with `Authorization: Bearer ...` always return 401

The `Authorization` header is not reaching PHP. The last line of `public/.htaccess` passes it through; make sure it
is still there.

### `composer install` fails because the path repository is missing

See step [4.2](#42-install-the-library).

### Errors about `pdo_sqlite` or `sodium`

The extension is not enabled. Enable it in `php.ini` (Laragon: PHP > Extensions) and restart the web server.

### The setup wizard returns 403 from another machine

The wizard only accepts local requests. Use the `setupToken` option, see [section 5](#5-setup-wizard).

### The setup wizard returns 404

That is expected once the setup is complete. Sign in at `/grocery-restable`.

### Forgotten admin password

If another admin can still sign in, reset it under Settings > Admin users. Otherwise create a temporary file, for
example `reset-admin.php` in the project folder (not in `public/`):

```php
<?php
require __DIR__ . '/vendor/autoload.php';
$api = \GroceryRestable\GroceryRestable::create(require __DIR__ . '/config/grocery-restable.php');
foreach ($api->store()->users() as $user) {
    if ($user['username'] === 'admin') {                       // your admin username
        $api->store()->changePassword((int) $user['id'], 'new-password-8-chars-min');
        echo "Password changed\n";
    }
}
```

Run `php reset-admin.php`, then delete the file.

### Starting over

Delete `writable/grocery-restable/grocery-restable.sqlite` and the wizard comes back. All GUI settings and API keys
are lost. Your own database (`classicmodels`) is not touched.

### A new table does not show up in the API

Schema changes are picked up automatically on the next request. To force it, click the refresh icon in the Data
area or use API > All tables > Refresh schema. If auto expose is off, click Expose next to the table.

### The `anon` key cannot read anything

That is the secure default. Grant access under API > (table) > Permissions, or in code with
`->setPermissions('anon', ['select'])`.

## 13. Further reading

Documentation: <https://kctofficial.github.io/grocery-restable/>

- Getting started: concepts and a 10 minute tutorial
- No framework: plain PHP usage in more depth (the topic of this project)
- Admin GUI: every screen explained
- REST API reference: endpoints, filters and response formats
- JavaScript SDK: browser and Node.js usage
- Authentication: API keys, JWT, user login, roles and row level security
- Relations: relations between tables and embedding them in one request
- Troubleshooting: frequently asked questions

Source code: <https://github.com/KCTOfficial/grocery-restable>

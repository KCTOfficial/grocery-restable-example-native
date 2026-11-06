<?php

/**
 * Storage cleanup for cron (no framework):
 *
 *   php bin/cleanup.php            report only (dry run)
 *   php bin/cleanup.php --run      move orphan files to the trash and do the housekeeping
 *
 *   crontab: 0 3 * * * php /var/www/grocery-restable-native/bin/cleanup.php --run
 */

declare(strict_types=1);

use GroceryRestable\GroceryRestable;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

$api = GroceryRestable::create(require __DIR__ . '/../config/grocery-restable.php');
$janitor = $api->janitor();

$report = $janitor->scan();
foreach ($report['tasks'] as $task => $summary) {
    printf("%-18s %6d items %12d bytes\n", $task, $summary['count'], $summary['bytes']);
}
foreach ($report['skipped'] as $skipped) {
    printf("skipped %s: %s\n", $skipped['directory'], $skipped['reason']);
}

if (! in_array('--run', $argv, true)) {
    echo "Dry run. Add --run to clean (orphans go to {$report['trash_path']} first).\n";
    exit(0);
}

$done = $janitor->clean(['orphan_files', 'orphan_buckets', 'stale_index', 'untracked_objects', 'tmp_files', 'empty_folders', 'trash']);
foreach ($done as $task => $count) {
    printf("cleaned %-18s %d\n", $task, $count);
}

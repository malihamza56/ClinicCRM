<?php
declare(strict_types=1);

/*
 * Loads the fictional demo data into a database whose schema is already imported
 * (for example after importing database/schema.sql with phpMyAdmin).
 *
 *     php bin/seed-demo.php
 *
 * DEVELOPMENT ONLY. It refuses to run if any user account already exists, so it cannot be run twice
 * and cannot be pointed at a database that holds real data.
 */

require dirname(__DIR__) . '/bootstrap/app.php';
require BASE_PATH . '/database/seed.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line.\n");
    exit(1);
}

try {
    $made = seedDemoData();
} catch (Throwable $e) {
    fwrite(STDERR, 'Seeding stopped: ' . $e->getMessage() . "\n");
    exit(1);
}
echo 'Demo data loaded: ' . json_encode($made) . "\nDEVELOPMENT ONLY. All demo accounts use the password in docs/setup.md and must change it at first sign-in.\n";

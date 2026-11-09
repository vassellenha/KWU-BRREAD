<?php

// Ensure cache & storage directories exist in writable /tmp on Vercel
$storageDir = '/tmp/storage';
$cacheDirs = [
    $storageDir . '/framework/views',
    $storageDir . '/framework/cache',
    $storageDir . '/framework/sessions',
    '/tmp/bootstrap/cache',
];

foreach ($cacheDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Redirect cache file locations to /tmp
putenv('VIEW_COMPILED_PATH=' . $storageDir . '/framework/views');
putenv('APP_SERVICES_CACHE=/tmp/bootstrap/cache/services.php');
putenv('APP_PACKAGES_CACHE=/tmp/bootstrap/cache/packages.php');
putenv('APP_CONFIG_CACHE=/tmp/bootstrap/cache/config.php');
putenv('APP_ROUTES_CACHE=/tmp/bootstrap/cache/routes.php');
putenv('APP_EVENTS_CACHE=/tmp/bootstrap/cache/events.php');

// Fix SCRIPT_NAME and SCRIPT_FILENAME so Laravel routing matches correctly
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

// Forward serverless request to Laravel's public entrypoint
require __DIR__ . '/../public/index.php';

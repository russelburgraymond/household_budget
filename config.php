<?php
// Update these if needed for your local environment.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'ao_0010_Budgeter_II');
define('DB_USER', 'root');
define('DB_PASS', '3473');

date_default_timezone_set('America/Chicago');


define('APP_VERSION', trim((string) @file_get_contents(__DIR__ . '/VERSION')) ?: 'dev');

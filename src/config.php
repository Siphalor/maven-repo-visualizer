<?php

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();

if (isset($_ENV['MAVENRV_ROOT_DIR'])) {
    define('IGNORED_EXTENSIONS', explode(',', $_ENV['MAVENRV_ROOT_DIR']));
} else {
    define('IGNORED_EXTENSIONS', array('.php', '.md'));
}
define("SITE_NAME", $_ENV['MAVENRV_SITE_NAME'] ?? 'Maven Repository');
define("PRIMARY_COLOR", $_ENV['MAVENRV_PRIMARY_COLOR'] ?? "#516d29");
define("PRIMARY_COLOR_DARK_MODE", $_ENV['MAVENRV_PRIMARY_COLOR_DARK_MODE'] ?? $_ENV['MAVENRV_PRIMARY_COLOR'] ?? "#82b342");

<?php

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();

# Cosmetics
define("SITE_NAME", $_ENV['MAVENRV_SITE_NAME'] ?? 'Maven Repository');
define("PRIMARY_COLOR", $_ENV['MAVENRV_PRIMARY_COLOR'] ?? "#516d29");
define("PRIMARY_COLOR_DARK_MODE", $_ENV['MAVENRV_PRIMARY_COLOR_DARK_MODE'] ?? $_ENV['MAVENRV_PRIMARY_COLOR'] ?? "#82b342");

# List display
if (isset($_ENV['MAVENRV_IGNORED_EXTENSIONS'])) {
    define('IGNORED_EXTENSIONS', explode(',', $_ENV['MAVENRV_IGNORED_EXTENSIONS']));
} else {
    define('IGNORED_EXTENSIONS', array('.php', '.md'));
}
define('VERSIONS_SORT_BY', $_ENV['MAVENRV_VERSIONS_SORT_BY'] ?? 'name'); # last_modified, name
define('VERSIONS_SORT_ORDER', $_ENV['MAVENRV_VERSIONS_SORT_ORDER'] ?? 'desc'); # desc, asc

# Metadata
define(
    'GRADLE_CATALOG_ALIAS_REGEX',
    $_ENV['MAVENRV_GRADLE_CATALOG_ALIAS_REGEX']
        # language=regexp
        ?? '/([a-zA-Z0-9]*[a-zA-Z][a-zA-Z0-9]*(?:[-_][a-zA-Z0-9]*[a-zA-Z][a-zA-Z0-9]*)*)(?:[-_][^a-zA-Z]+)?$/'
);
define('GRADLE_CATALOG_LIBRARY_STYLE', $_ENV['MAVENRV_GRADLE_CATALOG_LIBRARY_STYLE'] ?? 'group_name');

# Assets
define('ASSETS_SERVE_PATH', $_ENV['MAVENRV_ASSETS_SERVE_PATH'] ?? '$EMBED');

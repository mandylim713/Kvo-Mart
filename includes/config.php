<?php
/**
 * KVO Mart — configuration
 * ------------------------------------------------------------------
 * Default database is SQLite (no setup — the file is created in /data).
 * For MySQL on cPanel: create a database and user, set DB_DRIVER to
 * 'mysql' and fill in the details. Tables are created automatically.
 *
 * Business details, payment keys, delivery zones, tax and everything
 * else are edited in the admin dashboard (/admin), not here.
 */

define('DB_DRIVER', 'sqlite');                 // 'sqlite' or 'mysql'
define('DB_SQLITE_PATH', __DIR__ . '/../data/kvomart.sqlite');

define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_kvomart');
define('DB_USER', 'cpaneluser_kvo');
define('DB_PASS', '');

// Leave empty to auto-detect. Set only if links come out wrong,
// e.g. 'https://kvomart.com' or 'https://example.com/shop'
define('SITE_URL', '');

date_default_timezone_set('Africa/Lagos');

// true while setting up; false when live
define('DEBUG', false);

<?php
/**
 * KVO Mart — configuration
 * --------------------------------------------------------------
 * Default database is SQLite (zero setup, file stored in /data).
 * To use MySQL on cPanel: create a database + user, then set
 * DB_DRIVER to 'mysql' and fill in the credentials below.
 * Tables are created automatically on first page load.
 */

define('DB_DRIVER', 'sqlite');           // 'sqlite' or 'mysql'

// SQLite
define('DB_SQLITE_PATH', __DIR__ . '/../data/kvomart.sqlite');

// MySQL (only used when DB_DRIVER = 'mysql')
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_kvomart');
define('DB_USER', 'cpaneluser_kvo');
define('DB_PASS', '');

// Leave empty to auto-detect (works in root or a sub-folder).
// Set it if auto-detect is wrong, e.g. 'https://kvomart.com'
define('SITE_URL', '');

// Timezone
date_default_timezone_set('Africa/Lagos');

// Show PHP errors (set false when live)
define('DEBUG', false);

<?php
session_start();
error_reporting(0);

define('DB_NAME', getenv('DB_NAME') ?: 'quimica');
define('DB_USER', getenv('DB_USER') ?: 'nealice');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('LAB_DEBUG', false);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$urlSite = getenv('URL_SITE') ?: '/';
define('URL_SITE', rtrim($urlSite, '/') . '/');
define('URL_SYSTEM', __DIR__ . '/');

include __DIR__ . '/register.php';

<?php
// Cookie de sessão: HttpOnly impede leitura por JavaScript, SameSite=Lax
// reduz CSRF e Secure só é ligado quando a requisição já chegou por HTTPS
// (atrás do Caddy, quem informa isso é X-Forwarded-Proto).
$httpsAtivo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

ini_set('session.use_strict_mode', '1');
session_set_cookie_params(array(
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $httpsAtivo,
    'httponly' => true,
    'samesite' => 'Lax',
));

session_start();

// Antes: error_reporting(0). Qualquer erro virava página em branco sem
// registro nenhum. Agora os erros vão para o log do container
// (docker compose logs web) e continuam fora da resposta HTTP.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

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

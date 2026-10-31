<?php

declare(strict_types=1);

session_start();

define('ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = ROOT . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$GLOBALS['app_config'] = require ROOT . '/app/config/config.php';

// Авто-база: /sport/public из SCRIPT_NAME, если base_url в конфиге пустой
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '') {
    $scriptDir = '';
}
if (($GLOBALS['app_config']['base_url'] ?? '') === '') {
    $GLOBALS['app_config']['base_url'] = $scriptDir;
}

use App\Core\Database;
use App\Core\Router;
use App\Lang\Lang;

Lang::boot();

Database::connect($GLOBALS['app_config']['db']);

$router = new Router();
require ROOT . '/app/config/routes.php';

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = $GLOBALS['app_config']['base_url'] ?? '';

// nginx без .htaccess: /alashgym → public/index.php, а base = /alashgym/public
if ($base !== '' && str_ends_with($base, '/public')) {
    $appRoot = substr($base, 0, -strlen('/public'));
    $uriNorm = rtrim($uriPath, '/') ?: '/';
    $rootNorm = rtrim($appRoot, '/') ?: '/';
    if ($uriNorm === $rootNorm) {
        $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
            ? '?' . $_SERVER['QUERY_STRING']
            : '';
        header('Location: ' . $base . '/' . $qs, true, 301);
        exit;
    }
}

if ($base !== '' && str_starts_with($uriPath, $base)) {
    $uriPath = substr($uriPath, strlen($base)) ?: '/';
}

// /index.php или /index.php/register
if (str_starts_with($uriPath, '/index.php')) {
    $uriPath = substr($uriPath, strlen('/index.php')) ?: '/';
}

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $uriPath);

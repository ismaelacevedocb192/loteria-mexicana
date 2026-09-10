<?php
/**
 * Router para el servidor embebido de PHP en desarrollo: fuerza SQLite
 * e ignora config.local.php, para probar sin MySQL.
 *
 *   php -S 127.0.0.1:8080 tools/dev-router.php
 */
define('LOTERIA_TEST_DSN', 'sqlite:' . __DIR__ . '/../local.sqlite');

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$archivo = __DIR__ . '/..' . $ruta;
if ($ruta !== '/' && is_file($archivo) && !str_ends_with($ruta, '.php')) return false; // estáticos
require is_file($archivo) ? $archivo : __DIR__ . '/../index.php';

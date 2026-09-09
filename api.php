<?php
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/api_partidas.php';
require_once __DIR__ . '/lib/api_jugadores.php';
if (file_exists(__DIR__ . '/lib/api_mazos.php')) require_once __DIR__ . '/lib/api_mazos.php';

header('Cache-Control: no-store');
$a = preg_replace('/[^a-z_]/', '', (string)($_GET['a'] ?? ''));
$fn = "api_$a";
try {
    if ($a === '' || !function_exists($fn)) throw new ApiException('Acción desconocida', 404);
    $fn();
} catch (ApiException $e) {
    jsonError($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    jsonError('Error interno: ' . $e->getMessage(), 500);
}

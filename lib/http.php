<?php
/*
 * Lotería Mexicana — juego de lotería para el salón de clases.
 * Copyright (C) 2026 Ismael A. Acevedo Rendón
 *
 * Este programa es software libre: puedes redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General GNU publicada por la
 * Free Software Foundation, ya sea la versión 3 de la Licencia o (a tu
 * elección) cualquier versión posterior.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero SIN
 * NINGUNA GARANTÍA; ni siquiera la garantía implícita de COMERCIABILIDAD o
 * IDONEIDAD PARA UN PROPÓSITO PARTICULAR. Consulta la Licencia Pública
 * General GNU para más detalles.
 *
 * Deberías haber recibido una copia de la Licencia Pública General GNU
 * junto con este programa. Si no, consulta <https://www.gnu.org/licenses/>.
 */
require_once __DIR__ . '/../config.php';

class ApiException extends Exception {}

/** Lee un parámetro: en pruebas de $GLOBALS['__params'], en web de JSON, POST o GET. */
function param(string $k, $def = null) {
    if (isset($GLOBALS['__params'])) return $GLOBALS['__params'][$k] ?? $def;
    static $json = null;
    if ($json === null) {
        $json = [];
        if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $json = json_decode(file_get_contents('php://input'), true) ?: [];
        }
    }
    return $json[$k] ?? $_POST[$k] ?? $_GET[$k] ?? $def;
}

function jsonOk(array $d = []): void {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $d, JSON_UNESCAPED_UNICODE);
}

function jsonError(string $m, int $code = 400): void {
    if (!headers_sent()) { http_response_code($code ?: 400); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
}

function claveOk(): bool {
    if (PRESENTADOR_CLAVE === '') return true;
    $k = $_GET['k'] ?? $_POST['k'] ?? $_COOKIE['lot_k'] ?? '';
    return hash_equals(PRESENTADOR_CLAVE, (string)$k);
}

function exigirClavePresentador(): void {
    if (isset($GLOBALS['__params'])) return; // pruebas
    if (!claveOk()) throw new ApiException('Clave de presentador requerida', 403);
}

/** URL pública de la carpeta de la app, sin diagonal final. */
function urlBase(): string {
    if (BASE_URL !== '') return rtrim(BASE_URL, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

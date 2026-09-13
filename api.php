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

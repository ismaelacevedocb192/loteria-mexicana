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

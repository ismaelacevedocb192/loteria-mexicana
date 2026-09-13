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

/*
 * Valores de respaldo de toda la configuración.
 *
 * Se cargan después de config.php y de config.local.php, así que solo
 * rellenan lo que falte. Gracias a esto, una instalación que conserve un
 * config.php de una versión anterior sigue funcionando cuando el programa
 * estrena una opción nueva: nunca falla por una constante sin definir.
 */

$lot_valores = [
    // Conexión. Sin datos válidos, db.php muestra su página de ayuda.
    'DB_DSN'  => getenv('DB_DSN')  ?: 'mysql:host=localhost;dbname=loteria;charset=utf8mb4',
    'DB_USER' => getenv('DB_USER') ?: 'root',
    'DB_PASS' => getenv('DB_PASS') ?: '',

    // Clave opcional para las pantallas de presentador. Vacía = sin clave.
    'PRESENTADOR_CLAVE' => getenv('PRESENTADOR_CLAVE') ?: '',

    // Cada cuánto consultan el servidor la pantalla grande y los teléfonos.
    'INTERVALO_SONDEO_MS' => 1500,

    // URL pública sin diagonal final. Vacía = se deduce de la petición.
    'BASE_URL' => getenv('BASE_URL') ?: '',

    // Cintilla inferior. Vacía = no se muestra.
    'INSTITUCION' => getenv('INSTITUCION') ?: 'Academia Local de Humanidades',

    // Código fuente, para la leyenda de licencia. Vacío = no se muestra.
    'REPO_URL' => getenv('REPO_URL') ?: 'https://github.com/ismaelacevedocb192/loteria-mexicana',
];

foreach ($lot_valores as $lot_nombre => $lot_valor) {
    if (!defined($lot_nombre)) define($lot_nombre, $lot_valor);
}
unset($lot_valores, $lot_nombre, $lot_valor);

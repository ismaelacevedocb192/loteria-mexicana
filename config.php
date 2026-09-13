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
// Configuración. En Railway se leen variables de entorno; en LAMP edita las constantes
// o crea config.local.php (ignorado por git) que las redefina con define() antes.
// Tus credenciales van aquí o, mejor, en config.local.php (no se sube a git).
if (file_exists(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';

date_default_timezone_set('America/Mexico_City');

// Base de datos. Cámbialas aquí si no usas config.local.php.
// define('DB_DSN',  'mysql:host=localhost;dbname=NOMBRE_BD;charset=utf8mb4');
// define('DB_USER', 'USUARIO_BD');
// define('DB_PASS', 'CONTRASEÑA');

// Otras opciones, todas con valor por defecto en lib/constantes.php:
//   PRESENTADOR_CLAVE   clave de las pantallas de presentador ('' = sin clave)
//   INSTITUCION         texto de la cintilla inferior ('' = ocultarla)
//   REPO_URL            enlace al código fuente en la portada ('' = ocultarlo)
//   INTERVALO_SONDEO_MS cada cuánto consultan el servidor pantalla y teléfonos
//   BASE_URL            URL pública, si la que deduce PHP no es la correcta

// Rellena todo lo que no se haya definido arriba.
require_once __DIR__ . '/lib/constantes.php';

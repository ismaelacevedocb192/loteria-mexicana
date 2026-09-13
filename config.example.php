<?php
/*
 * Plantilla de configuración local.
 *
 * Copia este archivo como config.local.php y pon los datos de tu servidor.
 * config.local.php está en .gitignore, así que tus credenciales nunca se suben
 * al repositorio. config.php lo carga automáticamente si existe.
 */

// Base de datos MySQL / MariaDB.
define('DB_DSN',  'mysql:host=localhost;dbname=NOMBRE_BD;charset=utf8mb4');
define('DB_USER', 'USUARIO_BD');
define('DB_PASS', 'CONTRASEÑA');

// Opcional: protege las pantallas de presentador con una clave.
// Con la clave puesta, entra una vez con index.php?k=TU_CLAVE.
// define('PRESENTADOR_CLAVE', '');

// Opcional: texto de la cintilla inferior. Cadena vacía para ocultarla.
// define('INSTITUCION', 'Academia Local de Humanidades');

// Opcional: URL pública, sin diagonal final. Vacío = se deduce sola.
// Útil si el QR debe apuntar a un dominio distinto del que ve PHP.
// define('BASE_URL', '');

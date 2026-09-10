<?php
// Configuración. En Railway se leen variables de entorno; en LAMP edita las constantes
// o crea config.local.php (ignorado por git) que las redefina con define() antes.
if (file_exists(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';

date_default_timezone_set('America/Mexico_City');

if (!defined('DB_DSN'))  define('DB_DSN',  getenv('DB_DSN')  ?: 'mysql:host=localhost;dbname=loteria;charset=utf8mb4');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');

// Si no está vacía, presentador.php, index.php, mazos.php y las acciones de presentador la exigen (?k=clave).
if (!defined('PRESENTADOR_CLAVE')) define('PRESENTADOR_CLAVE', getenv('PRESENTADOR_CLAVE') ?: '');

// Texto de la cintilla inferior. Vacío ('') la oculta.
if (!defined('INSTITUCION')) define('INSTITUCION', getenv('INSTITUCION') ?: 'Academia Local de Humanidades');

// Cada cuánto consultan el servidor la pantalla grande y los teléfonos.
if (!defined('INTERVALO_SONDEO_MS')) define('INTERVALO_SONDEO_MS', 1500);

// URL base pública (sin diagonal final). Vacío = se deduce de la petición.
if (!defined('BASE_URL')) define('BASE_URL', getenv('BASE_URL') ?: '');

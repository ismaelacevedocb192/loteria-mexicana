<?php
// Auxiliar de test_config.php: carga solo los respaldos, sin config.php.
require __DIR__ . '/../lib/constantes.php';
$faltan = [];
foreach (['DB_DSN','DB_USER','DB_PASS','PRESENTADOR_CLAVE','INTERVALO_SONDEO_MS','BASE_URL','INSTITUCION','REPO_URL'] as $c) {
    if (!defined($c)) $faltan[] = $c;
}
echo $faltan ? 'FALTAN: ' . implode(', ', $faltan) : 'OK';

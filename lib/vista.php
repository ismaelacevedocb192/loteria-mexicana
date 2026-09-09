<?php
require_once __DIR__ . '/http.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Páginas de presentador: si hay clave configurada, la exige (?k= o cookie). */
function exigirClaveVista(): void {
    if (PRESENTADOR_CLAVE === '') return;
    if (isset($_GET['k']) && hash_equals(PRESENTADOR_CLAVE, (string)$_GET['k'])) {
        setcookie('lot_k', $_GET['k'], ['expires' => time() + 86400 * 30, 'path' => '/', 'samesite' => 'Lax']);
        $_COOKIE['lot_k'] = $_GET['k'];
        return;
    }
    if (claveOk()) return;
    http_response_code(403);
    cabecera('Clave requerida');
    echo '<main class="centro"><form method="get" class="tarjeta"><h1>Clave de presentador</h1>
          <label>Clave <input type="password" name="k" autofocus></label><button class="btn btn-primario">Entrar</button></form></main>';
    pie(); exit;
}

function cabecera(string $titulo, string $clase = ''): void {
    $k = PRESENTADOR_CLAVE !== '' && claveOk() ? ($_COOKIE['lot_k'] ?? $_GET['k'] ?? '') : '';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>' . h($titulo) . ' · Lotería</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext y=%22.9em%22 font-size=%2290%22%3E🎉%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/app.css?v=1">
<script>window.LOT={sondeo:' . (int)INTERVALO_SONDEO_MS . ',k:' . json_encode($k) . '};</script>
</head><body class="' . h($clase) . '">';
}

function pie(): void { echo '</body></html>'; }

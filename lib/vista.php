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
require_once __DIR__ . '/http.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/**
 * Sello de versión de los archivos estáticos: la fecha del CSS y de las cartas.
 * Cambia solo al actualizar la app, así el navegador vuelve a pedir CSS, JS e imágenes.
 */
function assetVer(): string {
    static $v = null;
    if ($v !== null) return $v;
    $t = 0;
    foreach ([__DIR__ . '/../assets/app.css', __DIR__ . '/../cartas/01.svg'] as $f) {
        if (file_exists($f)) $t = max($t, (int)filemtime($f));
    }
    return $v = (string)($t ?: time());
}

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
<title>' . h($titulo) . ' · Lotería Mexicana</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext y=%22.9em%22 font-size=%2290%22%3E🎉%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/app.css?v=' . assetVer() . '">
<script>window.LOT={sondeo:' . (int)INTERVALO_SONDEO_MS . ',k:' . json_encode($k) . ',v:' . json_encode(assetVer()) . '};</script>
</head><body class="' . h($clase) . '">';
}

function cintilla(): void {
    if (INSTITUCION === '') return;
    echo '<footer class="cintilla"><span>' . h(INSTITUCION) . '</span></footer>';
}

function pie(): void { cintilla(); echo '</body></html>'; }

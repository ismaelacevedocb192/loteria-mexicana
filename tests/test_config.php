<?php
/**
 * Evita el error "Undefined constant" en instalaciones que conservan un
 * config.php de una versión anterior: toda constante de configuración que
 * use el código debe tener un valor de respaldo en lib/constantes.php.
 */

/** Constantes en MAYÚSCULAS que un archivo PHP usa, sin llamadas ni constantes de clase. */
function constantesUsadas(string $archivo): array {
    $tokens = token_get_all(file_get_contents($archivo));
    $usadas = [];
    foreach ($tokens as $i => $t) {
        if (!is_array($t) || $t[0] !== T_STRING) continue;
        $nombre = $t[1];
        if ($nombre !== strtoupper($nombre) || !preg_match('/^[A-Z][A-Z0-9_]*$/', $nombre)) continue;
        if (class_exists($nombre) || interface_exists($nombre)) continue; // tipos como PDO
        // Descartar métodos, constantes de clase y llamadas a función.
        $antes = null;
        for ($j = $i - 1; $j >= 0; $j--) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) continue;
            $antes = $tokens[$j]; break;
        }
        if (is_array($antes) && in_array($antes[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST])) continue;
        $despues = null;
        for ($j = $i + 1; $j < count($tokens); $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) continue;
            $despues = $tokens[$j]; break;
        }
        if ($despues === '(' || $despues === '::') continue;
        $usadas[$nombre] = true;
    }
    return array_keys($usadas);
}

test('toda constante de configuración tiene respaldo en lib/constantes.php', function () {
    $raiz = dirname(__DIR__);
    $respaldo = file_get_contents("$raiz/lib/constantes.php");
    preg_match_all("/'([A-Z][A-Z0-9_]*)'\s*=>/", $respaldo, $m);
    $conRespaldo = $m[1];
    assertTrue(count($conRespaldo) >= 5, 'lib/constantes.php debería listar la configuración');

    $internas = [];
    foreach (get_defined_constants(true) as $grupo => $lista) {
        if ($grupo !== 'user') $internas += $lista;
    }

    $archivos = array_merge(glob("$raiz/*.php"), glob("$raiz/lib/*.php"), glob("$raiz/tools/*.php"));

    // Constantes que define el propio programa. config.php queda fuera a
    // propósito: es el archivo que cada instalación edita y puede ser viejo,
    // así que lo que se defina solo ahí no cuenta como garantizado.
    $definidas = [];
    foreach ($archivos as $f) {
        if (basename($f) === 'config.php') continue;
        preg_match_all("/(?:define\(\s*'|const\s+)([A-Z][A-Z0-9_]*)/", file_get_contents($f), $m2);
        $definidas = array_merge($definidas, $m2[1]);
    }

    $faltan = [];
    foreach ($archivos as $f) {
        if (basename($f) === 'constantes.php') continue;
        // Constantes que el archivo consulta con defined() antes de usarlas.
        preg_match_all("/defined\(\s*'([A-Z][A-Z0-9_]*)'/", file_get_contents($f), $protegidas);
        foreach (constantesUsadas($f) as $c) {
            if (isset($internas[$c])) continue;                    // constante de PHP
            if (in_array($c, $protegidas[1], true)) continue;      // protegida con defined()
            if (in_array($c, $definidas, true)) continue;          // la define el programa
            if (in_array($c, $conRespaldo, true)) continue;        // tiene valor por defecto
            $faltan[] = basename($f) . ': ' . $c;
        }
    }
    assertEq([], $faltan, 'sin valor por defecto, romperían una instalación con config.php viejo');
});

test('lib/constantes.php define la configuración por sí solo', function () {
    $salida = trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ayuda_constantes.php')));
    assertEq('OK', $salida, $salida);
});

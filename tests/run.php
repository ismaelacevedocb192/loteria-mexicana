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
// Corredor mínimo: incluye cada tests/test_*.php; cada uno registra pruebas con test('nombre', fn).
$tests = [];
function test(string $nombre, callable $fn): void { global $tests; $tests[] = [$nombre, $fn]; }
function assertTrue($cond, string $msg = ''): void { if (!$cond) throw new Exception("assertTrue falló. $msg"); }
function assertEq($esp, $real, string $msg = ''): void {
    if ($esp !== $real) throw new Exception("assertEq falló: esperado " . var_export($esp, true) . ", real " . var_export($real, true) . ". $msg");
}
foreach (glob(__DIR__ . '/test_*.php') as $f) require $f;
$fallos = 0;
foreach ($tests as [$nombre, $fn]) {
    try { $fn(); echo "  ✓ $nombre\n"; }
    catch (Throwable $e) { $fallos++; echo "  ✗ $nombre\n      " . $e->getMessage() . "\n      " . $e->getFile() . ':' . $e->getLine() . "\n"; }
}
echo $fallos ? "FALLARON $fallos de " . count($tests) . "\n" : "OK (" . count($tests) . " pruebas)\n";
exit($fallos ? 1 : 0);

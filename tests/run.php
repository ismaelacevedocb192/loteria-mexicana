<?php
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

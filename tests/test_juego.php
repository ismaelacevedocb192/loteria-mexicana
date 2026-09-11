<?php
require_once __DIR__ . '/../lib/juego.php';

test('generarCodigo da 6 chars sin ambiguos', function () {
    for ($i = 0; $i < 50; $i++) {
        $c = generarCodigo();
        assertEq(6, strlen($c));
        assertTrue(preg_match('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}$/', $c) === 1, $c);
    }
});

test('generarToken da 32 hex', function () {
    assertTrue(preg_match('/^[0-9a-f]{32}$/', generarToken()) === 1);
});

test('barajar es permutación completa', function () {
    $ids = range(1, 54);
    $b = barajar($ids);
    assertEq(54, count($b));
    $s = $b; sort($s);
    assertEq($ids, $s);
});

test('generarTablero da 16 ids únicos del mazo', function () {
    $mazo = range(100, 153);
    $t = generarTablero($mazo, []);
    assertEq(16, count($t));
    assertEq(16, count(array_unique($t)));
    foreach ($t as $id) assertTrue(in_array($id, $mazo));
});

test('generarTablero evita repetir conjunto existente', function () {
    $mazo = range(1, 17); // solo 17 tableros posibles, fácil chocar
    $existentes = [];
    for ($i = 0; $i < 10; $i++) {
        $t = generarTablero($mazo, $existentes);
        $s = $t; sort($s);
        foreach ($existentes as $e) { $es = $e; sort($es); assertTrue($es !== $s, 'tablero repetido'); }
        $existentes[] = $t;
    }
});

test('cartasSalidas respeta el índice', function () {
    assertEq([], cartasSalidas([5, 6, 7], -1));
    assertEq([5], cartasSalidas([5, 6, 7], 0));
    assertEq([5, 6, 7], cartasSalidas([5, 6, 7], 2));
    assertEq([5, 6, 7], cartasSalidas([5, 6, 7], 9));
});

test('puedeMarcar solo cartas del tablero que ya salieron', function () {
    $tablero = [1, 2, 3];
    assertTrue(puedeMarcar(2, $tablero, [9, 2]));
    assertTrue(!puedeMarcar(3, $tablero, [9, 2]));   // no ha salido
    assertTrue(!puedeMarcar(9, $tablero, [9, 2]));   // no está en tablero
});

test('esLoteriaValida solo si las 16 salieron', function () {
    $tablero = range(1, 16);
    assertTrue(esLoteriaValida($tablero, range(1, 20)));
    assertTrue(!esLoteriaValida($tablero, range(1, 15)));
    assertTrue(esLoteriaValida($tablero, array_reverse(range(1, 16))));
});

test('normalizarNombre ignora mayúsculas, acentos y espacios', function () {
    assertEq('ana', normalizarNombre('  Ana '));
    assertEq('ana', normalizarNombre('ANÁ'));
    assertEq('jose luis', normalizarNombre('José   Luis'));
    assertEq('nino', normalizarNombre('Niño'));
    assertTrue(normalizarNombre('Ana') !== normalizarNombre('Ane'));
});

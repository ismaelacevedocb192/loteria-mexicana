<?php
if (!defined('LOTERIA_TEST_DSN')) define('LOTERIA_TEST_DSN', 'sqlite::memory:');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/mazo_clasico.php';

test('MAZO_CLASICO tiene 54 cartas numeradas 1..54', function () {
    assertEq(54, count(MAZO_CLASICO));
    assertEq(range(1, 54), array_column(MAZO_CLASICO, 0));
    assertEq('El Gallo', MAZO_CLASICO[0][1]);
    assertEq('La Rana', MAZO_CLASICO[53][1]);
});

test('existen los 54 SVG en cartas/', function () {
    for ($i = 1; $i <= 54; $i++) {
        $f = sprintf(__DIR__ . '/../cartas/%02d.svg', $i);
        assertTrue(file_exists($f), "falta $f");
        assertTrue(str_contains(file_get_contents($f), '<svg'), "$f no es svg");
    }
});

test('getDB siembra el mazo clásico una sola vez', function () {
    $pdo = getDB();
    $n = $pdo->query("SELECT COUNT(*) FROM mazos WHERE es_default=1")->fetchColumn();
    assertEq(1, (int)$n);
    sembrarMazoClasico($pdo);
    $n = $pdo->query("SELECT COUNT(*) FROM mazos WHERE es_default=1")->fetchColumn();
    assertEq(1, (int)$n);
    $c = $pdo->query("SELECT COUNT(*) FROM cartas")->fetchColumn();
    assertEq(54, (int)$c);
    $img = $pdo->query("SELECT imagen FROM cartas WHERE numero=7")->fetchColumn();
    assertEq('cartas/07.svg', $img);
});

test('nombres del mazo clásico según Wikipedia (2 El Diablo, 18 El Violonchelo)', function () {
    assertEq('El Diablo', MAZO_CLASICO[1][1]);
    assertEq('El Violonchelo', MAZO_CLASICO[17][1]);
});

test('la siembra corrige nombres viejos en una base existente', function () {
    $pdo = getDB();
    $pdo->exec("UPDATE cartas SET nombre='El Diablito' WHERE numero=2");
    sembrarMazoClasico($pdo);
    assertEq('El Diablo', $pdo->query("SELECT nombre FROM cartas WHERE numero=2")->fetchColumn());
});

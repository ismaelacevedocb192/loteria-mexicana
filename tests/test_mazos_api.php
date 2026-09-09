<?php
if (!defined('LOTERIA_TEST_DSN')) define('LOTERIA_TEST_DSN', 'sqlite::memory:');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/api_partidas.php';
require_once __DIR__ . '/../lib/api_mazos.php';

test('CRUD de mazos y cartas', function () {
    $r = llamar('mazo_crear', ['nombre' => 'Biología']);
    assertTrue($r['ok'], json_encode($r));
    $id = $r['id'];
    assertTrue(!llamar('mazo_crear', ['nombre' => ''])['ok']);

    $c = llamar('carta_guardar', ['mazo_id' => $id, 'numero' => 1, 'nombre' => 'Mitocondria']);
    assertTrue($c['ok'], json_encode($c));
    $c2 = llamar('carta_guardar', ['mazo_id' => $id, 'id' => $c['id'], 'numero' => 1, 'nombre' => 'La Mitocondria']);
    assertEq($c['id'], $c2['id']);
    assertTrue(!llamar('carta_guardar', ['mazo_id' => $id, 'numero' => 1, 'nombre' => 'Dup'])['ok'], 'número duplicado');
    assertTrue(!llamar('carta_guardar', ['mazo_id' => $id, 'numero' => 0, 'nombre' => 'X'])['ok'], 'número inválido');

    $l = llamar('mazo_cartas', ['mazo_id' => $id]);
    assertEq(1, count($l['cartas']));
    assertEq('La Mitocondria', $l['cartas'][0]['nombre']);
    assertEq(null, $l['cartas'][0]['imagen']);
    assertEq(false, $l['mazo']['es_default']);

    $ls = llamar('mazos_listar');
    $mio = array_values(array_filter($ls['mazos'], fn($m) => $m['id'] == $id))[0];
    assertEq(1, $mio['cartas']);
    assertEq('Biología', $mio['nombre']);

    assertTrue(llamar('mazo_renombrar', ['id' => $id, 'nombre' => 'Bio'])['ok']);
    assertEq('Bio', llamar('mazo_cartas', ['mazo_id' => $id])['mazo']['nombre']);
    assertTrue(llamar('carta_borrar', ['id' => $c['id']])['ok']);
    assertEq(0, count(llamar('mazo_cartas', ['mazo_id' => $id])['cartas']));
    assertTrue(llamar('mazo_borrar', ['id' => $id])['ok']);
    assertEq(0, count(array_filter(llamar('mazos_listar')['mazos'], fn($m) => $m['id'] == $id)));
});

test('no se borra el mazo default ni uno con partidas', function () {
    $pdo = getDB();
    $def = mazoDefault();
    assertTrue(!llamar('mazo_borrar', ['id' => $def])['ok']);
    assertTrue(!llamar('carta_guardar', ['mazo_id' => $def, 'numero' => 99, 'nombre' => 'X'])['ok'], 'default no editable');
    $cartaDef = (int)$pdo->query("SELECT id FROM cartas WHERE mazo_id = $def LIMIT 1")->fetchColumn();
    assertTrue(!llamar('carta_borrar', ['id' => $cartaDef])['ok'], 'carta default no borrable');
    $id = llamar('mazo_crear', ['nombre' => 'Con partida'])['id'];
    $pdo->exec("INSERT INTO partidas (codigo, mazo_id) VALUES ('TESTAA', $id)");
    assertTrue(!llamar('mazo_borrar', ['id' => $id])['ok']);
});

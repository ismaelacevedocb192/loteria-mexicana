<?php
if (!defined('LOTERIA_TEST_DSN')) define('LOTERIA_TEST_DSN', 'sqlite::memory:');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/api_partidas.php';
require_once __DIR__ . '/../lib/api_jugadores.php';

/** Llama a una acción del API como si fuera una petición y devuelve el JSON decodificado. */
function llamar(string $accion, array $params = []): array {
    $GLOBALS['__params'] = $params;
    ob_start();
    try { ("api_$accion")(); } catch (ApiException $e) { jsonError($e->getMessage(), $e->getCode()); }
    $out = ob_get_clean();
    $r = json_decode($out, true);
    if ($r === null) throw new Exception("respuesta no JSON en $accion: $out");
    return $r;
}

function mazoDefault(): int {
    return (int)getDB()->query("SELECT id FROM mazos WHERE es_default=1")->fetchColumn();
}

test('flujo completo de partida', function () {
    $pdo = getDB();
    $r = llamar('crear_partida', ['mazo_id' => mazoDefault()]);
    assertTrue($r['ok'], json_encode($r));
    $cod = $r['codigo'];
    assertEq(6, strlen($cod));

    $j1 = llamar('unirse', ['codigo' => $cod, 'nombre' => 'Ana']);
    assertTrue($j1['ok'], json_encode($j1));
    assertEq(16, count($j1['tablero']));
    $j2 = llamar('unirse', ['codigo' => $cod, 'nombre' => 'Beto']);
    assertTrue($j2['ok']);

    assertTrue(!llamar('unirse', ['codigo' => $cod, 'nombre' => ''])['ok'], 'nombre vacío');
    assertTrue(!llamar('unirse', ['codigo' => 'ZZZZZZ', 'nombre' => 'X'])['ok'], 'código inexistente');

    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq('lobby', $e['estado']);
    assertEq(2, count($e['jugadores']));
    assertTrue(str_ends_with($e['url_jugar'], 'jugar.php?c=' . $cod), $e['url_jugar']);

    // marcar antes de iniciar: rechazado
    $m = llamar('marcar', ['token' => $j1['token'], 'carta_id' => $j1['tablero'][0]['id']]);
    assertTrue(!$m['ok']);

    assertTrue(llamar('iniciar', ['codigo' => $cod])['ok']);
    assertTrue(!llamar('unirse', ['codigo' => $cod, 'nombre' => 'Tarde'])['ok'], 'no entrar tras iniciar');
    assertTrue(!llamar('iniciar', ['codigo' => $cod])['ok'], 'no iniciar dos veces');

    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq('jugando', $e['estado']);
    assertEq(0, $e['indice']);
    assertEq(54, $e['total']);
    assertTrue(isset($e['carta_actual']['nombre']));
    assertEq([], $e['ultimas']);

    $tableroAna = array_column($j1['tablero'], 'id');
    $orden = json_decode($pdo->query("SELECT orden_cartas FROM partidas WHERE codigo='$cod'")->fetchColumn(), true);
    $pos = max(array_map(fn($id) => array_search($id, $orden), $tableroAna));
    $noSalida = null;
    foreach ($tableroAna as $id) if (array_search($id, $orden) > 0) { $noSalida = $id; break; }
    if ($noSalida !== null) {
        $m = llamar('marcar', ['token' => $j1['token'], 'carta_id' => $noSalida]);
        assertTrue(!$m['ok'], 'no debe marcar carta no salida');
    }
    assertTrue(!llamar('gritar', ['token' => $j1['token']])['ok'], 'gritar sin 16 marcas');

    for ($i = 0; $i < $pos; $i++) assertTrue(llamar('siguiente', ['codigo' => $cod])['ok']);
    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq(min(8, $pos), count($e['ultimas']));
    if ($pos > 0) assertEq($orden[$pos - 1], $e['ultimas'][0]['id'], 'ultimas empieza por la anterior a la actual');

    foreach ($tableroAna as $id) {
        $m = llamar('marcar', ['token' => $j1['token'], 'carta_id' => $id]);
        assertTrue($m['ok'], "marcar $id: " . json_encode($m));
    }
    // marcar dos veces no duplica
    llamar('marcar', ['token' => $j1['token'], 'carta_id' => $tableroAna[0]]);
    assertEq(16, count(llamar('estado_jugador', ['token' => $j1['token']])['marcas']));

    assertTrue(llamar('desmarcar', ['token' => $j1['token'], 'carta_id' => $tableroAna[0]])['ok']);
    $ej = llamar('estado_jugador', ['token' => $j1['token']]);
    assertEq(15, count($ej['marcas']));
    assertTrue(!$ej['puede_gritar']);
    assertTrue(llamar('marcar', ['token' => $j1['token'], 'carta_id' => $tableroAna[0]])['ok']);
    $ej = llamar('estado_jugador', ['token' => $j1['token']]);
    assertTrue($ej['puede_gritar']);
    assertTrue(!isset($ej['salidas']) && !isset($ej['carta_actual']) && !isset($ej['indice']), 'el jugador no debe recibir cartas salidas');
    foreach ($ej['tablero'] as $c) assertTrue(!isset($c['salida']), 'el tablero no debe revelar cartas salidas');

    $g = llamar('gritar', ['token' => $j1['token']]);
    assertTrue($g['ok'] && $g['valido'] === true, json_encode($g));
    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq('pausada', $e['estado']);
    assertEq(1, count($e['gritos']));
    assertEq('Ana', $e['gritos'][0]['nombre']);
    assertEq(false, $e['gritos'][0]['atendido']);
    $ej = llamar('estado_jugador', ['token' => $j1['token']]);
    assertEq(true, $ej['mi_grito']['valido']);

    assertTrue(llamar('continuar', ['codigo' => $cod])['ok']);
    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq('jugando', $e['estado']);
    assertEq(true, $e['gritos'][0]['atendido']);
    assertTrue(llamar('pausar', ['codigo' => $cod])['ok']);
    assertEq('pausada', llamar('estado_presentador', ['codigo' => $cod])['estado']);
    assertTrue(llamar('terminar', ['codigo' => $cod, 'ganador_id' => $e['gritos'][0]['jugador_id']])['ok']);
    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq('terminada', $e['estado']);
    assertEq('Ana', $e['ganador']);
    $ej = llamar('estado_jugador', ['token' => $j2['token']]);
    assertEq('terminada', $ej['estado']);
    assertEq('Ana', $ej['ganador']);
    assertTrue(!llamar('siguiente', ['codigo' => $cod])['ok'], 'no avanzar tras terminar');
});

test('grito falso: marcas forzadas sin cartas salidas se detectan', function () {
    $pdo = getDB();
    $cod = llamar('crear_partida', ['mazo_id' => mazoDefault()])['codigo'];
    $j = llamar('unirse', ['codigo' => $cod, 'nombre' => 'Caro']);
    llamar('iniciar', ['codigo' => $cod]);
    $jid = $pdo->query("SELECT id FROM jugadores WHERE token='{$j['token']}'")->fetchColumn();
    foreach ($j['tablero'] as $c) $pdo->exec("INSERT INTO marcas (jugador_id, carta_id) VALUES ($jid, {$c['id']})");
    $g = llamar('gritar', ['token' => $j['token']]);
    assertTrue($g['ok']);
    assertEq(false, $g['valido']);
    assertEq('pausada', llamar('estado_presentador', ['codigo' => $cod])['estado']);
});

test('siguiente tras la última carta termina la partida', function () {
    $cod = llamar('crear_partida', ['mazo_id' => mazoDefault()])['codigo'];
    llamar('unirse', ['codigo' => $cod, 'nombre' => 'D']);
    llamar('iniciar', ['codigo' => $cod]);
    for ($i = 0; $i < 53; $i++) llamar('siguiente', ['codigo' => $cod]);
    assertEq(53, llamar('estado_presentador', ['codigo' => $cod])['indice']);
    llamar('siguiente', ['codigo' => $cod]);
    assertEq('terminada', llamar('estado_presentador', ['codigo' => $cod])['estado']);
});

test('config_auto guarda y estado lo devuelve', function () {
    $cod = llamar('crear_partida', ['mazo_id' => mazoDefault()])['codigo'];
    assertTrue(llamar('config_auto', ['codigo' => $cod, 'auto' => 1, 'intervalo_seg' => 4])['ok']);
    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq(true, $e['auto']);
    assertEq(4, $e['intervalo']);
    assertTrue(!llamar('config_auto', ['codigo' => $cod, 'auto' => 1, 'intervalo_seg' => 0])['ok']);
});

test('crear_partida rechaza mazo con menos de 16 cartas', function () {
    $pdo = getDB();
    $pdo->exec("INSERT INTO mazos (nombre) VALUES ('chico')");
    $id = $pdo->lastInsertId();
    assertTrue(!llamar('crear_partida', ['mazo_id' => $id])['ok']);
});

test('jugador con token inválido recibe error', function () {
    $r = llamar('estado_jugador', ['token' => 'nada']);
    assertTrue(!$r['ok']);
    assertEq('Jugador no encontrado', $r['error']);
});

test('borrar_partida elimina la partida y todo lo suyo', function () {
    $pdo = getDB();
    $cod = llamar('crear_partida', ['mazo_id' => mazoDefault()])['codigo'];
    $j = llamar('unirse', ['codigo' => $cod, 'nombre' => 'Efímero']);
    llamar('iniciar', ['codigo' => $cod]);
    $tablero = array_column($j['tablero'], 'id');
    $orden = json_decode($pdo->query("SELECT orden_cartas FROM partidas WHERE codigo='$cod'")->fetchColumn(), true);
    llamar('marcar', ['token' => $j['token'], 'carta_id' => $orden[0]]);
    $pid = (int)$pdo->query("SELECT id FROM partidas WHERE codigo='$cod'")->fetchColumn();
    $jid = (int)$pdo->query("SELECT id FROM jugadores WHERE partida_id=$pid")->fetchColumn();

    assertTrue(llamar('borrar_partida', ['codigo' => $cod])['ok']);
    assertEq(0, (int)$pdo->query("SELECT COUNT(*) FROM partidas WHERE id=$pid")->fetchColumn());
    assertEq(0, (int)$pdo->query("SELECT COUNT(*) FROM jugadores WHERE partida_id=$pid")->fetchColumn());
    assertEq(0, (int)$pdo->query("SELECT COUNT(*) FROM marcas WHERE jugador_id=$jid")->fetchColumn());
    assertEq(0, (int)$pdo->query("SELECT COUNT(*) FROM gritos WHERE partida_id=$pid")->fetchColumn());
    assertTrue(!llamar('borrar_partida', ['codigo' => $cod])['ok'], 'ya no existe');
    assertTrue(!llamar('estado_jugador', ['token' => $j['token']])['ok'], 'el jugador queda sin partida');
});

test('borrar_partidas_terminadas solo borra las terminadas', function () {
    $pdo = getDB();
    $viva = llamar('crear_partida', ['mazo_id' => mazoDefault()])['codigo'];
    $muerta = llamar('crear_partida', ['mazo_id' => mazoDefault()])['codigo'];
    llamar('unirse', ['codigo' => $muerta, 'nombre' => 'X']);
    llamar('iniciar', ['codigo' => $muerta]);
    llamar('terminar', ['codigo' => $muerta]);
    $r = llamar('borrar_partidas_terminadas');
    assertTrue($r['ok']);
    assertTrue($r['borradas'] >= 1);
    assertEq(0, (int)$pdo->query("SELECT COUNT(*) FROM partidas WHERE codigo='$muerta'")->fetchColumn());
    assertEq(1, (int)$pdo->query("SELECT COUNT(*) FROM partidas WHERE codigo='$viva'")->fetchColumn());
    assertEq(0, (int)$pdo->query("SELECT COUNT(*) FROM partidas WHERE estado='terminada'")->fetchColumn());
});

test('no se admiten dos jugadores con el mismo nombre en una partida', function () {
    $cod = llamar('crear_partida', ['mazo_id' => mazoDefault()])['codigo'];
    assertTrue(llamar('unirse', ['codigo' => $cod, 'nombre' => 'Ana'])['ok']);

    $r = llamar('unirse', ['codigo' => $cod, 'nombre' => 'Ana']);
    assertTrue(!$r['ok'], 'nombre idéntico');
    assertTrue(str_contains($r['error'], 'Ya hay un jugador con ese nombre'), $r['error']);

    assertTrue(!llamar('unirse', ['codigo' => $cod, 'nombre' => '  ana  '])['ok'], 'espacios y minúsculas');
    assertTrue(!llamar('unirse', ['codigo' => $cod, 'nombre' => 'ANÁ'])['ok'], 'acentos');
    assertTrue(llamar('unirse', ['codigo' => $cod, 'nombre' => 'Ana Sofía'])['ok'], 'nombre distinto sí entra');

    // El mismo nombre en otra partida no estorba
    $otra = llamar('crear_partida', ['mazo_id' => mazoDefault()])['codigo'];
    assertTrue(llamar('unirse', ['codigo' => $otra, 'nombre' => 'Ana'])['ok']);
});

test('cada jugador tiene un token distinto', function () {
    $cod = llamar('crear_partida', ['mazo_id' => mazoDefault()])['codigo'];
    $tokens = [];
    foreach (['Uno', 'Dos', 'Tres'] as $n) $tokens[] = llamar('unirse', ['codigo' => $cod, 'nombre' => $n])['token'];
    assertEq(3, count(array_unique($tokens)));
});

<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/juego.php';

function cargarPartida(string $codigo): array {
    $st = getDB()->prepare("SELECT * FROM partidas WHERE codigo = ?");
    $st->execute([strtoupper(trim($codigo))]);
    $p = $st->fetch();
    if (!$p) throw new ApiException('Partida no encontrada', 404);
    $p['orden'] = $p['orden_cartas'] ? json_decode($p['orden_cartas'], true) : [];
    return $p;
}

function actualizarPartida(int $id, array $campos): void {
    $sets = []; $vals = [];
    foreach ($campos as $k => $v) { $sets[] = "$k = ?"; $vals[] = $v; }
    $sets[] = 'actualizada = CURRENT_TIMESTAMP';
    $vals[] = $id;
    getDB()->prepare("UPDATE partidas SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
}

function cartaPorId(?int $id): ?array {
    if (!$id) return null;
    $st = getDB()->prepare("SELECT id, numero, nombre, imagen FROM cartas WHERE id = ?");
    $st->execute([$id]);
    $c = $st->fetch();
    return $c ? ['id' => (int)$c['id'], 'numero' => (int)$c['numero'], 'nombre' => $c['nombre'], 'imagen' => $c['imagen']] : null;
}

function nombreJugador(?int $id): ?string {
    if (!$id) return null;
    $st = getDB()->prepare("SELECT nombre FROM jugadores WHERE id = ?");
    $st->execute([$id]);
    return $st->fetchColumn() ?: null;
}

function api_crear_partida(): void {
    exigirClavePresentador();
    $pdo = getDB();
    $mazoId = (int)param('mazo_id', 0);
    $st = $pdo->prepare("SELECT COUNT(*) FROM cartas WHERE mazo_id = ?");
    $st->execute([$mazoId]);
    if ((int)$st->fetchColumn() < TABLERO_TAM) throw new ApiException('El mazo necesita al menos 16 cartas', 400);
    $codigo = '';
    for ($i = 0; $i < 10; $i++) {
        $codigo = generarCodigo();
        $st = $pdo->prepare("SELECT 1 FROM partidas WHERE codigo = ?");
        $st->execute([$codigo]);
        if (!$st->fetch()) break;
    }
    $pdo->prepare("INSERT INTO partidas (codigo, mazo_id) VALUES (?, ?)")->execute([$codigo, $mazoId]);
    jsonOk(['codigo' => $codigo]);
}

function api_estado_presentador(): void {
    exigirClavePresentador();
    $pdo = getDB();
    $p = cargarPartida((string)param('codigo', ''));
    $salidas = cartasSalidas($p['orden'], (int)$p['indice_actual']);
    $ultimas = [];
    foreach (array_slice(array_reverse($salidas), 1, 8) as $id) $ultimas[] = cartaPorId($id);
    $st = $pdo->prepare("SELECT j.id, j.nombre, (SELECT COUNT(*) FROM marcas m WHERE m.jugador_id = j.id) AS marcas
                         FROM jugadores j WHERE j.partida_id = ? ORDER BY j.id");
    $st->execute([$p['id']]);
    $jugadores = array_map(fn($j) => ['id' => (int)$j['id'], 'nombre' => $j['nombre'], 'marcas' => (int)$j['marcas']], $st->fetchAll());
    $st = $pdo->prepare("SELECT g.id, g.jugador_id, g.valido, g.atendido, g.hora, j.nombre FROM gritos g JOIN jugadores j ON j.id = g.jugador_id
                         WHERE g.partida_id = ? ORDER BY g.id");
    $st->execute([$p['id']]);
    $gritos = array_map(fn($g) => ['id' => (int)$g['id'], 'jugador_id' => (int)$g['jugador_id'], 'nombre' => $g['nombre'],
                                   'valido' => (bool)$g['valido'], 'atendido' => (bool)$g['atendido'], 'hora' => $g['hora']], $st->fetchAll());
    $total = count($p['orden']);
    if (!$total) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM cartas WHERE mazo_id = ?");
        $st->execute([$p['mazo_id']]);
        $total = (int)$st->fetchColumn();
    }
    jsonOk([
        'codigo' => $p['codigo'], 'estado' => $p['estado'], 'indice' => (int)$p['indice_actual'], 'total' => $total,
        'carta_actual' => $salidas ? cartaPorId(end($salidas)) : null, 'ultimas' => $ultimas,
        'jugadores' => $jugadores, 'gritos' => $gritos, 'ganador' => nombreJugador($p['ganador_id'] ? (int)$p['ganador_id'] : null),
        'auto' => (bool)$p['auto'], 'intervalo' => (int)$p['intervalo_seg'],
        'url_jugar' => urlBase() . '/jugar.php?c=' . $p['codigo'],
    ]);
}

function api_iniciar(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    if ($p['estado'] !== 'lobby') throw new ApiException('La partida ya inició', 400);
    $st = getDB()->prepare("SELECT id FROM cartas WHERE mazo_id = ?");
    $st->execute([$p['mazo_id']]);
    $orden = barajar(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
    actualizarPartida((int)$p['id'], ['estado' => 'jugando', 'orden_cartas' => json_encode($orden), 'indice_actual' => 0]);
    jsonOk();
}

function api_siguiente(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    if (!in_array($p['estado'], ['jugando', 'pausada'])) throw new ApiException('La partida no está en juego', 400);
    $i = (int)$p['indice_actual'] + 1;
    if ($i >= count($p['orden'])) {
        actualizarPartida((int)$p['id'], ['estado' => 'terminada']);
        jsonOk(['terminada' => true]);
        return;
    }
    actualizarPartida((int)$p['id'], ['indice_actual' => $i, 'estado' => 'jugando']);
    jsonOk(['indice' => $i]);
}

function api_pausar(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    if ($p['estado'] !== 'jugando') throw new ApiException('No se puede pausar', 400);
    actualizarPartida((int)$p['id'], ['estado' => 'pausada']);
    jsonOk();
}

function api_continuar(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    if ($p['estado'] !== 'pausada') throw new ApiException('La partida no está pausada', 400);
    getDB()->prepare("UPDATE gritos SET atendido = 1 WHERE partida_id = ?")->execute([$p['id']]);
    actualizarPartida((int)$p['id'], ['estado' => 'jugando']);
    jsonOk();
}

function api_terminar(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    if ($p['estado'] === 'terminada') throw new ApiException('La partida ya terminó', 400);
    $g = (int)param('ganador_id', 0) ?: null;
    getDB()->prepare("UPDATE gritos SET atendido = 1 WHERE partida_id = ?")->execute([$p['id']]);
    actualizarPartida((int)$p['id'], ['estado' => 'terminada', 'ganador_id' => $g]);
    jsonOk();
}

function api_config_auto(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    $auto = (int)(bool)param('auto', 0);
    $seg = (int)param('intervalo_seg', 6);
    if ($seg < 2 || $seg > 60) throw new ApiException('El intervalo debe estar entre 2 y 60 segundos', 400);
    actualizarPartida((int)$p['id'], ['auto' => $auto, 'intervalo_seg' => $seg]);
    jsonOk();
}

<?php
require_once __DIR__ . '/api_partidas.php';

function cargarJugador(string $token): array {
    $st = getDB()->prepare("SELECT j.*, p.codigo FROM jugadores j JOIN partidas p ON p.id = j.partida_id WHERE j.token = ?");
    $st->execute([$token]);
    $j = $st->fetch();
    if (!$j) throw new ApiException('Jugador no encontrado', 404);
    $j['tablero_ids'] = array_map('intval', json_decode($j['tablero'], true));
    $j['partida'] = cargarPartida($j['codigo']);
    return $j;
}

function marcasDe(int $jugadorId): array {
    $st = getDB()->prepare("SELECT carta_id FROM marcas WHERE jugador_id = ? ORDER BY carta_id");
    $st->execute([$jugadorId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Devuelve las cartas del tablero en el orden de $ids. Nunca incluye si ya salieron. */
function tableroConCartas(array $ids): array {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = getDB()->prepare("SELECT id, numero, nombre, imagen FROM cartas WHERE id IN ($in)");
    $st->execute($ids);
    $porId = [];
    foreach ($st->fetchAll() as $c) $porId[(int)$c['id']] = ['id' => (int)$c['id'], 'numero' => (int)$c['numero'], 'nombre' => $c['nombre'], 'imagen' => $c['imagen']];
    return array_values(array_filter(array_map(fn($id) => $porId[$id] ?? null, $ids)));
}

function api_unirse(): void {
    $pdo = getDB();
    $p = cargarPartida((string)param('codigo', ''));
    $nombre = trim((string)param('nombre', ''));
    if ($nombre === '' || mb_strlen($nombre) > 30) throw new ApiException('Escribe un nombre de 1 a 30 caracteres', 400);
    if ($p['estado'] !== 'lobby') throw new ApiException('La partida ya empezó, ya no se puede entrar', 400);
    $st = $pdo->prepare("SELECT nombre FROM jugadores WHERE partida_id = ?");
    $st->execute([$p['id']]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $usado) {
        if (normalizarNombre($usado) === normalizarNombre($nombre)) {
            throw new ApiException('Ya hay un jugador con ese nombre en la partida. Escribe otro, por ejemplo agrega tu apellido.', 409);
        }
    }
    $st = $pdo->prepare("SELECT id FROM cartas WHERE mazo_id = ?");
    $st->execute([$p['mazo_id']]);
    $idsMazo = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    $st = $pdo->prepare("SELECT tablero FROM jugadores WHERE partida_id = ?");
    $st->execute([$p['id']]);
    $existentes = array_map(fn($t) => json_decode($t, true), $st->fetchAll(PDO::FETCH_COLUMN));
    $tablero = generarTablero($idsMazo, $existentes);
    $token = generarToken();
    $pdo->prepare("INSERT INTO jugadores (partida_id, nombre, token, tablero) VALUES (?, ?, ?, ?)")
        ->execute([$p['id'], $nombre, $token, json_encode($tablero)]);
    jsonOk(['token' => $token, 'nombre' => $nombre, 'tablero' => tableroConCartas($tablero)]);
}

function api_estado_jugador(): void {
    $j = cargarJugador((string)param('token', ''));
    $p = $j['partida'];
    $marcas = marcasDe((int)$j['id']);
    $st = getDB()->prepare("SELECT valido, atendido FROM gritos WHERE jugador_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$j['id']]);
    $g = $st->fetch();
    jsonOk([
        'estado' => $p['estado'], 'nombre' => $j['nombre'], 'marcas' => $marcas,
        'puede_gritar' => count($marcas) === TABLERO_TAM && in_array($p['estado'], ['jugando', 'pausada']),
        'ganador' => nombreJugador($p['ganador_id'] ? (int)$p['ganador_id'] : null),
        'mi_grito' => $g ? ['valido' => (bool)$g['valido'], 'atendido' => (bool)$g['atendido']] : null,
        'tablero' => tableroConCartas($j['tablero_ids']),
    ]);
}

function api_marcar(): void {
    $j = cargarJugador((string)param('token', ''));
    $p = $j['partida'];
    if (!in_array($p['estado'], ['jugando', 'pausada'])) throw new ApiException('La partida no está en juego', 400);
    $cartaId = (int)param('carta_id', 0);
    $salidas = cartasSalidas($p['orden'], (int)$p['indice_actual']);
    if (!puedeMarcar($cartaId, $j['tablero_ids'], $salidas)) throw new ApiException('No se puede marcar', 400);
    getDB()->prepare(dbInsertIgnore("INSERT INTO marcas (jugador_id, carta_id) VALUES (?, ?)"))->execute([$j['id'], $cartaId]);
    jsonOk(['marcas' => marcasDe((int)$j['id'])]);
}

function api_desmarcar(): void {
    $j = cargarJugador((string)param('token', ''));
    $cartaId = (int)param('carta_id', 0);
    getDB()->prepare("DELETE FROM marcas WHERE jugador_id = ? AND carta_id = ?")->execute([$j['id'], $cartaId]);
    jsonOk(['marcas' => marcasDe((int)$j['id'])]);
}

function api_gritar(): void {
    $j = cargarJugador((string)param('token', ''));
    $p = $j['partida'];
    if (!in_array($p['estado'], ['jugando', 'pausada'])) throw new ApiException('La partida no está en juego', 400);
    $marcas = marcasDe((int)$j['id']);
    if (count($marcas) < TABLERO_TAM) throw new ApiException('Aún no llenas tu tablero', 400);
    $salidas = cartasSalidas($p['orden'], (int)$p['indice_actual']);
    $valido = esLoteriaValida($j['tablero_ids'], $salidas);
    getDB()->prepare("INSERT INTO gritos (partida_id, jugador_id, valido) VALUES (?, ?, ?)")->execute([$p['id'], $j['id'], (int)$valido]);
    actualizarPartida((int)$p['id'], ['estado' => 'pausada']);
    jsonOk(['valido' => $valido]);
}

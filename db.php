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
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/constantes.php';

function dbDsn(): string {
    return defined('LOTERIA_TEST_DSN') ? LOTERIA_TEST_DSN : DB_DSN;
}

function dbDriver(): string {
    return str_starts_with(dbDsn(), 'sqlite') ? 'sqlite' : 'mysql';
}

function dbInsertIgnore(string $sql): string {
    return dbDriver() === 'sqlite'
        ? preg_replace('/^INSERT INTO/i', 'INSERT OR IGNORE INTO', $sql)
        : preg_replace('/^INSERT INTO/i', 'INSERT IGNORE INTO', $sql);
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdo = dbDriver() === 'sqlite'
            ? new PDO(dbDsn(), null, null, $opts)
            : new PDO(dbDsn(), DB_USER, DB_PASS, $opts);
    } catch (PDOException $e) {
        http_response_code(500);
        $local = __DIR__ . '/config.local.php';
        $hayLocal = file_exists($local) ? 'sí existe' : 'NO existe';
        $usandoDefault = DB_USER === 'root' && DB_PASS === '';
        echo '<!doctype html><meta charset="utf-8"><title>Configuración pendiente</title>'
           . '<body style="font-family:system-ui;max-width:700px;margin:40px auto;line-height:1.5">'
           . '<h1>No se pudo conectar a la base de datos</h1>'
           . '<p><b>Detalle:</b> ' . htmlspecialchars($e->getMessage()) . '</p>'
           . ($usandoDefault ? '<p>Se están usando las credenciales por defecto (usuario <code>root</code> sin contraseña), así que <b>no se leyó tu configuración</b>.</p>' : '')
           . '<p>Archivo esperado: <code>' . htmlspecialchars($local) . '</code> &rarr; <b>' . $hayLocal . '</b>.</p>'
           . '<p>Crea ese archivo (en la misma carpeta que <code>index.php</code>) con:</p>'
           . '<pre style="background:#f3f3f3;padding:12px;border-radius:8px">&lt;?php
define(\'DB_DSN\', \'mysql:host=localhost;dbname=NOMBRE_BD;charset=utf8mb4\');
define(\'DB_USER\', \'USUARIO_BD\');
define(\'DB_PASS\', \'CONTRASEÑA\');</pre>'
           . '<p>La base de datos debe existir y el usuario tener privilegios sobre ella; las tablas se crean solas.</p></body>';
        exit;
    }
    if (dbDriver() === 'sqlite') $pdo->exec('PRAGMA foreign_keys = ON');
    crearTablas($pdo);
    require_once __DIR__ . '/lib/mazo_clasico.php';
    sembrarMazoClasico($pdo);
    return $pdo;
}

function crearTablas(PDO $pdo): void {
    $sqlite = dbDriver() === 'sqlite';
    $id  = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $now = 'DATETIME DEFAULT CURRENT_TIMESTAMP';
    $eng = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $pdo->exec("CREATE TABLE IF NOT EXISTS mazos (
        id $id, nombre VARCHAR(80) NOT NULL, es_default TINYINT NOT NULL DEFAULT 0, creado $now)$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cartas (
        id $id, mazo_id INT NOT NULL, numero INT NOT NULL, nombre VARCHAR(80) NOT NULL, imagen VARCHAR(255) NULL)$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS partidas (
        id $id, codigo VARCHAR(6) NOT NULL UNIQUE, mazo_id INT NOT NULL, estado VARCHAR(12) NOT NULL DEFAULT 'lobby',
        orden_cartas TEXT NULL, indice_actual INT NOT NULL DEFAULT -1, auto TINYINT NOT NULL DEFAULT 0,
        intervalo_seg INT NOT NULL DEFAULT 6, ganador_id INT NULL, creada $now, actualizada $now)$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS jugadores (
        id $id, partida_id INT NOT NULL, nombre VARCHAR(30) NOT NULL, token VARCHAR(40) NOT NULL UNIQUE,
        tablero TEXT NOT NULL, creado $now,
        UNIQUE (partida_id, nombre))$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS marcas (
        jugador_id INT NOT NULL, carta_id INT NOT NULL, marcada_en $now, PRIMARY KEY (jugador_id, carta_id))$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS gritos (
        id $id, partida_id INT NOT NULL, jugador_id INT NOT NULL, valido TINYINT NOT NULL,
        atendido TINYINT NOT NULL DEFAULT 0, hora $now)$eng");
}

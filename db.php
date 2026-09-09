<?php
require_once __DIR__ . '/config.php';

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
        die('Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage()));
    }
    if (dbDriver() === 'sqlite') $pdo->exec('PRAGMA foreign_keys = ON');
    crearTablas($pdo);
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
        tablero TEXT NOT NULL, creado $now)$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS marcas (
        jugador_id INT NOT NULL, carta_id INT NOT NULL, marcada_en $now, PRIMARY KEY (jugador_id, carta_id))$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS gritos (
        id $id, partida_id INT NOT NULL, jugador_id INT NOT NULL, valido TINYINT NOT NULL,
        atendido TINYINT NOT NULL DEFAULT 0, hora $now)$eng");
}

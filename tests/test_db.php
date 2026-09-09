<?php
if (!defined('LOTERIA_TEST_DSN')) define('LOTERIA_TEST_DSN', 'sqlite::memory:');
require_once __DIR__ . '/../db.php';

test('getDB crea las tablas en sqlite en memoria', function () {
    $pdo = getDB();
    $tablas = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['mazos', 'cartas', 'partidas', 'jugadores', 'marcas', 'gritos'] as $t) {
        assertTrue(in_array($t, $tablas), "falta tabla $t");
    }
});

test('dbInsertIgnore adapta la sintaxis al driver', function () {
    assertEq('INSERT OR IGNORE INTO x', dbInsertIgnore('INSERT INTO x'));
});

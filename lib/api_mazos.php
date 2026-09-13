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
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/http.php';

function cargarMazo(int $id): array {
    $st = getDB()->prepare("SELECT * FROM mazos WHERE id = ?");
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) throw new ApiException('Mazo no encontrado', 404);
    return $m;
}

function exigirMazoEditable(int $id): array {
    $m = cargarMazo($id);
    if ((int)$m['es_default'] === 1) throw new ApiException('El mazo clásico no se puede modificar', 400);
    return $m;
}

function borrarImagenSubida(?string $ruta): void {
    if ($ruta && str_starts_with($ruta, 'uploads/') && !str_contains($ruta, '..')) @unlink(__DIR__ . '/../' . $ruta);
}

/** Valida y guarda un archivo de $_FILES; devuelve la ruta relativa 'uploads/xxx.ext'. */
function guardarImagenSubida(array $f): string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new ApiException('Error al subir la imagen', 400);
    if ($f['size'] > 2 * 1024 * 1024) throw new ApiException('La imagen debe pesar menos de 2 MB', 400);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'][$mime] ?? null;
    if (!$ext) throw new ApiException('Solo se permiten PNG, JPG, WEBP o SVG', 400);
    $nombre = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = __DIR__ . '/../uploads/' . $nombre;
    if (!move_uploaded_file($f['tmp_name'], $dest)) throw new ApiException('No se pudo guardar la imagen (revisa permisos de uploads/)', 500);
    return 'uploads/' . $nombre;
}

function validarNombre(string $nombre, int $max = 80): string {
    $nombre = trim($nombre);
    if ($nombre === '' || mb_strlen($nombre) > $max) throw new ApiException("Nombre de 1 a $max caracteres", 400);
    return $nombre;
}

function api_mazos_listar(): void {
    exigirClavePresentador();
    $rows = getDB()->query("SELECT m.id, m.nombre, m.es_default, (SELECT COUNT(*) FROM cartas c WHERE c.mazo_id = m.id) AS cartas
                            FROM mazos m ORDER BY m.es_default DESC, m.nombre")->fetchAll();
    jsonOk(['mazos' => array_map(fn($m) => ['id' => (int)$m['id'], 'nombre' => $m['nombre'], 'es_default' => (bool)$m['es_default'], 'cartas' => (int)$m['cartas']], $rows)]);
}

function api_mazo_crear(): void {
    exigirClavePresentador();
    $nombre = validarNombre((string)param('nombre', ''));
    getDB()->prepare("INSERT INTO mazos (nombre) VALUES (?)")->execute([$nombre]);
    jsonOk(['id' => (int)getDB()->lastInsertId()]);
}

function api_mazo_renombrar(): void {
    exigirClavePresentador();
    $m = exigirMazoEditable((int)param('id', 0));
    $nombre = validarNombre((string)param('nombre', ''));
    getDB()->prepare("UPDATE mazos SET nombre = ? WHERE id = ?")->execute([$nombre, $m['id']]);
    jsonOk();
}

function api_mazo_borrar(): void {
    exigirClavePresentador();
    $pdo = getDB();
    $m = exigirMazoEditable((int)param('id', 0));
    $st = $pdo->prepare("SELECT COUNT(*) FROM partidas WHERE mazo_id = ?");
    $st->execute([$m['id']]);
    if ((int)$st->fetchColumn() > 0) throw new ApiException('Este mazo tiene partidas; no se puede borrar', 400);
    $st = $pdo->prepare("SELECT imagen FROM cartas WHERE mazo_id = ?");
    $st->execute([$m['id']]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $img) borrarImagenSubida($img);
    $pdo->prepare("DELETE FROM cartas WHERE mazo_id = ?")->execute([$m['id']]);
    $pdo->prepare("DELETE FROM mazos WHERE id = ?")->execute([$m['id']]);
    jsonOk();
}

function api_mazo_cartas(): void {
    exigirClavePresentador();
    $m = cargarMazo((int)param('mazo_id', 0));
    $st = getDB()->prepare("SELECT id, numero, nombre, imagen FROM cartas WHERE mazo_id = ? ORDER BY numero");
    $st->execute([$m['id']]);
    jsonOk(['mazo' => ['id' => (int)$m['id'], 'nombre' => $m['nombre'], 'es_default' => (bool)$m['es_default']],
            'cartas' => array_map(fn($c) => ['id' => (int)$c['id'], 'numero' => (int)$c['numero'], 'nombre' => $c['nombre'], 'imagen' => $c['imagen']], $st->fetchAll())]);
}

function api_carta_guardar(): void {
    exigirClavePresentador();
    $pdo = getDB();
    $m = exigirMazoEditable((int)param('mazo_id', 0));
    $id = (int)param('id', 0);
    $numero = (int)param('numero', 0);
    if ($numero < 1 || $numero > 999) throw new ApiException('Número entre 1 y 999', 400);
    $nombre = validarNombre((string)param('nombre', ''));
    $st = $pdo->prepare("SELECT id FROM cartas WHERE mazo_id = ? AND numero = ? AND id <> ?");
    $st->execute([$m['id'], $numero, $id]);
    if ($st->fetch()) throw new ApiException("Ya existe una carta con el número $numero", 400);
    $imagen = null;
    if (!empty($_FILES['imagen']['name'])) $imagen = guardarImagenSubida($_FILES['imagen']);
    if ($id) {
        $st = $pdo->prepare("SELECT imagen FROM cartas WHERE id = ? AND mazo_id = ?");
        $st->execute([$id, $m['id']]);
        $prev = $st->fetch();
        if (!$prev) throw new ApiException('Carta no encontrada', 404);
        if ($imagen) borrarImagenSubida($prev['imagen']);
        $pdo->prepare("UPDATE cartas SET numero = ?, nombre = ?, imagen = COALESCE(?, imagen) WHERE id = ?")->execute([$numero, $nombre, $imagen, $id]);
    } else {
        $pdo->prepare("INSERT INTO cartas (mazo_id, numero, nombre, imagen) VALUES (?, ?, ?, ?)")->execute([$m['id'], $numero, $nombre, $imagen]);
        $id = (int)$pdo->lastInsertId();
    }
    jsonOk(['id' => $id]);
}

function api_carta_borrar(): void {
    exigirClavePresentador();
    $pdo = getDB();
    $id = (int)param('id', 0);
    $st = $pdo->prepare("SELECT c.imagen, m.es_default FROM cartas c JOIN mazos m ON m.id = c.mazo_id WHERE c.id = ?");
    $st->execute([$id]);
    $c = $st->fetch();
    if (!$c) throw new ApiException('Carta no encontrada', 404);
    if ((int)$c['es_default'] === 1) throw new ApiException('El mazo clásico no se puede modificar', 400);
    borrarImagenSubida($c['imagen']);
    $pdo->prepare("DELETE FROM cartas WHERE id = ?")->execute([$id]);
    jsonOk();
}

<?php
// Nombres y numeración tradicionales de la Lotería mexicana. Las ilustraciones (cartas/*.svg) son propias.
const MAZO_CLASICO = [
 [1,'El Gallo'],[2,'El Diablito'],[3,'La Dama'],[4,'El Catrín'],[5,'El Paraguas'],[6,'La Sirena'],
 [7,'La Escalera'],[8,'La Botella'],[9,'El Barril'],[10,'El Árbol'],[11,'El Melón'],[12,'El Valiente'],
 [13,'El Gorrito'],[14,'La Muerte'],[15,'La Pera'],[16,'La Bandera'],[17,'El Bandolón'],[18,'El Violoncello'],
 [19,'La Garza'],[20,'El Pájaro'],[21,'La Mano'],[22,'La Bota'],[23,'La Luna'],[24,'El Cotorro'],
 [25,'El Borracho'],[26,'El Negrito'],[27,'El Corazón'],[28,'La Sandía'],[29,'El Tambor'],[30,'El Camarón'],
 [31,'Las Jaras'],[32,'El Músico'],[33,'La Araña'],[34,'El Soldado'],[35,'La Estrella'],[36,'El Cazo'],
 [37,'El Mundo'],[38,'El Apache'],[39,'El Nopal'],[40,'El Alacrán'],[41,'La Rosa'],[42,'La Calavera'],
 [43,'La Campana'],[44,'El Cantarito'],[45,'El Venado'],[46,'El Sol'],[47,'La Corona'],[48,'La Chalupa'],
 [49,'El Pino'],[50,'El Pescado'],[51,'La Palma'],[52,'La Maceta'],[53,'El Arpa'],[54,'La Rana'],
];

/** Crea el mazo clásico si no existe ninguno marcado como default. Devuelve su id. */
function sembrarMazoClasico(PDO $pdo): int {
    $id = $pdo->query("SELECT id FROM mazos WHERE es_default=1 LIMIT 1")->fetchColumn();
    if ($id) return (int)$id;
    $pdo->prepare("INSERT INTO mazos (nombre, es_default) VALUES (?, 1)")->execute(['Lotería clásica']);
    $id = (int)$pdo->lastInsertId();
    $st = $pdo->prepare("INSERT INTO cartas (mazo_id, numero, nombre, imagen) VALUES (?, ?, ?, ?)");
    foreach (MAZO_CLASICO as [$n, $nombre]) $st->execute([$id, $n, $nombre, sprintf('cartas/%02d.svg', $n)]);
    return $id;
}

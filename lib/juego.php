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
// Lógica pura del juego. Sin base de datos, sin estado global.

const CODIGO_ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
const TABLERO_TAM = 16;

function generarCodigo(): string {
    $s = '';
    $n = strlen(CODIGO_ALFABETO);
    for ($i = 0; $i < 6; $i++) $s .= CODIGO_ALFABETO[random_int(0, $n - 1)];
    return $s;
}

function generarToken(): string {
    return bin2hex(random_bytes(16));
}

function barajar(array $ids): array {
    $ids = array_values($ids);
    for ($i = count($ids) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    }
    return $ids;
}

/** 16 ids únicos del mazo; intenta no repetir el conjunto de otro tablero de la partida. */
function generarTablero(array $idsMazo, array $tablerosExistentes): array {
    $existentes = array_map(function ($t) { sort($t); return implode(',', $t); }, $tablerosExistentes);
    $tablero = [];
    for ($intento = 0; $intento < 20; $intento++) {
        $tablero = array_slice(barajar($idsMazo), 0, TABLERO_TAM);
        $clave = $tablero; sort($clave);
        if (!in_array(implode(',', $clave), $existentes, true)) break;
    }
    return array_map('intval', $tablero);
}

/**
 * Forma comparable de un nombre: sin mayúsculas, sin acentos y con los
 * espacios colapsados, para que "Ana", " ana " y "ANÁ" cuenten como el mismo.
 */
function normalizarNombre(string $nombre): string {
    $n = mb_strtolower(trim($nombre), 'UTF-8');
    $n = strtr($n, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u']);
    return preg_replace('/\s+/u', ' ', $n);
}

function cartasSalidas(array $orden, int $indice): array {
    if ($indice < 0) return [];
    return array_map('intval', array_slice($orden, 0, $indice + 1));
}

function puedeMarcar(int $cartaId, array $tablero, array $salidas): bool {
    return in_array($cartaId, $tablero, true) && in_array($cartaId, $salidas, true);
}

function esLoteriaValida(array $tablero, array $salidas): bool {
    if (count($tablero) !== TABLERO_TAM) return false;
    return count(array_diff($tablero, $salidas)) === 0;
}

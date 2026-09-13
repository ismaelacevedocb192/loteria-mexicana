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
// Genera cartas/NN.svg para el mazo clásico. Ilustraciones geométricas propias.
// Uso: php tools/generar_cartas.php
require_once __DIR__ . '/../lib/mazo_clasico.php';

const R = '#c8412b'; const Y = '#e0a526'; const G = '#2b6f5c'; const B = '#1f3a5f'; const K = '#2a2a2a';
const CREMA = '#f6e7c8'; const PIEL = '#e8b98a'; const CAFE = '#8b5a2b'; const ROSA = '#e58aa0'; const CIELO = '#7fb3d5';

function estrella(float $cx, float $cy, float $r, string $fill, int $p = 5): string {
    $pts = [];
    for ($i = 0; $i < $p * 2; $i++) {
        $rr = $i % 2 ? $r * 0.42 : $r;
        $a = -M_PI / 2 + $i * M_PI / $p;
        $pts[] = sprintf('%.1f,%.1f', $cx + $rr * cos($a), $cy + $rr * sin($a));
    }
    return '<polygon points="' . implode(' ', $pts) . '" fill="' . $fill . '"/>';
}

function cara(float $cx, float $cy, float $r, string $piel = PIEL, string $gesto = 'sonrisa'): string {
    $ojo = $r * 0.12;
    $boca = $gesto === 'sonrisa'
        ? sprintf('<path d="M%.1f %.1f q%.1f %.1f %.1f 0" stroke="%s" stroke-width="3" fill="none"/>', $cx - $r * 0.35, $cy + $r * 0.3, $r * 0.35, $r * 0.3, $r * 0.7, K)
        : sprintf('<path d="M%.1f %.1f q%.1f -%.1f %.1f 0" stroke="%s" stroke-width="3" fill="none"/>', $cx - $r * 0.3, $cy + $r * 0.4, $r * 0.3, $r * 0.2, $r * 0.6, K);
    return sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s" stroke="%s" stroke-width="3"/>', $cx, $cy, $r, $piel, K)
        . sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s"/><circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s"/>', $cx - $r * 0.35, $cy - $r * 0.15, $ojo, K, $cx + $r * 0.35, $cy - $r * 0.15, $ojo, K)
        . $boca;
}

/** Persona de pie: cabeza en (150,130), cuerpo hasta y=320. $ropa color, $extra svg encima. */
function persona(string $ropa, string $extra = '', string $piel = PIEL, string $gesto = 'sonrisa'): string {
    return '<rect x="118" y="230" width="26" height="90" rx="6" fill="' . B . '"/><rect x="156" y="230" width="26" height="90" rx="6" fill="' . B . '"/>'
        . '<rect x="105" y="165" width="90" height="80" rx="18" fill="' . $ropa . '" stroke="' . K . '" stroke-width="3"/>'
        . '<rect x="80" y="172" width="24" height="70" rx="12" fill="' . $ropa . '" stroke="' . K . '" stroke-width="3"/>'
        . '<rect x="196" y="172" width="24" height="70" rx="12" fill="' . $ropa . '" stroke="' . K . '" stroke-width="3"/>'
        . '<rect x="112" y="312" width="38" height="16" rx="6" fill="' . K . '"/><rect x="150" y="312" width="38" height="16" rx="6" fill="' . K . '"/>'
        . cara(150, 130, 34, $piel, $gesto) . $extra;
}

$f = [];

$f[1] = // El Gallo
 '<ellipse cx="150" cy="230" rx="70" ry="55" fill="' . R . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M205 210 q60 -60 40 -110 q-10 40 -40 60 q20 -50 -10 -80 q-5 50 -30 70 z" fill="' . G . '" stroke="' . K . '" stroke-width="3"/>
  <circle cx="105" cy="150" r="30" fill="' . R . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M95 122 q-5 -25 10 -25 q5 15 15 0 q10 0 8 20 z" fill="' . R . '"/>
  <polygon points="75,150 50,158 75,166" fill="' . Y . '"/>
  <path d="M100 178 q-5 25 10 25 q5 -15 5 -25" fill="' . R . '"/>
  <circle cx="98" cy="145" r="4" fill="' . K . '"/>
  <rect x="120" y="280" width="8" height="40" fill="' . Y . '"/><rect x="160" y="280" width="8" height="40" fill="' . Y . '"/>
  <path d="M110 320 h28 M150 320 h28" stroke="' . Y . '" stroke-width="6"/>';

$f[2] = // El Diablo
 persona(R, '<polygon points="122,102 112,70 138,96" fill="' . R . '" stroke="' . K . '" stroke-width="3"/>
  <polygon points="178,102 188,70 162,96" fill="' . R . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M195 250 q60 20 40 70 q-5 -30 -30 -40" stroke="' . R . '" stroke-width="10" fill="none" stroke-linecap="round"/>
  <polygon points="233,318 252,308 244,330" fill="' . R . '"/>
  <line x1="60" y1="100" x2="80" y2="230" stroke="' . CAFE . '" stroke-width="6"/>
  <polygon points="45,100 60,80 75,100 60,92" fill="' . K . '"/>', PIEL, 'pícaro') ;

$f[3] = // La Dama
 '<path d="M150 165 L60 320 H240 Z" fill="' . ROSA . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M80 300 h140 M95 270 h110" stroke="' . R . '" stroke-width="4" fill="none"/>
  <rect x="122" y="160" width="56" height="30" fill="' . ROSA . '"/>
  <path d="M110 105 q40 -40 80 0 v40 q-40 20 -80 0 z" fill="' . K . '"/>'
 . cara(150, 130, 32) .
 '<path d="M118 100 q32 -25 64 0 q-32 -10 -64 0" fill="' . K . '"/>
  <circle cx="115" cy="160" r="8" fill="' . Y . '"/><circle cx="185" cy="160" r="8" fill="' . Y . '"/>
  <path d="M140 200 q10 40 -30 60" stroke="' . R . '" stroke-width="5" fill="none"/>
  ' . estrella(115, 268, 14, Y) ;

$f[4] = // El Catrín
 persona(K, '<rect x="108" y="88" width="84" height="12" fill="' . K . '"/><rect x="122" y="52" width="56" height="40" fill="' . K . '"/>
  <rect x="122" y="80" width="56" height="8" fill="' . R . '"/>
  <polygon points="135,166 150,190 165,166" fill="' . CREMA . '"/><rect x="146" y="178" width="8" height="30" fill="' . R . '"/>
  <line x1="215" y1="235" x2="235" y2="320" stroke="' . CAFE . '" stroke-width="6" stroke-linecap="round"/>
  <circle cx="213" cy="232" r="8" fill="' . Y . '"/>');

$f[5] = // El Paraguas
 '<path d="M50 200 q100 -130 200 0 z" fill="' . R . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M50 200 q25 -20 50 0 q25 -20 50 0 q25 -20 50 0 q25 -20 50 0" fill="none" stroke="' . K . '" stroke-width="4"/>
  <path d="M150 138 v-26" stroke="' . K . '" stroke-width="5" stroke-linecap="round"/>
  <path d="M150 200 v100 q0 25 -22 25 q-18 0 -18 -18" stroke="' . CAFE . '" stroke-width="8" fill="none" stroke-linecap="round"/>
  <g fill="' . CIELO . '"><ellipse cx="70" cy="250" rx="4" ry="9"/><ellipse cx="230" cy="260" rx="4" ry="9"/><ellipse cx="95" cy="300" rx="4" ry="9"/><ellipse cx="205" cy="310" rx="4" ry="9"/></g>';

$f[6] = // La Sirena
 '<path d="M40 300 q30 -15 60 0 t60 0 t60 0 t40 0 v30 H40 z" fill="' . CIELO . '"/>
  <path d="M120 200 q-10 70 30 100 q20 -20 10 -40 q40 -10 60 20 q10 -60 -50 -80 z" fill="' . G . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M130 215 q20 20 40 5 M125 245 q25 20 50 0" stroke="' . K . '" stroke-width="2" fill="none"/>
  <path d="M112 160 q38 -20 76 0 v45 q-38 10 -76 0 z" fill="' . PIEL . '" stroke="' . K . '" stroke-width="3"/>
  <circle cx="135" cy="182" r="10" fill="' . ROSA . '"/><circle cx="165" cy="182" r="10" fill="' . ROSA . '"/>
  <path d="M110 100 q40 -45 80 0 v70 q-15 -10 -12 -40 q-28 15 -56 0 q3 30 -12 40 z" fill="' . CAFE . '"/>'
 . cara(150, 122, 30) . estrella(70, 110, 12, Y) . estrella(235, 130, 9, Y);

$f[7] = // La Escalera
 '<line x1="95" y1="75" x2="95" y2="325" stroke="' . CAFE . '" stroke-width="12" stroke-linecap="round"/>
  <line x1="205" y1="75" x2="205" y2="325" stroke="' . CAFE . '" stroke-width="12" stroke-linecap="round"/>
  <g stroke="' . Y . '" stroke-width="10" stroke-linecap="round"><line x1="95" y1="105" x2="205" y2="105"/><line x1="95" y1="150" x2="205" y2="150"/><line x1="95" y1="195" x2="205" y2="195"/><line x1="95" y1="240" x2="205" y2="240"/><line x1="95" y1="285" x2="205" y2="285"/></g>';

$f[8] = // La Botella
 '<path d="M130 80 h40 v40 q30 20 30 60 v130 q0 20 -20 20 h-60 q-20 0 -20 -20 v-130 q0 -40 30 -60 z" fill="' . G . '" stroke="' . K . '" stroke-width="5"/>
  <rect x="125" y="70" width="50" height="16" rx="4" fill="' . R . '"/>
  <rect x="110" y="200" width="80" height="60" fill="' . CREMA . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M120 215 h60 M120 230 h60 M120 245 h40" stroke="' . B . '" stroke-width="3"/>
  <path d="M112 140 q-5 60 0 150" stroke="#fff" stroke-width="4" opacity=".5" fill="none"/>';

$f[9] = // El Barril
 '<path d="M85 100 q65 -20 130 0 q25 100 0 200 q-65 20 -130 0 q-25 -100 0 -200 z" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4"/>
  <g fill="none" stroke="' . K . '" stroke-width="2"><path d="M110 105 q-15 95 0 190 M150 100 q-8 100 0 200 M190 105 q15 95 0 190"/></g>
  <g stroke="' . Y . '" stroke-width="12"><path d="M76 140 q74 -18 148 0" fill="none"/><path d="M70 200 q80 -14 160 0" fill="none"/><path d="M76 260 q74 18 148 0" fill="none"/></g>';

$f[10] = // El Árbol
 '<rect x="130" y="230" width="40" height="95" fill="' . CAFE . '"/>
  <circle cx="150" cy="180" r="70" fill="' . G . '"/><circle cx="100" cy="210" r="45" fill="' . G . '"/><circle cx="200" cy="210" r="45" fill="' . G . '"/>
  <circle cx="150" cy="130" r="45" fill="' . G . '"/>
  <g fill="' . R . '"><circle cx="120" cy="170" r="9"/><circle cx="180" cy="150" r="9"/><circle cx="150" cy="210" r="9"/><circle cx="95" cy="225" r="9"/><circle cx="210" cy="230" r="9"/></g>
  <path d="M60 325 h180" stroke="' . G . '" stroke-width="6"/>';

$f[11] = // El Melón
 '<path d="M60 200 a90 90 0 0 0 180 0 z" fill="' . Y . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M75 200 a75 75 0 0 0 150 0 z" fill="' . ROSA . '"/>
  <path d="M60 200 h180" stroke="' . K . '" stroke-width="4"/>
  <g fill="' . K . '"><ellipse cx="120" cy="230" rx="5" ry="8"/><ellipse cx="150" cy="245" rx="5" ry="8"/><ellipse cx="180" cy="230" rx="5" ry="8"/><ellipse cx="135" cy="215" rx="4" ry="6"/><ellipse cx="165" cy="215" rx="4" ry="6"/></g>
  <circle cx="150" cy="130" r="35" fill="' . G . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M120 120 q30 -20 60 0 M125 140 q25 15 50 0" stroke="' . K . '" stroke-width="2" fill="none"/>';

$f[12] = // El Valiente
 persona(CREMA, '<path d="M100 90 q50 -30 100 0 l-10 -10 h-80 z" fill="' . K . '"/>
  <rect x="112" y="60" width="76" height="34" rx="6" fill="' . Y . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M85 88 q65 22 130 0" stroke="' . Y . '" stroke-width="10" fill="none"/>
  <path d="M75 250 l-30 60" stroke="' . K . '" stroke-width="7" stroke-linecap="round"/><circle cx="46" cy="308" r="8" fill="' . CAFE . '"/>
  <path d="M225 250 l40 -90" stroke="#bbb" stroke-width="8" stroke-linecap="round"/><rect x="216" y="240" width="18" height="18" rx="4" fill="' . CAFE . '"/>
  <rect x="110" y="200" width="80" height="14" fill="' . R . '"/>');

$f[13] = // El Gorrito
 '<path d="M70 250 q80 -190 160 0 z" fill="' . ROSA . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M55 250 h190" stroke="' . K . '" stroke-width="10" stroke-linecap="round"/>
  <path d="M100 250 q10 30 -5 60 M200 250 q-10 30 5 60" stroke="' . R . '" stroke-width="6" fill="none" stroke-linecap="round"/>
  <path d="M150 80 q-10 -20 5 -30" stroke="' . K . '" stroke-width="4" fill="none"/><circle cx="150" cy="50" r="6" fill="' . Y . '"/>
  <g fill="' . CREMA . '"><circle cx="150" cy="170" r="10"/><circle cx="110" cy="220" r="8"/><circle cx="190" cy="220" r="8"/><circle cx="130" cy="130" r="6"/></g>';

$f[14] = // La Muerte
 '<path d="M150 90 q-20 -5 -30 30 q-30 130 30 210 q60 -80 30 -210 q-10 -35 -30 -30 z" fill="' . K . '"/>
  <circle cx="150" cy="140" r="36" fill="' . CREMA . '" stroke="' . K . '" stroke-width="3"/>
  <ellipse cx="136" cy="135" rx="9" ry="12" fill="' . K . '"/><ellipse cx="164" cy="135" rx="9" ry="12" fill="' . K . '"/>
  <path d="M132 165 h36 M138 158 v14 M150 158 v14 M162 158 v14" stroke="' . K . '" stroke-width="3"/>
  <path d="M100 170 l40 20 M215 100 q-25 40 -60 55" stroke="' . CAFE . '" stroke-width="6" stroke-linecap="round"/>
  <path d="M215 100 q40 -45 -10 -60 q-20 30 10 60 z" fill="#bbb" stroke="' . K . '" stroke-width="3"/>
  <line x1="215" y1="100" x2="80" y2="330" stroke="' . CAFE . '" stroke-width="6" stroke-linecap="round"/>';

$f[15] = // La Pera
 '<path d="M150 120 q-25 40 -55 90 q-25 60 15 100 q40 25 80 0 q40 -40 15 -100 q-30 -50 -55 -90 z" fill="' . Y . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M150 120 q5 -30 15 -45" stroke="' . CAFE . '" stroke-width="6" stroke-linecap="round" fill="none"/>
  <path d="M158 95 q40 -30 40 10 q-30 5 -40 -10 z" fill="' . G . '"/>
  <circle cx="115" cy="250" r="14" fill="#fff" opacity=".35"/>';

$f[16] = // La Bandera
 '<rect x="60" y="80" width="10" height="250" fill="' . CAFE . '"/><circle cx="65" cy="76" r="8" fill="' . Y . '"/>
  <path d="M70 90 h180 q-20 20 0 40 q-20 20 0 40 q-20 20 0 40 h-180 z" fill="' . G . '"/>
  <path d="M130 90 h60 q-5 20 0 40 q-5 20 0 40 q-5 20 0 40 h-60 q5 -20 0 -40 q5 -20 0 -40 q5 -20 0 -40 z" fill="' . CREMA . '"/>
  <path d="M190 90 h60 q-20 20 0 40 q-20 20 0 40 q-20 20 0 40 h-60 q-5 -20 0 -40 q-5 -20 0 -40 q-5 -20 0 -40 z" fill="' . R . '"/>
  <circle cx="158" cy="150" r="16" fill="' . CAFE . '"/><path d="M148 140 q10 -12 20 0 q-10 20 -20 0 z" fill="' . G . '"/>';

$f[17] = // El Bandolón
 '<circle cx="150" cy="240" r="75" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4"/>
  <circle cx="150" cy="240" r="30" fill="' . K . '"/><circle cx="150" cy="240" r="24" fill="' . Y . '" opacity=".3"/>
  <rect x="135" y="70" width="30" height="120" rx="4" fill="' . K . '"/>
  <g stroke="' . Y . '" stroke-width="1.5"><line x1="140" y1="75" x2="140" y2="300"/><line x1="145" y1="75" x2="145" y2="300"/><line x1="150" y1="75" x2="150" y2="300"/><line x1="155" y1="75" x2="155" y2="300"/><line x1="160" y1="75" x2="160" y2="300"/></g>
  <rect x="130" y="60" width="40" height="22" rx="4" fill="' . R . '"/>
  <rect x="125" y="292" width="50" height="10" rx="3" fill="' . K . '"/>';

$f[18] = // El Violonchelo
 '<path d="M150 120 q-55 0 -50 60 q10 20 -5 40 q-40 50 5 100 q30 20 50 5 q20 15 50 -5 q45 -50 5 -100 q-15 -20 -5 -40 q5 -60 -50 -60 z" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4"/>
  <rect x="142" y="55" width="16" height="90" fill="' . K . '"/><rect x="136" y="50" width="28" height="18" rx="4" fill="' . K . '"/>
  <g stroke="' . Y . '" stroke-width="1.5"><line x1="145" y1="70" x2="145" y2="300"/><line x1="150" y1="70" x2="150" y2="300"/><line x1="155" y1="70" x2="155" y2="300"/></g>
  <path d="M120 200 q-6 15 6 30 M180 200 q6 15 -6 30" stroke="' . K . '" stroke-width="3" fill="none"/>
  <rect x="130" y="250" width="40" height="8" rx="2" fill="' . K . '"/>
  <line x1="60" y1="120" x2="230" y2="290" stroke="' . K . '" stroke-width="4"/>
  <line x1="150" y1="325" x2="150" y2="345" stroke="' . K . '" stroke-width="4"/>';

$f[19] = // La Garza
 '<path d="M40 320 q40 -20 80 0 t80 0 t60 0" stroke="' . CIELO . '" stroke-width="8" fill="none"/>
  <line x1="150" y1="230" x2="150" y2="320" stroke="' . Y . '" stroke-width="5"/><line x1="170" y1="240" x2="185" y2="320" stroke="' . Y . '" stroke-width="5"/>
  <ellipse cx="160" cy="215" rx="60" ry="35" fill="' . CREMA . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M110 200 q-30 20 -40 40 q20 -10 50 -20" fill="' . CREMA . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M195 200 q10 -70 -5 -110" stroke="' . CREMA . '" stroke-width="14" fill="none" stroke-linecap="round"/>
  <path d="M195 200 q10 -70 -5 -110" stroke="' . K . '" stroke-width="18" fill="none" stroke-linecap="round" opacity="0"/>
  <circle cx="190" cy="85" r="18" fill="' . CREMA . '" stroke="' . K . '" stroke-width="3"/>
  <polygon points="206,82 250,92 206,96" fill="' . Y . '"/><circle cx="192" cy="80" r="3" fill="' . K . '"/>
  <path d="M175 75 q-10 -15 5 -20" stroke="' . K . '" stroke-width="3" fill="none"/>';

$f[20] = // El Pájaro
 '<path d="M60 300 q30 -30 60 0" stroke="' . CAFE . '" stroke-width="8" fill="none"/>
  <line x1="40" y1="290" x2="260" y2="290" stroke="' . CAFE . '" stroke-width="8" stroke-linecap="round"/>
  <ellipse cx="150" cy="215" rx="55" ry="45" fill="' . CIELO . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M120 215 q-10 -40 30 -40 q30 10 20 40 z" fill="' . B . '"/>
  <circle cx="190" cy="180" r="26" fill="' . CIELO . '" stroke="' . K . '" stroke-width="3"/>
  <polygon points="212,175 245,185 212,192" fill="' . Y . '"/><circle cx="195" cy="175" r="4" fill="' . K . '"/>
  <path d="M95 225 q-50 10 -55 40 q30 -15 60 -15 z" fill="' . B . '"/>
  <path d="M140 258 v30 M160 258 v30" stroke="' . Y . '" stroke-width="4"/>';

$f[21] = // La Mano
 '<path d="M110 330 v-110 q0 -15 15 -15 q15 0 15 15 v-80 q0 -15 15 -15 q15 0 15 15 v70 q0 -15 15 -15 q15 0 15 15 v-40 q0 -15 15 -15 q15 0 15 15 v90 q0 60 -60 70 z" fill="' . PIEL . '" stroke="' . K . '" stroke-width="4" stroke-linejoin="round"/>
  <path d="M110 240 q-30 -30 -40 -60 q-5 -15 8 -18 q10 0 15 10 q12 30 30 45 z" fill="' . PIEL . '" stroke="' . K . '" stroke-width="4" stroke-linejoin="round"/>
  <rect x="100" y="300" width="100" height="30" fill="' . R . '"/>
  <circle cx="185" cy="245" r="6" fill="' . Y . '"/>';

$f[22] = // La Bota
 '<path d="M110 80 h60 v140 q0 20 20 30 l50 25 q15 8 5 30 h-150 v-80 q15 -20 15 -40 z" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4" stroke-linejoin="round"/>
  <path d="M95 300 h150" stroke="' . K . '" stroke-width="6"/>
  <rect x="105" y="72" width="70" height="16" rx="4" fill="' . R . '"/>
  <path d="M120 110 h40 M120 140 h40 M120 170 h40" stroke="' . K . '" stroke-width="3"/>
  <g fill="' . Y . '"><circle cx="130" cy="110" r="4"/><circle cx="150" cy="110" r="4"/><circle cx="130" cy="140" r="4"/><circle cx="150" cy="140" r="4"/><circle cx="130" cy="170" r="4"/><circle cx="150" cy="170" r="4"/></g>
  <path d="M180 250 q20 -10 40 5" stroke="' . Y . '" stroke-width="3" fill="none"/>';

$f[23] = // La Luna
 '<circle cx="150" cy="200" r="90" fill="' . Y . '"/><circle cx="190" cy="185" r="78" fill="' . CREMA . '"/>
  <circle cx="118" cy="175" r="6" fill="' . K . '"/><path d="M105 225 q15 15 30 0" stroke="' . K . '" stroke-width="5" fill="none"/>
  ' . estrella(235, 110, 16, Y) . estrella(60, 120, 10, Y) . estrella(230, 300, 10, Y);

$f[24] = // El Cotorro
 '<line x1="40" y1="310" x2="260" y2="310" stroke="' . CAFE . '" stroke-width="8" stroke-linecap="round"/>
  <ellipse cx="150" cy="220" rx="45" ry="70" fill="' . G . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M110 200 q-20 40 -5 90 q20 -30 25 -80 z" fill="' . B . '"/><path d="M190 200 q20 40 5 90 q-20 -30 -25 -80 z" fill="' . B . '"/>
  <circle cx="150" cy="140" r="34" fill="' . G . '" stroke="' . K . '" stroke-width="3"/>
  <circle cx="140" cy="135" r="9" fill="#fff"/><circle cx="141" cy="135" r="4" fill="' . K . '"/>
  <path d="M158 130 q30 0 30 20 q-10 20 -30 10 z" fill="' . Y . '" stroke="' . K . '" stroke-width="2"/>
  <path d="M135 105 q20 -25 35 0" fill="' . R . '"/>
  <path d="M138 290 v20 M162 290 v20" stroke="' . Y . '" stroke-width="4"/>';

$f[25] = // El Borracho
 '<g transform="rotate(-12 150 200)">' . persona(CIELO, '<rect x="108" y="92" width="84" height="10" fill="' . CAFE . '"/><rect x="120" y="70" width="60" height="26" fill="' . CAFE . '"/>
  <path d="M75 245 q-25 40 -10 70" stroke="' . CIELO . '" stroke-width="10" fill="none" stroke-linecap="round"/>
  <rect x="55" y="290" width="22" height="40" rx="4" fill="' . G . '"/><rect x="61" y="280" width="10" height="14" fill="' . G . '"/>', PIEL, 'pícaro') . '</g>
  <line x1="40" y1="330" x2="260" y2="330" stroke="' . K . '" stroke-width="3"/>
  <circle cx="210" cy="330" r="6" fill="' . Y . '"/>';

$f[26] = // El Negrito
 persona(R, '<rect x="118" y="82" width="64" height="16" rx="4" fill="' . Y . '"/><rect x="112" y="80" width="76" height="10" rx="4" fill="' . K . '"/>
  <path d="M110 205 h80" stroke="' . Y . '" stroke-width="6"/>
  <line x1="215" y1="240" x2="240" y2="180" stroke="' . CAFE . '" stroke-width="6" stroke-linecap="round"/>', '#7a4a2a');

$f[27] = // El Corazón
 '<path d="M150 320 q-100 -80 -100 -150 q0 -55 50 -55 q35 0 50 40 q15 -40 50 -40 q50 0 50 55 q0 70 -100 150 z" fill="' . R . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M90 150 q0 -30 30 -35" stroke="#fff" stroke-width="6" fill="none" opacity=".5" stroke-linecap="round"/>
  <path d="M110 230 q40 30 80 0" stroke="' . Y . '" stroke-width="5" fill="none"/>
  <path d="M120 100 l-15 -30 M180 100 l15 -30" stroke="' . Y . '" stroke-width="4" stroke-linecap="round"/>';

$f[28] = // La Sandía
 '<path d="M50 190 a100 100 0 0 0 200 0 z" fill="' . G . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M65 190 a85 85 0 0 0 170 0 z" fill="' . CREMA . '"/>
  <path d="M75 190 a75 75 0 0 0 150 0 z" fill="' . R . '"/>
  <path d="M50 190 h200" stroke="' . K . '" stroke-width="4"/>
  <g fill="' . K . '"><ellipse cx="110" cy="215" rx="5" ry="8"/><ellipse cx="150" cy="240" rx="5" ry="8"/><ellipse cx="190" cy="215" rx="5" ry="8"/><ellipse cx="130" cy="205" rx="4" ry="6"/><ellipse cx="170" cy="205" rx="4" ry="6"/><ellipse cx="150" cy="212" rx="4" ry="6"/></g>
  <path d="M60 110 q90 -50 180 0" stroke="' . G . '" stroke-width="10" fill="none" stroke-linecap="round"/>';

$f[29] = // El Tambor
 '<ellipse cx="150" cy="270" rx="90" ry="30" fill="' . R . '" stroke="' . K . '" stroke-width="4"/>
  <rect x="60" y="160" width="180" height="110" fill="' . R . '"/>
  <line x1="60" y1="160" x2="60" y2="270" stroke="' . K . '" stroke-width="4"/><line x1="240" y1="160" x2="240" y2="270" stroke="' . K . '" stroke-width="4"/>
  <ellipse cx="150" cy="160" rx="90" ry="30" fill="' . CREMA . '" stroke="' . K . '" stroke-width="4"/>
  <g stroke="' . Y . '" stroke-width="4"><line x1="80" y1="182" x2="110" y2="255"/><line x1="110" y1="182" x2="140" y2="255"/><line x1="140" y1="182" x2="170" y2="255"/><line x1="170" y1="182" x2="200" y2="255"/><line x1="200" y1="182" x2="222" y2="240"/></g>
  <line x1="110" y1="150" x2="60" y2="90" stroke="' . CAFE . '" stroke-width="6" stroke-linecap="round"/><circle cx="58" cy="86" r="10" fill="' . K . '"/>
  <line x1="190" y1="150" x2="240" y2="90" stroke="' . CAFE . '" stroke-width="6" stroke-linecap="round"/><circle cx="242" cy="86" r="10" fill="' . K . '"/>';

$f[30] = // El Camarón
 '<path d="M80 150 q90 -70 150 20 q30 60 -20 110 q-30 30 -60 20 q40 -30 40 -80 q-10 -60 -110 -70 z" fill="' . R . '" stroke="' . K . '" stroke-width="4"/>
  <g fill="none" stroke="' . K . '" stroke-width="2"><path d="M150 132 q30 40 20 80 M175 140 q25 40 10 80 M200 158 q15 40 0 70"/></g>
  <circle cx="95" cy="160" r="5" fill="' . K . '"/>
  <path d="M80 150 q-40 -40 -30 -80 M80 150 q-40 -10 -50 -50" stroke="' . R . '" stroke-width="3" fill="none"/>
  <path d="M150 300 l-20 20 M165 300 l-5 25 M180 295 l10 20" stroke="' . R . '" stroke-width="5" stroke-linecap="round"/>
  <path d="M100 190 q0 40 20 60 M120 175 q10 50 40 70" stroke="' . R . '" stroke-width="4" fill="none" stroke-linecap="round"/>';

$f[31] = // Las Jaras
 '<g stroke="' . CAFE . '" stroke-width="7" stroke-linecap="round"><line x1="80" y1="320" x2="220" y2="80"/><line x1="220" y1="320" x2="80" y2="80"/></g>
  <polygon points="220,80 195,88 212,105" fill="#bbb" stroke="' . K . '" stroke-width="3"/>
  <polygon points="80,80 105,88 88,105" fill="#bbb" stroke="' . K . '" stroke-width="3"/>
  <g fill="' . R . '"><polygon points="80,320 95,285 110,300"/><polygon points="80,320 115,305 100,290"/></g>
  <g fill="' . G . '"><polygon points="220,320 205,285 190,300"/><polygon points="220,320 185,305 200,290"/></g>
  <circle cx="150" cy="200" r="16" fill="' . Y . '" stroke="' . K . '" stroke-width="3"/>';

$f[32] = // El Músico
 persona(B, '<rect x="108" y="92" width="84" height="10" fill="' . K . '"/><rect x="122" y="66" width="56" height="30" fill="' . K . '"/>
  <ellipse cx="60" cy="230" rx="30" ry="28" fill="' . CAFE . '" stroke="' . K . '" stroke-width="3"/><circle cx="60" cy="230" r="9" fill="' . K . '"/>
  <rect x="55" y="130" width="10" height="80" fill="' . K . '"/><g stroke="' . Y . '" stroke-width="1.5"><line x1="57" y1="135" x2="57" y2="255"/><line x1="63" y1="135" x2="63" y2="255"/></g>
  <path d="M225 190 q10 -10 20 0 M240 165 v40" stroke="' . K . '" stroke-width="4" fill="none"/>
  <text x="228" y="150" font-size="34" fill="' . K . '">♪</text>');

$f[33] = // La Araña
 '<line x1="150" y1="60" x2="150" y2="160" stroke="' . K . '" stroke-width="3"/>
  <g stroke="' . K . '" stroke-width="6" fill="none" stroke-linecap="round">
   <path d="M120 200 q-40 -30 -60 -70 M120 215 q-50 -10 -80 -30 M120 230 q-50 20 -75 50 M125 245 q-30 40 -35 80"/>
   <path d="M180 200 q40 -30 60 -70 M180 215 q50 -10 80 -30 M180 230 q50 20 75 50 M175 245 q30 40 35 80"/></g>
  <ellipse cx="150" cy="240" rx="45" ry="55" fill="' . K . '"/><circle cx="150" cy="175" r="28" fill="' . K . '"/>
  <path d="M130 225 q20 -15 40 0 M125 250 q25 -15 50 0 M130 275 q20 -12 40 0" stroke="' . R . '" stroke-width="5" fill="none"/>
  <circle cx="140" cy="168" r="5" fill="' . Y . '"/><circle cx="160" cy="168" r="5" fill="' . Y . '"/>';

$f[34] = // El Soldado
 persona(G, '<rect x="112" y="72" width="76" height="30" rx="4" fill="' . G . '" stroke="' . K . '" stroke-width="3"/><rect x="106" y="96" width="88" height="8" fill="' . K . '"/>
  <circle cx="150" cy="86" r="6" fill="' . Y . '"/>
  <g fill="' . Y . '"><circle cx="150" cy="185" r="4"/><circle cx="150" cy="205" r="4"/><circle cx="150" cy="225" r="4"/></g>
  <rect x="80" y="180" width="24" height="12" fill="' . Y . '"/><rect x="196" y="180" width="24" height="12" fill="' . Y . '"/>
  <line x1="238" y1="100" x2="232" y2="330" stroke="' . CAFE . '" stroke-width="7" stroke-linecap="round"/><rect x="230" y="120" width="12" height="70" rx="3" fill="' . K . '"/>
  <rect x="105" y="240" width="90" height="10" fill="' . K . '"/>');

$f[35] = // La Estrella
 estrella(150, 200, 105, Y) . estrella(150, 200, 65, R) . estrella(150, 200, 28, CREMA)
 . '<circle cx="60" cy="90" r="5" fill="' . B . '"/><circle cx="240" cy="90" r="5" fill="' . B . '"/><circle cx="70" cy="310" r="5" fill="' . B . '"/><circle cx="230" cy="310" r="5" fill="' . B . '"/>';

$f[36] = // El Cazo
 '<path d="M70 190 q0 110 80 110 q80 0 80 -110 z" fill="' . K . '" stroke="' . K . '" stroke-width="4"/>
  <ellipse cx="150" cy="190" rx="80" ry="22" fill="#555" stroke="' . K . '" stroke-width="4"/>
  <ellipse cx="150" cy="190" rx="65" ry="14" fill="' . Y . '"/>
  <path d="M230 185 h40 q12 0 12 12 q0 12 -12 12 h-40" fill="none" stroke="' . CAFE . '" stroke-width="10" stroke-linecap="round"/>
  <path d="M80 250 q10 50 60 45" stroke="#888" stroke-width="3" fill="none"/>
  <g stroke="#bbb" stroke-width="4" fill="none" stroke-linecap="round"><path d="M120 165 q5 -20 -5 -40 q10 -20 0 -40 M150 160 q5 -20 -5 -40 q10 -20 0 -40 M180 165 q5 -20 -5 -40 q10 -20 0 -40"/></g>';

$f[37] = // El Mundo
 '<circle cx="150" cy="200" r="100" fill="' . CIELO . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M90 140 q30 -30 60 0 q10 30 -20 40 q-20 20 -10 40 q-30 0 -40 -30 q-10 -30 10 -50 z" fill="' . G . '"/>
  <path d="M170 110 q40 -10 60 30 q-20 40 -50 30 q-20 -20 -10 -60 z" fill="' . G . '"/>
  <path d="M170 220 q40 0 50 40 q-30 40 -60 10 q-10 -30 10 -50 z" fill="' . G . '"/>
  <path d="M55 200 h190" stroke="' . K . '" stroke-width="2" opacity=".4"/>
  <ellipse cx="150" cy="200" rx="40" ry="100" fill="none" stroke="' . K . '" stroke-width="2" opacity=".4"/>
  <path d="M100 300 h100 l10 25 h-120 z" fill="' . CAFE . '"/>';

$f[38] = // El Apache
 persona(CAFE, '<g fill="' . Y . '" stroke="' . K . '" stroke-width="2"><path d="M150 60 l8 40 h-16 z"/><path d="M120 66 l14 36 h-16 z" transform="rotate(-15 128 84)"/><path d="M180 66 l-14 36 h16 z" transform="rotate(15 172 84)"/></g>
  <rect x="112" y="96" width="76" height="12" fill="' . R . '"/>
  <path d="M112 100 q-10 30 5 60 M188 100 q10 30 -5 60" stroke="' . K . '" stroke-width="10" fill="none" stroke-linecap="round"/>
  <path d="M112 118 h14 M174 118 h14" stroke="' . R . '" stroke-width="4"/>
  <path d="M105 195 h90 M105 215 h90" stroke="' . Y . '" stroke-width="4"/>
  <line x1="45" y1="120" x2="65" y2="330" stroke="' . CAFE . '" stroke-width="6" stroke-linecap="round"/><polygon points="45,120 35,95 58,100" fill="#bbb" stroke="' . K . '" stroke-width="2"/>');

$f[39] = // El Nopal
 '<path d="M60 325 h180" stroke="' . CAFE . '" stroke-width="8" stroke-linecap="round"/>
  <ellipse cx="150" cy="250" rx="55" ry="75" fill="' . G . '" stroke="' . K . '" stroke-width="4"/>
  <ellipse cx="95" cy="170" rx="35" ry="55" fill="' . G . '" stroke="' . K . '" stroke-width="4" transform="rotate(-25 95 170)"/>
  <ellipse cx="205" cy="160" rx="35" ry="55" fill="' . G . '" stroke="' . K . '" stroke-width="4" transform="rotate(25 205 160)"/>
  <g fill="' . K . '"><circle cx="130" cy="230" r="3"/><circle cx="170" cy="240" r="3"/><circle cx="150" cy="280" r="3"/><circle cx="140" cy="205" r="3"/><circle cx="90" cy="160" r="3"/><circle cx="100" cy="190" r="3"/><circle cx="205" cy="150" r="3"/><circle cx="200" cy="180" r="3"/></g>
  <ellipse cx="85" cy="115" rx="12" ry="16" fill="' . R . '"/><ellipse cx="215" cy="105" rx="12" ry="16" fill="' . R . '"/>';

$f[40] = // El Alacrán
 '<ellipse cx="140" cy="230" rx="55" ry="35" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4"/>
  <circle cx="85" cy="215" r="24" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M65 200 q-30 -20 -20 -45 q15 5 10 25 M65 230 q-30 20 -20 45 q15 -5 10 -25" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M190 225 q40 -10 50 -60 q5 -40 -20 -50" stroke="' . CAFE . '" stroke-width="14" fill="none" stroke-linecap="round"/>
  <polygon points="220,110 200,105 215,130" fill="' . R . '" stroke="' . K . '" stroke-width="2"/>
  <g stroke="' . K . '" stroke-width="4" fill="none" stroke-linecap="round"><path d="M110 250 l-20 40 M130 260 l-10 45 M155 260 l5 45 M175 250 l20 40"/><path d="M110 210 l-20 -40 M130 200 l-10 -45 M155 200 l5 -45 M175 210 l20 -40"/></g>
  <circle cx="80" cy="210" r="3" fill="' . K . '"/>';

$f[41] = // La Rosa
 '<path d="M150 210 v110" stroke="' . G . '" stroke-width="7"/>
  <path d="M150 270 q-40 -10 -50 -40 q35 0 50 40 z M150 290 q40 -10 50 -40 q-35 0 -50 40 z" fill="' . G . '"/>
  <path d="M120 250 l-8 -10 M180 240 l8 -10" stroke="' . G . '" stroke-width="3"/>
  <circle cx="150" cy="150" r="70" fill="' . R . '" stroke="' . K . '" stroke-width="3"/>
  <path d="M150 90 q-50 10 -50 60 q0 40 50 55 q50 -15 50 -55 q0 -50 -50 -60" fill="none" stroke="' . K . '" stroke-width="3"/>
  <path d="M150 110 q-30 10 -30 40 q0 25 30 35 q30 -10 30 -35 q0 -30 -30 -40" fill="none" stroke="' . K . '" stroke-width="3"/>
  <circle cx="150" cy="150" r="14" fill="' . ROSA . '" stroke="' . K . '" stroke-width="3"/>';

$f[42] = // La Calavera
 '<path d="M85 180 q0 -90 65 -90 q65 0 65 90 q0 50 -25 65 v30 h-80 v-30 q-25 -15 -25 -65 z" fill="' . CREMA . '" stroke="' . K . '" stroke-width="4"/>
  <ellipse cx="122" cy="180" rx="18" ry="22" fill="' . K . '"/><ellipse cx="178" cy="180" rx="18" ry="22" fill="' . K . '"/>
  <polygon points="150,205 140,230 160,230" fill="' . K . '"/>
  <g stroke="' . K . '" stroke-width="3"><path d="M120 250 h60 M130 245 v20 M145 245 v20 M160 245 v20 M170 245 v20"/></g>
  <g fill="' . R . '"><circle cx="122" cy="180" r="7"/><circle cx="178" cy="180" r="7"/></g>
  <path d="M105 130 q10 -10 20 0 M175 130 q10 -10 20 0" stroke="' . Y . '" stroke-width="4" fill="none"/>
  <path d="M80 300 q70 30 140 0" stroke="' . Y . '" stroke-width="5" fill="none"/>';

$f[43] = // La Campana
 '<path d="M150 90 q-15 0 -15 15 v10 q-55 20 -55 110 v40 h140 v-40 q0 -90 -55 -110 v-10 q0 -15 -15 -15 z" fill="' . Y . '" stroke="' . K . '" stroke-width="4"/>
  <rect x="60" y="265" width="180" height="20" rx="8" fill="' . Y . '" stroke="' . K . '" stroke-width="4"/>
  <circle cx="150" cy="300" r="16" fill="' . K . '"/>
  <path d="M110 150 q-15 40 -10 90" stroke="#fff" stroke-width="5" fill="none" opacity=".5" stroke-linecap="round"/>
  <path d="M60 130 q-15 40 0 80 M240 130 q15 40 0 80" stroke="' . B . '" stroke-width="4" fill="none" stroke-linecap="round"/>
  <path d="M150 80 v-15" stroke="' . K . '" stroke-width="4"/><circle cx="150" cy="60" r="6" fill="' . K . '"/>';

$f[44] = // El Cantarito
 '<path d="M120 100 h60 v25 q50 10 50 80 q0 100 -80 100 q-80 0 -80 -100 q0 -70 50 -80 z" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4"/>
  <ellipse cx="150" cy="100" rx="35" ry="10" fill="' . K . '"/>
  <path d="M225 190 q40 -5 30 40 q-10 20 -30 15" stroke="' . CAFE . '" stroke-width="10" fill="none" stroke-linecap="round"/>
  <path d="M75 230 q75 -30 150 0 M75 250 q75 30 150 0" stroke="' . Y . '" stroke-width="4" fill="none"/>
  <g fill="' . R . '"><circle cx="110" cy="240" r="5"/><circle cx="150" cy="240" r="5"/><circle cx="190" cy="240" r="5"/></g>
  <path d="M95 160 q-5 60 10 110" stroke="#fff" stroke-width="4" fill="none" opacity=".35"/>';

$f[45] = // El Venado
 '<g transform="translate(0,22)"><path d="M60 303 h180" stroke="' . G . '" stroke-width="6" stroke-linecap="round"/>
  <ellipse cx="160" cy="215" rx="60" ry="38" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4"/>
  <g stroke="' . CAFE . '" stroke-width="10" stroke-linecap="round"><line x1="115" y1="240" x2="105" y2="300"/><line x1="140" y1="248" x2="140" y2="300"/><line x1="180" y1="248" x2="185" y2="300"/><line x1="205" y1="240" x2="215" y2="300"/></g>
  <path d="M105 195 q-10 -50 5 -80" stroke="' . CAFE . '" stroke-width="16" fill="none" stroke-linecap="round"/>
  <ellipse cx="105" cy="110" rx="24" ry="20" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4"/>
  <ellipse cx="82" cy="115" rx="10" ry="7" fill="' . K . '"/><circle cx="100" cy="103" r="3" fill="' . K . '"/>
  <g stroke="' . K . '" stroke-width="5" fill="none" stroke-linecap="round"><path d="M100 93 q-8 -30 -25 -42 M94 88 q-20 -12 -38 -12 M96 90 q-4 -22 4 -38"/><path d="M118 90 q8 -30 25 -42 M122 86 q20 -12 38 -12 M120 88 q4 -22 -4 -38"/></g>
  <circle cx="150" cy="200" r="6" fill="' . CREMA . '"/><circle cx="175" cy="220" r="6" fill="' . CREMA . '"/><circle cx="195" cy="200" r="6" fill="' . CREMA . '"/>
  <path d="M215 200 q15 -10 20 5" stroke="' . CAFE . '" stroke-width="8" fill="none" stroke-linecap="round"/></g>';

$f[46] = // El Sol
 '<g stroke="' . Y . '" stroke-width="14" stroke-linecap="round">' . implode('', array_map(fn($a) =>
   sprintf('<line x1="%.0f" y1="%.0f" x2="%.0f" y2="%.0f"/>', 150 + 100 * cos($a), 200 + 100 * sin($a), 150 + 130 * cos($a), 200 + 130 * sin($a)),
   array_map(fn($i) => $i * M_PI / 6, range(0, 11)))) . '</g>
  <circle cx="150" cy="200" r="85" fill="' . Y . '" stroke="' . R . '" stroke-width="6"/>
  <circle cx="125" cy="185" r="7" fill="' . K . '"/><circle cx="175" cy="185" r="7" fill="' . K . '"/>
  <path d="M120 225 q30 25 60 0" stroke="' . K . '" stroke-width="6" fill="none"/>
  <circle cx="110" cy="215" r="9" fill="' . R . '" opacity=".5"/><circle cx="190" cy="215" r="9" fill="' . R . '" opacity=".5"/>';

$f[47] = // La Corona
 '<path d="M60 300 v-150 l45 60 l45 -100 l45 100 l45 -60 v150 z" fill="' . Y . '" stroke="' . K . '" stroke-width="4" stroke-linejoin="round"/>
  <rect x="60" y="275" width="180" height="28" fill="' . R . '" stroke="' . K . '" stroke-width="4"/>
  <g fill="' . B . '"><circle cx="105" cy="290" r="8"/><circle cx="150" cy="290" r="8"/><circle cx="195" cy="290" r="8"/></g>
  <circle cx="60" cy="150" r="9" fill="' . R . '"/><circle cx="150" cy="110" r="10" fill="' . R . '"/><circle cx="240" cy="150" r="9" fill="' . R . '"/>
  <circle cx="105" cy="210" r="7" fill="' . G . '"/><circle cx="195" cy="210" r="7" fill="' . G . '"/>
  <circle cx="150" cy="240" r="12" fill="' . B . '" stroke="' . K . '" stroke-width="2"/>';

$f[48] = // La Chalupa
 '<path d="M40 300 q30 -15 60 0 t60 0 t60 0 t40 0 v30 H40 z" fill="' . CIELO . '"/>
  <path d="M55 250 q95 40 190 0 l-25 45 h-140 z" fill="' . CAFE . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M55 250 q95 15 190 0" stroke="' . R . '" stroke-width="6" fill="none"/>
  <g fill="' . R . '"><circle cx="80" cy="258" r="6"/><circle cx="120" cy="266" r="6"/><circle cx="180" cy="266" r="6"/><circle cx="220" cy="258" r="6"/></g>
  <line x1="150" y1="250" x2="150" y2="110" stroke="' . CAFE . '" stroke-width="6"/>
  <path d="M150 115 h70 q-10 40 0 80 h-70 z" fill="' . Y . '" stroke="' . K . '" stroke-width="3"/>
  ' . cara(120, 205, 16) . '<rect x="106" y="220" width="28" height="30" rx="6" fill="' . G . '"/>
  <line x1="60" y1="215" x2="100" y2="270" stroke="' . CAFE . '" stroke-width="5" stroke-linecap="round"/>';

$f[49] = // El Pino
 '<rect x="138" y="280" width="24" height="45" fill="' . CAFE . '"/>
  <polygon points="150,70 105,150 195,150" fill="' . G . '" stroke="' . K . '" stroke-width="3"/>
  <polygon points="150,110 85,210 215,210" fill="' . G . '" stroke="' . K . '" stroke-width="3"/>
  <polygon points="150,160 65,285 235,285" fill="' . G . '" stroke="' . K . '" stroke-width="3"/>
  <g fill="' . CAFE . '"><ellipse cx="120" cy="240" rx="6" ry="9"/><ellipse cx="185" cy="255" rx="6" ry="9"/><ellipse cx="150" cy="190" rx="6" ry="9"/></g>
  <path d="M60 325 h180" stroke="' . G . '" stroke-width="6" stroke-linecap="round"/>';

$f[50] = // El Pescado
 '<path d="M40 300 q30 -15 60 0 t60 0 t60 0 t40 0 v30 H40 z" fill="' . CIELO . '"/>
  <path d="M70 200 q60 -70 130 -20 q30 20 30 20 q-30 20 -30 20 q-70 50 -130 -20 z" fill="' . Y . '" stroke="' . K . '" stroke-width="4"/>
  <polygon points="230,200 265,160 265,240" fill="' . Y . '" stroke="' . K . '" stroke-width="4" stroke-linejoin="round"/>
  <path d="M120 165 q30 -30 60 -10 l-10 20 z M120 235 q30 30 60 10 l-10 -20 z" fill="' . R . '"/>
  <circle cx="100" cy="195" r="10" fill="#fff" stroke="' . K . '" stroke-width="2"/><circle cx="101" cy="195" r="4" fill="' . K . '"/>
  <g fill="none" stroke="' . K . '" stroke-width="2"><path d="M140 180 q10 10 0 20 M160 175 q10 12 0 25 M180 175 q10 12 0 25"/></g>
  <g fill="' . CIELO . '"><circle cx="70" cy="150" r="5"/><circle cx="60" cy="130" r="4"/></g>';

$f[51] = // La Palma
 '<path d="M60 325 h180" stroke="' . Y . '" stroke-width="8" stroke-linecap="round"/>
  <path d="M150 320 q-10 -100 20 -190" stroke="' . CAFE . '" stroke-width="14" fill="none" stroke-linecap="round"/>
  <g fill="' . G . '" stroke="' . K . '" stroke-width="3" stroke-linejoin="round">
   <path d="M170 130 q-60 -40 -110 0 q50 0 90 30 z"/><path d="M170 130 q60 -40 110 0 q-50 0 -90 30 z"/>
   <path d="M170 130 q-70 10 -80 70 q40 -30 90 -40 z"/><path d="M170 130 q70 10 80 70 q-40 -30 -90 -40 z"/>
   <path d="M170 130 q-30 -60 0 -80 q30 20 0 80 z"/></g>
  <g fill="' . CAFE . '"><circle cx="165" cy="150" r="7"/><circle cx="180" cy="152" r="7"/><circle cx="172" cy="164" r="7"/></g>';

$f[52] = // La Maceta
 '<path d="M90 230 h120 l-15 90 h-90 z" fill="' . R . '" stroke="' . K . '" stroke-width="4" stroke-linejoin="round"/>
  <rect x="80" y="215" width="140" height="22" rx="4" fill="' . R . '" stroke="' . K . '" stroke-width="4"/>
  <path d="M100 270 h100" stroke="' . Y . '" stroke-width="5"/>
  <path d="M150 215 v-70" stroke="' . G . '" stroke-width="6"/>
  <path d="M150 180 q-40 -5 -50 -35 q35 0 50 35 z M150 195 q40 -5 50 -35 q-35 0 -50 35 z" fill="' . G . '"/>
  <g fill="' . ROSA . '"><circle cx="150" cy="115" r="14"/><circle cx="130" cy="130" r="14"/><circle cx="170" cy="130" r="14"/><circle cx="135" cy="105" r="14"/><circle cx="165" cy="105" r="14"/></g>
  <circle cx="150" cy="120" r="9" fill="' . Y . '"/>';

$f[53] = // El Arpa
 '<g stroke="' . Y . '" stroke-width="2"><line x1="120" y1="75" x2="120" y2="302"/><line x1="140" y1="68" x2="140" y2="265"/><line x1="160" y1="68" x2="160" y2="229"/><line x1="180" y1="75" x2="180" y2="192"/><line x1="200" y1="88" x2="200" y2="156"/></g>
  <path d="M85 320 v-225 q65 -70 140 15 l-115 210 z" fill="none" stroke="' . CAFE . '" stroke-width="14" stroke-linejoin="round"/>
  <path d="M225 110 l-115 210" stroke="' . CAFE . '" stroke-width="26" stroke-linecap="round"/>
  <path d="M85 320 h35" stroke="' . CAFE . '" stroke-width="16" stroke-linecap="round"/>
  <circle cx="85" cy="95" r="10" fill="' . Y . '" stroke="' . K . '" stroke-width="3"/><circle cx="225" cy="110" r="10" fill="' . Y . '" stroke="' . K . '" stroke-width="3"/>
  <circle cx="85" cy="320" r="8" fill="' . Y . '"/>';

$f[54] = // La Rana
 '<ellipse cx="150" cy="180" rx="80" ry="45" fill="' . CIELO . '" opacity=".5"/>
  <ellipse cx="150" cy="240" rx="75" ry="55" fill="' . G . '" stroke="' . K . '" stroke-width="4"/>
  <ellipse cx="150" cy="255" rx="45" ry="30" fill="' . Y . '" opacity=".6"/>
  <circle cx="115" cy="190" r="24" fill="' . G . '" stroke="' . K . '" stroke-width="4"/><circle cx="185" cy="190" r="24" fill="' . G . '" stroke="' . K . '" stroke-width="4"/>
  <circle cx="115" cy="188" r="12" fill="#fff"/><circle cx="185" cy="188" r="12" fill="#fff"/><circle cx="117" cy="188" r="6" fill="' . K . '"/><circle cx="183" cy="188" r="6" fill="' . K . '"/>
  <path d="M110 240 q40 30 80 0" stroke="' . K . '" stroke-width="4" fill="none"/>
  <path d="M85 270 q-40 20 -30 50 q20 -5 45 -25 M215 270 q40 20 30 50 q-20 -5 -45 -25" fill="' . G . '" stroke="' . K . '" stroke-width="4" stroke-linejoin="round"/>
  <g fill="' . K . '"><circle cx="100" cy="230" r="4"/><circle cx="200" cy="230" r="4"/></g>';

function carta_svg(int $n, string $nombre, string $figura): string {
    $nom = htmlspecialchars($nombre, ENT_QUOTES);
    $tam = mb_strlen($nombre) > 12 ? 20 : 24;
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 420" width="300" height="420">
<!-- Lotería Mexicana - ilustración propia. Copyright (C) 2026 Ismael A. Acevedo Rendón.
     Licencia: GNU GPL v3 o posterior. https://www.gnu.org/licenses/gpl-3.0.html -->
<rect width="300" height="420" rx="14" fill="#f6e7c8"/>
<rect x="8" y="8" width="284" height="404" rx="10" fill="none" stroke="#ce1126" stroke-width="4"/>
<rect x="16" y="16" width="268" height="388" rx="8" fill="none" stroke="#006847" stroke-width="1.5"/>
<g fill="#006847"><circle cx="24" cy="24" r="5"/><circle cx="276" cy="24" r="5"/></g>
<g fill="#ce1126"><circle cx="24" cy="396" r="5"/><circle cx="276" cy="396" r="5"/></g>
<circle cx="150" cy="46" r="22" fill="#006847"/>
<text x="150" y="53" text-anchor="middle" font-family="Georgia, serif" font-size="22" font-weight="bold" fill="#f6e7c8">$n</text>
<g>$figura</g>
<rect x="24" y="346" width="252" height="44" rx="8" fill="#006847"/>
<rect x="24" y="386" width="252" height="4" rx="2" fill="#ce1126"/>
<text x="150" y="376" text-anchor="middle" font-family="Georgia, serif" font-size="$tam" font-weight="bold" fill="#f6e7c8">$nom</text>
</svg>
SVG;
}

$dir = __DIR__ . '/../cartas';
if (!is_dir($dir)) mkdir($dir, 0775, true);
foreach (MAZO_CLASICO as [$n, $nombre]) {
    if (!isset($f[$n])) { fwrite(STDERR, "Falta figura $n\n"); exit(1); }
    file_put_contents(sprintf('%s/%02d.svg', $dir, $n), carta_svg($n, $nombre, trim($f[$n])) . "\n");
}
echo "Generadas " . count(MAZO_CLASICO) . " cartas en cartas/\n";

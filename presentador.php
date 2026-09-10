<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/vista.php';
exigirClaveVista();
$codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['c'] ?? ''));
$st = getDB()->prepare("SELECT p.*, m.nombre AS mazo FROM partidas p JOIN mazos m ON m.id=p.mazo_id WHERE codigo = ?");
$st->execute([$codigo]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404); cabecera('No encontrada');
    echo '<main class="centro"><div class="tarjeta"><h1>Partida no encontrada</h1><a class="btn btn-primario" href="index.php">Ir al inicio</a></div></main>';
    pie(); exit;
}
cabecera('Presentador ' . $codigo, 'presentador');
?>
<div id="lobby" class="pantalla">
  <h1>Escanea el código para recibir tu tablero</h1>
  <div id="qr"></div>
  <p class="codigo">Código: <b><?= h($codigo) ?></b></p>
  <p class="url" id="url"></p>
  <div class="lobby-abajo">
    <div><h2>Jugadores (<span id="nJug">0</span>)</h2><ul id="listaLobby"></ul></div>
    <button id="btnIniciar" class="btn btn-primario grande" disabled>Iniciar partida</button>
  </div>
</div>

<div id="juego" class="pantalla oculto">
  <div class="escena">
    <div class="carta-grande"><div class="carta" id="cartaActual"></div><div class="nombre-grande" id="nombreActual"></div></div>
    <aside id="panel" class="panel">
      <h3>Jugadores</h3><ul id="listaJuego"></ul>
      <h3>Gritos de lotería</h3><ul id="listaGritos"></ul>
    </aside>
  </div>
  <div class="tira" id="tira"></div>
  <div class="controles">
    <span id="contador" class="contador"></span>
    <button id="btnSiguiente" class="btn btn-primario">Siguiente ▶</button>
    <button id="btnPausa" class="btn btn-secundario">Pausa</button>
    <label class="auto"><input type="checkbox" id="chkAuto"> Auto cada <input type="number" id="numSeg" min="2" max="60" value="<?= (int)$p['intervalo_seg'] ?>"> s</label>
    <button id="btnPanel" class="btn btn-secundario">Jugadores</button>
    <button id="btnTerminar" class="btn btn-peligro">Terminar</button>
  </div>
</div>

<div id="alerta" class="alerta oculto">
  <div class="alerta-caja">
    <div class="alerta-titulo">🎉 <span id="alNombre"></span> gritó ¡LOTERÍA!</div>
    <div id="alVeredicto" class="veredicto"></div>
    <div class="alerta-botones">
      <button id="alGanador" class="btn btn-primario grande">Terminar: es el ganador</button>
      <button id="alContinuar" class="btn btn-secundario grande">Continuar el juego</button>
    </div>
  </div>
</div>

<div id="fin" class="pantalla oculto">
  <h1>Partida terminada</h1>
  <p class="ganador" id="finGanador"></p>
  <p id="finResumen"></p>
  <a class="btn btn-primario grande" href="index.php">Nueva partida</a>
</div>
<script>window.CODIGO = <?= json_encode($codigo) ?>;</script>
<script src="assets/qrcode.min.js"></script>
<script src="assets/presentador.js?v=<?= assetVer() ?>"></script>
<?php pie();

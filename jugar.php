<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/vista.php';
$codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['c'] ?? ''));
$st = getDB()->prepare("SELECT estado FROM partidas WHERE codigo = ?");
$st->execute([$codigo]);
$p = $st->fetch();
cabecera('Jugar', 'jugador');
if (!$p) {
    echo '<main class="centro"><div class="tarjeta"><h1>Partida no encontrada</h1><p>Revisa el código o vuelve a escanear el QR.</p></div></main>';
    pie(); exit;
}
?>
<div id="entrar" class="centro">
  <form class="tarjeta" id="formNombre">
    <div class="tricolor"></div>
    <h1>🎉 Lotería Mexicana</h1>
    <p>Partida <b><?= h($codigo) ?></b></p>
    <label for="nombre">Tu nombre</label>
    <input name="nombre" id="nombre" maxlength="30" required autocomplete="off" autofocus placeholder="Escribe tu nombre">
    <button class="btn btn-primario grande">Recibir tablero</button>
    <p class="error oculto" id="err"></p>
  </form>
</div>
<div id="tablero" class="oculto">
  <header class="barra"><span id="miNombre"></span><span id="estadoTxt" class="pill"></span><span id="cuenta">0/16</span></header>
  <div class="cuadricula" id="cuadricula"></div>
  <button id="btnGritar" class="btn btn-primario gritar" disabled>¡LOTERÍA!</button>
  <div id="aviso" class="aviso oculto"></div>
</div>
<script>window.CODIGO = <?= json_encode($codigo) ?>;</script>
<script src="assets/jugar.js?v=1"></script>
<?php pie();

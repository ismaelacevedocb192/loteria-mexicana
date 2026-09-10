<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/vista.php';
exigirClaveVista();
$pdo = getDB();
$mazos = $pdo->query("SELECT m.id, m.nombre, (SELECT COUNT(*) FROM cartas c WHERE c.mazo_id=m.id) AS n FROM mazos m ORDER BY es_default DESC, nombre")->fetchAll();
$partidas = $pdo->query("SELECT p.codigo, p.estado, p.creada, m.nombre AS mazo, (SELECT COUNT(*) FROM jugadores j WHERE j.partida_id=p.id) AS jugadores
                         FROM partidas p JOIN mazos m ON m.id=p.mazo_id ORDER BY p.id DESC LIMIT 15")->fetchAll();
cabecera('Inicio', 'portada');
?>
<main class="centro">
  <div class="tarjeta">
    <div class="tricolor"></div>
    <h1>🎉 Lotería Mexicana</h1>
    <form id="nueva">
      <label for="mazo_id">Mazo</label>
      <select name="mazo_id" id="mazo_id"><?php foreach ($mazos as $m): ?>
        <option value="<?= (int)$m['id'] ?>" <?= $m['n'] < 16 ? 'disabled' : '' ?>><?= h($m['nombre']) ?> (<?= (int)$m['n'] ?> cartas)</option>
      <?php endforeach ?></select>
      <button class="btn btn-primario grande">Nueva partida</button>
      <p class="error oculto" id="err"></p>
    </form>
    <p style="margin-top:14px"><a href="mazos.php">Administrar mazos personalizados</a></p>
    <h2 style="margin-top:20px">Partidas recientes</h2>
    <table class="tabla"><thead><tr><th>Código</th><th>Mazo</th><th>Estado</th><th>Jug.</th><th></th></tr></thead><tbody>
    <?php foreach ($partidas as $p): ?>
      <tr><td><b><?= h($p['codigo']) ?></b></td><td><?= h($p['mazo']) ?></td><td><?= h($p['estado']) ?></td><td><?= (int)$p['jugadores'] ?></td>
          <td><a class="btn btn-secundario" href="presentador.php?c=<?= h($p['codigo']) ?>">Abrir</a></td></tr>
    <?php endforeach ?>
    <?php if (!$partidas): ?><tr><td colspan="5" class="tenue">Aún no hay partidas</td></tr><?php endif ?>
    </tbody></table>
  </div>
</main>
<script>
document.getElementById('nueva').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target); if (LOT.k) fd.append('k', LOT.k);
  const r = await fetch('api.php?a=crear_partida', {method: 'POST', body: fd}).then(r => r.json());
  if (!r.ok) { const el = document.getElementById('err'); el.textContent = r.error; el.classList.remove('oculto'); return; }
  location.href = 'presentador.php?c=' + r.codigo;
});
</script>
<?php pie();

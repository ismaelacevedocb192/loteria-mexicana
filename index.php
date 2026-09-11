<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/vista.php';
exigirClaveVista();
$pdo = getDB();
$mazos = $pdo->query("SELECT m.id, m.nombre, (SELECT COUNT(*) FROM cartas c WHERE c.mazo_id=m.id) AS n FROM mazos m ORDER BY es_default DESC, nombre")->fetchAll();
$partidas = $pdo->query("SELECT p.codigo, p.estado, p.creada, m.nombre AS mazo,
                                (SELECT COUNT(*) FROM jugadores j WHERE j.partida_id=p.id) AS jugadores,
                                g.nombre AS ganador,
                                (SELECT COUNT(*) FROM gritos gr WHERE gr.partida_id=p.id AND gr.valido=1) AS gritos_validos
                         FROM partidas p
                         JOIN mazos m ON m.id = p.mazo_id
                         LEFT JOIN jugadores g ON g.id = p.ganador_id
                         ORDER BY p.id DESC LIMIT 15")->fetchAll();
$terminadas = (int)$pdo->query("SELECT COUNT(*) FROM partidas WHERE estado='terminada'")->fetchColumn();
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
    <div class="barra" style="margin-top:20px">
      <h2 style="margin:0">Partidas recientes</h2>
      <?php if ($terminadas): ?><button class="btn btn-peligro" id="limpiar" style="min-height:36px;padding:6px 14px;font-size:.9rem">Borrar terminadas (<?= (int)$terminadas ?>)</button><?php endif ?>
    </div>
    <table class="tabla"><thead><tr><th>Código</th><th>Mazo</th><th>Estado</th><th>Jug.</th><th>Ganador</th><th></th></tr></thead><tbody>
    <?php foreach ($partidas as $p): ?>
      <tr><td><b><?= h($p['codigo']) ?></b></td><td><?= h($p['mazo']) ?></td><td><?= h($p['estado']) ?></td><td><?= (int)$p['jugadores'] ?></td>
          <td><?php if ($p['ganador']): ?><span class="ganador-celda">🏆 <?= h($p['ganador']) ?></span>
              <?php elseif ($p['estado'] === 'terminada'): ?><span class="tenue">sin ganador</span>
              <?php elseif ($p['gritos_validos']): ?><span class="tenue">lotería cantada</span>
              <?php else: ?><span class="tenue">—</span><?php endif ?></td>
          <td><div class="acciones">
            <a class="btn btn-secundario" href="presentador.php?c=<?= h($p['codigo']) ?>">Abrir</a>
            <button class="mini borrar" data-codigo="<?= h($p['codigo']) ?>" title="Borrar partida">🗑</button>
          </div></td></tr>
    <?php endforeach ?>
    <?php if (!$partidas): ?><tr><td colspan="6" class="tenue">Aún no hay partidas</td></tr><?php endif ?>
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

const post = (a, datos) => {
  const fd = new FormData();
  for (const [k, v] of Object.entries(datos)) fd.append(k, v);
  if (LOT.k) fd.append('k', LOT.k);
  return fetch('api.php?a=' + a, {method: 'POST', body: fd}).then(r => r.json());
};

for (const b of document.querySelectorAll('.borrar')) b.onclick = async () => {
  const cod = b.dataset.codigo;
  if (!confirm('¿Borrar la partida ' + cod + '? Se pierden sus jugadores y tableros.')) return;
  const r = await post('borrar_partida', {codigo: cod});
  if (!r.ok) return alert(r.error);
  location.reload();
};

const limpiar = document.getElementById('limpiar');
if (limpiar) limpiar.onclick = async () => {
  if (!confirm('¿Borrar todas las partidas terminadas?')) return;
  const r = await post('borrar_partidas_terminadas', {});
  if (!r.ok) return alert(r.error);
  location.reload();
};
</script>
<?php pie();

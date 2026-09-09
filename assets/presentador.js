(() => {
  const $ = id => document.getElementById(id);
  const cod = window.CODIGO;
  let estado = null, timerAuto = null, qrHecho = false, gritoMostrado = null, ocupado = false;

  const api = async (a, datos = {}) => {
    const fd = new FormData(); fd.append('codigo', cod); if (LOT.k) fd.append('k', LOT.k);
    for (const [k, v] of Object.entries(datos)) fd.append(k, v);
    return fetch('api.php?a=' + a, {method: 'POST', body: fd}).then(r => r.json());
  };
  const sondear = () => fetch(`api.php?a=estado_presentador&codigo=${cod}${LOT.k ? '&k=' + encodeURIComponent(LOT.k) : ''}`).then(r => r.json());

  const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const cartaHTML = c => c.imagen
    ? `<img src="${esc(c.imagen)}" alt="${esc(c.nombre)}">`
    : `<div class="carta-texto" style="background:hsl(${(c.numero * 47) % 360},55%,45%)">${c.numero}<br>${esc(c.nombre)}</div>`;

  function mostrar(id) { for (const p of ['lobby', 'juego', 'fin']) $(p).classList.toggle('oculto', p !== id); }

  function render(e) {
    estado = e;
    if (e.estado === 'lobby') {
      mostrar('lobby');
      if (!qrHecho) {
        const lado = Math.min(360, Math.floor(window.innerHeight * 0.42));
        new QRCode($('qr'), {text: e.url_jugar, width: lado, height: lado, correctLevel: QRCode.CorrectLevel.M});
        $('url').textContent = e.url_jugar; qrHecho = true;
      }
      $('nJug').textContent = e.jugadores.length;
      $('listaLobby').innerHTML = e.jugadores.map(j => `<li>${esc(j.nombre)}</li>`).join('');
      $('btnIniciar').disabled = e.jugadores.length === 0;
      return;
    }
    if (e.estado === 'terminada') {
      mostrar('fin'); pararAuto(); $('alerta').classList.add('oculto');
      $('finGanador').textContent = e.ganador ? `🏆 Ganó ${e.ganador}` : 'Sin ganador';
      $('finResumen').textContent = `Se cantaron ${e.indice + 1} de ${e.total} cartas · ${e.jugadores.length} jugador${e.jugadores.length === 1 ? '' : 'es'}`;
      return;
    }
    mostrar('juego');
    if (e.carta_actual) {
      const nuevo = cartaHTML(e.carta_actual);
      if ($('cartaActual').innerHTML !== nuevo) $('cartaActual').innerHTML = nuevo;
      $('nombreActual').textContent = `${e.carta_actual.numero} · ${e.carta_actual.nombre}`;
    }
    $('tira').innerHTML = e.ultimas.map(c => `<div class="carta">${cartaHTML(c)}</div>`).join('');
    $('contador').textContent = `Carta ${e.indice + 1} de ${e.total}`;
    $('btnPausa').textContent = e.estado === 'pausada' ? '▶ Continuar' : '⏸ Pausa';
    $('btnSiguiente').disabled = e.estado !== 'jugando';
    $('listaJuego').innerHTML = e.jugadores.map(j => `<li>${esc(j.nombre)} <span class="pill">${j.marcas}/16</span></li>`).join('');
    $('listaGritos').innerHTML = e.gritos.map(g => `<li>${esc(g.nombre)} <b class="${g.valido ? 'ok' : 'mal'}">${g.valido ? 'válido' : 'falso'}</b></li>`).join('') || '<li class="tenue">Ninguno</li>';
    if (document.activeElement !== $('numSeg')) { $('chkAuto').checked = e.auto; $('numSeg').value = e.intervalo; }
    const pendiente = e.gritos.find(g => !g.atendido);
    if (pendiente && gritoMostrado !== pendiente.id) {
      gritoMostrado = pendiente.id;
      $('alNombre').textContent = pendiente.nombre;
      $('alVeredicto').textContent = pendiente.valido ? '✔ TABLERO VÁLIDO' : '✘ FALSA ALARMA: su tablero no está completo';
      $('alVeredicto').className = 'veredicto ' + (pendiente.valido ? 'ok' : 'mal');
      $('alGanador').classList.toggle('oculto', !pendiente.valido);
      $('alGanador').dataset.jugador = pendiente.jugador_id;
      $('alerta').classList.remove('oculto');
      pararAuto();
    }
    if (!pendiente) $('alerta').classList.add('oculto');
    if (e.estado === 'jugando' && e.auto && !timerAuto) arrancarAuto(e.intervalo);
    if ((e.estado !== 'jugando' || !e.auto) && timerAuto) pararAuto();
  }

  function arrancarAuto(seg) { pararAuto(); timerAuto = setInterval(siguiente, seg * 1000); }
  function pararAuto() { if (timerAuto) clearInterval(timerAuto); timerAuto = null; }

  async function siguiente() {
    if (ocupado || !estado || estado.estado !== 'jugando') return;
    ocupado = true;
    try { await api('siguiente'); await ciclo(); } finally { ocupado = false; }
  }

  async function ciclo() { try { const e = await sondear(); if (e.ok) render(e); } catch (_) {} }

  $('btnIniciar').onclick = async () => { $('btnIniciar').disabled = true; await api('iniciar'); ciclo(); };
  $('btnSiguiente').onclick = siguiente;
  $('btnPausa').onclick = async () => { if (!estado) return; await api(estado.estado === 'pausada' ? 'continuar' : 'pausar'); ciclo(); };
  $('btnTerminar').onclick = async () => { if (confirm('¿Terminar la partida sin ganador?')) { await api('terminar'); ciclo(); } };
  $('btnPanel').onclick = () => $('panel').classList.toggle('abierto');
  const guardarAuto = async () => {
    const r = await api('config_auto', {auto: $('chkAuto').checked ? 1 : 0, intervalo_seg: $('numSeg').value});
    if (!r.ok) alert(r.error);
    pararAuto(); ciclo();
  };
  $('chkAuto').onchange = guardarAuto; $('numSeg').onchange = guardarAuto;
  $('alGanador').onclick = async () => { await api('terminar', {ganador_id: $('alGanador').dataset.jugador}); $('alerta').classList.add('oculto'); ciclo(); };
  $('alContinuar').onclick = async () => { await api('continuar'); $('alerta').classList.add('oculto'); ciclo(); };
  document.addEventListener('keydown', ev => {
    if (['INPUT', 'SELECT', 'TEXTAREA'].includes(ev.target.tagName)) return;
    if (ev.code === 'Space' || ev.code === 'ArrowRight') { ev.preventDefault(); siguiente(); }
    if (ev.key.toLowerCase() === 'p') $('btnPausa').click();
  });

  ciclo(); setInterval(ciclo, LOT.sondeo);
})();

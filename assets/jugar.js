(() => {
  const $ = id => document.getElementById(id);
  const cod = window.CODIGO, clave = 'lot_token_' + cod;
  let token = null, tablero = [], marcas = new Set(), estado = null, gritando = false;
  try { token = localStorage.getItem(clave); } catch (_) {}

  const api = async (a, datos = {}) => {
    const fd = new FormData(); for (const [k, v] of Object.entries(datos)) fd.append(k, v);
    return fetch('api.php?a=' + a, {method: 'POST', body: fd}).then(r => r.json());
  };
  const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const cartaHTML = c => c.imagen
    ? `<img src="${esc(c.imagen)}" alt="${esc(c.nombre)}" draggable="false">`
    : `<div class="carta-texto" style="background:hsl(${(c.numero * 47) % 360},55%,45%)">${c.numero}<br>${esc(c.nombre)}</div>`;

  function pintarTablero() {
    $('cuadricula').innerHTML = tablero.map(c => `<div class="carta celda" data-id="${c.id}">${cartaHTML(c)}<div class="frijol"></div></div>`).join('');
    for (const el of $('cuadricula').children) el.addEventListener('click', () => tocar(el));
    pintarMarcas();
  }
  function pintarMarcas() {
    for (const el of $('cuadricula').children) el.classList.toggle('marcada', marcas.has(+el.dataset.id));
    $('cuenta').textContent = `${marcas.size}/16`;
  }
  function aviso(txt, clase = '') {
    const a = $('aviso'); a.textContent = txt; a.className = 'aviso ' + clase; a.classList.toggle('oculto', !txt);
  }
  function mostrarTablero() {
    $('entrar').classList.add('oculto'); $('tablero').classList.remove('oculto');
  }

  async function tocar(el) {
    if (!estado || !['jugando', 'pausada'].includes(estado.estado)) return;
    const id = +el.dataset.id;
    if (marcas.has(id)) {
      const r = await api('desmarcar', {token, carta_id: id});
      if (r.ok) { marcas = new Set(r.marcas); pintarMarcas(); }
      return;
    }
    const r = await api('marcar', {token, carta_id: id});
    if (r.ok) { marcas = new Set(r.marcas); pintarMarcas(); return; }
    // Rechazada: solo vibrar y sacudir, sin explicar por qué.
    if (navigator.vibrate) navigator.vibrate(200);
    el.classList.remove('sacudir'); void el.offsetWidth; el.classList.add('sacudir');
  }

  function render(e) {
    estado = e;
    marcas = new Set(e.marcas); pintarMarcas();
    $('estadoTxt').textContent = {lobby: 'Esperando que inicie…', jugando: 'En juego', pausada: 'Pausa', terminada: 'Terminada'}[e.estado] || e.estado;
    $('btnGritar').disabled = !e.puede_gritar || gritando;
    if (e.estado === 'terminada') aviso(e.ganador ? `🏆 Ganó ${e.ganador}` : 'La partida terminó', 'fin');
    else if (e.mi_grito && !e.mi_grito.atendido) aviso(e.mi_grito.valido ? '✔ ¡Tu lotería es válida! Muestra tu teléfono' : '✘ Tu tablero todavía no es válido', e.mi_grito.valido ? 'ok' : 'mal');
    else if (e.estado === 'pausada') aviso('Juego en pausa');
    else aviso('');
  }

  async function ciclo() {
    if (!token) return;
    try {
      const e = await fetch(`api.php?a=estado_jugador&token=${encodeURIComponent(token)}`).then(r => r.json());
      if (!e.ok) {
        if (e.error === 'Jugador no encontrado') { try { localStorage.removeItem(clave); } catch (_) {} token = null; location.reload(); }
        return;
      }
      if (!tablero.length) { tablero = e.tablero; $('miNombre').textContent = e.nombre; pintarTablero(); mostrarTablero(); }
      render(e);
    } catch (_) {}
  }

  $('formNombre').addEventListener('submit', async ev => {
    ev.preventDefault();
    const btn = ev.target.querySelector('button'); btn.disabled = true;
    const r = await api('unirse', {codigo: cod, nombre: new FormData(ev.target).get('nombre')});
    btn.disabled = false;
    if (!r.ok) { $('err').textContent = r.error; $('err').classList.remove('oculto'); return; }
    token = r.token; try { localStorage.setItem(clave, token); } catch (_) {}
    tablero = r.tablero; $('miNombre').textContent = r.nombre; pintarTablero(); mostrarTablero();
    ciclo();
  });

  $('btnGritar').onclick = async () => {
    if (gritando) return;
    gritando = true; $('btnGritar').disabled = true;
    const r = await api('gritar', {token});
    if (!r.ok) aviso(r.error, 'mal');
    setTimeout(() => { gritando = false; }, 5000);
    ciclo();
  };

  ciclo(); setInterval(ciclo, LOT.sondeo);
})();

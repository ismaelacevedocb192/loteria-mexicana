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
(() => {
  const $ = id => document.getElementById(id);
  const k = LOT.k ? '&k=' + encodeURIComponent(LOT.k) : '';
  const get = a => fetch('api.php?a=' + a + k).then(r => r.json());
  const post = (a, fd) => { if (LOT.k) fd.append('k', LOT.k); return fetch('api.php?a=' + a, {method: 'POST', body: fd}).then(r => r.json()); };
  const fdDe = obj => { const fd = new FormData(); for (const [a, b] of Object.entries(obj)) fd.append(a, b); return fd; };
  const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const cartaHTML = c => c.imagen
    ? `<img src="${esc(c.imagen)}?v=${LOT.v}" alt="${esc(c.nombre)}">`
    : `<div class="carta-texto" style="background:hsl(${(c.numero * 47) % 360},55%,45%)">${c.numero}<br>${esc(c.nombre)}</div>`;
  let actual = null;

  async function listar() {
    const r = await get('mazos_listar');
    if (!r.ok) return alert(r.error);
    $('listaMazos').innerHTML = r.mazos.map(m =>
      `<li><a href="#" data-id="${m.id}" class="${actual === m.id ? 'activo' : ''}">${esc(m.nombre)}${m.es_default ? ' 🔒' : ''}</a> <span class="pill">${m.cartas} cartas</span></li>`).join('');
    for (const a of $('listaMazos').querySelectorAll('a')) a.onclick = e => { e.preventDefault(); abrir(+a.dataset.id); };
  }

  async function abrir(id) {
    actual = id;
    const r = await get('mazo_cartas&mazo_id=' + id);
    if (!r.ok) return alert(r.error);
    $('detalle').innerHTML = '';
    $('detalle').append($('tplDetalle').content.cloneNode(true));
    $('detNombre').textContent = r.mazo.nombre;
    const editable = !r.mazo.es_default;
    $('formCarta').classList.toggle('oculto', !editable);
    $('formCarta').mazo_id.value = id;
    if (editable) $('detAcciones').innerHTML = `<button class="btn btn-secundario" id="btnRenombrar">Renombrar</button> <button class="btn btn-peligro" id="btnBorrarMazo">Borrar mazo</button>`;
    else $('detAcciones').innerHTML = '<span class="pill">Mazo clásico, solo lectura</span>';
    $('cartas').innerHTML = r.cartas.map(c => `<div class="carta-item"><div class="carta">${cartaHTML(c)}</div><div class="carta-pie"><b>${c.numero}</b> ${esc(c.nombre)}
        ${editable ? `<button class="mini" data-editar="${c.id}" title="Editar">✎</button><button class="mini" data-borrar="${c.id}" title="Borrar">🗑</button>` : ''}</div></div>`).join('')
      || '<p class="tenue">Sin cartas. Agrega al menos 16 para poder jugar.</p>';
    listar();
    if (!editable) return;
    $('formCarta').onsubmit = async e => {
      e.preventDefault();
      const rr = await post('carta_guardar', new FormData(e.target));
      if (!rr.ok) { $('errCarta').textContent = rr.error; $('errCarta').classList.remove('oculto'); return; }
      const siguienteNumero = +e.target.numero.value + 1;
      await abrir(id);
      if (!e.target.id.value) { $('formCarta').numero.value = siguienteNumero; $('formCarta').nombre.focus(); }
    };
    $('btnCancelar').onclick = () => { const f = $('formCarta'); f.reset(); f.id.value = ''; f.mazo_id.value = id; $('btnCancelar').classList.add('oculto'); };
    for (const b of $('cartas').querySelectorAll('[data-editar]')) b.onclick = () => {
      const c = r.cartas.find(x => x.id == b.dataset.editar);
      const f = $('formCarta'); f.id.value = c.id; f.numero.value = c.numero; f.nombre.value = c.nombre;
      $('btnCancelar').classList.remove('oculto'); f.nombre.focus();
    };
    for (const b of $('cartas').querySelectorAll('[data-borrar]')) b.onclick = async () => {
      if (!confirm('¿Borrar esta carta?')) return;
      const rr = await post('carta_borrar', fdDe({id: b.dataset.borrar}));
      if (!rr.ok) alert(rr.error);
      abrir(id);
    };
    $('btnRenombrar').onclick = async () => {
      const n = prompt('Nuevo nombre del mazo', r.mazo.nombre);
      if (!n) return;
      const rr = await post('mazo_renombrar', fdDe({id, nombre: n}));
      if (!rr.ok) alert(rr.error);
      abrir(id);
    };
    $('btnBorrarMazo').onclick = async () => {
      if (!confirm('¿Borrar el mazo y todas sus cartas?')) return;
      const rr = await post('mazo_borrar', fdDe({id}));
      if (!rr.ok) return alert(rr.error);
      actual = null; $('detalle').innerHTML = '<p class="tenue">Elige un mazo de la lista</p>'; listar();
    };
  }

  $('formMazo').onsubmit = async e => {
    e.preventDefault();
    const r = await post('mazo_crear', new FormData(e.target));
    if (!r.ok) return alert(r.error);
    e.target.reset(); await listar(); abrir(r.id);
  };
  listar();
})();

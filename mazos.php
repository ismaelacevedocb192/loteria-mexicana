<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/vista.php';
exigirClaveVista();
cabecera('Mazos', 'mazos');
?>
<main class="contenedor">
  <header class="barra"><h1>Mazos</h1><a class="btn btn-secundario" href="index.php">← Inicio</a></header>
  <p class="tenue">Crea mazos con tus propias cartas (por ejemplo, conceptos de una materia). Un mazo necesita al menos 16 cartas para jugar. Si una carta no tiene imagen, se muestra su nombre sobre un fondo de color.</p>
  <section class="dos-columnas">
    <div class="tarjeta">
      <form id="formMazo" class="fila"><input name="nombre" placeholder="Nombre del nuevo mazo" maxlength="80" required><button class="btn btn-primario">Crear</button></form>
      <ul id="listaMazos" class="lista"></ul>
    </div>
    <div class="tarjeta" id="detalle"><p class="tenue">Elige un mazo de la lista</p></div>
  </section>
</main>
<template id="tplDetalle">
  <header class="barra"><h2 id="detNombre"></h2><span id="detAcciones"></span></header>
  <form id="formCarta" class="form-carta" enctype="multipart/form-data">
    <input type="hidden" name="id"><input type="hidden" name="mazo_id">
    <input name="numero" type="number" min="1" max="999" placeholder="N°" required title="Número de la carta">
    <input name="nombre" placeholder="Nombre de la carta" maxlength="80" required>
    <input name="imagen" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" title="Imagen opcional (PNG, JPG, WEBP o SVG, máx. 2 MB)">
    <button class="btn btn-primario">Guardar carta</button>
    <button type="button" class="btn btn-secundario oculto" id="btnCancelar">Cancelar</button>
    <p class="error oculto" id="errCarta"></p>
  </form>
  <div class="rejilla-cartas" id="cartas"></div>
</template>
<script src="assets/mazos.js?v=<?= assetVer() ?>"></script>
<?php pie();

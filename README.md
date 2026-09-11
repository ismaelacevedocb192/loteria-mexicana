# Lotería web

Juego de Lotería para un salón: una **pantalla grande** (presentador) muestra un código QR con el que los jugadores reciben su tablero en el teléfono; luego canta las cartas en grande. Cada jugador marca su tablero 4×4 en el teléfono; el servidor **rechaza marcar cartas que aún no han salido**. Al llenar el tablero, el jugador pulsa **¡LOTERÍA!**, el servidor verifica y la pantalla grande lo anuncia.

- Mazo clásico de 54 cartas con ilustraciones propias (SVG) y **mazos personalizados** (por ejemplo, conceptos de una materia) desde un panel.
- Colores de las fiestas patrias y cintilla inferior con el nombre de la institución, configurable en `config.php` (`INSTITUCION`).
- Avance manual o con temporizador.
- El teléfono no muestra la carta actual ni resalta las salidas: el jugador debe estar atento a la pantalla.
- PHP plano + MySQL (o SQLite para pruebas). Corre en hosting LAMP compartido y en Railway.

## Requisitos

- PHP 8.1 o superior con `pdo_mysql` (y opcionalmente `pdo_sqlite` para pruebas locales), `mbstring`, `fileinfo`.
- MySQL / MariaDB.

## Instalación en LAMP (hosting compartido)

1. Sube la carpeta completa al servidor (por ejemplo a `public_html/loteria`).
2. Crea una base de datos MySQL vacía.
3. Edita `config.php` **o** crea `config.local.php` (no se sube a git) con:
   ```php
   <?php
   define('DB_DSN', 'mysql:host=localhost;dbname=TU_BD;charset=utf8mb4');
   define('DB_USER', 'TU_USUARIO');
   define('DB_PASS', 'TU_CLAVE');
   // define('PRESENTADOR_CLAVE', 'algo-secreto'); // opcional: protege las páginas de presentador
   ```
4. Da permisos de escritura a `uploads/` (para imágenes de mazos personalizados).
5. Abre `https://tu-dominio/loteria/`. Las tablas y el mazo clásico se crean solos en la primera visita.

## Despliegue en Railway

1. Crea un proyecto desde este repositorio; Railway detecta el `Dockerfile`.
2. Agrega un servicio **MySQL** al proyecto.
3. En el servicio web define las variables:
   - `DB_DSN` = `mysql:host=${{MySQL.MYSQLHOST}};port=${{MySQL.MYSQLPORT}};dbname=${{MySQL.MYSQLDATABASE}};charset=utf8mb4`
   - `DB_USER` = `${{MySQL.MYSQLUSER}}`
   - `DB_PASS` = `${{MySQL.MYSQLPASSWORD}}`
   - `PRESENTADOR_CLAVE` (recomendado en Railway, porque la URL es pública)
   - `BASE_URL` (opcional) = `https://tu-app.up.railway.app`
4. Genera un dominio público. Nota: `uploads/` es efímero en Railway; si usas imágenes en mazos personalizados, monta un volumen en `/var/www/html/uploads`.

## Cómo se juega

1. **Inicio** → elige mazo → **Nueva partida**. Se abre la pantalla del presentador con el QR.
2. Los jugadores escanean el QR (o abren `jugar.php?c=CÓDIGO`), escriben su nombre y reciben su tablero. No se admiten dos nombres iguales en la misma partida: la comparación ignora mayúsculas, acentos y espacios de más, así que "Ana" y " aná " cuentan como el mismo.
3. El presentador pulsa **Iniciar partida**. Ya no entran más jugadores.
4. **Siguiente** (o barra espaciadora / flecha derecha) canta la siguiente carta. **Auto** avanza sola cada N segundos. **P** pausa.
5. Los jugadores tocan en su teléfono las cartas que van saliendo. Si tocan una que no ha salido, el teléfono solo vibra.
6. Con 16 marcas se habilita **¡LOTERÍA!**. La pantalla grande muestra la alerta con **VÁLIDO** o **FALSO**; el presentador decide **Terminar (ganador)** o **Continuar**.
7. Si el jugador recarga o cierra la página, recupera su tablero automáticamente en el mismo teléfono.
8. La portada lista las partidas con su estado y su ganador.
9. En la portada, cada partida tiene un botón 🗑 para borrarla, y hay un botón para borrar de golpe todas las terminadas. Al borrar se eliminan también sus jugadores, tableros y marcas.

Si `PRESENTADOR_CLAVE` está definida, abre las páginas de presentador con `?k=LA_CLAVE` una vez; queda guardada en una cookie.

## Mazos personalizados

En **Administrar mazos** crea un mazo y agrega cartas con número, nombre e imagen opcional (PNG, JPG, WEBP o SVG, máx. 2 MB). Sin imagen, la carta muestra el nombre sobre un fondo de color. Se necesitan al menos 16 cartas para jugar. El mazo clásico no se puede editar.

## Desarrollo

```bash
# Pruebas (usan SQLite en memoria)
php tests/run.php

# Servidor local con SQLite (ignora config.local.php, no necesita MySQL)
php -S 127.0.0.1:8080 tools/dev-router.php

# Regenerar las cartas del mazo clásico
php tools/generar_cartas.php
```

Estructura: `api.php` es el único endpoint JSON (`?a=accion`); la lógica pura está en `lib/juego.php`; las pantallas son `index.php`, `presentador.php`, `jugar.php` y `mazos.php` con su JS en `assets/`.

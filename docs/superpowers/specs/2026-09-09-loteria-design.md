# Lotería web — Diseño

Fecha: 2026-09-09

## Objetivo

Página web para jugar Lotería en un salón: una pantalla grande (presentador)
muestra un QR para repartir tableros a los teléfonos de los jugadores y luego
canta las cartas en grande. Los jugadores marcan su tablero 4×4 en el teléfono;
el servidor rechaza marcas de cartas que aún no han salido. Cuando un jugador
llena su tablero pulsa "¡LOTERÍA!", el servidor verifica y la pantalla grande
lo anuncia. Debe correr en hosting LAMP compartido y también en Railway.

## Decisiones tomadas

| Tema | Decisión |
|---|---|
| Cartas | Mazo clásico de 54 (nombres y numeración tradicionales) con **ilustraciones SVG propias**, más mazos personalizados creados desde un panel. |
| Avance de cartas | Botón manual "Siguiente" siempre disponible, y temporizador opcional configurable (segundos) con pausa. El temporizador corre en el navegador del presentador. |
| ¡Lotería! | Botón en el teléfono, habilitado solo con 16 marcas. El servidor verifica que las 16 cartas del tablero ya salieron. Pausa la partida y la pantalla grande muestra alerta con nombre y VÁLIDO / FALSO. Presentador decide Terminar o Continuar. Solo tablero lleno, sin modalidades cortas. |
| Identidad del jugador | Nombre libre al entrar. Token secreto en `localStorage` recupera el mismo tablero al recargar. |
| Acceso presentador | Sin contraseña por defecto. `config.php` tiene `PRESENTADOR_CLAVE`; si no está vacía, las páginas y acciones de presentador la exigen (query `?k=` o cookie). |
| Partidas | Se pueden crear varias; cada una tiene código de 6 caracteres y su propio QR. |
| Dificultad | El teléfono **no** muestra la carta actual ni resalta las cartas que ya salieron. El jugador debe mirar la pantalla grande. Un toque a una carta no salida solo vibra y sacude la carta, sin explicación. |
| Desmarcar | Permitido (por si se tocó una carta equivocada). |
| Tiempo real | Sondeo HTTP (polling) cada 1.5 s. Sin WebSockets (no disponibles en LAMP compartido). |
| Base de datos | PDO con DSN configurable: MySQL en producción, SQLite opcional para pruebas locales. `db.php` crea las tablas si no existen. |
| Stack | PHP 8 plano, sin frameworks; JS vanilla; CSS propio. Igual que los demás proyectos de la carpeta. |

## Estructura de archivos

```
loteria/
├── config.php            # DSN, zona horaria, PRESENTADOR_CLAVE, INTERVALO_SONDEO_MS
├── db.php                # getDB() PDO + creación de tablas + siembra del mazo clásico
├── lib/
│   └── juego.php         # lógica pura: barajar, generar tablero, validar marca, verificar lotería, código de partida
├── index.php             # Portada: crear partida, partidas recientes, enlace a mazos
├── presentador.php       # Pantalla grande
├── jugar.php             # Teléfono
├── mazos.php             # Panel de mazos personalizados
├── api.php               # Único endpoint JSON (?a=accion)
├── assets/
│   ├── app.css
│   ├── presentador.js
│   ├── jugar.js
│   ├── mazos.js
│   └── qrcode.min.js     # generación de QR en cliente (librería MIT, copia local)
├── cartas/               # 54 SVG del mazo clásico, diseño propio: 01.svg … 54.svg
├── uploads/              # imágenes subidas para mazos personalizados (gitignored salvo .gitkeep)
├── tests/
│   ├── run.php           # corredor mínimo: ejecuta test_*.php, imprime OK/FAIL
│   ├── test_juego.php    # lógica pura
│   └── test_api.php      # humo del API con SQLite en memoria
├── schema.sql            # referencia (db.php es la fuente de verdad)
├── Dockerfile            # php:8.2-apache para Railway
├── .gitignore
└── README.md             # instalación LAMP y Railway
```

## Modelo de datos

```
mazos      (id, nombre, es_default 0/1, creado)
cartas     (id, mazo_id, numero, nombre, imagen)        -- imagen: 'cartas/01.svg' | 'uploads/x.png' | NULL
partidas   (id, codigo UNIQUE, mazo_id, estado, orden_cartas JSON, indice_actual,
            auto 0/1, intervalo_seg, ganador_id NULL, creada, actualizada)
jugadores  (id, partida_id, nombre, token UNIQUE, tablero JSON[16 ids], creado)
marcas     (jugador_id, carta_id, marcada_en)  PK(jugador_id, carta_id)
gritos     (id, partida_id, jugador_id, valido 0/1, hora)
```

Estados de partida: `lobby` → `jugando` ⇄ `pausada` → `terminada`.

`indice_actual` = -1 en lobby; 0 significa que salió la primera carta de
`orden_cartas`. Cartas salidas = `orden_cartas[0..indice_actual]`.

Restricciones:
- Un mazo necesita ≥ 16 cartas para crear partida.
- Tablero: 16 ids distintos tomados al azar del mazo; se reintenta hasta que el
  conjunto (ordenado) no coincida con otro tablero de la misma partida (máx. 20 intentos).
- Solo se puede `unirse` en estado `lobby`.
- `marcar` acepta solo cartas del tablero del jugador que ya salieron y partida en
  `jugando` o `pausada`. `desmarcar` acepta cualquier carta del tablero.
- `gritar` exige 16 marcas; el servidor recalcula: válido ⇔ las 16 cartas del
  tablero ⊆ cartas salidas. Siempre pasa la partida a `pausada` y registra el grito.
- El mazo clásico se siembra en la primera conexión si no existe ningún mazo con `es_default=1`.

## API — `api.php?a=<accion>`

Respuesta siempre JSON: `{"ok":true, ...}` o `{"ok":false,"error":"mensaje"}`.
Las acciones de escritura van por POST (form o JSON). Las de presentador
exigen `PRESENTADOR_CLAVE` si está configurada.

| Acción | Rol | Entrada | Salida |
|---|---|---|---|
| `crear_partida` | presentador | mazo_id | codigo |
| `estado_presentador` | presentador (sondeo) | codigo | estado, indice, total, carta_actual, ultimas (8), jugadores [{nombre, marcas}], gritos pendientes [{jugador, valido, hora}], auto, intervalo, url_jugar |
| `iniciar` | presentador | codigo | baraja y pasa a `jugando`, indice 0 |
| `siguiente` | presentador | codigo | indice+1; si se acabó el mazo → `terminada` |
| `pausar` / `continuar` | presentador | codigo | cambia estado |
| `terminar` | presentador | codigo, ganador_id opcional | `terminada` |
| `config_auto` | presentador | codigo, auto, intervalo_seg | guarda |
| `unirse` | jugador | codigo, nombre | token, tablero [{id, numero, nombre, imagen}] |
| `estado_jugador` | jugador (sondeo) | token | estado, marcas [ids], puede_gritar, ganador (si terminada), aviso de grito propio |
| `marcar` / `desmarcar` | jugador | token, carta_id | ok / rechazado |
| `gritar` | jugador | token | valido 0/1 |
| `mazos_listar` | panel | — | mazos con conteo de cartas |
| `mazo_crear` / `mazo_renombrar` / `mazo_borrar` | panel | … | (no se puede borrar el default ni uno usado por partidas) |
| `carta_guardar` / `carta_borrar` | panel | mazo_id, numero, nombre, imagen (multipart opcional) | id |

Subida de imágenes: solo png/jpg/webp/svg, máx. 2 MB, nombre aleatorio en `uploads/`.

## Pantallas

**Portada (`index.php`)**: selector de mazo, botón "Nueva partida", lista de
partidas recientes con estado y enlace a su pantalla de presentador, enlace a Mazos.

**Presentador (`presentador.php?c=CODIGO`)**: fondo oscuro.
- Lobby: QR grande (≈60 % del alto) con la URL `jugar.php?c=CODIGO`, el código
  en texto grande, lista de jugadores conectados en vivo, botón "Iniciar"
  (deshabilitado con 0 jugadores).
- Jugando: carta actual ocupando casi todo el alto con número y nombre; tira de
  las últimas 8 cartas abajo; contador "Carta 12 de 54"; barra de controles:
  Siguiente, Pausa/Continuar, Auto (toggle + segundos), Terminar; panel lateral
  plegable con jugadores y su conteo de marcas.
- Alerta de Lotería: overlay a pantalla completa "🎉 {nombre} gritó ¡Lotería!" con
  VÁLIDO (verde) o FALSO (rojo); botones "Terminar partida (ganador)" o "Continuar".
  Si hay varios gritos, se muestra el primero y los demás quedan en el panel.
- Terminada: nombre del ganador (si lo hay), total de cartas cantadas, botón "Nueva partida".
- Atajos de teclado: espacio/→ = Siguiente, P = pausa.

**Teléfono (`jugar.php?c=CODIGO`)**:
- Sin token válido: campo de nombre + "Entrar". Errores: código inexistente,
  partida ya empezada o terminada.
- Con tablero: cuadrícula 4×4 que ocupa el ancho, sin scroll; cada celda con
  imagen y nombre; marcada = frijol (círculo) semitransparente encima. Encabezado
  mínimo con nombre del jugador y contador de marcas. Botón "¡LOTERÍA!" fijo
  abajo, deshabilitado hasta 16 marcas. Estado "esperando que inicie",
  "pausada", "terminada — ganó X".
- Toque a carta no salida: `navigator.vibrate(200)` + animación de sacudida. Sin mensaje.

**Mazos (`mazos.php`)**: lista de mazos; al elegir uno, tabla de cartas con
número, nombre, miniatura, editar/borrar; formulario para agregar carta con
imagen opcional (sin imagen se renderiza el nombre sobre un fondo de color
derivado del número). El mazo clásico se puede ver pero no editar.

## Mazo clásico (diseño propio)

54 archivos SVG en `cartas/`, viewBox 300×420, estilo grabado/papel picado:
marco ornamentado, número arriba, nombre abajo, ilustración geométrica plana
propia en 3–4 colores. Nombres y orden tradicionales: 1 El Gallo, 2 El Diablito,
3 La Dama, 4 El Catrín, 5 El Paraguas, 6 La Sirena, 7 La Escalera, 8 La Botella,
9 El Barril, 10 El Árbol, 11 El Melón, 12 El Valiente, 13 El Gorrito, 14 La Muerte,
15 La Pera, 16 La Bandera, 17 El Bandolón, 18 El Violoncello, 19 La Garza,
20 El Pájaro, 21 La Mano, 22 La Bota, 23 La Luna, 24 El Cotorro, 25 El Borracho,
26 El Negrito, 27 El Corazón, 28 La Sandía, 29 El Tambor, 30 El Camarón,
31 Las Jaras, 32 El Músico, 33 La Araña, 34 El Soldado, 35 La Estrella,
36 El Cazo, 37 El Mundo, 38 El Apache, 39 El Nopal, 40 El Alacrán, 41 La Rosa,
42 La Calavera, 43 La Campana, 44 El Cantarito, 45 El Venado, 46 El Sol,
47 La Corona, 48 La Chalupa, 49 El Pino, 50 El Pescado, 51 La Palma,
52 La Maceta, 53 El Arpa, 54 La Rana.

## Errores y casos límite

- Código inexistente / partida terminada: mensaje claro en el teléfono.
- Entrar después de iniciar: rechazado ("la partida ya empezó").
- Nombre vacío o > 30 caracteres: rechazado. Nombres duplicados permitidos (se
  distingue por tablero #).
- Pérdida de red: el sondeo reintenta en el siguiente ciclo; las marcas se
  guardan al instante y se reconcilian con la respuesta del servidor.
- Dos gritos casi simultáneos: ambos se registran; la alerta muestra el más
  antiguo no atendido.
- `siguiente` cuando ya salió la última carta: pasa a `terminada` sin ganador.
- Temporizador: si el presentador recarga, el toggle se restaura desde `partidas.auto`.
- Mazo con < 16 cartas: `crear_partida` falla con mensaje.

## Pruebas

- `tests/test_juego.php`: barajar produce permutación completa sin repetidos;
  tablero tiene 16 ids únicos del mazo; tableros no se repiten en una partida;
  validar marca acepta cartas salidas y rechaza no salidas / ajenas al tablero;
  verificar lotería válido solo si las 16 salieron; código de partida de 6 chars
  sin caracteres ambiguos (0/O, 1/I/L).
- `tests/test_api.php`: con `DSN=sqlite::memory:`, flujo completo: crear
  partida → unirse ×2 → iniciar → siguiente ×N → marcar (aceptada/rechazada) →
  gritar falso/válido → terminar.
- Corredor: `php tests/run.php`.
- Verificación manual final en navegador: presentador + jugador en pestañas separadas.

## Despliegue

- **LAMP**: copiar carpeta, editar `config.php` con credenciales MySQL, dar
  permisos de escritura a `uploads/`. Las tablas se crean solas.
- **Railway**: `Dockerfile` con `php:8.2-apache`; variables de entorno
  `DB_DSN`, `DB_USER`, `DB_PASS` (config.php las lee con `getenv` antes que las
  constantes). Servicio MySQL de Railway. Nota: `uploads/` es efímero salvo volumen.

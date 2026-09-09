# Lotería web — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Juego de Lotería para salón: pantalla grande con QR y cartas cantadas, tableros 4×4 en teléfonos con marcas validadas por servidor y grito de ¡Lotería! verificado.

**Architecture:** PHP 8 plano + PDO (MySQL en producción, SQLite para pruebas), un solo `api.php` JSON consultado por sondeo cada 1.5 s desde `presentador.js` y `jugar.js`. La lógica pura del juego vive en `lib/juego.php` y se prueba sin base de datos; el flujo completo se prueba contra SQLite en memoria.

**Tech Stack:** PHP ≥ 8.1, PDO (pdo_mysql, pdo_sqlite), JS vanilla, CSS propio, `qrcode.min.js` (davidshimjs, MIT) copia local, Docker `php:8.2-apache` para Railway.

**Spec:** `docs/superpowers/specs/2026-09-09-loteria-design.md`

## Global Constraints

- Sin frameworks ni Composer. Todo PHP plano, JS vanilla.
- Todo texto de interfaz en español.
- Toda respuesta del API: `{"ok":true,...}` o `{"ok":false,"error":"..."}`, `Content-Type: application/json; charset=utf-8`.
- Escrituras del API por POST. Sondeos por GET.
- Acciones de presentador exigen `PRESENTADOR_CLAVE` solo si no está vacía (`?k=` o cookie `lot_k`).
- El teléfono nunca recibe qué cartas han salido (ni resalta, ni muestra la carta actual).
- Tablero: 16 ids únicos; mazo mínimo 16 cartas.
- Código de partida: 6 caracteres del alfabeto `ABCDEFGHJKMNPQRSTUVWXYZ23456789`.
- Estados: `lobby`, `jugando`, `pausada`, `terminada`.
- `indice_actual = -1` en lobby; cartas salidas = `orden_cartas[0..indice_actual]`.
- SQL compatible con MySQL y SQLite (sin `ON DUPLICATE KEY`, usar `INSERT OR IGNORE`/`INSERT IGNORE` según driver vía helper).
- Corredor de pruebas: `php tests/run.php` debe terminar con `OK` y código de salida 0.
- Commit al final de cada tarea.

---

### Task 1: Config, conexión y corredor de pruebas

**Files:**
- Create: `config.php`, `db.php`, `tests/run.php`, `tests/test_db.php`, `uploads/.gitkeep`

**Interfaces:**
- Produces: `getDB(): PDO` (singleton), `dbDriver(): string` (`'mysql'|'sqlite'`), `dbInsertIgnore(string $sql): string`, `crearTablas(PDO $pdo): void`, constantes `DB_DSN, DB_USER, DB_PASS, PRESENTADOR_CLAVE, INTERVALO_SONDEO_MS, BASE_URL`.
- Las pruebas definen `define('LOTERIA_TEST_DSN','sqlite::memory:')` antes de incluir `db.php`; `db.php` respeta esa constante.

- [ ] **Step 1: Escribir prueba que falla**

`tests/run.php`:
```php
<?php
// Corredor mínimo: incluye cada tests/test_*.php; cada uno registra pruebas con test('nombre', fn).
$tests = [];
function test(string $nombre, callable $fn): void { global $tests; $tests[] = [$nombre, $fn]; }
function assertTrue($cond, string $msg = ''): void { if (!$cond) throw new Exception("assertTrue falló. $msg"); }
function assertEq($esp, $real, string $msg = ''): void {
    if ($esp !== $real) throw new Exception("assertEq falló: esperado " . var_export($esp, true) . ", real " . var_export($real, true) . ". $msg");
}
foreach (glob(__DIR__ . '/test_*.php') as $f) require $f;
$fallos = 0;
foreach ($tests as [$nombre, $fn]) {
    try { $fn(); echo "  ✓ $nombre\n"; }
    catch (Throwable $e) { $fallos++; echo "  ✗ $nombre\n      " . $e->getMessage() . "\n      " . $e->getFile() . ':' . $e->getLine() . "\n"; }
}
echo $fallos ? "FALLARON $fallos de " . count($tests) . "\n" : "OK (" . count($tests) . " pruebas)\n";
exit($fallos ? 1 : 0);
```

`tests/test_db.php`:
```php
<?php
if (!defined('LOTERIA_TEST_DSN')) define('LOTERIA_TEST_DSN', 'sqlite::memory:');
require_once __DIR__ . '/../db.php';

test('getDB crea las tablas en sqlite en memoria', function () {
    $pdo = getDB();
    $tablas = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['mazos', 'cartas', 'partidas', 'jugadores', 'marcas', 'gritos'] as $t) {
        assertTrue(in_array($t, $tablas), "falta tabla $t");
    }
});

test('dbInsertIgnore adapta la sintaxis al driver', function () {
    assertEq('INSERT OR IGNORE INTO x', dbInsertIgnore('INSERT INTO x'));
});
```

- [ ] **Step 2: Correr y ver que falla**

Run: `php tests/run.php`
Expected: fatal por `db.php` inexistente.

- [ ] **Step 3: Implementar config.php y db.php**

`config.php`:
```php
<?php
// Configuración. En Railway se leen variables de entorno; en LAMP edita las constantes.
date_default_timezone_set('America/Mexico_City');

define('DB_DSN',  getenv('DB_DSN')  ?: 'mysql:host=localhost;dbname=loteria;charset=utf8mb4');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Si no está vacía, presentador.php, index.php, mazos.php y las acciones de presentador la exigen (?k=clave).
define('PRESENTADOR_CLAVE', getenv('PRESENTADOR_CLAVE') ?: '');

define('INTERVALO_SONDEO_MS', 1500);

// URL base pública (sin diagonal final). Vacío = se deduce de la petición.
define('BASE_URL', getenv('BASE_URL') ?: '');

if (file_exists(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';
```

`db.php`:
```php
<?php
require_once __DIR__ . '/config.php';

function dbDriver(): string {
    $dsn = defined('LOTERIA_TEST_DSN') ? LOTERIA_TEST_DSN : DB_DSN;
    return str_starts_with($dsn, 'sqlite') ? 'sqlite' : 'mysql';
}

function dbInsertIgnore(string $sql): string {
    return dbDriver() === 'sqlite'
        ? preg_replace('/^INSERT INTO/i', 'INSERT OR IGNORE INTO', $sql)
        : preg_replace('/^INSERT INTO/i', 'INSERT IGNORE INTO', $sql);
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = defined('LOTERIA_TEST_DSN') ? LOTERIA_TEST_DSN : DB_DSN;
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = dbDriver() === 'sqlite' ? new PDO($dsn, null, null, $opts) : new PDO($dsn, DB_USER, DB_PASS, $opts);
    if (dbDriver() === 'sqlite') $pdo->exec('PRAGMA foreign_keys = ON');
    crearTablas($pdo);
    return $pdo;
}

function crearTablas(PDO $pdo): void {
    $sqlite = dbDriver() === 'sqlite';
    $id  = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $now = $sqlite ? "DATETIME DEFAULT CURRENT_TIMESTAMP" : "DATETIME DEFAULT CURRENT_TIMESTAMP";
    $eng = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $pdo->exec("CREATE TABLE IF NOT EXISTS mazos (
        id $id, nombre VARCHAR(80) NOT NULL, es_default TINYINT NOT NULL DEFAULT 0, creado $now)$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cartas (
        id $id, mazo_id INT NOT NULL, numero INT NOT NULL, nombre VARCHAR(80) NOT NULL, imagen VARCHAR(255) NULL)$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS partidas (
        id $id, codigo VARCHAR(6) NOT NULL UNIQUE, mazo_id INT NOT NULL, estado VARCHAR(12) NOT NULL DEFAULT 'lobby',
        orden_cartas TEXT NULL, indice_actual INT NOT NULL DEFAULT -1, auto TINYINT NOT NULL DEFAULT 0,
        intervalo_seg INT NOT NULL DEFAULT 6, ganador_id INT NULL, creada $now, actualizada $now)$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS jugadores (
        id $id, partida_id INT NOT NULL, nombre VARCHAR(30) NOT NULL, token VARCHAR(40) NOT NULL UNIQUE,
        tablero TEXT NOT NULL, creado $now)$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS marcas (
        jugador_id INT NOT NULL, carta_id INT NOT NULL, marcada_en $now, PRIMARY KEY (jugador_id, carta_id))$eng");
    $pdo->exec("CREATE TABLE IF NOT EXISTS gritos (
        id $id, partida_id INT NOT NULL, jugador_id INT NOT NULL, valido TINYINT NOT NULL, atendido TINYINT NOT NULL DEFAULT 0, hora $now)$eng");
}
```

- [ ] **Step 4: Correr pruebas**

Run: `php tests/run.php`
Expected: `OK (2 pruebas)`

- [ ] **Step 5: Commit**

```bash
touch uploads/.gitkeep
git add config.php db.php tests/ uploads/.gitkeep
git commit -m "feat: config, conexión PDO con tablas automáticas y corredor de pruebas"
```

---

### Task 2: Lógica pura del juego (`lib/juego.php`)

**Files:**
- Create: `lib/juego.php`, `tests/test_juego.php`

**Interfaces:**
- Produces:
  - `generarCodigo(): string` — 6 chars del alfabeto sin ambiguos.
  - `generarToken(): string` — 32 hex.
  - `barajar(array $ids): array` — permutación de los ids (int[]).
  - `generarTablero(array $idsMazo, array $tablerosExistentes): array` — 16 ids únicos; evita repetir conjunto con `$tablerosExistentes` (array de arrays de ids), máx. 20 intentos.
  - `cartasSalidas(array $orden, int $indice): array` — `array_slice($orden, 0, $indice+1)` o `[]` si indice < 0.
  - `puedeMarcar(int $cartaId, array $tablero, array $salidas): bool`.
  - `esLoteriaValida(array $tablero, array $salidas): bool`.

- [ ] **Step 1: Escribir pruebas que fallan**

`tests/test_juego.php`:
```php
<?php
require_once __DIR__ . '/../lib/juego.php';

test('generarCodigo da 6 chars sin ambiguos', function () {
    for ($i = 0; $i < 50; $i++) {
        $c = generarCodigo();
        assertEq(6, strlen($c));
        assertTrue(preg_match('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}$/', $c) === 1, $c);
    }
});

test('generarToken da 32 hex', function () {
    assertTrue(preg_match('/^[0-9a-f]{32}$/', generarToken()) === 1);
});

test('barajar es permutación completa', function () {
    $ids = range(1, 54);
    $b = barajar($ids);
    assertEq(54, count($b));
    $s = $b; sort($s);
    assertEq($ids, $s);
});

test('generarTablero da 16 ids únicos del mazo', function () {
    $mazo = range(100, 153);
    $t = generarTablero($mazo, []);
    assertEq(16, count($t));
    assertEq(16, count(array_unique($t)));
    foreach ($t as $id) assertTrue(in_array($id, $mazo));
});

test('generarTablero evita repetir conjunto existente', function () {
    $mazo = range(1, 17); // solo 17 tableros posibles, fácil chocar
    $existentes = [];
    for ($i = 0; $i < 10; $i++) {
        $t = generarTablero($mazo, $existentes);
        $s = $t; sort($s);
        foreach ($existentes as $e) { $es = $e; sort($es); assertTrue($es !== $s, 'tablero repetido'); }
        $existentes[] = $t;
    }
});

test('cartasSalidas respeta el índice', function () {
    assertEq([], cartasSalidas([5, 6, 7], -1));
    assertEq([5], cartasSalidas([5, 6, 7], 0));
    assertEq([5, 6, 7], cartasSalidas([5, 6, 7], 2));
    assertEq([5, 6, 7], cartasSalidas([5, 6, 7], 9));
});

test('puedeMarcar solo cartas del tablero que ya salieron', function () {
    $tablero = [1, 2, 3];
    assertTrue(puedeMarcar(2, $tablero, [9, 2]));
    assertTrue(!puedeMarcar(3, $tablero, [9, 2]));   // no ha salido
    assertTrue(!puedeMarcar(9, $tablero, [9, 2]));   // no está en tablero
});

test('esLoteriaValida solo si las 16 salieron', function () {
    $tablero = range(1, 16);
    assertTrue(esLoteriaValida($tablero, range(1, 20)));
    assertTrue(!esLoteriaValida($tablero, range(1, 15)));
    assertTrue(esLoteriaValida($tablero, array_reverse(range(1, 16))));
});
```

- [ ] **Step 2: Correr y ver que falla**

Run: `php tests/run.php`
Expected: fatal por `lib/juego.php` inexistente.

- [ ] **Step 3: Implementar**

`lib/juego.php`:
```php
<?php
// Lógica pura del juego. Sin base de datos, sin estado global.

const CODIGO_ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
const TABLERO_TAM = 16;

function generarCodigo(): string {
    $s = '';
    $n = strlen(CODIGO_ALFABETO);
    for ($i = 0; $i < 6; $i++) $s .= CODIGO_ALFABETO[random_int(0, $n - 1)];
    return $s;
}

function generarToken(): string {
    return bin2hex(random_bytes(16));
}

function barajar(array $ids): array {
    $ids = array_values($ids);
    for ($i = count($ids) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    }
    return $ids;
}

function generarTablero(array $idsMazo, array $tablerosExistentes): array {
    $existentes = array_map(function ($t) { sort($t); return implode(',', $t); }, $tablerosExistentes);
    $tablero = [];
    for ($intento = 0; $intento < 20; $intento++) {
        $tablero = array_slice(barajar($idsMazo), 0, TABLERO_TAM);
        $clave = $tablero; sort($clave);
        if (!in_array(implode(',', $clave), $existentes, true)) break;
    }
    return array_map('intval', $tablero);
}

function cartasSalidas(array $orden, int $indice): array {
    if ($indice < 0) return [];
    return array_map('intval', array_slice($orden, 0, $indice + 1));
}

function puedeMarcar(int $cartaId, array $tablero, array $salidas): bool {
    return in_array($cartaId, $tablero, true) && in_array($cartaId, $salidas, true);
}

function esLoteriaValida(array $tablero, array $salidas): bool {
    if (count($tablero) !== TABLERO_TAM) return false;
    return count(array_diff($tablero, $salidas)) === 0;
}
```

- [ ] **Step 4: Correr pruebas**

Run: `php tests/run.php`
Expected: `OK (10 pruebas)`

- [ ] **Step 5: Commit**

```bash
git add lib/juego.php tests/test_juego.php
git commit -m "feat: lógica pura del juego con pruebas"
```

---

### Task 3: Mazo clásico con SVG propios

**Files:**
- Create: `lib/mazo_clasico.php`, `tools/generar_cartas.php`, `cartas/01.svg … 54.svg`, `tests/test_mazo.php`
- Modify: `db.php` (siembra)

**Interfaces:**
- Produces: `MAZO_CLASICO` (const array de 54 `[numero, nombre]`), `sembrarMazoClasico(PDO $pdo): int` (id del mazo default, crea si no existe), `getDB()` llama a `sembrarMazoClasico` tras crear tablas.

- [ ] **Step 1: Prueba que falla**

`tests/test_mazo.php`:
```php
<?php
if (!defined('LOTERIA_TEST_DSN')) define('LOTERIA_TEST_DSN', 'sqlite::memory:');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/mazo_clasico.php';

test('MAZO_CLASICO tiene 54 cartas numeradas 1..54', function () {
    assertEq(54, count(MAZO_CLASICO));
    assertEq(range(1, 54), array_column(MAZO_CLASICO, 0));
    assertEq('El Gallo', MAZO_CLASICO[0][1]);
    assertEq('La Rana', MAZO_CLASICO[53][1]);
});

test('existen los 54 SVG en cartas/', function () {
    for ($i = 1; $i <= 54; $i++) {
        $f = sprintf(__DIR__ . '/../cartas/%02d.svg', $i);
        assertTrue(file_exists($f), "falta $f");
        assertTrue(str_contains(file_get_contents($f), '<svg'), "$f no es svg");
    }
});

test('getDB siembra el mazo clásico una sola vez', function () {
    $pdo = getDB();
    $n = $pdo->query("SELECT COUNT(*) FROM mazos WHERE es_default=1")->fetchColumn();
    assertEq(1, (int)$n);
    sembrarMazoClasico($pdo);
    $n = $pdo->query("SELECT COUNT(*) FROM mazos WHERE es_default=1")->fetchColumn();
    assertEq(1, (int)$n);
    $c = $pdo->query("SELECT COUNT(*) FROM cartas")->fetchColumn();
    assertEq(54, (int)$c);
    $img = $pdo->query("SELECT imagen FROM cartas WHERE numero=7")->fetchColumn();
    assertEq('cartas/07.svg', $img);
});
```

- [ ] **Step 2: Correr y ver que falla**

Run: `php tests/run.php` → fallan las 3 nuevas.

- [ ] **Step 3: Implementar lista y siembra**

`lib/mazo_clasico.php`:
```php
<?php
const MAZO_CLASICO = [
 [1,'El Gallo'],[2,'El Diablito'],[3,'La Dama'],[4,'El Catrín'],[5,'El Paraguas'],[6,'La Sirena'],
 [7,'La Escalera'],[8,'La Botella'],[9,'El Barril'],[10,'El Árbol'],[11,'El Melón'],[12,'El Valiente'],
 [13,'El Gorrito'],[14,'La Muerte'],[15,'La Pera'],[16,'La Bandera'],[17,'El Bandolón'],[18,'El Violoncello'],
 [19,'La Garza'],[20,'El Pájaro'],[21,'La Mano'],[22,'La Bota'],[23,'La Luna'],[24,'El Cotorro'],
 [25,'El Borracho'],[26,'El Negrito'],[27,'El Corazón'],[28,'La Sandía'],[29,'El Tambor'],[30,'El Camarón'],
 [31,'Las Jaras'],[32,'El Músico'],[33,'La Araña'],[34,'El Soldado'],[35,'La Estrella'],[36,'El Cazo'],
 [37,'El Mundo'],[38,'El Apache'],[39,'El Nopal'],[40,'El Alacrán'],[41,'La Rosa'],[42,'La Calavera'],
 [43,'La Campana'],[44,'El Cantarito'],[45,'El Venado'],[46,'El Sol'],[47,'La Corona'],[48,'La Chalupa'],
 [49,'El Pino'],[50,'El Pescado'],[51,'La Palma'],[52,'La Maceta'],[53,'El Arpa'],[54,'La Rana'],
];

function sembrarMazoClasico(PDO $pdo): int {
    $id = $pdo->query("SELECT id FROM mazos WHERE es_default=1 LIMIT 1")->fetchColumn();
    if ($id) return (int)$id;
    $pdo->prepare("INSERT INTO mazos (nombre, es_default) VALUES (?, 1)")->execute(['Lotería clásica']);
    $id = (int)$pdo->lastInsertId();
    $st = $pdo->prepare("INSERT INTO cartas (mazo_id, numero, nombre, imagen) VALUES (?, ?, ?, ?)");
    foreach (MAZO_CLASICO as [$n, $nombre]) $st->execute([$id, $n, $nombre, sprintf('cartas/%02d.svg', $n)]);
    return $id;
}
```

En `db.php`, al final de `getDB()` antes del `return`:
```php
    require_once __DIR__ . '/lib/mazo_clasico.php';
    sembrarMazoClasico($pdo);
```

- [ ] **Step 4: Generador de SVG**

`tools/generar_cartas.php` produce `cartas/NN.svg` (viewBox 300×420). Marco común: fondo crema `#f6e7c8`, borde doble `#8b2f1f`, esquinas con florones, número arriba en círculo, nombre abajo en banda `#1f3a5f`. Paleta de figuras: `#c8412b` rojo, `#e0a526` amarillo, `#2b6f5c` verde, `#1f3a5f` azul, `#2a2a2a` negro. Cada carta define un fragmento SVG propio (formas geométricas: círculos, polígonos, paths simples) centrado en el área 40..260 × 70..330. El script contiene un array `$figuras[numero] = '<g>...</g>'` con las 54 ilustraciones y escribe los archivos. Ejemplo de tres entradas (las demás siguen el mismo estilo, cada una distinta y reconocible):

```php
$figuras[23] = // La Luna
 '<circle cx="150" cy="200" r="90" fill="#e0a526"/><circle cx="185" cy="185" r="80" fill="#f6e7c8"/>
  <circle cx="120" cy="170" r="6" fill="#2a2a2a"/><path d="M105 225 q15 15 30 0" stroke="#2a2a2a" stroke-width="5" fill="none"/>
  <polygon points="230,110 236,126 252,126 239,136 244,152 230,142 216,152 221,136 208,126 224,126" fill="#e0a526"/>';
$figuras[46] = // El Sol
 '<g stroke="#e0a526" stroke-width="14" stroke-linecap="round">'.implode('',array_map(fn($a)=>
   sprintf('<line x1="%.0f" y1="%.0f" x2="%.0f" y2="%.0f"/>',150+100*cos($a),200+100*sin($a),150+130*cos($a),200+130*sin($a)),
   array_map(fn($i)=>$i*M_PI/6, range(0,11)))).'</g>
  <circle cx="150" cy="200" r="85" fill="#e0a526" stroke="#c8412b" stroke-width="6"/>
  <circle cx="125" cy="185" r="7" fill="#2a2a2a"/><circle cx="175" cy="185" r="7" fill="#2a2a2a"/>
  <path d="M120 225 q30 25 60 0" stroke="#2a2a2a" stroke-width="6" fill="none"/>';
$figuras[8] = // La Botella
 '<path d="M130 80 h40 v40 q30 20 30 60 v130 q0 20 -20 20 h-60 q-20 0 -20 -20 v-130 q0 -40 30 -60 z" fill="#2b6f5c" stroke="#2a2a2a" stroke-width="5"/>
  <rect x="125" y="70" width="50" height="16" rx="4" fill="#c8412b"/><rect x="110" y="200" width="80" height="60" fill="#f6e7c8" stroke="#2a2a2a" stroke-width="3"/>';
```

Marco:
```php
function carta_svg(int $n, string $nombre, string $figura): string {
    $nom = htmlspecialchars($nombre, ENT_QUOTES);
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 420" width="300" height="420">
<rect width="300" height="420" rx="14" fill="#f6e7c8"/>
<rect x="8" y="8" width="284" height="404" rx="10" fill="none" stroke="#8b2f1f" stroke-width="4"/>
<rect x="16" y="16" width="268" height="388" rx="8" fill="none" stroke="#8b2f1f" stroke-width="1.5"/>
<g fill="#8b2f1f"><circle cx="24" cy="24" r="5"/><circle cx="276" cy="24" r="5"/><circle cx="24" cy="396" r="5"/><circle cx="276" cy="396" r="5"/></g>
<circle cx="150" cy="46" r="22" fill="#1f3a5f"/>
<text x="150" y="53" text-anchor="middle" font-family="Georgia, serif" font-size="22" font-weight="bold" fill="#f6e7c8">$n</text>
<g>$figura</g>
<rect x="24" y="346" width="252" height="44" rx="8" fill="#1f3a5f"/>
<text x="150" y="376" text-anchor="middle" font-family="Georgia, serif" font-size="24" font-weight="bold" fill="#f6e7c8">$nom</text>
</svg>
SVG;
}
foreach (MAZO_CLASICO as [$n, $nombre]) file_put_contents(sprintf(__DIR__.'/../cartas/%02d.svg', $n), carta_svg($n, $nombre, $figuras[$n]));
```

Run: `php tools/generar_cartas.php` y verificar `ls cartas | wc -l` = 54.

- [ ] **Step 5: Correr pruebas**

Run: `php tests/run.php` → `OK (13 pruebas)`

- [ ] **Step 6: Commit**

```bash
git add lib/mazo_clasico.php tools/generar_cartas.php cartas/ db.php tests/test_mazo.php
git commit -m "feat: mazo clásico con 54 SVG propios y siembra automática"
```

---

### Task 4: API de partidas y jugadores

**Files:**
- Create: `api.php`, `lib/api_partidas.php`, `lib/api_jugadores.php`, `lib/http.php`, `tests/test_api.php`

**Interfaces:**
- `lib/http.php`: `jsonOk(array $d=[])`, `jsonError(string $m, int $code=400)`, `param(string $k, $def=null)` (lee POST JSON, POST form o GET), `exigirClavePresentador(): void`, `urlBase(): string`.
- `api.php`: `require` de los tres lib, despacha `$_GET['a']` a `api_<accion>()` si existe la función; si no, error 404 "acción desconocida". Las pruebas incluyen los lib y llaman las funciones `api_*` directamente con `$GLOBALS['__params']` fijado por un helper `llamar(string $accion, array $params): array` que captura la salida JSON (output buffering) y decodifica.
- Funciones `api_crear_partida, api_estado_presentador, api_iniciar, api_siguiente, api_pausar, api_continuar, api_terminar, api_config_auto, api_unirse, api_estado_jugador, api_marcar, api_desmarcar, api_gritar`.
- Helper interno `cargarPartida(string $codigo): array` (lanza ApiException 404), `cargarJugador(string $token): array` (incluye partida), `ApiException extends Exception` con código HTTP.

- [ ] **Step 1: Pruebas que fallan** — `tests/test_api.php`:

```php
<?php
if (!defined('LOTERIA_TEST_DSN')) define('LOTERIA_TEST_DSN', 'sqlite::memory:');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/api_partidas.php';
require_once __DIR__ . '/../lib/api_jugadores.php';

function llamar(string $accion, array $params = []): array {
    $GLOBALS['__params'] = $params;
    ob_start();
    try { ("api_$accion")(); } catch (ApiException $e) { jsonError($e->getMessage(), $e->getCode()); }
    $out = ob_get_clean();
    $r = json_decode($out, true);
    if ($r === null) throw new Exception("respuesta no JSON en $accion: $out");
    return $r;
}

test('flujo completo de partida', function () {
    $pdo = getDB();
    $mazoId = (int)$pdo->query("SELECT id FROM mazos WHERE es_default=1")->fetchColumn();

    $r = llamar('crear_partida', ['mazo_id' => $mazoId]);
    assertTrue($r['ok'], json_encode($r));
    $cod = $r['codigo'];
    assertEq(6, strlen($cod));

    $j1 = llamar('unirse', ['codigo' => $cod, 'nombre' => 'Ana']);
    assertTrue($j1['ok'], json_encode($j1));
    assertEq(16, count($j1['tablero']));
    assertTrue(!isset($j1['tablero'][0]['salida']), 'el tablero no debe revelar cartas salidas');
    $j2 = llamar('unirse', ['codigo' => $cod, 'nombre' => 'Beto']);
    assertTrue($j2['ok']);

    assertTrue(!llamar('unirse', ['codigo' => $cod, 'nombre' => ''])['ok'], 'nombre vacío');
    assertTrue(!llamar('unirse', ['codigo' => 'ZZZZZZ', 'nombre' => 'X'])['ok'], 'código inexistente');

    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq('lobby', $e['estado']);
    assertEq(2, count($e['jugadores']));

    // marcar antes de iniciar: rechazado
    $m = llamar('marcar', ['token' => $j1['token'], 'carta_id' => $j1['tablero'][0]['id']]);
    assertTrue(!$m['ok']);

    assertTrue(llamar('iniciar', ['codigo' => $cod])['ok']);
    assertTrue(!llamar('unirse', ['codigo' => $cod, 'nombre' => 'Tarde'])['ok'], 'no entrar tras iniciar');

    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq('jugando', $e['estado']);
    assertEq(0, $e['indice']);
    assertEq(54, $e['total']);
    assertTrue(isset($e['carta_actual']['nombre']));

    // sacar cartas hasta que salgan las 16 de Ana
    $tableroAna = array_column($j1['tablero'], 'id');
    $orden = json_decode($pdo->query("SELECT orden_cartas FROM partidas WHERE codigo='$cod'")->fetchColumn(), true);
    $pos = max(array_map(fn($id) => array_search($id, $orden), $tableroAna));
    // primero una carta no salida (si existe) → rechazada
    $noSalida = null;
    foreach ($tableroAna as $id) if (array_search($id, $orden) > 0) { $noSalida = $id; break; }
    if ($noSalida !== null) {
        $m = llamar('marcar', ['token' => $j1['token'], 'carta_id' => $noSalida]);
        assertTrue(!$m['ok'], 'no debe marcar carta no salida');
    }
    // gritar sin 16 marcas: rechazado
    assertTrue(!llamar('gritar', ['token' => $j1['token']])['ok']);

    for ($i = 0; $i < $pos; $i++) assertTrue(llamar('siguiente', ['codigo' => $cod])['ok']);
    foreach ($tableroAna as $id) {
        $m = llamar('marcar', ['token' => $j1['token'], 'carta_id' => $id]);
        assertTrue($m['ok'], "marcar $id: " . json_encode($m));
    }
    // desmarcar y volver a marcar
    assertTrue(llamar('desmarcar', ['token' => $j1['token'], 'carta_id' => $tableroAna[0]])['ok']);
    $ej = llamar('estado_jugador', ['token' => $j1['token']]);
    assertEq(15, count($ej['marcas']));
    assertTrue(!$ej['puede_gritar']);
    assertTrue(llamar('marcar', ['token' => $j1['token'], 'carta_id' => $tableroAna[0]])['ok']);
    $ej = llamar('estado_jugador', ['token' => $j1['token']]);
    assertTrue($ej['puede_gritar']);
    assertTrue(!isset($ej['salidas']) && !isset($ej['carta_actual']), 'el jugador no debe recibir cartas salidas');

    $g = llamar('gritar', ['token' => $j1['token']]);
    assertTrue($g['ok'] && $g['valido'] === true, json_encode($g));
    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq('pausada', $e['estado']);
    assertEq(1, count($e['gritos']));
    assertEq('Ana', $e['gritos'][0]['nombre']);

    assertTrue(llamar('continuar', ['codigo' => $cod])['ok']);
    assertEq('jugando', llamar('estado_presentador', ['codigo' => $cod])['estado']);
    assertTrue(llamar('terminar', ['codigo' => $cod, 'ganador_id' => $e['gritos'][0]['jugador_id']])['ok']);
    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq('terminada', $e['estado']);
    assertEq('Ana', $e['ganador']);
    $ej = llamar('estado_jugador', ['token' => $j2['token']]);
    assertEq('terminada', $ej['estado']);
    assertEq('Ana', $ej['ganador']);
});

test('grito falso: 16 marcas no son posibles sin cartas salidas, pero se verifica igual', function () {
    $pdo = getDB();
    $mazoId = (int)$pdo->query("SELECT id FROM mazos WHERE es_default=1")->fetchColumn();
    $cod = llamar('crear_partida', ['mazo_id' => $mazoId])['codigo'];
    $j = llamar('unirse', ['codigo' => $cod, 'nombre' => 'Caro']);
    llamar('iniciar', ['codigo' => $cod]);
    // forzar marcas directas en BD (simula manipulación) sin que salgan las cartas
    $jid = $pdo->query("SELECT id FROM jugadores WHERE token='{$j['token']}'")->fetchColumn();
    foreach ($j['tablero'] as $c) $pdo->exec("INSERT INTO marcas (jugador_id, carta_id) VALUES ($jid, {$c['id']})");
    $g = llamar('gritar', ['token' => $j['token']]);
    assertTrue($g['ok']);
    assertEq(false, $g['valido']);
    assertEq('pausada', llamar('estado_presentador', ['codigo' => $cod])['estado']);
});

test('siguiente tras la última carta termina la partida', function () {
    $pdo = getDB();
    $mazoId = (int)$pdo->query("SELECT id FROM mazos WHERE es_default=1")->fetchColumn();
    $cod = llamar('crear_partida', ['mazo_id' => $mazoId])['codigo'];
    llamar('unirse', ['codigo' => $cod, 'nombre' => 'D']);
    llamar('iniciar', ['codigo' => $cod]);
    for ($i = 0; $i < 53; $i++) llamar('siguiente', ['codigo' => $cod]);
    assertEq(53, llamar('estado_presentador', ['codigo' => $cod])['indice']);
    llamar('siguiente', ['codigo' => $cod]);
    assertEq('terminada', llamar('estado_presentador', ['codigo' => $cod])['estado']);
});

test('config_auto guarda y estado lo devuelve', function () {
    $pdo = getDB();
    $mazoId = (int)$pdo->query("SELECT id FROM mazos WHERE es_default=1")->fetchColumn();
    $cod = llamar('crear_partida', ['mazo_id' => $mazoId])['codigo'];
    assertTrue(llamar('config_auto', ['codigo' => $cod, 'auto' => 1, 'intervalo_seg' => 4])['ok']);
    $e = llamar('estado_presentador', ['codigo' => $cod]);
    assertEq(true, $e['auto']);
    assertEq(4, $e['intervalo']);
    assertTrue(!llamar('config_auto', ['codigo' => $cod, 'auto' => 1, 'intervalo_seg' => 0])['ok']);
});

test('crear_partida rechaza mazo con menos de 16 cartas', function () {
    $pdo = getDB();
    $pdo->exec("INSERT INTO mazos (nombre) VALUES ('chico')");
    $id = $pdo->lastInsertId();
    $r = llamar('crear_partida', ['mazo_id' => $id]);
    assertTrue(!$r['ok']);
});
```

- [ ] **Step 2: Correr y ver que falla** — `php tests/run.php` falla por archivos inexistentes.

- [ ] **Step 3: Implementar `lib/http.php`**

```php
<?php
require_once __DIR__ . '/../config.php';

class ApiException extends Exception {}

function param(string $k, $def = null) {
    if (isset($GLOBALS['__params'])) return $GLOBALS['__params'][$k] ?? $def;
    static $json = null;
    if ($json === null) {
        $json = [];
        if (str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $json = json_decode(file_get_contents('php://input'), true) ?: [];
        }
    }
    return $json[$k] ?? $_POST[$k] ?? $_GET[$k] ?? $def;
}

function jsonOk(array $d = []): void {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $d, JSON_UNESCAPED_UNICODE);
}

function jsonError(string $m, int $code = 400): void {
    if (!headers_sent()) { http_response_code($code ?: 400); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
}

function claveOk(): bool {
    if (PRESENTADOR_CLAVE === '') return true;
    $k = $_GET['k'] ?? $_POST['k'] ?? $_COOKIE['lot_k'] ?? '';
    return hash_equals(PRESENTADOR_CLAVE, (string)$k);
}

function exigirClavePresentador(): void {
    if (isset($GLOBALS['__params'])) return; // pruebas
    if (!claveOk()) throw new ApiException('Clave de presentador requerida', 403);
}

function urlBase(): string {
    if (BASE_URL !== '') return rtrim(BASE_URL, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}
```

- [ ] **Step 4: Implementar `lib/api_partidas.php`**

```php
<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/juego.php';

function cargarPartida(string $codigo): array {
    $st = getDB()->prepare("SELECT * FROM partidas WHERE codigo = ?");
    $st->execute([strtoupper(trim($codigo))]);
    $p = $st->fetch();
    if (!$p) throw new ApiException('Partida no encontrada', 404);
    $p['orden'] = $p['orden_cartas'] ? json_decode($p['orden_cartas'], true) : [];
    return $p;
}

function actualizarPartida(int $id, array $campos): void {
    $sets = []; $vals = [];
    foreach ($campos as $k => $v) { $sets[] = "$k = ?"; $vals[] = $v; }
    $sets[] = 'actualizada = CURRENT_TIMESTAMP';
    $vals[] = $id;
    getDB()->prepare("UPDATE partidas SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
}

function cartaPorId(?int $id): ?array {
    if (!$id) return null;
    $st = getDB()->prepare("SELECT id, numero, nombre, imagen FROM cartas WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function api_crear_partida(): void {
    exigirClavePresentador();
    $pdo = getDB();
    $mazoId = (int)param('mazo_id', 0);
    $st = $pdo->prepare("SELECT COUNT(*) FROM cartas WHERE mazo_id = ?");
    $st->execute([$mazoId]);
    if ((int)$st->fetchColumn() < TABLERO_TAM) throw new ApiException('El mazo necesita al menos 16 cartas', 400);
    for ($i = 0; $i < 10; $i++) {
        $codigo = generarCodigo();
        $st = $pdo->prepare("SELECT 1 FROM partidas WHERE codigo = ?");
        $st->execute([$codigo]);
        if (!$st->fetch()) break;
    }
    $pdo->prepare("INSERT INTO partidas (codigo, mazo_id) VALUES (?, ?)")->execute([$codigo, $mazoId]);
    jsonOk(['codigo' => $codigo]);
}

function api_estado_presentador(): void {
    exigirClavePresentador();
    $pdo = getDB();
    $p = cargarPartida((string)param('codigo', ''));
    $salidas = cartasSalidas($p['orden'], (int)$p['indice_actual']);
    $ultimas = [];
    foreach (array_slice(array_reverse($salidas), 1, 8) as $id) $ultimas[] = cartaPorId($id);
    $st = $pdo->prepare("SELECT j.id, j.nombre, (SELECT COUNT(*) FROM marcas m WHERE m.jugador_id = j.id) AS marcas
                         FROM jugadores j WHERE j.partida_id = ? ORDER BY j.id");
    $st->execute([$p['id']]);
    $jugadores = array_map(fn($j) => ['id' => (int)$j['id'], 'nombre' => $j['nombre'], 'marcas' => (int)$j['marcas']], $st->fetchAll());
    $st = $pdo->prepare("SELECT g.id, g.jugador_id, g.valido, g.atendido, g.hora, j.nombre FROM gritos g JOIN jugadores j ON j.id = g.jugador_id
                         WHERE g.partida_id = ? ORDER BY g.id");
    $st->execute([$p['id']]);
    $gritos = array_map(fn($g) => ['id' => (int)$g['id'], 'jugador_id' => (int)$g['jugador_id'], 'nombre' => $g['nombre'],
                                   'valido' => (bool)$g['valido'], 'atendido' => (bool)$g['atendido'], 'hora' => $g['hora']], $st->fetchAll());
    $ganador = $p['ganador_id'] ? ($pdo->query("SELECT nombre FROM jugadores WHERE id = " . (int)$p['ganador_id'])->fetchColumn() ?: null) : null;
    $total = count($p['orden']) ?: (int)$pdo->query("SELECT COUNT(*) FROM cartas WHERE mazo_id = " . (int)$p['mazo_id'])->fetchColumn();
    jsonOk([
        'codigo' => $p['codigo'], 'estado' => $p['estado'], 'indice' => (int)$p['indice_actual'], 'total' => $total,
        'carta_actual' => $salidas ? cartaPorId(end($salidas)) : null, 'ultimas' => $ultimas,
        'jugadores' => $jugadores, 'gritos' => $gritos, 'ganador' => $ganador,
        'auto' => (bool)$p['auto'], 'intervalo' => (int)$p['intervalo_seg'],
        'url_jugar' => urlBase() . '/jugar.php?c=' . $p['codigo'],
    ]);
}

function api_iniciar(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    if ($p['estado'] !== 'lobby') throw new ApiException('La partida ya inició', 400);
    $st = getDB()->prepare("SELECT id FROM cartas WHERE mazo_id = ?");
    $st->execute([$p['mazo_id']]);
    $orden = barajar(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
    actualizarPartida((int)$p['id'], ['estado' => 'jugando', 'orden_cartas' => json_encode($orden), 'indice_actual' => 0]);
    jsonOk();
}

function api_siguiente(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    if (!in_array($p['estado'], ['jugando', 'pausada'])) throw new ApiException('La partida no está en juego', 400);
    $i = (int)$p['indice_actual'] + 1;
    if ($i >= count($p['orden'])) { actualizarPartida((int)$p['id'], ['estado' => 'terminada']); jsonOk(['terminada' => true]); return; }
    actualizarPartida((int)$p['id'], ['indice_actual' => $i, 'estado' => 'jugando']);
    jsonOk(['indice' => $i]);
}

function api_pausar(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    if ($p['estado'] !== 'jugando') throw new ApiException('No se puede pausar', 400);
    actualizarPartida((int)$p['id'], ['estado' => 'pausada']);
    jsonOk();
}

function api_continuar(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    if ($p['estado'] !== 'pausada') throw new ApiException('La partida no está pausada', 400);
    getDB()->prepare("UPDATE gritos SET atendido = 1 WHERE partida_id = ?")->execute([$p['id']]);
    actualizarPartida((int)$p['id'], ['estado' => 'jugando']);
    jsonOk();
}

function api_terminar(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    $g = (int)param('ganador_id', 0) ?: null;
    getDB()->prepare("UPDATE gritos SET atendido = 1 WHERE partida_id = ?")->execute([$p['id']]);
    actualizarPartida((int)$p['id'], ['estado' => 'terminada', 'ganador_id' => $g]);
    jsonOk();
}

function api_config_auto(): void {
    exigirClavePresentador();
    $p = cargarPartida((string)param('codigo', ''));
    $auto = (int)(bool)param('auto', 0);
    $seg = (int)param('intervalo_seg', 6);
    if ($seg < 2 || $seg > 60) throw new ApiException('El intervalo debe estar entre 2 y 60 segundos', 400);
    actualizarPartida((int)$p['id'], ['auto' => $auto, 'intervalo_seg' => $seg]);
    jsonOk();
}
```

- [ ] **Step 5: Implementar `lib/api_jugadores.php`**

```php
<?php
require_once __DIR__ . '/api_partidas.php';

function cargarJugador(string $token): array {
    $st = getDB()->prepare("SELECT j.*, p.codigo FROM jugadores j JOIN partidas p ON p.id = j.partida_id WHERE j.token = ?");
    $st->execute([$token]);
    $j = $st->fetch();
    if (!$j) throw new ApiException('Jugador no encontrado', 404);
    $j['tablero_ids'] = array_map('intval', json_decode($j['tablero'], true));
    $j['partida'] = cargarPartida($j['codigo']);
    return $j;
}

function marcasDe(int $jugadorId): array {
    $st = getDB()->prepare("SELECT carta_id FROM marcas WHERE jugador_id = ?");
    $st->execute([$jugadorId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function tableroConCartas(array $ids): array {
    $pdo = getDB();
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, numero, nombre, imagen FROM cartas WHERE id IN ($in)");
    $st->execute($ids);
    $porId = [];
    foreach ($st->fetchAll() as $c) $porId[(int)$c['id']] = ['id' => (int)$c['id'], 'numero' => (int)$c['numero'], 'nombre' => $c['nombre'], 'imagen' => $c['imagen']];
    return array_map(fn($id) => $porId[$id], $ids);
}

function api_unirse(): void {
    $pdo = getDB();
    $p = cargarPartida((string)param('codigo', ''));
    $nombre = trim((string)param('nombre', ''));
    if ($nombre === '' || mb_strlen($nombre) > 30) throw new ApiException('Escribe un nombre de 1 a 30 caracteres', 400);
    if ($p['estado'] !== 'lobby') throw new ApiException('La partida ya empezó, ya no se puede entrar', 400);
    $st = $pdo->prepare("SELECT id FROM cartas WHERE mazo_id = ?");
    $st->execute([$p['mazo_id']]);
    $idsMazo = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    $st = $pdo->prepare("SELECT tablero FROM jugadores WHERE partida_id = ?");
    $st->execute([$p['id']]);
    $existentes = array_map(fn($t) => json_decode($t, true), $st->fetchAll(PDO::FETCH_COLUMN));
    $tablero = generarTablero($idsMazo, $existentes);
    $token = generarToken();
    $pdo->prepare("INSERT INTO jugadores (partida_id, nombre, token, tablero) VALUES (?, ?, ?, ?)")
        ->execute([$p['id'], $nombre, $token, json_encode($tablero)]);
    jsonOk(['token' => $token, 'nombre' => $nombre, 'tablero' => tableroConCartas($tablero)]);
}

function api_estado_jugador(): void {
    $j = cargarJugador((string)param('token', ''));
    $p = $j['partida'];
    $marcas = marcasDe((int)$j['id']);
    $ganador = $p['ganador_id'] ? (getDB()->query("SELECT nombre FROM jugadores WHERE id = " . (int)$p['ganador_id'])->fetchColumn() ?: null) : null;
    $st = getDB()->prepare("SELECT valido, atendido FROM gritos WHERE jugador_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$j['id']]);
    $g = $st->fetch();
    jsonOk([
        'estado' => $p['estado'], 'nombre' => $j['nombre'], 'marcas' => $marcas,
        'puede_gritar' => count($marcas) === TABLERO_TAM && in_array($p['estado'], ['jugando', 'pausada']),
        'ganador' => $ganador,
        'mi_grito' => $g ? ['valido' => (bool)$g['valido'], 'atendido' => (bool)$g['atendido']] : null,
        'tablero' => tableroConCartas($j['tablero_ids']),
    ]);
}

function api_marcar(): void {
    $j = cargarJugador((string)param('token', ''));
    $p = $j['partida'];
    if (!in_array($p['estado'], ['jugando', 'pausada'])) throw new ApiException('La partida no está en juego', 400);
    $cartaId = (int)param('carta_id', 0);
    $salidas = cartasSalidas($p['orden'], (int)$p['indice_actual']);
    if (!puedeMarcar($cartaId, $j['tablero_ids'], $salidas)) throw new ApiException('No se puede marcar', 400);
    getDB()->prepare(dbInsertIgnore("INSERT INTO marcas (jugador_id, carta_id) VALUES (?, ?)"))->execute([$j['id'], $cartaId]);
    jsonOk(['marcas' => marcasDe((int)$j['id'])]);
}

function api_desmarcar(): void {
    $j = cargarJugador((string)param('token', ''));
    $cartaId = (int)param('carta_id', 0);
    getDB()->prepare("DELETE FROM marcas WHERE jugador_id = ? AND carta_id = ?")->execute([$j['id'], $cartaId]);
    jsonOk(['marcas' => marcasDe((int)$j['id'])]);
}

function api_gritar(): void {
    $j = cargarJugador((string)param('token', ''));
    $p = $j['partida'];
    if (!in_array($p['estado'], ['jugando', 'pausada'])) throw new ApiException('La partida no está en juego', 400);
    $marcas = marcasDe((int)$j['id']);
    if (count($marcas) < TABLERO_TAM) throw new ApiException('Aún no llenas tu tablero', 400);
    $salidas = cartasSalidas($p['orden'], (int)$p['indice_actual']);
    $valido = esLoteriaValida($j['tablero_ids'], $salidas);
    getDB()->prepare("INSERT INTO gritos (partida_id, jugador_id, valido) VALUES (?, ?, ?)")->execute([$p['id'], $j['id'], (int)$valido]);
    actualizarPartida((int)$p['id'], ['estado' => 'pausada']);
    jsonOk(['valido' => $valido]);
}
```

- [ ] **Step 6: Implementar `api.php`**

```php
<?php
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/api_partidas.php';
require_once __DIR__ . '/lib/api_jugadores.php';
if (file_exists(__DIR__ . '/lib/api_mazos.php')) require_once __DIR__ . '/lib/api_mazos.php';

$a = preg_replace('/[^a-z_]/', '', (string)($_GET['a'] ?? ''));
$fn = "api_$a";
try {
    if ($a === '' || !function_exists($fn)) throw new ApiException('Acción desconocida', 404);
    $fn();
} catch (ApiException $e) {
    jsonError($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    jsonError('Error interno: ' . $e->getMessage(), 500);
}
```

- [ ] **Step 7: Correr pruebas** — `php tests/run.php` → `OK (18 pruebas)`

- [ ] **Step 8: Commit**

```bash
git add api.php lib/ tests/test_api.php
git commit -m "feat: API de partidas y jugadores con pruebas de flujo completo"
```

---

### Task 5: API de mazos personalizados

**Files:**
- Create: `lib/api_mazos.php`, `tests/test_mazos_api.php`

**Interfaces:**
- `api_mazos_listar` → `mazos: [{id, nombre, es_default, cartas}]`
- `api_mazo_crear` (nombre) → `id`; `api_mazo_renombrar` (id, nombre); `api_mazo_borrar` (id) — rechaza default y mazos con partidas.
- `api_mazo_cartas` (mazo_id) → `cartas: [{id, numero, nombre, imagen}]`
- `api_carta_guardar` (mazo_id, id opcional, numero, nombre, `$_FILES['imagen']` opcional) → `id`; `api_carta_borrar` (id).
- `guardarImagenSubida(array $file): string` — valida tipo (png/jpg/webp/svg por `finfo`), tamaño ≤ 2 MB, guarda en `uploads/<hex>.<ext>` y devuelve ruta relativa. En pruebas se omite (sin `$_FILES`).

- [ ] **Step 1: Pruebas que fallan** — `tests/test_mazos_api.php`:

```php
<?php
if (!defined('LOTERIA_TEST_DSN')) define('LOTERIA_TEST_DSN', 'sqlite::memory:');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/api_partidas.php';
require_once __DIR__ . '/../lib/api_mazos.php';

test('CRUD de mazos y cartas', function () {
    $r = llamar('mazo_crear', ['nombre' => 'Biología']);
    assertTrue($r['ok'], json_encode($r));
    $id = $r['id'];
    assertTrue(!llamar('mazo_crear', ['nombre' => ''])['ok']);

    $c = llamar('carta_guardar', ['mazo_id' => $id, 'numero' => 1, 'nombre' => 'Mitocondria']);
    assertTrue($c['ok'], json_encode($c));
    $c2 = llamar('carta_guardar', ['mazo_id' => $id, 'id' => $c['id'], 'numero' => 1, 'nombre' => 'La Mitocondria']);
    assertEq($c['id'], $c2['id']);
    assertTrue(!llamar('carta_guardar', ['mazo_id' => $id, 'numero' => 1, 'nombre' => 'Dup'])['ok'], 'número duplicado');

    $l = llamar('mazo_cartas', ['mazo_id' => $id]);
    assertEq(1, count($l['cartas']));
    assertEq('La Mitocondria', $l['cartas'][0]['nombre']);
    assertEq(null, $l['cartas'][0]['imagen']);

    $ls = llamar('mazos_listar');
    $mio = array_values(array_filter($ls['mazos'], fn($m) => $m['id'] == $id))[0];
    assertEq(1, $mio['cartas']);
    assertEq('Biología', $mio['nombre']);

    assertTrue(llamar('mazo_renombrar', ['id' => $id, 'nombre' => 'Bio'])['ok']);
    assertTrue(llamar('carta_borrar', ['id' => $c['id']])['ok']);
    assertEq(0, count(llamar('mazo_cartas', ['mazo_id' => $id])['cartas']));
    assertTrue(llamar('mazo_borrar', ['id' => $id])['ok']);
    assertEq(0, count(array_filter(llamar('mazos_listar')['mazos'], fn($m) => $m['id'] == $id)));
});

test('no se borra el mazo default ni uno con partidas', function () {
    $pdo = getDB();
    $def = (int)$pdo->query("SELECT id FROM mazos WHERE es_default=1")->fetchColumn();
    assertTrue(!llamar('mazo_borrar', ['id' => $def])['ok']);
    assertTrue(!llamar('carta_guardar', ['mazo_id' => $def, 'numero' => 99, 'nombre' => 'X'])['ok'], 'default no editable');
    $id = llamar('mazo_crear', ['nombre' => 'Con partida'])['id'];
    $pdo->exec("INSERT INTO partidas (codigo, mazo_id) VALUES ('TESTAA', $id)");
    assertTrue(!llamar('mazo_borrar', ['id' => $id])['ok']);
});
```

- [ ] **Step 2: Correr y ver que falla.**

- [ ] **Step 3: Implementar `lib/api_mazos.php`**

```php
<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/http.php';

function cargarMazo(int $id): array {
    $st = getDB()->prepare("SELECT * FROM mazos WHERE id = ?");
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) throw new ApiException('Mazo no encontrado', 404);
    return $m;
}

function exigirMazoEditable(int $id): array {
    $m = cargarMazo($id);
    if ((int)$m['es_default'] === 1) throw new ApiException('El mazo clásico no se puede modificar', 400);
    return $m;
}

function guardarImagenSubida(array $f): string {
    if ($f['error'] !== UPLOAD_ERR_OK) throw new ApiException('Error al subir la imagen', 400);
    if ($f['size'] > 2 * 1024 * 1024) throw new ApiException('La imagen debe pesar menos de 2 MB', 400);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'][$mime] ?? null;
    if (!$ext) throw new ApiException('Solo se permiten PNG, JPG, WEBP o SVG', 400);
    $nombre = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = __DIR__ . '/../uploads/' . $nombre;
    if (!move_uploaded_file($f['tmp_name'], $dest)) throw new ApiException('No se pudo guardar la imagen', 500);
    return 'uploads/' . $nombre;
}

function api_mazos_listar(): void {
    exigirClavePresentador();
    $rows = getDB()->query("SELECT m.id, m.nombre, m.es_default, (SELECT COUNT(*) FROM cartas c WHERE c.mazo_id = m.id) AS cartas
                            FROM mazos m ORDER BY m.es_default DESC, m.nombre")->fetchAll();
    jsonOk(['mazos' => array_map(fn($m) => ['id' => (int)$m['id'], 'nombre' => $m['nombre'], 'es_default' => (bool)$m['es_default'], 'cartas' => (int)$m['cartas']], $rows)]);
}

function api_mazo_crear(): void {
    exigirClavePresentador();
    $nombre = trim((string)param('nombre', ''));
    if ($nombre === '' || mb_strlen($nombre) > 80) throw new ApiException('Nombre de 1 a 80 caracteres', 400);
    getDB()->prepare("INSERT INTO mazos (nombre) VALUES (?)")->execute([$nombre]);
    jsonOk(['id' => (int)getDB()->lastInsertId()]);
}

function api_mazo_renombrar(): void {
    exigirClavePresentador();
    $m = exigirMazoEditable((int)param('id', 0));
    $nombre = trim((string)param('nombre', ''));
    if ($nombre === '' || mb_strlen($nombre) > 80) throw new ApiException('Nombre de 1 a 80 caracteres', 400);
    getDB()->prepare("UPDATE mazos SET nombre = ? WHERE id = ?")->execute([$nombre, $m['id']]);
    jsonOk();
}

function api_mazo_borrar(): void {
    exigirClavePresentador();
    $m = exigirMazoEditable((int)param('id', 0));
    $st = getDB()->prepare("SELECT COUNT(*) FROM partidas WHERE mazo_id = ?");
    $st->execute([$m['id']]);
    if ((int)$st->fetchColumn() > 0) throw new ApiException('Este mazo tiene partidas; no se puede borrar', 400);
    $st = getDB()->prepare("SELECT imagen FROM cartas WHERE mazo_id = ? AND imagen LIKE 'uploads/%'");
    $st->execute([$m['id']]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $img) @unlink(__DIR__ . '/../' . $img);
    getDB()->prepare("DELETE FROM cartas WHERE mazo_id = ?")->execute([$m['id']]);
    getDB()->prepare("DELETE FROM mazos WHERE id = ?")->execute([$m['id']]);
    jsonOk();
}

function api_mazo_cartas(): void {
    exigirClavePresentador();
    $m = cargarMazo((int)param('mazo_id', 0));
    $st = getDB()->prepare("SELECT id, numero, nombre, imagen FROM cartas WHERE mazo_id = ? ORDER BY numero");
    $st->execute([$m['id']]);
    jsonOk(['mazo' => ['id' => (int)$m['id'], 'nombre' => $m['nombre'], 'es_default' => (bool)$m['es_default']],
            'cartas' => array_map(fn($c) => ['id' => (int)$c['id'], 'numero' => (int)$c['numero'], 'nombre' => $c['nombre'], 'imagen' => $c['imagen']], $st->fetchAll())]);
}

function api_carta_guardar(): void {
    exigirClavePresentador();
    $pdo = getDB();
    $m = exigirMazoEditable((int)param('mazo_id', 0));
    $id = (int)param('id', 0);
    $numero = (int)param('numero', 0);
    $nombre = trim((string)param('nombre', ''));
    if ($numero < 1 || $numero > 999) throw new ApiException('Número entre 1 y 999', 400);
    if ($nombre === '' || mb_strlen($nombre) > 80) throw new ApiException('Nombre de 1 a 80 caracteres', 400);
    $st = $pdo->prepare("SELECT id FROM cartas WHERE mazo_id = ? AND numero = ? AND id <> ?");
    $st->execute([$m['id'], $numero, $id]);
    if ($st->fetch()) throw new ApiException("Ya existe una carta con el número $numero", 400);
    $imagen = null;
    if (!empty($_FILES['imagen']['name'])) $imagen = guardarImagenSubida($_FILES['imagen']);
    if ($id) {
        $st = $pdo->prepare("SELECT imagen FROM cartas WHERE id = ? AND mazo_id = ?");
        $st->execute([$id, $m['id']]);
        $prev = $st->fetch();
        if (!$prev) throw new ApiException('Carta no encontrada', 404);
        if ($imagen && $prev['imagen'] && str_starts_with($prev['imagen'], 'uploads/')) @unlink(__DIR__ . '/../' . $prev['imagen']);
        $pdo->prepare("UPDATE cartas SET numero = ?, nombre = ?, imagen = COALESCE(?, imagen) WHERE id = ?")->execute([$numero, $nombre, $imagen, $id]);
    } else {
        $pdo->prepare("INSERT INTO cartas (mazo_id, numero, nombre, imagen) VALUES (?, ?, ?, ?)")->execute([$m['id'], $numero, $nombre, $imagen]);
        $id = (int)$pdo->lastInsertId();
    }
    jsonOk(['id' => $id]);
}

function api_carta_borrar(): void {
    exigirClavePresentador();
    $pdo = getDB();
    $id = (int)param('id', 0);
    $st = $pdo->prepare("SELECT c.imagen, m.es_default FROM cartas c JOIN mazos m ON m.id = c.mazo_id WHERE c.id = ?");
    $st->execute([$id]);
    $c = $st->fetch();
    if (!$c) throw new ApiException('Carta no encontrada', 404);
    if ((int)$c['es_default'] === 1) throw new ApiException('El mazo clásico no se puede modificar', 400);
    if ($c['imagen'] && str_starts_with($c['imagen'], 'uploads/')) @unlink(__DIR__ . '/../' . $c['imagen']);
    $pdo->prepare("DELETE FROM cartas WHERE id = ?")->execute([$id]);
    jsonOk();
}
```

- [ ] **Step 4: Correr pruebas** → `OK (20 pruebas)`

- [ ] **Step 5: Commit**

```bash
git add lib/api_mazos.php tests/test_mazos_api.php
git commit -m "feat: API de mazos personalizados con subida de imágenes"
```

---

### Task 6: Estilos comunes y portada

**Files:**
- Create: `assets/app.css`, `lib/vista.php`, `index.php`

**Interfaces:**
- `lib/vista.php`: `exigirClaveVista(): void` (si hay clave y no coincide: 403 con formulario para `?k=`; si coincide vía `?k=`, pone cookie `lot_k`), `cabecera(string $titulo, string $clase = ''): void`, `pie(): void`, `h(string): string` (htmlspecialchars). `cabecera` imprime `<!doctype html>` … `<body class="$clase">` con `<meta name="viewport">`, enlaza `assets/app.css` y define `window.LOT = {sondeo: INTERVALO_SONDEO_MS, k: '<clave si cookie>'}`.
- `assets/app.css`: variables `--crema #f6e7c8 --rojo #c8412b --amar #e0a526 --verde #2b6f5c --azul #1f3a5f --tinta #2a2a2a --fondo #14181f`; clases `.btn .btn-primario .btn-peligro .btn-secundario`, `.tarjeta`, `.carta` (relación 300/420, imagen `object-fit: contain`), `.carta-texto` (sin imagen: nombre centrado sobre fondo `hsl(numero*47 % 360, 55%, 45%)` que se pone inline), `.oculto`, `.alerta`, `.tabla`.

- [ ] **Step 1: Escribir `lib/vista.php`**

```php
<?php
require_once __DIR__ . '/http.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function exigirClaveVista(): void {
    if (PRESENTADOR_CLAVE === '') return;
    if (isset($_GET['k']) && hash_equals(PRESENTADOR_CLAVE, (string)$_GET['k'])) {
        setcookie('lot_k', $_GET['k'], ['expires' => time() + 86400 * 30, 'path' => '/', 'samesite' => 'Lax']);
        $_COOKIE['lot_k'] = $_GET['k'];
        return;
    }
    if (claveOk()) return;
    http_response_code(403);
    cabecera('Clave requerida');
    echo '<main class="centro"><form method="get" class="tarjeta"><h1>Clave de presentador</h1>
          <input type="password" name="k" autofocus placeholder="Clave"><button class="btn btn-primario">Entrar</button></form></main>';
    pie(); exit;
}

function cabecera(string $titulo, string $clase = ''): void {
    $k = PRESENTADOR_CLAVE !== '' && claveOk() ? ($_COOKIE['lot_k'] ?? $_GET['k'] ?? '') : '';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>' . h($titulo) . ' · Lotería</title><link rel="stylesheet" href="assets/app.css">
    <script>window.LOT={sondeo:' . (int)INTERVALO_SONDEO_MS . ',k:' . json_encode($k) . '};</script></head><body class="' . h($clase) . '">';
}

function pie(): void { echo '</body></html>'; }
```

- [ ] **Step 2: Escribir `assets/app.css`** (≈150 líneas): reset básico, variables, tipografía Georgia para títulos / system-ui para texto, `.btn` grandes (min-height 48px, táctiles), `.carta` con `aspect-ratio: 300/420; border-radius: 10px; overflow: hidden; background: var(--crema)`, `.carta img {width:100%;height:100%;object-fit:contain}`, `.carta-texto {display:grid;place-items:center;color:#fff;font-weight:700;text-align:center;padding:6px}`, `.centro {min-height:100vh;display:grid;place-items:center;padding:16px}`, `.tarjeta {background:#fff;border-radius:14px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.15);max-width:520px;width:100%}`, `.tabla` con filas alternas, `.oculto{display:none!important}`, `@keyframes sacudir` (translateX ±6px, 0.3 s), `.sacudir{animation:sacudir .3s}`.

- [ ] **Step 3: Escribir `index.php`**

```php
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
    <h1>🎉 Lotería</h1>
    <form id="nueva">
      <label>Mazo
        <select name="mazo_id"><?php foreach ($mazos as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= $m['n'] < 16 ? 'disabled' : '' ?>><?= h($m['nombre']) ?> (<?= (int)$m['n'] ?> cartas)</option>
        <?php endforeach ?></select>
      </label>
      <button class="btn btn-primario">Nueva partida</button>
      <p class="error oculto" id="err"></p>
    </form>
    <p><a href="mazos.php">Administrar mazos</a></p>
    <h2>Partidas recientes</h2>
    <table class="tabla"><thead><tr><th>Código</th><th>Mazo</th><th>Estado</th><th>Jug.</th><th></th></tr></thead><tbody>
    <?php foreach ($partidas as $p): ?>
      <tr><td><b><?= h($p['codigo']) ?></b></td><td><?= h($p['mazo']) ?></td><td><?= h($p['estado']) ?></td><td><?= (int)$p['jugadores'] ?></td>
          <td><a class="btn btn-secundario" href="presentador.php?c=<?= h($p['codigo']) ?>">Abrir</a></td></tr>
    <?php endforeach ?>
    <?php if (!$partidas): ?><tr><td colspan="5">Aún no hay partidas</td></tr><?php endif ?>
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
```

- [ ] **Step 4: Verificar en navegador** — `php -S 127.0.0.1:8080` con `config.local.php` que defina SQLite local (`define('LOTERIA_TEST_DSN','sqlite:'.__DIR__.'/local.sqlite')` no: usar `DB_DSN` vía env `DB_DSN=sqlite:/ruta/local.sqlite php -S ...`). Abrir `/index.php`, crear partida, debe redirigir a `presentador.php?c=…` (404 por ahora es aceptable).

- [ ] **Step 5: Commit**

```bash
git add assets/app.css lib/vista.php index.php
git commit -m "feat: portada con creación de partidas y estilos comunes"
```

---

### Task 7: Pantalla del presentador

**Files:**
- Create: `presentador.php`, `assets/presentador.js`, `assets/qrcode.min.js`

**Interfaces:**
- Consume `estado_presentador`, `iniciar`, `siguiente`, `pausar`, `continuar`, `terminar`, `config_auto`.
- `assets/qrcode.min.js`: copia de qrcodejs (davidshimjs, MIT). Descargar con `curl -L https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js -o assets/qrcode.min.js`; verificar que empieza con el encabezado de la librería. API usada: `new QRCode(el, {text, width, height, correctLevel: QRCode.CorrectLevel.M})`.

- [ ] **Step 1: `presentador.php`**

```php
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/vista.php';
exigirClaveVista();
$codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['c'] ?? ''));
$st = getDB()->prepare("SELECT p.*, m.nombre AS mazo FROM partidas p JOIN mazos m ON m.id=p.mazo_id WHERE codigo = ?");
$st->execute([$codigo]);
$p = $st->fetch();
if (!$p) { http_response_code(404); cabecera('No encontrada'); echo '<main class="centro"><div class="tarjeta"><h1>Partida no encontrada</h1><a class="btn" href="index.php">Inicio</a></div></main>'; pie(); exit; }
cabecera('Presentador ' . $codigo, 'presentador');
?>
<div id="lobby" class="pantalla">
  <h1>Escanea para recibir tu tablero</h1>
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
      <h3>Gritos</h3><ul id="listaGritos"></ul>
    </aside>
  </div>
  <div class="tira" id="tira"></div>
  <div class="controles">
    <span id="contador" class="contador"></span>
    <button id="btnSiguiente" class="btn btn-primario">Siguiente ▶</button>
    <button id="btnPausa" class="btn btn-secundario">Pausa</button>
    <label class="auto"><input type="checkbox" id="chkAuto"> Auto cada <input type="number" id="numSeg" min="2" max="60" value="6"> s</label>
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
<script src="assets/presentador.js"></script>
<?php pie();
```

- [ ] **Step 2: `assets/presentador.js`**

```js
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

  const cartaHTML = c => c.imagen
    ? `<img src="${c.imagen}" alt="${c.nombre}">`
    : `<div class="carta-texto" style="background:hsl(${(c.numero * 47) % 360},55%,45%)">${c.numero}<br>${c.nombre}</div>`;

  function mostrar(id) { for (const p of ['lobby', 'juego', 'fin']) $(p).classList.toggle('oculto', p !== id); }

  function render(e) {
    estado = e;
    if (e.estado === 'lobby') {
      mostrar('lobby');
      if (!qrHecho) { new QRCode($('qr'), {text: e.url_jugar, width: 360, height: 360, correctLevel: QRCode.CorrectLevel.M}); $('url').textContent = e.url_jugar; qrHecho = true; }
      $('nJug').textContent = e.jugadores.length;
      $('listaLobby').innerHTML = e.jugadores.map(j => `<li>${j.nombre}</li>`).join('');
      $('btnIniciar').disabled = e.jugadores.length === 0;
      return;
    }
    if (e.estado === 'terminada') {
      mostrar('fin'); pararAuto();
      $('finGanador').textContent = e.ganador ? `🏆 Ganó ${e.ganador}` : 'Sin ganador';
      $('finResumen').textContent = `Se cantaron ${e.indice + 1} de ${e.total} cartas · ${e.jugadores.length} jugadores`;
      return;
    }
    mostrar('juego');
    if (e.carta_actual) { $('cartaActual').innerHTML = cartaHTML(e.carta_actual); $('nombreActual').textContent = `${e.carta_actual.numero} · ${e.carta_actual.nombre}`; }
    $('tira').innerHTML = e.ultimas.map(c => `<div class="carta chica">${cartaHTML(c)}</div>`).join('');
    $('contador').textContent = `Carta ${e.indice + 1} de ${e.total}`;
    $('btnPausa').textContent = e.estado === 'pausada' ? 'Continuar' : 'Pausa';
    $('listaJuego').innerHTML = e.jugadores.map(j => `<li>${j.nombre} <span class="pill">${j.marcas}/16</span></li>`).join('');
    $('listaGritos').innerHTML = e.gritos.map(g => `<li>${g.nombre}: <b class="${g.valido ? 'ok' : 'mal'}">${g.valido ? 'válido' : 'falso'}</b></li>`).join('') || '<li class="tenue">Ninguno</li>';
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

  function arrancarAuto(seg) { pararAuto(); timerAuto = setInterval(() => siguiente(), seg * 1000); }
  function pararAuto() { if (timerAuto) clearInterval(timerAuto); timerAuto = null; }

  async function siguiente() {
    if (ocupado || !estado || estado.estado !== 'jugando') return;
    ocupado = true; try { await api('siguiente'); await ciclo(); } finally { ocupado = false; }
  }

  async function ciclo() { try { const e = await sondear(); if (e.ok) render(e); } catch (_) {} }

  $('btnIniciar').onclick = async () => { await api('iniciar'); ciclo(); };
  $('btnSiguiente').onclick = siguiente;
  $('btnPausa').onclick = async () => { await api(estado.estado === 'pausada' ? 'continuar' : 'pausar'); ciclo(); };
  $('btnTerminar').onclick = async () => { if (confirm('¿Terminar la partida sin ganador?')) { await api('terminar'); ciclo(); } };
  $('btnPanel').onclick = () => $('panel').classList.toggle('abierto');
  const guardarAuto = async () => { const r = await api('config_auto', {auto: $('chkAuto').checked ? 1 : 0, intervalo_seg: $('numSeg').value}); if (!r.ok) alert(r.error); ciclo(); };
  $('chkAuto').onchange = guardarAuto; $('numSeg').onchange = guardarAuto;
  $('alGanador').onclick = async () => { await api('terminar', {ganador_id: $('alGanador').dataset.jugador}); $('alerta').classList.add('oculto'); ciclo(); };
  $('alContinuar').onclick = async () => { await api('continuar'); $('alerta').classList.add('oculto'); ciclo(); };
  document.addEventListener('keydown', ev => {
    if (ev.target.tagName === 'INPUT') return;
    if (ev.code === 'Space' || ev.code === 'ArrowRight') { ev.preventDefault(); siguiente(); }
    if (ev.key.toLowerCase() === 'p') $('btnPausa').click();
  });

  ciclo(); setInterval(ciclo, LOT.sondeo);
})();
```

- [ ] **Step 3: CSS del presentador** en `assets/app.css` (bloque `body.presentador`): fondo `var(--fondo)`, texto crema; `.pantalla{min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:16px}`; `#qr{background:#fff;padding:16px;border-radius:16px}`; `.escena{flex:1;display:flex;width:100%;justify-content:center;position:relative}`; `.carta-grande .carta{height:min(70vh,calc(100vw - 32px)*1.4)}`; `.nombre-grande{font-size:clamp(28px,5vw,64px);font-family:Georgia,serif;text-align:center}`; `.tira{display:flex;gap:8px;justify-content:center}.carta.chica{height:110px}`; `.controles{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;align-items:center;padding:12px}`; `.panel{position:absolute;right:0;top:0;bottom:0;width:280px;background:#1e2430;padding:16px;transform:translateX(100%);transition:.2s}.panel.abierto{transform:none}`; `.alerta{position:fixed;inset:0;background:rgba(0,0,0,.85);display:grid;place-items:center;z-index:10}.alerta-caja{background:var(--crema);color:var(--tinta);padding:40px;border-radius:20px;text-align:center;max-width:800px}.alerta-titulo{font-size:clamp(32px,6vw,72px)}.veredicto{font-size:clamp(24px,4vw,44px);margin:20px 0}.veredicto.ok{color:var(--verde)}.veredicto.mal{color:var(--rojo)}`.

- [ ] **Step 4: Verificar en navegador**: abrir presentador de una partida; ver QR y URL; en otra pestaña abrir la URL (jugar.php aún no existe → 404, aceptable); tras Task 8 repetir completo.

- [ ] **Step 5: Commit**

```bash
git add presentador.php assets/presentador.js assets/qrcode.min.js assets/app.css
git commit -m "feat: pantalla de presentador con QR, carta grande, controles y alerta de lotería"
```

---

### Task 8: Pantalla del jugador (teléfono)

**Files:**
- Create: `jugar.php`, `assets/jugar.js`

**Interfaces:**
- Consume `unirse`, `estado_jugador`, `marcar`, `desmarcar`, `gritar`.
- `localStorage['lot_token_<CODIGO>']` guarda el token.

- [ ] **Step 1: `jugar.php`**

```php
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/vista.php';
$codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['c'] ?? ''));
$st = getDB()->prepare("SELECT estado FROM partidas WHERE codigo = ?");
$st->execute([$codigo]);
$p = $st->fetch();
cabecera('Jugar', 'jugador');
if (!$p) { echo '<main class="centro"><div class="tarjeta"><h1>Partida no encontrada</h1><p>Revisa el código o vuelve a escanear el QR.</p></div></main>'; pie(); exit; }
?>
<div id="entrar" class="centro">
  <form class="tarjeta" id="formNombre">
    <h1>🎉 Lotería</h1>
    <p>Partida <b><?= h($codigo) ?></b></p>
    <label>Tu nombre <input name="nombre" maxlength="30" required autocomplete="off" autofocus placeholder="Escribe tu nombre"></label>
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
<script src="assets/jugar.js"></script>
<?php pie();
```

- [ ] **Step 2: `assets/jugar.js`**

```js
(() => {
  const $ = id => document.getElementById(id);
  const cod = window.CODIGO, clave = 'lot_token_' + cod;
  let token = null, tablero = [], marcas = new Set(), estado = null, gritando = false;
  try { token = localStorage.getItem(clave); } catch (_) {}

  const api = async (a, datos = {}) => {
    const fd = new FormData(); for (const [k, v] of Object.entries(datos)) fd.append(k, v);
    return fetch('api.php?a=' + a, {method: 'POST', body: fd}).then(r => r.json());
  };
  const cartaHTML = c => c.imagen
    ? `<img src="${c.imagen}" alt="${c.nombre}" draggable="false">`
    : `<div class="carta-texto" style="background:hsl(${(c.numero * 47) % 360},55%,45%)">${c.numero}<br>${c.nombre}</div>`;

  function pintarTablero() {
    $('cuadricula').innerHTML = tablero.map(c => `<div class="carta celda" data-id="${c.id}">${cartaHTML(c)}<div class="frijol"></div></div>`).join('');
    for (const el of $('cuadricula').children) el.addEventListener('click', () => tocar(el));
    pintarMarcas();
  }
  function pintarMarcas() {
    for (const el of $('cuadricula').children) el.classList.toggle('marcada', marcas.has(+el.dataset.id));
    $('cuenta').textContent = `${marcas.size}/16`;
  }
  function aviso(txt, clase = '') { const a = $('aviso'); a.textContent = txt; a.className = 'aviso ' + clase; a.classList.toggle('oculto', !txt); }

  async function tocar(el) {
    if (!estado || !['jugando', 'pausada'].includes(estado.estado)) return;
    const id = +el.dataset.id;
    if (marcas.has(id)) { const r = await api('desmarcar', {token, carta_id: id}); if (r.ok) { marcas = new Set(r.marcas); pintarMarcas(); } return; }
    el.classList.add('marcada'); // optimista
    const r = await api('marcar', {token, carta_id: id});
    if (r.ok) { marcas = new Set(r.marcas); pintarMarcas(); return; }
    el.classList.remove('marcada');
    if (navigator.vibrate) navigator.vibrate(200);
    el.classList.remove('sacudir'); void el.offsetWidth; el.classList.add('sacudir');
  }

  function render(e) {
    estado = e;
    marcas = new Set(e.marcas); pintarMarcas();
    const txt = {lobby: 'Esperando que inicie…', jugando: 'En juego', pausada: 'Pausa', terminada: 'Terminada'}[e.estado];
    $('estadoTxt').textContent = txt;
    $('btnGritar').disabled = !e.puede_gritar || gritando;
    if (e.estado === 'terminada') aviso(e.ganador ? `🏆 Ganó ${e.ganador}` : 'La partida terminó', 'fin');
    else if (e.mi_grito && !e.mi_grito.atendido) aviso(e.mi_grito.valido ? '✔ ¡Tu lotería es válida! Muestra tu teléfono' : '✘ Tu tablero no es válido todavía', e.mi_grito.valido ? 'ok' : 'mal');
    else if (e.estado === 'pausada') aviso('Juego en pausa');
    else aviso('');
  }

  async function ciclo() {
    if (!token) return;
    try {
      const e = await fetch(`api.php?a=estado_jugador&token=${encodeURIComponent(token)}`).then(r => r.json());
      if (!e.ok) { if (e.error === 'Jugador no encontrado') { try { localStorage.removeItem(clave); } catch (_) {} location.reload(); } return; }
      if (!tablero.length) { tablero = e.tablero; $('miNombre').textContent = e.nombre; pintarTablero(); $('entrar').classList.add('oculto'); $('tablero').classList.remove('oculto'); }
      render(e);
    } catch (_) {}
  }

  $('formNombre').addEventListener('submit', async ev => {
    ev.preventDefault();
    const r = await api('unirse', {codigo: cod, nombre: new FormData(ev.target).get('nombre')});
    if (!r.ok) { $('err').textContent = r.error; $('err').classList.remove('oculto'); return; }
    token = r.token; try { localStorage.setItem(clave, token); } catch (_) {}
    tablero = r.tablero; $('miNombre').textContent = r.nombre; pintarTablero();
    $('entrar').classList.add('oculto'); $('tablero').classList.remove('oculto');
    ciclo();
  });

  $('btnGritar').onclick = async () => {
    if (gritando) return; gritando = true; $('btnGritar').disabled = true;
    const r = await api('gritar', {token});
    if (!r.ok) aviso(r.error, 'mal');
    setTimeout(() => { gritando = false; }, 5000);
    ciclo();
  };

  ciclo(); setInterval(ciclo, LOT.sondeo);
})();
```

- [ ] **Step 3: CSS del jugador** (bloque `body.jugador`): `#tablero{height:100dvh;display:flex;flex-direction:column;padding:8px;gap:8px;background:var(--fondo);color:var(--crema)}`; `.barra{display:flex;justify-content:space-between;align-items:center;font-weight:700}`; `.cuadricula{flex:1;display:grid;grid-template-columns:repeat(4,1fr);grid-template-rows:repeat(4,1fr);gap:6px;min-height:0}`; `.celda{position:relative;aspect-ratio:auto;height:100%;user-select:none;-webkit-tap-highlight-color:transparent}`; `.frijol{position:absolute;inset:0;display:none;place-items:center}.frijol::after{content:'';width:55%;aspect-ratio:1;border-radius:50%;background:radial-gradient(circle at 35% 35%,#8b5a2b,#3b2314);box-shadow:0 3px 8px rgba(0,0,0,.5);opacity:.92}.celda.marcada .frijol{display:grid}`; `.gritar{font-size:28px;min-height:64px;letter-spacing:2px}.gritar:disabled{opacity:.35}`; `.aviso{position:fixed;left:8px;right:8px;bottom:84px;padding:12px;border-radius:10px;background:var(--azul);text-align:center;font-weight:700}.aviso.ok{background:var(--verde)}.aviso.mal{background:var(--rojo)}.aviso.fin{background:var(--amar);color:var(--tinta)}`.

- [ ] **Step 4: Verificar en navegador de punta a punta**: presentador en una pestaña, jugador en otra (viewport móvil). Unirse, iniciar, Siguiente, marcar carta salida (aparece frijol), tocar carta no salida (sacude, sin frijol), completar 16, gritar, alerta en presentador, terminar con ganador, jugador ve "Ganó X". Recargar el teléfono: conserva el tablero.

- [ ] **Step 5: Commit**

```bash
git add jugar.php assets/jugar.js assets/app.css
git commit -m "feat: tablero de jugador en teléfono con marcas validadas y grito de lotería"
```

---

### Task 9: Panel de mazos

**Files:**
- Create: `mazos.php`, `assets/mazos.js`

- [ ] **Step 1: `mazos.php`**

```php
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/vista.php';
exigirClaveVista();
cabecera('Mazos', 'mazos');
?>
<main class="contenedor">
  <header class="barra"><h1>Mazos</h1><a class="btn btn-secundario" href="index.php">← Inicio</a></header>
  <section class="dos-columnas">
    <div class="tarjeta">
      <form id="formMazo" class="fila"><input name="nombre" placeholder="Nuevo mazo" maxlength="80" required><button class="btn btn-primario">Crear</button></form>
      <ul id="listaMazos" class="lista"></ul>
    </div>
    <div class="tarjeta" id="detalle">
      <p class="tenue">Elige un mazo</p>
    </div>
  </section>
</main>
<template id="tplDetalle">
  <header class="barra"><h2 id="detNombre"></h2><span id="detAcciones"></span></header>
  <form id="formCarta" class="form-carta" enctype="multipart/form-data">
    <input type="hidden" name="id"><input type="hidden" name="mazo_id">
    <input name="numero" type="number" min="1" max="999" placeholder="N°" required style="width:80px">
    <input name="nombre" placeholder="Nombre de la carta" maxlength="80" required>
    <input name="imagen" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml">
    <button class="btn btn-primario">Guardar</button><button type="button" class="btn btn-secundario oculto" id="btnCancelar">Cancelar</button>
    <p class="error oculto" id="errCarta"></p>
  </form>
  <div class="rejilla-cartas" id="cartas"></div>
</template>
<script src="assets/mazos.js"></script>
<?php pie();
```

- [ ] **Step 2: `assets/mazos.js`**

```js
(() => {
  const $ = id => document.getElementById(id);
  const k = LOT.k ? '&k=' + encodeURIComponent(LOT.k) : '';
  const get = a => fetch('api.php?a=' + a + k).then(r => r.json());
  const post = (a, fd) => { if (LOT.k) fd.append('k', LOT.k); return fetch('api.php?a=' + a, {method: 'POST', body: fd}).then(r => r.json()); };
  const fdDe = obj => { const fd = new FormData(); for (const [a, b] of Object.entries(obj)) fd.append(a, b); return fd; };
  const cartaHTML = c => c.imagen ? `<img src="${c.imagen}" alt="">` : `<div class="carta-texto" style="background:hsl(${(c.numero * 47) % 360},55%,45%)">${c.numero}<br>${c.nombre}</div>`;
  let actual = null;

  async function listar() {
    const r = await get('mazos_listar');
    $('listaMazos').innerHTML = r.mazos.map(m => `<li><a href="#" data-id="${m.id}" class="${actual === m.id ? 'activo' : ''}">${m.nombre}</a> <span class="pill">${m.cartas} cartas</span>${m.es_default ? ' 🔒' : ''}</li>`).join('');
    for (const a of $('listaMazos').querySelectorAll('a')) a.onclick = e => { e.preventDefault(); abrir(+a.dataset.id); };
  }

  async function abrir(id) {
    actual = id;
    const r = await get('mazo_cartas&mazo_id=' + id);
    if (!r.ok) return alert(r.error);
    $('detalle').innerHTML = ''; $('detalle').append($('tplDetalle').content.cloneNode(true));
    $('detNombre').textContent = r.mazo.nombre;
    const editable = !r.mazo.es_default;
    $('formCarta').classList.toggle('oculto', !editable);
    $('formCarta').mazo_id.value = id;
    if (editable) $('detAcciones').innerHTML = `<button class="btn btn-secundario" id="btnRenombrar">Renombrar</button> <button class="btn btn-peligro" id="btnBorrarMazo">Borrar mazo</button>`;
    $('cartas').innerHTML = r.cartas.map(c => `<div class="carta-item"><div class="carta">${cartaHTML(c)}</div><div class="carta-pie"><b>${c.numero}</b> ${c.nombre}
        ${editable ? `<button class="mini" data-editar="${c.id}">✎</button><button class="mini" data-borrar="${c.id}">🗑</button>` : ''}</div></div>`).join('') || '<p class="tenue">Sin cartas. Agrega al menos 16 para poder jugar.</p>';
    if (!editable) { listar(); return; }
    $('formCarta').onsubmit = async e => {
      e.preventDefault();
      const rr = await post('carta_guardar', new FormData(e.target));
      if (!rr.ok) { $('errCarta').textContent = rr.error; $('errCarta').classList.remove('oculto'); return; }
      abrir(id);
    };
    $('btnCancelar').onclick = () => { $('formCarta').reset(); $('formCarta').id.value = ''; $('formCarta').mazo_id.value = id; $('btnCancelar').classList.add('oculto'); };
    for (const b of $('cartas').querySelectorAll('[data-editar]')) b.onclick = () => {
      const c = r.cartas.find(x => x.id == b.dataset.editar);
      const f = $('formCarta'); f.id.value = c.id; f.numero.value = c.numero; f.nombre.value = c.nombre; $('btnCancelar').classList.remove('oculto'); f.nombre.focus();
    };
    for (const b of $('cartas').querySelectorAll('[data-borrar]')) b.onclick = async () => { if (confirm('¿Borrar esta carta?')) { await post('carta_borrar', fdDe({id: b.dataset.borrar})); abrir(id); } };
    $('btnRenombrar').onclick = async () => { const n = prompt('Nuevo nombre', r.mazo.nombre); if (n) { const rr = await post('mazo_renombrar', fdDe({id, nombre: n})); if (!rr.ok) alert(rr.error); abrir(id); } };
    $('btnBorrarMazo').onclick = async () => { if (confirm('¿Borrar el mazo y todas sus cartas?')) { const rr = await post('mazo_borrar', fdDe({id})); if (!rr.ok) return alert(rr.error); actual = null; $('detalle').innerHTML = '<p class="tenue">Elige un mazo</p>'; listar(); } };
    listar();
  }

  $('formMazo').onsubmit = async e => { e.preventDefault(); const r = await post('mazo_crear', new FormData(e.target)); if (!r.ok) return alert(r.error); e.target.reset(); await listar(); abrir(r.id); };
  listar();
})();
```

- [ ] **Step 3: CSS** (bloque `body.mazos`): `.contenedor{max-width:1100px;margin:0 auto;padding:16px}`, `.dos-columnas{display:grid;grid-template-columns:300px 1fr;gap:16px}` con `@media (max-width:800px){grid-template-columns:1fr}`, `.rejilla-cartas{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px}`, `.form-carta{display:flex;gap:8px;flex-wrap:wrap;align-items:center}`, `.mini{border:0;background:none;cursor:pointer}`, `.lista a.activo{font-weight:700}`.

- [ ] **Step 4: Verificar en navegador**: crear mazo, agregar carta sin imagen (aparece con color), agregar con imagen PNG (miniatura), editar, borrar, crear 16 cartas y confirmar que aparece habilitado en la portada.

- [ ] **Step 5: Commit**

```bash
git add mazos.php assets/mazos.js assets/app.css
git commit -m "feat: panel de mazos personalizados"
```

---

### Task 10: Despliegue (LAMP y Railway) y README

**Files:**
- Create: `Dockerfile`, `.htaccess`, `schema.sql`, `README.md`, `.dockerignore`

- [ ] **Step 1: `Dockerfile`**

```dockerfile
FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite headers
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/uploads
# Railway inyecta PORT
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf && sed -i 's/:80>/:${PORT}>/' /etc/apache2/sites-available/000-default.conf
ENV PORT=8080
EXPOSE 8080
CMD ["apache2-foreground"]
```

- [ ] **Step 2: `.htaccess`**

```apache
Options -Indexes
<FilesMatch "\.(sqlite|md|sql)$">
  Require all denied
</FilesMatch>
<IfModule mod_headers.c>
  <FilesMatch "api\.php$">
    Header set Cache-Control "no-store"
  </FilesMatch>
</IfModule>
```

También negar acceso directo a `lib/`, `tests/`, `tools/`, `docs/`: crear `lib/.htaccess`, `tests/.htaccess`, `tools/.htaccess`, `docs/.htaccess` con `Require all denied`.

- [ ] **Step 3: `schema.sql`** — versión MySQL de las seis tablas (copiar de `crearTablas` con `INT AUTO_INCREMENT PRIMARY KEY`), como referencia para quien prefiera crearlas a mano.

- [ ] **Step 4: `README.md`** con: qué es, requisitos (PHP ≥ 8.1 con pdo_mysql; opcional pdo_sqlite), instalación LAMP (copiar, editar `config.php` o crear `config.local.php`, permisos `uploads/`), Railway (crear servicio desde repo con Dockerfile, agregar MySQL, variables `DB_DSN=mysql:host=${{MySQL.MYSQLHOST}};port=${{MySQL.MYSQLPORT}};dbname=${{MySQL.MYSQLDATABASE}};charset=utf8mb4`, `DB_USER`, `DB_PASS`, opcional `PRESENTADOR_CLAVE`, `BASE_URL`), cómo jugar (paso a paso), pruebas (`php tests/run.php`), regenerar cartas (`php tools/generar_cartas.php`), correr local (`DB_DSN=sqlite:$PWD/local.sqlite php -S 0.0.0.0:8080`).

- [ ] **Step 5: `.dockerignore`**: `.git`, `docs`, `tests`, `*.sqlite`, `config.local.php`.

- [ ] **Step 6: Verificar**: `php tests/run.php` → OK. `docker build -t loteria .` si Docker está disponible (si no, anotar en el resumen).

- [ ] **Step 7: Commit**

```bash
git add Dockerfile .htaccess lib/.htaccess tests/.htaccess tools/.htaccess docs/.htaccess schema.sql README.md .dockerignore
git commit -m "feat: despliegue LAMP/Railway y README"
```

---

### Task 11: Verificación de punta a punta en navegador

- [ ] **Step 1:** Levantar `DB_DSN=sqlite:$PWD/local.sqlite php -S 127.0.0.1:8080` (vía preview del navegador integrado).
- [ ] **Step 2:** Portada → Nueva partida → presentador muestra QR y código.
- [ ] **Step 3:** Segunda pestaña con viewport móvil en `jugar.php?c=CODIGO` → nombre → tablero 4×4 sin scroll; presentador lista al jugador.
- [ ] **Step 4:** Iniciar → carta grande. Tocar en el teléfono una carta no salida → sacude, sin frijol. Siguiente hasta que salga una del tablero → tocarla → frijol. Comprobar que el teléfono no muestra la carta actual ni resalta.
- [ ] **Step 5:** Activar Auto 2 s → avanza solo; Pausa → se detiene.
- [ ] **Step 6:** Marcar las 16 (avanzando cartas) → botón ¡LOTERÍA! habilitado → gritar → alerta VÁLIDO en presentador → "Terminar: es el ganador" → pantalla final y teléfono con "Ganó X".
- [ ] **Step 7:** Recargar teléfono a mitad de partida → conserva tablero y marcas.
- [ ] **Step 8:** Mazos: crear mazo con 16 cartas (sin imágenes) y jugar una partida con él (cartas de color con texto).
- [ ] **Step 9:** Corregir lo que falle, `php tests/run.php` OK, commit `fix:` si hubo cambios.

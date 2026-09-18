# Arquitectura web — Dashboard Visitadora Médica (stack tipo grade_app + BD real)

## 0. Qué se conserva y qué cambia respecto a grade_app

| grade_app | Este sistema |
|---|---|
| HTML/CSS/JS plano, sin framework | Igual |
| PHP como capa de guardado de archivo | PHP como **API REST simple** (varios endpoints .php) |
| Excel completo cargado en memoria vía SheetJS | **SQLite** como fuente de verdad; Excel queda solo para importar el fichero inicial de médicos |
| `localStorage` para persistencia temporal | Base de datos real; `localStorage` solo para cola offline (ver Fase 6) |
| Un solo archivo de salida (`notas_actualizadas.xlsx`) | Múltiples tablas relacionadas con integridad referencial |

El "look and feel" (tarjetas blancas, tabs, paleta azul, mensajes toast) se conserva igual — reutilizas `styles.css` casi tal cual.

---

## 1. Estructura de carpetas

```
visitador-medico/
├── index.php                 # Shell principal (tabs: Resumen, Médicos, Visitas, Mapa, Reportes)
├── css/
│   └── styles.css            # Copiado de grade_app, + estilos de mapa/chart
├── js/
│   ├── app.js                 # Navegación de tabs, inicialización
│   ├── medicosManager.js      # CRUD de médicos
│   ├── visitasManager.js      # CRUD de visitas
│   ├── mapManager.js          # Leaflet, pines, colores por cobertura
│   ├── chartsManager.js       # Chart.js: cobertura, frecuencia, ranking, prescripciones
│   └── apiClient.js           # Wrapper fetch() hacia api/*.php
├── api/
│   ├── db.php                  # Conexión PDO a SQLite, se incluye en todos los endpoints
│   ├── medicos.php             # GET/POST/PUT/DELETE médicos
│   ├── visitas.php             # GET/POST/PUT/DELETE visitas
│   ├── productos.php           # GET/POST/PUT/DELETE productos
│   ├── prescripciones.php      # GET/POST/PUT/DELETE prescripciones
│   ├── plan_visitas.php        # GET/POST/PUT/DELETE metas
│   └── reportes.php            # Endpoints agregados: cobertura, ranking, frecuencia
├── data/
│   └── visitador.sqlite        # Base de datos (mismo lugar donde grade_app guardaba el xlsx)
└── import_excel.php            # Importador one-time del Excel de médicos existente
```

---

## 2. Esquema de base de datos (SQLite)

```sql
CREATE TABLE medicos (
    id_medico INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    especialidad TEXT NOT NULL,
    centro_salud TEXT,
    lat REAL,
    lng REAL,
    sector TEXT DEFAULT 'San Miguelito',
    activo INTEGER DEFAULT 1
);

CREATE TABLE productos (
    id_producto INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    categoria TEXT NOT NULL,
    material_promocional TEXT
);

CREATE TABLE visitas (
    id_visita INTEGER PRIMARY KEY AUTOINCREMENT,
    id_medico INTEGER NOT NULL REFERENCES medicos(id_medico),
    id_producto INTEGER NOT NULL REFERENCES productos(id_producto),
    material_entregado TEXT,
    duracion_min INTEGER,
    resultado TEXT CHECK(resultado IN ('Efectiva','Cancelada')),
    fecha TEXT NOT NULL   -- formato 'YYYY-MM-DD'
);

CREATE TABLE prescripciones (
    id_prescripcion INTEGER PRIMARY KEY AUTOINCREMENT,
    id_medico INTEGER NOT NULL REFERENCES medicos(id_medico),
    id_producto INTEGER NOT NULL REFERENCES productos(id_producto),
    cantidad_estimada REAL,
    fecha TEXT NOT NULL
);

CREATE TABLE plan_visitas (
    id_plan INTEGER PRIMARY KEY AUTOINCREMENT,
    id_medico INTEGER NOT NULL REFERENCES medicos(id_medico),
    mes TEXT NOT NULL,      -- formato 'YYYY-MM'
    meta_visitas INTEGER NOT NULL
);

-- Índices para que los reportes no se pongan lentos con el tiempo
CREATE INDEX idx_visitas_medico ON visitas(id_medico);
CREATE INDEX idx_visitas_fecha ON visitas(fecha);
CREATE INDEX idx_prescripciones_medico ON prescripciones(id_medico);
CREATE INDEX idx_plan_medico_mes ON plan_visitas(id_medico, mes);
```

Esto es exactamente lo mismo que diseñamos para AppSheet — Médicos/Visitas/Productos/Prescripciones/Plan_Visitas — solo que ahora con Foreign Keys reales que SQLite sí valida.

---

## 3. Capa PHP — patrón para cada endpoint

`api/db.php` (se incluye en todos los demás):
```php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$dbPath = __DIR__ . '/../data/visitador.sqlite';
$dbExists = file_exists($dbPath);

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

if (!$dbExists) {
    // Ejecuta el schema.sql la primera vez
    $pdo->exec(file_get_contents(__DIR__ . '/../schema.sql'));
}
```

`api/medicos.php` (ejemplo del patrón REST que se repite en cada tabla):
```php
<?php
require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM medicos WHERE id_medico = ?');
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } else {
            $stmt = $pdo->query('SELECT * FROM medicos WHERE activo = 1 ORDER BY nombre');
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare(
            'INSERT INTO medicos (nombre, especialidad, centro_salud, lat, lng, sector)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nombre'], $data['especialidad'], $data['centro_salud'] ?? null,
            $data['lat'] ?? null, $data['lng'] ?? null, $data['sector'] ?? 'San Miguelito'
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare(
            'UPDATE medicos SET nombre=?, especialidad=?, centro_salud=?, lat=?, lng=?, sector=?
             WHERE id_medico=?'
        );
        $stmt->execute([
            $data['nombre'], $data['especialidad'], $data['centro_salud'],
            $data['lat'], $data['lng'], $data['sector'], $data['id_medico']
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        // Baja lógica, no borrado físico — conserva el historial de visitas
        $stmt = $pdo->prepare('UPDATE medicos SET activo = 0 WHERE id_medico = ?');
        $stmt->execute([$_GET['id']]);
        echo json_encode(['success' => true]);
        break;
}
```

`visitas.php`, `productos.php`, `prescripciones.php` y `plan_visitas.php` siguen el mismo patrón — cambia la tabla y los campos.

**`api/reportes.php`** es el que reemplaza toda la lógica que en AppSheet resolvían las columnas virtuales:

```php
<?php
require 'db.php';
$reporte = $_GET['tipo'] ?? '';

switch ($reporte) {

    case 'cobertura':
        // Médicos ginecólogos: visitados este mes vs meta
        $sql = "
            SELECT m.id_medico, m.nombre, m.lat, m.lng,
                   COALESCE(v.total_visitas, 0) AS visitas_mes,
                   COALESCE(p.meta, 0) AS meta_mes,
                   CASE
                       WHEN COALESCE(v.total_visitas,0) = 0 THEN 'Sin visitar'
                       WHEN v.total_visitas >= p.meta THEN 'Cumplido'
                       ELSE 'Parcial'
                   END AS estado
            FROM medicos m
            LEFT JOIN (
                SELECT id_medico, COUNT(*) AS total_visitas
                FROM visitas
                WHERE strftime('%Y-%m', fecha) = strftime('%Y-%m', 'now')
                GROUP BY id_medico
            ) v ON v.id_medico = m.id_medico
            LEFT JOIN (
                SELECT id_medico, meta_visitas AS meta
                FROM plan_visitas
                WHERE mes = strftime('%Y-%m', 'now')
            ) p ON p.id_medico = m.id_medico
            WHERE m.especialidad = 'Ginecología' AND m.activo = 1
        ";
        echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'frecuencia':
        // Visitas agrupadas por semana/mes
        $agrupacion = $_GET['periodo'] === 'semana' ? "strftime('%Y-W%W', fecha)" : "strftime('%Y-%m', fecha)";
        $sql = "SELECT $agrupacion AS periodo, COUNT(*) AS total
                FROM visitas GROUP BY periodo ORDER BY periodo";
        echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'ranking_productos':
        $sql = "
            SELECT p.nombre, COUNT(v.id_visita) AS total_visitas
            FROM productos p
            LEFT JOIN visitas v ON v.id_producto = p.id_producto
            WHERE p.categoria = 'Ginecología'
            GROUP BY p.id_producto
            ORDER BY total_visitas DESC
        ";
        echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'prescripciones_evolucion':
        $sql = "
            SELECT strftime('%Y-%m', pr.fecha) AS mes, p.nombre AS producto,
                   SUM(pr.cantidad_estimada) AS total
            FROM prescripciones pr
            JOIN productos p ON p.id_producto = pr.id_producto
            GROUP BY mes, p.id_producto
            ORDER BY mes
        ";
        echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'eficiencia':
        $sql = "
            SELECT
                AVG(duracion_min) AS duracion_promedio,
                SUM(CASE WHEN resultado='Efectiva' THEN 1 ELSE 0 END) AS efectivas,
                SUM(CASE WHEN resultado='Cancelada' THEN 1 ELSE 0 END) AS canceladas,
                COUNT(*) AS total
            FROM visitas
            WHERE strftime('%Y-%m', fecha) = strftime('%Y-%m', 'now')
        ";
        echo json_encode($pdo->query($sql)->fetch(PDO::FETCH_ASSOC));
        break;
}
```

Con esto, **todo el cálculo pesado vive en SQL**, no en JavaScript recorriendo arreglos — mucho más rápido cuando el historial crezca.

---

## 4. Frontend — estructura de vistas (tabs, igual que grade_app)

```html
<div class="tabs">
    <button class="tablink active" onclick="openTab('resumen')">Resumen</button>
    <button class="tablink" onclick="openTab('medicos')">Médicos</button>
    <button class="tablink" onclick="openTab('registrar')">Registrar Visita</button>
    <button class="tablink" onclick="openTab('mapa')">Mapa</button>
    <button class="tablink" onclick="openTab('reportes')">Reportes</button>
</div>
```

### Tab Resumen (KPIs)
Tarjetas `.card` reutilizando el CSS de grade_app, alimentadas por `reportes.php?tipo=eficiencia` y `reportes.php?tipo=cobertura`:
```js
async function cargarResumen() {
    const eficiencia = await ApiClient.get('reportes.php?tipo=eficiencia');
    const cobertura = await ApiClient.get('reportes.php?tipo=cobertura');
    const cumplidos = cobertura.filter(m => m.estado !== 'Sin visitar').length;
    document.getElementById('kpi-cobertura').textContent =
        `${Math.round(cumplidos / cobertura.length * 100)}%`;
    document.getElementById('kpi-visitas-mes').textContent = eficiencia.total;
    document.getElementById('kpi-duracion').textContent =
        `${Math.round(eficiencia.duracion_promedio)} min`;
    document.getElementById('kpi-ratio').textContent =
        `${eficiencia.efectivas}/${eficiencia.canceladas}`;
}
```

### Tab Médicos (CRUD)
Reutiliza el patrón `search-container` + `search-results` que ya tienes en `student-search` de grade_app, pero apuntando a `medicos.php`.

### Tab Registrar Visita
Formulario con dos `<select>` (médico, producto) poblados vía `ApiClient.get('medicos.php')` y `ApiClient.get('productos.php')`, igual de simple que el formulario de nota de grade_app.

### Tab Mapa (Leaflet — nuevo, no existía en grade_app)
```html
<div id="mapa-container" style="height: 500px;"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
```
```js
async function cargarMapa() {
    const map = L.map('mapa-container').setView([9.0333, -79.5000], 13); // San Miguelito
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    const colores = { 'Cumplido': 'green', 'Parcial': 'orange', 'Sin visitar': 'red' };
    const medicos = await ApiClient.get('reportes.php?tipo=cobertura');

    medicos.forEach(m => {
        if (!m.lat || !m.lng) return;
        L.circleMarker([m.lat, m.lng], {
            color: colores[m.estado], radius: 8, fillOpacity: 0.8
        })
        .bindPopup(`<b>${m.nombre}</b><br>${m.estado}<br>${m.visitas_mes}/${m.meta_mes} visitas`)
        .addTo(map);
    });
}
```

### Tab Reportes (Chart.js — nuevo)
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<canvas id="chart-cobertura"></canvas>
<canvas id="chart-frecuencia"></canvas>
<canvas id="chart-ranking"></canvas>
<canvas id="chart-prescripciones"></canvas>
```
```js
async function cargarCharts() {
    const cobertura = await ApiClient.get('reportes.php?tipo=cobertura');
    const conteo = { Cumplido: 0, Parcial: 0, 'Sin visitar': 0 };
    cobertura.forEach(m => conteo[m.estado]++);

    new Chart(document.getElementById('chart-cobertura'), {
        type: 'pie',
        data: {
            labels: Object.keys(conteo),
            datasets: [{ data: Object.values(conteo), backgroundColor: ['#10b981','#f59e0b','#ef4444'] }]
        }
    });

    const ranking = await ApiClient.get('reportes.php?tipo=ranking_productos');
    new Chart(document.getElementById('chart-ranking'), {
        type: 'bar',
        data: {
            labels: ranking.map(r => r.nombre),
            datasets: [{ label: 'Visitas', data: ranking.map(r => r.total_visitas), backgroundColor: '#3b82f6' }]
        },
        options: { indexAxis: 'y' }
    });

    // frecuencia y prescripciones siguen el mismo patrón
}
```

---

## 5. `apiClient.js` — wrapper reutilizable

```js
const ApiClient = {
    base: 'api/',
    async get(endpoint) {
        const res = await fetch(this.base + endpoint);
        return res.json();
    },
    async post(endpoint, data) {
        const res = await fetch(this.base + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return res.json();
    },
    async put(endpoint, data) {
        const res = await fetch(this.base + endpoint, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return res.json();
    },
    async delete(endpoint) {
        const res = await fetch(this.base + endpoint, { method: 'DELETE' });
        return res.json();
    }
};
```

---

## 6. Uso en campo desde el celular (sin AppSheet, sin app nativa)

Como es una web normal, la visitadora simplemente:
1. Abre el navegador (Chrome Android) → entra a la URL del sistema
2. **"Agregar a pantalla de inicio"** desde el menú de Chrome → le queda un ícono como si fuera app nativa (esto se llama PWA-lite, sin necesidad de todo el aparataje de Service Workers si no quieres complicarte)

**Punto débil real de este enfoque frente a AppSheet: sin internet, no funciona.** grade_app tampoco resuelve esto (usa `localStorage` pero no cola de sincronización). Si necesitas registrar visitas sin señal en el sector, hay dos caminos:
- **Simple:** el formulario guarda en `localStorage` si el `fetch()` a `visitas.php` falla, y un botón "Sincronizar pendientes" los reenvía cuando vuelve la señal (parecido a lo que ya hace `gradeManager.js` con su `catch` que cae a localStorage).
- **Robusto:** convertir la web en PWA real con Service Worker + IndexedDB — más trabajo, pero sincronización automática en segundo plano.

Para una visitadora que se mueve por San Miguelito (zona urbana con cobertura razonable), la opción simple probablemente basta.

---

## 7. Migración de datos desde el Sheet de AppSheet (si ya cargaste médicos ahí)

`import_excel.php` — reutiliza la misma lógica de `excelParser.js` pero corriendo del lado servidor con `PhpSpreadsheet`, o más simple: exportas el Sheet de Google como .xlsx, lo subes una vez con un formulario tipo el de grade_app, y un script PHP recorre las filas e inserta en SQLite. Es un uso único, no parte del flujo diario.

---

## 8. Orden de construcción sugerido

1. `schema.sql` + `db.php` — verifica que SQLite conecta y crea las tablas
2. `medicos.php` + tab Médicos — CRUD más simple, valida el patrón end-to-end
3. `productos.php` + carga de catálogo de productos de ginecología
4. `visitas.php` + tab Registrar Visita — el flujo diario real
5. `reportes.php` (endpoint `eficiencia` primero, es el más simple) + tab Resumen
6. Mapa con Leaflet
7. Resto de charts en Reportes
8. `plan_visitas.php` + lógica de cobertura (depende de que ya existan visitas y médicos para probarse)
9. Offline básico (fallback a localStorage) al final, una vez todo lo demás funciona online

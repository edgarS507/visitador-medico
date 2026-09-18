<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Render: si montas un Persistent Disk en /var/data, la BD sobrevive a los deploys.
// Si NO montas disco (plan free), cambia esto por:
//   $dbPath = __DIR__ . '/../data/visitador.sqlite';
$dbPath = '/var/data/visitador.sqlite';
if (!is_dir('/var/data') || !is_writable('/var/data')) { $dbPath = __DIR__ . '/../data/visitador.sqlite'; }
if (!is_dir(dirname($dbPath))) { mkdir(dirname($dbPath), 0775, true); }
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
    $pdo->exec(file_get_contents(__DIR__ . '/../schema.sql'));
} else {
    // Migraciones idempotentes para instalaciones existentes.
    $pdo->exec("CREATE TABLE IF NOT EXISTS farmacias (id_farmacia INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, direccion TEXT NOT NULL, encargado TEXT, lat REAL NOT NULL DEFAULT 0, lng REAL NOT NULL DEFAULT 0, activo INTEGER DEFAULT 1)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS visitas_farmacias (id_visita_farmacia INTEGER PRIMARY KEY AUTOINCREMENT, id_farmacia INTEGER NOT NULL REFERENCES farmacias(id_farmacia), fecha TEXT NOT NULL, stock_productos TEXT, pedido_sugerido TEXT, observaciones TEXT)");
    foreach (["ALTER TABLE productos ADD COLUMN activo INTEGER DEFAULT 1", "ALTER TABLE visitas ADD COLUMN compromisos TEXT", "ALTER TABLE visitas ADD COLUMN muestras_material TEXT", "ALTER TABLE visitas ADD COLUMN hora TEXT"] as $migration) { try { $pdo->exec($migration); } catch (PDOException $e) { /* columna ya existente */ } }
}

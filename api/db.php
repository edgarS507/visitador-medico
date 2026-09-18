<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Render: si montas un Persistent Disk en /var/data, la BD sobrevive a los deploys.
// Si NO montas disco (plan free), cambia esto por:
//   $dbPath = __DIR__ . '/../data/visitador.sqlite';
$dbPath = '/var/data/visitador.sqlite';
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
}

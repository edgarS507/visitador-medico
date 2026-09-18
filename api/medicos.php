<?php
require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM medicos WHERE id_medico = ?');
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch());
        } else {
            $where = 'WHERE activo = 1';
            $params = [];
            if (!empty($_GET['especialidad'])) {
                $where .= ' AND especialidad = ?';
                $params[] = $_GET['especialidad'];
            }
            if (!empty($_GET['q'])) {
                $where .= ' AND (nombre LIKE ? OR centro_salud LIKE ?)';
                $params[] = '%' . $_GET['q'] . '%';
                $params[] = '%' . $_GET['q'] . '%';
            }
            $stmt = $pdo->prepare("SELECT * FROM medicos $where ORDER BY nombre");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['nombre']) || empty($data['especialidad'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'nombre y especialidad son requeridos']);
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO medicos (nombre, especialidad, centro_salud, lat, lng, sector)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nombre'],
            $data['especialidad'],
            $data['centro_salud'] ?? null,
            $data['lat'] ?? null,
            $data['lng'] ?? null,
            $data['sector'] ?? 'San Miguelito'
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
            $data['nombre'],
            $data['especialidad'],
            $data['centro_salud'] ?? null,
            $data['lat'] ?? null,
            $data['lng'] ?? null,
            $data['sector'] ?? 'San Miguelito',
            $data['id_medico']
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        // Baja lógica para conservar historial
        $stmt = $pdo->prepare('UPDATE medicos SET activo = 0 WHERE id_medico = ?');
        $stmt->execute([$_GET['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

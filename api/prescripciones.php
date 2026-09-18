<?php
require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM prescripciones WHERE id_prescripcion = ?');
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch());
        } else {
            $where = '1=1';
            $params = [];
            if (!empty($_GET['id_medico'])) {
                $where .= ' AND pr.id_medico = ?';
                $params[] = $_GET['id_medico'];
            }
            $stmt = $pdo->prepare("
                SELECT pr.*, m.nombre AS nombre_medico, p.nombre AS nombre_producto
                FROM prescripciones pr
                JOIN medicos m ON m.id_medico = pr.id_medico
                JOIN productos p ON p.id_producto = pr.id_producto
                WHERE $where
                ORDER BY pr.fecha DESC
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare(
            'INSERT INTO prescripciones (id_medico, id_producto, cantidad_estimada, fecha)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['id_medico'],
            $data['id_producto'],
            $data['cantidad_estimada'] ?? null,
            $data['fecha']
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare(
            'UPDATE prescripciones SET id_medico=?, id_producto=?, cantidad_estimada=?, fecha=?
             WHERE id_prescripcion=?'
        );
        $stmt->execute([
            $data['id_medico'],
            $data['id_producto'],
            $data['cantidad_estimada'] ?? null,
            $data['fecha'],
            $data['id_prescripcion']
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        $stmt = $pdo->prepare('DELETE FROM prescripciones WHERE id_prescripcion = ?');
        $stmt->execute([$_GET['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

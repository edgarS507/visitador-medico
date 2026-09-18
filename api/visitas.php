<?php
require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('
                SELECT v.*, m.nombre AS nombre_medico, p.nombre AS nombre_producto
                FROM visitas v
                JOIN medicos m ON m.id_medico = v.id_medico
                JOIN productos p ON p.id_producto = v.id_producto
                WHERE v.id_visita = ?
            ');
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch());
        } else {
            $where = '1=1';
            $params = [];
            if (!empty($_GET['id_medico'])) {
                $where .= ' AND v.id_medico = ?';
                $params[] = $_GET['id_medico'];
            }
            if (!empty($_GET['fecha_desde'])) {
                $where .= ' AND v.fecha >= ?';
                $params[] = $_GET['fecha_desde'];
            }
            if (!empty($_GET['fecha_hasta'])) {
                $where .= ' AND v.fecha <= ?';
                $params[] = $_GET['fecha_hasta'];
            }
            if (!empty($_GET['mes'])) {
                $where .= " AND strftime('%Y-%m', v.fecha) = ?";
                $params[] = $_GET['mes'];
            }
            $stmt = $pdo->prepare("
                SELECT v.*, m.nombre AS nombre_medico, p.nombre AS nombre_producto
                FROM visitas v
                JOIN medicos m ON m.id_medico = v.id_medico
                JOIN productos p ON p.id_producto = v.id_producto
                WHERE $where
                ORDER BY v.fecha DESC, v.id_visita DESC
                LIMIT 200
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id_medico']) || empty($data['id_producto']) || empty($data['fecha'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id_medico, id_producto y fecha son requeridos']);
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO visitas (id_medico, id_producto, material_entregado, duracion_min, resultado, fecha)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['id_medico'],
            $data['id_producto'],
            $data['material_entregado'] ?? null,
            $data['duracion_min'] ?? null,
            $data['resultado'] ?? 'Efectiva',
            $data['fecha']
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare(
            'UPDATE visitas SET id_medico=?, id_producto=?, material_entregado=?,
             duracion_min=?, resultado=?, fecha=? WHERE id_visita=?'
        );
        $stmt->execute([
            $data['id_medico'],
            $data['id_producto'],
            $data['material_entregado'] ?? null,
            $data['duracion_min'] ?? null,
            $data['resultado'] ?? 'Efectiva',
            $data['fecha'],
            $data['id_visita']
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        $stmt = $pdo->prepare('DELETE FROM visitas WHERE id_visita = ?');
        $stmt->execute([$_GET['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

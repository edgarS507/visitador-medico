<?php
require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM productos WHERE id_producto = ?');
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch());
        } else {
            $where = '1=1';
            $params = [];
            if (!empty($_GET['categoria'])) {
                $where .= ' AND categoria = ?';
                $params[] = $_GET['categoria'];
            }
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE $where ORDER BY nombre");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['nombre']) || empty($data['categoria'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'nombre y categoria son requeridos']);
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO productos (nombre, categoria, material_promocional) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $data['nombre'],
            $data['categoria'],
            $data['material_promocional'] ?? null
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare(
            'UPDATE productos SET nombre=?, categoria=?, material_promocional=? WHERE id_producto=?'
        );
        $stmt->execute([
            $data['nombre'],
            $data['categoria'],
            $data['material_promocional'] ?? null,
            $data['id_producto']
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        $stmt = $pdo->prepare('DELETE FROM productos WHERE id_producto = ?');
        $stmt->execute([$_GET['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

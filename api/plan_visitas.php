<?php
require 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $mes = $_GET['mes'] ?? date('Y-m');
        if (isset($_GET['id_medico'])) {
            $stmt = $pdo->prepare(
                'SELECT * FROM plan_visitas WHERE id_medico = ? AND mes = ?'
            );
            $stmt->execute([$_GET['id_medico'], $mes]);
            echo json_encode($stmt->fetch());
        } else {
            $stmt = $pdo->prepare('
                SELECT pv.*, m.nombre AS nombre_medico
                FROM plan_visitas pv
                JOIN medicos m ON m.id_medico = pv.id_medico
                WHERE pv.mes = ?
                ORDER BY m.nombre
            ');
            $stmt->execute([$mes]);
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        // Upsert: si ya existe plan para ese médico/mes, actualiza
        $stmt = $pdo->prepare(
            'SELECT id_plan FROM plan_visitas WHERE id_medico = ? AND mes = ?'
        );
        $stmt->execute([$data['id_medico'], $data['mes']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE plan_visitas SET meta_visitas = ? WHERE id_plan = ?'
            );
            $stmt->execute([$data['meta_visitas'], $existing['id_plan']]);
            echo json_encode(['success' => true, 'id' => $existing['id_plan'], 'updated' => true]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO plan_visitas (id_medico, mes, meta_visitas) VALUES (?, ?, ?)'
            );
            $stmt->execute([$data['id_medico'], $data['mes'], $data['meta_visitas']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'updated' => false]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare(
            'UPDATE plan_visitas SET meta_visitas=? WHERE id_plan=?'
        );
        $stmt->execute([$data['meta_visitas'], $data['id_plan']]);
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        $stmt = $pdo->prepare('DELETE FROM plan_visitas WHERE id_plan = ?');
        $stmt->execute([$_GET['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

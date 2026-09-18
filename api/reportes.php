<?php
require 'db.php';

$reporte = $_GET['tipo'] ?? '';

switch ($reporte) {

    case 'cobertura':
        $mes = $_GET['mes'] ?? date('Y-m');
        $sql = "
            SELECT m.id_medico, m.nombre, m.especialidad, m.centro_salud, m.lat, m.lng, m.sector,
                   COALESCE(v.total_visitas, 0) AS visitas_mes,
                   COALESCE(p.meta, 2) AS meta_mes,
                   CASE
                       WHEN COALESCE(v.total_visitas,0) = 0 THEN 'Sin visitar'
                       WHEN v.total_visitas >= COALESCE(p.meta, 2) THEN 'Cumplido'
                       ELSE 'Parcial'
                   END AS estado
            FROM medicos m
            LEFT JOIN (
                SELECT id_medico, COUNT(*) AS total_visitas
                FROM visitas
                WHERE strftime('%Y-%m', fecha) = ?
                GROUP BY id_medico
            ) v ON v.id_medico = m.id_medico
            LEFT JOIN (
                SELECT id_medico, meta_visitas AS meta
                FROM plan_visitas
                WHERE mes = ?
            ) p ON p.id_medico = m.id_medico
            WHERE m.activo = 1
            ORDER BY m.nombre
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mes, $mes]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'frecuencia':
        $periodo = $_GET['periodo'] ?? 'mes';
        if ($periodo === 'semana') {
            $agrupacion = "strftime('%Y-W%W', fecha)";
        } else {
            $agrupacion = "strftime('%Y-%m', fecha)";
        }
        $sql = "SELECT $agrupacion AS periodo, COUNT(*) AS total
                FROM visitas GROUP BY periodo ORDER BY periodo DESC LIMIT 24";
        echo json_encode($pdo->query($sql)->fetchAll());
        break;

    case 'ranking_productos':
        $sql = "
            SELECT p.nombre, p.categoria, COUNT(v.id_visita) AS total_visitas
            FROM productos p
            LEFT JOIN visitas v ON v.id_producto = p.id_producto
            GROUP BY p.id_producto
            ORDER BY total_visitas DESC
        ";
        echo json_encode($pdo->query($sql)->fetchAll());
        break;

    case 'prescripciones_evolucion':
        $sql = "
            SELECT strftime('%Y-%m', pr.fecha) AS mes, p.nombre AS producto,
                   SUM(pr.cantidad_estimada) AS total
            FROM prescripciones pr
            JOIN productos p ON p.id_producto = pr.id_producto
            GROUP BY mes, p.id_producto
            ORDER BY mes DESC
            LIMIT 60
        ";
        echo json_encode($pdo->query($sql)->fetchAll());
        break;

    case 'eficiencia':
        $mes = $_GET['mes'] ?? date('Y-m');
        $sql = "
            SELECT
                COALESCE(AVG(duracion_min), 0)                               AS duracion_promedio,
                SUM(CASE WHEN resultado='Efectiva'  THEN 1 ELSE 0 END)       AS efectivas,
                SUM(CASE WHEN resultado='Cancelada' THEN 1 ELSE 0 END)       AS canceladas,
                COUNT(*)                                                       AS total
            FROM visitas
            WHERE strftime('%Y-%m', fecha) = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mes]);
        echo json_encode($stmt->fetch());
        break;

    case 'medicos_sin_visitar':
        $mes = $_GET['mes'] ?? date('Y-m');
        $sql = "
            SELECT m.id_medico, m.nombre, m.especialidad, m.centro_salud
            FROM medicos m
            WHERE m.activo = 1
              AND m.id_medico NOT IN (
                  SELECT DISTINCT id_medico FROM visitas
                  WHERE strftime('%Y-%m', fecha) = ?
              )
            ORDER BY m.nombre
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mes]);
        echo json_encode($stmt->fetchAll());
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tipo de reporte desconocido: ' . $reporte]);
}

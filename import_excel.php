<?php
/**
 * import_excel.php — Importador one-time de médicos desde Excel/CSV
 * Uso: abrir http://localhost/visitador-medico/import_excel.php en el navegador
 * Solo necesita correrse una vez para migrar datos desde el Sheet de Google/AppSheet.
 *
 * Acepta CSV con columnas: nombre, especialidad, centro_salud, lat, lng, sector
 * O archivo .xlsx (requiere PhpSpreadsheet, instalado vía Composer si está disponible)
 */

require __DIR__ . '/api/db.php';

$message = '';
$type    = '';
$preview = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $file    = $_FILES['archivo'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $tmpPath = $file['tmp_name'];

    if ($ext === 'csv') {
        $rows = array_map('str_getcsv', file($tmpPath));
        $header = array_map('strtolower', array_map('trim', $rows[0]));
        $imported = 0;
        $errors   = 0;

        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO medicos (nombre, especialidad, centro_salud, lat, lng, sector)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        for ($i = 1; $i < count($rows); $i++) {
            if (count($rows[$i]) < 2) continue;
            $row = array_combine($header, array_pad($rows[$i], count($header), null));
            try {
                $stmt->execute([
                    $row['nombre']        ?? '',
                    $row['especialidad']  ?? 'Ginecología',
                    $row['centro_salud']  ?? null,
                    is_numeric($row['lat'] ?? '') ? (float)$row['lat'] : null,
                    is_numeric($row['lng'] ?? '') ? (float)$row['lng'] : null,
                    $row['sector']        ?? 'San Miguelito',
                ]);
                $imported++;
                if (count($preview) < 5) $preview[] = $row['nombre'] ?? '?';
            } catch (Exception $e) {
                $errors++;
            }
        }

        $message = "✓ $imported médico(s) importado(s)" . ($errors ? " | ⚠ $errors error(es)" : '');
        $type    = 'success';

    } else {
        $message = 'Por ahora solo se soporta CSV. Exporta tu hoja de Google como CSV (Archivo → Descargar → CSV).';
        $type    = 'error';
    }
}

// Conteo actual
$total = $pdo->query('SELECT COUNT(*) FROM medicos WHERE activo = 1')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Médicos — Visitadora Médica</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .import-card { max-width: 520px; width: 100%; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; }
        .alert-error   { background: rgba(239,68,68,0.15);  border: 1px solid rgba(239,68,68,0.3);  color: #fca5a5; }
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: border-color var(--transition);
        }
        .drop-zone:hover { border-color: var(--primary); }
    </style>
</head>
<body>
<div class="card import-card">
    <div class="card-title" style="font-size:20px; margin-bottom:8px;">📥 Importar Médicos</div>
    <p style="color:var(--text-2); font-size:13px; margin-bottom:20px;">
        Sube un CSV exportado desde Google Sheets o AppSheet.<br>
        Columnas esperadas: <code>nombre, especialidad, centro_salud, lat, lng, sector</code>
    </p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $type ?>">
            <?= htmlspecialchars($message) ?>
            <?php if ($preview): ?>
                <br><small>Primeros importados: <?= implode(', ', array_map('htmlspecialchars', $preview)) ?></small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-success" style="margin-bottom:20px;">
        Médicos activos en la base de datos: <strong><?= $total ?></strong>
    </div>

    <form method="post" enctype="multipart/form-data">
        <div class="drop-zone" onclick="document.getElementById('archivo').click()">
            <div style="font-size:36px; margin-bottom:12px;">📂</div>
            <p>Haz clic o arrastra tu archivo CSV aquí</p>
            <small class="text-muted">Solo .csv por ahora</small>
            <input type="file" id="archivo" name="archivo" accept=".csv" style="display:none" onchange="this.form.submit()">
        </div>
    </form>

    <hr class="divider">
    <a href="index.php" class="btn btn-primary" style="width:100%; justify-content:center; text-decoration:none; margin-top:8px;">
        ← Volver al dashboard
    </a>
</div>
</body>
</html>

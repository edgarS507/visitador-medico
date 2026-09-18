<?php require_once __DIR__ . '/auth.php'; require_login(); $u = usuario_actual(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard para visitadora médica — gestión de médicos, visitas, cobertura y reportes en San Miguelito, Panamá">
    <title>Visitadora Médica — Dashboard</title>

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">

    <!-- Estilos propios -->
    <link rel="stylesheet" href="css/styles.css">

    <!-- Web App meta -->
    <meta name="theme-color" content="#0b1120">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body>

<div class="app-shell">

    <!-- ══ HEADER ══════════════════════════════════════════════════ -->
    <header class="app-header">
        <div class="header-brand">
            <div class="header-logo">💊</div>
            <div>
                <div class="header-title">MedVisit Pro</div>
                <span class="header-subtitle">Dashboard Visitadora Médica · San Miguelito</span>
            </div>
        </div>
        <div class="header-actions">
            <div class="btn-sync-wrapper">
                <button id="btn-sync-offline" class="btn btn-ghost btn-sm" title="Sincronizar registros pendientes">
                    ⬆ Sincronizar
                </button>
                <span id="offline-badge">0</span>
            </div>
        </div>
    </header>

    <!-- ══ NAVIGATION ══════════════════════════════════════════════ -->
    <nav class="tabs-nav" role="navigation" aria-label="Secciones principales">
        <button class="tablink" data-tab="resumen"   aria-controls="tab-resumen">📊 Resumen</button>
        <button class="tablink" data-tab="medicos" aria-controls="tab-medicos">👨‍⚕️ Médicos</button>
        <button class="tablink" data-tab="productos" aria-controls="tab-productos">💊 Productos</button>
        <button class="tablink" data-tab="farmacias" aria-controls="tab-farmacias">🏪 Farmacias</button>
        <button class="tablink" data-tab="registrar" aria-controls="tab-registrar">➕ Registrar Visita</button>
        <button class="tablink" data-tab="visitas"   aria-controls="tab-visitas">📋 Historial</button>
        <button class="tablink" data-tab="mapa"      aria-controls="tab-mapa">🗺 Mapa</button>
        <button class="tablink" data-tab="reportes"  aria-controls="tab-reportes">📈 Reportes</button>
        <button class="tablink" data-tab="plan" aria-controls="tab-plan">🎯 Plan</button>
        <a class="tablink" href="reporte_visita.php" target="_blank">🖨 PDF</a>
        <a class="tablink" href="perfil.php">👤 <?=htmlspecialchars($u['nombre'])?></a>
        <a class="tablink" href="logout.php">Salir</a>
    </nav>

    <!-- ══ MAIN ════════════════════════════════════════════════════ -->
    <main class="main-content" role="main">

        <!-- ━━━━━━ TAB: RESUMEN ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <section id="tab-resumen" class="tab-content" role="tabpanel" aria-labelledby="tab-btn-resumen">

            <div class="section-header">
                <div>
                    <h1 class="section-title">Resumen del mes</h1>
                    <p class="section-subtitle">KPIs en tiempo real desde la base de datos</p>
                </div>
                <input type="month" id="filtro-mes-resumen" class="btn btn-ghost btn-sm" style="cursor:pointer">
            </div>

            <!-- KPIs -->
            <div class="kpi-grid">
                <div class="kpi-card" style="--accent-color: #10b981">
                    <div class="kpi-label">Cobertura</div>
                    <div class="kpi-value" id="kpi-cobertura">—</div>
                    <div class="kpi-icon">🎯</div>
                </div>
                <div class="kpi-card" style="--accent-color: #6366f1">
                    <div class="kpi-label">Visitas realizadas</div>
                    <div class="kpi-value" id="kpi-visitas-mes">—</div>
                    <div class="kpi-icon">📋</div>
                </div>
                <div class="kpi-card" style="--accent-color: #14b8a6">
                    <div class="kpi-label">Duración promedio</div>
                    <div class="kpi-value" id="kpi-duracion">—</div>
                    <div class="kpi-icon">⏱</div>
                </div>
                <div class="kpi-card" style="--accent-color: #f59e0b">
                    <div class="kpi-label">Efectivas / Canceladas</div>
                    <div class="kpi-value" id="kpi-ratio">—</div>
                    <div class="kpi-icon">✅</div>
                </div>
                <div class="kpi-card" style="--accent-color: #10b981">
                    <div class="kpi-label">Metas cumplidas</div>
                    <div class="kpi-value" id="kpi-cumplidos">—</div>
                    <div class="kpi-icon">🏆</div>
                </div>
                <div class="kpi-card" style="--accent-color: #ef4444">
                    <div class="kpi-label">Sin visitar</div>
                    <div class="kpi-value" id="kpi-sin-visitar">—</div>
                    <div class="kpi-icon">⚠</div>
                </div>
            </div>

            <!-- Médicos pendientes -->
            <div class="card">
                <div class="card-title">⚠️ Médicos sin visitar este mes</div>
                <ul id="lista-sin-visitar" class="plan-grid" style="list-style:none; gap:8px;">
                    <li class="text-muted">Cargando...</li>
                </ul>
            </div>

        </section>

        <!-- ━━━━━━ TAB: MÉDICOS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <section id="tab-medicos" class="tab-content" role="tabpanel">

            <div class="section-header">
                <div>
                    <h2 class="section-title">Médicos</h2>
                    <p class="section-subtitle">Directorio de médicos activos</p>
                </div>
                <button class="btn btn-primary" onclick="MedicosManager.abrirModal()">+ Nuevo médico</button>
            </div>

            <div class="search-bar">
                <span class="search-icon">🔍</span>
                <input type="search" id="medicos-buscar" placeholder="Buscar por nombre, especialidad o centro de salud…">
            </div>

            <div id="medicos-lista">
                <div class="empty-state">
                    <div class="empty-icon">⏳</div>
                    <p>Cargando médicos…</p>
                </div>
            </div>

        </section>

        <!-- ━━━━━━ TAB: REGISTRAR VISITA ━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <section id="tab-registrar" class="tab-content" role="tabpanel">

            <div class="section-header">
                <div>
                    <h2 class="section-title">Registrar Visita</h2>
                    <p class="section-subtitle">Registra una visita médica en campo</p>
                </div>
            </div>

            <div class="registrar-layout">
                <!-- Formulario -->
                <div class="card">
                    <form id="form-visita" novalidate>
                        <div class="form-grid">
                            <div class="form-group span-2">
                                <label for="visita-medico">Médico *</label>
                                <select id="visita-medico" required>
                                    <option value="">— Cargando médicos… —</option>
                                </select>
                            </div>
                            <div class="form-group span-2">
                                <label for="visita-producto">Producto presentado *</label>
                                <select id="visita-producto" required>
                                    <option value="">— Cargando productos… —</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="visita-fecha">Fecha *</label>
                                <input type="date" id="visita-fecha" required>
                            </div>
                            <div class="form-group">
                                <label for="visita-resultado">Resultado</label>
                                <select id="visita-resultado">
                                    <option value="Efectiva">✅ Efectiva</option>
                                    <option value="Cancelada">❌ Cancelada</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="visita-duracion">Duración (minutos)</label>
                                <input type="number" id="visita-duracion" min="1" max="180" placeholder="Ej: 15">
                            </div>
                            <div class="form-group">
                                <label for="visita-material">Material entregado</label>
                                <input type="text" id="visita-material" placeholder="Muestras, folletos…">
                            </div>
                        </div>
                        <div class="modal-footer" style="border:none; padding-top: 20px; margin-top:0">
                            <button type="submit" class="btn btn-primary" style="width:100%">💾 Guardar visita</button>
                        </div>
                    </form>
                </div>

                <!-- Tips card -->
                <div class="card glass">
                    <div class="card-title">💡 Modo campo</div>
                    <p style="font-size:13px; color:var(--text-2); line-height:1.8;">
                        Si no hay señal al guardar, el registro se guarda localmente. 
                        Cuando recuperes conexión, presiona <strong>"⬆ Sincronizar"</strong> en la barra superior para enviarlo.
                    </p>
                    <hr class="divider">
                    <div class="card-title">📦 Pendientes offline</div>
                    <div id="pending-list" class="pending-list text-muted">Sin registros pendientes.</div>
                </div>
            </div>

        </section>

        <!-- ━━━━━━ TAB: HISTORIAL VISITAS ━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <section id="tab-visitas" class="tab-content" role="tabpanel">

            <div class="section-header">
                <div>
                    <h2 class="section-title">Historial de Visitas</h2>
                    <p class="section-subtitle">Últimas 200 visitas del período</p>
                </div>
                <input type="month" id="visitas-filtro-mes" class="btn btn-ghost btn-sm" style="cursor:pointer">
            </div>

            <div class="card" style="padding:0; overflow:hidden;">
                <div id="visitas-historial" style="padding:20px;">
                    <div class="empty-state">
                        <div class="empty-icon">⏳</div>
                        <p>Cargando historial…</p>
                    </div>
                </div>
            </div>

        </section>

        <!-- ━━━━━━ TAB: MAPA ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <section id="tab-mapa" class="tab-content" role="tabpanel">

            <div class="section-header">
                <div>
                    <h2 class="section-title">Mapa de cobertura</h2>
                    <p class="section-subtitle">Cobertura por médico en San Miguelito</p>
                </div>
            </div>

            <div class="mapa-toolbar">
                <input type="month" id="mapa-filtro-mes" class="btn btn-ghost btn-sm" style="cursor:pointer">
                <div id="mapa-leyenda"></div>
            </div>

            <div style="position:relative;">
                <div id="mapa-container"></div>
                <div class="mapa-spinner" id="mapa-spinner">
                    <div class="spinner"></div>
                </div>
            </div>

            <div id="mapa-info" class="mt-4"></div>

        </section>

        <section id="tab-productos" class="tab-content" role="tabpanel"><div class="section-header"><div><h2 class="section-title">Productos</h2><p class="section-subtitle">Catálogo para promoción y visitas</p></div><button class="btn btn-primary" onclick="ProductosManager.abrirModal()">+ Nuevo producto</button></div><div class="card"><div id="productos-lista"></div></div></section>
        <section id="tab-farmacias" class="tab-content" role="tabpanel"><div class="section-header"><div><h2 class="section-title">Visitas a Farmacias</h2><p class="section-subtitle">Transferencia, stock y pedidos sugeridos</p></div><button class="btn btn-primary" type="button" onclick="FarmaciasManager.nueva()">+ Nueva farmacia</button></div><div class="card"><form id="form-visita-farmacia" class="form-grid"><select id="farm-vis-farmacia" required><option value="">Seleccione farmacia</option></select><input type="date" id="farm-vis-fecha" required><input id="farm-vis-stock" placeholder="Stock de productos"><input id="farm-vis-pedido" placeholder="Pedido sugerido"><textarea id="farm-vis-observaciones" placeholder="Observaciones"></textarea><button class="btn btn-primary">Registrar visita</button></form><hr class="divider"><div id="farmacias-lista"></div></div></section>
        <!-- ━━━━━━ TAB: REPORTES ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <section id="tab-reportes" class="tab-content" role="tabpanel">

            <div class="section-header">
                <div>
                    <h2 class="section-title">Reportes & Analytics</h2>
                    <p class="section-subtitle">Visualización de datos históricos</p>
                </div>
                <div class="flex gap-2 items-center flex-wrap">
                    <select id="frecuencia-periodo" class="btn btn-ghost btn-sm">
                        <option value="mes">Por mes</option>
                        <option value="semana">Por semana</option>
                    </select>
                    <input type="month" id="reportes-filtro-mes" class="btn btn-ghost btn-sm" style="cursor:pointer">
                    <button id="btn-actualizar-reportes" class="btn btn-primary btn-sm">↺ Actualizar</button>
                </div>
            </div>

            <div class="charts-grid">

                <!-- Cobertura doughnut -->
                <div class="chart-card">
                    <div class="chart-card-title">🎯 Cobertura del mes</div>
                    <canvas id="chart-cobertura" height="220"></canvas>
                    <div class="cobertura-center" id="cobertura-total"></div>
                </div>

                <!-- Frecuencia bar -->
                <div class="chart-card">
                    <div class="chart-card-title">📅 Frecuencia de visitas</div>
                    <canvas id="chart-frecuencia" height="220"></canvas>
                </div>

                <!-- Ranking productos horizontal bar -->
                <div class="chart-card">
                    <div class="chart-card-title">💊 Ranking de productos</div>
                    <canvas id="chart-ranking" height="220"></canvas>
                </div>

                <!-- Prescripciones line -->
                <div class="chart-card">
                    <div class="chart-card-title">📈 Evolución de prescripciones</div>
                    <canvas id="chart-prescripciones" height="220"></canvas>
                </div>

            </div>

        </section>

        <!-- ━━━━━━ TAB: PLAN DE VISITAS ━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <section id="tab-plan" class="tab-content" role="tabpanel">

            <div class="section-header">
                <div>
                    <h2 class="section-title">Plan de Visitas</h2>
                    <p class="section-subtitle">Meta mensual de visitas por médico</p>
                </div>
                <div class="flex gap-2 items-center">
                    <input type="month" id="plan-filtro-mes" class="btn btn-ghost btn-sm" style="cursor:pointer">
                    <button id="btn-guardar-plan" class="btn btn-primary btn-sm">💾 Guardar plan</button>
                </div>
            </div>

            <div class="card">
                <div id="plan-container">
                    <div class="empty-state">
                        <div class="empty-icon">⏳</div>
                        <p>Cargando plan…</p>
                    </div>
                </div>
            </div>

        </section>

    </main>
</div>

<!-- ══ MODAL: Médico ═══════════════════════════════════════════════ -->
<div id="modal-medico" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-medico-titulo">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modal-medico-titulo">Nuevo Médico</span>
            <button class="modal-close" id="btn-cerrar-modal-medico" aria-label="Cerrar">✕</button>
        </div>
        <form id="form-medico" novalidate>
            <div class="form-grid">
                <div class="form-group span-2">
                    <label for="med-nombre">Nombre completo *</label>
                    <input type="text" id="med-nombre" placeholder="Dr/a. Nombre Apellido" required>
                </div>
                <div class="form-group">
                    <label for="med-especialidad">Especialidad *</label>
                    <select id="med-especialidad" required>
                        <option value="">— Seleccione —</option>
                        <option value="Ginecología">Ginecología</option>
                        <option value="Medicina General">Medicina General</option>
                        <option value="Obstetricia">Obstetricia</option>
                        <option value="Pediatría">Pediatría</option>
                        <option value="Medicina Interna">Medicina Interna</option>
                        <option value="Farmacéutico">Farmacéutico</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="med-sector">Sector</label>
                    <select id="med-sector">
                        <option value="San Miguelito">San Miguelito</option>
                        <option value="Alcalde Díaz">Alcalde Díaz</option>
                        <option value="Amelia Denis">Amelia Denis</option>
                        <option value="Belisario Frías">Belisario Frías</option>
                        <option value="Belisario Porras">Belisario Porras</option>
                        <option value="Chilibre">Chilibre</option>
                        <option value="Las Cumbres">Las Cumbres</option>
                        <option value="Mateo Iturralde">Mateo Iturralde</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="form-group span-2">
                    <label for="med-centro">Centro de salud / Clínica</label>
                    <input type="text" id="med-centro" placeholder="Hospital, Centro de Salud…">
                </div>
                <div class="form-group">
                    <label for="med-lat">Latitud *</label>
                    <input type="number" id="med-lat" step="any" placeholder="Detectando…" required readonly><button type="button" class="btn btn-ghost btn-sm geo-btn" onclick="obtenerUbicacion()">📍 Obtener ubicación</button>
                </div>
                <div class="form-group">
                    <label for="med-lng">Longitud *</label>
                    <input type="number" id="med-lng" step="any" placeholder="Detectando…" required readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" id="btn-cancelar-medico">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Guardar</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-producto" class="modal-overlay"><div class="modal"><div class="modal-header"><span class="modal-title" id="modal-producto-titulo">Nuevo Producto</span><button class="modal-close" onclick="ProductosManager.cerrarModal()">✕</button></div><form id="form-producto"><div class="form-grid"><input type="hidden" id="prod-id"><label>Nombre *<input id="prod-nombre" required></label><label>Categoría *<input id="prod-categoria" required></label><label class="span-2">Material promocional<input id="prod-material"></label></div><div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="ProductosManager.cerrarModal()">Cancelar</button><button class="btn btn-primary">Guardar</button></div></form></div></div>
<!-- ══ TOAST CONTAINER ══════════════════════════════════════════════ -->
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- ══ SCRIPTS ══════════════════════════════════════════════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>

<script src="js/apiClient.js"></script>
<script src="js/medicosManager.js"></script>
<script src="js/productosManager.js"></script>
<script src="js/visitasManager.js"></script>
<script src="js/mapManager.js"></script>
<script src="js/chartsManager.js"></script>
<script src="js/planManager.js"></script>
<script src="js/app.js"></script>

</body>
</html>

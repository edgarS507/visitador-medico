/**
 * visitasManager.js — Registro y listado de visitas
 */
const VisitasManager = (() => {

    let visitas = [];

    // ── Poblar selects ───────────────────────────────────────────────────────
    async function cargarSelects() {
        try {
            const [medicos, productos] = await Promise.all([
                ApiClient.get('medicos.php'),
                ApiClient.get('productos.php')
            ]);

            const selMedico   = document.getElementById('visita-medico');
            const selProducto = document.getElementById('visita-producto');
            if (!selMedico || !selProducto) return;

            selMedico.innerHTML = '<option value="">— Seleccione médico —</option>' +
                medicos.map(m => `<option value="${m.id_medico}">${m.nombre} (${m.especialidad})</option>`).join('');

            selProducto.innerHTML = '<option value="">— Seleccione producto —</option>' +
                productos.map(p => `<option value="${p.id_producto}">${p.nombre} — ${p.categoria}</option>`).join('');

        } catch {
            showToast('Error al cargar selects de visita', 'error');
        }
    }

    // ── Registrar visita ─────────────────────────────────────────────────────
    async function registrar(e) {
        e.preventDefault();
        const data = {
            id_medico:         parseInt(document.getElementById('visita-medico').value),
            id_producto:       parseInt(document.getElementById('visita-producto').value),
            fecha:             document.getElementById('visita-fecha').value,
            resultado:         document.getElementById('visita-resultado').value,
            duracion_min:      parseInt(document.getElementById('visita-duracion').value) || null,
            material_entregado: document.getElementById('visita-material').value.trim() || null,
        };

        if (!data.id_medico || !data.id_producto || !data.fecha) {
            showToast('Complete médico, producto y fecha', 'warning');
            return;
        }

        try {
            await ApiClient.post('visitas.php', data);
            showToast('Visita registrada ✓');
            document.getElementById('form-visita').reset();
            document.getElementById('visita-fecha').value = hoy();
            cargarHistorial();
        } catch (err) {
            // Fallback offline
            OfflineQueue.push({ method: 'POST', endpoint: 'visitas.php', data });
            actualizarBadgeOffline();
            showToast('Sin conexión — guardado localmente', 'warning');
        }
    }

    // ── Historial ────────────────────────────────────────────────────────────
    async function cargarHistorial() {
        const mes = document.getElementById('visitas-filtro-mes')?.value || fechaActualMes();
        try {
            visitas = await ApiClient.get(`visitas.php?mes=${mes}`);
            renderHistorial(visitas);
        } catch {
            showToast('Error al cargar historial', 'error');
        }
    }

    function renderHistorial(lista) {
        const container = document.getElementById('visitas-historial');
        if (!container) return;

        if (!lista.length) {
            container.innerHTML = `<div class="empty-state"><div class="empty-icon">📋</div><p>No hay visitas registradas este período.</p></div>`;
            return;
        }

        container.innerHTML = `
            <table class="tabla-visitas">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Médico</th>
                        <th>Producto</th>
                        <th>Duración</th>
                        <th>Resultado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    ${lista.map(v => `
                        <tr>
                            <td>${formatFecha(v.fecha)}</td>
                            <td><strong>${v.nombre_medico}</strong></td>
                            <td>${v.nombre_producto}</td>
                            <td>${v.duracion_min ? v.duracion_min + ' min' : '—'}</td>
                            <td><span class="badge ${v.resultado === 'Efectiva' ? 'badge-green' : 'badge-red'}">${v.resultado}</span></td>
                            <td><button class="btn btn-sm btn-danger" onclick="VisitasManager.eliminar(${v.id_visita})">🗑</button></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    async function eliminar(id) {
        if (!confirm('¿Eliminar esta visita?')) return;
        try {
            await ApiClient.delete(`visitas.php?id=${id}`);
            showToast('Visita eliminada');
            cargarHistorial();
        } catch {
            showToast('Error al eliminar visita', 'error');
        }
    }

    function hoy() {
        return new Date().toISOString().slice(0, 10);
    }

    function formatFecha(fecha) {
        if (!fecha) return '—';
        const [y, m, d] = fecha.split('-');
        return `${d}/${m}/${y}`;
    }

    // ── Init ─────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('form-visita');
        if (form) form.addEventListener('submit', registrar);

        const fechaInput = document.getElementById('visita-fecha');
        if (fechaInput) fechaInput.value = hoy();

        const filtroMes = document.getElementById('visitas-filtro-mes');
        if (filtroMes) {
            filtroMes.value = fechaActualMes();
            filtroMes.addEventListener('change', cargarHistorial);
        }

        cargarSelects();
    });

    return { cargarHistorial, eliminar, cargarSelects };
})();

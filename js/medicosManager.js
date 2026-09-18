/**
 * medicosManager.js — CRUD completo de médicos
 */
const MedicosManager = (() => {

    let medicos = [];
    let editandoId = null;

    // ── Cargar lista ─────────────────────────────────────────────────────────
    async function cargar(q = '') {
        try {
            const endpoint = 'medicos.php' + (q ? `?q=${encodeURIComponent(q)}` : '');
            medicos = await ApiClient.get(endpoint);
            renderLista(medicos);
        } catch {
            showToast('Error al cargar médicos', 'error');
        }
    }

    function renderLista(lista) {
        const container = document.getElementById('medicos-lista');
        if (!container) return;

        if (!lista.length) {
            container.innerHTML = `<div class="empty-state">
                <div class="empty-icon">👨‍⚕️</div>
                <p>No hay médicos registrados todavía.</p>
                <button class="btn btn-primary" onclick="MedicosManager.abrirModal()">Agregar primer médico</button>
            </div>`;
            return;
        }

        container.innerHTML = lista.map(m => `
            <div class="medico-card" data-id="${m.id_medico}">
                <div class="medico-avatar">${iniciales(m.nombre)}</div>
                <div class="medico-info">
                    <h3>${m.nombre}</h3>
                    <span class="badge badge-blue">${m.especialidad}</span>
                    ${m.centro_salud ? `<span class="medico-centro">🏥 ${m.centro_salud}</span>` : ''}
                    ${m.sector ? `<span class="medico-sector">📍 ${m.sector}</span>` : ''}
                    ${m.lat && m.lng ? `<span class="medico-coords text-muted">🗺 ${parseFloat(m.lat).toFixed(4)}, ${parseFloat(m.lng).toFixed(4)}</span>` : ''}
                </div>
                <div class="medico-actions">
                    <button class="btn btn-sm btn-outline" onclick="MedicosManager.abrirModal(${m.id_medico})">✏️ Editar</button>
                    <button class="btn btn-sm btn-danger" onclick="MedicosManager.eliminar(${m.id_medico}, '${escapar(m.nombre)}')">🗑</button>
                </div>
            </div>
        `).join('');
    }

    function iniciales(nombre) {
        return nombre.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase();
    }

    function escapar(str) {
        return str.replace(/'/g, "\\'");
    }

    // ── Modal ────────────────────────────────────────────────────────────────
    function abrirModal(id = null) {
        editandoId = id;
        const modal   = document.getElementById('modal-medico');
        const titulo  = document.getElementById('modal-medico-titulo');
        const form    = document.getElementById('form-medico');

        form.reset();
        titulo.textContent = id ? 'Editar Médico' : 'Nuevo Médico';

        if (id) {
            const m = medicos.find(x => x.id_medico == id);
            if (m) {
                document.getElementById('med-nombre').value       = m.nombre;
                document.getElementById('med-especialidad').value = m.especialidad;
                document.getElementById('med-centro').value       = m.centro_salud || '';
                document.getElementById('med-sector').value       = m.sector || 'San Miguelito';
                document.getElementById('med-lat').value          = m.lat || '';
                document.getElementById('med-lng').value          = m.lng || '';
            }
        }

        modal.classList.add('open');
    }

    function cerrarModal() {
        document.getElementById('modal-medico').classList.remove('open');
        editandoId = null;
    }

    // ── Guardar ──────────────────────────────────────────────────────────────
    async function guardar(e) {
        e.preventDefault();
        const data = {
            nombre:       document.getElementById('med-nombre').value.trim(),
            especialidad: document.getElementById('med-especialidad').value.trim(),
            centro_salud: document.getElementById('med-centro').value.trim() || null,
            sector:       document.getElementById('med-sector').value.trim() || 'San Miguelito',
            lat:          parseFloat(document.getElementById('med-lat').value) || null,
            lng:          parseFloat(document.getElementById('med-lng').value) || null,
        };

        try {
            if (editandoId) {
                data.id_medico = editandoId;
                await ApiClient.put('medicos.php', data);
                showToast('Médico actualizado ✓');
            } else {
                await ApiClient.post('medicos.php', data);
                showToast('Médico registrado ✓');
            }
            cerrarModal();
            cargar();
        } catch {
            showToast('Error al guardar médico', 'error');
        }
    }

    // ── Eliminar ─────────────────────────────────────────────────────────────
    async function eliminar(id, nombre) {
        if (!confirm(`¿Dar de baja a Dr/a. ${nombre}?\n(El historial de visitas se conserva)`)) return;
        try {
            await ApiClient.delete(`medicos.php?id=${id}`);
            showToast(`Dr/a. ${nombre} dado/a de baja`);
            cargar();
        } catch {
            showToast('Error al eliminar', 'error');
        }
    }

    // ── Búsqueda ─────────────────────────────────────────────────────────────
    function buscar(q) {
        if (q.length < 2) {
            renderLista(medicos);
            return;
        }
        const lower = q.toLowerCase();
        const filtrados = medicos.filter(m =>
            m.nombre.toLowerCase().includes(lower) ||
            (m.especialidad || '').toLowerCase().includes(lower) ||
            (m.centro_salud || '').toLowerCase().includes(lower)
        );
        renderLista(filtrados);
    }

    // ── Init listeners ───────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('form-medico');
        if (form) form.addEventListener('submit', guardar);

        const btnCerrar = document.getElementById('btn-cerrar-modal-medico');
        if (btnCerrar) btnCerrar.addEventListener('click', cerrarModal);

        const overlay = document.getElementById('modal-medico');
        if (overlay) overlay.addEventListener('click', e => {
            if (e.target === overlay) cerrarModal();
        });

        const buscador = document.getElementById('medicos-buscar');
        let timer;
        if (buscador) buscador.addEventListener('input', e => {
            clearTimeout(timer);
            timer = setTimeout(() => buscar(e.target.value), 300);
        });
    });

    return { cargar, abrirModal, cerrarModal, eliminar };
})();

/**
 * planManager.js — Plan de visitas mensual (meta por médico)
 */
const PlanManager = (() => {

    let medicos = [];
    let planesActuales = {};
    let mes = null;

    async function cargar() {
        mes = document.getElementById('plan-filtro-mes')?.value || fechaActualMes();

        try {
            const [listaMedicos, planes] = await Promise.all([
                ApiClient.get('medicos.php'),
                ApiClient.get(`plan_visitas.php?mes=${encodeURIComponent(mes)}`)
            ]);

            medicos = listaMedicos;
            planesActuales = {};
            planes.forEach(p => { planesActuales[p.id_medico] = p; });

            renderPlan();
        } catch {
            showToast('Error al cargar plan', 'error');
        }
    }

    function renderPlan() {
        const container = document.getElementById('plan-container');
        if (!container) return;

        if (!medicos.length) {
            container.innerHTML = `<div class="empty-state"><div class="empty-icon">👨‍⚕️</div><p>No hay médicos. Agrega médicos primero.</p></div>`;
            return;
        }

        container.innerHTML = `
            <p style="font-size:13px; color:var(--text-2); margin-bottom:16px;">
                Define cuántas visitas se planifican para cada médico en <strong>${formatMes(mes)}</strong>.
            </p>
            <div class="plan-grid">
                ${medicos.map(m => {
                    const plan = planesActuales[m.id_medico];
                    return `
                        <div class="plan-row">
                            <div class="medico-avatar" style="width:36px;height:36px;font-size:12px">${iniciales(m.nombre)}</div>
                            <div class="plan-medico-name">
                                <div style="font-size:14px">${m.nombre}</div>
                                <span class="badge badge-blue" style="font-size:10px">${m.especialidad}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <label style="font-size:11px; color:var(--text-3); text-transform:none; letter-spacing:0">Meta:</label>
                                <input type="number"
                                       class="plan-meta-input"
                                       data-id="${m.id_medico}"
                                       value="${plan ? plan.meta_visitas : 2}"
                                       min="0" max="99"
                                       title="Meta de visitas para ${m.nombre}">
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    async function guardar() {
        const inputs = document.querySelectorAll('.plan-meta-input');
        const promesas = [];

        inputs.forEach(input => {
            const idMedico = parseInt(input.dataset.id);
            const meta     = parseInt(input.value) || 0;
            promesas.push(
                ApiClient.post('plan_visitas.php', {
                    id_medico: idMedico,
                    mes:       mes,
                    meta_visitas: meta
                })
            );
        });

        try {
            await Promise.all(promesas);
            showToast(`Plan de ${formatMes(mes)} guardado ✓`);
            cargar();
        } catch {
            showToast('Error al guardar plan', 'error');
        }
    }

    function iniciales(nombre) {
        return nombre.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase();
    }

    function formatMes(mesStr) {
        if (!mesStr) return '';
        const [y, m] = mesStr.split('-');
        const meses  = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        return `${meses[parseInt(m)-1]} ${y}`;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const filtroMes = document.getElementById('plan-filtro-mes');
        if (filtroMes) {
            filtroMes.value = fechaActualMes();
            filtroMes.addEventListener('change', cargar);
        }

        const btnGuardar = document.getElementById('btn-guardar-plan');
        if (btnGuardar) btnGuardar.addEventListener('click', guardar);

        const btnCancelarMedico = document.getElementById('btn-cancelar-medico');
        if (btnCancelarMedico) btnCancelarMedico.addEventListener('click', MedicosManager.cerrarModal);
    });

    return { cargar, guardar };
})();

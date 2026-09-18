/**
 * app.js — Navegación de tabs, inicialización global, toasts
 */

// ── Tab navigation ──────────────────────────────────────────────────────────
function openTab(name) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tablink').forEach(el => el.classList.remove('active'));

    const content = document.getElementById('tab-' + name);
    const btn     = document.querySelector(`.tablink[data-tab="${name}"]`);
    if (content) content.classList.add('active');
    if (btn)     btn.classList.add('active');

    // Carga lazy por tab
    if (name === 'resumen')   cargarResumen();
    if (name === 'medicos')   MedicosManager.cargar();
    if (name === 'mapa')      MapManager.init();
    if (name === 'reportes')  ChartsManager.init();
    if (name === 'visitas')   VisitasManager.cargarHistorial();
    if (name === 'plan')      PlanManager.cargar();
}

// ── Toast notifications ─────────────────────────────────────────────────────
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}</span>
        <span>${message}</span>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 350);
    }, 3500);
}

// ── KPI Resumen ─────────────────────────────────────────────────────────────
async function cargarResumen() {
    const mes = document.getElementById('filtro-mes-resumen')?.value || fechaActualMes();
    try {
        const [eficiencia, cobertura] = await Promise.all([
            ApiClient.get(`reportes.php?tipo=eficiencia&mes=${mes}`),
            ApiClient.get(`reportes.php?tipo=cobertura&mes=${mes}`)
        ]);

        const visitados  = cobertura.filter(m => m.estado !== 'Sin visitar').length;
        const total      = cobertura.length || 1;
        const pctCob     = Math.round(visitados / total * 100);
        const efectivas  = parseInt(eficiencia.efectivas) || 0;
        const canceladas = parseInt(eficiencia.canceladas) || 0;

        animarContador('kpi-cobertura',    pctCob + '%');
        animarContador('kpi-visitas-mes',  eficiencia.total || 0);
        animarContador('kpi-duracion',     Math.round(eficiencia.duracion_promedio || 0) + ' min');
        animarContador('kpi-ratio',        efectivas + ' / ' + canceladas);
        animarContador('kpi-sin-visitar',  cobertura.filter(m => m.estado === 'Sin visitar').length);
        animarContador('kpi-cumplidos',    cobertura.filter(m => m.estado === 'Cumplido').length);

        // Mini lista médicos sin visitar
        const sinVisitar = cobertura.filter(m => m.estado === 'Sin visitar').slice(0, 6);
        const lista = document.getElementById('lista-sin-visitar');
        if (lista) {
            lista.innerHTML = sinVisitar.length
                ? sinVisitar.map(m => `<li><span class="badge badge-red">●</span> ${m.nombre} <small>${m.especialidad}</small></li>`).join('')
                : '<li class="text-muted">Todos los médicos han sido visitados este mes 🎉</li>';
        }

    } catch (err) {
        showToast('Error al cargar resumen', 'error');
        console.error(err);
    }
}

function animarContador(id, valor) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('kpi-updating');
    setTimeout(() => {
        el.textContent = valor;
        el.classList.remove('kpi-updating');
    }, 150);
}

function fechaActualMes() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

// ── Offline queue badge ──────────────────────────────────────────────────────
function actualizarBadgeOffline() {
    const q    = OfflineQueue.getAll();
    const badge = document.getElementById('offline-badge');
    if (!badge) return;
    if (q.length > 0) {
        badge.textContent = q.length;
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }
}

async function sincronizarPendientes() {
    const result = await OfflineQueue.flush();
    if (result.synced > 0) {
        showToast(`✓ ${result.synced} registro(s) sincronizado(s)`, 'success');
    }
    if (result.errors > 0) {
        showToast(`${result.errors} registro(s) sin poder sincronizar`, 'warning');
    }
    actualizarBadgeOffline();
}

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Tab buttons
    document.querySelectorAll('.tablink').forEach(btn => {
        btn.addEventListener('click', () => openTab(btn.dataset.tab));
    });

    // Mes filter en resumen
    const filtroMes = document.getElementById('filtro-mes-resumen');
    if (filtroMes) {
        filtroMes.value = fechaActualMes();
        filtroMes.addEventListener('change', cargarResumen);
    }

    // Botón sincronizar offline
    const btnSync = document.getElementById('btn-sync-offline');
    if (btnSync) btnSync.addEventListener('click', sincronizarPendientes);

    actualizarBadgeOffline();

    // Cargar tab inicial
    openTab('resumen');
});

// Carga y registro de visitas a farmacias
const FarmaciasManager=(()=>{async function cargar(){let fs=await ApiClient.get('farmacias.php');let s=document.getElementById('farm-vis-farmacia');if(s)s.innerHTML='<option value="">Seleccione farmacia</option>'+fs.map(f=>`<option value="${f.id_farmacia}">${f.nombre} — ${f.direccion}</option>`).join('');let list=await ApiClient.get('visitas_farmacias.php');let el=document.getElementById('farmacias-lista');if(el)el.innerHTML=list.map(v=>`<div class="list-row"><strong>${v.nombre_farmacia}</strong><span>${v.fecha}</span><span>${v.stock_productos||''}</span><span>${v.pedido_sugerido||''}</span><span class="text-muted">${v.observaciones||''}</span></div>`).join('')||'<div class="empty-state">No hay visitas a farmacias.</div>';}async function guardar(e){e.preventDefault();await ApiClient.post('visitas_farmacias.php',{id_farmacia:document.getElementById('farm-vis-farmacia').value,fecha:document.getElementById('farm-vis-fecha').value,stock_productos:document.getElementById('farm-vis-stock').value,pedido_sugerido:document.getElementById('farm-vis-pedido').value,observaciones:document.getElementById('farm-vis-observaciones').value});e.target.reset();cargar();showToast('Visita a farmacia registrada ✓');}document.addEventListener('DOMContentLoaded',()=>{document.getElementById('form-visita-farmacia')?.addEventListener('submit',guardar);document.querySelector('[data-tab="farmacias"]')?.addEventListener('click',cargar);});async function nueva(){let nombre=prompt('Nombre de la farmacia:');if(!nombre)return;let direccion=prompt('Dirección:');if(!direccion)return;let encargado=prompt('Encargado (opcional):')||'';let lat=prompt('Latitud (opcional, use 0 si no aplica):','0')||'0';let lng=prompt('Longitud (opcional, use 0 si no aplica):','0')||'0';await ApiClient.post('farmacias.php',{nombre,direccion,encargado,lat,lng});cargar();showToast('Farmacia creada ✓');}return{cargar,nueva};})();

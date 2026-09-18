/**
 * mapManager.js — Leaflet, pines por estado de cobertura
 */
const MapManager = (() => {

    let map = null;
    let markers = [];
    let initialized = false;
    let mes = null;

    const COLORES = {
        'Cumplido':    { color: '#10b981', label: 'Cumplido' },
        'Parcial':     { color: '#f59e0b', label: 'Parcial' },
        'Sin visitar': { color: '#ef4444', label: 'Sin visitar' }
    };

    // San Miguelito, Panamá
    const CENTER = [9.0333, -79.5000];
    const ZOOM   = 13;

    function init() {
        mes = document.getElementById('mapa-filtro-mes')?.value || fechaActualMes();

        if (!initialized) {
            map = L.map('mapa-container', { zoomControl: true }).setView(CENTER, ZOOM);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(map);

            initialized = true;
        }

        cargarMapa();
    }

    async function cargarMapa() {
        const spinner = document.getElementById('mapa-spinner');
        if (spinner) spinner.style.display = 'flex';

        try {
            const medicos = await ApiClient.get(`reportes.php?tipo=cobertura&mes=${mes}`);
            limpiarMarkers();
            renderLeyenda();

            let sinCoords = 0;
            medicos.forEach(m => {
                if (!m.lat || !m.lng) { sinCoords++; return; }

                const cfg = COLORES[m.estado] || COLORES['Sin visitar'];
                const icon = L.divIcon({
                    html: `<div class="map-pin" style="background:${cfg.color}"><span>${m.visitas_mes}</span></div>`,
                    className: '',
                    iconSize: [36, 36],
                    iconAnchor: [18, 36],
                    popupAnchor: [0, -36]
                });

                const marker = L.marker([parseFloat(m.lat), parseFloat(m.lng)], { icon })
                    .bindPopup(`
                        <div class="map-popup">
                            <strong>${m.nombre}</strong>
                            <span class="map-popup-esp">${m.especialidad}</span>
                            ${m.centro_salud ? `<span>🏥 ${m.centro_salud}</span>` : ''}
                            <span class="map-popup-estado" style="color:${cfg.color}">● ${m.estado}</span>
                            <span>Visitas: ${m.visitas_mes} / ${m.meta_mes} (meta)</span>
                        </div>
                    `)
                    .addTo(map);

                markers.push(marker);
            });

            const info = document.getElementById('mapa-info');
            if (info) {
                const cumplidos  = medicos.filter(m => m.estado === 'Cumplido').length;
                const parciales  = medicos.filter(m => m.estado === 'Parcial').length;
                const sinVisitar = medicos.filter(m => m.estado === 'Sin visitar').length;
                info.innerHTML = `
                    <span class="legend-dot" style="background:#10b981"></span>${cumplidos} Cumplidos &nbsp;
                    <span class="legend-dot" style="background:#f59e0b"></span>${parciales} Parciales &nbsp;
                    <span class="legend-dot" style="background:#ef4444"></span>${sinVisitar} Sin visitar &nbsp;
                    ${sinCoords ? `<span class="text-muted">(${sinCoords} sin coordenadas)</span>` : ''}
                `;
            }

            // Ajustar vista si hay markers
            if (markers.length > 0) {
                const group = L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }

        } catch (err) {
            showToast('Error al cargar mapa', 'error');
            console.error(err);
        } finally {
            if (spinner) spinner.style.display = 'none';
        }
    }

    function limpiarMarkers() {
        markers.forEach(m => map.removeLayer(m));
        markers = [];
    }

    function renderLeyenda() {
        const leyenda = document.getElementById('mapa-leyenda');
        if (!leyenda) return;
        leyenda.innerHTML = Object.entries(COLORES).map(([estado, cfg]) =>
            `<span><span class="legend-dot" style="background:${cfg.color}"></span>${estado}</span>`
        ).join('');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const filtroMes = document.getElementById('mapa-filtro-mes');
        if (filtroMes) {
            filtroMes.value = fechaActualMes();
            filtroMes.addEventListener('change', () => {
                mes = filtroMes.value;
                cargarMapa();
            });
        }
    });

    return { init, cargarMapa };
})();

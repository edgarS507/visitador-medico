/**
 * chartsManager.js — Chart.js: cobertura, frecuencia, ranking, prescripciones
 */
const ChartsManager = (() => {

    const charts = {};

    function destroyAll() {
        Object.values(charts).forEach(c => c && c.destroy());
        Object.keys(charts).forEach(k => delete charts[k]);
    }

    async function init() {
        destroyAll();
        const mes = document.getElementById('reportes-filtro-mes')?.value || fechaActualMes();

        await Promise.all([
            chartCobertura(mes),
            chartFrecuencia(),
            chartRanking(),
            chartPrescripciones()
        ]);
    }

    // ── Pie: Cobertura ───────────────────────────────────────────────────────
    async function chartCobertura(mes) {
        try {
            const data = await ApiClient.get(`reportes.php?tipo=cobertura&mes=${mes}`);
            const conteo = { Cumplido: 0, Parcial: 0, 'Sin visitar': 0 };
            data.forEach(m => conteo[m.estado] = (conteo[m.estado] || 0) + 1);

            const ctx = document.getElementById('chart-cobertura');
            if (!ctx) return;

            charts.cobertura = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(conteo),
                    datasets: [{
                        data: Object.values(conteo),
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#cbd5e1', padding: 16 } },
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    const total = data.length || 1;
                                    return ` ${ctx.label}: ${ctx.raw} (${Math.round(ctx.raw / total * 100)}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Número central
            const total = document.getElementById('cobertura-total');
            if (total) total.textContent = data.length + ' médicos';

        } catch (err) { console.error('chartCobertura', err); }
    }

    // ── Bar: Frecuencia de visitas ───────────────────────────────────────────
    async function chartFrecuencia() {
        try {
            const periodo = document.getElementById('frecuencia-periodo')?.value || 'mes';
            const data = await ApiClient.get(`reportes.php?tipo=frecuencia&periodo=${periodo}`);

            const ctx = document.getElementById('chart-frecuencia');
            if (!ctx) return;

            charts.frecuencia = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.periodo).reverse(),
                    datasets: [{
                        label: 'Visitas',
                        data: data.map(d => d.total).reverse(),
                        backgroundColor: 'rgba(99, 102, 241, 0.8)',
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' }, beginAtZero: true }
                    }
                }
            });
        } catch (err) { console.error('chartFrecuencia', err); }
    }

    // ── Horizontal bar: Ranking de productos ─────────────────────────────────
    async function chartRanking() {
        try {
            const data = await ApiClient.get('reportes.php?tipo=ranking_productos');
            const top  = data.slice(0, 10);

            const ctx = document.getElementById('chart-ranking');
            if (!ctx) return;

            charts.ranking = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: top.map(r => r.nombre),
                    datasets: [{
                        label: 'Visitas',
                        data: top.map(r => r.total_visitas),
                        backgroundColor: 'rgba(20, 184, 166, 0.8)',
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' }, beginAtZero: true },
                        y: { ticks: { color: '#94a3b8' }, grid: { display: false } }
                    }
                }
            });
        } catch (err) { console.error('chartRanking', err); }
    }

    // ── Line: Evolución de prescripciones ────────────────────────────────────
    async function chartPrescripciones() {
        try {
            const data = await ApiClient.get('reportes.php?tipo=prescripciones_evolucion');
            if (!data.length) return;

            // Agrupar por producto
            const meses     = [...new Set(data.map(d => d.mes))].sort();
            const productos  = [...new Set(data.map(d => d.producto))];
            const PALETTE   = ['#6366f1','#14b8a6','#f59e0b','#ef4444','#8b5cf6','#ec4899'];

            const datasets = productos.map((prod, i) => ({
                label: prod,
                data: meses.map(mes => {
                    const row = data.find(d => d.mes === mes && d.producto === prod);
                    return row ? parseFloat(row.total) : 0;
                }),
                borderColor: PALETTE[i % PALETTE.length],
                backgroundColor: PALETTE[i % PALETTE.length] + '22',
                fill: true,
                tension: 0.3,
                pointRadius: 4
            }));

            const ctx = document.getElementById('chart-prescripciones');
            if (!ctx) return;

            charts.prescripciones = new Chart(ctx, {
                type: 'line',
                data: { labels: meses, datasets },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom', labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' }, beginAtZero: true }
                    }
                }
            });
        } catch (err) { console.error('chartPrescripciones', err); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const filtroMes = document.getElementById('reportes-filtro-mes');
        if (filtroMes) filtroMes.value = fechaActualMes();

        const btnActualizar = document.getElementById('btn-actualizar-reportes');
        if (btnActualizar) btnActualizar.addEventListener('click', init);

        const periodoPicker = document.getElementById('frecuencia-periodo');
        if (periodoPicker) periodoPicker.addEventListener('change', init);
    });

    return { init };
})();

<?php
/**
 * Template para el Dashboard La Higuera
 *
 * Este archivo es incluido por el shortcode [dashboard_higuera]
 */

// Seguridad: evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="dashboard-higuera-wrapper" class="dashboard-higuera-container">
    <div id="dashboard-higuera-loading" style="position:relative;padding:60px 20px;text-align:center;font-family:sans-serif;color:#8b93a6;">
        <div style="display:inline-block;padding:20px 40px;background:#151822;border:1px solid #232738;border-radius:12px;">
            <div style="font-size:14px;margin-bottom:10px;">Cargando Dashboard...</div>
            <div style="width:200px;height:4px;background:#232738;border-radius:2px;overflow:hidden;">
                <div style="width:30%;height:100%;background:#60a5fa;animation:loading 1.5s ease-in-out infinite;"></div>
            </div>
        </div>
    </div>

    <div id="dashboard-higuera-content" style="display:none;">
        <header>
            <h1>Dashboard — Agrícola La Higuera</h1>
            <div class="subtitle">
                Temporada 2025–2026 · Fuente: <span class="badge" id="srcChip">Inicializando...</span>
                · <button id="btnSwap" class="link" type="button">Cambiar CSV</button>
                · <button id="btnSave" class="link" type="button">Guardar HTML con datos</button>
                · Generado: <span id="gen">—</span>
            </div>
        </header>

        <input id="fileSwap" type="file" accept=".csv,text/csv" style="display:none;">

        <div class="container">
            <div class="card">
                <div class="row">
                    <div class="field"><label>Predio</label><select id="f_predio"><option>Todos</option></select></div>
                    <div class="field"><label>Cultivo (SECTOR)</label><select id="f_cultivo"><option>Todos</option></select></div>
                    <div class="field"><label>Nivel 1</label><select id="f_n1"><option>Todos</option></select></div>
                    <div class="field"><label>Faena</label><select id="f_faena"><option>Todas</option></select></div>
                    <div class="field"><label>Métrica</label><select id="f_metrica"><option value="VALOR">Gasto total</option><option value="COSTO_HA">Costo por hectárea</option></select></div>
                    <div class="field">
                        <label>Meses</label>
                        <div id="f_mes" class="mes-dropdown">
                            <button type="button" class="mes-dropdown-btn"><span class="mes-label">Todos</span><span class="arrow">▼</span></button>
                            <div class="mes-dropdown-panel">
                                <div class="mes-dropdown-item todos"><input type="checkbox" id="mes_todos" checked><label for="mes_todos">Seleccionar todos</label></div>
                            </div>
                        </div>
                    </div>
                    <div class="field"><label>Orden</label><select id="f_orden"><option value="Desc">Descendente</option><option value="Asc">Ascendente</option></select></div>
                    <button id="btnToggleInv2425" type="button">Ocultar INVERSIONES VARIAS</button>
                </div>
            </div>

            <div class="tabs">
                <div class="tab active" data-tab="resumen">Resumen</div>
                <div class="tab" data-tab="graficos">Gráficos</div>
                <div class="tab" data-tab="comparativo">Comparativo 24-25 vs 25-26</div>
                <div class="tab" data-tab="detalle">Detalle</div>
            </div>

            <div id="panel-resumen" class="card">
                <div id="resumen-status" class="comp-status">
                    <div class="comp-status-header">
                        <button type="button" id="btnToggleDataStatus" class="button-link">Ocultar estado de datos</button>
                    </div>
                    <div class="comp-status-body"></div>
                </div>
                <div id="nota-resumen" class="note">Resumen por cuartel según filtros activos.</div>
                <div id="resumen" class="table-wrap"></div>
            </div>

            <div id="panel-graficos" class="card hidden">
                <div class="row" style="margin-bottom:12px;">
                    <label><input type="radio" name="gtype" value="barras" checked> Barras</label>
                    <label><input type="radio" name="gtype" value="pie"> Circular</label>
                </div>
                <div id="chart_bars"></div>
                <div id="chart_pie" class="hidden"></div>
            </div>

            <div id="panel-comparativo" class="card hidden">
                <div class="note">Comparativo de costos por cuartel entre las temporadas 24-25 y 25-26. Usa los filtros de arriba (Predio, Cultivo, Nivel 1, Faena, Métrica, Mes y Orden) para ajustar la vista.</div>
                <div id="comparativo-status" class="comp-status" aria-live="polite"></div>
                <div class="comp-toolbar">
                    <div class="comp-control">
                        <label class="comp-control-label" for="comparativo-orden-by">Ordenar por</label>
                        <select id="comparativo-orden-by">
                            <option value="v24t">24-25 total</option>
                            <option value="v24m">24-25 meses comparables</option>
                            <option value="v25" selected>25-26 actual</option>
                            <option value="diff">Diferencia (Δ)</option>
                            <option value="pct">Diferencia %</option>
                        </select>
                        <span class="comp-control-help">Usa el Orden (Asc/Desc) y Meses del header.</span>
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="tabla-comparativo">
                        <thead>
                            <tr>
                                <th>Cuartel</th>
                                <th>24-25 total<br><span class="col-subtitle">Temporada completa</span></th>
                                <th>24-25 meses comparables<br><span class="col-subtitle">Mismos meses 25-26</span></th>
                                <th>25-26 actual<br><span class="col-subtitle">Misma métrica</span></th>
                                <th>Δ absoluto<br><span class="col-subtitle">25-26 vs 24-25</span></th>
                                <th>Δ %<br><span class="col-subtitle">Variación</span></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot></tfoot>
                    </table>
                </div>
            </div>

            <div id="comparativo-detalle" class="card hidden">
                <div class="note" id="comparativo-detalle-titulo">Detalle por Nivel 1 —</div>
                <div class="table-wrap">
                    <table id="tabla-comparativo-detalle">
                        <thead>
                            <tr>
                                <th>Nivel 1</th>
                                <th>24-25</th>
                                <th>25-26</th>
                                <th>Diferencia</th>
                                <th>% Dif.</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot></tfoot>
                    </table>
                </div>
            </div>

            <div id="panel-detalle" class="card hidden">
                <div class="row">
                    <div class="field"><label>Nivel 1 (Detalle)</label><select id="d_n1"><option>Todos</option></select></div>
                    <div class="field"><label>Métrica (Detalle)</label><select id="d_metrica"><option value="VALOR">Gasto total</option><option value="COSTO_HA">Costo por hectárea</option></select></div>
                    <div class="field"><label>Orden cuarteles</label><select id="d_orden"><option value="Desc">Descendente</option><option value="Asc">Ascendente</option></select></div>
                    <button id="d_expand" type="button">Expandir todo</button>
                    <button id="d_collapse" type="button">Contraer todo</button>
                </div>
                <div id="detalle"></div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes loading {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(400%); }
}
</style>

<script>
// Inicializar dashboard cuando se cargue
(function() {
    async function initDashboard() {
        try {
            const loadingEl = document.getElementById('dashboard-higuera-loading');
            const contentEl = document.getElementById('dashboard-higuera-content');

            // Cargar CSVs externos
            if (typeof loadExternalCSVs === 'function') {
                await loadExternalCSVs();
            }

            // Inicializar dashboard
            if (typeof initDashboardLive === 'function') {
                await initDashboardLive();
            }

            // Ocultar loading, mostrar contenido
            if (loadingEl) loadingEl.style.display = 'none';
            if (contentEl) contentEl.style.display = 'block';

            // Actualizar timestamp
            const genEl = document.getElementById('gen');
            if (genEl) {
                genEl.textContent = new Date().toISOString().slice(0,16).replace('T',' ');
            }
        } catch (error) {
            console.error('Error inicializando dashboard:', error);
            const loadingEl = document.getElementById('dashboard-higuera-loading');
            if (loadingEl) {
                loadingEl.innerHTML = '<div style="color:#ef4444;">Error al cargar el dashboard. Por favor, recarga la página.</div>';
            }
        }
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }
})();
</script>

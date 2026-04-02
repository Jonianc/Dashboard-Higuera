<?php
/**
 * Template para el Dashboard La Higuera
 *
 * Este archivo es incluido por el shortcode [dashboard_higuera]
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="dashboard-higuera-wrapper" class="dashboard-higuera-container dlh-ui-reference" data-theme="light">
    <div id="dashboard-higuera-loading" class="dlh-loading">
        <div class="dlh-loading-card">
            <div class="dlh-loading-title">Cargando Dashboard...</div>
            <div class="dlh-loading-bar"><div class="dlh-loading-progress"></div></div>
        </div>
    </div>

    <div id="dashboard-higuera-content" style="display:none;">
        <header class="dlh-header">
            <div class="dlh-header-main">
                <h1>Dashboard — Agrícola La Higuera</h1>
                <div class="subtitle">
                    <span>Temporada 2025–2026</span>
                    <span>· Fuente: <span class="badge" id="srcChip">Inicializando...</span></span>
                    <span>· Generado: <span id="gen">—</span></span>
                </div>
            </div>
            <div class="dlh-header-actions">
                <button id="themeToggle" class="theme-toggle" type="button" aria-label="Cambiar entre modo claro y oscuro" aria-pressed="false">
                    <span class="theme-toggle-track"><span class="theme-toggle-knob"></span></span>
                    <span id="themeToggleText" class="theme-toggle-text">Claro</span>
                </button>
            </div>
        </header>

        <input id="fileSwap" type="file" accept=".csv,text/csv" style="display:none;">

        <div class="dashboard-layout dashboard-layout--premium">
            <main class="dashboard-main dashboard-main--premium">
                <section class="card dashboard-command dashboard-command--compact" aria-label="Controles principales del dashboard">
                    <div class="dashboard-command-top dashboard-command-top--stacked">
                        <div class="dashboard-command-copy">
                            <span class="dashboard-command-kicker">Workspace del dashboard</span>
                            <h2>Filtros unificados y navegación principal</h2>
                            <p>Usa una sola barra de control para cambiar la lectura del dashboard completo y mantener el contexto entre pestañas.</p>
                        </div>
                        <div class="dashboard-command-meta" aria-label="Estado rápido del dashboard">
                            <div class="dashboard-meta-card">
                                <span class="dashboard-meta-label">Fuente activa</span>
                                <strong id="dashboardMetaSource">Inicializando...</strong>
                            </div>
                            <div class="dashboard-meta-card">
                                <span class="dashboard-meta-label">Generado</span>
                                <strong id="dashboardMetaGenerated">—</strong>
                            </div>
                        </div>
                    </div>

                    <div class="filters-toolbar filters-toolbar--primary">
                        <div class="filters-toolbar-copy">
                            <span class="filters-toolbar-kicker">Filtros</span>
                            <p>Usa una sola caja de filtros para definir universo, métrica y orden de lectura.</p>
                        </div>
                        <div class="filters-master-grid filters-master-grid--primary">
                            <div class="field"><label for="f_cultivo">Cultivo</label><select id="f_cultivo"><option>Todos</option></select></div>
                            <div class="field"><label for="f_n1">Categoría</label><select id="f_n1"><option>Todos</option></select></div>
                            <div class="field"><label for="f_faena">Faena</label><select id="f_faena"><option>Todas</option></select></div>
                            <div class="field field-search"><label for="f_faena_search">Buscar faena</label><input id="f_faena_search" type="search" placeholder="Escribe para filtrar opciones"></div>
                            <div class="field field-meses field-meses--premium">
                                <label for="f_mes">Meses</label>
                                <div id="f_mes" class="mes-dropdown">
                                    <button type="button" class="mes-dropdown-btn" aria-haspopup="true" aria-expanded="false"><span class="mes-label">Todos</span><span class="arrow">▼</span></button>
                                    <div class="mes-dropdown-panel">
                                        <div class="mes-dropdown-item todos"><input type="checkbox" id="mes_todos" checked><label for="mes_todos">Seleccionar todos</label></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filters-master-grid filters-master-grid--secondary">
                            <div class="field"><label for="f_predio">Tipo de registro</label><select id="f_predio"><option>Todos</option></select></div>
                            <div class="field"><label for="f_metrica">Métrica</label><select id="f_metrica"><option value="VALOR">Gasto total</option><option value="COSTO_HA">Costo por hectárea</option></select></div>
                            <div class="field"><label for="f_orden">Orden</label><select id="f_orden"><option value="Desc">Descendente</option><option value="Asc">Ascendente</option></select></div>
                        </div>
                        <div class="filters-master-actions filters-master-actions--toolbar">
                            <button id="btnToggleInv2425" type="button">Ocultar INVERSIONES VARIAS</button>
                            <button id="btnClearFilters" type="button">Limpiar filtros</button>
                            <button id="btnResetView" type="button">Restablecer vista</button>
                        </div>
                    </div>
                </section>

                <section class="dashboard-overview" aria-label="Resumen rápido y navegación">
                    <section id="kpi-row" class="kpi-grid kpi-grid--premium"></section>
                    <section id="active-filters-chips" class="active-filters-chips active-filters-chips--premium"></section>
                </section>

                <section class="dashboard-view-nav card" aria-label="Vistas principales del dashboard">
                    <div class="dashboard-view-nav-head">
                        <div>
                            <span class="dashboard-command-kicker">Vistas</span>
                            <h2>Explora el dashboard por tipo de lectura</h2>
                        </div>
                        <p>Cambia entre resumen operativo, comparativo histórico, gráficos y detalle sin perder filtros.</p>
                    </div>
                    <div class="tabs tabs--elevated" role="tablist" aria-label="Vistas del dashboard">
                    <div id="tab-resumen" class="tab active" data-tab="resumen" role="tab" tabindex="0" aria-selected="true" aria-controls="panel-resumen">Resumen</div>
                    <div id="tab-comparativo" class="tab" data-tab="comparativo" role="tab" tabindex="-1" aria-selected="false" aria-controls="panel-comparativo">Comparativo 24-25 vs 25-26</div>
                    <div id="tab-graficos" class="tab" data-tab="graficos" role="tab" tabindex="-1" aria-selected="false" aria-controls="panel-graficos">Gráficos</div>
                    <div id="tab-detalle" class="tab" data-tab="detalle" role="tab" tabindex="-1" aria-selected="false" aria-controls="panel-detalle">Detalle</div>
                </div>
                </section>

                <div id="panel-resumen" class="card panel-card panel-resumen" role="tabpanel" aria-labelledby="tab-resumen">
                    <div class="panel-section-head panel-section-head--resumen">
                        <div>
                            <span class="dashboard-command-kicker">Vista principal</span>
                            <h2>Resumen general</h2>
                        </div>
                        <p>Lectura rápida por cuartel con una capa ejecutiva arriba y rentabilidad como apoyo contextual.</p>
                    </div>

                    <section class="resumen-hero" aria-label="Señales rápidas de resumen">
                        <div class="resumen-hero-copy">
                            <span class="dashboard-command-kicker">Contexto operativo</span>
                            <p id="nota-resumen" class="note note--hero">Resumen por cuartel según filtros activos.</p>
                        </div>
                        <div id="resumen-signal-cards" class="resumen-signal-cards"></div>
                    </section>

                    <div class="panel-resumen-shell">
                        <aside id="rentabilidad-resumen-block" class="rent-panel rent-panel--aside" aria-label="Rentabilidad resumida">
                            <div class="rent-panel-head rent-panel-head--compact">
                                <div>
                                    <span class="dashboard-command-kicker">Complemento</span>
                                    <h3>Rentabilidad</h3>
                                    <p>Bloque de apoyo para revisar ingresos, costos y resultado sin cortar la lectura del resumen.</p>
                                </div>
                            </div>
                            <div id="rentabilidadCollapseBody" class="rent-panel-body">
                                <div id="rentabilidad-cards" class="rentabilidad-cards"></div>
                                <div class="table-wrap rent-table-wrap">
                                    <div id="rentabilidad-resumen" class="rentabilidad-resumen-table"></div>
                                </div>
                            </div>
                        </aside>

                        <div class="panel-resumen-main">
                            <div id="resumen" class="table-wrap"></div>
                        </div>
                    </div>

                    <div id="resumen-status" class="comp-status comp-status--subtle">
                        <div class="comp-status-header">
                            <button type="button" id="btnToggleDataStatus" class="button-link">Ocultar estado de datos</button>
                        </div>
                        <div class="comp-status-body"></div>
                    </div>
                </div>

                <div id="panel-comparativo" class="card panel-card hidden" role="tabpanel" aria-labelledby="tab-comparativo">
                    <div class="panel-section-head">
                        <div>
                            <span class="dashboard-command-kicker">Comparativo</span>
                            <h2>24-25 vs 25-26</h2>
                        </div>
                        <p>Compara acumulados y meses equivalentes con el mismo contexto de filtros.</p>
                    </div>
                    <div class="note">Comparativo de costos por cuartel entre las temporadas 24-25 y 25-26. Usa los filtros para ajustar la vista.</div>
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
                            <span class="comp-control-help">Usa el Orden (Asc/Desc) y Meses del panel de filtros.</span>
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

                <div id="panel-graficos" class="card panel-card hidden" role="tabpanel" aria-labelledby="tab-graficos">
                    <div class="panel-section-head">
                        <div>
                            <span class="dashboard-command-kicker">Visualización</span>
                            <h2>Gráficos</h2>
                        </div>
                        <p>Alterna entre barras y circular para una lectura más visual.</p>
                    </div>
                    <div class="row" style="margin-bottom:12px;">
                        <label><input type="radio" name="gtype" value="barras" checked> Barras</label>
                        <label><input type="radio" name="gtype" value="pie"> Circular</label>
                    </div>
                    <div id="chart_bars"></div>
                    <div id="chart_pie" class="hidden"></div>
                </div>

                <div id="comparativo-detalle" class="card panel-card hidden">
                    <div class="note" id="comparativo-detalle-titulo">Detalle por categoría —</div>
                    <div class="table-wrap">
                        <table id="tabla-comparativo-detalle">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
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

                <div id="panel-detalle" class="card panel-card hidden" role="tabpanel" aria-labelledby="tab-detalle">
                    <div class="panel-section-head">
                        <div>
                            <span class="dashboard-command-kicker">Desglose</span>
                            <h2>Detalle</h2>
                        </div>
                        <p>Profundiza por categoría, faena y período manteniendo la misma selección global.</p>
                    </div>
                    <div class="row detail-toolbar">
                        <div class="field"><label for="d_n1">Categoría (Detalle)</label><select id="d_n1"><option>Todos</option></select></div>
                        <div class="field"><label for="d_metrica">Métrica (Detalle)</label><select id="d_metrica"><option value="VALOR">Gasto total</option><option value="COSTO_HA">Costo por hectárea</option></select></div>
                        <div class="field"><label for="d_orden">Orden cuarteles</label><select id="d_orden"><option value="Desc">Descendente</option><option value="Asc">Ascendente</option></select></div>
                        <div class="toolbar-actions">
                            <button id="d_expand" type="button">Expandir todo</button>
                            <button id="d_collapse" type="button">Contraer todo</button>
                        </div>
                    </div>
                    <div id="detalle"></div>
                </div>
            </main>
        </div>
    </div>
</div>

<script>
(function() {
    async function initDashboard() {
        try {
            const loadingEl = document.getElementById('dashboard-higuera-loading');
            const contentEl = document.getElementById('dashboard-higuera-content');

            if (typeof loadExternalCSVs === 'function') {
                await loadExternalCSVs();
            }

            if (typeof initDashboardLive === 'function') {
                await initDashboardLive();
            }

            if (loadingEl) loadingEl.style.display = 'none';
            if (contentEl) contentEl.style.display = 'block';

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }
})();
</script>

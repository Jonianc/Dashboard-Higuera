(function($){
  var SETTINGS_SECTION_STORAGE_KEY = 'dlh_settings_active_section';

  function byId(id){ return document.getElementById(id); }

  function escapeHtml(value){
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function syncRoles(){
    var checks = document.querySelectorAll('input[name="dlh_roles_arr[]"]:checked');
    var vals = [];
    checks.forEach(function(c){ vals.push(c.value); });
    var hidden = byId('dlh_roles_hidden');
    if(hidden){ hidden.value = vals.join(','); }
  }

  function toggleRoles(){
    var sel = byId('dlh_access');
    var wrap = byId('dlh_roles_wrap');
    if(!sel || !wrap){ return; }
    var show = sel.value === 'role';
    wrap.style.display = show ? '' : 'none';
    var labelCell = wrap.parentElement ? wrap.parentElement.previousElementSibling : null;
    if(labelCell){ labelCell.style.display = show ? '' : 'none'; }
  }

  function validateApiUrl(){
    var input = byId('dlh_api_url');
    var feedback = byId('dlh-api-url-feedback');
    if(!input || !feedback){ return; }
    var value = String(input.value || '').trim();
    if(!value){
      feedback.textContent = 'Ingresa una URL para probar la carga por API.';
      feedback.className = 'description dlh-field-feedback is-warning';
      return;
    }
    try {
      new URL(value);
      feedback.textContent = 'URL válida.';
      feedback.className = 'description dlh-field-feedback is-ok';
    } catch (e) {
      feedback.textContent = 'URL inválida. Revisa el formato (https://...).';
      feedback.className = 'description dlh-field-feedback is-error';
    }
  }

  function extractPowerBiUrl(formula){
    var raw = String(formula || '').trim();
    var match = raw.match(/Web\.Contents\(\s*"([^"]+)"\s*\)/i);
    return match ? match[1].trim() : '';
  }

  function validatePowerBiFormula(){
    var input = byId('dlh_api_powerbi_formula');
    var feedback = byId('dlh-powerbi-feedback');
    if(!input || !feedback){ return; }
    var value = String(input.value || '').trim();
    if(!value){
      feedback.textContent = 'Opcional. Si la dejas vacía, no se probará fuente Power BI.';
      feedback.className = 'description dlh-field-feedback is-warning';
      return;
    }
    var url = extractPowerBiUrl(value);
    if(url){
      feedback.textContent = 'Fórmula válida. URL detectada: ' + url;
      feedback.className = 'description dlh-field-feedback is-ok';
      return;
    }
    feedback.textContent = 'No se detectó Web.Contents("..."). Revisa la fórmula.';
    feedback.className = 'description dlh-field-feedback is-error';
  }

  function toggleApiSourceFields(){
    var mode = byId('dlh_api_source_mode');
    var urlGroup = byId('dlh-api-url-group');
    var powerbiGroup = byId('dlh-powerbi-group');
    if(!mode || !urlGroup || !powerbiGroup){ return; }
    var usePowerBi = mode.value === 'powerbi';
    urlGroup.style.display = usePowerBi ? 'none' : '';
    powerbiGroup.style.display = usePowerBi ? '' : 'none';
  }

  function togglePasswordPortalFields(){
    var enabled = document.querySelector('input[name="dlh_password_portal_enabled"]');
    var rowPassword = byId('dlh_password_portal_password') ? byId('dlh_password_portal_password').closest('tr') : null;
    var rowTitle = document.querySelector('input[name="dlh_password_portal_title"]');
    rowTitle = rowTitle ? rowTitle.closest('tr') : null;
    var rowMessage = document.querySelector('input[name="dlh_password_portal_message"]');
    rowMessage = rowMessage ? rowMessage.closest('tr') : null;
    if(!enabled){ return; }
    var show = enabled.checked;
    [rowPassword,rowTitle,rowMessage].forEach(function(row){
      if(row){ row.style.display = show ? '' : 'none'; }
    });
  }

  function renderApiTestResults(payload){
    var out = byId('dlh-test-api-results');
    if(!out){ return; }
    if(!payload || !payload.results){
      out.innerHTML = '<p class="is-error">' + escapeHtml(dlhSettings.labels.errorExec) + '</p>';
      return;
    }

    var html = '<div class="dlh-test-title">Resultado test API</div>';
    html += '<ul class="dlh-test-list">';

    payload.results.forEach(function(item){
      html += '<li class="dlh-test-item">';
      html += '<div><strong>' + escapeHtml(item.label) + '</strong> ';
      html += '<span class="dlh-pill ' + (item.ok ? 'is-ok' : 'is-error') + '">' + (item.ok ? 'OK' : 'FAIL') + '</span></div>';
      html += '<div class="dlh-test-meta">' + escapeHtml(item.ms) + ' ms · filas: ' + escapeHtml(item.rows) + '</div>';
      html += '<code>' + escapeHtml(item.url || '—') + '</code>';
      html += '</li>';
    });

    html += '</ul>';
    html += payload.fastest
      ? '<p class="dlh-test-best"><strong>' + escapeHtml(dlhSettings.labels.best) + ':</strong> ' + escapeHtml(payload.fastest.label) + ' (' + escapeHtml(payload.fastest.ms) + ' ms)</p>'
      : '<p class="dlh-test-best">No se pudo determinar fuente más rápida.</p>';

    out.innerHTML = html;
  }

  function bindApiTest(){
    var btn = byId('dlh-test-api-sources');
    var out = byId('dlh-test-api-results');
    if(!btn || !out || !window.dlhSettings){ return; }

    btn.addEventListener('click', function(){
      out.style.display = 'block';
      out.innerHTML = '<p>' + escapeHtml(dlhSettings.labels.running) + '</p>';
      btn.disabled = true;
      btn.textContent = dlhSettings.labels.running;

      var fd = new FormData();
      fd.append('action', 'dlh_test_api_sources');
      fd.append('nonce', dlhSettings.nonce);

      fetch(dlhSettings.ajaxUrl, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if(!data || !data.success){
          out.innerHTML = '<p class="is-error">' + escapeHtml(dlhSettings.labels.errorExec) + '</p>';
          return;
        }
        renderApiTestResults(data.data);
      })
      .catch(function(){
        out.innerHTML = '<p class="is-error">' + escapeHtml(dlhSettings.labels.errorNetwork) + '</p>';
      })
      .finally(function(){
        btn.disabled = false;
        btn.textContent = dlhSettings.labels.runTest;
      });
    });
  }

  function buildSectionCards(){
    var form = document.querySelector('.dlh-settings-form');
    if(!form){ return; }
    var nodes = Array.prototype.slice.call(form.children);
    var submit = form.querySelector('.submit');
    var sections = [];
    var current = null;

    nodes.forEach(function(node){
      if(node.classList && node.classList.contains('submit')){ return; }
      if(node.tagName === 'H2'){
        current = document.createElement('section');
        current.className = 'dlh-settings-section';
        var slug = 'section-' + String(node.textContent || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-');
        current.id = slug;
        sections.push({ id: slug, label: String(node.textContent || '').trim() });
        form.insertBefore(current, node);
      }
      if(current){ current.appendChild(node); }
    });

    if(submit){
      var submitWrap = document.createElement('div');
      submitWrap.className = 'dlh-settings-submit';
      submit.parentNode.insertBefore(submitWrap, submit);
      submitWrap.appendChild(submit);
    }

    if(sections.length){
      var nav = document.createElement('nav');
      nav.className = 'dlh-settings-nav';
      nav.innerHTML = sections.map(function(item){
        return '<a href="#' + escapeHtml(item.id) + '">' + escapeHtml(item.label) + '</a>';
      }).join('');
      var hero = document.querySelector('.dlh-settings-page__hero');
      if(hero && hero.parentNode){ hero.parentNode.insertBefore(nav, hero.nextSibling); }
    }
  }

  function getCurrentSectionId(){
    var sections = document.querySelectorAll('.dlh-settings-form .dlh-settings-section[id]');
    if(!sections.length){ return ''; }
    var anchorOffset = 120;
    var lastPassedId = '';
    var firstUpcomingId = '';
    sections.forEach(function(section){
      var rect = section.getBoundingClientRect();
      if(rect.top <= anchorOffset){
        lastPassedId = section.id;
      } else if(!firstUpcomingId){
        firstUpcomingId = section.id;
      }
    });
    if(lastPassedId){ return lastPassedId; }
    if(firstUpcomingId){ return firstUpcomingId; }
    return sections[sections.length - 1].id || '';
  }

  function persistCurrentSection(){
    var sectionId = getCurrentSectionId();
    if(!sectionId){ return; }
    try {
      window.sessionStorage.setItem(SETTINGS_SECTION_STORAGE_KEY, sectionId);
    } catch (e) {}
  }

  function restorePersistedSection(){
    var hash = String(window.location.hash || '').replace(/^#/, '').trim();
    var targetId = hash;
    if(!targetId){
      try {
        targetId = String(window.sessionStorage.getItem(SETTINGS_SECTION_STORAGE_KEY) || '').trim();
      } catch (e) {
        targetId = '';
      }
    }
    if(!targetId){ return; }
    var target = document.getElementById(targetId);
    if(!target){ return; }
    window.requestAnimationFrame(function(){
      target.scrollIntoView({ behavior: 'auto', block: 'start' });
    });
  }

  function bindSectionPersistence(){
    var nav = document.querySelector('.dlh-settings-nav');
    if(nav){
      nav.addEventListener('click', function(e){
        var link = e.target.closest('a[href^="#"]');
        if(!link){ return; }
        var id = String(link.getAttribute('href') || '').replace(/^#/, '').trim();
        if(!id){ return; }
        try {
          window.sessionStorage.setItem(SETTINGS_SECTION_STORAGE_KEY, id);
        } catch (err) {}
      });
    }

    var form = document.querySelector('.dlh-settings-form');
    if(form){
      form.addEventListener('submit', persistCurrentSection);
    }

    window.addEventListener('beforeunload', persistCurrentSection);
  }

  function refreshRentOrderValues(){
    var rows = document.querySelectorAll('[data-rent-card-item]');
    rows.forEach(function(row, index){
      var value = (index + 1) * 10;
      var input = row.querySelector('[data-rent-order-input]');
      var badge = row.querySelector('[data-rent-order-badge]');
      if(input){ input.value = String(value); }
      if(badge){ badge.textContent = String(value); }
    });
  }

  function bindRentCardOrdering(){
    var wrap = document.querySelector('[data-rent-cards-admin]');
    if(!wrap){ return; }
    wrap.addEventListener('click', function(e){
      var btn = e.target.closest('[data-rent-move]');
      if(!btn){ return; }
      e.preventDefault();
      var item = btn.closest('[data-rent-card-item]');
      if(!item){ return; }
      if(btn.getAttribute('data-rent-move') === 'up'){
        var prev = item.previousElementSibling;
        if(prev && prev.hasAttribute('data-rent-card-item')){ wrap.insertBefore(item, prev); }
      } else {
        var next = item.nextElementSibling;
        if(next){ wrap.insertBefore(next, item); }
      }
      refreshRentOrderValues();
    });
    refreshRentOrderValues();
  }

  function sanitizeNumber(value){
    if(typeof value === 'number' && Number.isFinite(value)) return value;
    var raw = String(value == null ? '' : value).trim();
    if(!raw) return 0;
    raw = raw.replace(/[^0-9,\.\-]/g, '');
    var commas = (raw.match(/,/g) || []).length;
    var dots = (raw.match(/\./g) || []).length;
    if(commas && dots){
      if(raw.lastIndexOf(',') > raw.lastIndexOf('.')){
        raw = raw.replace(/\./g, '').replace(',', '.');
      } else {
        raw = raw.replace(/,/g, '');
      }
    } else if(commas > 1 && !dots){
      raw = raw.replace(/,/g, '');
    } else if(dots > 1 && !commas){
      raw = raw.replace(/\./g, '');
    } else if(commas === 1 && !dots){
      raw = raw.replace(',', '.');
    }
    var num = parseFloat(raw);
    return Number.isFinite(num) ? num : 0;
  }

  function fmtNumber(value, opts){
    var num = sanitizeNumber(value);
    var options = Object.assign({ maximumFractionDigits: 0, minimumFractionDigits: 0 }, opts || {});
    try {
      return new Intl.NumberFormat('es-CL', options).format(num);
    } catch (e) {
      var decimals = Number.isFinite(options.maximumFractionDigits) ? options.maximumFractionDigits : 0;
      return String(num.toFixed(decimals));
    }
  }

  function fmtInt(value){
    return fmtNumber(value, { maximumFractionDigits: 0 });
  }

  function fmtMoney(value){
    return '$' + fmtInt(value);
  }

  function fmtHas(value){
    var num = sanitizeNumber(value);
    var hasDecimals = Math.abs(num - Math.round(num)) > 0.0001;
    return fmtNumber(num, {
      minimumFractionDigits: hasDecimals ? 1 : 0,
      maximumFractionDigits: hasDecimals ? 2 : 0
    });
  }

  function normalizeKeyPart(value){
    var raw = String(value == null ? '' : value).trim().toUpperCase();
    if(!raw){ return ''; }
    if(typeof raw.normalize === 'function'){
      raw = raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return raw.replace(/\s+/g, ' ');
  }

  function buildCompoundKey(predio, sector, cuartel){
    return [normalizeKeyPart(predio), normalizeKeyPart(sector), normalizeKeyPart(cuartel)].join('|');
  }

  function buildManualRowsHidden(rows){
    return JSON.stringify((rows || []).map(function(row){
      return {
        compound_key: row.compound_key || row._rowKey || '',
        predio: row.predio || '',
        sector: row.sector || '',
        cuartel: row.cuartel || '',
        kilos_reales: sanitizeNumber(row.kilos_reales),
        ingreso_exportacion: sanitizeNumber(row.ingreso_exportacion),
        ingreso_mercado_nacional: sanitizeNumber(row.ingreso_mercado_nacional),
        otras_ventas_dte: sanitizeNumber(row.otras_ventas_dte),
        otro_ingreso: sanitizeNumber(row.otro_ingreso)
      };
    }));
  }

  function createToast(message, type){
    var toast = document.createElement('div');
    toast.className = 'dlh-toast dlh-toast--' + (type || 'success');
    toast.innerHTML = '<span>' + escapeHtml(message) + '</span>';
    document.body.appendChild(toast);
    window.requestAnimationFrame(function(){ toast.classList.add('is-visible'); });
    window.setTimeout(function(){
      toast.classList.remove('is-visible');
      window.setTimeout(function(){ if(toast.parentNode){ toast.parentNode.removeChild(toast); } }, 220);
    }, 2800);
  }


  function initRentManualBuilder(){
    var builder = document.querySelector('[data-dlh-rent-manual-builder]');
    if(!builder || !window.dlhSettings){ return null; }

    var table = builder.querySelector('[data-dlh-rent-table]');
    var hidden = builder.querySelector('[data-dlh-rent-hidden]');
    var status = builder.querySelector('[data-dlh-rent-status]');
    var stats = builder.querySelector('[data-dlh-rent-stats]');
    var refreshBtn = builder.querySelector('[data-dlh-rent-refresh]');
    var state = { rows: [], loading: false, source: '', savedMap: {}, hasSavedMap: false, filters: { search: '', status: 'all', order: 'cuartel', dirtyOnly: false } };

    function showStatus(message, type){
      if(!status){ return; }
      if(!message){
        status.style.display = 'none';
        status.className = 'dlh-rent-manual__status notice inline';
        status.innerHTML = '';
        return;
      }
      status.style.display = '';
      status.className = 'dlh-rent-manual__status notice inline notice-' + (type || 'info');
      status.innerHTML = '<p>' + escapeHtml(message) + '</p>';
    }

    function readHiddenRows(){
      if(!hidden || !hidden.value){ return []; }
      try {
        var parsed = JSON.parse(hidden.value);
        return Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        return [];
      }
    }

    function ensureRowKey(row, index){
      if(row._rowKey){ return row._rowKey; }
      var candidate = row.compound_key || buildCompoundKey(row.predio, row.sector, row.cuartel);
      if(!candidate || candidate === '||'){
        candidate = 'manual-temp-' + String(index) + '-' + String(Date.now()) + '-' + String(Math.floor(Math.random() * 100000));
      }
      row._rowKey = candidate;
      return candidate;
    }

    function normalizeRows(rows){
      return (Array.isArray(rows) ? rows : []).map(function(item, index){
        var row = Object.assign({}, item || {});
        row.predio = String(row.predio || '').trim();
        row.sector = String(row.sector || '').trim();
        row.cuartel = String(row.cuartel || '').trim();
        row.especie = String(row.especie || '').trim();
        row.variedad = String(row.variedad || '').trim();
        row.hectareas = sanitizeNumber(row.hectareas);
        row.total_costos = sanitizeNumber(row.total_costos);
        row.kilos_reales = sanitizeNumber(row.kilos_reales);
        row.ingreso_exportacion = sanitizeNumber(row.ingreso_exportacion);
        row.ingreso_mercado_nacional = sanitizeNumber(row.ingreso_mercado_nacional);
        row.otras_ventas_dte = sanitizeNumber(row.otras_ventas_dte);
        row.otro_ingreso = sanitizeNumber(row.otro_ingreso);
        row.is_manual_only = !!row.is_manual_only || (!row.hectareas && !row.total_costos && !row.especie && !row.variedad && (!row.compound_key || String(row.compound_key).indexOf('manual-temp-') === 0 || row.predio || row.sector || row.cuartel));
        row.compound_key = row.compound_key || buildCompoundKey(row.predio, row.sector, row.cuartel);
        ensureRowKey(row, index);
        return row;
      });
    }

    function getRowLookup(){
      var lookup = {};
      state.rows.forEach(function(row, index){
        ensureRowKey(row, index);
        var composed = buildCompoundKey(row.predio, row.sector, row.cuartel);
        if(composed && composed !== '||'){
          lookup[composed] = row;
        } else if(row.compound_key){
          lookup[row.compound_key] = row;
        } else {
          lookup[row._rowKey] = row;
        }
      });
      return lookup;
    }

    function rowSnapshot(row){
      return JSON.stringify({
        predio: String(row.predio || '').trim(),
        sector: String(row.sector || '').trim(),
        cuartel: String(row.cuartel || '').trim(),
        kilos_reales: sanitizeNumber(row.kilos_reales),
        ingreso_exportacion: sanitizeNumber(row.ingreso_exportacion),
        ingreso_mercado_nacional: sanitizeNumber(row.ingreso_mercado_nacional),
        otras_ventas_dte: sanitizeNumber(row.otras_ventas_dte),
        otro_ingreso: sanitizeNumber(row.otro_ingreso)
      });
    }

    function captureSavedMap(){
      state.savedMap = {};
      state.rows.forEach(function(row, index){
        var key = ensureRowKey(row, index);
        state.savedMap[key] = rowSnapshot(row);
      });
      state.hasSavedMap = true;
    }

    function decorateRow(row){
      var kilos = sanitizeNumber(row.kilos_reales);
      var ingresoExportacion = sanitizeNumber(row.ingreso_exportacion);
      var ingresoMercado = sanitizeNumber(row.ingreso_mercado_nacional);
      var otrasVentas = sanitizeNumber(row.otras_ventas_dte);
      var otroIngreso = sanitizeNumber(row.otro_ingreso);
      var hectareas = sanitizeNumber(row.hectareas);
      var costos = sanitizeNumber(row.total_costos);
      var totalIngresos = ingresoExportacion + ingresoMercado + otrasVentas + otroIngreso;
      var resultado = totalIngresos - costos;
      var hasAnyManual = kilos > 0 || ingresoExportacion > 0 || ingresoMercado > 0 || otrasVentas > 0 || otroIngreso > 0;
      var missingIds = row.is_manual_only && (!String(row.predio || '').trim() || !String(row.sector || '').trim() || !String(row.cuartel || '').trim());
      var missingCore = hasAnyManual && (kilos <= 0 || totalIngresos <= 0);
      var statusMeta = { state: 'empty', label: 'Vacía', helper: 'Fila sin carga manual todavía.' };

      if(row.is_manual_only && !hasAnyManual && missingIds){
        statusMeta = { state: 'new', label: 'Nueva', helper: 'Completa identificación y valores para usar esta fila.' };
      } else if(hasAnyManual && (missingIds || missingCore)){
        statusMeta = { state: 'incomplete', label: 'Incompleta', helper: 'Faltan kilos, ingresos o datos de identificación.' };
      } else if(hasAnyManual){
        statusMeta = { state: 'ready', label: 'Lista', helper: 'La fila ya aporta al cálculo de rentabilidad.' };
      }

      var key = ensureRowKey(row, 0);
      var hasSaved = state.hasSavedMap && Object.prototype.hasOwnProperty.call(state.savedMap, key);
      var isDirty = state.hasSavedMap
        ? (hasSaved ? state.savedMap[key] !== rowSnapshot(row) : (hasAnyManual || !!String(row.predio || row.sector || row.cuartel || '').trim()))
        : false;

      return Object.assign({}, row, {
        kilos_reales: kilos,
        ingreso_exportacion: ingresoExportacion,
        ingreso_mercado_nacional: ingresoMercado,
        otras_ventas_dte: otrasVentas,
        otro_ingreso: otroIngreso,
        hectareas: hectareas,
        total_costos: costos,
        total_ingresos: totalIngresos,
        resultado: resultado,
        costo_kilo: kilos > 0 ? (costos / kilos) : 0,
        ingreso_kilo: kilos > 0 ? (totalIngresos / kilos) : 0,
        costo_hectarea: hectareas > 0 ? (costos / hectareas) : 0,
        ingreso_hectarea: hectareas > 0 ? (totalIngresos / hectareas) : 0,
        status_meta: statusMeta,
        has_any_manual: hasAnyManual,
        is_dirty: isDirty
      });
    }

    function emitState(){
      var decorated = state.rows.map(decorateRow);
      var totals = decorated.reduce(function(acc, row){
        acc.total_costos += sanitizeNumber(row.total_costos);
        acc.total_kilos += sanitizeNumber(row.kilos_reales);
        acc.total_ingresos += sanitizeNumber(row.total_ingresos);
        acc.resultado += sanitizeNumber(row.resultado);
        if(row.status_meta.state === 'ready'){ acc.ready += 1; }
        if(row.status_meta.state === 'incomplete' || row.status_meta.state === 'new'){ acc.incomplete += 1; }
        if(row.is_dirty){ acc.dirty += 1; }
        return acc;
      }, { total_costos: 0, total_kilos: 0, total_ingresos: 0, resultado: 0, ready: 0, incomplete: 0, dirty: 0, total: decorated.length });
      builder.dispatchEvent(new CustomEvent('dlh-rent-state', { detail: { rows: decorated, totals: totals, source: state.source || '' } }));
    }

    function syncHidden(){
      if(hidden){ hidden.value = buildManualRowsHidden(state.rows); }
      emitState();
    }

    function matchesFilters(row){
      var filters = state.filters || {};
      var term = String(filters.search || '').trim().toLowerCase();
      if(term){
        var haystack = [row.predio, row.sector, row.cuartel].join(' ').toLowerCase();
        if(haystack.indexOf(term) === -1){ return false; }
      }
      if(filters.dirtyOnly && !row.is_dirty){ return false; }
      var statusFilter = String(filters.status || 'all');
      if(statusFilter !== 'all' && row.status_meta && row.status_meta.state !== statusFilter){ return false; }
      return true;
    }

    function sortRows(rows){
      var order = (state.filters && state.filters.order) || 'cuartel';
      var sorted = rows.slice();
      sorted.sort(function(a, b){
        var av;
        var bv;
        if(order === 'resultado'){
          av = sanitizeNumber(a.resultado);
          bv = sanitizeNumber(b.resultado);
          return bv - av;
        }
        if(order === 'kilos'){
          av = sanitizeNumber(a.kilos_reales);
          bv = sanitizeNumber(b.kilos_reales);
          return bv - av;
        }
        if(order === 'estado'){
          av = String((a.status_meta && a.status_meta.label) || '');
          bv = String((b.status_meta && b.status_meta.label) || '');
        } else {
          av = String(a.cuartel || '');
          bv = String(b.cuartel || '');
        }
        return av.localeCompare(bv, 'es', { sensitivity: 'base', numeric: true });
      });
      return sorted;
    }

    function renderToolbar(totalRows, visibleRows){
      return ''
        + '<div class="dlh-rent-cards__toolbar">'
        + '  <label class="dlh-rent-field dlh-rent-field--search"><span>Buscar</span><input type="search" class="regular-text" placeholder="Predio, sector o cuartel" value="' + escapeHtml(state.filters.search || '') + '" data-rent-filter-search /></label>'
        + '  <label class="dlh-rent-field"><span>Estado</span><select data-rent-filter-status><option value="all"' + ((state.filters.status === 'all') ? ' selected' : '') + '>Todas</option><option value="new"' + ((state.filters.status === 'new') ? ' selected' : '') + '>Nuevas</option><option value="incomplete"' + ((state.filters.status === 'incomplete') ? ' selected' : '') + '>Incompletas</option><option value="ready"' + ((state.filters.status === 'ready') ? ' selected' : '') + '>Listas</option><option value="empty"' + ((state.filters.status === 'empty') ? ' selected' : '') + '>Vacías</option></select></label>'
        + '  <label class="dlh-rent-field"><span>Orden</span><select data-rent-filter-order><option value="cuartel"' + ((state.filters.order === 'cuartel') ? ' selected' : '') + '>Cuartel</option><option value="resultado"' + ((state.filters.order === 'resultado') ? ' selected' : '') + '>Resultado</option><option value="kilos"' + ((state.filters.order === 'kilos') ? ' selected' : '') + '>Kilos</option><option value="estado"' + ((state.filters.order === 'estado') ? ' selected' : '') + '>Estado</option></select></label>'
        + '  <label class="dlh-rent-check"><input type="checkbox" data-rent-filter-dirty ' + (state.filters.dirtyOnly ? 'checked' : '') + ' /><span>Solo con cambios</span></label>'
        + '  <button type="button" class="button button-secondary" data-rent-filter-reset>Limpiar filtros</button>'
        + '  <div class="dlh-rent-cards__counter">Mostrando ' + escapeHtml(String(visibleRows)) + ' de ' + escapeHtml(String(totalRows)) + '</div>'
        + '</div>';
    }

    function renderCard(row){
      var key = escapeHtml(row._rowKey || row.compound_key || '');
      var cardClass = 'dlh-rent-card is-' + row.status_meta.state + (row.is_dirty ? ' is-dirty' : '');
      var title = row.is_manual_only
        ? '<input type="text" class="regular-text dlh-rent-manual__input dlh-rent-manual__input--text dlh-rent-card__title-input" data-rent-text-field="cuartel" data-rent-row-key="' + key + '" placeholder="Cuartel" value="' + escapeHtml(row.cuartel || '') + '" />'
        : '<h4>' + escapeHtml(row.cuartel || 'Sin cuartel') + '</h4>';
      var predioField = row.is_manual_only
        ? '<input type="text" class="regular-text dlh-rent-manual__input dlh-rent-manual__input--text" data-rent-text-field="predio" data-rent-row-key="' + key + '" placeholder="Predio" value="' + escapeHtml(row.predio || '') + '" />'
        : '<strong>' + escapeHtml(row.predio || '—') + '</strong>';
      var sectorField = row.is_manual_only
        ? '<input type="text" class="regular-text dlh-rent-manual__input dlh-rent-manual__input--text" data-rent-text-field="sector" data-rent-row-key="' + key + '" placeholder="Sector" value="' + escapeHtml(row.sector || '') + '" />'
        : '<strong>' + escapeHtml(row.sector || '—') + '</strong>';
      var identityLine = row.is_manual_only
        ? '<div class="dlh-rent-card__identity-inline is-manual"><div class="dlh-rent-card__identity-input">' + predioField + '</div><div class="dlh-rent-card__identity-input">' + sectorField + '</div></div>'
        : '<div class="dlh-rent-card__identity-inline"><span>' + escapeHtml(row.predio || '—') + '</span><span>•</span><span>' + escapeHtml(row.sector || '—') + '</span></div>';
      var dirtyBadge = row.is_dirty ? '<span class="dlh-rent-status-chip is-dirty">Editada</span>' : '';
      var removeAction = row.is_manual_only ? '<button type="button" class="button button-link-delete" data-rent-row-action="delete" data-rent-row-key="' + key + '">Eliminar</button>' : '';
      var moreIncome = sanitizeNumber(row.otras_ventas_dte || 0);
      var moreIncomeBadge = moreIncome
        ? '<div class="dlh-rent-card__minor-note">DTE adicional: <strong>' + escapeHtml(fmtMoney(moreIncome)) + '</strong></div>'
        : '';
      return ''
        + '<article class="' + cardClass + '">'
        + '  <div class="dlh-rent-card__head">'
        + '    <div class="dlh-rent-card__title">' + title + '<div class="dlh-rent-card__meta"><span>' + escapeHtml(row.is_manual_only ? 'Fila manual' : 'Catálogo API/CSV') + '</span></div>' + identityLine + '</div>'
        + '    <div class="dlh-rent-card__badges"><span class="dlh-rent-status-chip is-' + escapeHtml(row.status_meta.state) + '">' + escapeHtml(row.status_meta.label) + '</span>' + dirtyBadge + '</div>'
        + '  </div>'
        + '  <div class="dlh-rent-card__topline">'
        + '    <div class="dlh-rent-card__mini"><span>Has</span><strong>' + escapeHtml(fmtHas(row.hectareas)) + '</strong></div>'
        + '    <div class="dlh-rent-card__mini"><span>Costos API</span><strong>' + escapeHtml(fmtMoney(row.total_costos)) + '</strong></div>'
        + '  </div>'
        + '  <div class="dlh-rent-card__grid">'
        + '    <div class="dlh-rent-card__section">'
        + '      <div class="dlh-rent-card__section-head"><strong>Datos editables</strong><small>Campos esenciales</small></div>'
        + '      <div class="dlh-rent-card__inputs is-compact">'
        + '        <label class="dlh-rent-card__input"><span>Kilos</span><input type="text" inputmode="decimal" class="regular-text dlh-rent-manual__input dlh-rent-manual__input--number" data-rent-field="kilos_reales" data-rent-row-key="' + key + '" placeholder="0" value="' + escapeHtml(row.kilos_reales ? String(row.kilos_reales) : '') + '" /></label>'
        + '        <label class="dlh-rent-card__input"><span>Ing. exportación</span><input type="text" inputmode="decimal" class="regular-text dlh-rent-manual__input dlh-rent-manual__input--number" data-rent-field="ingreso_exportacion" data-rent-row-key="' + key + '" placeholder="0" value="' + escapeHtml(row.ingreso_exportacion ? String(row.ingreso_exportacion) : '') + '" /></label>'
        + '        <label class="dlh-rent-card__input"><span>Ing. mercado nac.</span><input type="text" inputmode="decimal" class="regular-text dlh-rent-manual__input dlh-rent-manual__input--number" data-rent-field="ingreso_mercado_nacional" data-rent-row-key="' + key + '" placeholder="0" value="' + escapeHtml(row.ingreso_mercado_nacional ? String(row.ingreso_mercado_nacional) : '') + '" /></label>'
        + '        <label class="dlh-rent-card__input"><span>Otro ingreso</span><input type="text" inputmode="decimal" class="regular-text dlh-rent-manual__input dlh-rent-manual__input--number" data-rent-field="otro_ingreso" data-rent-row-key="' + key + '" placeholder="0" value="' + escapeHtml(row.otro_ingreso ? String(row.otro_ingreso) : '') + '" /></label>'
        + '      </div>' + moreIncomeBadge
        + '    </div>'
        + '    <div class="dlh-rent-card__section">'
        + '      <div class="dlh-rent-card__section-head"><strong>Cálculos</strong><small>Resumen clave</small></div>'
        + '      <div class="dlh-rent-card__metrics is-compact">'
        + '        <div class="dlh-rent-card__metric is-primary"><span>Resultado</span><strong>' + escapeHtml(fmtMoney(row.resultado)) + '</strong></div>'
        + '        <div class="dlh-rent-card__metric is-highlight"><span>Total ingresos</span><strong>' + escapeHtml(fmtMoney(row.total_ingresos)) + '</strong></div>'
        + '        <div class="dlh-rent-card__metric"><span>Ing/kg</span><strong>' + escapeHtml(fmtMoney(row.ingreso_kilo)) + '</strong></div>'
        + '        <div class="dlh-rent-card__metric"><span>Costo/kg</span><strong>' + escapeHtml(fmtMoney(row.costo_kilo)) + '</strong></div>'
        + '      </div>'
        + '    </div>'
        + '  </div>'
        + '  <div class="dlh-rent-card__foot">'
        + '    <div class="dlh-rent-card__actions"><button type="button" class="button button-secondary" data-rent-row-action="restore" data-rent-row-key="' + key + '">Restaurar</button><button type="button" class="button button-secondary" data-rent-row-action="clear" data-rent-row-key="' + key + '">Limpiar</button>' + removeAction + '</div>'
        + '  </div>'
        + '</article>';
    }

    function render(){
      if(!table){ return; }
      var rows = state.rows.map(decorateRow);
      var manualCount = rows.filter(function(row){ return row.has_any_manual; }).length;
      var incompleteCount = rows.filter(function(row){ return row.status_meta.state === 'incomplete' || row.status_meta.state === 'new'; }).length;
      var dirtyCount = rows.filter(function(row){ return row.is_dirty; }).length;
      var totalCostos = rows.reduce(function(total, row){ return total + sanitizeNumber(row.total_costos); }, 0);

      if(stats){
        stats.innerHTML = ''
          + '<span><strong>' + escapeHtml(String(rows.length)) + '</strong> filas</span>'
          + '<span><strong>' + escapeHtml(String(manualCount)) + '</strong> con carga</span>'
          + '<span><strong>' + escapeHtml(String(incompleteCount)) + '</strong> por revisar</span>'
          + '<span class="is-highlight"><strong>' + escapeHtml(fmtMoney(totalCostos)) + '</strong> costos API</span>'
          + '<span class="' + (dirtyCount ? 'is-dirty' : '') + '"><strong>' + escapeHtml(String(dirtyCount)) + '</strong> editadas</span>';
      }

      if(!rows.length){
        table.innerHTML = '<div class="dlh-rent-manual__empty">' + escapeHtml(dlhSettings.labels.catalogEmpty || 'Sin filas disponibles.') + '</div>';
        syncHidden();
        return;
      }

      var filteredRows = sortRows(rows.filter(matchesFilters));
      table.innerHTML = ''
        + '<div class="dlh-rent-manual__table-head dlh-rent-manual__table-head--cards">'
        + '  <div>'
        + '    <strong>Carga por cuartel</strong>'
        + '    <p>Ahora editas cada cuartel como ficha completa para aprovechar mejor el ancho y revisar el cálculo sin scroll horizontal.</p>'
        + '  </div>'
        + '  <div class="dlh-rent-manual__table-legend">'
        + '    <span class="is-ident">Identificación</span>'
        + '    <span class="is-edit">Editable</span>'
        + '    <span class="is-calc">Automático</span>'
        + '  </div>'
        + '</div>'
        + renderToolbar(rows.length, filteredRows.length)
        + (filteredRows.length ? '<div class="dlh-rent-cards">' + filteredRows.map(renderCard).join('') + '</div>' : '<div class="dlh-rent-manual__empty">No hay cuarteles que coincidan con los filtros actuales.</div>');
      syncHidden();
    }

    function mergeRows(baseRows){
      var incoming = normalizeRows(baseRows);
      var localLookup = getRowLookup();
      var used = {};
      var merged = incoming.map(function(row, index){
        ensureRowKey(row, index);
        var currentKey = buildCompoundKey(row.predio, row.sector, row.cuartel) || row.compound_key || row._rowKey;
        var local = localLookup[currentKey];
        if(local){
          used[local._rowKey] = true;
          return normalizeRows([Object.assign({}, row, {
            kilos_reales: local.kilos_reales,
            ingreso_exportacion: local.ingreso_exportacion,
            ingreso_mercado_nacional: local.ingreso_mercado_nacional,
            otras_ventas_dte: local.otras_ventas_dte,
            otro_ingreso: local.otro_ingreso,
            is_manual_only: false,
            _rowKey: local._rowKey,
            compound_key: currentKey
          })])[0];
        }
        row.compound_key = currentKey;
        row.is_manual_only = false;
        return row;
      });

      state.rows.forEach(function(row, index){
        ensureRowKey(row, index);
        if(used[row._rowKey]){ return; }
        var computedKey = buildCompoundKey(row.predio, row.sector, row.cuartel);
        var existsInIncoming = incoming.some(function(incomingRow){
          var incomingKey = buildCompoundKey(incomingRow.predio, incomingRow.sector, incomingRow.cuartel) || incomingRow.compound_key;
          return computedKey && incomingKey === computedKey;
        });
        if(!existsInIncoming){
          var manualRow = normalizeRows([Object.assign({}, row, { is_manual_only: true })])[0];
          merged.push(manualRow);
        }
      });

      return merged;
    }

    function loadCatalog(force){
      if(state.loading){ return; }
      state.loading = true;
      showStatus(dlhSettings.labels.loadingCatalog || 'Cargando catálogo…', 'info');
      if(refreshBtn){ refreshBtn.disabled = true; }
      var fd = new FormData();
      fd.append('action', 'dlh_fetch_rentabilidad_catalog');
      fd.append('nonce', dlhSettings.rentNonce);
      if(force){ fd.append('force', '1'); }
      fetch(dlhSettings.ajaxUrl, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if(!data || !data.success || !data.data){
          throw new Error((data && data.data && data.data.message) ? data.data.message : (dlhSettings.labels.catalogError || 'Error de catálogo'));
        }
        state.rows = mergeRows(Array.isArray(data.data.rows) ? data.data.rows : []);
        state.source = data.data.source || '';
        captureSavedMap();
        render();
        var sourceLabel = state.source === 'api' ? 'API 25-26' : (state.source === 'csv_fallback' ? 'CSV fallback 25-26' : 'sin fuente');
        showStatus('Catálogo cargado desde ' + sourceLabel + '. Filas disponibles: ' + String(state.rows.length) + '.', 'success');
      })
      .catch(function(err){
        var fallback = readHiddenRows();
        state.rows = normalizeRows(fallback.length ? fallback : (Array.isArray(dlhSettings.manualRows) ? dlhSettings.manualRows : []));
        captureSavedMap();
        render();
        showStatus((dlhSettings.labels.catalogError || 'No se pudo cargar el catálogo.') + ' ' + (err && err.message ? err.message : ''), 'warning');
      })
      .finally(function(){
        state.loading = false;
        if(refreshBtn){ refreshBtn.disabled = false; }
      });
    }

    function restoreFocus(rowKey, attrName, attrValue){
      window.requestAnimationFrame(function(){
        var candidates = table ? table.querySelectorAll('[' + attrName + ']') : [];
        Array.prototype.some.call(candidates, function(node){
          if(node.getAttribute('data-rent-row-key') === rowKey && node.getAttribute(attrName) === attrValue){
            node.focus();
            var length = String(node.value || '').length;
            if(typeof node.setSelectionRange === 'function'){
              node.setSelectionRange(length, length);
            }
            return true;
          }
          return false;
        });
      });
    }

    function updateNumericField(rowKey, field, value){
      state.rows = state.rows.map(function(row){
        if(ensureRowKey(row, 0) !== rowKey){ return row; }
        var next = Object.assign({}, row);
        next[field] = sanitizeNumber(value);
        return next;
      });
      render();
      restoreFocus(rowKey, 'data-rent-field', field);
    }

    function updateTextField(rowKey, field, value){
      state.rows = state.rows.map(function(row){
        if(ensureRowKey(row, 0) !== rowKey){ return row; }
        var next = Object.assign({}, row);
        next[field] = String(value || '');
        next.compound_key = buildCompoundKey(next.predio, next.sector, next.cuartel) || next.compound_key || next._rowKey;
        return next;
      });
      render();
      restoreFocus(rowKey, 'data-rent-text-field', field);
    }

    function restoreRow(rowKey){
      if(!state.hasSavedMap || !Object.prototype.hasOwnProperty.call(state.savedMap, rowKey)){ return; }
      var snapshot = null;
      try { snapshot = JSON.parse(state.savedMap[rowKey]); } catch (e) { snapshot = null; }
      if(!snapshot){ return; }
      state.rows = state.rows.map(function(row){
        if(ensureRowKey(row, 0) !== rowKey){ return row; }
        return normalizeRows([Object.assign({}, row, snapshot, { is_manual_only: !!row.is_manual_only, compound_key: buildCompoundKey(snapshot.predio, snapshot.sector, snapshot.cuartel) || row.compound_key || row._rowKey })])[0];
      });
      render();
      createToast('Fila restaurada.', 'success');
    }

    function clearRow(rowKey){
      state.rows = state.rows.map(function(row){
        if(ensureRowKey(row, 0) !== rowKey){ return row; }
        var next = Object.assign({}, row, { kilos_reales: 0, ingreso_exportacion: 0, ingreso_mercado_nacional: 0, otras_ventas_dte: 0, otro_ingreso: 0 });
        if(next.is_manual_only){
          next.predio = '';
          next.sector = '';
          next.cuartel = '';
          next.compound_key = next._rowKey;
        }
        return next;
      });
      render();
      createToast('Fila limpiada.', 'success');
    }

    function deleteRow(rowKey){
      var before = state.rows.length;
      state.rows = state.rows.filter(function(row){
        return ensureRowKey(row, 0) !== rowKey;
      });
      if(state.rows.length !== before){
        render();
        createToast('Fila manual eliminada.', 'success');
      }
    }

    function focusNextInput(current, backwards){
      if(!table){ return; }
      var inputs = Array.prototype.slice.call(table.querySelectorAll('input[data-rent-field], input[data-rent-text-field]')).filter(function(node){
        return node.offsetParent !== null;
      });
      var idx = inputs.indexOf(current);
      if(idx === -1){ return; }
      var next = inputs[idx + (backwards ? -1 : 1)];
      if(next){
        next.focus();
        if(typeof next.select === 'function'){ next.select(); }
      }
    }

    function addRow(){
      var row = normalizeRows([{
        compound_key: '',
        predio: '',
        sector: '',
        cuartel: '',
        hectareas: 0,
        total_costos: 0,
        kilos_reales: 0,
        ingreso_exportacion: 0,
        ingreso_mercado_nacional: 0,
        otras_ventas_dte: 0,
        otro_ingreso: 0,
        is_manual_only: true
      }])[0];
      state.rows = state.rows.concat([row]);
      render();
      showStatus('Fila manual agregada. Completa Predio, Sector y Cuartel para dejarla operativa.', 'info');
      window.setTimeout(function(){
        var target = table.querySelector('[data-rent-row-key]');
        if(target && target.getAttribute('data-rent-row-key') !== row._rowKey){
          var candidates = table.querySelectorAll('[data-rent-row-key]');
          Array.prototype.some.call(candidates, function(node){
            if(node.getAttribute('data-rent-row-key') === row._rowKey){ target = node; return true; }
            return false;
          });
        }
        if(target){ target.focus(); }
      }, 0);
    }

    builder.addEventListener('input', function(e){
      var numeric = e.target.closest('[data-rent-field]');
      if(numeric){
        updateNumericField(numeric.getAttribute('data-rent-row-key') || '', numeric.getAttribute('data-rent-field') || '', numeric.value);
        return;
      }
      var textInput = e.target.closest('[data-rent-text-field]');
      if(textInput){
        updateTextField(textInput.getAttribute('data-rent-row-key') || '', textInput.getAttribute('data-rent-text-field') || '', textInput.value);
      }
    });

    builder.addEventListener('click', function(e){
      if(e.target.closest('[data-dlh-rent-refresh]')){
        e.preventDefault();
        loadCatalog(true);
        return;
      }
      var rowAction = e.target.closest('[data-rent-row-action]');
      if(rowAction){
        e.preventDefault();
        var action = rowAction.getAttribute('data-rent-row-action') || '';
        var rowKey = rowAction.getAttribute('data-rent-row-key') || '';
        if(action === 'restore'){ restoreRow(rowKey); }
        else if(action === 'clear'){ clearRow(rowKey); }
        else if(action === 'delete'){ deleteRow(rowKey); }
        return;
      }
      if(e.target.closest('[data-rent-filter-reset]')){
        e.preventDefault();
        state.filters = { search: '', status: 'all', order: 'cuartel', dirtyOnly: false };
        render();
      }
    });

    builder.addEventListener('change', function(e){
      var statusFilter = e.target.closest('[data-rent-filter-status]');
      if(statusFilter){ state.filters.status = statusFilter.value || 'all'; render(); return; }
      var orderFilter = e.target.closest('[data-rent-filter-order]');
      if(orderFilter){ state.filters.order = orderFilter.value || 'cuartel'; render(); return; }
      var dirtyFilter = e.target.closest('[data-rent-filter-dirty]');
      if(dirtyFilter){ state.filters.dirtyOnly = !!dirtyFilter.checked; render(); }
    });

    builder.addEventListener('input', function(e){
      var searchFilter = e.target.closest('[data-rent-filter-search]');
      if(searchFilter){
        state.filters.search = searchFilter.value || '';
        render();
        return;
      }
    });

    builder.addEventListener('focusin', function(e){
      var field = e.target.closest('input[data-rent-field], input[data-rent-text-field]');
      if(field && typeof field.select === 'function'){ field.select(); }
    });

    builder.addEventListener('keydown', function(e){
      var field = e.target.closest('input[data-rent-field], input[data-rent-text-field]');
      if(field && e.key === 'Enter'){
        e.preventDefault();
        focusNextInput(field, !!e.shiftKey);
      }
    });

    builder.addEventListener('dlh-rent-add-row', function(){ addRow(); });
    builder.addEventListener('dlh-rent-recalculate', function(){ loadCatalog(true); });

    state.rows = normalizeRows(readHiddenRows());
    if(!state.rows.length && Array.isArray(dlhSettings.manualRows)){
      state.rows = normalizeRows(dlhSettings.manualRows);
    }
    captureSavedMap();
    render();
    loadCatalog(false);

    return {
      element: builder,
      addRow: addRow,
      refresh: function(){ loadCatalog(true); }
    };
  }



  function initRentWorkspace(builderApi){
    var builder = builderApi && builderApi.element ? builderApi.element : document.querySelector('[data-dlh-rent-manual-builder]');
    if(!builder){ return; }
    var rentSection = builder.closest('.dlh-settings-section') || builder.closest('.dlh-settings-form');
    if(!rentSection || rentSection.hasAttribute('data-dlh-rent-workspace-ready')){ return; }
    rentSection.setAttribute('data-dlh-rent-workspace-ready', '1');

    var formTable = rentSection.querySelector('.form-table');
    var cardsWrap = rentSection.querySelector('[data-rent-cards-admin]');
    var diagnosticsWrap = rentSection.querySelector('[data-dlh-rent-diagnostics-root]');
    var iconUploaders = Array.prototype.slice.call(rentSection.querySelectorAll('.dlh-icon-uploader'));
    if(!formTable || !cardsWrap || !diagnosticsWrap){ return; }

    var cardsRow = cardsWrap.closest('tr');
    var manualRow = builder.closest('tr');
    var diagRow = diagnosticsWrap.closest('tr');
    var iconRows = iconUploaders.map(function(node){ return node.closest('tr'); }).filter(Boolean);
    var globalSubmit = document.querySelector('.dlh-settings-submit');

    var workspace = document.createElement('div');
    workspace.className = 'dlh-rent-workspace';

    var sticky = document.createElement('div');
    sticky.className = 'dlh-rent-workspace__sticky';
    sticky.innerHTML = ''
      + '<div class="dlh-rent-summary" data-dlh-rent-summary>'
      + '  <div class="dlh-rent-summary__intro">'
      + '    <span class="dlh-rent-summary__eyebrow">Rentabilidad</span>'
      + '    <h3>Panel operativo para cargar ingresos y revisar el cálculo</h3>'
      + '    <p>La API define cuarteles, hectáreas y costos. Aquí solo completas kilos e ingresos y ves el resultado al instante.</p>'
      + '  </div>'
      + '  <div class="dlh-rent-summary__metrics" data-dlh-rent-summary-metrics></div>'
      + '  <div class="dlh-rent-summary__actions">'
      + '    <button type="submit" class="button button-primary button-large">' + escapeHtml((dlhSettings.labels && dlhSettings.labels.saveChanges) || 'Guardar todo') + '</button>'
      + '    <button type="button" class="button button-secondary" data-dlh-rent-action="add-row">' + escapeHtml((dlhSettings.labels && dlhSettings.labels.addRow) || 'Agregar fila') + '</button>'
      + '    <button type="button" class="button button-secondary" data-dlh-rent-action="recalculate">' + escapeHtml((dlhSettings.labels && dlhSettings.labels.recalculate) || 'Recalcular') + '</button>'
      + '  </div>'
      + '</div>';

    var tabs = document.createElement('div');
    tabs.className = 'dlh-rent-tabs';
    tabs.innerHTML = ''
      + '<button type="button" class="dlh-rent-tab is-active" data-dlh-rent-tab="resumen">Resumen</button>'
      + '<button type="button" class="dlh-rent-tab" data-dlh-rent-tab="datos">Datos</button>'
      + '<button type="button" class="dlh-rent-tab" data-dlh-rent-tab="diagnostico">Diagnóstico</button>';

    var panels = document.createElement('div');
    panels.className = 'dlh-rent-panels';
    panels.innerHTML = ''
      + '<section class="dlh-rent-panel is-active" data-dlh-rent-panel="resumen"></section>'
      + '<section class="dlh-rent-panel" data-dlh-rent-panel="datos"></section>'
      + '<section class="dlh-rent-panel" data-dlh-rent-panel="diagnostico"></section>';

    workspace.appendChild(sticky);
    workspace.appendChild(tabs);
    workspace.appendChild(panels);
    formTable.parentNode.insertBefore(workspace, formTable);

    var summaryPanel = panels.querySelector('[data-dlh-rent-panel="resumen"]');
    var dataPanel = panels.querySelector('[data-dlh-rent-panel="datos"]');
    var diagPanel = panels.querySelector('[data-dlh-rent-panel="diagnostico"]');

    var cardsBlock = document.createElement('article');
    cardsBlock.className = 'dlh-rent-surface';
    cardsBlock.innerHTML = '<div class="dlh-rent-surface__head"><div><h3>Cards visibles en el resumen</h3><p>Ordena, renombra y activa las métricas que se muestran al usuario final.</p></div></div>';
    cardsBlock.appendChild(cardsWrap);
    summaryPanel.appendChild(cardsBlock);

    var iconsBlock = document.createElement('article');
    iconsBlock.className = 'dlh-rent-surface';
    iconsBlock.innerHTML = '<div class="dlh-rent-surface__head"><div><h3>Íconos rápidos</h3><p>Personaliza los chips de Sector y Cuartel que aparecen en rentabilidad.</p></div></div>';
    var iconsGrid = document.createElement('div');
    iconsGrid.className = 'dlh-rent-icons-grid';
    iconUploaders.forEach(function(item){ iconsGrid.appendChild(item); });
    iconsBlock.appendChild(iconsGrid);
    summaryPanel.appendChild(iconsBlock);

    var dataBlock = document.createElement('article');
    dataBlock.className = 'dlh-rent-surface dlh-rent-surface--data';
    dataBlock.innerHTML = '<div class="dlh-rent-surface__head dlh-rent-surface__head--data"><div><h3>Carga por cuartel</h3><p>Las primeras columnas quedan fijas para que no pierdas contexto al desplazarte. Al centro editas kilos e ingresos y a la derecha revisas los cálculos automáticos.</p></div></div>';
    dataBlock.appendChild(builder);
    dataPanel.appendChild(dataBlock);

    var diagBlock = document.createElement('article');
    diagBlock.className = 'dlh-rent-surface';
    diagBlock.appendChild(diagnosticsWrap);
    diagPanel.appendChild(diagBlock);

    [cardsRow, manualRow, diagRow].concat(iconRows).forEach(function(row){
      if(row){ row.style.display = 'none'; }
    });

    function switchTab(target){
      tabs.querySelectorAll('[data-dlh-rent-tab]').forEach(function(tab){
        tab.classList.toggle('is-active', tab.getAttribute('data-dlh-rent-tab') === target);
      });
      panels.querySelectorAll('[data-dlh-rent-panel]').forEach(function(panel){
        panel.classList.toggle('is-active', panel.getAttribute('data-dlh-rent-panel') === target);
      });
    }

    tabs.addEventListener('click', function(e){
      var btn = e.target.closest('[data-dlh-rent-tab]');
      if(!btn){ return; }
      switchTab(btn.getAttribute('data-dlh-rent-tab'));
    });

    sticky.addEventListener('click', function(e){
      var actionBtn = e.target.closest('[data-dlh-rent-action]');
      if(!actionBtn){ return; }
      e.preventDefault();
      var action = actionBtn.getAttribute('data-dlh-rent-action');
      if(action === 'add-row'){
        switchTab('datos');
        builder.dispatchEvent(new CustomEvent('dlh-rent-add-row'));
      } else if(action === 'recalculate'){
        switchTab('datos');
        builder.dispatchEvent(new CustomEvent('dlh-rent-recalculate'));
      }
    });

    var metricsBox = sticky.querySelector('[data-dlh-rent-summary-metrics]');
    var submitHint = null;
    if(globalSubmit){
      submitHint = globalSubmit.querySelector('.dlh-settings-submit__hint');
      if(!submitHint){
        submitHint = document.createElement('div');
        submitHint.className = 'dlh-settings-submit__hint';
        submitHint.textContent = 'Sin cambios pendientes.';
        globalSubmit.insertBefore(submitHint, globalSubmit.firstChild || null);
      }
    }

    builder.addEventListener('dlh-rent-state', function(evt){
      var detail = evt.detail || {};
      var totals = detail.totals || {};
      var sourceLabel = detail.source === 'api' ? 'API' : (detail.source === 'csv_fallback' ? 'CSV fallback' : 'Manual / cache');
      metricsBox.innerHTML = ''
        + '<article class="dlh-rent-kpi"><span>Total costos API</span><strong>' + escapeHtml(fmtMoney(totals.total_costos || 0)) + '</strong></article>'
        + '<article class="dlh-rent-kpi"><span>Total kilos</span><strong>' + escapeHtml(fmtInt(totals.total_kilos || 0)) + '</strong></article>'
        + '<article class="dlh-rent-kpi"><span>Total ingresos</span><strong>' + escapeHtml(fmtMoney(totals.total_ingresos || 0)) + '</strong></article>'
        + '<article class="dlh-rent-kpi"><span>Resultado total</span><strong>' + escapeHtml(fmtMoney(totals.resultado || 0)) + '</strong></article>'
        + '<article class="dlh-rent-kpi dlh-rent-kpi--meta is-meta"><span>Estado</span><strong>' + escapeHtml(String(totals.ready || 0)) + ' listas · ' + escapeHtml(String(totals.incomplete || 0)) + ' por revisar</strong><small>Fuente catálogo: ' + escapeHtml(sourceLabel) + '</small></article>';

      if(submitHint && globalSubmit){
        var dirty = parseInt(totals.dirty || 0, 10) || 0;
        globalSubmit.classList.toggle('has-dirty', dirty > 0);
        submitHint.textContent = dirty > 0
          ? dirty + ' fila' + (dirty === 1 ? '' : 's') + ' con cambios sin guardar.'
          : 'Sin cambios pendientes.';
      }
    });
  }


  function bindIconUploaders(){
    if(typeof wp === 'undefined' || !wp.media){ return; }
    document.querySelectorAll('[data-dlh-icon-uploader]').forEach(function(box){
      var input = box.querySelector('[data-dlh-icon-input]');
      var preview = box.querySelector('[data-dlh-icon-preview]');
      var url = box.querySelector('[data-dlh-icon-url]');
      var selectBtn = box.querySelector('[data-dlh-icon-select]');
      var removeBtn = box.querySelector('[data-dlh-icon-remove]');
      if(!input || !preview || !selectBtn){ return; }

      var frame = null;
      function setImage(src){
        input.value = src || '';
        preview.classList.toggle('has-image', !!src);
        preview.innerHTML = src ? '<img src="' + escapeHtml(src) + '" alt="" />' : '<span>Sin ícono</span>';
        if(url){ url.textContent = src || 'Sin URL seleccionada'; }
        if(removeBtn){ removeBtn.style.display = src ? '' : 'none'; }
        selectBtn.textContent = src ? dlhSettings.labels.replaceImage : dlhSettings.labels.selectImage;
      }

      selectBtn.addEventListener('click', function(e){
        e.preventDefault();
        if(frame){ frame.open(); return; }
        frame = wp.media({
          title: dlhSettings.labels.selectImage,
          button: { text: dlhSettings.labels.useThisImage },
          multiple: false,
          library: { type: 'image' }
        });
        frame.on('select', function(){
          var attachment = frame.state().get('selection').first().toJSON();
          setImage(attachment.url || '');
        });
        frame.open();
      });

      if(removeBtn){
        removeBtn.addEventListener('click', function(e){
          e.preventDefault();
          setImage('');
        });
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('input[name="dlh_roles_arr[]"]').forEach(function(c){
      c.addEventListener('change', syncRoles);
    });
    var access = byId('dlh_access');
    if(access){ access.addEventListener('change', toggleRoles); }

    var sourceMode = byId('dlh_api_source_mode');
    if(sourceMode){ sourceMode.addEventListener('change', toggleApiSourceFields); }

    var apiInput = byId('dlh_api_url');
    if(apiInput){ apiInput.addEventListener('input', validateApiUrl); }

    var powerBiInput = byId('dlh_api_powerbi_formula');
    if(powerBiInput){ powerBiInput.addEventListener('input', validatePowerBiFormula); }

    var portalEnabled = document.querySelector('input[name="dlh_password_portal_enabled"]');
    if(portalEnabled){ portalEnabled.addEventListener('change', togglePasswordPortalFields); }

    bindRentCardOrdering();
    bindIconUploaders();
    var builderApi = initRentManualBuilder();
    initRentWorkspace(builderApi);
    toggleRoles();
    toggleApiSourceFields();
    validateApiUrl();
    validatePowerBiFormula();
    togglePasswordPortalFields();
    bindApiTest();

    var savedRoot = document.querySelector('[data-dlh-settings-saved="1"]');
    if(savedRoot){
      createToast((dlhSettings.labels && dlhSettings.labels.saveToast) || 'Ajustes guardados.', 'success');
    }
  });
})(jQuery);

(function(){
  function byId(id){ return document.getElementById(id); }

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
      // eslint-disable-next-line no-new
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

  function escapeHtml(value){
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
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

    if(payload.fastest){
      html += '<p class="dlh-test-best"><strong>' + escapeHtml(dlhSettings.labels.best) + ':</strong> ' + escapeHtml(payload.fastest.label) + ' (' + escapeHtml(payload.fastest.ms) + ' ms)</p>';
    } else {
      html += '<p class="dlh-test-best">No se pudo determinar fuente más rápida.</p>';
    }

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

    toggleRoles();
    toggleApiSourceFields();
    validateApiUrl();
    validatePowerBiFormula();
    bindApiTest();
  });
})();

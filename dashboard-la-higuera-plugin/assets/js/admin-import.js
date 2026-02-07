/**
 * Dashboard La Higuera — Admin Import 24-25
 * Maneja el flujo de importación: drag-drop, validación, preview e import.
 */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
       Elementos del DOM
       --------------------------------------------------------------- */
    var dropzone        = document.getElementById('dlh-dropzone');
    var fileInput       = document.getElementById('dlh-file-input');
    var btnBrowse       = document.getElementById('dlh-btn-browse');
    var btnDownload     = document.getElementById('dlh-btn-download');
    var btnRemove       = document.getElementById('dlh-btn-remove');
    var btnImport       = document.getElementById('dlh-btn-import');
    var confirmCheck    = document.getElementById('dlh-confirm-check');
    var fileInfoBox     = document.getElementById('dlh-file-info');
    var fileNameEl      = document.getElementById('dlh-file-name');
    var fileSizeEl      = document.getElementById('dlh-file-size');
    var errorsBox       = document.getElementById('dlh-validation-errors');
    var step3           = document.getElementById('dlh-step3');
    var step4           = document.getElementById('dlh-step4');
    var previewStats    = document.getElementById('dlh-preview-stats');
    var progressBar     = document.getElementById('dlh-progress-bar');
    var progressFill    = document.getElementById('dlh-progress-fill');
    var resultBox       = document.getElementById('dlh-result');
    var resultErrors    = document.getElementById('dlh-result-errors');

    var selectedFile = null;
    var parsedPreview = null;

    /* ---------------------------------------------------------------
       Utilidades
       --------------------------------------------------------------- */
    function fmtSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function fmtNum(n) {
        if (n === null || n === undefined || isNaN(n)) return '—';
        return Math.round(n).toLocaleString('es-CL');
    }

    function normalizeHeader(s) {
        return String(s || '')
            .toUpperCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^\w\s()]/g, '')
            .trim();
    }

    function detectDelimiter(text) {
        var lines = text.split(/\r?\n/).slice(0, 30);
        var counts = { ';': 0, ',': 0, '\t': 0 };
        lines.forEach(function (l) {
            counts[';'] += (l.split(';').length - 1);
            counts[','] += (l.split(',').length - 1);
            counts['\t'] += (l.split('\t').length - 1);
        });
        var best = ';';
        if (counts[','] > counts[best]) best = ',';
        if (counts['\t'] > counts[best]) best = '\t';
        return best;
    }

    function parseCSVLine(line, delim) {
        var out = [], cur = '', q = false;
        for (var i = 0; i < line.length; i++) {
            var ch = line[i];
            if (ch === '"') { q = !q; continue; }
            if (!q && ch === delim) { out.push(cur.trim()); cur = ''; continue; }
            cur += ch;
        }
        out.push(cur.trim());
        return out;
    }

    function parseNumCL(s) {
        s = String(s || '').trim();
        if (!s) return 0;
        // "1.234.567,89" → 1234567.89
        if (s.indexOf(',') !== -1 && s.indexOf('.') !== -1) {
            s = s.replace(/\./g, '').replace(',', '.');
        } else if (s.indexOf(',') !== -1) {
            s = s.replace(',', '.');
        }
        var v = parseFloat(s);
        return isFinite(v) ? v : 0;
    }

    /* ---------------------------------------------------------------
       Paso 1: Descargar plantilla
       --------------------------------------------------------------- */
    if (btnDownload) {
        btnDownload.addEventListener('click', function () {
            var url = dlhImport.templateUrl;
            // Fetch con nonce y descargar como blob
            fetch(url, { headers: { 'X-WP-Nonce': dlhImport.nonce } })
                .then(function (resp) { return resp.text(); })
                .then(function (csv) {
                    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'temporada-2024-25.csv';
                    a.click();
                    setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
                })
                .catch(function () { alert('No se pudo descargar el archivo.'); });
        });
    }

    /* ---------------------------------------------------------------
       Paso 2: Selección de archivo (click + drag & drop)
       --------------------------------------------------------------- */
    if (btnBrowse) {
        btnBrowse.addEventListener('click', function (e) {
            e.stopPropagation();
            fileInput.click();
        });
    }

    if (dropzone) {
        dropzone.addEventListener('click', function () { fileInput.click(); });

        dropzone.addEventListener('dragenter', function (e) {
            e.preventDefault();
            dropzone.classList.add('dlh-dragover');
        });
        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropzone.classList.add('dlh-dragover');
        });
        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('dlh-dragover');
        });
        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropzone.classList.remove('dlh-dragover');
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files.length > 0) {
                handleFile(files[0]);
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                handleFile(fileInput.files[0]);
            }
        });
    }

    if (btnRemove) {
        btnRemove.addEventListener('click', function () {
            resetSelection();
        });
    }

    function resetSelection() {
        selectedFile = null;
        parsedPreview = null;
        fileInput.value = '';
        fileInfoBox.style.display = 'none';
        dropzone.style.display = '';
        errorsBox.style.display = 'none';
        errorsBox.innerHTML = '';
        step3.style.display = 'none';
        step4.style.display = 'none';
        confirmCheck.checked = false;
        btnImport.disabled = true;
    }

    function handleFile(file) {
        resetSelection();

        // Validar extensión
        if (!/\.csv$/i.test(file.name)) {
            showError('El archivo debe tener extensión <strong>.csv</strong>. Recibido: ' + escapeHtml(file.name));
            return;
        }

        // Validar tamaño
        if (file.size > dlhImport.maxSize) {
            showError('El archivo excede el límite de 10 MB. Tamaño: ' + fmtSize(file.size));
            return;
        }

        if (file.size === 0) {
            showError('El archivo está vacío.');
            return;
        }

        selectedFile = file;

        // Mostrar info del archivo
        fileNameEl.textContent = file.name;
        fileSizeEl.textContent = fmtSize(file.size);
        fileInfoBox.style.display = 'flex';
        dropzone.style.display = 'none';

        // Leer y validar contenido
        var reader = new FileReader();
        reader.onload = function (e) {
            validateAndPreview(e.target.result);
        };
        reader.onerror = function () {
            showError('No se pudo leer el archivo.');
        };
        reader.readAsText(file, 'utf-8');
    }

    function showError(html) {
        errorsBox.innerHTML = '<strong>Error de validación</strong>' + html;
        errorsBox.style.display = 'block';
    }

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    /* ---------------------------------------------------------------
       Validación client-side + preview
       --------------------------------------------------------------- */
    function validateAndPreview(content) {
        var lines = content.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
        lines = lines.filter(function (l) { return l.trim() !== ''; });

        if (lines.length < 2) {
            showError('El archivo debe tener al menos un encabezado y una fila de datos. Filas encontradas: ' + lines.length);
            return;
        }

        var delim = detectDelimiter(content);
        var headerCols = parseCSVLine(lines[0], delim);
        var headerNorm = headerCols.map(normalizeHeader);

        // Verificar columnas requeridas
        var required = dlhImport.requiredCols;
        var missing = [];
        required.forEach(function (req) {
            var found = headerNorm.some(function (h) {
                return h.indexOf(req.toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')) !== -1;
            });
            if (!found) missing.push(req);
        });

        if (missing.length > 0) {
            showError(
                '<p>Faltan columnas requeridas en el encabezado:</p>' +
                '<ul>' + missing.map(function (m) { return '<li><code>' + m + '</code></li>'; }).join('') + '</ul>' +
                '<p>Columnas encontradas: <code>' + headerCols.slice(0, 15).join(', ') + (headerCols.length > 15 ? ', ...' : '') + '</code></p>'
            );
            return;
        }

        // Parsear datos para preview
        var idxTemp = -1, idxFecha = -1, idxTotal = -1, idxCuartel = -1;
        headerNorm.forEach(function (h, i) {
            if (h.indexOf('TEMPORADA') !== -1 && idxTemp === -1) idxTemp = i;
            if (h.indexOf('FECHA') !== -1 && idxFecha === -1) idxFecha = i;
            if (h.indexOf('TOTAL CUARTEL') !== -1) idxTotal = i;
            if (h.indexOf('CUARTEL') !== -1 && h.indexOf('TOTAL') === -1 && h.indexOf('PRINCIPAL') === -1 && idxCuartel === -1) idxCuartel = i;
        });

        var rowCount = lines.length - 1;
        var totalSum = 0;
        var temporadas = {};
        var fechas = [];
        var cuarteles = {};

        for (var i = 1; i < lines.length; i++) {
            var cols = parseCSVLine(lines[i], delim);
            if (cols.length < headerCols.length * 0.3) continue;

            if (idxTemp !== -1 && cols[idxTemp]) {
                var t = cols[idxTemp].trim();
                if (t) temporadas[t] = (temporadas[t] || 0) + 1;
            }
            if (idxFecha !== -1 && cols[idxFecha]) {
                var f = cols[idxFecha].trim();
                if (f) fechas.push(f);
            }
            if (idxTotal !== -1 && cols[idxTotal]) {
                totalSum += parseNumCL(cols[idxTotal]);
            }
            if (idxCuartel !== -1 && cols[idxCuartel]) {
                var cu = cols[idxCuartel].trim();
                if (cu) cuarteles[cu] = (cuarteles[cu] || 0) + 1;
            }
        }

        fechas.sort();
        var dateFrom = fechas.length ? fechas[0] : '—';
        var dateTo = fechas.length ? fechas[fechas.length - 1] : '—';
        var numCuarteles = Object.keys(cuarteles).length;
        var tempList = Object.keys(temporadas);

        parsedPreview = {
            rows: rowCount,
            total: totalSum,
            dateFrom: dateFrom,
            dateTo: dateTo,
            temporadas: tempList,
            cuarteles: numCuarteles
        };

        // Mostrar preview
        previewStats.innerHTML =
            stat('Filas de datos', fmtNum(rowCount)) +
            stat('Cuarteles', fmtNum(numCuarteles)) +
            stat('Período', escapeHtml(dateFrom) + ' &mdash; ' + escapeHtml(dateTo)) +
            stat('Temporada(s)', tempList.map(escapeHtml).join(', ') || '—') +
            stat('Total ($ aprox.)', '$' + fmtNum(totalSum));

        step3.style.display = '';
        step4.style.display = 'none';

        // Scroll to step 3
        step3.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function stat(label, value) {
        return '<div class="dlh-stat"><div class="dlh-stat-label">' + label + '</div><div class="dlh-stat-value">' + value + '</div></div>';
    }

    /* ---------------------------------------------------------------
       Confirmación checkbox → habilitar botón
       --------------------------------------------------------------- */
    if (confirmCheck) {
        confirmCheck.addEventListener('change', function () {
            btnImport.disabled = !confirmCheck.checked;
        });
    }

    /* ---------------------------------------------------------------
       Paso 3 → 4: Importar
       --------------------------------------------------------------- */
    if (btnImport) {
        btnImport.addEventListener('click', function () {
            if (!selectedFile || !confirmCheck.checked) return;
            doImport();
        });
    }

    function doImport() {
        step4.style.display = '';
        progressBar.style.display = '';
        progressFill.className = 'dlh-progress-fill dlh-indeterminate';
        progressFill.style.width = '';
        resultBox.style.display = 'none';
        resultErrors.style.display = 'none';
        btnImport.disabled = true;
        confirmCheck.disabled = true;

        var formData = new FormData();
        formData.append('csv_file', selectedFile);

        fetch(dlhImport.restUrl, {
            method: 'POST',
            headers: {
                'X-WP-Nonce': dlhImport.nonce
            },
            body: formData
        })
        .then(function (resp) {
            return resp.json().then(function (data) {
                return { ok: resp.ok, status: resp.status, data: data };
            });
        })
        .then(function (result) {
            progressFill.className = 'dlh-progress-fill';
            progressFill.style.width = '100%';

            if (result.ok && result.data.success) {
                showSuccess(result.data);
            } else {
                var msg = (result.data && result.data.message) || 'Error desconocido al importar.';
                showFail(msg);
            }
        })
        .catch(function (err) {
            progressFill.className = 'dlh-progress-fill';
            progressFill.style.width = '100%';
            showFail('Error de conexión: ' + (err.message || err));
        });

        step4.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showSuccess(data) {
        var logItems = [
            'Filas importadas: ' + fmtNum(data.rows),
            'Total $: ' + fmtNum(data.total_sum),
            'Período: ' + escapeHtml(data.date_from) + ' — ' + escapeHtml(data.date_to),
            'Temporada(s): ' + Object.keys(data.temporadas || {}).map(escapeHtml).join(', '),
            'Respaldo creado: ' + escapeHtml(data.backup || '—')
        ];

        resultBox.className = 'dlh-result-success';
        resultBox.innerHTML =
            '<h4>Importación exitosa</h4>' +
            '<ul class="dlh-log">' + logItems.map(function (l) { return '<li>' + l + '</li>'; }).join('') + '</ul>';
        resultBox.style.display = '';

        // Mostrar errores si hay
        if (data.error_count > 0 && data.errors && data.errors.length) {
            resultErrors.className = 'dlh-error-log';
            var errText = data.errors.join('\n');
            resultErrors.innerHTML =
                '<details><summary>' + data.error_count + ' advertencia(s) encontradas</summary>' +
                '<pre>' + escapeHtml(errText) + '</pre>' +
                '<button type="button" class="button button-small" id="dlh-btn-download-errors">Descargar log de errores</button>' +
                '</details>';
            resultErrors.style.display = '';

            var dlBtn = document.getElementById('dlh-btn-download-errors');
            if (dlBtn) {
                dlBtn.addEventListener('click', function () {
                    var blob = new Blob([data.errors.join('\n')], { type: 'text/plain' });
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'errores-import-2425.txt';
                    a.click();
                });
            }
        }
    }

    function showFail(msg) {
        resultBox.className = 'dlh-result-fail';
        resultBox.innerHTML = '<strong>Error:</strong> ' + escapeHtml(msg);
        resultBox.style.display = '';
        confirmCheck.disabled = false;
        confirmCheck.checked = false;
        btnImport.disabled = true;
    }
})();

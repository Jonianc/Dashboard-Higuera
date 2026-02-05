<?php
/**
 * Clase para la pantalla de importación de Base 24-25 en el admin
 */

if (!defined('WPINC')) {
    die;
}

class Dashboard_Higuera_Import {

    const SUBMENU_SLUG = 'dlh-import-2425';

    /**
     * Inicializar hooks de admin (menú + assets)
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_submenu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
    }

    /**
     * Registrar hooks REST (llamar fuera de is_admin para que funcione en REST requests)
     */
    public static function register_rest_hooks() {
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
    }

    /**
     * Agregar submenú bajo "Dashboard"
     */
    public static function add_submenu() {
        add_submenu_page(
            Dashboard_Higuera_Settings::MENU_SLUG,
            'Importar Base 24-25',
            'Base 24-25',
            'manage_options',
            self::SUBMENU_SLUG,
            array(__CLASS__, 'render_page')
        );
    }

    /**
     * Encolar assets solo en esta página admin
     */
    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, self::SUBMENU_SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'dlh-admin-import',
            DASHBOARD_HIGUERA_PLUGIN_URL . 'assets/css/admin-import.css',
            array(),
            DASHBOARD_HIGUERA_VERSION
        );

        wp_enqueue_script(
            'dlh-admin-import',
            DASHBOARD_HIGUERA_PLUGIN_URL . 'assets/js/admin-import.js',
            array(),
            DASHBOARD_HIGUERA_VERSION,
            true
        );

        wp_localize_script('dlh-admin-import', 'dlhImport', array(
            'restUrl'       => rest_url('dashboard-higuera/v1/import-2425'),
            'templateUrl'   => rest_url('dashboard-higuera/v1/csv/2024-25'),
            'nonce'         => wp_create_nonce('wp_rest'),
            'maxSize'       => 10 * 1024 * 1024, // 10 MB
            'requiredCols'  => array(
                'TEMPORADA', 'FECHA', 'PREDIO', 'SECTOR', 'CUARTEL',
                'FAENA', 'NIVEL 1', 'SUPERFICIE REAL', 'TOTAL CUARTEL'
            ),
        ));
    }

    /**
     * Registrar endpoint REST para la importación
     */
    public static function register_rest_routes() {
        register_rest_route('dashboard-higuera/v1', '/import-2425', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_import'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
    }

    /**
     * Procesar la importación del CSV
     */
    public static function handle_import($request) {
        $files = $request->get_file_params();

        if (empty($files['csv_file'])) {
            return new WP_Error('no_file', 'No se recibió ningún archivo.', array('status' => 400));
        }

        $file = $files['csv_file'];

        // Validar errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'Error al subir el archivo (código ' . $file['error'] . ').', array('status' => 400));
        }

        // Validar tipo MIME
        $allowed = array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true) && !preg_match('/\.csv$/i', $file['name'])) {
            return new WP_Error('invalid_type', 'El archivo debe ser CSV. Tipo detectado: ' . $mime, array('status' => 400));
        }

        // Validar tamaño (10 MB max)
        if ($file['size'] > 10 * 1024 * 1024) {
            return new WP_Error('too_large', 'El archivo excede el límite de 10 MB.', array('status' => 400));
        }

        // Leer contenido
        $content = file_get_contents($file['tmp_name']);
        if ($content === false || strlen(trim($content)) === 0) {
            return new WP_Error('empty_file', 'El archivo está vacío.', array('status' => 400));
        }

        // Detectar delimitador
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lines = array_filter($lines, function ($l) { return trim($l) !== ''; });

        if (count($lines) < 2) {
            return new WP_Error('too_few_rows', 'El archivo tiene menos de 2 filas.', array('status' => 400));
        }

        // Parsear header
        $delimiters = array(';' => 0, ',' => 0, "\t" => 0);
        $sample = array_slice($lines, 0, min(20, count($lines)));
        foreach ($sample as $line) {
            foreach ($delimiters as $d => &$count) {
                $count += substr_count($line, $d);
            }
        }
        unset($count);
        arsort($delimiters);
        $delim = array_key_first($delimiters);

        $header_line = reset($lines);
        $header_cols = str_getcsv($header_line, $delim);
        $header_norm = array_map(function ($h) {
            $s = mb_strtoupper(trim($h));
            $s = preg_replace('/[^\w\s()áéíóúñÁÉÍÓÚÑ]/u', '', $s);
            return normalizer_is_normalized($s) ? $s : $s; // keep as-is
        }, $header_cols);

        // Verificar columnas requeridas
        $required = array('TEMPORADA', 'FECHA', 'PREDIO', 'SECTOR', 'CUARTEL', 'FAENA', 'NIVEL 1', 'SUPERFICIE REAL', 'TOTAL CUARTEL');
        $missing = array();
        foreach ($required as $req) {
            $found = false;
            foreach ($header_norm as $h) {
                if (strpos($h, $req) !== false) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $req;
            }
        }

        if (!empty($missing)) {
            return new WP_Error(
                'missing_columns',
                'Faltan columnas requeridas: ' . implode(', ', $missing),
                array('status' => 400, 'missing' => $missing)
            );
        }

        // Contar filas de datos
        $data_lines = array_slice(array_values($lines), 1);
        $row_count = count($data_lines);

        // Estadísticas básicas
        $idx_temp = null;
        $idx_fecha = null;
        $idx_total = null;
        foreach ($header_norm as $i => $h) {
            if (strpos($h, 'TEMPORADA') !== false) $idx_temp = $i;
            if (strpos($h, 'FECHA') !== false && $idx_fecha === null) $idx_fecha = $i;
            if (strpos($h, 'TOTAL CUARTEL') !== false) $idx_total = $i;
        }

        $total_sum = 0;
        $dates = array();
        $temporadas = array();
        $errors = array();
        $valid_rows = 0;

        foreach ($data_lines as $line_num => $line) {
            $cols = str_getcsv($line, $delim);
            if (count($cols) < count($header_cols) * 0.5) {
                $errors[] = 'Fila ' . ($line_num + 2) . ': muy pocas columnas (' . count($cols) . ')';
                continue;
            }

            if ($idx_temp !== null && isset($cols[$idx_temp])) {
                $t = trim($cols[$idx_temp]);
                if ($t) $temporadas[$t] = ($temporadas[$t] ?? 0) + 1;
            }

            if ($idx_fecha !== null && isset($cols[$idx_fecha])) {
                $f = trim($cols[$idx_fecha]);
                if ($f) $dates[] = $f;
            }

            if ($idx_total !== null && isset($cols[$idx_total])) {
                $v = trim($cols[$idx_total]);
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
                $total_sum += floatval($v);
            }

            $valid_rows++;
        }

        // Rango de fechas
        sort($dates);
        $date_from = !empty($dates) ? reset($dates) : '—';
        $date_to = !empty($dates) ? end($dates) : '—';

        // Escribir el archivo
        $target_path = DASHBOARD_HIGUERA_PLUGIN_DIR . 'data/temporada-2024-25.csv';
        $backup_path = $target_path . '.bak.' . gmdate('Ymd_His');

        // Crear backup si existe
        if (file_exists($target_path)) {
            copy($target_path, $backup_path);
        }

        $written = file_put_contents($target_path, $content);
        if ($written === false) {
            return new WP_Error('write_error', 'No se pudo escribir el archivo. Verifica los permisos del directorio data/.', array('status' => 500));
        }

        // Guardar metadatos de la importación
        $import_meta = array(
            'date'        => current_time('mysql'),
            'filename'    => sanitize_file_name($file['name']),
            'rows'        => $valid_rows,
            'total_sum'   => round($total_sum, 2),
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'temporadas'  => $temporadas,
            'file_size'   => $file['size'],
            'errors'      => count($errors),
            'user'        => wp_get_current_user()->user_login,
        );
        update_option('dlh_last_import_2425', $import_meta);

        return new WP_REST_Response(array(
            'success'     => true,
            'message'     => 'Base 24-25 importada correctamente.',
            'rows'        => $valid_rows,
            'total_sum'   => round($total_sum, 2),
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'temporadas'  => $temporadas,
            'errors'      => array_slice($errors, 0, 50),
            'error_count' => count($errors),
            'backup'      => basename($backup_path),
        ), 200);
    }

    /**
     * Renderizar la página de importación
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $last_import = get_option('dlh_last_import_2425', null);
        ?>
        <div class="wrap dlh-import-wrap">
            <h1>Importar Base Temporada 24-25</h1>
            <p class="dlh-import-desc">
                Carga el archivo CSV con los datos de costos y faenas de la temporada 2024-2025.<br>
                Este archivo se usa en la pestaña <strong>"Comparativo 24-25 vs 25-26"</strong> del dashboard
                para comparar gastos entre ambas temporadas.
                <strong>Al importar se reemplaza completamente</strong> la base anterior (se crea un respaldo automático).
            </p>

            <?php if ($last_import) : ?>
            <div class="dlh-card dlh-card-info">
                <h3>Última importación</h3>
                <div class="dlh-meta-grid">
                    <div><strong>Fecha:</strong> <?php echo esc_html($last_import['date']); ?></div>
                    <div><strong>Usuario:</strong> <?php echo esc_html($last_import['user'] ?? '—'); ?></div>
                    <div><strong>Archivo:</strong> <?php echo esc_html($last_import['filename']); ?></div>
                    <div><strong>Filas:</strong> <?php echo esc_html(number_format_i18n($last_import['rows'])); ?></div>
                    <div><strong>Total:</strong> $<?php echo esc_html(number_format_i18n($last_import['total_sum'], 0)); ?></div>
                    <div><strong>Período:</strong> <?php echo esc_html($last_import['date_from'] . ' — ' . $last_import['date_to']); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Paso 1: Descargar plantilla -->
            <div class="dlh-card" id="dlh-step1">
                <div class="dlh-step-header">
                    <span class="dlh-step-num">1</span>
                    <div>
                        <h3>Descargar plantilla / base actual</h3>
                        <p>Descarga el archivo CSV actual para usarlo como referencia de estructura y formato.</p>
                    </div>
                </div>
                <div class="dlh-step-body">
                    <button type="button" class="button" id="dlh-btn-download">
                        <span class="dashicons dashicons-download" style="margin-top:3px"></span>
                        Descargar base 24-25 actual
                    </button>
                    <span class="dlh-hint">Columnas requeridas: TEMPORADA, FECHA, PREDIO, SECTOR, CUARTEL, FAENA, NIVEL 1, SUPERFICIE REAL (há), TOTAL CUARTEL</span>
                </div>
            </div>

            <!-- Paso 2: Subir archivo -->
            <div class="dlh-card" id="dlh-step2">
                <div class="dlh-step-header">
                    <span class="dlh-step-num">2</span>
                    <div>
                        <h3>Subir nuevo archivo CSV</h3>
                        <p>Arrastra el archivo aquí o haz clic para seleccionarlo. Máximo 10 MB, formato CSV (delimitado por <code>;</code> o <code>,</code>).</p>
                    </div>
                </div>
                <div class="dlh-step-body">
                    <div id="dlh-dropzone" class="dlh-dropzone">
                        <div class="dlh-dropzone-inner">
                            <span class="dashicons dashicons-upload dlh-dropzone-icon"></span>
                            <p class="dlh-dropzone-text">Arrastra tu archivo CSV aquí</p>
                            <p class="dlh-dropzone-sub">o</p>
                            <button type="button" class="button button-secondary" id="dlh-btn-browse">Seleccionar archivo</button>
                            <input type="file" id="dlh-file-input" accept=".csv,text/csv" style="display:none">
                        </div>
                    </div>
                    <div id="dlh-file-info" class="dlh-file-info" style="display:none">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <span id="dlh-file-name"></span>
                        <span id="dlh-file-size"></span>
                        <button type="button" class="dlh-btn-remove" id="dlh-btn-remove" title="Quitar archivo">&times;</button>
                    </div>
                    <div id="dlh-validation-errors" class="dlh-errors" style="display:none"></div>
                </div>
            </div>

            <!-- Paso 3: Vista previa -->
            <div class="dlh-card" id="dlh-step3" style="display:none">
                <div class="dlh-step-header">
                    <span class="dlh-step-num">3</span>
                    <div>
                        <h3>Vista previa y confirmación</h3>
                        <p>Revisa el resumen antes de importar. Esta acción <strong>reemplaza completamente</strong> la base 24-25 actual.</p>
                    </div>
                </div>
                <div class="dlh-step-body">
                    <div class="dlh-preview-grid" id="dlh-preview-stats"></div>
                    <div class="dlh-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <strong>Atención:</strong> Al importar se reemplaza la base 24-25 actual. Se creará un respaldo automático del archivo anterior.
                    </div>
                    <div class="dlh-confirm-row">
                        <label>
                            <input type="checkbox" id="dlh-confirm-check">
                            Entiendo que esta acción reemplaza la base 24-25 actual y no se puede deshacer fácilmente.
                        </label>
                    </div>
                    <button type="button" class="button button-primary button-hero" id="dlh-btn-import" disabled>
                        Importar Base 24-25
                    </button>
                </div>
            </div>

            <!-- Paso 4: Progreso y resultado -->
            <div class="dlh-card" id="dlh-step4" style="display:none">
                <div class="dlh-step-header">
                    <span class="dlh-step-num dlh-step-done">&#10003;</span>
                    <div>
                        <h3>Resultado de la importación</h3>
                    </div>
                </div>
                <div class="dlh-step-body">
                    <div id="dlh-progress-bar" class="dlh-progress" style="display:none">
                        <div class="dlh-progress-fill" id="dlh-progress-fill"></div>
                    </div>
                    <div id="dlh-result" style="display:none"></div>
                    <div id="dlh-result-errors" style="display:none"></div>
                </div>
            </div>
        </div>
        <?php
    }
}

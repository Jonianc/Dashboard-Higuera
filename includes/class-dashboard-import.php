<?php
/**
 * Clase para gestionar Base 24-25 por FTP en el admin.
 */

if (!defined('WPINC')) {
    die;
}

class Dashboard_Higuera_Import {

    const SUBMENU_SLUG = 'dlh-import-2425';
    const EXPECTED_COLUMNS = 38;

    private static $required_headers = array(
        'TEMPORADA',
        'FECHA',
        'PREDIO',
        'SECTOR',
        'CUARTEL',
        'FAENA',
        'NIVEL 1',
        'TOTAL CUARTEL',
    );

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_submenu'));
        add_action('admin_init', array(__CLASS__, 'handle_post_actions'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    public static function enqueue_assets($hook) {
        $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($current_page !== self::SUBMENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'dlh-admin-import-css',
            DASHBOARD_HIGUERA_PLUGIN_URL . 'assets/css/admin-import.css',
            array(),
            DASHBOARD_HIGUERA_VERSION
        );

        wp_enqueue_script(
            'dlh-admin-import-js',
            DASHBOARD_HIGUERA_PLUGIN_URL . 'assets/js/admin-import.js',
            array(),
            DASHBOARD_HIGUERA_VERSION,
            true
        );

        wp_localize_script('dlh-admin-import-js', 'dlhImportPage', array(
            'labels' => array(
                'noFile' => 'Ningún archivo seleccionado',
                'chooseFile' => 'Selecciona un CSV o XLSX para rentabilidad.',
                'selected' => 'Archivo listo para validar y activar',
                'confirmActivate2425' => 'Se activará la base 24-25 vigente. ¿Deseas continuar?',
                'confirmActivateRentabilidad' => 'Se validará y activará la base de rentabilidad reemplazando la activa. ¿Deseas continuar?',
            ),
        ));
    }

    public static function register_rest_hooks() {
        // Legacy: sin rutas REST para flujo FTP/admin.
    }

    public static function add_submenu() {
        add_submenu_page(
            Dashboard_Higuera_Settings::MENU_SLUG,
            'Base 24-25',
            'Base 24-25',
            'manage_options',
            self::SUBMENU_SLUG,
            array(__CLASS__, 'render_page')
        );
    }

    private static function get_upload_dir() {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['basedir']) . 'dashboard-higuera';
    }

    private static function get_source_csv_path() {
        return trailingslashit(self::get_upload_dir()) . 'base-24-25.csv';
    }

    private static function get_source_xlsx_path() {
        return trailingslashit(self::get_upload_dir()) . 'base-24-25.xlsx';
    }

    private static function get_target_path() {
        return trailingslashit(self::get_upload_dir()) . 'temporada-2024-25.csv';
    }

    private static function get_rent_source_csv_path() {
        return trailingslashit(self::get_upload_dir()) . 'base-rentabilidad.csv';
    }

    private static function get_rent_source_xlsx_path() {
        return trailingslashit(self::get_upload_dir()) . 'base-rentabilidad.xlsx';
    }

    private static function get_rent_target_path() {
        return trailingslashit(self::get_upload_dir()) . 'temporada-rentabilidad.csv';
    }

    private static function get_rent_2425_source_csv_path() {
        return trailingslashit(self::get_upload_dir()) . 'base-rentabilidad-2024-25.csv';
    }

    private static function get_rent_2425_source_xlsx_path() {
        return trailingslashit(self::get_upload_dir()) . 'base-rentabilidad-2024-25.xlsx';
    }

    public static function get_rent_2425_target_path() {
        return trailingslashit(self::get_upload_dir()) . 'temporada-rentabilidad-2024-25.csv';
    }

    public static function get_rent_source_path() {
        return self::resolve_rent_source_path();
    }

    public static function get_rent_active_path() {
        return self::get_rent_target_path();
    }

    public static function get_rentabilidad_csv_content() {
        $hybrid_catalog = self::get_rentabilidad_hybrid_catalog_data();
        if (!is_wp_error($hybrid_catalog) && !empty($hybrid_catalog['rows'])) {
            return self::build_hybrid_rentabilidad_csv_from_catalog($hybrid_catalog);
        }

        return self::get_legacy_rentabilidad_csv_content();
    }

    public static function get_rentabilidad_dataset_status() {
        $source = self::resolve_rent_source_path();
        $active = self::get_rent_target_path();
        $active_exists = file_exists($active) && is_readable($active) && filesize($active) > 0;
        $source_analysis = $source ? self::analyze_rentabilidad_source($source) : null;
        $hybrid_catalog = self::get_rentabilidad_hybrid_catalog_data();
        $using_hybrid = !is_wp_error($hybrid_catalog) && !empty($hybrid_catalog['rows']);
        $using = $using_hybrid ? 'hybrid' : ($active_exists ? 'active' : ($source ? 'source' : 'none'));
        $csv = $using_hybrid ? self::build_hybrid_rentabilidad_csv_from_catalog($hybrid_catalog) : self::get_legacy_rentabilidad_csv_content();
        $parsed = !is_wp_error($csv) ? self::analyze_rentabilidad_csv_content($csv) : null;

        return array(
            'source_path' => $source,
            'source_exists' => (bool) $source,
            'source_analysis' => $source_analysis,
            'source_size' => ($source && file_exists($source)) ? (int) filesize($source) : 0,
            'source_hash' => ($source && file_exists($source)) ? md5_file($source) : '',
            'active_path' => $active,
            'active_exists' => $active_exists,
            'active_size' => $active_exists ? (int) filesize($active) : 0,
            'active_hash' => $active_exists ? md5_file($active) : '',
            'using' => $using,
            'using_source_fallback' => ($using === 'source'),
            'using_hybrid' => $using_hybrid,
            'content_error' => is_wp_error($csv) ? $csv->get_error_message() : '',
            'content_hash' => !is_wp_error($csv) ? md5((string) $csv) : '',
            'diagnostics' => is_array($parsed) ? $parsed : array(),
            'hybrid' => !is_wp_error($hybrid_catalog) && is_array($hybrid_catalog) ? $hybrid_catalog : array(
                'available' => false,
                'error' => is_wp_error($hybrid_catalog) ? $hybrid_catalog->get_error_message() : '',
                'rows' => array(),
            ),
        );
    }

    private static function ensure_upload_folder() {
        $dir = self::get_upload_dir();
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    private static function resolve_source_path() {
        $csv = self::get_source_csv_path();
        $xlsx = self::get_source_xlsx_path();

        if (file_exists($xlsx)) {
            return $xlsx;
        }
        if (file_exists($csv)) {
            return $csv;
        }

        return null;
    }

    private static function resolve_rent_source_path() {
        $csv = self::get_rent_source_csv_path();
        $xlsx = self::get_rent_source_xlsx_path();

        if (file_exists($xlsx)) {
            return $xlsx;
        }
        if (file_exists($csv)) {
            return $csv;
        }

        return null;
    }

    private static function resolve_rent_2425_source_path() {
        $csv = self::get_rent_2425_source_csv_path();
        $xlsx = self::get_rent_2425_source_xlsx_path();
        if (file_exists($xlsx)) {
            return $xlsx;
        }
        if (file_exists($csv)) {
            return $csv;
        }
        return null;
    }

    private static function get_uploaded_rentabilidad_file() {
        if (empty($_FILES['dlh_rentabilidad_file']) || !is_array($_FILES['dlh_rentabilidad_file'])) {
            return new WP_Error('missing_upload', 'Selecciona un archivo CSV o XLSX antes de validar.');
        }

        $file = $_FILES['dlh_rentabilidad_file'];
        if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return new WP_Error('missing_upload', 'Selecciona un archivo CSV o XLSX antes de validar.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'La carga del archivo falló. Código: ' . (int) $file['error']);
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('upload_tmp', 'No se pudo verificar el archivo subido.');
        }

        $name = isset($file['name']) ? sanitize_file_name(wp_unslash($file['name'])) : '';
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, array('csv', 'xlsx'), true)) {
            return new WP_Error('bad_ext', 'El archivo debe ser CSV o XLSX.');
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        $max_size = min((int) wp_max_upload_size(), 20 * MB_IN_BYTES);
        if ($size <= 0) {
            return new WP_Error('empty_upload', 'El archivo subido está vacío.');
        }
        if ($max_size > 0 && $size > $max_size) {
            return new WP_Error('upload_too_large', 'El archivo excede el tamaño permitido (' . size_format($max_size) . ').');
        }

        return array(
            'tmp_name' => $file['tmp_name'],
            'name' => $name !== '' ? $name : ('base-rentabilidad.' . $ext),
            'ext' => $ext,
            'size' => $size,
        );
    }

    private static function persist_uploaded_rentabilidad_file($upload) {
        $dir = self::ensure_upload_folder();
        $tmp_path = wp_tempnam('dlh-rentabilidad-' . gmdate('YmdHis'));
        if (!$tmp_path) {
            return new WP_Error('tmp_file', 'No se pudo crear un archivo temporal para validar la base.');
        }

        if (!@move_uploaded_file($upload['tmp_name'], $tmp_path)) {
            if (!@copy($upload['tmp_name'], $tmp_path)) {
                @unlink($tmp_path);
                return new WP_Error('move_upload', 'No se pudo mover el archivo temporal de rentabilidad.');
            }
        }

        $validation_path = $tmp_path . '.' . $upload['ext'];
        @rename($tmp_path, $validation_path);
        if (!file_exists($validation_path)) {
            $validation_path = $tmp_path;
        }

        $analysis = self::analyze_rentabilidad_source($validation_path);
        if (is_wp_error($analysis)) {
            @unlink($validation_path);
            return $analysis;
        }

        $target_source = $upload['ext'] === 'xlsx' ? self::get_rent_source_xlsx_path() : self::get_rent_source_csv_path();
        $alternate_source = $upload['ext'] === 'xlsx' ? self::get_rent_source_csv_path() : self::get_rent_source_xlsx_path();
        if (file_exists($alternate_source)) {
            @unlink($alternate_source);
        }

        if (!@rename($validation_path, $target_source)) {
            if (!@copy($validation_path, $target_source)) {
                @unlink($validation_path);
                return new WP_Error('store_upload', 'No se pudo guardar la base fuente de rentabilidad en uploads/dashboard-higuera.');
            }
            @unlink($validation_path);
        }

        update_option('dlh_rentabilidad_file_meta', array(
            'original_name' => $upload['name'],
            'stored_name' => basename($target_source),
            'size' => (int) $upload['size'],
            'uploaded_at' => current_time('mysql'),
            'source' => 'admin_upload',
            'extension' => $upload['ext'],
        ));

        $analysis['path'] = $target_source;
        $analysis['format'] = $upload['ext'];

        return $analysis;
    }

    private static function activate_rentabilidad_analysis($analysis, $source, $success_message) {
        $normalized = self::build_normalized_rentabilidad_csv($analysis);
        $target = self::get_rent_target_path();
        $backup = $target . '.bak.' . gmdate('Ymd_His');
        if (file_exists($target)) {
            @copy($target, $backup);
        }
        $written = file_put_contents($target, $normalized);
        if ($written === false) {
            self::redirect_with_notice('error', 'No se pudo escribir la base activa de rentabilidad en uploads/dashboard-higuera.');
        }
        if (strtolower((string) pathinfo($source, PATHINFO_EXTENSION)) === 'xlsx') {
            @file_put_contents(self::get_rent_source_csv_path(), $normalized);
        }
        update_option('dlh_last_validation_rentabilidad', array(
            'time' => current_time('mysql'),
            'source' => basename($source),
            'rows' => $analysis['valid_rows'],
            'warnings' => $analysis['warnings_count'],
            'recognized_columns' => $analysis['recognized_columns'],
            'metrics_available' => $analysis['metrics_available'],
            'format' => $analysis['format'],
            'auto_activated' => 1,
        ));
        update_option('dlh_last_rentabilidad_activation', current_time('mysql'));
        self::redirect_with_notice('success', $success_message . (!empty($backup) ? ' Respaldo: ' . basename($backup) : ''));
    }

    private static function get_rentabilidad_file_meta($source_path = null) {
        $meta = get_option('dlh_rentabilidad_file_meta', array());
        $source_path = $source_path ?: self::resolve_rent_source_path();
        $basename = $source_path ? basename($source_path) : '';
        $size = ($source_path && file_exists($source_path)) ? filesize($source_path) : 0;
        $modified = ($source_path && file_exists($source_path)) ? date_i18n('Y-m-d H:i:s', filemtime($source_path)) : '—';

        return array(
            'original_name' => !empty($meta['original_name']) ? (string) $meta['original_name'] : ($basename ?: '—'),
            'stored_name' => !empty($meta['stored_name']) ? (string) $meta['stored_name'] : ($basename ?: '—'),
            'size' => !empty($meta['size']) ? (int) $meta['size'] : (int) $size,
            'uploaded_at' => !empty($meta['uploaded_at']) ? (string) $meta['uploaded_at'] : $modified,
            'source' => !empty($meta['source']) ? (string) $meta['source'] : ($source_path ? 'ftp_manual' : 'none'),
            'extension' => !empty($meta['extension']) ? (string) $meta['extension'] : strtolower((string) pathinfo($basename, PATHINFO_EXTENSION)),
        );
    }




    private static function get_legacy_rentabilidad_csv_content() {
        $target = self::get_rent_target_path();
        if (file_exists($target) && is_readable($target)) {
            $content = file_get_contents($target);
            if ($content !== false && trim((string) $content) !== '') {
                return $content;
            }
        }

        $source = self::resolve_rent_source_path();
        if (!$source) {
            return new WP_Error('rent_missing', 'No existe una base de rentabilidad activa ni una base fuente disponible.');
        }

        $analysis = self::analyze_rentabilidad_source($source);
        if (is_wp_error($analysis)) {
            return $analysis;
        }

        return self::build_normalized_rentabilidad_csv($analysis);
    }

    public static function clear_rentabilidad_hybrid_catalog_cache() {
        delete_transient(self::get_hybrid_catalog_cache_key());
    }

    private static function get_hybrid_catalog_cache_key() {
        $source_url = self::resolve_rentabilidad_api_source_url();
        $data_source = get_option('dlh_data_source', 'api_fallback_csv');
        return 'dlh_rent_hybrid_' . md5($data_source . '|' . $source_url . '|' . DASHBOARD_HIGUERA_VERSION);
    }

    private static function resolve_rentabilidad_api_source_url() {
        $mode = get_option('dlh_api_source_mode', 'url');

        if ($mode === 'powerbi') {
            $formula = (string) get_option('dlh_api_powerbi_formula', '');
            if (preg_match('/Web\.Contents\(\s*"([^"]+)"\s*\)/i', trim($formula), $m)) {
                return esc_url_raw(trim($m[1]));
            }
        }

        return Dashboard_La_Higuera::get_configured_api_url();
    }

    private static function normalize_catalog_key_part($value) {
        $value = (string) $value;
        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }
        $value = strtoupper($value);
        $value = preg_replace('/\s+/', ' ', trim($value));
        return $value;
    }

    public static function build_hybrid_compound_key($predio, $sector, $cuartel) {
        return implode('|', array(
            self::normalize_catalog_key_part($predio),
            self::normalize_catalog_key_part($sector),
            self::normalize_catalog_key_part($cuartel),
        ));
    }

    private static function get_rentabilidad_manual_rows_option() {
        $raw = get_option('dlh_rentabilidad_manual_rows', array());
        if (!is_array($raw)) {
            return array();
        }

        $rows = array();
        foreach ($raw as $stored_key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $predio = isset($row['predio']) ? sanitize_text_field((string) $row['predio']) : '';
            $sector = isset($row['sector']) ? sanitize_text_field((string) $row['sector']) : '';
            $cuartel = isset($row['cuartel']) ? sanitize_text_field((string) $row['cuartel']) : '';
            $compound_key = self::build_hybrid_compound_key($predio, $sector, $cuartel);
            if ($compound_key === '||') {
                $compound_key = sanitize_text_field((string) $stored_key);
            }
            if ($compound_key === '') {
                continue;
            }
            $rows[$compound_key] = array(
                'compound_key' => $compound_key,
                'predio' => $predio,
                'sector' => $sector,
                'cuartel' => $cuartel,
                'kilos_reales' => self::parse_rent_number(isset($row['kilos_reales']) ? $row['kilos_reales'] : 0),
                'ingreso_exportacion' => self::parse_rent_number(isset($row['ingreso_exportacion']) ? $row['ingreso_exportacion'] : 0),
                'ingreso_mercado_nacional' => self::parse_rent_number(isset($row['ingreso_mercado_nacional']) ? $row['ingreso_mercado_nacional'] : 0),
                'otras_ventas_dte' => self::parse_rent_number(isset($row['otras_ventas_dte']) ? $row['otras_ventas_dte'] : 0),
                'otro_ingreso' => self::parse_rent_number(isset($row['otro_ingreso']) ? $row['otro_ingreso'] : 0),
            );
        }

        return $rows;
    }

    private static function find_alias_in_normalized_map($normalized_map, $aliases, $options = array()) {
        $options = is_array($options) ? $options : array();
        $allow_loose = !array_key_exists('allow_loose', $options) || !empty($options['allow_loose']);

        foreach ($aliases as $alias) {
            $alias_key = self::normalize_header_key($alias);
            foreach ($normalized_map as $original => $normalized) {
                if ($normalized === $alias_key) {
                    return $original;
                }
            }
        }

        foreach ($aliases as $alias) {
            $alias_key = self::normalize_header_key($alias);
            foreach ($normalized_map as $original => $normalized) {
                if (strpos($normalized, $alias_key . ' ') === 0 || strpos($normalized, $alias_key . '_') === 0) {
                    return $original;
                }
            }
        }

        if (!$allow_loose) {
            return null;
        }

        foreach ($aliases as $alias) {
            $alias_key = self::normalize_header_key($alias);
            foreach ($normalized_map as $original => $normalized) {
                if (strpos($normalized, $alias_key) !== false || strpos($alias_key, $normalized) !== false) {
                    return $original;
                }
            }
        }
        return null;
    }


    private static function normalize_api_alias_value($value) {
        $value = (string) $value;
        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }
        $value = strtoupper($value);
        $value = str_replace('_', ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));
        return $value;
    }

    private static function find_api_row_key_by_aliases($keys, $aliases) {
        $keys = is_array($keys) ? $keys : array();
        $aliases = is_array($aliases) ? $aliases : array();
        if (empty($keys) || empty($aliases)) {
            return null;
        }

        $normalized_keys = array();
        foreach ($keys as $key) {
            $normalized_keys[$key] = self::normalize_api_alias_value($key);
        }

        $normalized_aliases = array_map(array(__CLASS__, 'normalize_api_alias_value'), $aliases);

        foreach ($normalized_aliases as $alias) {
            foreach ($normalized_keys as $key => $normalized) {
                if ($normalized === $alias) {
                    return $key;
                }
            }
        }

        foreach ($normalized_aliases as $alias) {
            foreach ($normalized_keys as $key => $normalized) {
                if (strpos($normalized, $alias) !== false || strpos($alias, $normalized) !== false) {
                    return $key;
                }
            }
        }

        return null;
    }

    private static function build_dashboard_csv_from_api_rows($rows, $options = array()) {
        $rows = is_array($rows) ? $rows : array();
        if (empty($rows)) {
            return '';
        }

        $options = is_array($options) ? $options : array();
        $temporada_target = isset($options['temporada']) ? (string) $options['temporada'] : '';
        $predio_contains = isset($options['predioContains']) ? (string) $options['predioContains'] : '';
        $razon_social_target = isset($options['razonSocial']) ? (string) $options['razonSocial'] : '';
        $razon_social_contains = isset($options['razonSocialContains']) ? (string) $options['razonSocialContains'] : '';

        $header_out = array('TEMPORADA','FECHA','ORIGEN','PREDIO','SECTOR','CUARTEL','FAENA','NIVEL 1','SUPERFICIE REAL (há)','TOTAL CUARTEL');

        $key_set = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (array_keys($row) as $key) {
                $key_set[$key] = true;
            }
        }
        $keys = array_keys($key_set);
        if (empty($keys)) {
            return '';
        }

        $map = array(
            'TEMPORADA' => self::find_api_row_key_by_aliases($keys, array('TEMPORADA', 'TEMP')),
            'FECHA' => self::find_api_row_key_by_aliases($keys, array('FECHA', 'FECHA DOCUMENTO', 'FEC')),
            'ORIGEN' => self::find_api_row_key_by_aliases($keys, array('ORIGEN', 'TIPO ORIGEN')),
            'RAZON SOCIAL' => self::find_api_row_key_by_aliases($keys, array('RAZON SOCIAL', 'RAZON_SOCIAL', 'EMPRESA', 'CLIENTE')),
            'PREDIO' => self::find_api_row_key_by_aliases($keys, array('PREDIO', 'CAMPO')),
            'SECTOR' => self::find_api_row_key_by_aliases($keys, array('SECTOR', 'CULTIVO')),
            'CUARTEL' => self::find_api_row_key_by_aliases($keys, array('CUARTEL', 'LOTE')),
            'FAENA' => self::find_api_row_key_by_aliases($keys, array('FAENA', 'LABOR')),
            'NIVEL 1' => self::find_api_row_key_by_aliases($keys, array('NIVEL 1', 'NIVEL_1', 'NIVEL1')),
            'SUPERFICIE REAL (há)' => self::find_api_row_key_by_aliases($keys, array('SUPERFICIE REAL (HA)', 'SUPERFICIE REAL', 'SUPERFICIE', 'HAS', 'HECTAREAS')),
            'TOTAL CUARTEL' => self::find_api_row_key_by_aliases($keys, array('TOTAL CUARTEL', 'TOTAL', 'MONTO', 'VALOR')),
        );

        foreach (array('FECHA','PREDIO','SECTOR','CUARTEL','FAENA','NIVEL 1','TOTAL CUARTEL') as $required) {
            if (empty($map[$required])) {
                return '';
            }
        }

        $lines = array();
        $lines[] = implode(';', $header_out);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ($temporada_target !== '' || $predio_contains !== '') {
                $temp_value = !empty($map['TEMPORADA']) && isset($row[$map['TEMPORADA']]) ? trim((string) $row[$map['TEMPORADA']]) : '';
                $predio_value = !empty($map['PREDIO']) && isset($row[$map['PREDIO']]) ? strtoupper((string) $row[$map['PREDIO']]) : '';
                if ($temporada_target !== '' && $temp_value !== $temporada_target) {
                    continue;
                }
                if ($predio_contains !== '' && strpos($predio_value, strtoupper($predio_contains)) === false) {
                    continue;
                }
            }

            if ($razon_social_target !== '' || $razon_social_contains !== '') {
                $razon_social_value = !empty($map['RAZON SOCIAL']) && isset($row[$map['RAZON SOCIAL']]) ? (string) $row[$map['RAZON SOCIAL']] : '';
                $razon_social_norm = self::normalize_api_alias_value($razon_social_value);
                if ($razon_social_target !== '' && $razon_social_norm !== self::normalize_api_alias_value($razon_social_target)) {
                    continue;
                }
                if ($razon_social_contains !== '' && strpos($razon_social_norm, self::normalize_api_alias_value($razon_social_contains)) === false) {
                    continue;
                }
            }

            $line = array();
            foreach ($header_out as $header_name) {
                $source_key = isset($map[$header_name]) ? $map[$header_name] : null;
                $value = ($source_key !== null && isset($row[$source_key])) ? $row[$source_key] : '';
                $line[] = '"' . str_replace('"', '""', (string) $value) . '"';
            }
            $lines[] = implode(';', $line);
        }

        return implode("
", $lines);
    }

    private static function get_dashboard_2526_csv_payload_for_rentabilidad() {
        $cache_key = self::get_hybrid_catalog_cache_key();
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['csv'])) {
            return $cached;
        }

        $source = 'none';
        $source_url = self::resolve_rentabilidad_api_source_url();
        $csv = '';

        if (trim((string) $source_url) !== '') {
            $response = wp_remote_get($source_url, array('timeout' => 20));
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                if ($code >= 200 && $code < 300 && trim((string) $body) !== '') {
                    $decoded = json_decode($body, true);
                    if (is_array($decoded)) {
                        $rows = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
                        if (!empty($rows)) {
                            $csv = self::build_dashboard_csv_from_api_rows($rows, array(
                                'temporada' => '2025-2026',
                                'predioContains' => 'HIGUERA',
                                'razonSocial' => 'AGRICOLA LA HIGUERA S.A.',
                            ));
                            if (trim((string) $csv) !== '') {
                                $source = 'api';
                            }
                        }
                    }
                }
            }
        }

        if ($csv === '') {
            $fallback_file = DASHBOARD_HIGUERA_PLUGIN_DIR . 'data/temporada-2025-26.csv';
            if (file_exists($fallback_file) && is_readable($fallback_file)) {
                $csv = (string) file_get_contents($fallback_file);
                if (trim($csv) !== '') {
                    $source = 'csv_fallback';
                }
            }
        }

        $payload = array(
            'csv' => $csv,
            'source' => $source,
            'source_url' => $source_url,
            'fetched_at' => current_time('mysql'),
        );

        set_transient($cache_key, $payload, (int) apply_filters('dlh_rentabilidad_hybrid_cache_ttl', 300));

        return $payload;
    }

    private static function parse_dashboard_2526_csv_dataset_for_rentabilidad($csv_content) {
        $trimmed = trim((string) $csv_content);
        if ($trimmed === '') {
            return array(
                'rows' => array(),
                'sup_map' => array(),
                'header_index' => -1,
            );
        }

        $lines = preg_split('/
|
|
/', $trimmed);
        $delimiter = self::detect_delimiter($lines);
        $records = self::split_repaired_records($trimmed);
        $rows = array();
        foreach ($records as $record) {
            $rows[] = str_getcsv($record, $delimiter);
        }
        if (empty($rows)) {
            return array(
                'rows' => array(),
                'sup_map' => array(),
                'header_index' => -1,
            );
        }

        $required = array('FECHA','TEMPORADA','PREDIO','SECTOR','CUARTEL','FAENA','NIVEL 1','SUPERFICIE REAL (HA)','TOTAL CUARTEL');
        $header_index = -1;
        $header = array();
        foreach ($rows as $index => $row) {
            $normalized = array_map(array(__CLASS__, 'normalize_header_key'), $row);
            $score = 0;
            foreach ($required as $name) {
                if (in_array(self::normalize_header_key($name), $normalized, true)) {
                    $score++;
                }
            }
            if ($score >= 7) {
                $header_index = $index;
                $header = $row;
                break;
            }
        }
        if ($header_index < 0 || empty($header)) {
            return array(
                'rows' => array(),
                'sup_map' => array(),
                'header_index' => -1,
            );
        }

        $idx = self::find_header_indexes($header, array(
            'ORIGEN',
            'TEMPORADA',
            'PREDIO',
            'SECTOR',
            'CUARTEL',
            'SUPERFICIE REAL (HA)',
            'SUPERFICIE REAL (HÁ)',
            'TOTAL CUARTEL',
        ));

        $i_origen = $idx['ORIGEN'];
        $i_temp = $idx['TEMPORADA'];
        $i_predio = $idx['PREDIO'];
        $i_sector = $idx['SECTOR'];
        $i_cuartel = $idx['CUARTEL'];
        $i_sup = $idx['SUPERFICIE REAL (HA)'];
        if ($i_sup === null) {
            $i_sup = $idx['SUPERFICIE REAL (HÁ)'];
        }
        $i_total = $idx['TOTAL CUARTEL'];

        $data = array();
        $sup_map = array();

        for ($r = $header_index + 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            if (!is_array($row)) {
                continue;
            }
            $non_empty = array_filter($row, function ($cell) {
                return trim((string) $cell) !== '';
            });
            if (empty($non_empty)) {
                continue;
            }

            $origen = ($i_origen !== null && isset($row[$i_origen])) ? trim((string) $row[$i_origen]) : '';
            if ($origen !== '' && preg_match('/PRESUP/i', $origen)) {
                continue;
            }

            $temp = ($i_temp !== null && isset($row[$i_temp])) ? self::canonicalize_dashboard_temp((string) $row[$i_temp]) : '';
            if ($temp !== '2025-2026') {
                continue;
            }

            $predio = ($i_predio !== null && isset($row[$i_predio])) ? trim((string) $row[$i_predio]) : '';
            $sector = ($i_sector !== null && isset($row[$i_sector])) ? trim((string) $row[$i_sector]) : '';
            $cuartel = ($i_cuartel !== null && isset($row[$i_cuartel])) ? trim((string) $row[$i_cuartel]) : '';
            $sup = ($i_sup !== null && isset($row[$i_sup])) ? self::parse_dashboard_surface_number($row[$i_sup]) : 0.0;
            $valor = ($i_total !== null && isset($row[$i_total])) ? self::parse_rent_number($row[$i_total]) : 0.0;

            if ($cuartel !== '' && $sup > 0) {
                $sup_map[$cuartel] = $sup;
            }

            $data[] = array(
                'predio' => $predio,
                'sector' => $sector,
                'cuartel' => $cuartel,
                'valor' => $valor,
            );
        }

        return array(
            'rows' => $data,
            'sup_map' => $sup_map,
            'header_index' => $header_index,
        );
    }

    private static function canonicalize_dashboard_temp($value) {
        $raw = str_replace(array('–', '—'), '-', trim((string) $value));
        $raw = preg_replace('/\s+/', '', $raw);
        if (preg_match('/(\d{4})[^\d]?(\d{4})/', $raw, $m)) {
            return $m[1] . '-' . $m[2];
        }
        return $raw;
    }

    private static function parse_dashboard_surface_number($value) {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    private static function build_hybrid_catalog_from_dashboard_dataset($dataset) {
        $rows = isset($dataset['rows']) && is_array($dataset['rows']) ? $dataset['rows'] : array();
        $sup_map = isset($dataset['sup_map']) && is_array($dataset['sup_map']) ? $dataset['sup_map'] : array();
        if (empty($rows)) {
            return array(
                'rows' => array(),
                'compare_rows' => array(),
                'dashboard_total_costos' => 0.0,
            );
        }

        $grouped = array();
        $by_cuartel = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $predio = isset($row['predio']) ? trim((string) $row['predio']) : '';
            $sector = isset($row['sector']) ? trim((string) $row['sector']) : '';
            $cuartel = isset($row['cuartel']) ? trim((string) $row['cuartel']) : '';
            if ($predio === '' && $sector === '' && $cuartel === '') {
                continue;
            }
            $compound_key = self::build_hybrid_compound_key($predio, $sector, $cuartel);
            if ($compound_key === '||') {
                continue;
            }

            if (!isset($grouped[$compound_key])) {
                $grouped[$compound_key] = array(
                    'compound_key' => $compound_key,
                    'predio' => $predio,
                    'sector' => $sector,
                    'cuartel' => $cuartel,
                    'especie' => '',
                    'variedad' => '',
                    'hectareas' => isset($sup_map[$cuartel]) ? (float) $sup_map[$cuartel] : 0.0,
                    'total_costos' => 0.0,
                );
            }

            $grouped[$compound_key]['total_costos'] += isset($row['valor']) ? (float) $row['valor'] : 0.0;

            if (!isset($by_cuartel[$cuartel])) {
                $by_cuartel[$cuartel] = 0.0;
            }
            $by_cuartel[$cuartel] += isset($row['valor']) ? (float) $row['valor'] : 0.0;
        }

        uasort($grouped, function ($a, $b) {
            return strcmp(implode('|', array($a['predio'], $a['sector'], $a['cuartel'])), implode('|', array($b['predio'], $b['sector'], $b['cuartel'])));
        });

        $compare_rows = array();
        foreach ($grouped as $row) {
            $dashboard_cost = isset($by_cuartel[$row['cuartel']]) ? (float) $by_cuartel[$row['cuartel']] : 0.0;
            $compare_rows[] = array(
                'compound_key' => $row['compound_key'],
                'predio' => $row['predio'],
                'sector' => $row['sector'],
                'cuartel' => $row['cuartel'],
                'dashboard_costos' => $dashboard_cost,
                'hybrid_costos' => (float) $row['total_costos'],
                'diferencia' => (float) $row['total_costos'] - $dashboard_cost,
                'hectareas' => (float) $row['hectareas'],
            );
        }

        return array(
            'rows' => array_values($grouped),
            'compare_rows' => $compare_rows,
            'dashboard_total_costos' => array_sum($by_cuartel),
        );
    }

    private static function extract_api_rows_for_rentabilidad() {
        $cache_key = self::get_hybrid_catalog_cache_key();
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['rows'])) {
            return $cached;
        }

        $source = 'none';
        $source_url = self::resolve_rentabilidad_api_source_url();
        $rows = array();

        if (trim((string) $source_url) !== '') {
            $response = wp_remote_get($source_url, array('timeout' => 20));
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                if ($code >= 200 && $code < 300 && trim((string) $body) !== '') {
                    $decoded = json_decode($body, true);
                    if (is_array($decoded)) {
                        $rows = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
                        if (!empty($rows)) {
                            $source = 'api';
                        }
                    }
                }
            }
        }

        if (empty($rows)) {
            $fallback_file = DASHBOARD_HIGUERA_PLUGIN_DIR . 'data/temporada-2025-26.csv';
            if (file_exists($fallback_file) && is_readable($fallback_file)) {
                $analysis = self::analyze_source($fallback_file);
                if (!is_wp_error($analysis) && !empty($analysis['records']) && !empty($analysis['header'])) {
                    foreach ($analysis['records'] as $record) {
                        $assoc = array();
                        foreach ($analysis['header'] as $index => $header_name) {
                            $assoc[$header_name] = isset($record[$index]) ? $record[$index] : '';
                        }
                        $rows[] = $assoc;
                    }
                    if (!empty($rows)) {
                        $source = 'csv_fallback';
                    }
                }
            }
        }

        $payload = array(
            'rows' => is_array($rows) ? $rows : array(),
            'source' => $source,
            'source_url' => $source_url,
            'fetched_at' => current_time('mysql'),
        );

        set_transient($cache_key, $payload, (int) apply_filters('dlh_rentabilidad_hybrid_cache_ttl', 300));

        return $payload;
    }

    private static function build_hybrid_catalog_from_api_rows($rows) {
        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        $key_map = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $key => $value) {
                if (!isset($key_map[$key])) {
                    $key_map[$key] = self::normalize_header_key($key);
                }
            }
        }

        if (empty($key_map)) {
            return array();
        }

        $field_map = array(
            'temporada' => self::find_alias_in_normalized_map($key_map, array('TEMPORADA', 'TEMP')),
            'origen' => self::find_alias_in_normalized_map($key_map, array('ORIGEN', 'TIPO ORIGEN')),
            'razon_social' => self::find_alias_in_normalized_map($key_map, array('RAZON SOCIAL', 'RAZON_SOCIAL', 'EMPRESA', 'CLIENTE')),
            'predio' => self::find_alias_in_normalized_map($key_map, array('PREDIO', 'CAMPO')),
            'sector' => self::find_alias_in_normalized_map($key_map, array('SECTOR', 'CULTIVO')),
            'cuartel' => self::find_alias_in_normalized_map($key_map, array('CUARTEL', 'LOTE')),
            'especie' => self::find_alias_in_normalized_map($key_map, array('ESPECIE')),
            'variedad' => self::find_alias_in_normalized_map($key_map, array('VARIEDAD')),
            'hectareas' => self::find_alias_in_normalized_map($key_map, array('SUPERFICIE REAL (HA)', 'SUPERFICIE REAL (HÁ)', 'SUPERFICIE REAL'), array('allow_loose' => false)),
            'total_costos' => self::find_alias_in_normalized_map($key_map, array('TOTAL CUARTEL'), array('allow_loose' => false)),
        );

        if (empty($field_map['hectareas'])) {
            $field_map['hectareas'] = self::find_alias_in_normalized_map($key_map, array('SUPERFICIE', 'HAS', 'HECTAREAS'));
        }
        if (empty($field_map['total_costos'])) {
            $field_map['total_costos'] = self::find_alias_in_normalized_map($key_map, array('TOTAL', 'MONTO', 'VALOR'));
        }

        $grouped = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $temporada = isset($field_map['temporada'], $row[$field_map['temporada']]) ? trim((string) $row[$field_map['temporada']]) : '';
            if ($temporada !== '' && $temporada !== '2025-2026') {
                continue;
            }
            $origen = isset($field_map['origen'], $row[$field_map['origen']]) ? trim((string) $row[$field_map['origen']]) : '';
            if ($origen !== '' && preg_match('/PRESUP/i', $origen)) {
                continue;
            }
            $predio = isset($field_map['predio'], $row[$field_map['predio']]) ? trim((string) $row[$field_map['predio']]) : '';
            $sector = isset($field_map['sector'], $row[$field_map['sector']]) ? trim((string) $row[$field_map['sector']]) : '';
            $cuartel = isset($field_map['cuartel'], $row[$field_map['cuartel']]) ? trim((string) $row[$field_map['cuartel']]) : '';
            if ($predio === '' || $sector === '' || $cuartel === '') {
                continue;
            }
            if (self::normalize_catalog_key_part($predio) !== '' && strpos(self::normalize_catalog_key_part($predio), 'HIGUERA') === false) {
                continue;
            }
            $razon_social = isset($field_map['razon_social'], $row[$field_map['razon_social']]) ? trim((string) $row[$field_map['razon_social']]) : '';
            if ($razon_social !== '' && self::normalize_catalog_key_part($razon_social) !== 'AGRICOLA LA HIGUERA S.A.') {
                continue;
            }

            $compound_key = self::build_hybrid_compound_key($predio, $sector, $cuartel);
            if (!isset($grouped[$compound_key])) {
                $grouped[$compound_key] = array(
                    'compound_key' => $compound_key,
                    'predio' => $predio,
                    'sector' => $sector,
                    'cuartel' => $cuartel,
                    'especie' => isset($field_map['especie'], $row[$field_map['especie']]) ? trim((string) $row[$field_map['especie']]) : '',
                    'variedad' => isset($field_map['variedad'], $row[$field_map['variedad']]) ? trim((string) $row[$field_map['variedad']]) : '',
                    'hectareas' => 0.0,
                    'total_costos' => 0.0,
                );
            }

            $hectareas = isset($field_map['hectareas'], $row[$field_map['hectareas']]) ? self::parse_rent_number($row[$field_map['hectareas']]) : 0.0;
            $costos = isset($field_map['total_costos'], $row[$field_map['total_costos']]) ? self::parse_rent_number($row[$field_map['total_costos']]) : 0.0;
            if ($hectareas > 0 && $hectareas > $grouped[$compound_key]['hectareas']) {
                $grouped[$compound_key]['hectareas'] = $hectareas;
            }
            $grouped[$compound_key]['total_costos'] += $costos;
            if ($grouped[$compound_key]['especie'] === '' && !empty($field_map['especie']) && isset($row[$field_map['especie']])) {
                $grouped[$compound_key]['especie'] = trim((string) $row[$field_map['especie']]);
            }
            if ($grouped[$compound_key]['variedad'] === '' && !empty($field_map['variedad']) && isset($row[$field_map['variedad']])) {
                $grouped[$compound_key]['variedad'] = trim((string) $row[$field_map['variedad']]);
            }
        }

        uasort($grouped, function ($a, $b) {
            $left = array($a['predio'], $a['sector'], $a['cuartel']);
            $right = array($b['predio'], $b['sector'], $b['cuartel']);
            return strcmp(implode('|', $left), implode('|', $right));
        });

        return array_values($grouped);
    }

    public static function get_rentabilidad_hybrid_catalog_data() {
        $csv_payload = self::get_dashboard_2526_csv_payload_for_rentabilidad();
        $dashboard_dataset = self::parse_dashboard_2526_csv_dataset_for_rentabilidad(isset($csv_payload['csv']) ? $csv_payload['csv'] : '');
        $catalog_data = self::build_hybrid_catalog_from_dashboard_dataset($dashboard_dataset);
        $catalog_rows = isset($catalog_data['rows']) && is_array($catalog_data['rows']) ? $catalog_data['rows'] : array();

        $raw_catalog_payload = self::extract_api_rows_for_rentabilidad();
        $raw_catalog_rows = self::build_hybrid_catalog_from_api_rows(isset($raw_catalog_payload['rows']) && is_array($raw_catalog_payload['rows']) ? $raw_catalog_payload['rows'] : array());
        $raw_catalog_lookup = array();
        foreach ($raw_catalog_rows as $raw_row) {
            if (!is_array($raw_row)) {
                continue;
            }
            $raw_key = isset($raw_row['compound_key']) ? (string) $raw_row['compound_key'] : self::build_hybrid_compound_key(
                isset($raw_row['predio']) ? $raw_row['predio'] : '',
                isset($raw_row['sector']) ? $raw_row['sector'] : '',
                isset($raw_row['cuartel']) ? $raw_row['cuartel'] : ''
            );
            if ($raw_key === '' || $raw_key === '||') {
                continue;
            }
            $raw_catalog_lookup[$raw_key] = $raw_row;
        }

        if (!empty($raw_catalog_lookup)) {
            foreach ($catalog_rows as $index => $row) {
                $compound_key = isset($row['compound_key']) ? (string) $row['compound_key'] : self::build_hybrid_compound_key(
                    isset($row['predio']) ? $row['predio'] : '',
                    isset($row['sector']) ? $row['sector'] : '',
                    isset($row['cuartel']) ? $row['cuartel'] : ''
                );
                if (!isset($raw_catalog_lookup[$compound_key])) {
                    continue;
                }
                $raw_row = $raw_catalog_lookup[$compound_key];
                $current_hectareas = isset($row['hectareas']) ? (float) $row['hectareas'] : 0.0;
                $fallback_hectareas = isset($raw_row['hectareas']) ? (float) $raw_row['hectareas'] : 0.0;
                if ($current_hectareas <= 0 && $fallback_hectareas > 0) {
                    $catalog_rows[$index]['hectareas'] = $fallback_hectareas;
                }
                if ((empty($catalog_rows[$index]['especie']) || empty($catalog_rows[$index]['variedad'])) && is_array($raw_row)) {
                    if (empty($catalog_rows[$index]['especie']) && !empty($raw_row['especie'])) {
                        $catalog_rows[$index]['especie'] = $raw_row['especie'];
                    }
                    if (empty($catalog_rows[$index]['variedad']) && !empty($raw_row['variedad'])) {
                        $catalog_rows[$index]['variedad'] = $raw_row['variedad'];
                    }
                }
            }
        }

        if (empty($catalog_rows) && !empty($raw_catalog_rows)) {
            $catalog_rows = $raw_catalog_rows;
        }

        $manual_map = self::get_rentabilidad_manual_rows_option();
        $merged = array();

        foreach ($catalog_rows as $row) {
            $compound_key = isset($row['compound_key']) ? (string) $row['compound_key'] : self::build_hybrid_compound_key($row['predio'], $row['sector'], $row['cuartel']);
            $manual = isset($manual_map[$compound_key]) ? $manual_map[$compound_key] : array();
            $kilos = isset($manual['kilos_reales']) ? (float) $manual['kilos_reales'] : 0.0;
            $ing_export = isset($manual['ingreso_exportacion']) ? (float) $manual['ingreso_exportacion'] : 0.0;
            $ing_mercado = isset($manual['ingreso_mercado_nacional']) ? (float) $manual['ingreso_mercado_nacional'] : 0.0;
            $otras = isset($manual['otras_ventas_dte']) ? (float) $manual['otras_ventas_dte'] : 0.0;
            $otro = isset($manual['otro_ingreso']) ? (float) $manual['otro_ingreso'] : 0.0;
            $total_ingresos = $ing_export + $ing_mercado + $otras + $otro;
            $costos = isset($row['total_costos']) ? (float) $row['total_costos'] : 0.0;
            $hectareas = isset($row['hectareas']) ? (float) $row['hectareas'] : 0.0;
            $resultado = $total_ingresos - $costos;
            $merged[$compound_key] = array(
                'compound_key' => $compound_key,
                'predio' => $row['predio'],
                'sector' => $row['sector'],
                'especie' => isset($row['especie']) ? $row['especie'] : '',
                'variedad' => isset($row['variedad']) ? $row['variedad'] : '',
                'cuartel' => $row['cuartel'],
                'hectareas' => $hectareas,
                'kilos_reales' => $kilos,
                'ingreso_exportacion' => $ing_export,
                'ingreso_mercado_nacional' => $ing_mercado,
                'otras_ventas_dte' => $otras,
                'otro_ingreso' => $otro,
                'total_ingresos' => $total_ingresos,
                'total_costos' => $costos,
                'resultado' => $resultado,
                'ingreso_hectarea' => $hectareas > 0 ? ($total_ingresos / $hectareas) : 0.0,
                'costo_hectarea' => $hectareas > 0 ? ($costos / $hectareas) : 0.0,
                'ingresos_kilo' => $kilos > 0 ? ($total_ingresos / $kilos) : 0.0,
                'costo_kilo' => $kilos > 0 ? ($costos / $kilos) : 0.0,
                'manual_complete' => ($kilos > 0 || $total_ingresos > 0),
            );
        }

        foreach ($manual_map as $compound_key => $manual) {
            if (isset($merged[$compound_key])) {
                continue;
            }
            $kilos = isset($manual['kilos_reales']) ? (float) $manual['kilos_reales'] : 0.0;
            $ing_export = isset($manual['ingreso_exportacion']) ? (float) $manual['ingreso_exportacion'] : 0.0;
            $ing_mercado = isset($manual['ingreso_mercado_nacional']) ? (float) $manual['ingreso_mercado_nacional'] : 0.0;
            $otras = isset($manual['otras_ventas_dte']) ? (float) $manual['otras_ventas_dte'] : 0.0;
            $otro = isset($manual['otro_ingreso']) ? (float) $manual['otro_ingreso'] : 0.0;
            $total_ingresos = $ing_export + $ing_mercado + $otras + $otro;
            $merged[$compound_key] = array(
                'compound_key' => $compound_key,
                'predio' => isset($manual['predio']) ? $manual['predio'] : '',
                'sector' => isset($manual['sector']) ? $manual['sector'] : '',
                'especie' => '',
                'variedad' => '',
                'cuartel' => isset($manual['cuartel']) ? $manual['cuartel'] : '',
                'hectareas' => 0.0,
                'kilos_reales' => $kilos,
                'ingreso_exportacion' => $ing_export,
                'ingreso_mercado_nacional' => $ing_mercado,
                'otras_ventas_dte' => $otras,
                'otro_ingreso' => $otro,
                'total_ingresos' => $total_ingresos,
                'total_costos' => 0.0,
                'resultado' => $total_ingresos,
                'ingreso_hectarea' => 0.0,
                'costo_hectarea' => 0.0,
                'ingresos_kilo' => $kilos > 0 ? ($total_ingresos / $kilos) : 0.0,
                'costo_kilo' => 0.0,
                'manual_complete' => ($kilos > 0 || $total_ingresos > 0),
            );
        }

        uasort($merged, function ($a, $b) {
            $left = implode('|', array($a['predio'], $a['sector'], $a['cuartel']));
            $right = implode('|', array($b['predio'], $b['sector'], $b['cuartel']));
            return strcmp($left, $right);
        });

        $manual_completed = 0;
        foreach ($merged as $row) {
            if (!empty($row['manual_complete'])) {
                $manual_completed++;
            }
        }

        $missing_hectareas_count = 0;
        foreach ($merged as $row) {
            if ((float) (isset($row['hectareas']) ? $row['hectareas'] : 0) <= 0) {
                $missing_hectareas_count++;
            }
        }

        return array(
            'available' => !empty($merged),
            'rows' => array_values($merged),
            'source' => isset($csv_payload['source']) ? $csv_payload['source'] : 'none',
            'source_url' => isset($csv_payload['source_url']) ? $csv_payload['source_url'] : '',
            'catalog_count' => count($catalog_rows),
            'raw_catalog_count' => count($raw_catalog_rows),
            'manual_count' => count($manual_map),
            'manual_completed_count' => $manual_completed,
            'missing_hectareas_count' => $missing_hectareas_count,
            'fetched_at' => isset($csv_payload['fetched_at']) ? $csv_payload['fetched_at'] : '',
            'dashboard_total_costos' => isset($catalog_data['dashboard_total_costos']) ? (float) $catalog_data['dashboard_total_costos'] : 0.0,
            'compare_rows' => isset($catalog_data['compare_rows']) && is_array($catalog_data['compare_rows']) ? $catalog_data['compare_rows'] : array(),
            'csv_header_index' => isset($dashboard_dataset['header_index']) ? (int) $dashboard_dataset['header_index'] : -1,
            'raw_catalog_source' => isset($raw_catalog_payload['source']) ? $raw_catalog_payload['source'] : 'none',
        );
    }

    private static function build_hybrid_rentabilidad_csv_from_catalog($catalog) {
        if (!is_array($catalog) || empty($catalog['rows']) || !is_array($catalog['rows'])) {
            return new WP_Error('rent_hybrid_empty', 'No hay filas híbridas disponibles para rentabilidad.');
        }

        $fp = fopen('php://temp', 'w+');
        fputcsv($fp, self::get_rentabilidad_canonical_headers(), ';', '"');

        foreach ($catalog['rows'] as $row) {
            fputcsv($fp, array(
                isset($row['predio']) ? $row['predio'] : '',
                isset($row['sector']) ? $row['sector'] : '',
                isset($row['especie']) ? $row['especie'] : '',
                isset($row['variedad']) ? $row['variedad'] : '',
                isset($row['cuartel']) ? $row['cuartel'] : '',
                self::format_rent_number_for_csv(isset($row['hectareas']) ? $row['hectareas'] : 0),
                self::format_rent_number_for_csv(isset($row['kilos_reales']) ? $row['kilos_reales'] : 0),
                self::format_rent_number_for_csv(isset($row['total_ingresos']) ? $row['total_ingresos'] : 0),
                self::format_rent_number_for_csv(isset($row['total_costos']) ? $row['total_costos'] : 0),
                self::format_rent_number_for_csv(isset($row['resultado']) ? $row['resultado'] : 0),
                self::format_rent_number_for_csv(isset($row['ingreso_hectarea']) ? $row['ingreso_hectarea'] : 0),
                self::format_rent_number_for_csv(isset($row['costo_hectarea']) ? $row['costo_hectarea'] : 0),
                self::format_rent_number_for_csv(isset($row['ingresos_kilo']) ? $row['ingresos_kilo'] : 0),
                self::format_rent_number_for_csv(isset($row['costo_kilo']) ? $row['costo_kilo'] : 0),
            ), ';', '"');
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    private static function handle_dataset_action($source, $target, $source_csv_path, $success_label) {
        if (!$source) {
            self::redirect_with_notice('error', 'No existe la base fuente esperada en uploads/dashboard-higuera.');
        }

        $analysis = self::analyze_source($source);
        if (is_wp_error($analysis)) {
            self::redirect_with_notice('error', $analysis->get_error_message());
        }

        $normalized = self::build_normalized_csv($analysis);
        $backup = $target . '.bak.' . gmdate('Ymd_His');

        if (file_exists($target)) {
            @copy($target, $backup);
        }

        $written = file_put_contents($target, $normalized);
        if ($written === false) {
            self::redirect_with_notice('error', 'No se pudo escribir la base activa en uploads/dashboard-higuera.');
        }

        if ($analysis['format'] === 'xlsx') {
            @file_put_contents($source_csv_path, $normalized);
        }

        update_option('last_import_time', current_time('mysql'));
        update_option('last_rows', (int) $analysis['valid_rows']);
        update_option('last_warnings', (int) $analysis['warnings_count']);
        update_option('last_2425_updated', current_time('mysql'));

        self::redirect_with_notice('success', $success_label . ' activada correctamente. Respaldo: ' . basename($backup));
    }

    public static function handle_post_actions() {
        if (empty($_POST['dlh_import_action'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.', 403);
        }

        check_admin_referer('dlh_base_2425_action');
        self::ensure_upload_folder();

        $action = sanitize_text_field(wp_unslash($_POST['dlh_import_action']));

        if ($action === 'upload_validate_rentabilidad') {
            $upload = self::get_uploaded_rentabilidad_file();
            if (is_wp_error($upload)) {
                self::redirect_with_notice('error', $upload->get_error_message());
            }
            $analysis = self::persist_uploaded_rentabilidad_file($upload);
            if (is_wp_error($analysis)) {
                self::redirect_with_notice('error', $analysis->get_error_message());
            }
            $source = isset($analysis['path']) ? $analysis['path'] : self::resolve_rent_source_path();
            self::activate_rentabilidad_analysis($analysis, $source, 'Base de rentabilidad cargada, validada y activada correctamente.');
        }

        if ($action === 'validate_rentabilidad' || $action === 'activate_rentabilidad') {
            $source = self::resolve_rent_source_path();
            if (!$source) {
                self::redirect_with_notice('error', 'No existe base-rentabilidad.csv ni base-rentabilidad.xlsx en uploads/dashboard-higuera.');
            }
            $analysis = self::analyze_rentabilidad_source($source);
            if (is_wp_error($analysis)) {
                self::redirect_with_notice('error', $analysis->get_error_message());
            }
            if ($action === 'validate_rentabilidad') {
                self::activate_rentabilidad_analysis($analysis, $source, 'Base de rentabilidad validada y activada correctamente.');
            }
            self::activate_rentabilidad_analysis($analysis, $source, 'Base de rentabilidad activada correctamente.');
        }

        $source = self::resolve_source_path();

        if (!$source) {
            self::redirect_with_notice('error', 'No existe base-24-25.csv ni base-24-25.xlsx en uploads/dashboard-higuera.');
        }

        $analysis = self::analyze_source($source);
        if (is_wp_error($analysis)) {
            self::redirect_with_notice('error', $analysis->get_error_message());
        }

        if ($action === 'validate') {
            update_option('dlh_last_validation_2425', array(
                'time' => current_time('mysql'),
                'source' => basename($source),
                'rows' => $analysis['valid_rows'],
                'warnings' => $analysis['warnings_count'],
                'incomplete' => $analysis['incomplete_count'],
                'excessive' => $analysis['excessive_count'],
                'encoding' => $analysis['encoding'],
                'delimiter' => $analysis['delimiter'],
                'format' => $analysis['format'],
            ));
            self::redirect_with_notice('success', 'Validación completada.');
        }

        if ($action === 'activate') {
            self::handle_dataset_action($source, self::get_target_path(), self::get_source_csv_path(), 'Base 24-25');
        }

        self::redirect_with_notice('error', 'Acción no válida.');
    }

    private static function redirect_with_notice($type, $message) {
        $url = add_query_arg(array(
            'page' => self::SUBMENU_SLUG,
            'dlh_notice_type' => $type,
            'dlh_notice' => rawurlencode($message),
        ), admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private static function read_file_utf8($path) {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return new WP_Error('read_error', 'No se pudo leer el archivo CSV.');
        }
        if ($raw === '') {
            return '';
        }

        $encoding = mb_detect_encoding($raw, array('UTF-8', 'ISO-8859-1', 'Windows-1252'), true);
        if (!$encoding) {
            $encoding = 'UTF-8';
        }

        if ($encoding !== 'UTF-8') {
            $raw = mb_convert_encoding($raw, 'UTF-8', $encoding);
        }

        return $raw;
    }

    private static function detect_delimiter($lines) {
        $score = array(';' => 0, ',' => 0);
        $sample = array_slice($lines, 0, min(30, count($lines)));
        foreach ($sample as $line) {
            $score[';'] += substr_count($line, ';');
            $score[','] += substr_count($line, ',');
        }
        return ($score[';'] >= $score[',']) ? ';' : ',';
    }

    private static function split_repaired_records($content) {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $records = array();
        $buffer = '';
        $openQuotes = 0;

        foreach ($lines as $line) {
            $buffer .= ($buffer === '' ? '' : "\n") . $line;
            $openQuotes += self::count_unescaped_quotes($line);

            if ($openQuotes % 2 === 0) {
                $records[] = $buffer;
                $buffer = '';
                $openQuotes = 0;
            }
        }

        if ($buffer !== '') {
            $records[] = $buffer;
        }

        return $records;
    }

    private static function count_unescaped_quotes($line) {
        $count = 0;
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            if ($line[$i] !== '"') {
                continue;
            }
            if ($i + 1 < $len && $line[$i + 1] === '"') {
                $i++;
                continue;
            }
            $count++;
        }
        return $count;
    }

    private static function col_to_index($letters) {
        $letters = strtoupper(preg_replace('/[^A-Z]/', '', $letters));
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $index - 1);
    }

    private static function parse_xlsx_rows($path) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('zip_missing', 'ZipArchive no está disponible en el servidor para leer XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return new WP_Error('xlsx_open', 'No se pudo abrir el archivo XLSX.');
        }

        $sharedStrings = array();
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sx = @simplexml_load_string($sharedXml);
            if ($sx) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                        continue;
                    }
                    $text = '';
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            return new WP_Error('sheet_missing', 'No se encontró xl/worksheets/sheet1.xml en el XLSX.');
        }

        $sheet = @simplexml_load_string($sheetXml);
        if (!$sheet) {
            $zip->close();
            return new WP_Error('sheet_parse', 'No se pudo parsear la hoja principal del XLSX.');
        }

        $rows = array();
        if (!isset($sheet->sheetData->row)) {
            $zip->close();
            return array();
        }

        foreach ($sheet->sheetData->row as $rowNode) {
            $row = array();
            foreach ($rowNode->c as $cell) {
                $ref = (string) $cell['r'];
                $idx = self::col_to_index($ref);
                $type = (string) $cell['t'];
                $value = '';

                if ($type === 's') {
                    $ssIdx = (int) ((string) $cell->v);
                    $value = isset($sharedStrings[$ssIdx]) ? $sharedStrings[$ssIdx] : '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($cell->is->t) ? (string) $cell->is->t : '';
                } else {
                    $value = isset($cell->v) ? (string) $cell->v : '';
                }

                $row[$idx] = $value;
            }

            if (!empty($row)) {
                ksort($row);
                $max = max(array_keys($row));
                $normalized = array();
                for ($i = 0; $i <= $max; $i++) {
                    $normalized[] = isset($row[$i]) ? $row[$i] : '';
                }
                $rows[] = $normalized;
            }
        }

        $zip->close();
        return $rows;
    }



    private static function normalize_rent_header_key($value) {
        $value = (string) $value;
        $value = str_replace("ï»¿", '', $value);
        $value = str_replace(array("Â ", " "), ' ', $value);
        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        } else {
            $value = strtr($value, array(
                'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'Å' => 'A',
                'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
                'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
                'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
                'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
                'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
                'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
                'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
                'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
                'Ñ' => 'N', 'ñ' => 'n', 'Ç' => 'C', 'ç' => 'c',
            ));
        }
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $value = preg_replace('/[$%°]/', '', $value);
        $value = preg_replace('/[^a-z0-9]+/', '_', trim($value));
        return trim((string) $value, '_');
    }

    private static function get_rentabilidad_canonical_header_map() {
        return array(
            'predio' => 'PREDIO',
            'sector' => 'SECTOR',
            'especie' => 'ESPECIE',
            'variedad' => 'VARIEDAD',
            'cuartel' => 'CUARTEL',
            'hectareas' => 'HECTAREAS',
            'kilos_reales' => 'KILOS REALES',
            'total_ingresos' => 'TOTAL INGRESOS',
            'total_costos' => 'TOTAL COSTOS ACUMULADOS',
            'resultado' => 'RESULTADO',
            'ingreso_hectarea' => 'INGRESO HECTAREA',
            'costo_hectarea' => 'COSTO TOTAL POR HECTAREA',
            'ingresos_kilo' => 'INGRESOS POR KILO',
            'costo_kilo' => 'COSTO POR KILO',
        );
    }

    private static function get_rentabilidad_canonical_headers() {
        return array_values(self::get_rentabilidad_canonical_header_map());
    }

    private static function get_rentabilidad_expected_keys() {
        return array_keys(self::get_rentabilidad_canonical_header_map());
    }

    private static function header_matches_rent_alias($normalized_value, $alias) {
        if ($normalized_value === $alias) {
            return true;
        }
        return strpos($normalized_value, $alias . '_') === 0;
    }

    private static function format_rent_number_for_csv($value, $max_decimals = 6) {
        $number = (float) $value;
        if (!is_finite($number)) {
            return '';
        }
        if (abs($number - round($number)) < 0.0000001) {
            return (string) (int) round($number);
        }
        $formatted = number_format($number, $max_decimals, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    private static function get_rent_cell_value($row, $map, $key) {
        if (!isset($map[$key]) || $map[$key] === null) {
            return '';
        }
        $index = (int) $map[$key];
        return isset($row[$index]) ? trim((string) $row[$index]) : '';
    }

    private static function build_rentabilidad_canonical_row($row, $map) {
        $predio = trim((string) self::get_rent_cell_value($row, $map, 'predio'));
        $sector = trim((string) self::get_rent_cell_value($row, $map, 'sector'));
        $especie = trim((string) self::get_rent_cell_value($row, $map, 'especie'));
        $variedad = trim((string) self::get_rent_cell_value($row, $map, 'variedad'));
        $cuartel = trim((string) self::get_rent_cell_value($row, $map, 'cuartel'));
        if (strtoupper($cuartel) === 'TOTAL COSTOS') {
            return null;
        }

        $hectareas = self::parse_rent_number(self::get_rent_cell_value($row, $map, 'hectareas'));
        $kilos = self::parse_rent_number(self::get_rent_cell_value($row, $map, 'kilos_reales'));
        $ingresos = self::parse_rent_number(self::get_rent_cell_value($row, $map, 'total_ingresos'));
        $costos = self::parse_rent_number(self::get_rent_cell_value($row, $map, 'total_costos'));
        $resultado = $ingresos - $costos;

        $ingreso_hectarea = $hectareas > 0 ? $ingresos / $hectareas : 0.0;
        $costo_hectarea = $hectareas > 0 ? $costos / $hectareas : 0.0;
        $ingresos_kilo = $kilos > 0 ? $ingresos / $kilos : 0.0;
        $costo_kilo = $kilos > 0 ? $costos / $kilos : 0.0;

        return array(
            $predio,
            $sector,
            $especie,
            $variedad,
            $cuartel,
            self::format_rent_number_for_csv($hectareas),
            self::format_rent_number_for_csv($kilos),
            self::format_rent_number_for_csv($ingresos),
            self::format_rent_number_for_csv($costos),
            self::format_rent_number_for_csv($resultado),
            self::format_rent_number_for_csv($ingreso_hectarea),
            self::format_rent_number_for_csv($costo_hectarea),
            self::format_rent_number_for_csv($ingresos_kilo),
            self::format_rent_number_for_csv($costo_kilo),
        );
    }

    private static function get_rentabilidad_alias_map() {
        return array(
            'predio' => array('predio'),
            'sector' => array('sector'),
            'especie' => array('especie'),
            'variedad' => array('variedad'),
            'cuartel' => array('cuartel'),
            'hectareas' => array('hectareas', 'hectarea', 'has'),
            'kilos_reales' => array('kilos_reales', 'kilos_real', 'kg_reales', 'kg_real', 'kilos'),
            'total_ingresos' => array('total_ingresos', 'ingreso_total', 'ingresos_totales'),
            'total_costos' => array('total_costos_acumulados', 'total_costos', 'costos_acumulados'),
            'resultado' => array('resultado', 'margen', 'utilidad'),
            'ingreso_hectarea' => array('ingreso_hectarea', 'ingreso_por_hectarea', 'ingreso_ha'),
            'costo_hectarea' => array('costo_total_por_hectarea', 'costo_por_hectarea', 'costos_por_hectarea', 'costo_ha'),
            'ingresos_kilo' => array('ingresos_por_kilo', 'ingreso_por_kilo', 'ingreso_kg'),
            'costo_kilo' => array('costo_por_kilo', 'costos_por_kilo', 'costo_kg'),
        );
    }

    private static function find_best_rentabilidad_header_index($rows) {
        $aliases = self::get_rentabilidad_alias_map();
        $targets = array('predio', 'sector', 'cuartel', 'hectareas', 'kilos_reales', 'total_ingresos', 'total_costos', 'resultado');
        $best_index = -1;
        $best_score = -1;

        for ($i = 0; $i < min(count($rows), 40); $i++) {
            $normalized = array_map(array(__CLASS__, 'normalize_rent_header_key'), array_map('strval', (array) $rows[$i]));
            $score = 0;
            foreach ($targets as $target) {
                foreach ($aliases[$target] as $alias) {
                    if (in_array($alias, $normalized, true)) {
                        $score++;
                        break;
                    }
                }
            }
            if ($score > $best_score) {
                $best_score = $score;
                $best_index = $i;
            }
        }

        return array($best_index, $best_score);
    }

    private static function find_rentabilidad_column_map($header) {
        $aliases = self::get_rentabilidad_alias_map();
        $normalized = array_map(array(__CLASS__, 'normalize_rent_header_key'), array_map('strval', (array) $header));
        $map = array();

        foreach ($aliases as $key => $list) {
            $map[$key] = null;
            foreach ($normalized as $index => $value) {
                if ($value === '') {
                    continue;
                }
                foreach ($list as $alias) {
                    if (self::header_matches_rent_alias($value, $alias)) {
                        $map[$key] = (int) $index;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    private static function parse_rentabilidad_source_rows($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            return self::parse_xlsx_rows($path);
        }

        $content = self::read_file_utf8($path);
        if (is_wp_error($content)) {
            return $content;
        }

        if (trim($content) === '') {
            return new WP_Error('empty_file', 'La base de rentabilidad está vacía.');
        }

        $lines = preg_split('/
|
|
/', $content);
        $lines = array_values(array_filter($lines, function ($line) {
            return trim((string) $line) !== '';
        }));
        $delimiter = self::detect_delimiter($lines);
        $records = self::split_repaired_records($content);
        $records = array_values(array_filter($records, function ($record) {
            return trim((string) $record) !== '';
        }));

        $rows = array();
        foreach ($records as $record) {
            $rows[] = str_getcsv($record, $delimiter);
        }

        return $rows;
    }

    private static function analyze_rentabilidad_source($path) {
        $rows = self::parse_rentabilidad_source_rows($path);
        if (is_wp_error($rows)) {
            return $rows;
        }

        $rows = array_values(array_filter($rows, function ($row) {
            return is_array($row);
        }));
        if (count($rows) < 2) {
            return new WP_Error('few_records', 'La base de rentabilidad debe tener encabezado y al menos una fila.');
        }

        list($header_index, $score) = self::find_best_rentabilidad_header_index($rows);
        if ($header_index < 0 || $score < 3) {
            return new WP_Error('bad_rent_header', 'No se pudo detectar un encabezado válido para la base de rentabilidad.');
        }

        $header = array_map('strval', (array) $rows[$header_index]);
        $col_count = max(1, count($header));
        $map = self::find_rentabilidad_column_map($header);
        $records = array();
        $valid_rows = 0;
        $warnings = array();

        for ($i = $header_index + 1; $i < count($rows); $i++) {
            $row = array_slice(array_pad(array_map('strval', (array) $rows[$i]), $col_count, ''), 0, $col_count);
            $has_data = false;
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    $has_data = true;
                    break;
                }
            }
            if (!$has_data) {
                continue;
            }

            $records[] = $row;
            $predio = ($map['predio'] !== null && isset($row[$map['predio']])) ? trim((string) $row[$map['predio']]) : '';
            $sector = ($map['sector'] !== null && isset($row[$map['sector']])) ? trim((string) $row[$map['sector']]) : '';
            $cuartel = ($map['cuartel'] !== null && isset($row[$map['cuartel']])) ? trim((string) $row[$map['cuartel']]) : '';
            if ($predio !== '' || $sector !== '' || $cuartel !== '') {
                $valid_rows++;
            } else {
                $warnings[] = 'Fila ' . ($i + 1) . ': sin Predio, Sector ni Cuartel.';
            }
        }

        $recognized = array_values(array_filter(self::get_rentabilidad_expected_keys(), function ($key) use ($map) {
            return array_key_exists($key, $map) && $map[$key] !== null;
        }));
        $metrics = array_values(array_filter(array('total_ingresos', 'total_costos', 'resultado', 'kilos_reales', 'ingreso_hectarea', 'costo_hectarea', 'ingresos_kilo', 'costo_kilo'), function ($key) use ($map) {
            return array_key_exists($key, $map) && $map[$key] !== null;
        }));

        return array(
            'path' => $path,
            'format' => strtolower((string) pathinfo($path, PATHINFO_EXTENSION)),
            'header' => $header,
            'header_index' => $header_index,
            'column_count' => $col_count,
            'valid_rows' => $valid_rows,
            'warnings' => $warnings,
            'warnings_count' => count($warnings),
            'records' => $records,
            'column_map' => $map,
            'recognized_columns' => $recognized,
            'metrics_available' => $metrics,
        );
    }

    private static function build_normalized_rentabilidad_csv($analysis) {
        $fp = fopen('php://temp', 'w+');
        fputcsv($fp, self::get_rentabilidad_canonical_headers(), ';', '"');
        $map = isset($analysis['column_map']) && is_array($analysis['column_map']) ? $analysis['column_map'] : array();
        foreach ((array) $analysis['records'] as $row) {
            $normalized_row = self::build_rentabilidad_canonical_row((array) $row, $map);
            if ($normalized_row === null) {
                continue;
            }
            if (
                trim((string) $normalized_row[0]) === '' &&
                trim((string) $normalized_row[1]) === '' &&
                trim((string) $normalized_row[4]) === ''
            ) {
                continue;
            }
            fputcsv($fp, $normalized_row, ';', '"');
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }

    public static function get_rentabilidad_2425_csv_content() {
        $active = self::get_rent_2425_target_path();
        if (file_exists($active) && is_readable($active) && filesize($active) > 0) {
            $content = self::read_file_utf8($active);
            if (!is_wp_error($content) && trim((string) $content) !== '') {
                return (string) $content;
            }
        }
        $source = self::resolve_rent_2425_source_path();
        if (!$source) {
            return new WP_Error('rent_2425_missing', 'No existe base comparativa de rentabilidad 24-25.');
        }
        $analysis = self::analyze_rentabilidad_source($source);
        if (is_wp_error($analysis)) {
            return $analysis;
        }
        return self::build_normalized_rentabilidad_csv($analysis);
    }

    private static function parse_rent_number($value) {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace(array('$', '€', '£', '¥', "Â ", ' '), '', $raw);
        $raw = preg_replace('/[^0-9,\.\-]/', '', $raw);
        if ($raw === '' || $raw === '-' || $raw === '.' || $raw === ',') {
            return 0.0;
        }
        $comma_count = substr_count($raw, ',');
        $dot_count = substr_count($raw, '.');
        if ($comma_count > 0 && $dot_count > 0) {
            $last_comma = strrpos($raw, ',');
            $last_dot = strrpos($raw, '.');
            if ($last_comma > $last_dot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($dot_count > 1 && $comma_count === 0) {
            $raw = str_replace('.', '', $raw);
        } elseif ($comma_count > 1 && $dot_count === 0) {
            $raw = str_replace(',', '', $raw);
        } elseif ($comma_count === 1 && $dot_count === 0) {
            $raw = str_replace(',', '.', $raw);
        }
        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    private static function analyze_rentabilidad_csv_content($content) {
        $trimmed = trim((string) $content);
        if ($trimmed === '') {
            return array(
                'rows' => 0,
                'recognized_columns' => array(),
                'metrics_available' => array(),
                'totals' => array(),
                'header_index' => -1,
            );
        }

        $lines = preg_split('/
|
|
/', $trimmed);
        $lines = array_values(array_filter($lines, function ($line) {
            return trim((string) $line) !== '';
        }));
        $delimiter = self::detect_delimiter($lines);
        $rows = array();
        foreach (self::split_repaired_records($trimmed) as $record) {
            if (trim((string) $record) === '') {
                continue;
            }
            $rows[] = str_getcsv($record, $delimiter);
        }
        list($header_index, $score) = self::find_best_rentabilidad_header_index($rows);
        if ($header_index < 0 || $score < 3) {
            return array(
                'rows' => 0,
                'recognized_columns' => array(),
                'metrics_available' => array(),
                'totals' => array(),
                'header_index' => $header_index,
            );
        }

        $header = array_map('strval', (array) $rows[$header_index]);
        $map = self::find_rentabilidad_column_map($header);
        $recognized = array_values(array_filter(self::get_rentabilidad_expected_keys(), function ($key) use ($map) {
            return isset($map[$key]) && $map[$key] !== null;
        }));
        $metrics = array_values(array_filter(array('total_ingresos', 'total_costos', 'resultado', 'kilos_reales', 'ingreso_hectarea', 'costo_hectarea', 'ingresos_kilo', 'costo_kilo'), function ($key) use ($map) {
            return isset($map[$key]) && $map[$key] !== null;
        }));

        $totals = array(
            'total_ingresos' => 0.0,
            'total_costos' => 0.0,
            'resultado' => 0.0,
            'kilos_reales' => 0.0,
            'hectareas' => 0.0,
        );
        $row_count = 0;

        for ($i = $header_index + 1; $i < count($rows); $i++) {
            $row = array_map('strval', (array) $rows[$i]);
            $predio = ($map['predio'] !== null && isset($row[$map['predio']])) ? trim((string) $row[$map['predio']]) : '';
            $sector = ($map['sector'] !== null && isset($row[$map['sector']])) ? trim((string) $row[$map['sector']]) : '';
            $cuartel = ($map['cuartel'] !== null && isset($row[$map['cuartel']])) ? trim((string) $row[$map['cuartel']]) : '';
            if ($predio === '' && $sector === '' && $cuartel === '') {
                continue;
            }
            $row_count++;
            if ($map['total_ingresos'] !== null && isset($row[$map['total_ingresos']])) {
                $totals['total_ingresos'] += self::parse_rent_number($row[$map['total_ingresos']]);
            }
            if ($map['total_costos'] !== null && isset($row[$map['total_costos']])) {
                $totals['total_costos'] += self::parse_rent_number($row[$map['total_costos']]);
            }
            if ($map['resultado'] !== null && isset($row[$map['resultado']])) {
                $totals['resultado'] += self::parse_rent_number($row[$map['resultado']]);
            }
            if ($map['kilos_reales'] !== null && isset($row[$map['kilos_reales']])) {
                $totals['kilos_reales'] += self::parse_rent_number($row[$map['kilos_reales']]);
            }
            if ($map['hectareas'] !== null && isset($row[$map['hectareas']])) {
                $totals['hectareas'] += self::parse_rent_number($row[$map['hectareas']]);
            }
        }

        return array(
            'rows' => $row_count,
            'recognized_columns' => $recognized,
            'metrics_available' => $metrics,
            'totals' => $totals,
            'header_index' => $header_index,
        );
    }

    private static function normalize_header_key($value) {
        $value = strtoupper((string) $value);
        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }
        $value = preg_replace('/\s+/', ' ', trim($value));
        return $value;
    }

    private static function find_header_indexes($header, $requiredNames) {
        $map = array();
        foreach ($header as $idx => $name) {
            $map[self::normalize_header_key($name)] = $idx;
        }

        $indexes = array();
        foreach ($requiredNames as $name) {
            $key = self::normalize_header_key($name);
            $indexes[$name] = array_key_exists($key, $map) ? (int) $map[$key] : null;
        }

        return $indexes;
    }

    private static function normalize_date_value($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (is_numeric($value)) {
            $num = (float) $value;
            if ($num > 0 && $num < 60000) {
                $timestamp = ((int) round($num) - 25569) * DAY_IN_SECONDS;
                if ($timestamp > 0) {
                    return gmdate('Y-m-d', $timestamp);
                }
            }
        }

        $formats = array('d/m/Y', 'd-m-Y', 'Y/m/d', 'Y-m-d', 'd.m.Y');
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($value);
        if ($ts !== false) {
            return gmdate('Y-m-d', $ts);
        }

        return $value;
    }

    private static function analyze_source($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            return self::analyze_xlsx($path);
        }
        return self::analyze_csv($path);
    }

    private static function analyze_csv($path) {
        $content = self::read_file_utf8($path);
        if (is_wp_error($content)) {
            return $content;
        }

        if (trim($content) === '') {
            return new WP_Error('empty_file', 'El archivo CSV está vacío.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lines = array_values(array_filter($lines, function ($line) {
            return trim($line) !== '';
        }));

        if (count($lines) < 2) {
            return new WP_Error('few_lines', 'El archivo debe tener al menos encabezado + 1 fila.');
        }

        $delimiter = self::detect_delimiter($lines);
        $records = self::split_repaired_records($content);
        $records = array_values(array_filter($records, function ($r) { return trim($r) !== ''; }));

        $rows = array();
        foreach ($records as $record) {
            $rows[] = str_getcsv($record, $delimiter);
        }

        return self::analyze_rows($rows, $path, 'csv', $delimiter, 'UTF-8');
    }

    private static function analyze_xlsx($path) {
        $rows = self::parse_xlsx_rows($path);
        if (is_wp_error($rows)) {
            return $rows;
        }
        return self::analyze_rows($rows, $path, 'xlsx', '(excel)', 'UTF-8');
    }

    private static function analyze_rows($rows, $path, $format, $delimiter, $encoding) {
        $rows = array_values(array_filter($rows, function ($row) {
            return is_array($row);
        }));

        if (count($rows) < 2) {
            return new WP_Error('few_records', 'No se detectaron registros suficientes en la base.');
        }

        $header = array_map('strval', $rows[0]);
        $colCount = self::EXPECTED_COLUMNS;
        $header = array_slice(array_pad($header, $colCount, ''), 0, $colCount);

        if (count(array_filter($header, function ($cell) { return trim((string) $cell) !== ''; })) < 2) {
            return new WP_Error('bad_header', 'No se pudo detectar un encabezado válido.');
        }

        $requiredIndexes = self::find_header_indexes($header, self::$required_headers);
        $fechaIndex = isset($requiredIndexes['FECHA']) ? $requiredIndexes['FECHA'] : null;

        $validRows = 0;
        $incomplete = 0;
        $excessive = 0;
        $warnings = array();
        $dataRows = array();

        for ($i = 1; $i < count($rows); $i++) {
            $row = array_map('strval', $rows[$i]);
            $rawCount = count($row);
            if ($rawCount > $colCount) {
                $excessive++;
                $warnings[] = 'Fila ' . ($i + 1) . ': con columnas extra (' . $rawCount . '/' . $colCount . ').';
            }

            $row = array_slice(array_pad($row, $colCount, ''), 0, $colCount);

            if ($fechaIndex !== null) {
                $row[$fechaIndex] = self::normalize_date_value($row[$fechaIndex]);
            }

            $isEmptyRow = true;
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    $isEmptyRow = false;
                    break;
                }
            }

            if ($isEmptyRow) {
                $incomplete++;
                $warnings[] = 'Fila ' . ($i + 1) . ': incompleta (fila vacía).';
                $dataRows[] = $row;
                continue;
            }

            $missingRequired = array();
            foreach ($requiredIndexes as $name => $index) {
                if ($index === null) {
                    continue;
                }
                if (trim((string) $row[$index]) === '') {
                    $missingRequired[] = $name;
                }
            }

            if (!empty($missingRequired)) {
                $incomplete++;
                $warnings[] = 'Fila ' . ($i + 1) . ': incompleta (faltan obligatorias: ' . implode(', ', $missingRequired) . ').';
            } else {
                $validRows++;
            }

            $dataRows[] = $row;
        }

        return array(
            'path' => $path,
            'format' => $format,
            'encoding' => $encoding,
            'delimiter' => $delimiter,
            'header' => $header,
            'header_columns' => $colCount,
            'valid_rows' => $validRows,
            'incomplete_count' => $incomplete,
            'excessive_count' => $excessive,
            'warnings_count' => $incomplete + $excessive,
            'warnings' => $warnings,
            'records' => $dataRows,
        );
    }

    private static function build_normalized_csv($analysis) {
        $header = $analysis['header'];
        $cols = self::EXPECTED_COLUMNS;
        $header = array_slice(array_pad($header, $cols, ''), 0, $cols);
        $requiredIndexes = self::find_header_indexes($header, self::$required_headers);
        $fechaIndex = isset($requiredIndexes['FECHA']) ? $requiredIndexes['FECHA'] : null;

        $fp = fopen('php://temp', 'w+');
        fputcsv($fp, $header, ';', '"');

        foreach ($analysis['records'] as $row) {
            $row = array_map('strval', is_array($row) ? $row : array());
            $row = array_slice(array_pad($row, $cols, ''), 0, $cols);

            if ($fechaIndex !== null) {
                $row[$fechaIndex] = self::normalize_date_value($row[$fechaIndex]);
            }

            fputcsv($fp, $row, ';', '"');
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        self::ensure_upload_folder();

        $notice = isset($_GET['dlh_notice']) ? sanitize_text_field(wp_unslash($_GET['dlh_notice'])) : '';
        $notice_type = isset($_GET['dlh_notice_type']) ? sanitize_text_field(wp_unslash($_GET['dlh_notice_type'])) : 'success';

        $source = self::resolve_source_path();
        $analysis = $source ? self::analyze_source($source) : null;
        $active_target = self::get_target_path();
        $active_exists = file_exists($active_target);

        $rent_status = self::get_rentabilidad_dataset_status();
        $rent_source = $rent_status['source_path'];
        $rent_analysis = $rent_status['source_analysis'];
        $rent_diagnostics = !empty($rent_status['diagnostics']) && is_array($rent_status['diagnostics']) ? $rent_status['diagnostics'] : array();
        $rent_meta = self::get_rentabilidad_file_meta($rent_source);
        $rent_has_error = is_wp_error($rent_analysis) || !empty($rent_status['content_error']);
        $rent_recognized = array();
        $rent_metrics = array();
        $rent_rows = 0;
        $rent_warnings = array();
        if (is_array($rent_analysis) && !is_wp_error($rent_analysis)) {
            $rent_recognized = !empty($rent_analysis['recognized_columns']) ? (array) $rent_analysis['recognized_columns'] : array();
            $rent_metrics = !empty($rent_analysis['metrics_available']) ? (array) $rent_analysis['metrics_available'] : array();
            $rent_rows = !empty($rent_analysis['valid_rows']) ? (int) $rent_analysis['valid_rows'] : 0;
            $rent_warnings = !empty($rent_analysis['warnings']) ? (array) $rent_analysis['warnings'] : array();
        } elseif (!empty($rent_diagnostics)) {
            $rent_recognized = !empty($rent_diagnostics['recognized_columns']) ? (array) $rent_diagnostics['recognized_columns'] : array();
            $rent_metrics = !empty($rent_diagnostics['metrics_available']) ? (array) $rent_diagnostics['metrics_available'] : array();
            $rent_rows = !empty($rent_diagnostics['rows']) ? (int) $rent_diagnostics['rows'] : 0;
        }

        $rent_expected_labels = array(
            'predio' => 'Predio',
            'sector' => 'Sector',
            'especie' => 'Especie',
            'variedad' => 'Variedad',
            'cuartel' => 'Cuartel',
            'hectareas' => 'Hectáreas',
            'kilos_reales' => 'Kilos reales',
            'total_ingresos' => 'Total ingresos',
            'total_costos' => 'Total costos',
            'resultado' => 'Resultado',
            'ingreso_hectarea' => 'Ingreso/ha',
            'costo_hectarea' => 'Costo/ha',
            'ingresos_kilo' => 'Ingreso/kg',
            'costo_kilo' => 'Costo/kg',
        );
        $rent_missing = array();
        foreach ($rent_expected_labels as $key => $label) {
            if (!in_array($key, $rent_recognized, true)) {
                $rent_missing[$key] = $label;
            }
        }

        $rent_checklist = array(
            array(
                'label' => 'Archivo leído',
                'ok' => (bool) $rent_source && !is_wp_error($rent_analysis),
                'meta' => $rent_source ? basename($rent_source) : 'Sin archivo fuente',
            ),
            array(
                'label' => 'Columnas reconocidas',
                'ok' => count($rent_recognized) >= 5,
                'meta' => count($rent_recognized) . ' detectadas',
            ),
            array(
                'label' => 'Filas válidas',
                'ok' => $rent_rows > 0,
                'meta' => number_format_i18n($rent_rows),
            ),
            array(
                'label' => 'Métricas detectadas',
                'ok' => count($rent_metrics) >= 3,
                'meta' => count($rent_metrics) . ' métricas',
            ),
            array(
                'label' => 'Lista para activar',
                'ok' => !$rent_has_error && $rent_rows > 0 && count($rent_metrics) >= 3,
                'meta' => !$rent_has_error ? 'Activación automática habilitada' : 'Revisa diagnóstico',
            ),
        );

        $rent_validation = get_option('dlh_last_validation_rentabilidad', array());
        $rent_active_path = self::get_rent_target_path();
        $rent_active_exists = !empty($rent_status['active_exists']);
        $rent_active_size = ($rent_active_exists && file_exists($rent_active_path)) ? filesize($rent_active_path) : 0;
        $rent_active_modified = ($rent_active_exists && file_exists($rent_active_path)) ? date_i18n('Y-m-d H:i:s', filemtime($rent_active_path)) : '—';
        $using_mode_label = $rent_status['using'] === 'active' ? 'Base activa' : ($rent_status['using'] === 'source' ? 'Fallback desde fuente' : 'Sin datos');
        $rent_endpoint_url = rest_url('dashboard-higuera/v1/csv/rentabilidad');
        $rent_diag_endpoint_url = rest_url('dashboard-higuera/v1/csv/rentabilidad-diagnostics');
        $rent_sync_state = (!$rent_has_error && $rent_active_exists) ? 'Sincronizado' : ($rent_has_error ? 'Revisar flujo' : 'Pendiente de activar');
        ?>
        <div class="wrap dlh-import-page">
            <div class="dlh-page-hero">
                <div>
                    <h1>Gestión de bases</h1>
                    <p>Panel técnico para validar, activar y diagnosticar la base <strong>24-25</strong> y la base de <strong>rentabilidad</strong> sin romper el flujo actual del dashboard.</p>
                </div>
                <div class="dlh-page-hero__meta">
                    <span class="dlh-pill <?php echo $rent_has_error ? 'is-error' : 'is-success'; ?>"><?php echo $rent_has_error ? 'Rentabilidad con observaciones' : 'Rentabilidad operativa'; ?></span>
                    <span class="dlh-pill"><?php echo esc_html($using_mode_label); ?></span>
                </div>
            </div>

            <?php if ($notice) : ?>
                <div class="dlh-inline-notice <?php echo esc_attr($notice_type === 'error' ? 'is-error' : 'is-success'); ?>">
                    <span class="dashicons <?php echo esc_attr($notice_type === 'error' ? 'dashicons-warning' : 'dashicons-yes-alt'); ?>" aria-hidden="true"></span>
                    <div>
                        <strong><?php echo $notice_type === 'error' ? 'Proceso detenido' : 'Proceso completado'; ?></strong>
                        <p><?php echo esc_html(rawurldecode($notice)); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="dlh-import-layout">
                <main class="dlh-import-main">
                    <section class="dlh-card dlh-card--accent">
                        <div class="dlh-card__header">
                            <div>
                                <h2>Base rentabilidad</h2>
                                <p>Selecciona un archivo <code>.csv</code> o <code>.xlsx</code>, valida la estructura y activa la base automáticamente si pasa el checklist técnico.</p>
                            </div>
                            <span class="dlh-pill is-strong">Flujo seguro</span>
                        </div>

                        <div class="dlh-upload-panel">
                            <form method="post" enctype="multipart/form-data" class="dlh-upload-form">
                                <?php wp_nonce_field('dlh_base_2425_action'); ?>
                                <input type="hidden" name="dlh_import_action" value="upload_validate_rentabilidad" />
                                <label for="dlh-rentabilidad-file" class="dlh-field-label">Archivo de rentabilidad</label>
                                <input type="file" id="dlh-rentabilidad-file" name="dlh_rentabilidad_file" accept=".csv,.xlsx" class="dlh-file-input" />
                                <p class="description">Permitido: CSV o XLSX. El archivo subido se valida en temporal antes de reemplazar la fuente actual.</p>
                                <div id="dlh-rent-file-feedback" class="dlh-file-feedback">
                                    <strong>Ningún archivo seleccionado</strong>
                                    <span>Selecciona un CSV o XLSX para rentabilidad.</span>
                                </div>
                                <div class="dlh-action-row">
                                    <button type="submit" class="button button-primary">Validar y activar</button>
                                    <?php if ($rent_source) : ?>
                                        <button type="submit" name="dlh_import_action" value="validate_rentabilidad" class="button button-secondary">Revalidar base actual</button>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <div class="dlh-upload-sideinfo">
                                <div class="dlh-mini-stat">
                                    <span class="dlh-mini-stat__label">Archivo esperado</span>
                                    <strong><code>base-rentabilidad.csv</code> / <code>base-rentabilidad.xlsx</code></strong>
                                </div>
                                <div class="dlh-mini-stat">
                                    <span class="dlh-mini-stat__label">Destino activo</span>
                                    <strong><code>temporada-rentabilidad.csv</code></strong>
                                </div>
                                <div class="dlh-mini-stat">
                                    <span class="dlh-mini-stat__label">Activación</span>
                                    <strong>Automática después de validar</strong>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="dlh-card">
                        <div class="dlh-card__header">
                            <div>
                                <h2>Columnas detectadas</h2>
                                <p>Vista rápida para confirmar si el archivo trae los encabezados correctos antes de usarlo en el dashboard.</p>
                            </div>
                            <span class="dlh-pill"><?php echo esc_html(number_format_i18n(count($rent_recognized))); ?> detectadas</span>
                        </div>

                        <?php if (!empty($rent_recognized)) : ?>
                            <div class="dlh-tag-groups">
                                <div>
                                    <h3>Reconocidas</h3>
                                    <div class="dlh-tag-list">
                                        <?php foreach ($rent_expected_labels as $key => $label) : ?>
                                            <?php if (in_array($key, $rent_recognized, true)) : ?>
                                                <span class="dlh-tag is-success"><?php echo esc_html($label); ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div>
                                    <h3>Faltantes</h3>
                                    <div class="dlh-tag-list">
                                        <?php if (!empty($rent_missing)) : ?>
                                            <?php foreach ($rent_missing as $label) : ?>
                                                <span class="dlh-tag is-muted"><?php echo esc_html($label); ?></span>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <span class="dlh-tag is-success">Sin faltantes</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="dlh-empty-state">
                                <span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
                                <div>
                                    <strong>Aún no hay columnas analizables.</strong>
                                    <p>Sube o revalida una base de rentabilidad para ver el preview técnico.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="dlh-card dlh-card--legacy">
                        <div class="dlh-card__header">
                            <div>
                                <h2>Base 24-25</h2>
                                <p>Módulo legacy por FTP. Se mantiene operativo y separado del flujo nuevo de rentabilidad.</p>
                            </div>
                            <span class="dlh-pill"><?php echo $source ? 'Fuente detectada' : 'Sin fuente'; ?></span>
                        </div>
                        <div class="dlh-legacy-grid">
                            <div>
                                <p><strong>Fuente:</strong> <?php echo $source ? '<code>' . esc_html(basename($source)) . '</code>' : 'No detectada'; ?></p>
                                <p><strong>Activo:</strong> <?php echo $active_exists ? 'Sí' : 'No'; ?></p>
                                <p><strong>Última activación:</strong> <?php echo esc_html((string) get_option('last_import_time', '—')); ?></p>
                            </div>
                            <div>
                                <form method="post" class="dlh-inline-form">
                                    <?php wp_nonce_field('dlh_base_2425_action'); ?>
                                    <input type="hidden" name="dlh_import_action" value="validate" />
                                    <button type="submit" class="button button-secondary">Validar 24-25</button>
                                </form>
                                <form method="post" class="dlh-inline-form">
                                    <?php wp_nonce_field('dlh_base_2425_action'); ?>
                                    <input type="hidden" name="dlh_import_action" value="activate" />
                                    <button type="submit" class="button">Activar 24-25</button>
                                </form>
                            </div>
                        </div>
                        <?php if (is_array($analysis) && !is_wp_error($analysis)) : ?>
                            <p class="description">Filas válidas detectadas: <strong><?php echo esc_html(number_format_i18n((int) $analysis['valid_rows'])); ?></strong>. Advertencias: <strong><?php echo esc_html(number_format_i18n((int) $analysis['warnings_count'])); ?></strong>.</p>
                        <?php endif; ?>
                    </section>
                </main>

                <aside class="dlh-import-side">
                    <section class="dlh-card">
                        <div class="dlh-card__header">
                            <div>
                                <h2>Estado actual</h2>
                                <p>Resumen operativo del archivo fuente y la base activa de rentabilidad.</p>
                            </div>
                            <span class="dlh-pill <?php echo $rent_active_exists ? 'is-success' : ''; ?>"><?php echo $rent_active_exists ? 'Activa' : 'Sin activa'; ?></span>
                        </div>
                        <dl class="dlh-keyvals">
                            <div><dt>Nombre</dt><dd><?php echo esc_html($rent_meta['original_name']); ?></dd></div>
                            <div><dt>Fecha</dt><dd><?php echo esc_html($rent_meta['uploaded_at']); ?></dd></div>
                            <div><dt>Tamaño</dt><dd><?php echo $rent_meta['size'] ? esc_html(size_format((int) $rent_meta['size'])) : '—'; ?></dd></div>
                            <div><dt>Formato</dt><dd><?php echo esc_html(strtoupper($rent_meta['extension'] ?: '—')); ?></dd></div>
                            <div><dt>Modo actual</dt><dd><?php echo esc_html($using_mode_label); ?></dd></div>
                            <div><dt>Activo modificado</dt><dd><?php echo esc_html($rent_active_modified); ?></dd></div>
                        </dl>
                        <?php if (!empty($rent_status['content_error'])) : ?>
                            <div class="dlh-inline-alert is-error">
                                <strong>Error actual</strong>
                                <p><?php echo esc_html($rent_status['content_error']); ?></p>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="dlh-card">
                        <div class="dlh-card__header">
                            <div>
                                <h2>Checklist de validación</h2>
                                <p>Control visual para detectar si el archivo está listo para activar sin tocar la base vigente por error.</p>
                            </div>
                        </div>
                        <div class="dlh-checklist">
                            <?php foreach ($rent_checklist as $item) : ?>
                                <div class="dlh-checklist__item <?php echo !empty($item['ok']) ? 'is-ok' : 'is-bad'; ?>">
                                    <span class="dashicons <?php echo !empty($item['ok']) ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
                                    <div>
                                        <strong><?php echo esc_html($item['label']); ?></strong>
                                        <small><?php echo esc_html((string) $item['meta']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($rent_validation['time'])) : ?>
                            <p class="description">Última validación registrada: <strong><?php echo esc_html((string) $rent_validation['time']); ?></strong></p>
                        <?php endif; ?>
                    </section>

                    <section class="dlh-card">
                        <div class="dlh-card__header">
                            <div>
                                <h2>Diagnóstico técnico</h2>
                                <p>Estado compacto del dataset renderizable para ayudar a depurar carga, columnas y métricas.</p>
                            </div>
                        </div>
                        <div class="dlh-diag-grid">
                            <article><span>Filas renderizables</span><strong><?php echo esc_html(number_format_i18n((int) (!empty($rent_diagnostics['rows']) ? $rent_diagnostics['rows'] : 0))); ?></strong></article>
                            <article><span>Columnas reconocidas</span><strong><?php echo esc_html(number_format_i18n(count($rent_recognized))); ?></strong></article>
                            <article><span>Métricas detectadas</span><strong><?php echo esc_html(number_format_i18n(count($rent_metrics))); ?></strong></article>
                            <article><span>Estado</span><strong><?php echo $rent_has_error ? 'Con observaciones' : 'Listo'; ?></strong></article>
                        </div>
                        <?php if (!empty($rent_metrics)) : ?>
                            <div class="dlh-tag-list dlh-tag-list--spaced">
                                <?php foreach ($rent_metrics as $metric_key) : ?>
                                    <span class="dlh-tag is-info"><?php echo esc_html(isset($rent_expected_labels[$metric_key]) ? $rent_expected_labels[$metric_key] : $metric_key); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="dlh-card">
                        <div class="dlh-card__header">
                            <div>
                                <h2>Sincronización frontend</h2>
                                <p>Confirma qué dataset sirve el endpoint y si coincide con la base normalizada activada.</p>
                            </div>
                            <span class="dlh-pill <?php echo $rent_has_error ? 'is-error' : 'is-success'; ?>"><?php echo esc_html($rent_sync_state); ?></span>
                        </div>
                        <dl class="dlh-keyvals">
                            <div><dt>Endpoint CSV</dt><dd><code><?php echo esc_html($rent_endpoint_url); ?></code></dd></div>
                            <div><dt>Endpoint diagnóstico</dt><dd><code><?php echo esc_html($rent_diag_endpoint_url); ?></code></dd></div>
                            <div><dt>Hash contenido</dt><dd><code><?php echo esc_html(!empty($rent_status['content_hash']) ? substr((string) $rent_status['content_hash'], 0, 12) : '—'); ?></code></dd></div>
                            <div><dt>Hash activo</dt><dd><code><?php echo esc_html(!empty($rent_status['active_hash']) ? substr((string) $rent_status['active_hash'], 0, 12) : '—'); ?></code></dd></div>
                            <div><dt>Tamaño activo</dt><dd><?php echo !empty($rent_status['active_size']) ? esc_html(size_format((int) $rent_status['active_size'])) : '—'; ?></dd></div>
                            <div><dt>Tamaño fuente</dt><dd><?php echo !empty($rent_status['source_size']) ? esc_html(size_format((int) $rent_status['source_size'])) : '—'; ?></dd></div>
                        </dl>
                    </section>

                    <?php if (!empty($rent_warnings)) : ?>
                        <section class="dlh-card dlh-card--warn">
                            <div class="dlh-card__header">
                                <div>
                                    <h2>Observaciones</h2>
                                    <p>Primeras alertas detectadas en la fuente actual.</p>
                                </div>
                                <span class="dlh-pill is-warning"><?php echo esc_html(number_format_i18n(count($rent_warnings))); ?></span>
                            </div>
                            <ul class="dlh-warning-list">
                                <?php foreach (array_slice($rent_warnings, 0, 8) as $warning) : ?>
                                    <li><?php echo esc_html($warning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
        <?php
    }
}

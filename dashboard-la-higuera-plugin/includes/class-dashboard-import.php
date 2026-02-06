<?php
/**
 * Clase para gestionar Base 24-25 por FTP en el admin.
 */

if (!defined('WPINC')) {
    die;
}

class Dashboard_Higuera_Import {

    const SUBMENU_SLUG = 'dlh-import-2425';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_submenu'));
        add_action('admin_init', array(__CLASS__, 'handle_post_actions'));
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
        return DASHBOARD_HIGUERA_PLUGIN_DIR . 'data/temporada-2024-25.csv';
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

        if (file_exists($csv)) {
            return $csv;
        }
        if (file_exists($xlsx)) {
            return $xlsx;
        }

        return null;
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
            $normalized = self::build_normalized_csv($analysis);
            $target = self::get_target_path();
            $backup = $target . '.bak.' . gmdate('Ymd_His');

            if (file_exists($target)) {
                @copy($target, $backup);
            }

            $written = file_put_contents($target, $normalized);
            if ($written === false) {
                self::redirect_with_notice('error', 'No se pudo escribir la base activa en data/temporada-2024-25.csv.');
            }

            // Si la fuente fue XLSX, guardar también CSV normalizado en ruta FTP fija .csv
            if ($analysis['format'] === 'xlsx') {
                @file_put_contents(self::get_source_csv_path(), $normalized);
            }

            update_option('last_import_time', current_time('mysql'));
            update_option('last_rows', (int) $analysis['valid_rows']);
            update_option('last_warnings', (int) $analysis['warnings_count']);

            self::redirect_with_notice('success', 'Base 24-25 activada correctamente. Respaldo: ' . basename($backup));
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
            if (!is_array($row)) {
                return false;
            }
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    return true;
                }
            }
            return false;
        }));

        if (count($rows) < 2) {
            return new WP_Error('few_records', 'No se detectaron registros suficientes en la base.');
        }

        $header = array_map('strval', $rows[0]);
        $colCount = count($header);
        if ($colCount < 2) {
            return new WP_Error('bad_header', 'No se pudo detectar un encabezado válido.');
        }

        $validRows = 0;
        $incomplete = 0;
        $excessive = 0;
        $warnings = array();
        $dataRows = array();

        for ($i = 1; $i < count($rows); $i++) {
            $row = array_map('strval', $rows[$i]);
            $count = count($row);
            if ($count === $colCount) {
                $validRows++;
            } elseif ($count < $colCount) {
                $incomplete++;
                $warnings[] = 'Fila ' . ($i + 1) . ': incompleta (' . $count . '/' . $colCount . ').';
            } else {
                $excessive++;
                $warnings[] = 'Fila ' . ($i + 1) . ': con columnas extra (' . $count . '/' . $colCount . ').';
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
        $cols = $analysis['header_columns'];

        $fp = fopen('php://temp', 'w+');
        fputcsv($fp, $header, ';', '"');

        foreach ($analysis['records'] as $row) {
            if (count($row) < $cols) {
                $row = array_pad($row, $cols, '');
            } elseif (count($row) > $cols) {
                $row = array_slice($row, 0, $cols);
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

        $source = self::resolve_source_path();
        $target = self::get_target_path();
        $notice = isset($_GET['dlh_notice']) ? sanitize_text_field(wp_unslash($_GET['dlh_notice'])) : '';
        $notice_type = isset($_GET['dlh_notice_type']) ? sanitize_text_field(wp_unslash($_GET['dlh_notice_type'])) : 'success';

        $analysis = $source ? self::analyze_source($source) : null;
        $csvHint = '/wp-content/uploads/dashboard-higuera/base-24-25.csv';
        $xlsxHint = '/wp-content/uploads/dashboard-higuera/base-24-25.xlsx';
        ?>
        <div class="wrap">
            <h1>Base 24-25 (FTP)</h1>
            <?php if ($notice) : ?>
                <div class="notice notice-<?php echo esc_attr($notice_type === 'error' ? 'error' : 'success'); ?> is-dismissible"><p><?php echo esc_html(rawurldecode($notice)); ?></p></div>
            <?php endif; ?>

            <div class="card" style="max-width:1000px;padding:16px;">
                <h2>Estado del archivo FTP</h2>
                <?php if (!$source) : ?>
                    <p><strong>No existe la base FTP.</strong></p>
                    <p>Sube el archivo por FTP a <code><?php echo esc_html($csvHint); ?></code> (o Excel en <code><?php echo esc_html($xlsxHint); ?></code>).</p>
                <?php else : ?>
                    <p><strong>Archivo fuente:</strong> <code><?php echo esc_html(str_replace(trailingslashit(ABSPATH), '', $source)); ?></code></p>
                    <ul>
                        <li><strong>Formato:</strong> <?php echo esc_html(strtoupper(pathinfo($source, PATHINFO_EXTENSION))); ?></li>
                        <li><strong>Tamaño:</strong> <?php echo esc_html(size_format(filesize($source))); ?></li>
                        <li><strong>Modificado:</strong> <?php echo esc_html(date_i18n('Y-m-d H:i:s', filemtime($source))); ?></li>
                        <?php if (is_wp_error($analysis)) : ?>
                            <li><strong>Estado:</strong> <?php echo esc_html($analysis->get_error_message()); ?></li>
                        <?php else : ?>
                            <li><strong>Encoding detectado:</strong> <?php echo esc_html($analysis['encoding']); ?></li>
                            <li><strong>Delimitador detectado:</strong> <code><?php echo esc_html($analysis['delimiter']); ?></code></li>
                            <li><strong>Filas válidas detectadas:</strong> <?php echo esc_html(number_format_i18n($analysis['valid_rows'])); ?></li>
                            <li><strong>Advertencias:</strong> <?php echo esc_html(number_format_i18n($analysis['warnings_count'])); ?> (incompletas: <?php echo esc_html(number_format_i18n($analysis['incomplete_count'])); ?>, excesivas: <?php echo esc_html(number_format_i18n($analysis['excessive_count'])); ?>)</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width:1000px;padding:16px;margin-top:16px;">
                <h2>Acciones</h2>
                <form method="post" style="display:inline-block;margin-right:12px;">
                    <?php wp_nonce_field('dlh_base_2425_action'); ?>
                    <input type="hidden" name="dlh_import_action" value="validate" />
                    <button type="submit" class="button button-secondary">Validar</button>
                </form>

                <form method="post" style="display:inline-block;">
                    <?php wp_nonce_field('dlh_base_2425_action'); ?>
                    <input type="hidden" name="dlh_import_action" value="activate" />
                    <button type="submit" class="button button-primary">Activar base 24-25</button>
                </form>

                <p class="description" style="margin-top:12px;">
                    Al activar, la base (CSV o XLSX) se normaliza a CSV UTF-8 con delimitador <code>;</code> y se guarda en
                    <code>wp-content/plugins/dashboard-la-higuera-plugin/data/temporada-2024-25.csv</code>
                    con respaldo automático <code>.bak.TIMESTAMP</code>.
                    Si la fuente es XLSX, también se genera <code>base-24-25.csv</code> en uploads.
                </p>
            </div>

            <?php if (!is_wp_error($analysis) && !empty($analysis['warnings'])) : ?>
                <div class="card" style="max-width:1000px;padding:16px;margin-top:16px;">
                    <h2>Advertencias detectadas</h2>
                    <ul style="max-height:220px;overflow:auto;">
                        <?php foreach (array_slice($analysis['warnings'], 0, 200) as $warning) : ?>
                            <li><?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width:1000px;padding:16px;margin-top:16px;">
                <h2>Última activación</h2>
                <ul>
                    <li><strong>last_import_time:</strong> <?php echo esc_html((string) get_option('last_import_time', '—')); ?></li>
                    <li><strong>last_rows:</strong> <?php echo esc_html((string) get_option('last_rows', '—')); ?></li>
                    <li><strong>last_warnings:</strong> <?php echo esc_html((string) get_option('last_warnings', '—')); ?></li>
                    <li><strong>Destino activo:</strong> <code><?php echo esc_html(str_replace(trailingslashit(ABSPATH), '', $target)); ?></code></li>
                </ul>
            </div>
        </div>
        <?php
    }
}

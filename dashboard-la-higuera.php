<?php
/**
 * Plugin Name: Dashboard La Higuera
 * Plugin URI: https://github.com/Jonianc/Dashboard-Higuera
 * Description: Dashboard interactivo para visualizar datos de costos y faenas de Agrícola La Higuera
 * Version: 1.18.0
 * Author: Agrícola La Higuera S.A.
 * Author URI: https://lahiguera.cl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dashboard-la-higuera
 * Domain Path: /languages
 */

// Si este archivo es llamado directamente, abortar.
if (!defined('WPINC')) {
    die;
}

// Definir constantes del plugin
define('DASHBOARD_HIGUERA_VERSION', '1.18.0');
define('DASHBOARD_HIGUERA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DASHBOARD_HIGUERA_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Clase principal del plugin
 */
class Dashboard_La_Higuera {

    /**
     * Instancia única del plugin (Singleton)
     */
    private static $instance = null;

    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Cargar dependencias
     */
    private function load_dependencies() {
        require_once DASHBOARD_HIGUERA_PLUGIN_DIR . 'includes/class-dashboard-shortcode.php';
        require_once DASHBOARD_HIGUERA_PLUGIN_DIR . 'includes/class-dashboard-standalone.php';
        require_once DASHBOARD_HIGUERA_PLUGIN_DIR . 'includes/class-dashboard-settings.php';
        require_once DASHBOARD_HIGUERA_PLUGIN_DIR . 'includes/class-dashboard-import.php';
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Registrar shortcode
        add_action('init', array($this, 'register_shortcode'));

        // Encolar scripts y estilos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Registrar template de página personalizado
        add_filter('theme_page_templates', array($this, 'add_page_template'));
        add_filter('template_include', array($this, 'load_page_template'));

        // Registrar endpoints REST para servir CSVs
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Frontend standalone (sin theme)
        Dashboard_Higuera_Standalone::init();

        // Panel de ajustes e importación en el admin
        if (is_admin()) {
            Dashboard_Higuera_Settings::init();
            Dashboard_Higuera_Import::init();
        }

        // REST endpoint de importación (necesario fuera de is_admin para REST API)
        Dashboard_Higuera_Import::register_rest_hooks();
    }

    /**
     * Obtener URL de API configurada sin exponer secretos por defecto.
     * Prioridad: constante DLH_API_URL > opción dlh_api_url > vacío.
     *
     * @return string
     */
    public static function get_configured_api_url() {
        if (defined('DLH_API_URL') && is_string(DLH_API_URL) && trim(DLH_API_URL) !== '') {
            return esc_url_raw(trim(DLH_API_URL));
        }

        $stored = (string) get_option('dlh_api_url', '');
        if (trim($stored) === '') {
            return '';
        }

        return esc_url_raw(trim($stored));
    }

    /**
     * Registrar shortcode
     */
    public function register_shortcode() {
        add_shortcode('dashboard_higuera', array('Dashboard_Higuera_Shortcode', 'render'));
    }

    /**
     * Encolar assets (CSS y JS)
     */
    public function enqueue_assets() {
        // Solo cargar si la página contiene el shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dashboard_higuera')) {
            // CSS
            wp_enqueue_style(
                'dashboard-higuera-css',
                DASHBOARD_HIGUERA_PLUGIN_URL . 'assets/css/dashboard.css',
                array(),
                DASHBOARD_HIGUERA_VERSION
            );

            // JavaScript
            wp_enqueue_script(
                'dashboard-higuera-js',
                DASHBOARD_HIGUERA_PLUGIN_URL . 'assets/js/dashboard.js',
                array(),
                DASHBOARD_HIGUERA_VERSION,
                true
            );

            $last_2425_updated = get_option('last_2425_updated', '');
            if (!$last_2425_updated) {
                $uploads = wp_upload_dir();
                $file_2425 = trailingslashit($uploads['basedir']) . 'dashboard-higuera/temporada-2024-25.csv';
                if (file_exists($file_2425)) {
                    $last_2425_updated = date_i18n('Y-m-d H:i:s', filemtime($file_2425));
                }
            }
            $debug = defined('WP_DEBUG') && WP_DEBUG;

            $rentabilidad_csv_content = Dashboard_Higuera_Import::get_rentabilidad_csv_content();
            $rentabilidad_inline_csv = '';
            if (!is_wp_error($rentabilidad_csv_content) && strlen((string) $rentabilidad_csv_content) <= 1024 * 1024) {
                $rentabilidad_inline_csv = (string) $rentabilidad_csv_content;
            }
            $rentabilidad_status = Dashboard_Higuera_Import::get_rentabilidad_dataset_status();

            // Pasar URLs de datos CSV a JavaScript usando REST API
            wp_localize_script('dashboard-higuera-js', 'dashboardHigueraData', array(
                'csv2526Url' => rest_url('dashboard-higuera/v1/csv/2025-26'),
                'csv2425Url' => rest_url('dashboard-higuera/v1/csv/2024-25'),
                'pluginUrl' => DASHBOARD_HIGUERA_PLUGIN_URL,
                'restNonce' => wp_create_nonce('wp_rest'),
                'dataSource' => get_option('dlh_data_source', 'api_fallback_csv'),
                'apiUrl' => self::get_configured_api_url(),
                'apiSourceMode' => get_option('dlh_api_source_mode', 'url'),
                'apiPowerBIFormula' => get_option('dlh_api_powerbi_formula', ''),
                'apiProxyUrl' => rest_url('dashboard-higuera/v1/api/2025-26'),
                'last2425Updated' => $last_2425_updated,
                'debug' => $debug,
                'csvRentabilidadUrl' => rest_url('dashboard-higuera/v1/csv/rentabilidad'),
                'csvRentabilidadDiagnosticsUrl' => rest_url('dashboard-higuera/v1/csv/rentabilidad-diagnostics'),
                'rentabilidadCsvInline' => $rentabilidad_inline_csv,
                'rentabilidadStatus' => array(
                    'mode' => isset($rentabilidad_status['using']) ? (string) $rentabilidad_status['using'] : 'none',
                    'contentHash' => isset($rentabilidad_status['content_hash']) ? (string) $rentabilidad_status['content_hash'] : '',
                    'activeHash' => isset($rentabilidad_status['active_hash']) ? (string) $rentabilidad_status['active_hash'] : '',
                    'contentError' => isset($rentabilidad_status['content_error']) ? (string) $rentabilidad_status['content_error'] : '',
                ),
                'rentabilidadCards' => get_option('dlh_rentabilidad_cards', array()),
                'rentabilidadIcons' => array(
                    'sector' => esc_url_raw((string) get_option('dlh_rent_sector_icon_url', '')),
                    'cuartel' => esc_url_raw((string) get_option('dlh_rent_cuartel_icon_url', '')),
                ),
            ));
        }
    }

    /**
     * Agregar template de página personalizado a la lista
     *
     * @param array $templates Templates existentes
     * @return array Templates con el nuevo agregado
     */
    public function add_page_template($templates) {
        $templates['templates/template-dashboard-fullwidth.php'] = 'Dashboard Full Width (Sin Header/Footer)';
        return $templates;
    }

    /**
     * Cargar el template personalizado cuando se selecciona
     *
     * @param string $template Ruta del template actual
     * @return string Ruta del template a usar
     */
    public function load_page_template($template) {
        global $post;

        if (!$post) {
            return $template;
        }

        $page_template = get_post_meta($post->ID, '_wp_page_template', true);

        if ('templates/template-dashboard-fullwidth.php' === $page_template) {
            $plugin_template = DASHBOARD_HIGUERA_PLUGIN_DIR . 'templates/template-dashboard-fullwidth.php';

            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Registrar rutas REST API para servir archivos CSV
     */
    public function register_rest_routes() {
        // Ruta para CSV 2025-26
        register_rest_route('dashboard-higuera/v1', '/csv/2025-26', array(
            'methods' => 'GET',
            'callback' => array($this, 'serve_csv_2526'),
            'permission_callback' => array($this, 'rest_can_access_csv')
        ));


        // Ruta para CSV rentabilidad
        register_rest_route('dashboard-higuera/v1', '/csv/rentabilidad', array(
            'methods' => 'GET',
            'callback' => array($this, 'serve_csv_rentabilidad'),
            'permission_callback' => array($this, 'rest_can_access_csv')
        ));
        register_rest_route('dashboard-higuera/v1', '/csv/rentabilidad-diagnostics', array(
            'methods' => 'GET',
            'callback' => array($this, 'serve_csv_rentabilidad_diagnostics'),
            'permission_callback' => array($this, 'rest_can_access_csv')
        ));
        // Ruta para CSV 2024-25
        register_rest_route('dashboard-higuera/v1', '/csv/2024-25', array(
            'methods' => 'GET',
            'callback' => array($this, 'serve_csv_2425'),
            'permission_callback' => array($this, 'rest_can_access_csv')
        ));

        // Ruta API 25-26 con caché server-side
        register_rest_route('dashboard-higuera/v1', '/api/2025-26', array(
            'methods' => 'GET',
            'callback' => array($this, 'serve_api_2526_cached'),
            'permission_callback' => array($this, 'rest_can_access_csv')
        ));
    }

    /**
     * Permisos para endpoints REST de CSV.
     * Respeta el mismo control de acceso configurado para standalone.
     *
     * @return bool|WP_Error
     */
    public function rest_can_access_csv() {
        if ($this->user_has_csv_access_for_rest_request()) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            'No tienes permisos para acceder a estos datos.',
            array('status' => $this->is_logged_in_for_csv_access() ? 403 : 401)
        );
    }

    /**
     * Determinar acceso a CSV para requests REST.
     *
     * En REST, WordPress requiere nonce para autenticar cookies de sesión.
     * Para peticiones GET del dashboard (lectura), aceptamos también la cookie
     * de login estándar para mantener compatibilidad con fetch() sin nonce.
     *
     * @return bool
     */
    private function user_has_csv_access_for_rest_request() {
        $access = get_option('dlh_access', 'public');

        if ($access === 'public') {
            return true;
        }

        if (!$this->is_logged_in_for_csv_access()) {
            return false;
        }

        if ($access === 'logged_in') {
            return true;
        }

        if ($access === 'role') {
            $allowed_roles = get_option('dlh_roles', 'administrator');
            $roles = array_filter(array_map('trim', explode(',', (string) $allowed_roles)));
            $user = $this->get_user_for_csv_access();
            if (!$user instanceof WP_User || empty($user->roles)) {
                return false;
            }

            foreach ($roles as $role) {
                if (in_array($role, $user->roles, true)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Verificar autenticación para acceso CSV en contexto REST.
     *
     * @return bool
     */
    private function is_logged_in_for_csv_access() {
        if (is_user_logged_in()) {
            return true;
        }

        return $this->get_logged_in_user_id_from_cookie() > 0;
    }

    /**
     * Resolver usuario para validación de roles en acceso CSV.
     *
     * @return WP_User|null
     */
    private function get_user_for_csv_access() {
        $user = wp_get_current_user();
        if ($user instanceof WP_User && $user->exists()) {
            return $user;
        }

        $cookie_user_id = $this->get_logged_in_user_id_from_cookie();
        if ($cookie_user_id <= 0) {
            return null;
        }

        $cookie_user = get_userdata($cookie_user_id);
        return ($cookie_user instanceof WP_User) ? $cookie_user : null;
    }

    /**
     * Obtener user ID desde cookie auth de WordPress sin requerir nonce REST.
     *
     * @return int
     */
    private function get_logged_in_user_id_from_cookie() {
        if (!function_exists('wp_validate_auth_cookie')) {
            return 0;
        }

        $user_id = wp_validate_auth_cookie('', 'logged_in');
        return is_numeric($user_id) ? (int) $user_id : 0;
    }

    /**
     * Resolver URL fuente para API 25-26 según ajustes.
     *
     * @return string
     */
    private function resolve_api_source_url() {
        $mode = get_option('dlh_api_source_mode', 'url');

        if ($mode === 'powerbi') {
            $formula = (string) get_option('dlh_api_powerbi_formula', '');
            if (preg_match('/Web\.Contents\(\s*"([^"]+)"\s*\)/i', trim($formula), $m)) {
                return esc_url_raw(trim($m[1]));
            }
        }

        return self::get_configured_api_url();
    }

    /**
     * Servir API 25-26 con caché en transients para reducir latencia.
     *
     * @return array|WP_Error
     */
    public function serve_api_2526_cached() {
        $source_url = $this->resolve_api_source_url();
        if (trim((string) $source_url) === '') {
            return new WP_Error('api_not_configured', 'URL de API no configurada', array('status' => 503));
        }

        $cache_key = 'dlh_api_2526_' . md5($source_url);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get($source_url, array('timeout' => 15));
        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', 'No se pudo consultar la API 25-26', array('status' => 502));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300 || trim((string) $body) === '') {
            return new WP_Error('api_invalid_response', 'Respuesta inválida desde API 25-26', array('status' => 502));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return new WP_Error('api_invalid_json', 'La API 25-26 no devolvió JSON válido', array('status' => 502));
        }

        $ttl = (int) apply_filters('dlh_api_cache_ttl', 300);
        if ($ttl < 30) {
            $ttl = 30;
        }

        set_transient($cache_key, $decoded, $ttl);

        return $decoded;
    }

    /**
     * Servir archivo CSV 2025-26
     */
    public function serve_csv_2526() {
        $file = DASHBOARD_HIGUERA_PLUGIN_DIR . 'data/temporada-2025-26.csv';

        if (!file_exists($file) || !is_readable($file)) {
            return new WP_Error('file_not_found', 'Archivo CSV no encontrado o no legible', array('status' => 404));
        }

        // Servir CSV crudo (no JSON) para evitar que WP REST lo envuelva como string JSON.
        $content = file_get_contents($file);
        if ($content === false || trim($content) === '') {
            return new WP_Error('file_empty', 'Archivo CSV vacío o no se pudo leer', array('status' => 404));
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $content;
        exit;
    }

    /**
     * Servir archivo CSV 2024-25
     */

    /**
     * Servir archivo CSV de rentabilidad
     */
    public function serve_csv_rentabilidad() {
        $content = Dashboard_Higuera_Import::get_rentabilidad_csv_content();
        if (is_wp_error($content)) {
            return new WP_Error('rent_csv_unavailable', $content->get_error_message(), array('status' => 404));
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $content;
        exit;
    }

    public function serve_csv_rentabilidad_diagnostics() {
        $status = Dashboard_Higuera_Import::get_rentabilidad_dataset_status();
        return array(
            'mode' => isset($status['using']) ? $status['using'] : 'none',
            'active_exists' => !empty($status['active_exists']),
            'active_size' => !empty($status['active_size']) ? (int) $status['active_size'] : 0,
            'active_hash' => !empty($status['active_hash']) ? (string) $status['active_hash'] : '',
            'content_hash' => !empty($status['content_hash']) ? (string) $status['content_hash'] : '',
            'content_error' => !empty($status['content_error']) ? (string) $status['content_error'] : '',
            'diagnostics' => !empty($status['diagnostics']) && is_array($status['diagnostics']) ? $status['diagnostics'] : array(),
        );
    }

    public function serve_csv_2425() {
        $uploads = wp_upload_dir();
        $file = trailingslashit($uploads['basedir']) . 'dashboard-higuera/temporada-2024-25.csv';

        if (!file_exists($file) || !is_readable($file)) {
            return new WP_Error(
                'file_not_found',
                'Archivo CSV no encontrado o no legible',
                array(
                    'status' => 404,
                    'path' => $file,
                )
            );
        }

        $size = filesize($file);
        if ($size === false || $size < 50) {
            return new WP_Error(
                'file_too_small',
                'Archivo CSV vacío o demasiado pequeño',
                array(
                    'status' => 404,
                    'path' => $file,
                    'size' => $size,
                )
            );
        }

        // Servir CSV crudo (no JSON) para que el frontend lo pueda parsear directo.
        $content = file_get_contents($file);
        if ($content === false || trim($content) === '') {
            return new WP_Error(
                'read_failed',
                'No se pudo leer el CSV 24-25 o está vacío',
                array('status' => 404, 'path' => $file)
            );
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $content;
        exit;
    }
}

/**
 * Inicializar el plugin
 */
function dashboard_la_higuera_init() {
    return Dashboard_La_Higuera::get_instance();
}

// Iniciar el plugin
dashboard_la_higuera_init();

// Flush rewrite rules al activar el plugin
register_activation_hook(__FILE__, function () {
    // Cargar dependencias primero
    require_once plugin_dir_path(__FILE__) . 'includes/class-dashboard-standalone.php';
    Dashboard_Higuera_Standalone::flush_rules();
});

// Flush rewrite rules al desactivar el plugin
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

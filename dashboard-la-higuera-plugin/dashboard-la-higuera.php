<?php
/**
 * Plugin Name: Dashboard La Higuera
 * Plugin URI: https://github.com/Jonianc/Dashboard-Higuera
 * Description: Dashboard interactivo para visualizar datos de costos y faenas de Agrícola La Higuera
 * Version: 1.4.1
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
define('DASHBOARD_HIGUERA_VERSION', '1.4.1');
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

            // Pasar URLs de datos CSV a JavaScript usando REST API
            wp_localize_script('dashboard-higuera-js', 'dashboardHigueraData', array(
                'csv2526Url' => rest_url('dashboard-higuera/v1/csv/2025-26'),
                'csv2425Url' => rest_url('dashboard-higuera/v1/csv/2024-25'),
                'pluginUrl' => DASHBOARD_HIGUERA_PLUGIN_URL,
                'restNonce' => wp_create_nonce('wp_rest')
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
            'permission_callback' => '__return_true'
        ));

        // Ruta para CSV 2024-25
        register_rest_route('dashboard-higuera/v1', '/csv/2024-25', array(
            'methods' => 'GET',
            'callback' => array($this, 'serve_csv_2425'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Servir archivo CSV 2025-26
     */
    public function serve_csv_2526() {
        $file = DASHBOARD_HIGUERA_PLUGIN_DIR . 'data/temporada-2025-26.csv';

        if (!file_exists($file)) {
            return new WP_Error('file_not_found', 'Archivo CSV no encontrado', array('status' => 404));
        }

        $content = file_get_contents($file);

        return new WP_REST_Response($content, 200, array(
            'Content-Type' => 'text/csv; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600'
        ));
    }

    /**
     * Servir archivo CSV 2024-25
     */
    public function serve_csv_2425() {
        $file = DASHBOARD_HIGUERA_PLUGIN_DIR . 'data/temporada-2024-25.csv';

        if (!file_exists($file)) {
            return new WP_Error('file_not_found', 'Archivo CSV no encontrado', array('status' => 404));
        }

        $content = file_get_contents($file);

        return new WP_REST_Response($content, 200, array(
            'Content-Type' => 'text/csv; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600'
        ));
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

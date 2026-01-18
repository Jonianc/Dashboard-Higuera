<?php
/**
 * Plugin Name: Dashboard La Higuera
 * Plugin URI: https://github.com/Jonianc/Dashboard-Higuera
 * Description: Dashboard interactivo para visualizar datos de costos y faenas de Agrícola La Higuera
 * Version: 1.0.0
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
define('DASHBOARD_HIGUERA_VERSION', '1.0.0');
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
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Registrar shortcode
        add_action('init', array($this, 'register_shortcode'));

        // Encolar scripts y estilos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
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

            // Pasar URLs de datos CSV a JavaScript
            wp_localize_script('dashboard-higuera-js', 'dashboardHigueraData', array(
                'csv2526Url' => DASHBOARD_HIGUERA_PLUGIN_URL . 'data/temporada-2025-26.csv',
                'csv2425Url' => DASHBOARD_HIGUERA_PLUGIN_URL . 'data/temporada-2024-25.csv',
                'pluginUrl' => DASHBOARD_HIGUERA_PLUGIN_URL
            ));
        }
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

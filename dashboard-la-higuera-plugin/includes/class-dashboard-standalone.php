<?php
/**
 * Clase para manejar el frontend standalone del dashboard
 *
 * Intercepta una URL configurable y renderiza el dashboard
 * como HTML completo sin cargar el theme de WordPress.
 */

if (!defined('WPINC')) {
    die;
}

class Dashboard_Higuera_Standalone {

    /**
     * Inicializar hooks
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_rewrite'));
        add_action('template_redirect', array(__CLASS__, 'render_standalone'), 1);
    }

    /**
     * Registrar rewrite rule para el slug configurado
     */
    public static function register_rewrite() {
        $slug = self::get_slug();
        add_rewrite_rule(
            '^' . preg_quote($slug, '/') . '/?$',
            'index.php?dlh_standalone=1',
            'top'
        );
        add_rewrite_tag('%dlh_standalone%', '([0-9]+)');
    }

    /**
     * Obtener el slug configurado (por defecto 'dashboard')
     */
    public static function get_slug() {
        $slug = get_option('dlh_slug', 'dashboard');
        return sanitize_title($slug);
    }

    /**
     * Interceptar la petición y renderizar el dashboard standalone
     */
    public static function render_standalone() {
        if (!get_query_var('dlh_standalone')) {
            return;
        }

        // Verificar acceso
        if (!self::check_access()) {
            if (!is_user_logged_in()) {
                wp_redirect(wp_login_url(home_url('/' . self::get_slug() . '/')));
                exit;
            }
            wp_die(
                'No tienes permisos para acceder a este dashboard.',
                'Acceso denegado',
                array('response' => 403)
            );
        }

        // Verificar si los assets están habilitados
        $load_assets = get_option('dlh_load_assets', '1');
        if (!$load_assets) {
            wp_die(
                'El dashboard está desactivado actualmente.',
                'Dashboard desactivado',
                array('response' => 503)
            );
        }

        // Renderizar HTML completo y salir (sin theme)
        self::output_html();
        exit;
    }

    /**
     * Verificar si el usuario actual tiene acceso
     */
    private static function check_access() {
        $access = get_option('dlh_access', 'public');

        if ($access === 'public') {
            return true;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        if ($access === 'logged_in') {
            return true;
        }

        // Acceso por rol
        if ($access === 'role') {
            $allowed_roles = get_option('dlh_roles', 'administrator');
            $roles = array_map('trim', explode(',', $allowed_roles));
            $user = wp_get_current_user();
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
     * Generar y enviar el HTML completo del dashboard standalone
     */
    private static function output_html() {
        $plugin_url = DASHBOARD_HIGUERA_PLUGIN_URL;
        $version    = DASHBOARD_HIGUERA_VERSION;
        $css_url    = $plugin_url . 'assets/css/dashboard.css?ver=' . $version;
        $js_url     = $plugin_url . 'assets/js/dashboard.js?ver=' . $version;
        $rest_nonce = wp_create_nonce('wp_rest');

        // URLs de datos
        $csv2526_url = rest_url('dashboard-higuera/v1/csv/2025-26');
        $csv2425_url = rest_url('dashboard-higuera/v1/csv/2024-25');

        // Fuente de datos configurada
        $data_source = get_option('dlh_data_source', 'api_fallback_csv');
        $api_url     = get_option('dlh_api_url', 'https://app.agrosmart.cl/v1/api/reporte/base_consolidada.php?token=02376e47a4771e34fcba564f88a9d4fbc42a0c40894ebd8e3ba0d60039bd4528');
        $last_2425_updated = get_option('last_2425_updated', '');
        if (!$last_2425_updated) {
            $uploads = wp_upload_dir();
            $file_2425 = trailingslashit($uploads['basedir']) . 'dashboard-higuera/temporada-2024-25.csv';
            if (file_exists($file_2425)) {
                $last_2425_updated = date_i18n('Y-m-d H:i:s', filemtime($file_2425));
            }
        }
        $debug = defined('WP_DEBUG') && WP_DEBUG;

        $charset = get_bloginfo('charset');
        $lang    = get_language_attributes();

        // Capturar el template del dashboard
        ob_start();
        include DASHBOARD_HIGUERA_PLUGIN_DIR . 'templates/dashboard-template.php';
        $dashboard_html = ob_get_clean();

        ?><!DOCTYPE html>
<html <?php echo $lang; ?>>
<head>
    <meta charset="<?php echo esc_attr($charset); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard — Agrícola La Higuera</title>
    <link rel="stylesheet" href="<?php echo esc_url($css_url); ?>">
    <style>
        /* Standalone: reset completo */
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: #0f1115;
            color: #e6e9f2;
            font-family: Inter, system-ui, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            overflow-x: hidden;
        }
        #dashboard-higuera-wrapper {
            max-width: 100%;
            width: 100%;
            padding: 0;
            margin: 0;
        }
        #dashboard-higuera-wrapper header {
            border-radius: 0;
        }
        #dashboard-higuera-wrapper .container {
            max-width: 100%;
            padding-left: 20px;
            padding-right: 20px;
        }
        @media (min-width: 1920px) {
            #dashboard-higuera-wrapper .container {
                max-width: 1800px;
                margin-left: auto;
                margin-right: auto;
            }
        }
        @keyframes loading {
            0%   { transform: translateX(-100%); }
            50%  { transform: translateX(200%); }
            100% { transform: translateX(-100%); }
        }
    </style>
</head>
<body>
    <?php echo $dashboard_html; ?>
    <script>
        var dashboardHigueraData = {
            csv2526Url: <?php echo wp_json_encode($csv2526_url); ?>,
            csv2425Url: <?php echo wp_json_encode($csv2425_url); ?>,
            pluginUrl:  <?php echo wp_json_encode($plugin_url); ?>,
            restNonce:  <?php echo wp_json_encode($rest_nonce); ?>,
            dataSource: <?php echo wp_json_encode($data_source); ?>,
            apiUrl:     <?php echo wp_json_encode($api_url); ?>,
            last2425Updated: <?php echo wp_json_encode($last_2425_updated); ?>,
            debug: <?php echo wp_json_encode($debug); ?>
        };
    </script>
    <script src="<?php echo esc_url($js_url); ?>"></script>
</body>
</html>
<?php
    }

    /**
     * Flush rewrite rules (llamar en activación o cambio de slug)
     */
    public static function flush_rules() {
        self::register_rewrite();
        flush_rewrite_rules();
    }
}

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

    private static function get_standalone_header_defaults() {
        return array(
            'enabled' => '0',
            'show_logo' => '0',
            'logo_url' => '',
            'logo_alt' => 'Logo Agrícola La Higuera',
            'logo_height' => 44,
            'show_title' => '1',
            'title' => 'Dashboard — Agrícola La Higuera',
            'show_subtitle' => '1',
            'subtitle' => 'Temporada 2025–2026',
            'sticky' => '1',
            'show_logout' => '1',
        );
    }

    public static function get_standalone_header_settings() {
        $defaults = self::get_standalone_header_defaults();
        $settings = array(
            'enabled' => get_option('dlh_standalone_header_enabled', $defaults['enabled']) === '1' ? '1' : '0',
            'show_logo' => get_option('dlh_standalone_header_show_logo', $defaults['show_logo']) === '1' ? '1' : '0',
            'logo_url' => esc_url_raw((string) get_option('dlh_standalone_header_logo_url', $defaults['logo_url'])),
            'logo_alt' => sanitize_text_field((string) get_option('dlh_standalone_header_logo_alt', $defaults['logo_alt'])),
            'logo_height' => (int) get_option('dlh_standalone_header_logo_height', $defaults['logo_height']),
            'show_title' => get_option('dlh_standalone_header_show_title', $defaults['show_title']) === '1' ? '1' : '0',
            'title' => sanitize_text_field((string) get_option('dlh_standalone_header_title', $defaults['title'])),
            'show_subtitle' => get_option('dlh_standalone_header_show_subtitle', $defaults['show_subtitle']) === '1' ? '1' : '0',
            'subtitle' => sanitize_text_field((string) get_option('dlh_standalone_header_subtitle', $defaults['subtitle'])),
            'sticky' => get_option('dlh_standalone_header_sticky', $defaults['sticky']) === '1' ? '1' : '0',
            'show_logout' => get_option('dlh_standalone_header_show_logout', $defaults['show_logout']) === '1' ? '1' : '0',
        );

        if ($settings['logo_height'] < 20) {
            $settings['logo_height'] = 20;
        }
        if ($settings['logo_height'] > 48) {
            $settings['logo_height'] = 48;
        }
        return $settings;
    }

    /**
     * Validar acceso según ajustes configurados (public/logged_in/role).
     *
     * @return bool
     */
    public static function user_has_access_by_settings() {
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

        // Procesar portal con contraseña (solo standalone)
        self::handle_password_portal();

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
        return self::user_has_access_by_settings();
    }


    private static function handle_password_portal() {
        if (!self::is_password_portal_enabled()) {
            return;
        }

        if (isset($_GET['dlh_portal_logout'])) {
            self::clear_password_portal_cookie();
            wp_safe_redirect(home_url('/' . self::get_slug() . '/'));
            exit;
        }

        if (self::has_password_portal_cookie()) {
            return;
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dlh_portal_nonce'])) {
            check_admin_referer('dlh_portal_access', 'dlh_portal_nonce');
            $password = isset($_POST['dlh_portal_password']) ? (string) wp_unslash($_POST['dlh_portal_password']) : '';
            if (self::verify_password_portal($password)) {
                self::set_password_portal_cookie();
                wp_safe_redirect(home_url('/' . self::get_slug() . '/'));
                exit;
            }
            $error = 'Contraseña incorrecta. Intenta nuevamente.';
        }

        self::output_password_portal($error);
        exit;
    }

    private static function is_password_portal_enabled() {
        $enabled = get_option('dlh_password_portal_enabled', '0') === '1';
        $hash = trim((string) get_option('dlh_password_portal_password', ''));
        return $enabled && $hash !== '';
    }

    private static function verify_password_portal($password) {
        $hash = (string) get_option('dlh_password_portal_password', '');
        if ($hash === '') {
            return false;
        }
        return wp_check_password((string) $password, $hash);
    }

    private static function get_password_portal_cookie_name() {
        return 'dlh_portal_access';
    }

    private static function has_password_portal_cookie() {
        $name = self::get_password_portal_cookie_name();
        if (empty($_COOKIE[$name])) {
            return false;
        }

        $value = (string) wp_unslash($_COOKIE[$name]);
        $parts = explode('|', $value);
        if (count($parts) !== 2) {
            return false;
        }

        $expires = (int) $parts[0];
        $token = (string) $parts[1];
        if ($expires < time()) {
            return false;
        }

        $hash = (string) get_option('dlh_password_portal_password', '');
        if ($hash === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $expires . '|' . $hash, wp_salt('auth'));
        return hash_equals($expected, $token);
    }

    private static function set_password_portal_cookie() {
        $expires = time() + (8 * HOUR_IN_SECONDS);
        $hash = (string) get_option('dlh_password_portal_password', '');
        $token = hash_hmac('sha256', $expires . '|' . $hash, wp_salt('auth'));
        $value = $expires . '|' . $token;

        setcookie(
            self::get_password_portal_cookie_name(),
            $value,
            $expires,
            COOKIEPATH ? COOKIEPATH : '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }

    private static function clear_password_portal_cookie() {
        setcookie(
            self::get_password_portal_cookie_name(),
            '',
            time() - HOUR_IN_SECONDS,
            COOKIEPATH ? COOKIEPATH : '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }

    private static function output_password_portal($error = '') {
        $charset = get_bloginfo('charset');
        $lang = get_language_attributes();
        $title = get_option('dlh_password_portal_title', 'Acceso al Dashboard');
        $message = get_option('dlh_password_portal_message', 'Ingresa la contraseña para continuar.');
        $version = DASHBOARD_HIGUERA_VERSION;
        $portal_css_url = DASHBOARD_HIGUERA_PLUGIN_URL . 'assets/css/dashboard-portal.css?ver=' . $version;
        $portal_js_url = DASHBOARD_HIGUERA_PLUGIN_URL . 'assets/js/dashboard-portal.js?ver=' . $version;
        ?>
<!DOCTYPE html>
<html <?php echo $lang; ?>>
<head>
    <meta charset="<?php echo esc_attr($charset); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($title); ?></title>
</head>
<body class="dlh-portal-page">
    <main class="dlh-portal-shell" aria-label="Portal de acceso">
        <form method="post" class="dlh-portal" id="dlh-portal-form" novalidate>
            <div class="dlh-portal-head">
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo esc_html($message); ?></p>
            </div>

            <?php if (!empty($error)) : ?>
                <div class="dlh-portal-error" role="alert" aria-live="polite"><?php echo esc_html($error); ?></div>
            <?php endif; ?>

            <div class="dlh-portal-field">
                <label for="dlh_portal_password">Contraseña</label>
                <div class="dlh-portal-password-wrap">
                    <input type="password" id="dlh_portal_password" name="dlh_portal_password" required autocomplete="current-password" autofocus>
                    <button type="button" class="dlh-portal-toggle" id="dlh-portal-toggle-password" aria-controls="dlh_portal_password" aria-label="Mostrar contraseña" aria-pressed="false">Mostrar</button>
                </div>
            </div>

            <?php wp_nonce_field('dlh_portal_access', 'dlh_portal_nonce'); ?>
            <input type="hidden" name="dlh_portal_submit" value="1">

            <button type="submit" class="dlh-portal-submit" id="dlh-portal-submit">Ingresar al dashboard</button>
            <div class="dlh-portal-meta">La sesión de acceso expira automáticamente después de 8 horas.</div>
        </form>
    </main>
    <script src="<?php echo esc_url($portal_js_url); ?>"></script>
</body>
</html>
<?php
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
        $header_settings = self::get_standalone_header_settings();
        $logout_url = '';
        if (self::is_password_portal_enabled()) {
            $logout_url = add_query_arg('dlh_portal_logout', '1', home_url('/' . self::get_slug() . '/'));
        }

        // URLs de datos
        $csv2526_url = rest_url('dashboard-higuera/v1/csv/2025-26');
        $csv2425_url = rest_url('dashboard-higuera/v1/csv/2024-25');

        // Fuente de datos configurada
        $data_source = get_option('dlh_data_source', 'api_fallback_csv');
        $api_url     = Dashboard_La_Higuera::get_configured_api_url();
        $api_source_mode = get_option('dlh_api_source_mode', 'url');
        $api_powerbi_formula = get_option('dlh_api_powerbi_formula', '');
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

        $charset = get_bloginfo('charset');
        $lang    = get_language_attributes();

        // Capturar el template del dashboard
        $dlh_is_standalone = true;
        $dlh_standalone_header_settings = $header_settings;
        $dlh_standalone_logout_url = $logout_url;
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
            apiSourceMode: <?php echo wp_json_encode($api_source_mode); ?>,
            apiPowerBIFormula: <?php echo wp_json_encode($api_powerbi_formula); ?>,
            apiProxyUrl: <?php echo wp_json_encode(rest_url('dashboard-higuera/v1/api/2025-26')); ?>,
            last2425Updated: <?php echo wp_json_encode($last_2425_updated); ?>,
            debug: <?php echo wp_json_encode($debug); ?>,
            csvRentabilidadUrl: <?php echo wp_json_encode(rest_url('dashboard-higuera/v1/csv/rentabilidad')); ?>,
            csvRentabilidadDiagnosticsUrl: <?php echo wp_json_encode(rest_url('dashboard-higuera/v1/csv/rentabilidad-diagnostics')); ?>,
            rentabilidadCsvInline: <?php echo wp_json_encode($rentabilidad_inline_csv); ?>,
            rentabilidadStatus: <?php echo wp_json_encode(array(
                'mode' => isset($rentabilidad_status['using']) ? (string) $rentabilidad_status['using'] : 'none',
                'contentHash' => isset($rentabilidad_status['content_hash']) ? (string) $rentabilidad_status['content_hash'] : '',
                'activeHash' => isset($rentabilidad_status['active_hash']) ? (string) $rentabilidad_status['active_hash'] : '',
                'contentError' => isset($rentabilidad_status['content_error']) ? (string) $rentabilidad_status['content_error'] : '',
            )); ?>,
            rentabilidadCards: <?php echo wp_json_encode(get_option('dlh_rentabilidad_cards', array())); ?>,
            rentabilidadIcons: <?php echo wp_json_encode(array(
                'sector' => esc_url_raw((string) get_option('dlh_rent_sector_icon_url', '')),
                'cuartel' => esc_url_raw((string) get_option('dlh_rent_cuartel_icon_url', '')),
            )); ?>
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

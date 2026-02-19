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
        ?>
<!DOCTYPE html>
<html <?php echo $lang; ?>>
<head>
    <meta charset="<?php echo esc_attr($charset); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($title); ?></title>
    <style>
        :root{color-scheme:dark}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:radial-gradient(circle at top,#1a2031 0%,#0f1115 55%);color:#e6e9f2;font-family:Inter,system-ui,-apple-system,sans-serif;padding:20px}
        .dlh-portal{width:100%;max-width:440px;background:rgba(21,24,34,.94);backdrop-filter:blur(6px);border:1px solid #2a3147;border-radius:16px;padding:28px;box-shadow:0 12px 40px rgba(0,0,0,.35)}
        .dlh-portal h1{margin:0 0 8px;font-size:24px;line-height:1.25}
        .dlh-portal p{margin:0 0 18px;color:#b2bbd1;line-height:1.5}
        .dlh-portal label{display:block;margin-bottom:8px;color:#d7def1;font-size:14px;font-weight:600}
        .dlh-portal input{width:100%;height:48px;padding:0 14px;border-radius:10px;border:1px solid #354262;background:#0f1320;color:#fff;outline:0;transition:border-color .2s,box-shadow .2s}
        .dlh-portal input:focus{border-color:#6ea8ff;box-shadow:0 0 0 3px rgba(78,140,255,.25)}
        .dlh-portal button{margin-top:14px;width:100%;height:48px;padding:0 14px;border:0;border-radius:10px;background:linear-gradient(180deg,#6ea8ff,#4f8cff);color:#fff;font-weight:700;cursor:pointer;transition:transform .06s ease,opacity .2s}
        .dlh-portal button:hover{opacity:.95}
        .dlh-portal button:active{transform:translateY(1px)}
        .dlh-portal button[disabled]{opacity:.7;cursor:wait}
        .dlh-portal .meta{margin-top:12px;font-size:12px;color:#8f9bb8;line-height:1.45}
        .dlh-portal .error{margin-top:12px;padding:10px 12px;border:1px solid #7d3340;background:#2a1720;color:#ffb3be;border-radius:10px;font-size:14px}
    </style>
</head>
<body>
    <form method="post" class="dlh-portal" id="dlh-portal-form" novalidate>
        <h1><?php echo esc_html($title); ?></h1>
        <p><?php echo esc_html($message); ?></p>
        <label for="dlh_portal_password">Contraseña</label>
        <input type="password" id="dlh_portal_password" name="dlh_portal_password" required autocomplete="current-password" autofocus>
        <?php wp_nonce_field('dlh_portal_access', 'dlh_portal_nonce'); ?>
        <input type="hidden" name="dlh_portal_submit" value="1">
        <button type="submit" id="dlh-portal-submit">Ingresar al dashboard</button>
        <div class="meta">La sesión de acceso expira automáticamente después de 8 horas.</div>
        <?php if (!empty($error)) : ?>
            <div class="error" role="alert" aria-live="polite"><?php echo esc_html($error); ?></div>
        <?php endif; ?>
    </form>
    <script>
    (function(){
      var form=document.getElementById('dlh-portal-form');
      var btn=document.getElementById('dlh-portal-submit');
      if(!form||!btn){return;}
      form.addEventListener('submit',function(){
        if(form.classList.contains('is-submitting')){
          return;
        }
        form.classList.add('is-submitting');
        btn.disabled=true;
        btn.textContent='Validando...';
      });
    })();
    </script>
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

        // URLs de datos
        $csv2526_url = rest_url('dashboard-higuera/v1/csv/2025-26');
        $csv2425_url = rest_url('dashboard-higuera/v1/csv/2024-25');

        // Fuente de datos configurada
        $data_source = get_option('dlh_data_source', 'api_fallback_csv');
        $api_url     = get_option('dlh_api_url', 'https://app.agrosmart.cl/v1/api/reporte/base_consolidada.php?token=02376e47a4771e34fcba564f88a9d4fbc42a0c40894ebd8e3ba0d60039bd4528');
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
        .dlh-portal-bar {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 9999;
        }
        .dlh-portal-logout {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 8px;
            background: #1e2433;
            color: #ffffff;
            border: 1px solid #2f3b55;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }
        .dlh-portal-logout:hover {
            background: #252f45;
        }
    </style>
</head>
<body>
    <?php if (self::is_password_portal_enabled()) : ?>
        <div class="dlh-portal-bar">
            <a href="<?php echo esc_url(add_query_arg('dlh_portal_logout', '1', home_url('/' . self::get_slug() . '/'))); ?>" class="dlh-portal-logout">Cerrar acceso</a>
        </div>
    <?php endif; ?>
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

<?php
/**
 * Clase para manejar la página de ajustes del plugin en el admin
 */

if (!defined('WPINC')) {
    die;
}

class Dashboard_Higuera_Settings {

    /** Slug de la página de opciones */
    const MENU_SLUG = 'dlh-settings';

    /** Grupo de opciones */
    const OPTION_GROUP = 'dlh_options';

    /**
     * Inicializar hooks del admin
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('wp_ajax_dlh_test_api_sources', array(__CLASS__, 'ajax_test_api_sources'));
    }

    /**
     * Cargar CSS/JS de ajustes solo en la pantalla del plugin
     */
    public static function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'dlh-admin-settings-css',
            DASHBOARD_HIGUERA_PLUGIN_URL . 'assets/css/admin-settings.css',
            array(),
            DASHBOARD_HIGUERA_VERSION
        );

        wp_enqueue_script(
            'dlh-admin-settings-js',
            DASHBOARD_HIGUERA_PLUGIN_URL . 'assets/js/admin-settings.js',
            array(),
            DASHBOARD_HIGUERA_VERSION,
            true
        );

        wp_localize_script('dlh-admin-settings-js', 'dlhSettings', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dlh_test_api_sources'),
            'labels' => array(
                'running' => 'Probando fuentes API…',
                'runTest' => 'Test de API (velocidad)',
                'errorExec' => 'Error ejecutando test.',
                'errorNetwork' => 'Error de red al ejecutar test.',
                'best' => 'Más rápida',
            ),
        ));
    }

    /**
     * Agregar menú "Dashboard" en el admin
     */
    public static function add_menu() {
        add_menu_page(
            'Ajustes Dashboard La Higuera',    // Título de la página
            'Dashboard',                        // Título del menú
            'manage_options',                   // Capacidad requerida
            self::MENU_SLUG,                    // Slug del menú
            array(__CLASS__, 'render_page'),    // Callback
            'dashicons-chart-area',             // Icono
            30                                  // Posición
        );
    }

    /**
     * Registrar todas las opciones con la Settings API
     */
    public static function register_settings() {

        // --- Sección: URL / Slug ---
        add_settings_section(
            'dlh_section_url',
            'URL del Dashboard',
            array(__CLASS__, 'section_url_cb'),
            self::MENU_SLUG
        );

        register_setting(self::OPTION_GROUP, 'dlh_slug', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_slug'),
            'default'           => 'dashboard',
        ));

        add_settings_field('dlh_slug', 'Slug / ruta', array(__CLASS__, 'field_slug'), self::MENU_SLUG, 'dlh_section_url');

        // --- Sección: Acceso ---
        add_settings_section(
            'dlh_section_access',
            'Control de acceso',
            array(__CLASS__, 'section_access_cb'),
            self::MENU_SLUG
        );

        register_setting(self::OPTION_GROUP, 'dlh_access', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'public',
        ));

        add_settings_field('dlh_access', 'Tipo de acceso', array(__CLASS__, 'field_access'), self::MENU_SLUG, 'dlh_section_access');

        register_setting(self::OPTION_GROUP, 'dlh_roles', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'administrator',
        ));

        add_settings_field('dlh_roles', 'Roles permitidos', array(__CLASS__, 'field_roles'), self::MENU_SLUG, 'dlh_section_access');


        register_setting(self::OPTION_GROUP, 'dlh_password_portal_enabled', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
            'default'           => '0',
        ));

        add_settings_field('dlh_password_portal_enabled', 'Portal con contraseña (standalone)', array(__CLASS__, 'field_password_portal_enabled'), self::MENU_SLUG, 'dlh_section_access');

        register_setting(self::OPTION_GROUP, 'dlh_password_portal_password', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_portal_password'),
            'default'           => '',
        ));

        add_settings_field('dlh_password_portal_password', 'Contraseña del portal', array(__CLASS__, 'field_password_portal_password'), self::MENU_SLUG, 'dlh_section_access');

        register_setting(self::OPTION_GROUP, 'dlh_password_portal_title', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Acceso al Dashboard',
        ));

        add_settings_field('dlh_password_portal_title', 'Título portal', array(__CLASS__, 'field_password_portal_title'), self::MENU_SLUG, 'dlh_section_access');

        register_setting(self::OPTION_GROUP, 'dlh_password_portal_message', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Ingresa la contraseña para continuar.',
        ));

        add_settings_field('dlh_password_portal_message', 'Mensaje portal', array(__CLASS__, 'field_password_portal_message'), self::MENU_SLUG, 'dlh_section_access');

        // --- Sección: Assets ---
        add_settings_section(
            'dlh_section_assets',
            'CSS / JS del Dashboard',
            array(__CLASS__, 'section_assets_cb'),
            self::MENU_SLUG
        );

        register_setting(self::OPTION_GROUP, 'dlh_load_assets', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
            'default'           => '1',
        ));

        add_settings_field('dlh_load_assets', 'Cargar assets', array(__CLASS__, 'field_load_assets'), self::MENU_SLUG, 'dlh_section_assets');

        // --- Sección: Fuente de datos ---
        add_settings_section(
            'dlh_section_data',
            'Fuente de datos',
            array(__CLASS__, 'section_data_cb'),
            self::MENU_SLUG
        );

        register_setting(self::OPTION_GROUP, 'dlh_data_source', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_data_source'),
            'default'           => 'api_fallback_csv',
        ));

        add_settings_field('dlh_data_source', 'Origen de datos', array(__CLASS__, 'field_data_source'), self::MENU_SLUG, 'dlh_section_data');

        register_setting(self::OPTION_GROUP, 'dlh_api_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ));

        add_settings_field('dlh_api_url', 'URL de la API', array(__CLASS__, 'field_api_url'), self::MENU_SLUG, 'dlh_section_data');

        register_setting(self::OPTION_GROUP, 'dlh_api_source_mode', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_api_source_mode'),
            'default'           => 'url',
        ));

        add_settings_field('dlh_api_source_mode', 'Fuente API 25-26', array(__CLASS__, 'field_api_source_mode'), self::MENU_SLUG, 'dlh_section_data');

        register_setting(self::OPTION_GROUP, 'dlh_api_powerbi_formula', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ));

        add_settings_field('dlh_api_powerbi_formula', 'Fórmula Power BI (opcional)', array(__CLASS__, 'field_api_powerbi_formula'), self::MENU_SLUG, 'dlh_section_data');
    }

    /* ================================================================
       Callbacks de secciones
       ================================================================ */

    public static function section_url_cb() {
        $slug = Dashboard_Higuera_Standalone::get_slug();
        echo '<p>Define la URL donde se mostrará el dashboard standalone (sin theme).</p>';
        echo '<p><strong>URL actual:</strong> <code>' . esc_html(home_url('/' . $slug . '/')) . '</code></p>';
    }

    public static function section_access_cb() {
        echo '<p>Controla quién puede ver el dashboard en la URL standalone y, opcionalmente, agrega una capa de contraseña.</p>';
    }

    public static function section_assets_cb() {
        echo '<p>Activa o desactiva la carga del CSS y JavaScript del dashboard.</p>';
    }

    public static function section_data_cb() {
        echo '<p>Configuración de datos del dashboard (v1.6.0+).</p>';
    }

    /* ================================================================
       Callbacks de campos
       ================================================================ */

    public static function field_slug() {
        $val = get_option('dlh_slug', 'dashboard');
        echo '<input type="text" name="dlh_slug" value="' . esc_attr($val) . '" class="regular-text" />';
        echo '<p class="description">Solo letras, números y guiones. Ejemplo: <code>dashboard</code>, <code>mi-panel</code></p>';
    }

    public static function field_access() {
        $val = get_option('dlh_access', 'public');
        $options = array(
            'public'    => 'Público (cualquier visitante)',
            'logged_in' => 'Solo usuarios logueados',
            'role'      => 'Solo ciertos roles',
        );
        echo '<select name="dlh_access" id="dlh_access">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($val, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public static function field_roles() {
        $val = get_option('dlh_roles', 'administrator');
        $all_roles = wp_roles()->get_names();
        echo '<fieldset id="dlh_roles_wrap">';
        $selected = array_map('trim', explode(',', $val));
        foreach ($all_roles as $role_slug => $role_name) {
            $checked = in_array($role_slug, $selected, true) ? 'checked' : '';
            echo '<label style="display:block;margin-bottom:4px;">';
            echo '<input type="checkbox" name="dlh_roles_arr[]" value="' . esc_attr($role_slug) . '" ' . $checked . '> ';
            echo esc_html(translate_user_role($role_name));
            echo '</label>';
        }
        echo '</fieldset>';
        // Hidden field que se llenará via JS
        echo '<input type="hidden" name="dlh_roles" id="dlh_roles_hidden" value="' . esc_attr($val) . '">';
        echo '<p class="description">Solo aplica si el tipo de acceso es "Solo ciertos roles".</p>';
    }


    public static function field_password_portal_enabled() {
        $val = get_option('dlh_password_portal_enabled', '0');
        echo '<label>';
        echo '<input type="checkbox" name="dlh_password_portal_enabled" value="1" ' . checked($val, '1', false) . '>';
        echo ' Activar portal de contraseña solo para URL standalone';
        echo '</label>';
        echo '<p class="description">Si está activo, se solicitará contraseña antes de mostrar el dashboard standalone.</p>';
    }

    public static function field_password_portal_password() {
        $has_password = trim((string) get_option('dlh_password_portal_password', '')) !== '';
        echo '<input type="password" name="dlh_password_portal_password" id="dlh_password_portal_password" value="" class="regular-text" autocomplete="new-password" />';
        echo '<p class="description">Deja este campo vacío para mantener la contraseña actual. Escribe una nueva para reemplazarla.</p>';
        echo '<p class="description"><strong>Estado actual:</strong> ' . ($has_password ? 'Contraseña configurada' : 'Sin contraseña configurada') . '. Recomendado: mínimo 10 caracteres.</p>';
    }

    public static function field_password_portal_title() {
        $val = get_option('dlh_password_portal_title', 'Acceso al Dashboard');
        echo '<input type="text" name="dlh_password_portal_title" value="' . esc_attr($val) . '" class="regular-text" />';
    }

    public static function field_password_portal_message() {
        $val = get_option('dlh_password_portal_message', 'Ingresa la contraseña para continuar.');
        echo '<input type="text" name="dlh_password_portal_message" value="' . esc_attr($val) . '" class="large-text" />';
    }

    public static function field_load_assets() {
        $val = get_option('dlh_load_assets', '1');
        echo '<label>';
        echo '<input type="checkbox" name="dlh_load_assets" value="1" ' . checked($val, '1', false) . '>';
        echo ' Activar CSS y JS del dashboard';
        echo '</label>';
        echo '<p class="description">Si se desactiva, el dashboard no se renderizará en la URL standalone.</p>';
    }

    public static function field_data_source() {
        $val = get_option('dlh_data_source', 'api_fallback_csv');
        $current = ($val === 'api_fallback_csv') ? 'API Agrosmart con fallback CSV 25-26' : 'Modo legado';
        echo '<input type="hidden" name="dlh_data_source" value="api_fallback_csv" />';
        echo '<span><strong>' . esc_html($current) . '</strong></span>';
        echo '<p class="description">Desde v1.6.0: 25-26 se carga desde API al iniciar (fallback CSV solo si falla API). 24-25 se carga desde CSV al entrar a Comparativo.</p>';
    }

    public static function field_api_url() {
        $val = Dashboard_La_Higuera::get_configured_api_url();
        echo '<div id="dlh-api-url-group">';
        echo '<input type="url" name="dlh_api_url" id="dlh_api_url" value="' . esc_attr($val) . '" class="large-text" />';
        echo '<p class="description dlh-field-feedback" id="dlh-api-url-feedback" aria-live="polite"></p>';
        echo '</div>';
        echo '<p class="description">URL completa de API (incluyendo token si aplica) usada para cargar 25-26 al iniciar.</p>';
        if (defined('DLH_API_URL') && trim((string) DLH_API_URL) !== '') {
            echo '<p class="description"><strong>Fuente activa:</strong> constante <code>DLH_API_URL</code> (tiene prioridad sobre esta opción).</p>';
        }
    }

    public static function field_api_source_mode() {
        $val = get_option('dlh_api_source_mode', 'url');
        echo '<select name="dlh_api_source_mode" id="dlh_api_source_mode">';
        echo '<option value="url"' . selected($val, 'url', false) . '>Usar URL de la API</option>';
        echo '<option value="powerbi"' . selected($val, 'powerbi', false) . '>Usar Fórmula Power BI</option>';
        echo '</select>';
        echo '<p class="description">Define qué entrada usar para cargar datos 25-26: URL de API o fórmula Power BI.</p>';
    }

    public static function field_api_powerbi_formula() {
        $val = get_option('dlh_api_powerbi_formula', '');
        echo '<div id="dlh-powerbi-group">';
        echo '<textarea name="dlh_api_powerbi_formula" id="dlh_api_powerbi_formula" rows="4" class="large-text code" placeholder="=Json.Document(Web.Contents(&quot;https://...&quot;))">' . esc_textarea($val) . '</textarea>';
        echo '<p class="description dlh-field-feedback" id="dlh-powerbi-feedback" aria-live="polite"></p>';
        echo '</div>';
        echo '<p class="description">Pega la fórmula de Power BI. Si en "Fuente API 25-26" eliges "Usar Fórmula Power BI", se extraerá la URL dentro de <code>Web.Contents("...")</code>.</p>';
        echo '<p><button type="button" class="button" id="dlh-test-api-sources">Test de API (velocidad)</button></p>';
        echo '<div id="dlh-test-api-results" class="dlh-test-results" role="status" aria-live="polite"></div>';
    }

    /* ================================================================
       Sanitización
       ================================================================ */

    /**
     * Sanitizar el slug y hacer flush de rewrite rules si cambió
     */
    public static function sanitize_slug($input) {
        $new_slug = sanitize_title($input);
        if (empty($new_slug)) {
            $new_slug = 'dashboard';
        }
        $old_slug = get_option('dlh_slug', 'dashboard');
        if ($new_slug !== $old_slug) {
            // Programar flush de rewrite rules después de guardar
            add_action('shutdown', array('Dashboard_Higuera_Standalone', 'flush_rules'));
        }
        return $new_slug;
    }

    public static function sanitize_checkbox($input) {
        return $input ? '1' : '0';
    }


    public static function sanitize_portal_password($input) {
        $raw = trim((string) $input);
        $current = get_option('dlh_password_portal_password', '');

        if ($raw === '') {
            return $current;
        }

        return wp_hash_password($raw);
    }

    public static function sanitize_data_source($input) {
        return 'api_fallback_csv';
    }

    public static function sanitize_api_source_mode($input) {
        return ($input === 'powerbi') ? 'powerbi' : 'url';
    }

    private static function extract_url_from_powerbi_formula($formula) {
        $raw = trim((string) $formula);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/Web\.Contents\(\s*"([^"]+)"\s*\)/i', $raw, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private static function test_api_endpoint($label, $url) {
        $url = trim((string) $url);
        if ($url === '') {
            return array('label' => $label, 'url' => '', 'ok' => false, 'ms' => 0, 'rows' => 0);
        }

        $start = microtime(true);
        $resp = wp_remote_get($url, array('timeout' => 15));
        $ms = (int) round((microtime(true) - $start) * 1000);

        if (is_wp_error($resp)) {
            return array('label' => $label, 'url' => $url, 'ok' => false, 'ms' => $ms, 'rows' => 0);
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);

        $rows = 0;
        if (is_array($json)) {
            if (isset($json['data']) && is_array($json['data'])) {
                $rows = count($json['data']);
            } else {
                $rows = count($json);
            }
        }

        return array(
            'label' => $label,
            'url' => $url,
            'ok' => ($code >= 200 && $code < 300),
            'ms' => $ms,
            'rows' => $rows,
        );
    }

    public static function ajax_test_api_sources() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sin permisos.'), 403);
        }
        check_ajax_referer('dlh_test_api_sources', 'nonce');

        $api_url = get_option('dlh_api_url', '');
        $formula = get_option('dlh_api_powerbi_formula', '');
        $formula_url = self::extract_url_from_powerbi_formula($formula);

        $results = array();
        $results[] = self::test_api_endpoint('URL API', $api_url);
        if ($formula_url !== '') {
            $results[] = self::test_api_endpoint('Fórmula Power BI', $formula_url);
        }

        $ok = array_values(array_filter($results, function($r){ return !empty($r['ok']); }));
        usort($ok, function($a,$b){ return $a['ms'] <=> $b['ms']; });
        $fastest = !empty($ok) ? $ok[0] : null;

        wp_send_json_success(array(
            'results' => $results,
            'fastest' => $fastest,
        ));
    }

    /* ================================================================
       Renderizar página
       ================================================================ */

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Ajustes — Dashboard La Higuera</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::MENU_SLUG);
                submit_button('Guardar ajustes');
                ?>
            </form>
        </div>
        <?php
    }
}

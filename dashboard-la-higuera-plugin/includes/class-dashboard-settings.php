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
            'default'           => 'https://app.agrosmart.cl/v1/api/reporte/base_consolidada.php?token=02376e47a4771e34fcba564f88a9d4fbc42a0c40894ebd8e3ba0d60039bd4528',
        ));

        add_settings_field('dlh_api_url', 'URL de la API', array(__CLASS__, 'field_api_url'), self::MENU_SLUG, 'dlh_section_data');

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
        echo '<p>Controla quién puede ver el dashboard en la URL standalone.</p>';
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
        // JS para sincronizar checkboxes al hidden field
        ?>
        <script>
        (function(){
            function syncRoles(){
                var checks = document.querySelectorAll('input[name="dlh_roles_arr[]"]:checked');
                var vals = [];
                checks.forEach(function(c){ vals.push(c.value); });
                document.getElementById('dlh_roles_hidden').value = vals.join(',');
            }
            document.querySelectorAll('input[name="dlh_roles_arr[]"]').forEach(function(c){
                c.addEventListener('change', syncRoles);
            });

            // Mostrar/ocultar roles según el tipo de acceso
            var sel = document.getElementById('dlh_access');
            var wrap = document.getElementById('dlh_roles_wrap');
            function toggleRoles(){
                var show = sel.value === 'role';
                wrap.style.display = show ? '' : 'none';
                wrap.parentElement.previousElementSibling.style.display = show ? '' : 'none';
            }
            sel.addEventListener('change', toggleRoles);
            toggleRoles();
        })();
        </script>
        <?php
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
        $val = get_option('dlh_api_url', 'https://app.agrosmart.cl/v1/api/reporte/base_consolidada.php?token=02376e47a4771e34fcba564f88a9d4fbc42a0c40894ebd8e3ba0d60039bd4528');
        echo '<input type="url" name="dlh_api_url" value="' . esc_attr($val) . '" class="large-text" />';
        echo '<p class="description">URL completa de API (incluyendo token si aplica) usada para cargar 25-26 al iniciar.</p>';
    }

    public static function field_api_powerbi_formula() {
        $val = get_option('dlh_api_powerbi_formula', '');
        echo '<textarea name="dlh_api_powerbi_formula" rows="4" class="large-text code" placeholder="=Json.Document(Web.Contents(&quot;https://...&quot;))">' . esc_textarea($val) . '</textarea>';
        echo '<p class="description">Pega la fórmula de Power BI. El dashboard intentará extraer automáticamente la URL dentro de <code>Web.Contents("...")</code> y priorizarla sobre la URL de API.</p>';
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

    public static function sanitize_data_source($input) {
        return 'api_fallback_csv';
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

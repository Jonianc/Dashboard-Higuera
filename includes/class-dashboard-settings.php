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
    const PAGE_ACCESS = 'dlh-settings-access';
    const PAGE_DATA_API = 'dlh-settings-data-api';
    const PAGE_RENTABILIDAD = 'dlh-settings-rentabilidad';

    /** Grupo de opciones */
    const OPTION_GROUP = 'dlh_options';
    const OPTION_GROUP_ACCESS = 'dlh_options_access';
    const OPTION_GROUP_DATA_API = 'dlh_options_data_api';
    const OPTION_GROUP_RENTABILIDAD = 'dlh_options_rentabilidad';

    /**
     * Inicializar hooks del admin
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('wp_ajax_dlh_test_api_sources', array(__CLASS__, 'ajax_test_api_sources'));
        add_action('wp_ajax_dlh_fetch_rentabilidad_catalog', array(__CLASS__, 'ajax_fetch_rentabilidad_catalog'));
    }

    /**
     * Cargar CSS/JS de ajustes solo en la pantalla del plugin
     */
    public static function enqueue_assets($hook) {
        $allowed_pages = array(
            self::MENU_SLUG,
            self::PAGE_ACCESS,
            self::PAGE_DATA_API,
            self::PAGE_RENTABILIDAD,
        );
        $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (!in_array($current_page, $allowed_pages, true)) {
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
            array('jquery'),
            DASHBOARD_HIGUERA_VERSION,
            true
        );

        wp_enqueue_media();

        wp_localize_script('dlh-admin-settings-js', 'dlhSettings', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dlh_test_api_sources'),
            'rentNonce' => wp_create_nonce('dlh_fetch_rentabilidad_catalog'),
            'manualRows' => array_values(self::get_rentabilidad_manual_rows_option()),
            'labels' => array(
                'running' => 'Probando fuentes API…',
                'runTest' => 'Test de API (velocidad)',
                'errorExec' => 'Error ejecutando test.',
                'errorNetwork' => 'Error de red al ejecutar test.',
                'best' => 'Más rápida',
                'selectImage' => 'Seleccionar imagen',
                'replaceImage' => 'Reemplazar imagen',
                'removeImage' => 'Quitar',
                'useThisImage' => 'Usar este icono',
                'loadingCatalog' => 'Cargando catálogo de cuarteles y costos…',
                'refreshCatalog' => 'Recargar catálogo',
                'catalogError' => 'No se pudo cargar el catálogo híbrido de rentabilidad.',
                'catalogEmpty' => 'No se encontraron cuarteles disponibles desde API/CSV fallback.',
                'saveToast' => 'Ajustes de rentabilidad guardados.',
                'addRow' => 'Agregar fila',
                'recalculate' => 'Recalcular',
                'saveChanges' => 'Guardar todo',
                'importInvalidType' => 'Archivo inválido. Sube un CSV UTF-8.',
                'importMissingHeaders' => 'Faltan columnas obligatorias en el CSV: Predio, Sector, Cuartel, Kilos.',
                'importNoRows' => 'El CSV no contiene filas para procesar.',
                'importPreviewReady' => 'Vista previa generada. Revisa el resumen y aplica solo filas válidas.',
                'importApplied' => 'Importación aplicada sobre la capa manual actual.',
                'importNothingToApply' => 'No hay filas válidas para aplicar.',
            ),
        ));
    }

    /**
     * Agregar menú "Dashboard" en el admin
     */
    public static function add_menu() {
        add_menu_page(
            'Ajustes Dashboard La Higuera',    // Título de la página
            'Dashboard Higuera',               // Título del menú
            'manage_options',                   // Capacidad requerida
            self::MENU_SLUG,                    // Slug del menú
            array(__CLASS__, 'render_page_summary'), // Callback
            'dashicons-chart-area',             // Icono
            30                                  // Posición
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Resumen',
            'Resumen',
            'manage_options',
            self::MENU_SLUG,
            array(__CLASS__, 'render_page_summary')
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Acceso',
            'Acceso',
            'manage_options',
            self::PAGE_ACCESS,
            array(__CLASS__, 'render_page_access')
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Datos/API',
            'Datos/API',
            'manage_options',
            self::PAGE_DATA_API,
            array(__CLASS__, 'render_page_data_api')
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Rentabilidad',
            'Rentabilidad',
            'manage_options',
            self::PAGE_RENTABILIDAD,
            array(__CLASS__, 'render_page_rentabilidad')
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
            self::PAGE_ACCESS
        );

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_slug', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_slug'),
            'default'           => 'dashboard',
        ));

        add_settings_field('dlh_slug', 'Slug / ruta', array(__CLASS__, 'field_slug'), self::PAGE_ACCESS, 'dlh_section_url');

        // --- Sección: Acceso ---
        add_settings_section(
            'dlh_section_access',
            'Control de acceso',
            array(__CLASS__, 'section_access_cb'),
            self::PAGE_ACCESS
        );

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_access', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'public',
        ));

        add_settings_field('dlh_access', 'Tipo de acceso', array(__CLASS__, 'field_access'), self::PAGE_ACCESS, 'dlh_section_access');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_roles', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'administrator',
        ));

        add_settings_field('dlh_roles', 'Roles permitidos', array(__CLASS__, 'field_roles'), self::PAGE_ACCESS, 'dlh_section_access');


        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_password_portal_enabled', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
            'default'           => '0',
        ));

        add_settings_field('dlh_password_portal_enabled', 'Portal con contraseña (standalone)', array(__CLASS__, 'field_password_portal_enabled'), self::PAGE_ACCESS, 'dlh_section_access');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_password_portal_password', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_portal_password'),
            'default'           => '',
        ));

        add_settings_field('dlh_password_portal_password', 'Contraseña del portal', array(__CLASS__, 'field_password_portal_password'), self::PAGE_ACCESS, 'dlh_section_access');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_password_portal_title', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Acceso al Dashboard',
        ));

        add_settings_field('dlh_password_portal_title', 'Título portal', array(__CLASS__, 'field_password_portal_title'), self::PAGE_ACCESS, 'dlh_section_access');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_password_portal_message', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Ingresa la contraseña para continuar.',
        ));

        add_settings_field('dlh_password_portal_message', 'Mensaje portal', array(__CLASS__, 'field_password_portal_message'), self::PAGE_ACCESS, 'dlh_section_access');

        // --- Sección: Header standalone ---
        add_settings_section(
            'dlh_section_standalone_header',
            'Header standalone',
            array(__CLASS__, 'section_standalone_header_cb'),
            self::PAGE_ACCESS
        );

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_enabled', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
            'default'           => '0',
        ));
        add_settings_field('dlh_standalone_header_enabled', 'Activar header personalizado', array(__CLASS__, 'field_standalone_header_enabled'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_show_logo', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
            'default'           => '0',
        ));
        add_settings_field('dlh_standalone_header_show_logo', 'Mostrar logo', array(__CLASS__, 'field_standalone_header_show_logo'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_logo_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ));
        add_settings_field('dlh_standalone_header_logo_url', 'Logo header', array(__CLASS__, 'field_standalone_header_logo_url'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_logo_alt', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Logo Agrícola La Higuera',
        ));
        add_settings_field('dlh_standalone_header_logo_alt', 'Alt logo', array(__CLASS__, 'field_standalone_header_logo_alt'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_logo_height', array(
            'type'              => 'integer',
            'sanitize_callback' => array(__CLASS__, 'sanitize_logo_height_px'),
            'default'           => 44,
        ));
        add_settings_field('dlh_standalone_header_logo_height', 'Alto logo (px)', array(__CLASS__, 'field_standalone_header_logo_height'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_show_title', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
            'default'           => '1',
        ));
        add_settings_field('dlh_standalone_header_show_title', 'Mostrar título', array(__CLASS__, 'field_standalone_header_show_title'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_title', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Dashboard — Agrícola La Higuera',
        ));
        add_settings_field('dlh_standalone_header_title', 'Título editable', array(__CLASS__, 'field_standalone_header_title'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_show_subtitle', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
            'default'           => '1',
        ));
        add_settings_field('dlh_standalone_header_show_subtitle', 'Mostrar subtítulo', array(__CLASS__, 'field_standalone_header_show_subtitle'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_subtitle', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Temporada 2025–2026',
        ));
        add_settings_field('dlh_standalone_header_subtitle', 'Subtítulo editable', array(__CLASS__, 'field_standalone_header_subtitle'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_sticky', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
            'default'           => '1',
        ));
        add_settings_field('dlh_standalone_header_sticky', 'Header sticky', array(__CLASS__, 'field_standalone_header_sticky'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        register_setting(self::OPTION_GROUP_ACCESS, 'dlh_standalone_header_show_logout', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
            'default'           => '1',
        ));
        add_settings_field('dlh_standalone_header_show_logout', 'Mostrar botón “Cerrar acceso” en header', array(__CLASS__, 'field_standalone_header_show_logout'), self::PAGE_ACCESS, 'dlh_section_standalone_header');

        // --- Sección: Assets ---
        add_settings_section(
            'dlh_section_assets',
            'CSS / JS del Dashboard',
            array(__CLASS__, 'section_assets_cb'),
            self::PAGE_DATA_API
        );

        register_setting(self::OPTION_GROUP_DATA_API, 'dlh_load_assets', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
            'default'           => '1',
        ));

        add_settings_field('dlh_load_assets', 'Cargar assets', array(__CLASS__, 'field_load_assets'), self::PAGE_DATA_API, 'dlh_section_assets');

        // --- Sección: Fuente de datos ---
        add_settings_section(
            'dlh_section_data',
            'Fuente de datos',
            array(__CLASS__, 'section_data_cb'),
            self::PAGE_DATA_API
        );

        register_setting(self::OPTION_GROUP_DATA_API, 'dlh_data_source', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_data_source'),
            'default'           => 'api_fallback_csv',
        ));

        add_settings_field('dlh_data_source', 'Origen de datos', array(__CLASS__, 'field_data_source'), self::PAGE_DATA_API, 'dlh_section_data');

        register_setting(self::OPTION_GROUP_DATA_API, 'dlh_api_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ));

        add_settings_field('dlh_api_url', 'URL de la API', array(__CLASS__, 'field_api_url'), self::PAGE_DATA_API, 'dlh_section_data');

        register_setting(self::OPTION_GROUP_DATA_API, 'dlh_api_source_mode', array(
            'type'              => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_api_source_mode'),
            'default'           => 'url',
        ));

        add_settings_field('dlh_api_source_mode', 'Fuente API 25-26', array(__CLASS__, 'field_api_source_mode'), self::PAGE_DATA_API, 'dlh_section_data');

        register_setting(self::OPTION_GROUP_DATA_API, 'dlh_api_powerbi_formula', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ));

        add_settings_field('dlh_api_powerbi_formula', 'Fórmula Power BI (opcional)', array(__CLASS__, 'field_api_powerbi_formula'), self::PAGE_DATA_API, 'dlh_section_data');

        // --- Sección: Rentabilidad ---
        add_settings_section(
            'dlh_section_rentabilidad',
            'Rentabilidad en Resumen',
            array(__CLASS__, 'section_rentabilidad_cb'),
            self::PAGE_RENTABILIDAD
        );

        register_setting(self::OPTION_GROUP_RENTABILIDAD, 'dlh_rentabilidad_cards', array(
            'type'              => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_rentabilidad_cards'),
            'default'           => array(),
        ));

        add_settings_field('dlh_rentabilidad_cards', 'Cards de rentabilidad', array(__CLASS__, 'field_rentabilidad_cards'), self::PAGE_RENTABILIDAD, 'dlh_section_rentabilidad');

        register_setting(self::OPTION_GROUP_RENTABILIDAD, 'dlh_rent_sector_icon_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ));
        add_settings_field('dlh_rent_sector_icon_url', 'Ícono rápido: Sector', array(__CLASS__, 'field_rent_sector_icon_url'), self::PAGE_RENTABILIDAD, 'dlh_section_rentabilidad');

        register_setting(self::OPTION_GROUP_RENTABILIDAD, 'dlh_rent_cuartel_icon_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ));
        add_settings_field('dlh_rent_cuartel_icon_url', 'Ícono rápido: Cuartel', array(__CLASS__, 'field_rent_cuartel_icon_url'), self::PAGE_RENTABILIDAD, 'dlh_section_rentabilidad');

        register_setting(self::OPTION_GROUP_RENTABILIDAD, 'dlh_rentabilidad_manual_rows', array(
            'type'              => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_rentabilidad_manual_rows'),
            'default'           => array(),
        ));
        add_settings_field('dlh_rentabilidad_manual_rows', 'Carga manual complementaria', array(__CLASS__, 'field_rentabilidad_manual_rows'), self::PAGE_RENTABILIDAD, 'dlh_section_rentabilidad');
        add_settings_field('dlh_rentabilidad_diagnostics', 'Diagnóstico de rentabilidad', array(__CLASS__, 'field_rentabilidad_diagnostics'), self::PAGE_RENTABILIDAD, 'dlh_section_rentabilidad');

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

    public static function section_standalone_header_cb() {
        echo '<p>Configura el header visual del standalone sin afectar la lógica de acceso ni el flujo del dashboard.</p>';
    }

    public static function section_assets_cb() {
        echo '<p>Activa o desactiva la carga del CSS y JavaScript del dashboard.</p>';
    }

    public static function section_data_cb() {
        echo '<p>Configuración de datos del dashboard (v1.6.0+).</p>';
    }

    public static function section_rentabilidad_cb() {
        echo '<div class="dlh-section-intro"><p><strong>Controla el bloque Rentabilidad del Resumen.</strong> Aquí defines qué cards se muestran, su orden, los íconos de filtros rápidos y la nueva carga híbrida: cuarteles/hectáreas/costos desde API o CSV fallback, más kilos/ingresos ingresados manualmente.</p></div>';
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

    public static function field_standalone_header_enabled() {
        $val = get_option('dlh_standalone_header_enabled', '0');
        echo '<label><input type="checkbox" name="dlh_standalone_header_enabled" value="1" ' . checked($val, '1', false) . '> Activar configuración de header standalone</label>';
    }

    public static function field_standalone_header_show_logo() {
        $val = get_option('dlh_standalone_header_show_logo', '0');
        echo '<label><input type="checkbox" name="dlh_standalone_header_show_logo" value="1" ' . checked($val, '1', false) . '> Mostrar logo en header</label>';
    }

    public static function field_standalone_header_logo_url() {
        self::render_icon_uploader('dlh_standalone_header_logo_url', 'Logo de header standalone', 'Se mostrará en el header standalone junto al título/subtítulo cuando esté activo.');
    }

    public static function field_standalone_header_logo_alt() {
        $val = get_option('dlh_standalone_header_logo_alt', 'Logo Agrícola La Higuera');
        echo '<input type="text" name="dlh_standalone_header_logo_alt" value="' . esc_attr($val) . '" class="regular-text" />';
    }

    public static function field_standalone_header_logo_height() {
        $val = (int) get_option('dlh_standalone_header_logo_height', 44);
        if ($val < 20) {
            $val = 20;
        }
        if ($val > 48) {
            $val = 48;
        }
        echo '<input type="number" name="dlh_standalone_header_logo_height" value="' . esc_attr((string) $val) . '" class="small-text" min="20" max="48" step="1" />';
        echo '<p class="description">Rango recomendado: 24 a 40 px. Límite: 20 a 48 px.</p>';
    }

    public static function field_standalone_header_show_title() {
        $val = get_option('dlh_standalone_header_show_title', '1');
        echo '<label><input type="checkbox" name="dlh_standalone_header_show_title" value="1" ' . checked($val, '1', false) . '> Mostrar título</label>';
    }

    public static function field_standalone_header_title() {
        $val = get_option('dlh_standalone_header_title', 'Dashboard — Agrícola La Higuera');
        echo '<input type="text" name="dlh_standalone_header_title" value="' . esc_attr($val) . '" class="large-text" />';
    }

    public static function field_standalone_header_show_subtitle() {
        $val = get_option('dlh_standalone_header_show_subtitle', '1');
        echo '<label><input type="checkbox" name="dlh_standalone_header_show_subtitle" value="1" ' . checked($val, '1', false) . '> Mostrar subtítulo</label>';
    }

    public static function field_standalone_header_subtitle() {
        $val = get_option('dlh_standalone_header_subtitle', 'Temporada 2025–2026');
        echo '<input type="text" name="dlh_standalone_header_subtitle" value="' . esc_attr($val) . '" class="large-text" />';
    }

    public static function field_standalone_header_sticky() {
        $val = get_option('dlh_standalone_header_sticky', '1');
        echo '<label><input type="checkbox" name="dlh_standalone_header_sticky" value="1" ' . checked($val, '1', false) . '> Header sticky</label>';
    }

    public static function field_standalone_header_show_logout() {
        $val = get_option('dlh_standalone_header_show_logout', '1');
        echo '<label><input type="checkbox" name="dlh_standalone_header_show_logout" value="1" ' . checked($val, '1', false) . '> Mostrar botón Cerrar acceso en header</label>';
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

    public static function field_rentabilidad_cards() {
        $defaults = self::get_default_rentabilidad_cards();
        $saved = get_option('dlh_rentabilidad_cards', array());
        $cards = array_replace_recursive($defaults, is_array($saved) ? $saved : array());
        uasort($cards, function ($a, $b) {
            return ((int) $a['order']) <=> ((int) $b['order']);
        });

        echo '<div class="dlh-rent-cards-admin" data-rent-cards-admin>';
        echo '<div class="dlh-rent-cards-admin__head"><div>Card</div><div>Etiqueta visible</div><div>Acciones</div></div>';
        foreach ($cards as $key => $card) {
            $label = isset($card['label']) ? $card['label'] : $key;
            $enabled = !empty($card['enabled']) ? '1' : '0';
            $order = isset($card['order']) ? (int) $card['order'] : 10;
            echo '<div class="dlh-rent-card-admin" data-rent-card-item>';
            echo '<div class="dlh-rent-card-admin__metric">';
            echo '<strong>' . esc_html($key) . '</strong>';
            echo '<span>' . esc_html(str_replace('_', ' ', $key)) . '</span>';
            echo '</div>';
            echo '<div class="dlh-rent-card-admin__label">';
            echo '<input type="text" class="regular-text" name="dlh_rentabilidad_cards[' . esc_attr($key) . '][label]" value="' . esc_attr($label) . '" />';
            echo '<input type="hidden" data-rent-order-input name="dlh_rentabilidad_cards[' . esc_attr($key) . '][order]" value="' . esc_attr((string) $order) . '" />';
            echo '</div>';
            echo '<div class="dlh-rent-card-admin__actions">';
            echo '<label class="dlh-switch-inline"><input type="checkbox" name="dlh_rentabilidad_cards[' . esc_attr($key) . '][enabled]" value="1" ' . checked($enabled, '1', false) . '><span>Visible</span></label>';
            echo '<div class="dlh-rent-card-admin__order">';
            echo '<button type="button" class="button button-secondary" data-rent-move="up">↑</button>';
            echo '<button type="button" class="button button-secondary" data-rent-move="down">↓</button>';
            echo '<span class="dlh-rent-order-badge" data-rent-order-badge>' . esc_html((string) $order) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<p class="description">Ordena con las flechas. El valor se guarda internamente sin que tengas que editar números manuales.</p>';
    }

    private static function render_icon_uploader($option_name, $title, $description = 'Se usará en los chips rápidos del bloque Rentabilidad.') {
        $value = (string) get_option($option_name, '');
        $has_image = trim($value) !== '';
        echo '<div class="dlh-icon-uploader" data-dlh-icon-uploader>'; 
        echo '<input type="hidden" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" data-dlh-icon-input />';
        echo '<div class="dlh-icon-uploader__preview' . ($has_image ? ' has-image' : '') . '" data-dlh-icon-preview>';
        if ($has_image) {
            echo '<img src="' . esc_url($value) . '" alt="" />';
        } else {
            echo '<span>Sin ícono</span>';
        }
        echo '</div>';
        echo '<div class="dlh-icon-uploader__meta">';
        echo '<strong>' . esc_html($title) . '</strong>';
        echo '<p class="description">' . esc_html($description) . '</p>';
        echo '<div class="dlh-icon-uploader__actions">';
        echo '<button type="button" class="button button-secondary" data-dlh-icon-select>Seleccionar imagen</button>';
        echo '<button type="button" class="button button-link-delete" data-dlh-icon-remove' . ($has_image ? '' : ' style="display:none;"') . '>Quitar</button>';
        echo '</div>';
        echo '<code class="dlh-icon-uploader__url" data-dlh-icon-url>' . ($has_image ? esc_html($value) : 'Sin URL seleccionada') . '</code>';
        echo '</div>';
        echo '</div>';
    }

    public static function field_rent_sector_icon_url() {
        self::render_icon_uploader('dlh_rent_sector_icon_url', 'Ícono rápido: Sector');
    }

    public static function field_rent_cuartel_icon_url() {
        self::render_icon_uploader('dlh_rent_cuartel_icon_url', 'Ícono rápido: Cuartel');
    }

    public static function field_rentabilidad_manual_rows() {
        $saved_rows = array_values(self::get_rentabilidad_manual_rows_option());
        echo '<div class="dlh-rent-manual" data-dlh-rent-manual-builder>';
        echo '<div class="dlh-rent-import" data-dlh-rent-import>';
        echo '<div class="dlh-rent-import__head"><strong>1. Importación masiva</strong><p class="description">Importa kilos desde CSV UTF-8 usando clave <code>Predio + Sector + Cuartel</code>. Esta importación actualiza <code>Kilos</code> en la capa manual actual, sin crear fuentes paralelas.</p></div>';
        echo '<div class="dlh-rent-import__actions">';
        echo '<button type="button" class="button button-secondary" data-rent-download-template>Descargar plantilla CSV</button>';
        echo '<label class="button button-secondary dlh-rent-import__upload"><input type="file" accept=".csv,text/csv" data-rent-import-file />Seleccionar CSV</label>';
        echo '<button type="button" class="button button-primary" data-rent-apply-import disabled>Aplicar importación</button>';
        echo '</div>';
        echo '<div class="dlh-rent-import__summary" data-rent-import-summary></div>';
        echo '<div class="dlh-rent-import__preview" data-rent-import-preview></div>';
        echo '</div>';
        echo '<div class="dlh-rent-manual__intro">';
        echo '<div><strong>2. Carga manual</strong><p class="description">Predio, sector, cuartel, hectáreas y costos se obtienen automáticamente desde la API 25-26 o, si falla, desde el CSV fallback del plugin. Aquí completas kilos e ingresos por cuartel y, si hace falta, puedes agregar filas manuales adicionales.</p></div>';
        echo '<div class="dlh-rent-manual__actions" data-dlh-rent-inline-actions><button type="button" class="button button-secondary" data-dlh-rent-refresh>Recargar catálogo</button></div>';
        echo '</div>';
        echo '<div class="dlh-rent-manual__helpers">';
        echo '<span class="dlh-rent-helper-pill">Clave: Predio + Sector + Cuartel</span>';
        echo '<span class="dlh-rent-helper-pill">Calculado en vivo mientras editas</span>';
        echo '<span class="dlh-rent-helper-pill">Las filas incompletas se resaltan</span>';
        echo '</div>';
        echo '<div class="dlh-rent-manual__stats" data-dlh-rent-stats></div>';
        echo '<div class="dlh-rent-manual__status notice inline" style="display:none;" data-dlh-rent-status></div>';
        echo '<div class="dlh-rent-manual__table-wrap"><div class="dlh-rent-manual__table" data-dlh-rent-table></div></div>';
        echo '<input type="hidden" name="dlh_rentabilidad_manual_rows" value="' . esc_attr(wp_json_encode($saved_rows)) . '" data-dlh-rent-hidden />';
        echo '</div>';
        echo '<p class="description">Se guarda por clave compuesta <code>Predio + Sector + Cuartel</code>. Los cálculos del bloque Rentabilidad se derivan automáticamente al guardar.</p>';
    }

    public static function field_rentabilidad_diagnostics() {
        $status = Dashboard_Higuera_Import::get_rentabilidad_dataset_status();
        $diag = !empty($status['diagnostics']) && is_array($status['diagnostics']) ? $status['diagnostics'] : array();
        $source_analysis = (!empty($status['source_analysis']) && is_array($status['source_analysis'])) ? $status['source_analysis'] : array();
        $hybrid = (!empty($status['hybrid']) && is_array($status['hybrid'])) ? $status['hybrid'] : array();
        $rows = isset($diag['rows']) ? (int) $diag['rows'] : 0;
        $recognized = !empty($diag['recognized_columns']) && is_array($diag['recognized_columns']) ? $diag['recognized_columns'] : array();
        $metrics = !empty($diag['metrics_available']) && is_array($diag['metrics_available']) ? $diag['metrics_available'] : array();
        $totals = !empty($diag['totals']) && is_array($diag['totals']) ? $diag['totals'] : array();
        $mode = isset($status['using']) ? $status['using'] : 'none';
        $source_label = $mode === 'hybrid' ? 'Usando el mismo CSV normalizado del dashboard 25-26 para costos y hectáreas, más la capa manual de kilos/ingresos. El archivo legacy no participa en el cálculo actual.' : ($mode === 'active' ? 'Usando temporada-rentabilidad.csv' : ($mode === 'source' ? 'Usando fallback desde base fuente' : 'Sin datos disponibles'));
        $compare_rows = !empty($hybrid['compare_rows']) && is_array($hybrid['compare_rows']) ? array_slice($hybrid['compare_rows'], 0, 20) : array();
        $dashboard_total_costos = isset($hybrid['dashboard_total_costos']) ? (float) $hybrid['dashboard_total_costos'] : 0.0;
        echo '<div class="dlh-rent-diagnostics" data-dlh-rent-diagnostics-root>';
        echo '<div class="dlh-rent-diagnostics__title"><strong>3. Estado / diagnóstico</strong></div>';
        echo '<div class="dlh-rent-diagnostics__grid">';
        echo '<article class="dlh-diag-card"><span class="dlh-diag-card__label">Archivo legacy detectado</span><strong>' . (!empty($status['source_exists']) ? 'Sí' : 'No') . '</strong><small>' . (!empty($status['source_path']) ? esc_html(basename((string) $status['source_path'])) : 'Sin base fuente legacy') . '</small></article>';
        echo '<article class="dlh-diag-card"><span class="dlh-diag-card__label">Fuente activa</span><strong>' . esc_html(ucfirst($mode)) . '</strong><small>' . esc_html($source_label) . '</small></article>';
        echo '<article class="dlh-diag-card"><span class="dlh-diag-card__label">Cuarteles catálogo</span><strong>' . esc_html(number_format_i18n((int) ($hybrid['catalog_count'] ?? 0))) . '</strong><small>' . (!empty($hybrid['source']) ? esc_html('Origen: ' . $hybrid['source']) : 'Sin catálogo híbrido') . '</small></article>';
        echo '<article class="dlh-diag-card"><span class="dlh-diag-card__label">Filas manuales con datos</span><strong>' . esc_html(number_format_i18n((int) ($hybrid['manual_completed_count'] ?? 0))) . '</strong><small>' . 'Guardadas: ' . esc_html(number_format_i18n((int) ($hybrid['manual_count'] ?? 0))) . '</small></article>';
        echo '<article class="dlh-diag-card"><span class="dlh-diag-card__label">Costo dashboard 25-26</span><strong>' . esc_html('$' . number_format_i18n($dashboard_total_costos, 0)) . '</strong><small>' . (!empty($hybrid['csv_header_index']) ? 'Header detectado en fila ' . esc_html(number_format_i18n((int) $hybrid['csv_header_index'] + 1)) : 'CSV no detectado') . '</small></article>';
        echo '<article class="dlh-diag-card"><span class="dlh-diag-card__label">Métricas calculadas</span><strong>' . esc_html(number_format_i18n(count($metrics))) . '</strong><small>' . (!empty($metrics) ? esc_html(implode(', ', $metrics)) : 'Sin métricas disponibles') . '</small></article>';
        echo '</div>';
        if (!empty($status['content_error'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html($status['content_error']) . '</p></div>';
        }
        echo '<div class="dlh-rent-diagnostics__meta">';
        echo '<p><strong>Filas renderizables:</strong> ' . esc_html(number_format_i18n($rows)) . '</p>';
        if (!empty($hybrid['fetched_at'])) {
            echo '<p><strong>Catálogo híbrido actualizado:</strong> ' . esc_html((string) $hybrid['fetched_at']) . '</p>';
        }
        if (!empty($source_analysis['warnings_count'])) {
            echo '<p><strong>Advertencias de fuente:</strong> ' . esc_html(number_format_i18n((int) $source_analysis['warnings_count'])) . '</p>';
        }
        if (!empty($totals)) {
            echo '<div class="dlh-rent-diagnostics__totals">';
            echo '<span><strong>Total ingresos:</strong> ' . esc_html(number_format_i18n((float) ($totals['total_ingresos'] ?? 0), 0)) . '</span>';
            echo '<span><strong>Total costos:</strong> ' . esc_html(number_format_i18n((float) ($totals['total_costos'] ?? 0), 0)) . '</span>';
            echo '<span><strong>Resultado:</strong> ' . esc_html(number_format_i18n((float) ($totals['resultado'] ?? 0), 0)) . '</span>';
        echo '</div>';
        }
        if (!empty($compare_rows)) {
            echo '<div class="dlh-rent-diagnostics__compare">';
            echo '<h4>Comparativo rápido costo dashboard vs rentabilidad</h4>';
            echo '<p class="description">Muestra las primeras 20 filas para validar que el costo que ve Rentabilidad coincide con el total que consumiría el dashboard por cuartel.</p>';
            echo '<div class="dlh-rent-diagnostics__compare-wrap"><table class="widefat striped"><thead><tr><th>Predio</th><th>Sector</th><th>Cuartel</th><th>Dashboard</th><th>Rentabilidad</th><th>Diferencia</th></tr></thead><tbody>';
            foreach ($compare_rows as $compare_row) {
                $diff = isset($compare_row['diferencia']) ? (float) $compare_row['diferencia'] : 0.0;
                $diff_class = abs($diff) > 0.01 ? ' is-error' : ' is-ok';
                echo '<tr>';
                echo '<td>' . esc_html((string) ($compare_row['predio'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($compare_row['sector'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($compare_row['cuartel'] ?? '')) . '</td>';
                echo '<td>' . esc_html('$' . number_format_i18n((float) ($compare_row['dashboard_costos'] ?? 0), 0)) . '</td>';
                echo '<td>' . esc_html('$' . number_format_i18n((float) ($compare_row['hybrid_costos'] ?? 0), 0)) . '</td>';
                echo '<td class="dlh-rent-diagnostics__diff' . esc_attr($diff_class) . '">' . esc_html('$' . number_format_i18n($diff, 0)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
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

    public static function sanitize_logo_height_px($input) {
        $value = absint($input);
        if ($value < 20) {
            $value = 20;
        }
        if ($value > 48) {
            $value = 48;
        }
        return $value;
    }


    public static function sanitize_portal_password($input) {
        $raw = trim((string) $input);
        $current = get_option('dlh_password_portal_password', '');

        if ($raw === '') {
            return $current;
        }

        return wp_hash_password($raw);
    }


    public static function get_default_rentabilidad_cards() {
        return array(
            'total_ingresos'   => array('label' => 'Total ingresos', 'enabled' => 1, 'order' => 10),
            'total_costos'     => array('label' => 'Total costos acumulados', 'enabled' => 1, 'order' => 20),
            'resultado'        => array('label' => 'Resultado', 'enabled' => 1, 'order' => 30),
            'kilos_reales'     => array('label' => 'Kilos reales', 'enabled' => 1, 'order' => 40),
            'ingreso_hectarea' => array('label' => 'Ingreso por hectárea', 'enabled' => 1, 'order' => 50),
            'costo_hectarea'   => array('label' => 'Costo total por hectárea', 'enabled' => 1, 'order' => 60),
            'ingresos_kilo'    => array('label' => 'Ingresos por kilo', 'enabled' => 1, 'order' => 70),
            'costo_kilo'       => array('label' => 'Costo por kilo', 'enabled' => 1, 'order' => 80),
        );
    }

    private static function get_rentabilidad_manual_rows_option() {
        $raw = get_option('dlh_rentabilidad_manual_rows', array());
        if (!is_array($raw)) {
            return array();
        }
        $rows = array();
        foreach ($raw as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $predio = isset($row['predio']) ? sanitize_text_field((string) $row['predio']) : '';
            $sector = isset($row['sector']) ? sanitize_text_field((string) $row['sector']) : '';
            $cuartel = isset($row['cuartel']) ? sanitize_text_field((string) $row['cuartel']) : '';
            $compound_key = Dashboard_Higuera_Import::build_hybrid_compound_key($predio, $sector, $cuartel);
            if ($compound_key === '||') {
                $compound_key = sanitize_text_field((string) $key);
            }
            if ($compound_key === '') {
                continue;
            }
            $rows[$compound_key] = array(
                'compound_key' => $compound_key,
                'predio' => $predio,
                'sector' => $sector,
                'cuartel' => $cuartel,
                'kilos_reales' => self::sanitize_rent_number(isset($row['kilos_reales']) ? $row['kilos_reales'] : 0),
                'ingreso_exportacion' => self::sanitize_rent_number(isset($row['ingreso_exportacion']) ? $row['ingreso_exportacion'] : 0),
                'ingreso_mercado_nacional' => self::sanitize_rent_number(isset($row['ingreso_mercado_nacional']) ? $row['ingreso_mercado_nacional'] : 0),
                'otras_ventas_dte' => self::sanitize_rent_number(isset($row['otras_ventas_dte']) ? $row['otras_ventas_dte'] : 0),
                'otro_ingreso' => self::sanitize_rent_number(isset($row['otro_ingreso']) ? $row['otro_ingreso'] : 0),
            );
        }
        return $rows;
    }

    private static function sanitize_rent_number($value) {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }
        $raw = preg_replace('/[^0-9,\.\-]/', '', $raw);
        $commas = substr_count($raw, ',');
        $dots = substr_count($raw, '.');
        if ($commas && $dots) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($commas > 1 && !$dots) {
            $raw = str_replace(',', '', $raw);
        } elseif ($dots > 1 && !$commas) {
            $raw = str_replace('.', '', $raw);
        } elseif ($commas === 1 && !$dots) {
            $raw = str_replace(',', '.', $raw);
        }
        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    public static function sanitize_rentabilidad_manual_rows($input) {
        if (is_string($input)) {
            $decoded = json_decode(wp_unslash($input), true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }
        if (!is_array($input)) {
            return array();
        }
        $clean = array();
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $predio = isset($row['predio']) ? sanitize_text_field((string) $row['predio']) : '';
            $sector = isset($row['sector']) ? sanitize_text_field((string) $row['sector']) : '';
            $cuartel = isset($row['cuartel']) ? sanitize_text_field((string) $row['cuartel']) : '';
            $compound_key = Dashboard_Higuera_Import::build_hybrid_compound_key($predio, $sector, $cuartel);
            if ($compound_key === '' || $compound_key === '||') {
                continue;
            }
            $clean[$compound_key] = array(
                'predio' => $predio,
                'sector' => $sector,
                'cuartel' => $cuartel,
                'kilos_reales' => self::sanitize_rent_number(isset($row['kilos_reales']) ? $row['kilos_reales'] : 0),
                'ingreso_exportacion' => self::sanitize_rent_number(isset($row['ingreso_exportacion']) ? $row['ingreso_exportacion'] : 0),
                'ingreso_mercado_nacional' => self::sanitize_rent_number(isset($row['ingreso_mercado_nacional']) ? $row['ingreso_mercado_nacional'] : 0),
                'otras_ventas_dte' => self::sanitize_rent_number(isset($row['otras_ventas_dte']) ? $row['otras_ventas_dte'] : 0),
                'otro_ingreso' => self::sanitize_rent_number(isset($row['otro_ingreso']) ? $row['otro_ingreso'] : 0),
            );
        }
        return $clean;
    }

    public static function sanitize_rentabilidad_cards($input) {
        $defaults = self::get_default_rentabilidad_cards();
        $clean = array();
        foreach ($defaults as $key => $default) {
            $row = (is_array($input) && isset($input[$key]) && is_array($input[$key])) ? $input[$key] : array();
            $clean[$key] = array(
                'label' => isset($row['label']) && trim((string) $row['label']) !== '' ? sanitize_text_field($row['label']) : $default['label'],
                'enabled' => !empty($row['enabled']) ? 1 : 0,
                'order' => isset($row['order']) ? max(1, min(99, (int) $row['order'])) : (int) $default['order'],
            );
        }
        uasort($clean, function($a, $b){ return ((int) $a['order']) <=> ((int) $b['order']); });
        return $clean;
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

    public static function ajax_fetch_rentabilidad_catalog() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sin permisos.'), 403);
        }
        check_ajax_referer('dlh_fetch_rentabilidad_catalog', 'nonce');

        if (!empty($_POST['force'])) {
            Dashboard_Higuera_Import::clear_rentabilidad_hybrid_catalog_cache();
        }

        $catalog = Dashboard_Higuera_Import::get_rentabilidad_hybrid_catalog_data();
        if (is_wp_error($catalog)) {
            wp_send_json_error(array('message' => $catalog->get_error_message()), 500);
        }

        wp_send_json_success($catalog);
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

    private static function render_header($title, $description, $badge = '') {
        ?>
        <div class="dlh-settings-page__hero">
            <div>
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo esc_html($description); ?></p>
            </div>
            <div class="dlh-settings-page__badge"><?php echo esc_html($badge !== '' ? $badge : ('v' . DASHBOARD_HIGUERA_VERSION)); ?></div>
        </div>
        <?php
    }

    private static function render_settings_page_form($title, $description, $settings_page, $option_group, $submit_label = 'Guardar ajustes', $context = '') {
        ?>
        <div class="wrap dlh-settings-page" data-dlh-settings-saved="<?php echo isset($_GET['settings-updated']) ? '1' : '0'; ?>">
            <?php self::render_header($title, $description); ?>
            <?php if ($context !== '') : ?>
                <div class="dlh-summary-card dlh-summary-card--context"><p><?php echo esc_html($context); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible dlh-settings-save-feedback"><p>Ajustes guardados correctamente.</p></div>
            <?php endif; ?>
            <div class="dlh-save-indicator is-clean" data-dlh-save-status data-state="clean" role="status" aria-live="polite">
                <span class="dlh-save-indicator__text">Sin cambios</span>
            </div>
            <form method="post" action="options.php" class="dlh-settings-form" data-dlh-edit-form>
                <?php
                settings_fields($option_group);
                self::render_settings_sections_blocks($settings_page);
                echo '<div class="dlh-settings-actions">';
                submit_button($submit_label, 'primary', 'submit', false);
                echo '</div>';
                ?>
            </form>
        </div>
        <?php
    }

    private static function render_settings_sections_blocks($page, $only_section_ids = array()) {
        global $wp_settings_sections;

        if (empty($wp_settings_sections[$page]) || !is_array($wp_settings_sections[$page])) {
            return;
        }

        foreach ($wp_settings_sections[$page] as $section) {
            if (!empty($only_section_ids) && !in_array($section['id'], $only_section_ids, true)) {
                continue;
            }
            self::render_settings_section_block($page, $section);
        }
    }

    private static function render_settings_section_block($page, $section) {
        $section_id = isset($section['id']) ? (string) $section['id'] : '';
        if ($section_id === '') {
            return;
        }

        echo '<section class="dlh-settings-section" id="' . esc_attr($section_id) . '">';
        if (!empty($section['title'])) {
            echo '<h2>' . esc_html($section['title']) . '</h2>';
        }
        if (!empty($section['callback']) && is_callable($section['callback'])) {
            echo '<div class="dlh-settings-section__intro">';
            call_user_func($section['callback'], $section);
            echo '</div>';
        }

        echo '<table class="form-table" role="presentation">';
        do_settings_fields($page, $section_id);
        echo '</table>';
        echo '</section>';
    }

    private static function get_access_label() {
        $access = get_option('dlh_access', 'public');
        $labels = array(
            'public' => 'Público',
            'logged_in' => 'Solo usuarios logueados',
            'role' => 'Solo ciertos roles',
        );
        return isset($labels[$access]) ? $labels[$access] : $access;
    }

    private static function get_api_mode_label() {
        $mode = get_option('dlh_api_source_mode', 'url');
        return $mode === 'powerbi' ? 'Fórmula Power BI' : 'URL de API';
    }

    private static function get_rentabilidad_status_label() {
        $status = Dashboard_Higuera_Import::get_rentabilidad_dataset_status();
        $mode = isset($status['using']) ? (string) $status['using'] : 'none';
        if ($mode === 'hybrid') {
            return 'Híbrido API + manual activo';
        }
        if ($mode === 'active') {
            return 'Base rentabilidad activa';
        }
        if ($mode === 'source') {
            return 'Usando fallback fuente';
        }
        return 'Sin datos activos';
    }

    public static function render_page_summary() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $slug = Dashboard_Higuera_Standalone::get_slug();
        $dashboard_url = home_url('/' . $slug . '/');
        $access_label = self::get_access_label();
        $portal_enabled = get_option('dlh_password_portal_enabled', '0') === '1';
        $api_mode = self::get_api_mode_label();
        $rentabilidad_status = self::get_rentabilidad_status_label();
        $data_source = get_option('dlh_data_source', 'api_fallback_csv');
        $data_source_label = $data_source === 'api_fallback_csv' ? 'API + fallback CSV' : 'Modo legado';
        $base_2425_url = admin_url('admin.php?page=' . Dashboard_Higuera_Import::SUBMENU_SLUG);
        $access_url = admin_url('admin.php?page=' . self::PAGE_ACCESS);
        $data_api_url = admin_url('admin.php?page=' . self::PAGE_DATA_API);
        $rentabilidad_url = admin_url('admin.php?page=' . self::PAGE_RENTABILIDAD);
        ?>
        <div class="wrap dlh-settings-page">
            <?php self::render_header('Resumen — Dashboard Higuera', 'Estado general del plugin y accesos rápidos por módulo.'); ?>
            <div class="dlh-summary-grid">
                <article class="dlh-summary-card"><h3>URL / Slug actual</h3><p><code><?php echo esc_html($dashboard_url); ?></code></p></article>
                <article class="dlh-summary-card"><h3>Tipo de acceso</h3><p><?php echo esc_html($access_label); ?></p></article>
                <article class="dlh-summary-card"><h3>Portal con contraseña</h3><p><?php echo esc_html($portal_enabled ? 'Activo' : 'Inactivo'); ?></p></article>
                <article class="dlh-summary-card"><h3>Modo API actual</h3><p><?php echo esc_html($api_mode); ?></p></article>
                <article class="dlh-summary-card"><h3>Fuente de datos</h3><p><?php echo esc_html($data_source_label); ?></p></article>
                <article class="dlh-summary-card"><h3>Estado rentabilidad</h3><p><?php echo esc_html($rentabilidad_status); ?></p></article>
            </div>
            <div class="dlh-summary-card">
                <h3>Test rápido API</h3>
                <p><button type="button" class="button" id="dlh-test-api-sources">Test de API (velocidad)</button></p>
                <div id="dlh-test-api-results" class="dlh-test-results" role="status" aria-live="polite"></div>
            </div>
            <div class="dlh-summary-actions">
                <a class="button button-primary" href="<?php echo esc_url($dashboard_url); ?>" target="_blank" rel="noopener noreferrer">Abrir dashboard</a>
                <a class="button" href="<?php echo esc_url($access_url); ?>">Ir a Acceso</a>
                <a class="button" href="<?php echo esc_url($data_api_url); ?>">Ir a Datos/API</a>
                <a class="button" href="<?php echo esc_url($rentabilidad_url); ?>">Ir a Rentabilidad</a>
                <a class="button" href="<?php echo esc_url($base_2425_url); ?>">Ir a Base 24-25</a>
            </div>
        </div>
        <?php
    }

    public static function render_page_access() {
        if (!current_user_can('manage_options')) {
            return;
        }
        self::render_settings_page_form(
            'Acceso — Dashboard Higuera',
            'Configura publicación, restricción por roles y portal con contraseña para el standalone.',
            self::PAGE_ACCESS,
            self::OPTION_GROUP_ACCESS,
            'Guardar ajustes de Acceso',
            'Aquí solo se administra la publicación del standalone: slug, tipo de acceso, roles y portal con contraseña.'
        );
    }

    public static function render_page_data_api() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $data_source = get_option('dlh_data_source', 'api_fallback_csv');
        $mode = get_option('dlh_api_source_mode', 'url');
        $api_url = Dashboard_La_Higuera::get_configured_api_url();
        ?>
        <div class="wrap dlh-settings-page" data-dlh-settings-saved="<?php echo isset($_GET['settings-updated']) ? '1' : '0'; ?>">
            <?php self::render_header('Datos/API — Dashboard Higuera', 'Configura assets, origen de datos y parámetros de API para temporada 25-26.'); ?>
            <div class="dlh-summary-grid">
                <article class="dlh-summary-card"><h3>Fuente de datos</h3><p><?php echo esc_html($data_source === 'api_fallback_csv' ? 'API + fallback CSV' : 'Modo legado'); ?></p></article>
                <article class="dlh-summary-card"><h3>Modo API activo</h3><p><?php echo esc_html($mode === 'powerbi' ? 'Fórmula Power BI' : 'URL de API'); ?></p></article>
                <article class="dlh-summary-card"><h3>Endpoint actual</h3><p><code><?php echo esc_html($api_url !== '' ? $api_url : 'Sin URL configurada'); ?></code></p></article>
            </div>
            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible dlh-settings-save-feedback"><p>Ajustes guardados correctamente.</p></div>
            <?php endif; ?>
            <div class="dlh-save-indicator is-clean" data-dlh-save-status data-state="clean" role="status" aria-live="polite">
                <span class="dlh-save-indicator__text">Sin cambios</span>
            </div>
            <form method="post" action="options.php" class="dlh-settings-form" data-dlh-edit-form>
                <?php
                settings_fields(self::OPTION_GROUP_DATA_API);
                self::render_settings_sections_blocks(self::PAGE_DATA_API);
                echo '<div class="dlh-settings-actions">';
                submit_button('Guardar ajustes de Datos/API', 'primary', 'submit', false);
                echo '</div>';
                ?>
            </form>
        </div>
        <?php
    }

    public static function render_page_rentabilidad() {
        if (!current_user_can('manage_options')) {
            return;
        }
        self::render_settings_page_form(
            'Rentabilidad — Dashboard Higuera',
            'Configura cards, íconos, carga manual complementaria y diagnóstico del bloque Rentabilidad.',
            self::PAGE_RENTABILIDAD,
            self::OPTION_GROUP_RENTABILIDAD,
            'Guardar ajustes de Rentabilidad',
            'Este módulo mantiene el builder/workspace y diagnóstico de rentabilidad sin alterar la persistencia actual.'
        );
    }
}

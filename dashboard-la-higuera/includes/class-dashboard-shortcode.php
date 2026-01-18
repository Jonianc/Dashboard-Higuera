<?php
/**
 * Clase para manejar el shortcode del dashboard
 */
class Dashboard_Higuera_Shortcode {

    /**
     * Renderizar el shortcode
     *
     * @param array $atts Atributos del shortcode
     * @return string HTML del dashboard
     */
    public static function render($atts) {
        // Atributos por defecto
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => 'auto',
            'fullwidth' => 'false'
        ), $atts, 'dashboard_higuera');

        // Iniciar buffer de salida
        ob_start();

        // Si fullwidth está activado, agregar estilos para ocultar header/footer
        $is_fullwidth = filter_var($atts['fullwidth'], FILTER_VALIDATE_BOOLEAN);

        if ($is_fullwidth) {
            self::render_fullwidth_styles();
        }

        // Cargar template
        include DASHBOARD_HIGUERA_PLUGIN_DIR . 'templates/dashboard-template.php';

        // Retornar contenido
        return ob_get_clean();
    }

    /**
     * Renderizar estilos CSS para modo fullwidth
     * Oculta header, footer y sidebar del tema WordPress
     */
    private static function render_fullwidth_styles() {
        ?>
        <style id="dashboard-higuera-fullwidth">
            /* === MODO FULLWIDTH: Ocultar elementos del tema === */

            /* Ocultar barra de admin de WordPress */
            #wpadminbar {
                display: none !important;
            }
            html {
                margin-top: 0 !important;
            }

            /* === TEMAS DE BLOQUES (FSE) - Twenty Twenty-Five, etc === */
            .wp-block-template-part,
            .wp-site-blocks > header,
            .wp-site-blocks > footer,
            .wp-site-blocks > .wp-block-template-part,
            header.wp-block-template-part,
            footer.wp-block-template-part,
            .wp-block-group.has-global-padding > header,
            .wp-block-group.has-global-padding > footer {
                display: none !important;
            }

            /* Ocultar header del tema (selectores comunes) */
            body > header,
            #header,
            .site-header,
            #site-header,
            .header,
            #masthead,
            .masthead,
            #site-navigation,
            .main-navigation,
            .nav-header,
            #top-header,
            .top-header,
            #branding,
            .site-branding-container,
            [role="banner"],
            .elementor-location-header,
            .ast-header,
            .genesis-header,
            #starter-header {
                display: none !important;
            }

            /* Ocultar footer del tema (selectores comunes) */
            body > footer,
            #footer,
            .site-footer,
            #site-footer,
            .footer,
            #colophon,
            .colophon,
            [role="contentinfo"],
            .elementor-location-footer,
            .ast-footer,
            .genesis-footer,
            #starter-footer,
            .footer-widgets {
                display: none !important;
            }

            /* Ocultar sidebars */
            #sidebar,
            .sidebar,
            .widget-area,
            #secondary,
            .site-sidebar,
            aside.sidebar {
                display: none !important;
            }

            /* Reset del body */
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: #0f1115 !important;
                overflow-x: hidden;
            }

            /* === Contenedores de temas FSE === */
            .wp-site-blocks {
                padding: 0 !important;
                margin: 0 !important;
            }
            .wp-site-blocks > main,
            .wp-site-blocks > .wp-block-group,
            .wp-site-blocks > .entry-content,
            .is-layout-constrained,
            .is-layout-flow {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Hacer el contenido principal fullwidth */
            #content,
            .site-content,
            .content-area,
            #primary,
            main,
            .main-content,
            #main,
            .entry-content,
            article,
            .page-content,
            .post-content {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                float: none !important;
            }

            /* Container de WordPress */
            .container,
            .site-container,
            .wrapper,
            .page-wrapper,
            .content-wrapper,
            .wp-block-post-content {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Ajustes específicos del dashboard */
            #dashboard-higuera-wrapper {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            #dashboard-higuera-wrapper header {
                border-radius: 0 !important;
            }

            #dashboard-higuera-wrapper .container {
                max-width: 100% !important;
                padding-left: 20px;
                padding-right: 20px;
            }

            /* Para pantallas muy grandes, limitar ancho */
            @media (min-width: 1920px) {
                #dashboard-higuera-wrapper .container {
                    max-width: 1800px;
                    margin-left: auto;
                    margin-right: auto;
                }
            }
        </style>
        <?php
    }
}

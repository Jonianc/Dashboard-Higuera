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
            'height' => 'auto'
        ), $atts, 'dashboard_higuera');

        // Iniciar buffer de salida
        ob_start();

        // Cargar template
        include DASHBOARD_HIGUERA_PLUGIN_DIR . 'templates/dashboard-template.php';

        // Retornar contenido
        return ob_get_clean();
    }
}

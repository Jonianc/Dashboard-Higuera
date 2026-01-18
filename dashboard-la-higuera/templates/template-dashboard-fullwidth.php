<?php
/**
 * Template Name: Dashboard Full Width (Sin Header/Footer)
 * Template Post Type: page
 * Description: Template de página completa para el Dashboard La Higuera sin header ni footer
 */

// Seguridad
if (!defined('ABSPATH')) {
    exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php wp_head(); ?>
    <style>
        /* Reset de estilos del tema */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: #0f1115 !important;
            overflow-x: hidden;
        }

        /* Ocultar elementos del tema que puedan aparecer */
        #wpadminbar {
            display: none !important;
        }

        /* Container a ancho completo */
        .dashboard-fullwidth-container {
            width: 100vw;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            position: relative;
        }

        /* Ajustes para el dashboard */
        #dashboard-higuera-wrapper {
            max-width: 100% !important;
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        #dashboard-higuera-wrapper header {
            border-radius: 0 !important;
        }

        /* Ajustar padding del container interno */
        #dashboard-higuera-wrapper .container {
            max-width: 100% !important;
            padding-left: 20px;
            padding-right: 20px;
        }

        /* Para pantallas muy grandes, centrar con max-width opcional */
        @media (min-width: 1920px) {
            #dashboard-higuera-wrapper .container {
                max-width: 1800px;
                margin-left: auto;
                margin-right: auto;
            }
        }

        /* Ocultar scrollbar si aparece en algunos temas */
        html {
            overflow-x: hidden;
        }
    </style>
</head>

<body <?php body_class('dashboard-fullwidth-page'); ?>>
<?php wp_body_open(); ?>

<div class="dashboard-fullwidth-container">
    <?php
    // Mostrar el contenido de la página
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>
</div>

<?php wp_footer(); ?>
</body>
</html>

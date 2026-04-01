# Plugin Map — Dashboard La Higuera

## Núcleo
- `dashboard-la-higuera.php`: bootstrap del plugin, carga dependencias, registra shortcode, assets, templates y rutas REST.

## Frontend (Dashboard)
- `templates/dashboard-template.php`: estructura HTML del dashboard (filtros, tabs, tablas, loader).
- `assets/js/dashboard.js`: lógica de parsing CSV/API, filtros, render de Resumen/Gráficos/Comparativo/Detalle.
- `assets/css/dashboard.css`: estilos del dashboard.

## Shortcode
- `includes/class-dashboard-shortcode.php`: registra y renderiza `[dashboard_higuera]`, incluyendo modo `fullwidth`.

## Standalone
- `includes/class-dashboard-standalone.php`: ruta standalone configurable por slug, acceso por reglas (`public`, `logged_in`, `role`) y portal con contraseña.

## Admin
- `includes/class-dashboard-settings.php`: menú Dashboard en admin, settings (slug, acceso, assets, fuente de datos/API) y test de API.
- `assets/js/admin-settings.js`: interacciones de la pantalla de ajustes.
- `assets/css/admin-settings.css`: estilos de la pantalla de ajustes.

## Importación Base 24-25
- `includes/class-dashboard-import.php`: validación/activación de base 24-25 (CSV/XLSX) en `uploads/dashboard-higuera`.
- `assets/js/admin-import.js`: UX de la pantalla de importación.
- `assets/css/admin-import.css`: estilos de importación.

## Datos
- `data/temporada-2025-26.csv`: dataset base 25-26 (fallback).
- `data/temporada-2024-25.csv`: dataset base 24-25 (referencia/fallback).

## Documentación
- `README.md`: instalación, uso y operación.
- `CHANGELOG.md`: historial de versiones.

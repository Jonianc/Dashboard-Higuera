# Changelog

## 1.6.0 - 2026-02-06

### Fixed
- Se alineó el frontend standalone al nuevo flujo de datos: 25-26 desde API al iniciar (fallback CSV solo si falla API) y 24-25 desde CSV solo al entrar a Comparativo.
- Se evitó que la precarga de CSV en frontend pueda pisar datos API en cualquier modo de ejecución.

### Changed
- Se ajustó el panel admin para reflejar el flujo actual (modo fijo API + fallback CSV), dejando `dlh_data_source` bloqueado a `api_fallback_csv`.
- Se expuso/configuró `dlh_api_url` también en frontend shortcode mediante `wp_localize_script`, igualando comportamiento con standalone.

## 1.5.0 - 2026-02-06

### Changed
- Temporada 25-26 ahora se carga al iniciar únicamente desde API Agrosmart; el CSV 25-26 se usa solo como fallback cuando la API falla.
- Se eliminó la precarga automática de CSVs al iniciar para evitar que el CSV 25-26 pueda pisar datos API.
- Temporada 24-25 se carga solo desde CSV y de forma lazy al entrar a la pestaña Comparativo.

### Fixed
- El comparativo usa de forma consistente `state` (25-26 API/fallback) vs `comp2425` (24-25 CSV), evitando mezclas de fuente de datos.

## 1.4.1 - 2026-02-06

### Fixed
- Se corrigió la lógica de disponibilidad del comparativo 24-25 para evitar falsos "Sin datos temporada 24-25 para comparar" cuando la carga aún está en curso.
- El comparativo ahora intenta cargar/parsear 24-25 en segundo plano si aún no está disponible y re-renderiza automáticamente al completar.
- Se agregó estado transitorio de carga: `Cargando temporada 24-25…`.

## 1.4.0 - 2026-02-06

### Fixed
- Se corrigió la pestaña Comparativo para que siempre renderice al abrirse y muestre estados vacíos (`Sin datos`) cuando falta una de las series o no hay resultados.
- Se protegieron los listeners de botones opcionales (`btnSwap`, `btnSave`) para evitar errores JS que rompían la ejecución del dashboard.
- Se corrigió el toggle de inversiones para que excluya `INVERSIONES VARIAS` de cálculos y totales en todas las vistas (Resumen, Gráficos, Detalle y Comparativo).

### Added
- Filtro de meses propio dentro de la pestaña Comparativo, con recálculo y re-render en caliente (sin recargar la página).

### Improved
- Ajustes UI en la barra de controles del comparativo para alojar filtro de meses + toggle de inversiones.

## 1.3.0 - 2026-02-05

### Fixed
- Se corrigió la carga del comparativo 24-25 vs 25-26, parseando de forma explícita el CSV 2024-25 durante la inicialización.

### Added
- Toggle visible y persistente (localStorage) para ocultar/mostrar `INVERSIONES VARIAS` en el comparativo.

### Improved
- Mejoras UI/UX en la pestaña Comparativo: estado visual activo del toggle, foco accesible y hover de filas para facilitar lectura.

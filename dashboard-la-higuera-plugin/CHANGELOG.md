# Changelog

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

# Changelog

## 1.9.3 - 2026-02-07

### Added
- Nuevo campo de ajustes “Fórmula Power BI (opcional)” para guardar expresiones como `=Json.Document(Web.Contents("..."))`.

### Changed
- Si la fórmula Power BI contiene `Web.Contents("URL")`, el dashboard extrae y prioriza esa URL para cargar 25-26 desde API.

## 1.9.2 - 2026-02-07

### Fixed
- Se robusteció el mapeo de columnas 25-26 desde API (alias por campo + validación de columnas mínimas) para evitar cruces incorrectos de datos cuando cambian encabezados.
- Si el mapeo API queda incompleto o la API demora, el dashboard cae a fallback CSV 25-26 de forma controlada.
- Se optimizó la carga inicial: el contenido se muestra de inmediato y la actualización en vivo corre en segundo plano para evitar una espera larga en “Cargando Dashboard...”.

## 1.9.1 - 2026-02-07

### Fixed
- En Comparativo, la columna 24-25 total (Temporada completa) vuelve a calcularse sin filtro de meses para reflejar toda la temporada 24-25, incluso cuando en el header se selecciona un subconjunto de meses.

## 1.9.0 - 2026-02-07

### Changed
- Comparativo ahora usa exclusivamente el filtro global de Meses del header (se elimina el selector de meses propio de la sección).
- Se ajusta la barra de controles del comparativo para reflejar que Meses/Orden se controlan desde el header.

## 1.8.9 - 2026-02-07

### Fixed
- En Detalle, el total por faena ahora suma todos los meses seleccionados en el filtro de meses.

## 1.8.8 - 2026-02-07

### Changed
- Comparativo ahora permite ordenar por columna (24-25 total, 24-25 meses comparables, 25-26 actual, diferencia absoluta o %) usando el orden global Asc/Desc.
- Encabezados del comparativo más explícitos con subtítulos para aclarar el alcance de cada columna.
- Barra de controles del comparativo reorganizada con etiquetas y ayudas de contexto para meses, orden y el toggle de inversiones.

## 1.8.7 - 2026-02-07

### Fixed
- Estado de datos en Resumen ahora se refresca en cada actualización de filtros.
- La carga desde API filtra por RAZON SOCIAL (Agrícola La Higuera) para alinear totales con “TOTAL COSTOS”.

## 1.8.6 - 2026-02-07

### Added
- En la pestaña Resumen se muestra el bloque “Estado de datos” antes de la tabla con botón de Ocultar/Mostrar.

### Fixed
- El parser 25-26/24-25 ahora ignora filas marcadas como presupuesto cuando la base/API expone columna ORIGEN, para alinear los totales con “TOTAL COSTOS”.

## 1.8.4 - 2026-02-06

### Changed
- La base activada 24-25 ahora se guarda en `uploads/dashboard-higuera/temporada-2024-25.csv` con backup en la misma carpeta, en vez de `plugins/.../data`.
- El endpoint REST `/csv/2024-25` lee desde la ruta de uploads y valida existencia, lectura y tamaño antes de responder.
- En el admin de Base 24-25 se muestra tamaño/fecha de la base activada para confirmar la activación real.

## 1.8.3 - 2026-02-06

### Added
- En Comparativo se muestra un bloque “Estado de datos” con fuente, filas leídas/válidas, última actualización y URL final usada para 24-25, además de fuente/filas/actualización para 25-26 y filtros activos (meses + inversiones). 

### Fixed
- El fetch del CSV 24-25 ahora agrega siempre `?ts=last_2425_updated` para evitar caché y registra (solo en debug) la cantidad de filas válidas detectadas.

## 1.8.2 - 2026-02-06

### Fixed
- Validador de Base 24-25 (CSV/XLSX) ahora usa siempre 38 columnas del header como referencia, sin inferir por celdas no vacías ni recortar vacíos finales para clasificar filas.
- Cada fila se normaliza a 38 columnas (relleno con `""` si faltan) y solo se marca como incompleta si está vacía o si faltan datos en columnas obligatorias (`TEMPORADA`, `FECHA`, `PREDIO`, `SECTOR`, `CUARTEL`, `FAENA`, `NIVEL 1`, `TOTAL CUARTEL`).
- `HOROMETRO`, `REMANENTES` y `LAVADOS` quedan tratadas como opcionales (sin advertencia por vacíos).
- La salida al activar mantiene CSV con 38 columnas, delimitador `;`, comillas y fecha normalizada a `YYYY-MM-DD`.

## 1.8.1 - 2026-02-06

### Fixed
- Base 24-25 por FTP ahora prioriza siempre `base-24-25.xlsx` cuando existen ambos archivos (`.xlsx` y `.csv`).
- Se alinea la detección de fuente para que Validar y Activar usen esa misma prioridad y eviten procesar el CSV por defecto cuando hay XLSX disponible.

## 1.8.0 - 2026-02-06

### Added
- Convertidor Excel (`.xlsx`) a CSV compatible con plugin dentro de **Base 24-25 por FTP**.
- Soporte de fuente FTP alternativa `base-24-25.xlsx` (además de `base-24-25.csv`).

### Improved
- La validación/activación ahora funciona para CSV y XLSX con la misma lógica de normalización a UTF-8 y delimitador `;`.
- Al activar desde XLSX se genera también `uploads/dashboard-higuera/base-24-25.csv` normalizado.

## 1.7.0 - 2026-02-06

### Added
- Nueva gestión **Base 24-25 por FTP** en Admin > Dashboard > Base 24-25 con ruta fija: `wp-content/uploads/dashboard-higuera/base-24-25.csv`.
- Estado del archivo con tamaño, fecha de modificación, filas válidas detectadas y advertencias (filas incompletas/excesivas).
- Acción **Validar** para detectar delimitador (`;`/`,`), encoding, filas válidas y advertencias por columnas desalineadas.
- Acción **Activar base 24-25** que normaliza CSV a UTF-8 + `;`, corrige filas partidas por comillas, completa filas cortas, recorta filas largas y publica en `data/temporada-2024-25.csv`.
- Respaldo automático del archivo activo con `.bak.TIMESTAMP` y guardado de opciones: `last_import_time`, `last_rows`, `last_warnings`.

### Security
- Acciones protegidas con `manage_options` + nonce.
- Creación automática de carpeta `uploads/dashboard-higuera` con `wp_mkdir_p`.

## 1.6.2 - 2026-02-06

### Fixed
- Se robusteció la detección de temporada en CSV (normalización de guiones/espacios), evitando falsos negativos al filtrar 2024-2025 y 2025-2026.
- El comparativo ahora distingue el caso “CSV cargado pero sin filas con temporada 2024-2025 reconocible” del caso “CSV vacío”.

## 1.6.1 - 2026-02-06

### Fixed
- Se mejoraron los mensajes de estado del Comparativo para indicar claramente por qué no carga: falta de datos 25-26 (API/fallback), carga 24-25 en curso, CSV 24-25 no disponible, inválido o vacío.
- Se agregó razón interna de error para la carga de 24-25 (`comp2425ErrorReason`) y se expone en la UI del comparativo en lugar de un `Sin datos` genérico.

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

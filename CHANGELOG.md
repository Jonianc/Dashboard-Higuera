## 1.30.5 - 2026-05-05
- Refinamiento compacto del sidebar: encabezado superior y caja interna de filtros con menor altura visual y jerarquía más limpia.
- Ajuste de espaciados y tamaños en grupos/chips/acciones para reducir scroll innecesario manteniendo filtros visibles y estilo actual.
- Cuartel integrado al scroll general del sidebar (sin mini-scroll interno) y sin reintroducir overflow horizontal.

## 1.30.4 - 2026-05-05
- Hotfix UI sidebar: se elimina overflow horizontal del panel y contenedores internos (chips/selects/acciones) con límites de ancho y `overflow-x` controlado.
- Hotfix UI sidebar: se compacta bloque introductorio y caja interna de filtros para reducir altura y peso visual.
- Hotfix UI sidebar: se ajusta ancho útil del sidebar en desktop y se ordenan grillas de Faena/Meses/Métrica/Orden, botones de acción y filtros activos sin cambios funcionales.

## 1.30.3 - 2026-05-05
- Refinamiento visual del sidebar de filtros (contenedor, jerarquía de encabezado, separación de grupos y pulido de chips/botones) sin cambios funcionales.
- Mejora de estados hover/active/focus de chips para mayor claridad del filtro activo, manteniendo coherencia en modo claro/oscuro.
- Ajustes responsive del panel de filtros para evitar compresión visual en tablet/mobile sin alterar el layout funcional.

## 1.30.2 - 2026-05-04
- UI polish (Header): se ajusta jerarquía visual, altura y espaciado de acciones para una cabecera más limpia.
- UI polish (Sidebar filtros): mejor separación entre grupos, uniformidad de chips/botones y scroll usable del panel sticky.
- UI polish (Tabs y Rentabilidad): tabs principales/rentabilidad más consistentes, chips métricos con mejor alineación y estado activo/focus.
- UI polish (KPI y tabla): cards con altura/padding homogéneos y tabla comparativa con lectura más clara (sticky head, primera columna reforzada, numérico tabular).
- Accesibilidad básica: foco visible consistente en botones, tabs, selects e inputs.
- Compatibilidad: sin cambios en cálculos, filtros, carga CSV ni lógica de negocio.

## 1.30.1 - 2026-05-04
- Hotfix (Layout): se corrige colapso visual introducido en 1.30.0 por herencia de columnas en `.dashboard-main--premium`.
- Hotfix (Grid): layout estable en desktop con 2 columnas explícitas (`sidebar 280-340px` + contenido fluido) y `min-width:0` en columna principal.
- Hotfix (Rentabilidad): tabs, chips métricos, cards KPI y tabla recuperan distribución usable sin superposición.
- Responsive: bajo `1320px` pasa a 1 columna; en mobile cards/chips se simplifican y tablas mantienen scroll horizontal.
- Compatibilidad: corrección CSS-only; sin cambios en cálculos, filtros, lógica de datos ni carga CSV.

## 1.30.0 - 2026-05-04
- UI/UX (Dashboard): actualización visual general inspirada en referencia aprobada (no pixel-perfect), manteniendo lógica y cálculos existentes.
- UI/UX (Layout): panel de filtros se presenta como sidebar más limpio en desktop y vuelve a flujo vertical en responsive.
- UI/UX (Header): header superior más moderno con mejor jerarquía tipográfica y superficie elevada.
- UI/UX (Rentabilidad): tabs principales más grandes, chips de métricas reforzados y tarjetas KPI con estilo semántico moderno.
- UI/UX (Tablas): contenedores y celdas con mayor legibilidad/espaciado y ancho útil ampliado con scroll horizontal seguro.
- Compatibilidad: sin cambios en lógica de filtros, cálculos, endpoints, ni flujo de carga CSV.

## 1.29.4 - 2026-05-02
- Fix (Rentabilidad > Comparativo temporadas): las cards principales ahora respetan la métrica seleccionada (`Resultado`, `Ingresos`, `Costos`, `Kilos`, `Ingreso/kg`, `Costo/kg`, `Ingreso/ha`, `Costo/ha`).
- Fix (Semántica visual): `Diferencia` y `% Dif.` se calculan y colorean con `diffMetric` de la métrica activa (no con `diffRes` fijo).
- Compatibilidad: se mantiene la tabla comparativa sin cambios de estructura/cálculo y se conservan cards de `Costo/kg` como apoyo.

## 1.29.3 - 2026-05-02
- Rentabilidad > Comparativo temporadas (cards): se agrega helper central `getMetricTone(metricKey, value, diffValue)` para tono semántico.
- Cards principales: número grande usa clases `.metric-positive`, `.metric-negative`, `.metric-neutral` con lógica normal e inversa para métricas de costo.
- Cards de temporada individual: `Resultado` por signo; `Costo/kg 25-26` usa comparación vs 24-25 cuando existe; sin comparativo queda neutro.
- Estilo visual leve: borde lateral semántico en cards, sin alterar estructura ni cálculos del comparativo, ni colores/lógica de la tabla.

## 1.29.2 - 2026-05-01
- Fix (Filtros): cuando `Tipo de registro` = `Solo Productivo`, el filtro `Cultivos` oculta el chip `Costos indirectos`.
- Fix (Estado filtros): si existía selección previa `__COSTOS_INDIRECTOS__`, se limpia automáticamente al pasar a `Solo Productivo` y se mantiene compatibilidad con `refreshAll()` y limpiar filtros.

## 1.29.1 - 2026-05-01
- Fix (Rentabilidad comparativo): se agregan reglas específicas en `.rent-table--compare` para colorear `.is-neg-cell`/`.is-pos-cell` (rojo/verde) y asegurar semántica visual en "Comparativo temporadas".
- Fix (Tabla comparativa): zebra/hover reforzado a nivel de `td`, incluyendo primera y última columna para consistencia visual en celdas sticky.

## 1.29.0 - 2026-05-01
- UI/UX (Rentabilidad > Comparativo temporadas): el selector de `Métrica` pasa a chips accesibles (botones con `aria-pressed`) manteniendo `#rent-metrica` sincronizado para compatibilidad con la lógica existente.
- UI/UX (Rentabilidad comparativo): la tabla agrega clase `rent-table--compare` con mayor legibilidad en desktop (contenedor, jerarquía de header, padding, zebra sutil, hover suave, primera columna más ancha y columnas numéricas alineadas a la derecha).
- UI/UX (Semántica visual): `Diferencia` y `% Dif.` muestran positivos en verde y negativos en rojo sin alterar cálculos.
- UI/UX (KPI Rentabilidad): refinado visual de cards (más aire, mejor jerarquía label/valor, borde/sombra suave) conservando layout y cálculos actuales.
- Responsive: chips con wrap en medianas, filas en móvil y tabla comparativa con scroll horizontal dentro de `table-wrap`.

## 1.28.6 - 2026-04-29
- Fix (Tabs refresh): `refreshAll()` ahora detecta tab activa solo en `.dashboard-main-tabs .tab.active[data-tab]` para evitar conflicto con tabs internas de Rentabilidad.

## 1.28.5 - 2026-04-29
- Fix (Tabs): se separa el alcance de tabs principales vs tabs internas de Rentabilidad para evitar conflicto de clase `.tab`.
- Template: el contenedor de tabs principales agrega clase `dashboard-main-tabs`.
- JS: `showTab()` y bind global ahora operan solo sobre `.dashboard-main-tabs .tab[data-tab]`.
- JS: `bindRentabilidadTabs()` mantiene manejo independiente para `.rent-tabs .tab[data-rent-tab]`.

## 1.28.4 - 2026-04-29
- Fix (Flujo admin 24-25): formularios de prevalidación/activación agregan `dlh_return_to` para volver a Ajustes > Rentabilidad.
- Fix (Redirect notice): `redirect_with_notice()` respeta `dlh_return_to` validado por `wp_validate_redirect()` y mantiene query args de notice.
- Fix (Rentabilidad settings): `render_page_rentabilidad()` ahora muestra notices `dlh_notice_type/dlh_notice`.
- Fix (Diagnóstico totales): `analyze_rentabilidad_csv_content()` calcula `ingresos_kilo`, `costo_kilo`, `ingreso_hectarea` y `costo_hectarea` en `totals`.

## 1.28.3 - 2026-04-29
- Fix (Admin Rentabilidad): la card "Base comparativa rentabilidad 24-25" se renderiza fuera del formulario `options.php` para evitar formularios anidados.
- Fix (Flujo 24-25): prevalidación y activación quedan en formularios POST separados (`prevalidate_rentabilidad_2425_upload` y `activate_rentabilidad_2425_validated`).
- Fix (Endpoint 24-25): `get_rentabilidad_2425_csv_content()` sirve solo `temporada-rentabilidad-2024-25.csv` activo; sin fallback a fuente/prevalidado.
- Fix (Diagnóstico): `rows_valid` usa filas del CSV normalizado y `rows_ignored` se calcula contra registros originales.
- Cleanup (Base 24-25): se elimina bloque duplicado de rentabilidad 24-25 con texto "Validar y activar".

## 1.28.2 - 2026-04-29
- Admin (Rentabilidad): nueva sección visible "Base comparativa rentabilidad 24-25" en Ajustes > Rentabilidad.
- Flujo 24-25: se separa en dos pasos reales: `Prevalidar archivo` y `Activar base 24-25` (activación no automática tras subir).
- Diagnóstico 24-25: se exponen nombre/fecha, columnas reconocidas/faltantes, filas leídas/útiles/ignoradas y métricas clave (ingresos, costos, resultado, costo/kg, ingreso/kg).
- Resumen: acceso rápido `Cargar rentabilidad 24-25` apuntando a Ajustes > Rentabilidad.

## 1.28.1 - 2026-04-29
- Fix (Standalone): se agrega `csvRentabilidad2425Url` a `dashboardHigueraData` para cargar comparativo 24-25 en `/dashboard/`.
- Fix (Normalización 24-25): se ignoran filas resumen `PREDIO=COSTOS`, `PREDIO=TOTAL COSTOS` y `CUARTEL=TOTAL COSTOS`; `COSTOS INDIRECTOS` sigue válido.
- Fix (Comparativo): filtros de rentabilidad unificados con `applyRentabilidadFiltersToRows(rows)` para aplicar a 24-25 y 25-26.
- Fix (Métricas ratio): `Ingreso/kg`, `Costo/kg`, `Ingreso/ha` y `Costo/ha` se calculan desde acumulados por cuartel, no por suma de ratios.
- Fix (Estado): en error de carga 24-25, el tab comparativo muestra mensaje explícito y evita renderizar 24-25 en cero.
- Admin: se agrega bloque independiente para “Base comparativa rentabilidad 24-25” con activación separada.

## 1.28.0 - 2026-04-29
- Rentabilidad: nuevo endpoint REST `dashboard-higuera/v1/csv/rentabilidad/2024-25` para base histórica comparativa.
- Importación/normalización rentabilidad: soporte de rutas `base-rentabilidad-2024-25.(csv|xlsx)` y salida activa `uploads/dashboard-higuera/temporada-rentabilidad-2024-25.csv`.
- Normalización rentabilidad: se ignora fila `TOTAL COSTOS`, se conservan filas indirectas y se recalculan siempre `RESULTADO`, `/ha` y `/kg` desde ingresos/costos.
- Dashboard: se agrega comparativo de rentabilidad por cuartel (24-25 vs 25-26) con tabs, selector de métrica, cards resumen y tabla `24-25 | 25-26 | Diferencia | %`.

## 1.27.1 - 2026-04-24
- UI/UX (Dashboard filtros): se ajusta la jerarquia final a bloques verticales full-width para `Tipo de registro`, `Cultivo` y `Cuartel`.
- UI/UX (Filtros): `Categoria` queda separada del bloque principal y `Faena / Meses` pasan a una fila secundaria independiente.
- UI/UX (Chips): se elimina el scroll horizontal de `Tipo de registro` y los chips principales hacen wrap natural.

## 1.27.0 - 2026-04-24
- UI/UX (Dashboard filtros): se reordena la jerarquia visual para priorizar `Tipo de registro`, `Cultivo`, `Cuartel`, filtros secundarios, metrica/orden y acciones.
- UI/UX (Cuartel): el filtro pasa de dropdown a chips y queda oculto hasta seleccionar uno o mas cultivos especificos.
- Compatibilidad: `Cuartel` conserva `state.filtros.cuartel`; al volver `Cultivo` a `Todos` o `Costos indirectos`, el filtro se limpia sin alterar `refreshAll()` ni `applyFilters()`.

## 1.26.1 - 2026-04-20
- Fix (Portal standalone): se restaura la carga explícita de `assets/css/dashboard-portal.css` en `output_password_portal()` para evitar portal sin estilos.
- Cache busting (Portal): CSS/JS del portal ahora usan versionado por `filemtime()` con fallback a `DASHBOARD_HIGUERA_VERSION`.
- Compatibilidad: sin cambios en flujo de nonce, POST, validación, cookie, expiración ni logout del portal.

## 1.26.0 - 2026-04-20
- UI/UX (Header standalone): se compacta la composición a bloque horizontal único (logo + título/subtítulo a la izquierda, acciones a la derecha) para evitar separación vertical y exceso de altura.
- UI/UX (Header standalone): se ajusta alineación vertical y responsive de brand/actions para mantener equilibrio visual en mobile sin romper toggle de tema ni botón `Cerrar acceso`.
- Ajustes (Acceso): se corrige ayuda del campo `Logo header` para referenciar el header standalone (se elimina mención errónea a chips de Rentabilidad).
- Validación: alto de logo acotado a clamp seguro `20–48px` (recomendado `24–40px`) en sanitización y render.

## 1.25.0 - 2026-04-20
- Admin (Acceso): nueva sección **Header standalone** con opciones para activar header personalizado, logo opcional (uploader/alt/alto), título/subtítulo, sticky y visibilidad de botón `Cerrar acceso`.
- Standalone: el template consume la configuración del header desde ajustes con defaults y lectura centralizada en helpers del standalone.
- UI/UX (Standalone): `Cerrar acceso` pasa a renderizarse dentro del header (condicional por ajuste), eliminando la barra separada.
- Compatibilidad: se mantiene intacta la lógica de portal, nonce, POST, validación, cookie, expiración, logout y toggle de tema.

## 1.24.0 - 2026-04-20
- UI/UX (Portal standalone): se rediseña el portal con contraseña con estética clara alineada al dashboard light y mejor jerarquía visual (título, mensaje, campo, CTA y meta de sesión).
- UI/UX (Portal standalone): se agrega toggle de mostrar/ocultar contraseña y se mantiene feedback de estado de envío (`Validando...`) sin alterar la lógica de autenticación.
- UI/UX (Portal standalone): se mueve CSS/JS inline a assets dedicados (`assets/css/dashboard-portal.css` y `assets/js/dashboard-portal.js`).
- UI/UX (Sesión): se ajusta el estilo de `Cerrar acceso` para integrarlo visualmente con el layout general y mejorar consistencia responsive.
- Compatibilidad: sin cambios en nonce, POST, validación, cookie, expiración ni flujo de logout.

## 1.23.0 - 2026-04-17
- UI/UX (Dashboard filtros): se mueve el bloque de **Filtros activos** dentro de la caja de filtros para mantener estado y controles en una misma sección.
- UI/UX (Acciones): se conservan y mantienen junto al bloque de filtros activos dentro del panel de filtros.
- UI/UX (Simplificación): se elimina la fila de cards KPI (`Gasto total`, `Costo por hectárea`, `Cuarteles visibles`, `Meses seleccionados`) debajo de filtros para reducir ruido visual y scroll.
- Compatibilidad: sin cambios en lógica de filtros ni en sincronización de `state.filtros`, `refreshAll()` y `clearFilterChip()`.

## 1.22.0 - 2026-04-17
- UI/UX (Dashboard filtros): se compacta la composición en una grilla alineada de 3 filas: `Cultivos/Tipo/Cuartel`, luego `Faena/Categoría/Meses`, y finalmente `Métrica/Orden/Acciones`.
- UI/UX (Layout): Cuartel se reposiciona en la primera fila y se reduce ancho visual de Faena para mejorar balance horizontal.
- UI/UX (Compactación): se reduce padding/espaciado vertical del contenedor de filtros para disminuir aire muerto sin tocar lógica funcional.
- Compatibilidad: se mantiene intacta la sincronización de filtros y flujo `state.filtros` + `refreshAll()` + `clearFilterChip()`.

## 1.21.0 - 2026-04-17
- UI/UX (Dashboard filtros): se reestructura la interfaz en 3 zonas para reducir densidad visual y mejorar jerarquía: filtros principales, filtros complementarios y bloque separado de acciones.
- UI/UX (Meses): se elimina la visual de chips permanentes y se mantiene selector multiselección en dropdown con resumen compacto de selección en la etiqueta del botón.
- UI/UX (Faena): se mantiene como select para reducir ruido visual en zona principal.
- Compatibilidad: se mantiene sincronización con `state.filtros`, `refreshAll()` y `clearFilterChip()` sin cambios de persistencia ni backend.

## 1.20.0 - 2026-04-17
- UI/UX (Dashboard filtros): se unifica patrón de chips en `/dashboard/` para **Tipo de registro** y **Meses**, reutilizando el comportamiento visual del patrón de Cultivos.
- UI/UX (Dashboard filtros): **Categoría** y **Faena** ahora alternan entre chips y select según cantidad visible (umbral `<=12` chips, `>12` select), manteniendo compatibilidad.
- Compatibilidad: **Cuartel** mantiene dropdown multiselección con búsqueda; sin cambios de lógica en `state.filtros`, `refreshAll()` y `clearFilterChip()`.
- UI/UX (Filtros activos): se homologa el estilo visual de chips activos con los chips principales.

## 1.19.1 - 2026-04-17
- Fix (Ajustes): se separan los settings groups por pantalla para evitar sobrescritura cruzada al guardar formularios parciales en `options.php`:
  - `dlh_options_access`
  - `dlh_options_data_api`
  - `dlh_options_rentabilidad`
- Compatibilidad: se mantienen intactos los nombres de options existentes; no hay migración de datos ni cambios de schema.

## 1.19.0 - 2026-04-17
- Rentabilidad (Ajustes > Datos): se agrega bloque **1. Importación masiva** antes de la carga manual, con descarga de plantilla CSV UTF-8 (`Predio, Sector, Cuartel, Kilos`) e importación local sin librerías externas.
- Rentabilidad (importador CSV): parser robusto con soporte de separador coma o punto y coma, validación por columnas obligatorias, kilos numérico, match por clave lógica (`Predio + Sector + Cuartel`) y detección de duplicados.
- Rentabilidad (preview y aplicación): nuevo resumen de resultados (`total filas`, `válidas`, `sin match`, `duplicadas`, `con error`) y acción para aplicar solo filas válidas, actualizando exclusivamente `kilos_reales` en la capa manual actual.
- Compatibilidad: se mantiene la lógica de cálculo, persistencia y edición manual existentes; no se agregan nuevas dependencias ni fuentes paralelas.

## 1.18.2 - 2026-04-15
- UX (Admin guardado por módulo): Acceso, Datos/API y Rentabilidad ahora muestran estado discreto por pantalla (`Sin cambios`, `Cambios sin guardar`, `Guardado correcto`).
- UX (Admin acciones): cada pantalla editable muestra bloque claro de acciones con submit principal del módulo.
- Compatibilidad: se mantiene Settings API actual, option keys existentes, AJAX y flujos de guardado sin autosave.

## 1.18.1 - 2026-04-15
- Fix (Admin layout): estructura de formularios y secciones renderizada explícitamente desde PHP por pantalla (sin depender de reconstrucción estructural en JS).
- Fix (Admin JS): se elimina `buildSectionCards()` y toda lógica de agrupación por `H2` para construir secciones.
- Compatibilidad: se mantienen option keys existentes, guardado por Settings API, Test API y workspace de rentabilidad.

## 1.18.0 - 2026-04-15
- Admin: menú principal renombrado a `Dashboard Higuera` y estructura modular por submenús (`Resumen`, `Acceso`, `Datos/API`, `Rentabilidad`, `Base 24-25`).
- Admin (Resumen): nueva vista de estado con cards y acciones rápidas; deja de ser formulario largo.
- Admin (Acceso/Datos/API/Rentabilidad): campos separados por módulo sin cambiar option keys ni lógica de guardado.
- Compatibilidad: `admin-settings.js` deja de depender de reconstrucción por `H2/buildSectionCards`; mantiene toggles, test API, uploaders y workspace rentabilidad.
- Fix (Admin assets): enqueue ajustado para cargar CSS/JS también en los nuevos submenús del plugin.

## 1.17.16 - 2026-04-08
- UI/UX (Cultivos): el estado activo de chips se rehace para que se vea como la opción 3 aprobada, con fondo verde marcado, borde más grueso y contraste real en modo claro y oscuro.
- Fix (Cultivos): se agregan `aria-pressed` y `data-selected` al render para reforzar el estado visual activo y evitar que estilos genéricos lo diluyan.
- Fix (Cache): versión del plugin y assets actualizada para forzar recarga del CSS/JS modificado.

## 1.17.15 - 2026-04-08
- UI/UX (Cultivos): los chips seleccionados ahora muestran un estado activo mucho más visible en modo claro y oscuro, con fondo diferenciado, borde más grueso y mayor contraste.
- Compatibilidad: ajuste solo visual/CSS; se mantiene la lógica actual de multiselección y filtros.

## 1.17.14 - 2026-04-08
- Fix (Rentabilidad): los filtros de rentabilidad ahora soportan chips multiselección de Cultivos y el filtro de Cuartel, evitando que el bloque quede vacío al filtrar.
- Fix (Cultivos): se elimina el duplicado visual de "Costos indirectos"; queda un solo chip que actúa como acceso al modo indirectos.

## 1.17.13 - 2026-04-08
- Fix (Rentabilidad): la grilla de cards usa 4 columnas en desktop (2 filas para 8 métricas) y cae a 2/1 columnas en responsive.

## 1.17.12 - 2026-04-08
- UI/UX (Rentabilidad): cards superiores se compactan y pasan a grilla 4 columnas (2 filas para 8 métricas) con tamaños homogéneos.
- UI/UX (Rentabilidad): se reduce tipografía/padding para lectura tipo “chip card” sin perder jerarquía.

## 1.17.11 - 2026-04-08
- Fix (Cache): se alinea `DASHBOARD_HIGUERA_VERSION` con la versión del plugin para forzar recarga de CSS/JS en navegadores con caché agresiva (Chrome).

## 1.17.10 - 2026-04-08
- UI/UX (Filtros): se corrige la grilla principal para evitar apilado innecesario y acomodar los controles como en la referencia compacta.
- UI/UX (Filtros): Métrica y Orden quedan alineados en la misma fila con las acciones a la derecha para optimizar espacio horizontal.
- Compatibilidad: ajuste solo de template/CSS; se mantienen IDs, lógica JS y comportamiento actual de filtros.

## 1.17.9 - 2026-04-08
- UI/UX (Filtros): se reduce ancho visual de Tipo de registro, Categoría, Cuartel y Faena para optimizar espacio horizontal.
- UI/UX (Cuartel): se elimina opción “Ninguno”; queda “Seleccionar todos” (marcar/desmarcar) + lista multiselección de cuarteles.
- UI/UX (Orden): se mantiene distribución compacta en filas: Cultivos|Tipo|Categoría, Cuartel|Faena|Meses, Métrica|Orden.

## 1.17.8 - 2026-04-08
- UI/UX (Filtros): ajuste de layout para calzar con referencia (Cultivos | Tipo/Categoría, luego Cuartel/Faena/Meses, y finalmente Métrica/Orden).
- UI/UX (Cultivos): chips en una sola fila, con “COSTOS INDIRECTOS” y “OTROS CULTIVOS” en variante de 2 líneas.
- UI/UX (Filtros): ajuste de tamaño visual en Cuartel/Faena y corrección de interacción de Cuartel en el flujo actual.

## 1.17.7 - 2026-04-08
- UI/UX (Filtros): se corrige orden visual final según referencia y se ajusta tamaño/altura de chips de Cultivos para mayor legibilidad.
- UI/UX (Filtros): iconos de Cultivos fijados con prioridad para 🍒 Cerezos y 🍇 Viña, manteniendo ambos modos (claro/oscuro).

## 1.17.6 - 2026-04-08
- UI/UX (Filtros): nueva disposición compacta en 3 filas (Cultivos/Tipo/Categoría, Cuartel/Faena/Meses, Métrica/Orden) y eliminación de búsqueda separada de Faena.
- UI/UX (Filtros): Cultivos pasa a chips multiselección compactos con iconos (🍒 Cerezos, 🍇 Viña), shortcut de Costos indirectos y soporte multilinea para etiquetas largas.
- UI/UX (Filtros): se agrega Cuartel multiselección buscable y Meses incorpora “Ninguno” (deselección total), manteniendo compatibilidad de IDs y flujo `applyFilters`.

## 1.17.5 - 2026-04-08
- UI/UX (Filtros): “Tipo de registro” (`#f_predio`) se mueve al primer lugar en la grilla primaria.
- UI/UX (Filtros): se elimina duplicado de `#f_predio` en la grilla secundaria, sin cambios de lógica.

## 1.17.4 - 2026-04-08
- UI/UX (Dark mode): se restringe `dlh-ui-reference` al tema claro para evitar que pise tokens oscuros.
- UI/UX (Dashboard): se ocultan visualmente “Fuente/Generado” en header y command center, manteniendo nodos para compatibilidad JS.

## 1.17.3 - 2026-04-08
- UI/UX (Dark mode): se eliminan fondos verde oscuro residuales en superficies del dashboard y se unifica a negro en tema oscuro.
- UI/UX (Dark mode): main panel, filter panel, meta cards, form controls, badges y botones secundarios mantienen fondo negro; verde solo como acento.

## 1.17.2 - 2026-04-08
- UI/UX (Dark mode): todas las superficies principales del dashboard pasan a negro puro (`#000000`) sin tintes verdes en fondos.
- UI/UX (Dark mode): panel principal, panel de filtros, meta cards, controles de formulario, badges y botones secundarios quedan con fondo negro en tema oscuro.
- Compatibilidad: se mantienen acentos verdes para estados/énfasis, sin cambios en lógica funcional ni toggle/storage de tema.

## 1.17.1 - 2026-04-08
- UI/UX (Dark mode): fondo real negro (`--bg: #000000`) y superficies negro/casi negro para mejorar contraste global.
- UI/UX (Dark mode): se preservan acentos verdes (`--primary`, `--accent` y relacionados) para mantener identidad visual.
- UI/UX (Dark mode): componentes clave (panel principal, filtros, meta cards, badges, controles y botones secundarios) pasan a depender de tokens para evitar colores claros hardcodeados en estado oscuro.

## 1.17.0 - 2026-04-02
- Admin UI/UX (Ajustes): corregida la detección de sección activa para que, al guardar desde el final del formulario, no vuelva erróneamente a la primera sección.
- Admin UI/UX (Base 24-25/Rentabilidad): añadida confirmación explícita antes de acciones sensibles de activación/reemplazo de base.
- Compatibilidad: cambios solo en flujo visual/admin; sin alterar permisos, nonces, persistencia de opciones ni endpoints.

## 1.16.0 - 2026-04-02
- Admin UI/UX (Ajustes): se persiste la sección activa de la navegación interna para volver al mismo bloque tras guardar o recargar.
- Compatibilidad: no se modifican opciones, permisos, nonces, endpoints ni lógica de persistencia; ajuste solo de flujo visual en admin.

## 1.15.0 - 2026-04-02
- UI/UX (Vista principal): el bloque de Rentabilidad se mueve inmediatamente debajo de la caja única de filtros para quedar siempre “arriba” en el flujo principal.
- Compatibilidad: se mantiene la lógica actual de filtros globales y cálculos de rentabilidad; cambio solo de ubicación visual.

## 1.14.0 - 2026-04-02
- UI/UX (Vista principal): Rentabilidad en Resumen pasa a mostrarse siempre visible y en la parte superior del bloque principal.
- UI/UX (Filtros): los filtros principales y secundarios se consolidan en una sola caja visual de filtros.
- Compatibilidad: se mantiene la lógica de filtros/cálculos existente, sin cambios en persistencia ni endpoints.

## 1.13.0 - 2026-04-01
- UI/UX (Resumen > Rentabilidad): se eliminan los quick-filters propios del bloque de Rentabilidad para operar solo con los filtros globales del dashboard.
- Compatibilidad: Rentabilidad mantiene ordenamiento de tabla y métricas, pero ya no aplica subfiltro local de cuartel.

## 1.12.0 - 2026-04-01
- UI/UX: el bloque de “Filtros unificados y navegación principal” adopta estilo visual tipo quick-filters (píldoras) como en Rentabilidad.
- UI/UX: selects, búsqueda, dropdown de meses y acciones de toolbar se unifican en forma/altura/borde para una lectura más consistente.
- Compatibilidad: cambio solo de estilo, sin modificar lógica de filtros ni cálculos.

## 1.11.0 - 2026-04-01
- UI/UX: la vista principal de Resumen pasa a layout de una sola columna para priorizar lectura lineal y reducir competencia visual lateral.
- UI/UX: el panel complementario de Rentabilidad deja de usar comportamiento sticky dentro de Resumen para mantener flujo vertical consistente.
- Compatibilidad: no se modifican cálculos, filtros ni lógica de datos.

## 1.10.0 - 2026-04-01
- UI/UX: se añade una capa visual estilo tablero ejecutivo (fondo claro, tarjetas más limpias, bordes suaves y jerarquía tipográfica más marcada) para aproximar la referencia solicitada.
- UI/UX: la grilla de KPI pasa a distribución auto-fit para mejorar lectura y balance en desktop sin alterar cálculos ni filtros.
- UI/UX: se refinan tabs, paneles y tabla de Resumen con estética más sobria y homogénea, manteniendo comportamiento existente.

## 1.9.42 - 2026-04-01
- UI/A11y: tabs principales ahora son navegables por teclado (Enter/Espacio/Flechas) y sincronizan `aria-selected` + `tabindex` para foco correcto.
- UI/A11y: se agregan relaciones accesibles `aria-controls`/`aria-labelledby` entre tabs y paneles, sin cambiar lógica de vistas.
- UI/A11y: dropdown de Meses expone estado con `aria-expanded` y foco visible reforzado en tabs/chips.

## 1.9.41 - 2026-03-31
- UI/UX: refinados los KPI principales con estilo más compacto, jerarquía visual superior y metadatos contextuales por tarjeta.
- UI/UX: mejorada la tabla Resumen con cabecera propia, badges de contexto, ranking visual por cuartel y mayor énfasis en la columna Total.
- UI/UX: ajustadas densidad, zebra suave, lectura numérica y tratamiento visual del bloque de estado para que Resumen se vea más premium sin tocar cálculos.

## 1.9.40 - 2026-03-31
- UI/UX: la vista Resumen se reestructura con una capa ejecutiva superior (señales rápidas) para mejorar contexto y jerarquía visual.
- UI/UX: Rentabilidad se integra como bloque complementario dentro de Resumen, con menor peso visual y sin cortar el flujo principal.
- UI/UX: el estado de datos baja de protagonismo y queda como bloque secundario al final de Resumen.

## 1.9.39 - 2026-03-31

### Improved
- Reordena la cabecera operativa del dashboard en dos niveles: filtros principales y filtros secundarios, separando mejor contexto, acciones y lectura.
- Añade tarjetas de estado rápido para fuente activa y fecha de generación dentro del workspace superior.
- Convierte los chips de filtros activos en elementos accionables para limpiar filtros individuales sin recorrer toda la barra.
- Refuerza la navegación con una sección de vistas más dominante y cabeceras internas para Resumen, Comparativo, Gráficos y Detalle.
- Compacta la transición entre filtros, KPIs y tabs para que la pantalla principal se sienta más ordenada y con mejor jerarquía visual.

## 1.9.38 - 2026-03-30

### Improved
- Se compactó la cabecera del dashboard y la barra de filtros unificados para reducir altura y limpiar la jerarquía visual.
- Se subieron las tabs principales para que Resumen, Comparativo, Gráficos y Detalle queden antes del bloque extenso de Rentabilidad.
- Se convirtió el bloque superior de Rentabilidad en un panel colapsable para evitar exceso de scroll vertical y mejorar la navegación.

## 1.9.37 - 2026-03-30
- rehace la cabecera del dashboard en una barra premium sobria y horizontal, eliminando el panel lateral reciclado de filtros.
- reorganiza los filtros unificados en una franja full width con mejor jerarquía visual y uso real del ancho disponible.
- recompone la fila de KPIs, tabs y paneles para que el contenido principal se distribuya a todo el ancho en desktop.
- refuerza el layout con anchos forzados y estilos namespaced para evitar que bloques genéricos queden encajonados a la izquierda.

## 1.9.36
- Unificados los filtros del dashboard en un panel maestro dentro del contenido principal.
- Rentabilidad pasa a concentrar la experiencia de filtros y se elimina el sidebar lateral de filtros.
- Mantiene persistencia de filtros entre pestañas con la misma lógica de estado existente.
- Mejorado el aprovechamiento del ancho útil al eliminar la columna lateral fija.

## 1.9.35 - 2026-03-30
- corrige la obtención de Has en Rentabilidad híbrida: si la ruta principal deja hectáreas en 0, ahora se rehidratan desde la fuente cruda API/CSV usando el mismo compound key por predio, sector y cuartel.
- agrega fallback adicional para usar directamente el catálogo crudo cuando la conversión previa a CSV no entrega filas válidas.
- incorpora conteo de cuarteles sin hectáreas y metadatos de fuente cruda para facilitar diagnóstico.

## 1.9.34 - 2026-03-30
- compacta la vista de boxes en Rentabilidad > Datos para reducir ruido visual y aprovechar mejor el ancho en desktop.
- deja visibles solo los campos esenciales en cada box: kilos, ingreso exportación, ingreso mercado nacional y otro ingreso.
- simplifica los cálculos visibles a resultado, total ingresos, ingreso/kg y costo/kg; hectárea queda fuera de la vista principal.
- mueve predio y sector a una línea secundaria del encabezado y resume Has + Costos API en una franja compacta superior.
- deja `Otras ventas DTE` fuera de la vista principal y la muestra solo como nota cuando tiene valor cargado.

## 1.9.33 - 2026-03-30
- reemplaza la tabla de carga por cuartel por una vista en boxes de 2 columnas para aprovechar mejor el ancho disponible.
- agrega búsqueda, filtro por estado, orden y toggle de solo cambios dentro de la vista de boxes.
- incorpora acciones por box para restaurar, limpiar y eliminar filas manuales.
- mejora la edición con autoselect al enfocar y navegación con Enter / Shift+Enter.
- mantiene el guardado general existente como flujo estable; no se activa auto guardado todavía para no introducir riesgo en persistencia.

## 1.9.30 - 2026-03-30
- Rediseñada la tab `Datos` de Ajustes > Rentabilidad con una tabla de carga más clara: grupos visuales para identificación, edición y cálculos automáticos.
- Añadidas columnas fijas para `Predio`, `Sector`, `Cuartel`, `Has` y `Costos API`, evitando perder contexto al desplazarse horizontalmente.
- Compactados inputs y filas, mejorando la densidad visual, la lectura de montos y el estado por fila (`Nueva`, `Incompleta`, `Lista`, `Editada`).
- Ajustada la barra inferior global de guardado para que deje de verse como un bloque gigante y muestre un estado simple de cambios pendientes.

## 1.9.29 - 2026-03-30
- Rentabilidad híbrida ahora toma costos y hectáreas desde el mismo CSV normalizado que usa el dashboard 25-26: si la API responde, primero se transforma con los mismos filtros del frontend (`temporada=2025-2026`, `predio contains HIGUERA`, `razon social = AGRICOLA LA HIGUERA S.A.`) y recién después se agrupa para rentabilidad.
- Eliminado el comportamiento sticky del panel superior en Ajustes > Rentabilidad para que el bloque quede en flujo normal al hacer scroll.
- Añadido comparativo técnico en Diagnóstico con costo dashboard vs costo rentabilidad por cuartel para validar rápido si ambas rutas están alineadas.
- Ajustado el mensaje de fuente activa para dejar explícito que el archivo legacy no participa cuando el cálculo está en modo híbrido.

## 1.9.28 - 2026-03-30
- Corregido el mapeo híbrido de rentabilidad para priorizar columnas exactas como `TOTAL CUARTEL` y `SUPERFICIE REAL`, reduciendo descalces con el dashboard general cuando la API expone otras columnas parecidas (`TOTAL`, `VALOR`, etc.).
- Corregida la visualización de hectáreas en Ajustes > Rentabilidad para respetar decimales en lugar de redondear a enteros.
- Corregido el header sticky de Rentabilidad para evitar solapes entre KPIs, títulos y estado; los KPI ahora usan una grilla flexible y más robusta.
- Mejorada la densidad de la tabla de carga manual y la claridad del diagnóstico, marcando el archivo de rentabilidad como `legacy` cuando el cálculo activo usa esquema híbrido API + manual.

## 1.9.27 - 2026-03-29
- Rediseñada la UI/UX de Ajustes > Rentabilidad con un workspace premium: resumen sticky superior, tabs internas (Resumen / Datos / Diagnóstico) y superficies visuales más claras.
- La carga manual complementaria ahora muestra tabla editable directa con validación en vivo, placeholders, más métricas calculadas visibles y resaltado de filas nuevas/incompletas/listas para reducir errores.
- Añadidas acciones rápidas visibles en el bloque Rentabilidad para guardar, agregar fila manual y recalcular el catálogo híbrido sin salir del flujo.
- Añadido feedback doble al guardar: aviso fijo en la página más toast discreto al volver desde options.php.

## 1.9.26 - 2026-03-29
- Rentabilidad ahora puede calcularse en modo híbrido sin base externa: cuarteles, hectáreas y costos se levantan desde la API 25-26 o desde el CSV fallback, mientras kilos e ingresos se ingresan manualmente en Ajustes.
- Añadida tabla editable en Ajustes > Rentabilidad para capturar kilos reales e ingresos por `Predio + Sector + Cuartel`, con búsqueda, recarga de catálogo y guardado en la configuración del plugin.
- El endpoint/CSV de rentabilidad pasa a priorizar el dataset híbrido calculado en servidor, manteniendo fallback legacy solo cuando no hay catálogo híbrido disponible.
- Añadido diagnóstico ampliado del bloque Rentabilidad para mostrar fuente activa, cantidad de cuarteles del catálogo y filas manuales con datos.

## 1.9.25 - 2026-03-27
- Corregida la vista standalone `/dashboard/` para inyectar la misma configuración de rentabilidad que el frontend normal (endpoints, fallback inline, cards e íconos).
- Corregida la normalización de encabezados de rentabilidad para reconocer correctamente columnas con acentos y mayúsculas como `HECTÁREAS`.
- Eliminado el falso error `No hay endpoint configurado para la base de rentabilidad` cuando la base sí estaba activa en admin.

## 1.9.24 - 2026-03-27
- Corregida la sincronización entre admin y frontend para rentabilidad usando una normalización canónica única al activar la base.
- La base activa `temporada-rentabilidad.csv` ahora se genera con encabezados ASCII estables y métricas derivadas, evitando desajustes como `HECTÁREAS` vs `HECTAREAS`.
- Añadido fallback inline controlado en frontend para que el bloque de Rentabilidad pueda renderizar la misma base servida por PHP aunque falle el endpoint REST.
- Mejorada la detección de delimitador en JavaScript para priorizar separadores consistentes fuera de comillas.
- Añadido endpoint REST de diagnóstico de rentabilidad y panel admin de sincronización con hashes/tamaños para depurar si frontend y admin están leyendo el mismo dataset.

## 1.9.23 - 2026-03-26
- Mejorada la página admin de gestión de bases con layout técnico en 2 columnas y jerarquía visual más clara.
- Añadido flujo real de carga de base de rentabilidad desde admin mediante selector clásico de archivo CSV/XLSX.
- La validación de rentabilidad ahora activa automáticamente la base si pasa el análisis, evitando pasos manuales redundantes.
- Añadido checklist técnico visible para archivo leído, columnas reconocidas, filas válidas, métricas detectadas y estado listo para activar.
- Añadido preview de columnas reconocidas y faltantes para detectar problemas de encabezado antes de romper la base vigente.
- Reforzado el flujo seguro: el archivo se valida en temporal antes de reemplazar la fuente actual de rentabilidad.

## 1.9.22 - 2026-03-26
- Corregido el flujo de rentabilidad para que el frontend pueda usar la base activa o, si aún no existe, un fallback desde la base fuente CSV/XLSX.
- Corregido el parseo numérico de rentabilidad para valores con separadores de miles y decimales, evitando métricas truncadas.
- Mejorada la UI completa de Ajustes con layout modernizado, secciones visuales, navegación rápida y acción sticky de guardado.
- Mejorada la gestión de cards de rentabilidad con reordenamiento por flechas en lugar de números manuales.
- Añadido uploader de medios con preview para los íconos de Sector y Cuartel.
- Añadido panel de diagnóstico de rentabilidad en Ajustes con archivo detectado, base activa, columnas reconocidas y métricas calculadas.
- Mejorado el bloque frontend de Rentabilidad con estado vacío visible, cards más destacadas, chips con icono+texto y tabla ordenable.

## 1.9.21
- Añadido bloque de rentabilidad en Resumen con cards configurables desde Ajustes.
- Añadidos filtros rápidos de Sector y Cuartel con íconos configurables por URL.
- Añadido soporte para importar y activar una base adicional de rentabilidad desde uploads/dashboard-higuera/base-rentabilidad.csv o .xlsx.
- Añadido endpoint REST para servir el CSV activo de rentabilidad.

# Changelog

## 1.9.20 - 2026-03-17

### Fixed
- Se corrige el layout para mostrar filtros en barra lateral real (desktop) con contenido principal a la derecha.
- Se corrige el selector claro/oscuro para que sea visible y operable, con etiqueta de estado (`Claro`/`Oscuro`) y persistencia.
- Se aplican explícitamente los colores solicitados para temas light/dark en el wrapper del dashboard.

## 1.9.19 - 2026-03-17

### Fixed
- Hotfix de visibilidad UI: se fuerza contraste, tipografía legible y estructura de 2 columnas (sidebar + contenido) para evitar vista “lavada” por estilos del tema activo.
- Se refuerzan estilos del switch claro/oscuro para estados visuales más claros y consistentes.

## 1.9.18 - 2026-03-17

### Changed
- Se corrige la UI para visibilidad de layout (sidebar + área principal) y se refuerza la paleta solicitada en light/dark.
- Se elimina del header la visualización de los botones técnicos `Cambiar CSV` y `Guardar HTML con datos` (la lógica interna permanece compatible).
- Se ajusta el switch de tema a estilo toggle redondeado tipo referencia, ubicado en la esquina superior derecha.

## 1.9.17 - 2026-03-17

### Changed
- Refactor UI/UX del dashboard: layout con sidebar de filtros + área principal, tarjetas KPI, chips de filtros activos y tabs con estilo renovado y responsive.
- Nuevo switch de tema claro/oscuro con persistencia en localStorage y aplicación global por `data-theme`.
- Nuevas acciones en filtros: `Limpiar filtros` y `Restablecer vista` (incluye volver a tab Resumen).

## 1.9.16 - 2026-03-17

### Added
- Se agrega `PLUGIN_MAP.md` con el mapeo operativo del plugin (núcleo, frontend, standalone, admin, importación, datos y documentación).

## 1.9.15 - 2026-02-25

### Added
- Nueva ruta REST `dashboard-higuera/v1/api/2025-26` que consulta la API 25-26 desde servidor con caché en `transients` para reducir latencia y carga repetida en frontend.

### Changed
- El frontend (shortcode y standalone) ahora prioriza `apiProxyUrl` para cargar 25-26 vía servidor cacheado, manteniendo fallback CSV cuando la API no está disponible.
- Se agrega filtro `dlh_api_cache_ttl` (default 300s, mínimo 30s) para ajustar el tiempo de caché de la API 25-26.

## 1.9.14 - 2026-02-25

### Changed
- Se refactoriza `dashboard-template.php` para eliminar el snapshot HTML masivo (tablas/opciones precargadas) y dejar solo el skeleton base renderizado por JavaScript en tiempo real.
- Se mantiene la misma estructura funcional (filtros, tabs, comparativo, detalle, gráficos), reduciendo drásticamente peso y deuda de mantenimiento del template.

## 1.9.13 - 2026-02-25

### Security
- Se elimina el token/API hardcodeado de los valores por defecto del plugin para evitar exposición de secretos en código fuente.
- La URL de API ahora puede definirse de forma segura mediante la constante `DLH_API_URL` (prioritaria) o mediante la opción guardada en Ajustes.

### Changed
- Se unifica la lectura de URL API en `Dashboard_La_Higuera::get_configured_api_url()` y se reutiliza en shortcode, standalone y panel de ajustes.
- Si no existe URL API configurada, el frontend evita usar una URL hardcodeada y cae al flujo de fallback CSV 25-26.

## 1.9.12 - 2026-02-25

### Security
- Se endurecen los endpoints REST de CSV (`/dashboard-higuera/v1/csv/2025-26` y `/dashboard-higuera/v1/csv/2024-25`): ya no son públicos por defecto y ahora respetan el control de acceso configurado del plugin (`public`, `logged_in`, `role`).

### Changed
- Se centraliza la verificación de acceso en una función reutilizable para mantener consistencia entre standalone y REST.

## 1.9.11 - 2026-02-19

### Fixed
- Se corrige el ingreso del portal: ya no depende del `name` del botón submit (que podía perderse al deshabilitarse en estado `Validando...`).
- El formulario vuelve a procesar correctamente la contraseña usando el nonce del portal y un campo hidden de acción.

### Improved
- Ajustes visuales del portal standalone para un layout más parejo: ancho/padding refinados y altura consistente entre input y botón.
- Se mejora el feedback de envío para evitar doble submit sin bloquear el flujo de autenticación.

## 1.9.10 - 2026-02-19

### Improved
- Portal standalone de contraseña con UI/UX renovada: layout más claro, mejor contraste, foco visible y botón principal más legible.
- Se agrega estado de envío en el formulario (`Validando...`) para evitar doble submit y mejorar feedback de interacción.
- Mensaje informativo de duración de sesión (8 horas) y alerta de error accesible (`role="alert"`, `aria-live`).
- Botón de `Cerrar acceso` en standalone ahora usa estilos consistentes (sin inline styles) para mejor mantenibilidad visual.

### Changed
- En Ajustes, el campo de contraseña del portal ahora muestra estado actual (configurada/no configurada) y recomendación de longitud mínima.

## 1.9.9 - 2026-02-19

### Added
- Nuevo portal de acceso con contraseña configurable desde Ajustes, aplicado solo a la URL standalone del dashboard.
- Opciones en Ajustes para activar portal, definir contraseña, título y mensaje de acceso.

### Changed
- Se agrega cierre de acceso (logout del portal) en standalone para volver a bloquear la vista.
- El panel de Ajustes oculta/muestra los campos del portal según si está habilitado.

## 1.9.8 - 2026-02-18

### Added
- Nuevo `admin-settings.js` para mejorar UX en Ajustes: sincroniza roles, muestra/oculta campos de fuente API y valida en vivo URL/fórmula Power BI.
- Nuevo `admin-settings.css` con estilos para feedback de validación y resultados del Test de API.

### Changed
- Se elimina JavaScript inline en la página de Ajustes y se mueve a archivos de assets dedicados para mejor mantenibilidad.
- El bloque de Test de API ahora entrega resultados con badges de estado, tiempos y fuente más rápida en formato visual más claro.

## 1.9.7 - 2026-02-07

### Changed
- El botón Mostrar/Ocultar INVERSIONES VARIAS se mueve a los filtros globales para mantener el flujo de uso actual.

## 1.9.6 - 2026-02-07

### Changed
- Se revierte el ajuste de carga inicial del template: el contenido vuelve a mostrarse solo después de completar la inicialización (loader “Cargando Dashboard...” hasta terminar).

## 1.9.5 - 2026-02-07

### Added
- En Ajustes se agrega botón “Test de API (velocidad)” para medir URL API y, si aplica, URL extraída desde Fórmula Power BI.

### Changed
- El test muestra estado, tiempo (ms), filas detectadas y recomendación de fuente más rápida sin cambiar automáticamente la configuración.

## 1.9.4 - 2026-02-07

### Added
- En Ajustes se agrega selector “Fuente API 25-26” para elegir explícitamente entre “Usar URL de la API” y “Usar Fórmula Power BI”.

### Changed
- La carga 25-26 ahora respeta la fuente elegida en ajustes (no prioriza fórmula automáticamente si está en modo URL).

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

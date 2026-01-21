# Dashboard La Higuera - Plugin de WordPress

Plugin de WordPress para visualizar el dashboard interactivo de Agrícola La Higuera con datos de costos y faenas.

## 📋 Descripción

Este plugin permite integrar el Dashboard La Higuera en cualquier sitio WordPress mediante un simple shortcode. Muestra datos de costos y faenas de forma interactiva con múltiples filtros y visualizaciones.

## 🚀 Instalación

### Método 1: Instalación Manual

1. Descarga o clona este repositorio
2. Comprime la carpeta `dashboard-la-higuera` en un archivo ZIP
3. Ve a tu panel de WordPress → Plugins → Añadir nuevo
4. Haz clic en "Subir plugin"
5. Selecciona el archivo ZIP y haz clic en "Instalar ahora"
6. Activa el plugin

### Método 2: Instalación FTP

1. Sube la carpeta `dashboard-la-higuera` a `/wp-content/plugins/`
2. Ve a WordPress → Plugins
3. Activa "Dashboard La Higuera"

## 💡 Uso

### Shortcode Básico

Para mostrar el dashboard en cualquier página o entrada:

```
[dashboard_higuera]
```

### En el Editor Clásico

1. Crea o edita una página
2. Agrega el shortcode `[dashboard_higuera]` donde quieras que aparezca el dashboard
3. Publica o actualiza la página

### En el Editor de Bloques (Gutenberg)

1. Crea o edita una página
2. Agrega un bloque de "Shortcode"
3. Escribe `[dashboard_higuera]`
4. Publica o actualiza la página

### 🎯 Modo Ancho Completo (Sin Header/Footer) - RECOMENDADO

Para mostrar el dashboard a **ancho completo sin el header ni footer** de tu tema WordPress:

1. **Crea una nueva página:**
   - WordPress → Páginas → Añadir nueva
   - Título: "Dashboard" (o el que prefieras)

2. **Selecciona el template:**
   - En el panel derecho, busca "Atributos de página"
   - En "Plantilla", selecciona: **"Dashboard Full Width (Sin Header/Footer)"**

3. **Agrega el shortcode:**
   - En el contenido de la página, agrega: `[dashboard_higuera]`

4. **Publica:**
   - Haz clic en "Publicar"
   - La página mostrará SOLO el dashboard, sin header ni footer, a ancho completo

**Ventajas del modo Full Width:**
- ✅ Sin header ni footer del tema
- ✅ Ancho completo (100% de la pantalla)
- ✅ Máximo espacio para visualizar datos
- ✅ Experiencia inmersiva
- ✅ No hay distracciones del tema

## 📊 Funcionalidades

- **Filtros dinámicos**: Por predio, cultivo, nivel 1, faena, métrica, meses y orden
- **4 pestañas de visualización**:
  - Resumen: Tabla por cuarteles con desglose
  - Gráficos: Visualización en barras y circular
  - Comparativo: Comparación entre temporadas 24-25 y 25-26
  - Detalle: Desglose por faena
- **Métricas**: Gasto total y costo por hectárea
- **Responsive**: Se adapta a dispositivos móviles

## 📁 Estructura del Plugin

```
dashboard-la-higuera/
├── dashboard-la-higuera.php          # Archivo principal del plugin
├── README.md                          # Este archivo
├── assets/
│   ├── css/
│   │   └── dashboard.css              # Estilos del dashboard
│   └── js/
│       └── dashboard.js               # Lógica JavaScript
├── includes/
│   └── class-dashboard-shortcode.php  # Clase del shortcode
├── templates/
│   └── dashboard-template.php         # Template HTML
└── data/
    ├── temporada-2025-26.csv          # Datos temporada actual
    └── temporada-2024-25.csv          # Datos temporada anterior
```

## 🔧 Actualización de Datos

Para actualizar los datos del dashboard:

1. Accede al servidor vía FTP o cPanel
2. Navega a `/wp-content/plugins/dashboard-la-higuera/data/`
3. Reemplaza los archivos CSV:
   - `temporada-2025-26.csv`
   - `temporada-2024-25.csv`
4. Limpia la caché de WordPress (si usas un plugin de caché)

## 🎨 Personalización

### CSS Personalizado

Para agregar estilos personalizados, agrega CSS en tu tema:

```css
/* En Apariencia → Personalizar → CSS Adicional */
#dashboard-higuera-wrapper {
    /* Tus estilos personalizados */
}
```

## 🐛 Solución de Problemas

### El dashboard no se muestra

1. Verifica que el plugin esté activado
2. Asegúrate de que el shortcode esté escrito correctamente: `[dashboard_higuera]`
3. Revisa la consola del navegador para errores JavaScript

### Los datos no cargan

1. Verifica que los archivos CSV estén en la carpeta `data/`
2. Comprueba los permisos de los archivos (deben ser legibles)
3. Limpia la caché de WordPress

### Conflictos con otros plugins

Si experimentas problemas:
1. Desactiva otros plugins uno por uno para identificar conflictos
2. Verifica que no haya conflictos de JavaScript en la consola

## 📝 Requisitos

- WordPress 5.0 o superior
- PHP 7.0 o superior
- Navegador moderno con JavaScript habilitado

## 📄 Licencia

GPL v2 or later

## 👥 Autor

Agrícola La Higuera S.A.

## 🔗 Enlaces

- [Repositorio GitHub](https://github.com/Jonianc/Dashboard-Higuera)

## 📮 Soporte

Para reportar problemas o solicitar funcionalidades, por favor usa el [sistema de issues en GitHub](https://github.com/Jonianc/Dashboard-Higuera/issues).

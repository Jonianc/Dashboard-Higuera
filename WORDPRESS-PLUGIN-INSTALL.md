# 🔌 Instalación del Plugin WordPress - Dashboard La Higuera

## ✅ Plugin Completado

El plugin de WordPress está listo para usar. Aquí tienes las instrucciones de instalación:

## 📦 Archivo de Instalación

**Archivo:** `dashboard-la-higuera.zip` (101 KB)

Este archivo ZIP contiene todo lo necesario para instalar el plugin en WordPress.

## 🚀 Instrucciones de Instalación

### Opción 1: Desde el Panel de WordPress (Recomendado)

1. **Accede a tu WordPress:**
   - Ve a tu sitio WordPress
   - Inicia sesión como administrador

2. **Instala el plugin:**
   - Ve a `Plugins` → `Añadir nuevo`
   - Haz clic en `Subir plugin` (parte superior de la página)
   - Haz clic en `Seleccionar archivo`
   - Selecciona `dashboard-la-higuera.zip`
   - Haz clic en `Instalar ahora`

3. **Activa el plugin:**
   - Una vez instalado, haz clic en `Activar plugin`

4. **Usa el shortcode:**
   - Crea una nueva página o edita una existente
   - Agrega el shortcode: `[dashboard_higuera]`
   - Publica la página

### Opción 2: Vía FTP

1. **Extrae el ZIP:**
   - Descomprime `dashboard-la-higuera.zip`
   - Obtendrás la carpeta `dashboard-la-higuera/`

2. **Sube la carpeta:**
   - Conecta a tu servidor vía FTP
   - Sube la carpeta a: `/wp-content/plugins/dashboard-la-higuera/`

3. **Activa el plugin:**
   - Ve al panel de WordPress
   - Ve a `Plugins`
   - Busca "Dashboard La Higuera"
   - Haz clic en `Activar`

## 💡 Uso del Plugin

### En una Página

1. **Editor Clásico:**
   ```
   [dashboard_higuera]
   ```

2. **Editor de Bloques (Gutenberg):**
   - Agrega un bloque de "Shortcode"
   - Escribe: `[dashboard_higuera]`

### Ejemplo de Página

```
Título: Dashboard Agrícola

Contenido:
Bienvenido al dashboard de Agrícola La Higuera.

[dashboard_higuera]

Aquí puedes ver todos los datos de la temporada actual.
```

### 🎯 Modo Ancho Completo (Sin Header/Footer) - **RECOMENDADO**

Para mostrar el dashboard a **ancho completo sin el header ni footer** de tu tema WordPress:

**Pasos:**

1. **Crear página:**
   - WordPress → Páginas → Añadir nueva
   - Título: "Dashboard" (o el nombre que prefieras)

2. **Seleccionar template:**
   - En el panel derecho de la página, busca **"Atributos de página"**
   - En el selector **"Plantilla"**, selecciona:
     ```
     Dashboard Full Width (Sin Header/Footer)
     ```

3. **Agregar shortcode:**
   - En el contenido de la página, escribe:
     ```
     [dashboard_higuera]
     ```

4. **Publicar:**
   - Haz clic en "Publicar"
   - ¡Listo! Tu dashboard se verá a pantalla completa

**Resultado:**
- ✅ **Sin header** del tema WordPress
- ✅ **Sin footer** del tema WordPress
- ✅ **Ancho 100%** de la pantalla
- ✅ **Sin menús de navegación**
- ✅ **Máxima visualización** de datos
- ✅ **Experiencia dedicada** al dashboard

**Antes vs Después:**

| Con tema normal | Con template Full Width |
|----------------|------------------------|
| Header del tema | ❌ Sin header |
| Menú de navegación | ❌ Sin menú |
| Ancho limitado (container) | ✅ Ancho 100% |
| Footer del tema | ❌ Sin footer |
| Sidebar (algunos temas) | ❌ Sin sidebar |

## 📊 Características del Plugin

- ✅ **Shortcode simple:** `[dashboard_higuera]`
- ✅ **Autocontenido:** No requiere configuración
- ✅ **Datos incluidos:** CSVs de ambas temporadas
- ✅ **Responsive:** Se adapta a móviles y tablets
- ✅ **Filtros dinámicos:** 7 filtros diferentes
- ✅ **4 pestañas:** Resumen, Gráficos, Comparativo, Detalle

## 🔧 Actualización de Datos

Para actualizar los datos del dashboard:

1. **Vía FTP:**
   ```
   /wp-content/plugins/dashboard-la-higuera/data/
   └── Reemplaza:
       ├── temporada-2025-26.csv
       └── temporada-2024-25.csv
   ```

2. **Limpia la caché:**
   - Si usas un plugin de caché (WP Super Cache, W3 Total Cache, etc.)
   - Limpia la caché después de actualizar los CSVs

## 🎨 Personalización

### Cambiar el ancho del dashboard

```css
/* En Apariencia → Personalizar → CSS Adicional */
#dashboard-higuera-wrapper {
    max-width: 1400px;
    margin: 0 auto;
}
```

### Cambiar colores

```css
#dashboard-higuera-wrapper {
    --accent: #22d3ee;    /* Color principal */
    --accent2: #60a5fa;   /* Color secundario */
}
```

## 🐛 Solución de Problemas

### El shortcode aparece como texto

**Problema:** Ves literalmente `[dashboard_higuera]` en la página.

**Solución:**
1. Verifica que el plugin esté activado
2. Asegúrate de que el shortcode esté en modo HTML, no en un bloque de código

### El dashboard no carga

**Problema:** La página está en blanco o muestra un error.

**Soluciones:**
1. Revisa la consola del navegador (F12)
2. Verifica los permisos de los archivos CSV
3. Desactiva otros plugins para detectar conflictos
4. Aumenta el límite de memoria de PHP (si necesario)

### Los filtros no funcionan

**Problema:** Los filtros no responden.

**Soluciones:**
1. Limpia la caché del navegador (Ctrl+F5)
2. Verifica que JavaScript esté habilitado
3. Revisa conflictos con otros plugins de JavaScript

## 📝 Requisitos Técnicos

- **WordPress:** 5.0 o superior
- **PHP:** 7.0 o superior
- **Navegador:** Chrome, Firefox, Safari, Edge (versiones recientes)
- **JavaScript:** Habilitado

## 📦 Contenido del Plugin

```
dashboard-la-higuera.zip (101 KB comprimido)
└── dashboard-la-higuera/
    ├── dashboard-la-higuera.php (3.3 KB)    # Plugin principal
    ├── README.md (4.1 KB)                    # Documentación
    ├── assets/
    │   ├── css/
    │   │   └── dashboard.css (4 KB)         # Estilos
    │   └── js/
    │       └── dashboard.js (46 KB)         # Lógica
    ├── data/
    │   ├── temporada-2025-26.csv (1.2 MB)   # Datos actuales
    │   └── temporada-2024-25.csv (56 KB)    # Datos anteriores
    ├── includes/
    │   └── class-dashboard-shortcode.php    # Clase del shortcode
    └── templates/
        └── dashboard-template.php (109 KB)  # Template HTML
```

## 🔗 Enlaces Útiles

- **Repositorio:** https://github.com/Jonianc/Dashboard-Higuera
- **Issues:** https://github.com/Jonianc/Dashboard-Higuera/issues

## 📧 Soporte

Para soporte técnico o consultas:
- Crear un issue en GitHub
- Contactar al equipo de desarrollo

---

**¡Listo!** El plugin está instalado y funcionando. Ahora puedes ver el dashboard en cualquier página de tu WordPress usando `[dashboard_higuera]`.

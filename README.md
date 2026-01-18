# Dashboard La Higuera 25-26

Dashboard interactivo para visualizar datos de costos y faenas de Agrícola La Higuera.

## 📁 Estructura del Proyecto

```
Dashboard-Higuera/
├── index.html                              # Dashboard principal
├── css/
│   └── dashboard.css                       # Estilos del dashboard (4 KB)
├── js/
│   └── main.js                             # Lógica del dashboard (46 KB)
├── data/
│   ├── temporada-2025-26.csv               # Datos temporada actual (1.2 MB)
│   └── temporada-2024-25.csv               # Datos temporada anterior (56 KB)
└── dashboard_la_higuera_25-26_dropdown.html # Versión original (5.3 MB)
```

## 🚀 Cómo Usar

### Opción 1: Servidor Local (Recomendado)

Para visualizar el dashboard correctamente, necesitas ejecutarlo desde un servidor web local debido a las restricciones de CORS al cargar archivos CSV.

#### Con Python 3:
```bash
python3 -m http.server 8000
```

#### Con Node.js:
```bash
npx http-server -p 8000
```

#### Con PHP:
```bash
php -S localhost:8000
```

Luego abre tu navegador en: `http://localhost:8000`

### Opción 2: Versión Original

Si prefieres usar la versión autocontenida que no requiere servidor:
```bash
# Abre directamente en el navegador
open dashboard_la_higuera_25-26_dropdown.html
```

## ✨ Características

- **Filtros dinámicos**: Por predio, cultivo, nivel 1, faena, métrica, meses y orden
- **4 pestañas de visualización**:
  - Resumen: Tabla por cuarteles con desglose
  - Gráficos: Visualización en barras y circular
  - Comparativo: Comparación entre temporadas 24-25 y 25-26
  - Detalle: Desglose por faena

- **Métricas disponibles**:
  - Gasto total
  - Costo por hectárea

## 📊 Datos

Los datos CSV contienen información sobre:
- Faenas agrícolas
- Mano de obra
- Insumos
- Servicios de terceros
- Maquinarias
- Gastos generales

## 💡 Ventajas de la Nueva Estructura

### Antes (versión original):
- ✗ Archivo único de 5.3 MB
- ✗ Difícil de mantener
- ✗ Sin cacheo efectivo
- ✗ Lento de cargar

### Ahora (versión modular):
- ✓ HTML: 108 KB (99% más pequeño)
- ✓ CSS separado: 4 KB
- ✓ JavaScript: 46 KB (99% más pequeño)
- ✓ Datos externos: Carga bajo demanda
- ✓ Cacheo efectivo de archivos
- ✓ Fácil de mantener y actualizar

## 🔧 Actualización de Datos

Para actualizar los datos del dashboard:

1. Reemplaza los archivos CSV en la carpeta `data/`:
   - `data/temporada-2025-26.csv`
   - `data/temporada-2024-25.csv`

2. Recarga el navegador (Ctrl+F5 para limpiar caché)

## 📝 Notas Técnicas

- El dashboard utiliza JavaScript vanilla (sin frameworks)
- Los gráficos se generan con SVG
- Compatible con navegadores modernos (Chrome, Firefox, Safari, Edge)
- Requiere JavaScript habilitado

## 🐛 Solución de Problemas

### El dashboard no carga los datos
- Verifica que estás usando un servidor local (no `file://`)
- Asegúrate de que los archivos CSV estén en la carpeta `data/`
- Revisa la consola del navegador para errores

### Los datos no se actualizan
- Limpia el caché del navegador (Ctrl+Shift+Delete)
- Recarga con Ctrl+F5

## 📄 Licencia

Copyright © Agrícola La Higuera S.A.

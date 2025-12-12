# Optimización SEO para GoFast

## 📋 Resumen

Se ha creado un sistema completo de optimización SEO para el sitio web de GoFast, compatible con **Cloudflare** y **Site Kit de Google**, siguiendo las mejores prácticas de la [Guía Oficial de SEO de Google](https://developers.google.com/search/docs/fundamentals/seo-starter-guide?hl=es).

## 🚀 Archivo Creado

**`code/gofast_seo.php`** - Sistema completo de meta tags y optimizaciones SEO

## ✨ Características Implementadas

### 1. Meta Tags Básicos
- ✅ **Title tags** personalizados por página
- ✅ **Meta description** optimizada para cada sección
- ✅ **Meta keywords** relevantes
- ✅ **Meta author** y **language**
- ✅ **Geo tags** (ubicación: Tulúa, Valle del Cauca)
- ✅ **Meta robots** (index, follow)

### 2. Open Graph (Redes Sociales)
- ✅ Tags completos para **Facebook** y **LinkedIn**
- ✅ Imágenes optimizadas (1200x630px)
- ✅ Títulos y descripciones personalizadas
- ✅ Locale configurado (es_CO)

### 3. Twitter Cards
- ✅ **Summary Large Image** card
- ✅ Integración con cuenta @gofastulua
- ✅ Imágenes y descripciones optimizadas

### 4. Schema.org Structured Data (JSON-LD)
- ✅ **LocalBusiness** schema completo
- ✅ **WebSite** schema con búsqueda
- ✅ **Organization** schema
- ✅ Información de servicios (mensajería urbana, intermunicipal, etc.)
- ✅ Horarios de atención
- ✅ Redes sociales vinculadas

### 5. Optimizaciones Cloudflare
- ✅ **DNS prefetch** para recursos externos
- ✅ **Preconnect** para Google Fonts y Analytics
- ✅ **Preload** para recursos críticos

### 6. Canonical URLs
- ✅ URLs canónicas para evitar contenido duplicado
- ✅ Compatible con Site Kit de Google

### 7. Hreflang Tags
- ✅ Soporte para español de Colombia (es-co)
- ✅ Español general (es)
- ✅ Idioma por defecto (x-default)

### 8. Sitemap.xml (Según Guía de Google)
- ✅ Generación dinámica de sitemap.xml
- ✅ Prioridades y frecuencias de actualización configuradas
- ✅ Incluye todas las páginas principales
- ✅ Accesible en: `/?gofast_sitemap=xml`

### 9. robots.txt Optimizado
- ✅ Configuración según mejores prácticas de Google
- ✅ Referencia al sitemap incluida
- ✅ Protección de áreas administrativas
- ✅ Filtrado de directivas no estándar (elimina errores de validación)
- ✅ Solo incluye directivas válidas según especificación estándar

### 10. Breadcrumbs Schema (JSON-LD)
- ✅ Navegación estructurada para Google
- ✅ Mejora la comprensión del sitio por los buscadores
- ✅ Generado automáticamente para páginas internas

## 📄 Páginas con SEO Personalizado

El sistema detecta automáticamente las siguientes páginas y personaliza el SEO:

1. **Home** - "GoFast - Mensajería Express en Tulúa"
2. **Cotizar** - "Cotizar Envío - GoFast Mensajería Express"
3. **Cotizar Intermunicipal** - "Envíos Intermunicipales - GoFast"
4. **Sobre Nosotros** - "Sobre Nosotros - GoFast Mensajería Express"
5. **Trabaja con Nosotros** - "Trabaja con Nosotros - Únete al Equipo GoFast"
6. **App Móvil** - "App Móvil GoFast - Descarga Nuestra Aplicación"

## 🔧 Instalación

### Opción 1: Usando Code Snippets (Recomendado)

1. Abre WordPress Admin → **Snippets** → **Add New**
2. Nombre: `GoFast SEO Optimization`
3. Copia el contenido de `code/gofast_seo.php`
4. Activa el snippet
5. Guarda

### Opción 2: Agregar a functions.php

1. Abre el archivo `functions.php` de tu tema
2. Copia y pega el contenido de `code/gofast_seo.php` al final
3. Guarda el archivo

## ✅ Compatibilidad

### Site Kit de Google
- ✅ **Totalmente compatible**
- ✅ No interfiere con las funciones de Site Kit
- ✅ Los meta tags son complementarios
- ✅ Site Kit puede seguir gestionando Analytics y Search Console

### Cloudflare
- ✅ **Optimizado para Cloudflare**
- ✅ DNS prefetch y preconnect configurados
- ✅ Compatible con CDN de Cloudflare
- ✅ Mejora el rendimiento de carga

### Plugins SEO Existentes
- ✅ Compatible con **Yoast SEO** (si está activo, respeta sus configuraciones)
- ✅ Compatible con **Rank Math**
- ✅ Compatible con **All in One SEO**

## 📊 Beneficios SEO

1. **Mejor indexación** en Google y otros buscadores
2. **Mejor apariencia** en resultados de búsqueda (rich snippets)
3. **Mejor compartido** en redes sociales (Open Graph)
4. **Datos estructurados** para Google Knowledge Graph
5. **Mejor rendimiento** con Cloudflare (preconnect, prefetch)
6. **SEO local** optimizado (Tulúa, Valle del Cauca)
7. **Sitemap.xml** para facilitar el rastreo de Google
8. **Breadcrumbs** para mejor navegación y comprensión del sitio
9. **robots.txt** optimizado según guía oficial de Google

## 🔗 URLs Importantes

- **Sitemap XML**: `https://tudominio.com/?gofast_sitemap=xml`
- **robots.txt**: `https://tudominio.com/robots.txt` (generado automáticamente)

## 🔍 Verificación

### Herramientas para Verificar SEO

1. **Google Search Console** ⭐ (Recomendado por Google)
   - URL: https://search.google.com/search-console
   - Verifica que el sitio esté indexado
   - Revisa errores de rastreo
   - Monitorea el rendimiento
   - **Importante**: Envía tu sitemap: `/?gofast_sitemap=xml`

2. **Google Rich Results Test**
   - URL: https://search.google.com/test/rich-results
   - Verifica que los Schema.org estén correctos
   - Valida datos estructurados (LocalBusiness, Breadcrumbs, etc.)

3. **Facebook Sharing Debugger**
   - URL: https://developers.facebook.com/tools/debug/
   - Verifica que los Open Graph tags funcionen
   - Limpia caché de Facebook si es necesario

4. **Twitter Card Validator**
   - URL: https://cards-dev.twitter.com/validator
   - Verifica que las Twitter Cards funcionen

5. **Google PageSpeed Insights**
   - URL: https://pagespeed.web.dev/
   - Verifica el rendimiento del sitio
   - Core Web Vitals

6. **Validar Sitemap**
   - URL: https://www.xml-sitemaps.com/validate-xml-sitemap.html
   - Valida que tu sitemap.xml esté correcto
   - URL del sitemap: `https://tudominio.com/?gofast_sitemap=xml`

7. **Google Mobile-Friendly Test**
   - URL: https://search.google.com/test/mobile-friendly
   - Verifica que el sitio sea móvil-friendly

## 📝 Personalización

### Actualizar Información de Contacto

En el archivo `gofast_seo.php`, busca y actualiza:

```php
"telephone": "+573194642513",  // Teléfono de WhatsApp: +57 319 4642513
```

### Configurar Facebook App ID

Para que Facebook Sharing Debugger no muestre advertencias, necesitas agregar tu `fb:app_id`:

1. **Crear una App de Facebook** (si no tienes una):
   - Ve a https://developers.facebook.com/apps/
   - Haz clic en "Crear app"
   - Selecciona "Negocio" o "Otro"
   - Completa el formulario

2. **Obtener el App ID**:
   - Una vez creada la app, verás el "App ID" en el dashboard
   - Copia ese número

3. **Agregar al código**:
   En `gofast_seo.php`, busca la línea:
   ```php
   $fb_app_id = ''; // Reemplazar con tu Facebook App ID si tienes una
   ```
   Y reemplázala con:
   ```php
   $fb_app_id = 'TU_APP_ID_AQUI'; // Tu Facebook App ID
   ```

**Nota:** Si no tienes una app de Facebook, puedes omitir este paso. El sitio funcionará igual, pero Facebook Sharing Debugger mostrará una advertencia (no es crítica).

### Actualizar Imagen por Defecto

```php
$default_image = 'https://gofastdomicilios.com/wp-content/uploads/2025/11/GoFast.png';
```

Asegúrate de que la imagen:
- Sea de al menos **1200x630px** para Open Graph
- Esté optimizada (formato WebP o JPG comprimido)
- Tenga buen contraste y sea legible

### Agregar Más Páginas Personalizadas

En la función `gofast_add_seo_meta_tags()`, agrega más casos en el `switch`:

```php
case 'nueva-pagina':
    $page_title = 'Título de la Nueva Página';
    $page_description = 'Descripción optimizada para SEO';
    break;
```

## 🎯 Próximos Pasos Recomendados

### Según la Guía Oficial de Google:
https://developers.google.com/search/docs/fundamentals/seo-starter-guide

1. **Configurar Google Search Console** ⭐ (PRIORITARIO)
   - Verificar propiedad del sitio
   - Enviar sitemap: `/?gofast_sitemap=xml`
   - Solicitar indexación de páginas principales
   - Monitorear errores de rastreo

2. **Optimizar Contenido** (Según Google)
   - Crear contenido útil, confiable y centrado en personas
   - Escribir títulos descriptivos y únicos
   - Usar encabezados (H1, H2, H3) correctamente
   - Agregar texto alternativo descriptivo a imágenes

3. **Optimizar Imágenes**
   - Convertir a WebP (formato moderno)
   - Comprimir imágenes sin perder calidad
   - Agregar alt text descriptivo (ya implementado)
   - Usar lazy loading (ya implementado)

4. **Mejorar Estructura de URLs**
   - URLs descriptivas y cortas (ya implementado)
   - Evitar parámetros innecesarios
   - Usar HTTPS (importante para SEO)

5. **Crear Contenido de Calidad**
   - Blog con artículos sobre mensajería
   - Preguntas frecuentes (FAQ) con Schema.org
   - Guías y tutoriales
   - Contenido local sobre Tulúa y servicios

6. **Construir Enlaces Internos**
   - Enlazar páginas relacionadas
   - Usar texto de anclaje descriptivo
   - Crear estructura lógica de navegación

7. **Monitorear Resultados**
   - Usar Site Kit de Google para métricas
   - Revisar posicionamiento en Google
   - Analizar tráfico orgánico
   - Revisar Core Web Vitals

## 🔧 Solución de Problemas

### Error en robots.txt: "Unknown directive"

Si ves un error como "Content-signal: search=yes,ai-train=no - Unknown directive":

**Causa**: WordPress o algún plugin está agregando directivas no estándar al robots.txt.

**Solución implementada**: El código ahora:
- Sobrescribe completamente el robots.txt con solo directivas válidas
- Filtra y elimina cualquier directiva no estándar automáticamente
- Asegura que solo se usen directivas según el estándar oficial

**Verificación**:
1. Visita: `https://tudominio.com/robots.txt`
2. Verifica que solo contenga directivas estándar (User-agent, Allow, Disallow, Sitemap)
3. Usa el validador de Google Search Console para confirmar que está correcto

### Si el problema persiste:

**Solución Recomendada**: Crear archivo robots.txt físico

Si WordPress o algún plugin sigue agregando directivas no estándar después de aplicar el filtro, la mejor solución es crear un archivo `robots.txt` físico en la raíz de tu WordPress. Este archivo tiene prioridad sobre la generación dinámica.

**Pasos:**

1. **Accede a tu servidor** (vía FTP, cPanel File Manager, o SSH)

2. **Navega a la raíz de WordPress** (donde está `wp-config.php`)

3. **Crea un archivo llamado `robots.txt`** (sin extensión adicional)

4. **Copia este contenido exacto:**
```
User-agent: *
Allow: /
Disallow: /wp-admin/
Disallow: /wp-includes/
Disallow: /?gofast_logout=1
Disallow: /auth/

Sitemap: https://gofastdomicilios.com/?gofast_sitemap=xml
```

5. **Reemplaza** `https://gofastdomicilios.com` con tu dominio real

6. **Guarda el archivo** y verifica en: `https://tudominio.com/robots.txt`

7. **Valida en Google Search Console** - debería mostrar "Válido" sin errores

**Nota**: Un archivo `robots.txt` físico siempre tiene prioridad sobre la generación dinámica de WordPress, por lo que esta es la solución más confiable.

**Alternativa**: Desactivar generación automática
- Si usas un plugin SEO (Yoast, Rank Math), desactiva su generación de robots.txt
- Ve a la configuración del plugin y busca la opción de robots.txt
- Desactívala para que use el archivo físico o nuestro código

## 📞 Soporte

Si tienes problemas con la implementación:

1. Verifica que el archivo esté activo en Code Snippets
2. Revisa que no haya conflictos con otros plugins SEO
3. Usa las herramientas de verificación mencionadas arriba
4. Revisa la consola del navegador para errores
5. Verifica robots.txt en: `https://tudominio.com/robots.txt`

---

**Versión:** 1.0  
**Fecha:** 2025  
**Compatible con:** WordPress, Cloudflare, Site Kit de Google


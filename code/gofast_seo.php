<?php
/***************************************************
 * GOFAST – OPTIMIZACIÓN SEO COMPLETA
 * Compatible con Cloudflare y Site Kit de Google
 * 
 * Incluye:
 * - Meta tags básicos (title, description, keywords)
 * - Open Graph (Facebook, LinkedIn)
 * - Twitter Cards
 * - Schema.org structured data (JSON-LD)
 * - Canonical URLs
 * - Meta robots
 * - Optimizaciones Cloudflare
 ***************************************************/

/**
 * Agregar meta tags SEO al head de WordPress
 */
function gofast_add_seo_meta_tags() {
    global $wp;
    
    // Obtener información del sitio
    $site_name = get_bloginfo('name');
    $site_description = get_bloginfo('description');
    $site_url = home_url('/');
    $current_url = home_url(add_query_arg(array(), $wp->request));
    
    // Configuración SEO por defecto
    $default_title = 'GoFast - Mensajería Express en Tulúa | Envíos Rápidos y Seguros';
    $default_description = 'GoFast es tu servicio de mensajería express en Tulúa y alrededores. Envíos rápidos, seguros y confiables. Cotiza tu domicilio en segundos y confirma por WhatsApp.';
    $default_keywords = 'mensajería express, domicilios tulúa, envíos rápidos, mensajero tulúa, delivery tulúa, envíos intermunicipales, gofast, mensajería valle del cauca';
    
    // Imagen por defecto (logo de GoFast)
    $default_image = 'https://gofastdomicilios.com/wp-content/uploads/2025/11/GoFast.png';
    
    // Detectar página actual y personalizar SEO
    $page_title = $default_title;
    $page_description = $default_description;
    $page_keywords = $default_keywords;
    $page_image = $default_image;
    $page_type = 'website';
    
    // Detectar tipo de página
    if (is_front_page() || is_home()) {
        $page_title = 'GoFast - Mensajería Express en Tulúa | Envíos Rápidos y Seguros';
        $page_description = 'GoFast es tu servicio de mensajería express en Tulúa y alrededores. Envíos rápidos, seguros y confiables. Cotiza tu domicilio en segundos y confirma por WhatsApp.';
        $page_type = 'website';
    } elseif (is_page()) {
        $page_obj = get_queried_object();
        $page_title = $page_obj->post_title . ' | ' . $site_name;
        
        // Personalizar según slug de página
        $page_slug = $page_obj->post_name;
        
        switch ($page_slug) {
            case 'cotizar':
                $page_title = 'Cotizar Envío - GoFast Mensajería Express';
                $page_description = 'Cotiza tu envío en segundos. Ingresa origen y destino, obtén el precio al instante. Servicio de mensajería express en Tulúa.';
                $page_keywords = 'cotizar envío, calcular costo envío, tarifas mensajería tulúa, precio domicilio';
                break;
                
            case 'cotizar-intermunicipal':
                $page_title = 'Envíos Intermunicipales - GoFast | Desde Tulúa a Otros Municipios';
                $page_description = 'Envía paquetes desde Tulúa a otros municipios cercanos. Envíos intermunicipales rápidos y seguros. Pago anticipado requerido.';
                $page_keywords = 'envíos intermunicipales, mensajería intermunicipal, envíos desde tulúa, delivery intermunicipal';
                break;
                
            case 'sobre-nosotros':
                $page_title = 'Sobre Nosotros - GoFast Mensajería Express';
                $page_description = 'Conoce más sobre GoFast, tu servicio de mensajería express en Tulúa. Nuestro equipo, políticas y compromiso con la calidad.';
                $page_keywords = 'sobre gofast, quiénes somos, mensajería tulúa, empresa mensajería';
                break;
                
            case 'trabaja-con-nosotros':
                $page_title = 'Trabaja con Nosotros - Únete al Equipo GoFast';
                $page_description = '¿Tienes moto y papeles al día? Únete a nuestra red de mensajeros GoFast y genera ingresos adicionales haciendo domicilios en Tulúa.';
                $page_keywords = 'trabajar como mensajero, empleo mensajero tulúa, ser mensajero, trabajo mensajería';
                break;
                
            case 'app-movil':
                $page_title = 'App Móvil GoFast - Descarga Nuestra Aplicación';
                $page_description = 'Descarga la app móvil de GoFast y gestiona tus envíos desde tu celular. Disponible para Android e iOS.';
                $page_keywords = 'app gofast, aplicación mensajería, app delivery tulúa, descargar app';
                break;
        }
    }
    
    // Asegurar que el título y descripción no estén vacíos
    if (empty($page_title)) {
        $page_title = $default_title;
    }
    if (empty($page_description)) {
        $page_description = $default_description;
    }
    
    // Limpiar y escapar contenido
    $page_title = esc_attr($page_title);
    $page_description = esc_attr($page_description);
    $page_keywords = esc_attr($page_keywords);
    $current_url = esc_url($current_url);
    $page_image = esc_url($page_image);
    
    // ============================================
    // META TAGS BÁSICOS
    // ============================================
    echo "\n<!-- GoFast SEO Meta Tags -->\n";
    
    // Title (si no está ya definido por otro plugin)
    if (!defined('WPSEO_VERSION') && !function_exists('yoast_breadcrumb')) {
        echo '<title>' . $page_title . '</title>' . "\n";
    }
    
    // Meta Description
    echo '<meta name="description" content="' . $page_description . '">' . "\n";
    
    // Meta Keywords (aunque ya no es tan importante, algunos buscadores lo usan)
    echo '<meta name="keywords" content="' . $page_keywords . '">' . "\n";
    
    // Author
    echo '<meta name="author" content="GoFast Mensajería Express">' . "\n";
    
    // Language
    echo '<meta http-equiv="content-language" content="es-CO">' . "\n";
    
    // Geo tags (Tulúa, Valle del Cauca, Colombia)
    echo '<meta name="geo.region" content="CO-VAC">' . "\n";
    echo '<meta name="geo.placename" content="Tulúa">' . "\n";
    echo '<meta name="geo.position" content="4.0847;-76.1953">' . "\n";
    echo '<meta name="ICBM" content="4.0847, -76.1953">' . "\n";
    
    // Robots
    echo '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
    echo '<meta name="googlebot" content="index, follow">' . "\n";
    
    // Canonical URL
    echo '<link rel="canonical" href="' . $current_url . '">' . "\n";
    
    // ============================================
    // OPEN GRAPH (Facebook, LinkedIn, etc.)
    // ============================================
    echo "\n<!-- Open Graph Meta Tags -->\n";
    echo '<meta property="og:type" content="' . $page_type . '">' . "\n";
    echo '<meta property="og:title" content="' . $page_title . '">' . "\n";
    echo '<meta property="og:description" content="' . $page_description . '">' . "\n";
    echo '<meta property="og:url" content="' . $current_url . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
    echo '<meta property="og:image" content="' . $page_image . '">' . "\n";
    echo '<meta property="og:image:width" content="1200">' . "\n";
    echo '<meta property="og:image:height" content="630">' . "\n";
    echo '<meta property="og:image:alt" content="' . esc_attr($site_name) . ' - Mensajería Express">' . "\n";
    echo '<meta property="og:locale" content="es_CO">' . "\n";
    echo '<meta property="og:locale:alternate" content="es_ES">' . "\n";
    
    // Facebook App ID (obtener desde https://developers.facebook.com/apps/)
    // Si no tienes una app de Facebook, puedes omitir esta línea o crear una app
    // Para crear una app: https://developers.facebook.com/apps/create/
    $fb_app_id = ''; // Reemplazar con tu Facebook App ID si tienes una
    if (!empty($fb_app_id)) {
        echo '<meta property="fb:app_id" content="' . esc_attr($fb_app_id) . '">' . "\n";
    }
    
    // Facebook Admins (ID de usuario de Facebook que administra la página)
    // Opcional: agregar IDs de administradores separados por comas
    // $fb_admins = '123456789,987654321'; // Reemplazar con IDs reales si es necesario
    // if (!empty($fb_admins)) {
    //     echo '<meta property="fb:admins" content="' . esc_attr($fb_admins) . '">' . "\n";
    // }
    
    // ============================================
    // TWITTER CARD
    // ============================================
    echo "\n<!-- Twitter Card Meta Tags -->\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . $page_title . '">' . "\n";
    echo '<meta name="twitter:description" content="' . $page_description . '">' . "\n";
    echo '<meta name="twitter:image" content="' . $page_image . '">' . "\n";
    echo '<meta name="twitter:image:alt" content="' . esc_attr($site_name) . ' - Mensajería Express">' . "\n";
    echo '<meta name="twitter:site" content="@gofastulua">' . "\n";
    echo '<meta name="twitter:creator" content="@gofastulua">' . "\n";
    
    // ============================================
    // OPTIMIZACIONES CLOUDFLARE
    // ============================================
    echo "\n<!-- Cloudflare Optimizations -->\n";
    echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
    echo '<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
    echo '<link rel="preconnect" href="https://www.google-analytics.com" crossorigin>' . "\n";
    
    // ============================================
    // SCHEMA.ORG STRUCTURED DATA (JSON-LD)
    // ============================================
    echo "\n<!-- Schema.org Structured Data -->\n";
    ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "GoFast Mensajería Express",
        "image": "<?php echo esc_url($default_image); ?>",
        "@id": "<?php echo esc_url($site_url); ?>",
        "url": "<?php echo esc_url($site_url); ?>",
        "telephone": "+573194642513",
        "priceRange": "$$",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Tulúa",
            "addressLocality": "Tulúa",
            "addressRegion": "Valle del Cauca",
            "postalCode": "763000",
            "addressCountry": "CO"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": 4.0847,
            "longitude": -76.1953
        },
        "openingHoursSpecification": {
            "@type": "OpeningHoursSpecification",
            "dayOfWeek": [
                "Monday",
                "Tuesday",
                "Wednesday",
                "Thursday",
                "Friday",
                "Saturday",
                "Sunday"
            ],
            "opens": "07:00",
            "closes": "20:00"
        },
        "sameAs": [
            "https://www.facebook.com/share/1BPYXAQJ7Y/?mibextid=wwXIfr",
            "https://www.instagram.com/gofastulua?igsh=ZXhoMHE0bTQ3azgy",
            "https://www.tiktok.com/@gofastulua?_r=1&_t=ZS-91GhG5Z2Xpf"
        ],
        "description": "<?php echo esc_js($default_description); ?>",
        "serviceArea": {
            "@type": "City",
            "name": "Tulúa"
        },
        "areaServed": {
            "@type": "City",
            "name": "Tulúa"
        },
        "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "Servicios de Mensajería",
            "itemListElement": [
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Mensajería Urbana",
                        "description": "Envíos punto a punto dentro de la ciudad"
                    }
                },
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Envíos Intermunicipales",
                        "description": "Envíos desde Tulúa a otros municipios"
                    }
                },
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Domicilios para Negocios",
                        "description": "Servicio de domicilios para restaurantes y comercios"
                    }
                },
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Pagos Contraentrega",
                        "description": "Recolección de pagos al momento de la entrega"
                    }
                },
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Compras",
                        "description": "Realizamos compras de productos en tiendas y supermercados"
                    }
                }
            ]
        }
    }
    </script>
    <?php
    
    // Schema.org para WebSite
    ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "<?php echo esc_js($site_name); ?>",
        "url": "<?php echo esc_url($site_url); ?>",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "<?php echo esc_url($site_url); ?>?s={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    <?php
    
    // Schema.org para Organization
    ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "<?php echo esc_js($site_name); ?>",
        "url": "<?php echo esc_url($site_url); ?>",
        "logo": "<?php echo esc_url($default_image); ?>",
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "+573194642513",
            "contactType": "customer service",
            "areaServed": "CO",
            "availableLanguage": ["Spanish"]
        },
        "sameAs": [
            "https://www.facebook.com/share/1BPYXAQJ7Y/?mibextid=wwXIfr",
            "https://www.instagram.com/gofastulua?igsh=ZXhoMHE0bTQ3azgy",
            "https://www.tiktok.com/@gofastulua?_r=1&_t=ZS-91GhG5Z2Xpf"
        ]
    }
    </script>
    <?php
    
    // Breadcrumb Schema (mejora la navegación según Google)
    if (!is_front_page() && is_page()) {
        $page_obj = get_queried_object();
        $breadcrumbs = array(
            array('name' => 'Inicio', 'url' => home_url('/'))
        );
        
        // Agregar página actual
        if (!empty($page_obj->post_title)) {
            $breadcrumbs[] = array(
                'name' => $page_obj->post_title,
                'url' => get_permalink($page_obj->ID)
            );
        }
        
        if (count($breadcrumbs) > 1) {
            ?>
            <script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "BreadcrumbList",
                "itemListElement": [
                    <?php
                    $position = 1;
                    foreach ($breadcrumbs as $crumb) {
                        if ($position > 1) echo ",\n";
                        ?>
                    {
                        "@type": "ListItem",
                        "position": <?php echo $position; ?>,
                        "name": "<?php echo esc_js($crumb['name']); ?>",
                        "item": "<?php echo esc_url($crumb['url']); ?>"
                    }<?php
                        $position++;
                    }
                    ?>
                ]
            }
            </script>
            <?php
        }
    }
    
    echo "\n<!-- End GoFast SEO Meta Tags -->\n";
}
add_action('wp_head', 'gofast_add_seo_meta_tags', 1);

/**
 * Agregar meta viewport si no existe (importante para móviles)
 */
function gofast_add_viewport_meta() {
    if (!has_action('wp_head', 'wp_head')) {
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">' . "\n";
    }
}
add_action('wp_head', 'gofast_add_viewport_meta', 0);

/**
 * Agregar hreflang para SEO internacional (si es necesario en el futuro)
 */
function gofast_add_hreflang() {
    $site_url = home_url('/');
    echo '<link rel="alternate" hreflang="es-co" href="' . esc_url($site_url) . '">' . "\n";
    echo '<link rel="alternate" hreflang="es" href="' . esc_url($site_url) . '">' . "\n";
    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($site_url) . '">' . "\n";
}
add_action('wp_head', 'gofast_add_hreflang', 2);

/**
 * Optimizar imágenes para SEO (lazy loading y alt text)
 * Esta función se puede expandir para agregar más optimizaciones
 */
function gofast_optimize_images_seo($attr, $attachment, $size) {
    // Asegurar que las imágenes tengan alt text
    if (empty($attr['alt']) && !empty($attachment->post_title)) {
        $attr['alt'] = esc_attr($attachment->post_title);
    }
    
    // Agregar loading lazy por defecto (WordPress 5.5+ ya lo hace, pero por si acaso)
    if (!isset($attr['loading'])) {
        $attr['loading'] = 'lazy';
    }
    
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'gofast_optimize_images_seo', 10, 3);

/**
 * Generar sitemap.xml dinámico según las mejores prácticas de Google
 * https://developers.google.com/search/docs/fundamentals/seo-starter-guide
 * 
 * Nota: Site Kit de Google puede generar su propio sitemap, pero esta función
 * asegura que las páginas principales estén incluidas
 */
function gofast_generate_sitemap() {
    // Solo generar si se solicita explícitamente y no hay otro sitemap activo
    if (isset($_GET['gofast_sitemap']) && $_GET['gofast_sitemap'] === 'xml') {
        // Lista de páginas importantes del sitio
        $important_pages = array(
            array('url' => home_url('/'), 'priority' => '1.0', 'changefreq' => 'daily'),
            array('url' => home_url('/cotizar'), 'priority' => '0.9', 'changefreq' => 'weekly'),
            array('url' => home_url('/cotizar-intermunicipal'), 'priority' => '0.8', 'changefreq' => 'weekly'),
            array('url' => home_url('/sobre-nosotros'), 'priority' => '0.7', 'changefreq' => 'monthly'),
            array('url' => home_url('/trabaja-con-nosotros'), 'priority' => '0.7', 'changefreq' => 'monthly'),
            array('url' => home_url('/app-movil'), 'priority' => '0.6', 'changefreq' => 'monthly'),
        );
        
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($important_pages as $page) {
            echo '  <url>' . "\n";
            echo '    <loc>' . esc_url($page['url']) . '</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            echo '    <changefreq>' . $page['changefreq'] . '</changefreq>' . "\n";
            echo '    <priority>' . $page['priority'] . '</priority>' . "\n";
            echo '  </url>' . "\n";
        }
        
        echo '</urlset>';
        exit;
    }
}
add_action('init', 'gofast_generate_sitemap');

/**
 * Generar robots.txt válido según estándar
 * Según: https://developers.google.com/search/docs/fundamentals/seo-starter-guide
 * 
 * Esta función sobrescribe cualquier robots.txt generado por WordPress o plugins
 * para asegurar que solo contenga directivas estándar válidas
 */
function gofast_output_robots_txt() {
    // Eliminar TODAS las acciones de robots.txt para evitar conflictos
    global $wp_filter;
    if (isset($wp_filter['do_robots'])) {
        unset($wp_filter['do_robots']);
    }
    
    // Generar robots.txt válido con solo directivas estándar
    header('Content-Type: text/plain; charset=utf-8');
    
    // Directivas estándar según especificación robots.txt
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo "Disallow: /wp-admin/\n";
    echo "Disallow: /wp-includes/\n";
    echo "Disallow: /?gofast_logout=1\n";
    echo "Disallow: /auth/\n";
    echo "\n";
    
    // Sitemap (directiva estándar)
    echo "Sitemap: " . esc_url(home_url('/?gofast_sitemap=xml')) . "\n";
    
    exit;
}

/**
 * Registrar la función de robots.txt con máxima prioridad
 * para sobrescribir cualquier otra implementación
 */
function gofast_add_sitemap_to_robots() {
    // Eliminar todas las acciones previas de do_robots
    remove_all_actions('do_robots');
    
    // Registrar nuestra función con prioridad 1 (más alta)
    add_action('do_robots', 'gofast_output_robots_txt', 1);
}
add_action('init', 'gofast_add_sitemap_to_robots', 999);

/**
 * Filtrar y limpiar robots.txt para eliminar directivas no estándar
 * Esto asegura que solo se incluyan directivas válidas según el estándar
 * 
 * Elimina específicamente "Content-signal:" y otras directivas no estándar
 */
function gofast_clean_robots_txt($output) {
    if (empty($output)) {
        return $output;
    }
    
    // Lista de directivas NO estándar que deben eliminarse
    $invalid_directives = array(
        'Content-signal:',
        'content-signal:',
        'CONTENT-SIGNAL:'
    );
    
    // Lista de directivas estándar válidas según robots.txt spec
    $valid_directives = array(
        'User-agent:',
        'Allow:',
        'Disallow:',
        'Crawl-delay:',
        'Sitemap:',
        'Host:'
    );
    
    // Dividir en líneas
    $lines = explode("\n", $output);
    $clean_lines = array();
    
    foreach ($lines as $line) {
        $original_line = $line;
        $line = trim($line);
        
        // Saltar líneas vacías
        if (empty($line)) {
            $clean_lines[] = '';
            continue;
        }
        
        // ELIMINAR específicamente directivas no estándar conocidas
        $should_remove = false;
        foreach ($invalid_directives as $invalid) {
            if (stripos($line, $invalid) === 0) {
                $should_remove = true;
                break;
            }
        }
        
        if ($should_remove) {
            // Ignorar esta línea completamente
            continue;
        }
        
        // Verificar si la línea comienza con una directiva válida
        $is_valid = false;
        foreach ($valid_directives as $directive) {
            if (stripos($line, $directive) === 0) {
                $is_valid = true;
                break;
            }
        }
        
        // También permitir comentarios (líneas que empiezan con #)
        if (!$is_valid && strpos($line, '#') === 0) {
            $is_valid = true;
        }
        
        // Solo agregar líneas válidas
        if ($is_valid) {
            $clean_lines[] = $original_line; // Mantener formato original
        }
        // Eliminar cualquier otra línea con directivas no estándar
    }
    
    return implode("\n", $clean_lines);
}

// Aplicar filtro con máxima prioridad para ejecutarse al final
// Esto asegura que se ejecute después de que WordPress/plugins agreguen sus directivas
add_filter('robots_txt', 'gofast_clean_robots_txt', 99999);

/**
 * Compatibilidad con Site Kit de Google
 * Asegurar que nuestros meta tags no interfieran con Site Kit
 */
function gofast_sitekit_compatibility() {
    // Site Kit maneja Analytics y Search Console
    // Nuestros meta tags son complementarios y no deberían interferir
    // Si Site Kit está activo, respetamos sus configuraciones
    if (defined('GOOGLESITEKIT_VERSION')) {
        // Site Kit está activo, nuestros tags son complementarios
        // No hacemos nada especial, solo coexistimos
    }
}
add_action('wp_head', 'gofast_sitekit_compatibility', 999);

/**
 * Agregar preload para recursos críticos (mejora rendimiento con Cloudflare)
 * Según mejores prácticas de Google: https://developers.google.com/search/docs/fundamentals/seo-starter-guide
 */
function gofast_preload_resources() {
    // Preload del logo (si es crítico)
    $logo_url = 'https://gofastdomicilios.com/wp-content/uploads/2025/11/GoFast.png';
    echo '<link rel="preload" as="image" href="' . esc_url($logo_url) . '">' . "\n";
}
add_action('wp_head', 'gofast_preload_resources', 3);

/**
 * Agregar link rel="alternate" para sitemap (mejora descubrimiento según Google)
 * https://developers.google.com/search/docs/fundamentals/seo-starter-guide
 */
function gofast_add_sitemap_link() {
    echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="' . esc_url(home_url('/?gofast_sitemap=xml')) . '">' . "\n";
}
add_action('wp_head', 'gofast_add_sitemap_link', 4);

/**
 * Mejorar estructura de enlaces internos (importante para SEO según Google)
 * Agregar atributos rel="nofollow" a enlaces externos y mejorar enlaces internos
 */
function gofast_improve_internal_links($content) {
    if (is_admin() || !is_singular()) {
        return $content;
    }
    
    // Agregar rel="nofollow" a enlaces externos (opcional, puede desactivarse)
    // Esta función se puede expandir según necesidades específicas
    
    return $content;
}
// Desactivado por defecto - descomentar si se necesita
// add_filter('the_content', 'gofast_improve_internal_links', 99);


# Documentación de Códigos - Shortcodes y Slugs

Este documento lista todos los archivos PHP con sus shortcodes, funciones asociadas y slugs sugeridos, extraído directamente del código fuente.

## Autenticación y Usuarios

### `gofast_auth.php`
- **Archivo:** `code/gofast_auth.php`
- **Función:** `gofast_auth_shortcode()`
- **Shortcode:** `[gofast_auth]`
- **Registro:** Línea 321: `add_shortcode('gofast_auth', 'gofast_auth_shortcode');`
- **Slug sugerido:** `/auth`
- **Descripción:** Formulario de login y registro de usuarios
- **Funcionalidad:** Permite iniciar sesión o crear cuenta nueva
- **Características:**
  - Modo login/registro según parámetro `?registro=1`
  - Validación de sesión existente
  - Soporte para cookies persistentes
  - Honeypot anti-bot

### `gofast_auth_logic.php`
- **Archivo:** `code/gofast_auth_logic.php`
- **Función:** `gofast_handle_auth_requests()`
- **Shortcode:** No tiene (se ejecuta automáticamente)
- **Registro:** Línea 381: `add_action('init', 'gofast_handle_auth_requests', 5);`
- **Slug sugerido:** N/A
- **Descripción:** Lógica de autenticación (login, registro, logout)
- **Funcionalidad:** Procesa formularios de autenticación y maneja sesiones
- **Características:**
  - Procesa login, registro y logout
  - Rate limiting (5 intentos en 10 minutos)
  - Creación de cookies persistentes (30 días)
  - Validación honeypot

### `gofast_recuperar_password.php`
- **Archivo:** `code/gofast_recuperar_password.php`
- **Función:** `gofast_recuperar_password_shortcode()`
- **Shortcode:** `[gofast_recuperar_password]`
- **Registro:** Línea 490: `add_shortcode('gofast_recuperar_password', 'gofast_recuperar_password_shortcode');`
- **Slug sugerido:** `/recuperar-password`
- **Descripción:** Recuperación de contraseña mediante email
- **Funcionalidad:** Permite solicitar y restablecer contraseña olvidada
- **Características:**
  - Genera tokens únicos (64 caracteres)
  - Expiración de 1 hora
  - Envío de email HTML
  - Enmascaramiento de email por seguridad

## Cotización - Cliente

### `gofast_cotizar.php`
- **Archivo:** `code/gofast_cotizar.php`
- **Función:** `gofast_cotizar_shortcode()`
- **Shortcode:** `[gofast_cotizar]`
- **Registro:** Línea 259: `add_shortcode('gofast_cotizar', 'gofast_cotizar_shortcode');`
- **Slug sugerido:** `/cotizar`
- **Descripción:** Cotizador de servicios para clientes
- **Funcionalidad:** Permite cotizar envíos locales con múltiples destinos
- **Características:**
  - Selección de origen (negocios propios o manual)
  - Múltiples destinos
  - Cálculo automático de tarifas
  - Aplicación de recargos

### `gofast_solicitar_mensajero.php`
- **Archivo:** `code/gofast_solicitar_mensajero.php`
- **Función:** `gofast_resultado_cotizacion()`
- **Shortcode:** `[gofast_resultado]`
- **Registro:** Línea 813: `add_shortcode("gofast_resultado", "gofast_resultado_cotizacion");`
- **Slug sugerido:** `/solicitar-mensajero`
- **Descripción:** Formulario para solicitar mensajero después de cotizar
- **Funcionalidad:** Permite crear un servicio después de la cotización
- **Características:**
  - Formulario de datos del cliente
  - Validación de cotización previa
  - Creación de servicio en BD

## Cotización - Mensajero

### `gofast_mensajero_cotizar.php`
- **Archivo:** `code/gofast_mensajero_cotizar.php`
- **Función:** `gofast_mensajero_cotizar_shortcode()`
- **Shortcode:** `[gofast_mensajero_cotizar]`
- **Registro:** Línea 1494: `add_shortcode('gofast_mensajero_cotizar', 'gofast_mensajero_cotizar_shortcode');`
- **Slug sugerido:** `/mensajero-cotizar`
- **Descripción:** Cotizador rápido para mensajeros
- **Funcionalidad:** Permite a mensajeros cotizar y aceptar servicios directamente
- **Características:**
  - Resumen editable
  - Auto-asignación al aceptar
  - Acceso a todos los negocios

## Cotización - Admin

### `gofast_admin_cotizar.php`
- **Archivo:** `code/gofast_admin_cotizar.php`
- **Función:** `gofast_admin_cotizar_shortcode()`
- **Shortcode:** `[gofast_admin_cotizar]`
- **Registro:** Línea 1277: `add_shortcode('gofast_admin_cotizar', 'gofast_admin_cotizar_shortcode');`
- **Slug sugerido:** `/admin-cotizar`
- **Descripción:** Cotizador para administradores
- **Funcionalidad:** Permite a admins cotizar y asignar servicios a mensajeros
- **Características:**
  - Selección de mensajero
  - Resumen editable
  - Asignación manual

## Cotización Intermunicipal - Cliente

### `gofast_cotizar_intermunicipal.php`
- **Archivo:** `code/gofast_cotizar_intermunicipal.php`
- **Función:** `gofast_cotizar_intermunicipal_shortcode()`
- **Shortcode:** `[gofast_cotizar_intermunicipal]`
- **Registro:** Línea 413: `add_shortcode('gofast_cotizar_intermunicipal', 'gofast_cotizar_intermunicipal_shortcode');`
- **Slug sugerido:** `/cotizar-intermunicipal`
- **Descripción:** Cotizador de servicios intermunicipales para clientes
- **Funcionalidad:** Permite cotizar envíos a destinos fuera de la ciudad
- **Características:**
  - Destinos predefinidos
  - Valores fijos por destino
  - Un solo destino por servicio

### `gofast_solicitar_intermunicipal.php`
- **Archivo:** `code/gofast_solicitar_intermunicipal.php`
- **Función:** `gofast_solicitar_intermunicipal_shortcode()`
- **Shortcode:** `[gofast_solicitar_intermunicipal]`
- **Registro:** Línea 480: `add_shortcode('gofast_solicitar_intermunicipal', 'gofast_solicitar_intermunicipal_shortcode');`
- **Slug sugerido:** `/solicitar-intermunicipal`
- **Descripción:** Formulario para solicitar servicio intermunicipal
- **Funcionalidad:** Permite crear servicio intermunicipal después de cotizar

## Cotización Intermunicipal - Mensajero

### `gofast_mensajero_cotizar_intermunicipal.php`
- **Archivo:** `code/gofast_mensajero_cotizar_intermunicipal.php`
- **Función:** `gofast_mensajero_cotizar_intermunicipal_shortcode()`
- **Shortcode:** `[gofast_mensajero_cotizar_intermunicipal]`
- **Registro:** Línea 602: `add_shortcode('gofast_mensajero_cotizar_intermunicipal', 'gofast_mensajero_cotizar_intermunicipal_shortcode');`
- **Slug sugerido:** `/mensajero-cotizar-intermunicipal`
- **Descripción:** Cotizador intermunicipal para mensajeros
- **Funcionalidad:** Permite a mensajeros cotizar y aceptar servicios intermunicipales

## Cotización Intermunicipal - Admin

### `gofast_admin_cotizar_intermunicipal.php`
- **Archivo:** `code/gofast_admin_cotizar_intermunicipal.php`
- **Función:** `gofast_admin_cotizar_intermunicipal_shortcode()`
- **Shortcode:** `[gofast_admin_cotizar_intermunicipal]`
- **Registro:** Línea 653: `add_shortcode('gofast_admin_cotizar_intermunicipal', 'gofast_admin_cotizar_intermunicipal_shortcode');`
- **Slug sugerido:** `/admin-cotizar-intermunicipal`
- **Descripción:** Cotizador intermunicipal para administradores
- **Funcionalidad:** Permite a admins cotizar y asignar servicios intermunicipales

### `gofast_admin_solicitar_intermunicipal.php`
- **Archivo:** `code/gofast_admin_solicitar_intermunicipal.php`
- **Función:** `gofast_admin_solicitar_intermunicipal_shortcode()`
- **Shortcode:** `[gofast_admin_solicitar_intermunicipal]`
- **Registro:** Línea 526: `add_shortcode('gofast_admin_solicitar_intermunicipal', 'gofast_admin_solicitar_intermunicipal_shortcode');`
- **Slug sugerido:** `/admin-solicitar-intermunicipal`
- **Descripción:** Formulario admin para crear servicios intermunicipales
- **Funcionalidad:** Permite a admins crear servicios intermunicipales directamente

## Gestión de Pedidos

### `mis-pedidos.php`
- **Archivo:** `code/mis-pedidos.php`
- **Función:** `gofast_pedidos_shortcode()`
- **Shortcode:** `[gofast_pedidos]`
- **Registro:** Línea 2922: `add_shortcode('gofast_pedidos', 'gofast_pedidos_shortcode');`
- **Slug sugerido:** `/mis-pedidos`
- **Descripción:** Listado de pedidos del usuario
- **Funcionalidad:** Muestra todos los servicios del usuario (cliente, mensajero o admin)

### `gofast_confirmacion.php`
- **Archivo:** `code/gofast_confirmacion.php`
- **Función:** Función anónima
- **Shortcode:** `[gofast_confirmacion]`
- **Registro:** Línea 8: `add_shortcode("gofast_confirmacion", function() { ... });`
- **Slug sugerido:** `/confirmacion`
- **Descripción:** Página de confirmación después de crear servicio
- **Funcionalidad:** Muestra confirmación y detalles del servicio creado

## Gestión de Negocios

### `gofast_registro_negocio.php`
- **Archivo:** `code/gofast_registro_negocio.php`
- **Función:** `gofast_registro_negocio_shortcode()`
- **Shortcode:** `[gofast_registro_negocio]`
- **Registro:** Línea 228: `add_shortcode('gofast_registro_negocio', 'gofast_registro_negocio_shortcode');`
- **Slug sugerido:** `/mi-negocio` o `/registro-negocio`
- **Descripción:** Registro y gestión de negocios
- **Funcionalidad:** Permite registrar y editar negocios asociados al usuario

## Funciones de Mensajero

### `gofast_compras.php`
- **Archivo:** `code/gofast_compras.php`
- **Función:** `gofast_compras_shortcode()`
- **Shortcode:** `[gofast_compras]`
- **Registro:** Línea 1279: `add_shortcode('gofast_compras', 'gofast_compras_shortcode');`
- **Slug sugerido:** `/compras`
- **Descripción:** Gestión de compras para mensajeros
- **Funcionalidad:** Permite crear y gestionar compras (mensajeros y admins)

### `gofast_transferencias.php`
- **Archivo:** `code/gofast_transferencias.php`
- **Función:** `gofast_transferencias_shortcode()`
- **Shortcode:** `[gofast_transferencias]`
- **Registro:** Línea 1028: `add_shortcode('gofast_transferencias', 'gofast_transferencias_shortcode');`
- **Slug sugerido:** `/transferencias`
- **Descripción:** Gestión de transferencias para mensajeros
- **Funcionalidad:** Permite solicitar transferencias de dinero (mensajeros y admins)

## Panel de Administración

### `gofast_dashboard_admin.php`
- **Archivo:** `code/gofast_dashboard_admin.php`
- **Función:** `gofast_dashboard_admin_shortcode()`
- **Shortcode:** `[gofast_dashboard_admin]`
- **Registro:** Línea 271: `add_shortcode('gofast_dashboard_admin', 'gofast_dashboard_admin_shortcode');`
- **Slug sugerido:** `/admin` o `/dashboard-admin`
- **Descripción:** Dashboard principal del administrador
- **Funcionalidad:** Panel de control con estadísticas y resumen

### `gofast_usuarios_admin.php`
- **Archivo:** `code/gofast_usuarios_admin.php`
- **Función:** `gofast_usuarios_admin_shortcode()`
- **Shortcode:** `[gofast_usuarios_admin]`
- **Registro:** Línea 673: `add_shortcode('gofast_usuarios_admin', 'gofast_usuarios_admin_shortcode');`
- **Slug sugerido:** `/admin-usuarios`
- **Descripción:** Gestión de usuarios
- **Funcionalidad:** Permite crear, editar, activar/desactivar usuarios

### `gofast_admin_negocios.php`
- **Archivo:** `code/gofast_admin_negocios.php`
- **Función:** `gofast_admin_negocios_shortcode()`
- **Shortcode:** `[gofast_admin_negocios]`
- **Registro:** Línea 720: `add_shortcode('gofast_admin_negocios', 'gofast_admin_negocios_shortcode');`
- **Slug sugerido:** `/admin-negocios`
- **Descripción:** Gestión de negocios (admin)
- **Funcionalidad:** Permite gestionar todos los negocios del sistema

### `gofast_recargos_admin.php`
- **Archivo:** `code/gofast_recargos_admin.php`
- **Función:** `gofast_recargos_admin_shortcode()`
- **Shortcode:** `[gofast_recargos_admin]`
- **Registro:** Línea 1101: `add_shortcode('gofast_recargos_admin', 'gofast_recargos_admin_shortcode');`
- **Slug sugerido:** `/admin-recargos`
- **Descripción:** Gestión de recargos
- **Funcionalidad:** Permite crear y configurar recargos (lluvia, volumen, peso, etc.)

### `gofast_reportes_admin.php`
- **Archivo:** `code/gofast_reportes_admin.php`
- **Función:** `gofast_reportes_admin_shortcode()`
- **Shortcode:** `[gofast_reportes_admin]`
- **Registro:** Línea 1669: `add_shortcode('gofast_reportes_admin', 'gofast_reportes_admin_shortcode');`
- **Slug sugerido:** `/admin-reportes`
- **Descripción:** Reportes y estadísticas
- **Funcionalidad:** Genera reportes de servicios, ingresos, mensajeros, etc.

### `gofast_admin_configuracion.php`
- **Archivo:** `code/gofast_admin_configuracion.php`
- **Función:** `gofast_admin_configuracion_shortcode()`
- **Shortcode:** `[gofast_admin_configuracion]`
- **Registro:** Línea 1751: `add_shortcode('gofast_admin_configuracion', 'gofast_admin_configuracion_shortcode');`
- **Slug sugerido:** `/admin-configuracion`
- **Descripción:** Configuración general del sistema
- **Funcionalidad:** Permite configurar parámetros del sistema

### `gofast_admin_solicitudes_trabajo.php`
- **Archivo:** `code/gofast_admin_solicitudes_trabajo.php`
- **Función:** `gofast_admin_solicitudes_trabajo_shortcode()`
- **Shortcode:** `[gofast_admin_solicitudes_trabajo]`
- **Registro:** Línea 567: `add_shortcode('gofast_admin_solicitudes_trabajo', 'gofast_admin_solicitudes_trabajo_shortcode');`
- **Slug sugerido:** `/admin-solicitudes-trabajo`
- **Descripción:** Gestión de solicitudes de trabajo
- **Funcionalidad:** Permite revisar y gestionar solicitudes de mensajeros

## Páginas Públicas

### `gofast_home.php`
- **Archivo:** `code/gofast_home.php`
- **Función:** `gofast_home_shortcode()`
- **Shortcode:** `[gofast_home]`
- **Registro:** Línea 613: `add_shortcode('gofast_home', 'gofast_home_shortcode');`
- **Slug sugerido:** `/` (página principal)
- **Descripción:** Página de inicio
- **Funcionalidad:** Landing page principal del sitio

### `gofast_sobre_nosotros.php`
- **Archivo:** `code/gofast_sobre_nosotros.php`
- **Función:** `gofast_sobre_nosotros_shortcode()`
- **Shortcode:** `[gofast_sobre_nosotros]`
- **Registro:** Línea 250: `add_shortcode('gofast_sobre_nosotros', 'gofast_sobre_nosotros_shortcode');`
- **Slug sugerido:** `/sobre-nosotros`
- **Descripción:** Página sobre nosotros
- **Funcionalidad:** Información sobre la empresa

### `gofast_trabaja_con_nosotros.php`
- **Archivo:** `code/gofast_trabaja_con_nosotros.php`
- **Función:** `gofast_trabaja_con_nosotros_shortcode()`
- **Shortcode:** `[gofast_trabaja_con_nosotros]`
- **Registro:** Línea 373: `add_shortcode('gofast_trabaja_con_nosotros', 'gofast_trabaja_con_nosotros_shortcode');`
- **Slug sugerido:** `/trabaja-con-nosotros`
- **Descripción:** Formulario de solicitud de trabajo
- **Funcionalidad:** Permite a usuarios solicitar trabajo como mensajero

### `gofast_app_movil.php`
- **Archivo:** `code/gofast_app_movil.php`
- **Función:** `gofast_app_movil_shortcode()`
- **Shortcode:** `[gofast_app_movil]`
- **Registro:** Línea 222: `add_shortcode('gofast_app_movil', 'gofast_app_movil_shortcode');`
- **Slug sugerido:** `/app-movil`
- **Descripción:** Información sobre la app móvil
- **Funcionalidad:** Página promocional de la aplicación móvil

## Componentes y Utilidades

### `gofast_menu.php`
- **Archivo:** `code/gofast_menu.php`
- **Función:** `gofast_menu_topbar()`
- **Shortcode:** No tiene shortcode (se ejecuta automáticamente)
- **Registro:** Línea 206: `add_action('generate_before_header', 'gofast_menu_topbar');`
- **Hook:** `generate_before_header`
- **Slug sugerido:** N/A
- **Descripción:** Menú de navegación superior
- **Funcionalidad:** Muestra menú según el rol del usuario
- **Características:**
  - Menú dinámico según rol
  - Responsive
  - Integración con GeneratePress

### `gofast_footer.php`
- **Archivo:** `code/gofast_footer.php`
- **Función:** `gofast_footer_content()`
- **Shortcode:** No tiene (se incluye en footer)
- **Registro:** Línea 120: `add_filter('generate_footer', function() { echo gofast_footer_content(); }, 999);`
- **Hook:** `generate_footer` (filtro)
- **Slug sugerido:** N/A
- **Descripción:** Pie de página
- **Funcionalidad:** Footer del sitio
- **Características:**
  - Enlaces importantes
  - Redes sociales
  - Información de contacto

### `gofast_cookies_modal.php`
- **Archivo:** `code/gofast_cookies_modal.php`
- **Función:** `gofast_cookies_modal()`
- **Shortcode:** No tiene (se ejecuta automáticamente)
- **Registro:** Se ejecuta mediante hook (verificar en archivo)
- **Slug sugerido:** N/A
- **Descripción:** Modal de cookies
- **Funcionalidad:** Muestra modal de aceptación de cookies
- **Características:**
  - Aparece automáticamente si no se ha aceptado
  - Guarda preferencia en localStorage
  - Política de cookies explicada

### `gofast_seo.php`
- **Archivo:** `code/gofast_seo.php`
- **Función:** `gofast_add_seo_meta_tags()`
- **Shortcode:** No tiene (se ejecuta automáticamente)
- **Registro:** Se ejecuta mediante hook `wp_head`
- **Slug sugerido:** N/A
- **Descripción:** Optimizaciones SEO
- **Funcionalidad:** Agrega meta tags y optimizaciones SEO
- **Características:**
  - Meta tags básicos
  - Open Graph (Facebook, LinkedIn)
  - Twitter Cards
  - Schema.org structured data (JSON-LD)
  - Canonical URLs
  - Compatible con Cloudflare y Site Kit

### `gofast-select2-loader.php`
- **Archivo:** `code/gofast-select2-loader.php`
- **Función:** No tiene función específica
- **Shortcode:** No tiene
- **Registro:** Se incluye donde se necesite
- **Slug sugerido:** N/A
- **Descripción:** Cargador de Select2
- **Funcionalidad:** Carga la librería Select2 para selects mejorados

### `redireccion.php`
- **Archivo:** `code/redireccion.php`
- **Función:** Función anónima
- **Shortcode:** No tiene (se ejecuta automáticamente)
- **Registro:** Línea 5: `add_action('template_redirect', function() { ... });`
- **Hook:** `template_redirect`
- **Slug sugerido:** N/A
- **Descripción:** Procesamiento de redirecciones
- **Funcionalidad:** Maneja redirecciones y acciones antes del header
- **Características:**
  - Eliminar negocios (`?delete=ID`)
  - Guardar negocios (POST `gofast_guardar_negocio`)

### `sesiones.php`
- **Archivo:** `code/sesiones.php`
- **Función:** `gofast_start_session()`, `gofast_restore_session_from_cookie()`
- **Shortcode:** No tiene (se ejecuta automáticamente)
- **Registro:** Línea 28: `add_action('init', 'gofast_start_session', 1);`
- **Hook:** `init` (prioridad 1)
- **Slug sugerido:** N/A
- **Descripción:** Gestión de sesiones
- **Funcionalidad:** Inicializa y gestiona sesiones PHP
- **Características:**
  - Sesiones de 30 días
  - Restauración desde cookies persistentes
  - Configuración de parámetros de sesión

### `utils.php`
- **Archivo:** `code/utils.php`
- **Función:** Múltiples funciones utilitarias
- **Shortcode:** No tiene
- **Registro:** N/A (funciones auxiliares)
- **Slug sugerido:** N/A
- **Descripción:** Funciones utilitarias
- **Funcionalidad:** Funciones auxiliares reutilizables
- **Funciones principales:**
  - `gofast_current_time()` - Obtener tiempo actual
  - `gofast_clean_whatsapp()` - Limpiar número de WhatsApp
  - Funciones de validación y sanitización

## Resumen de Registros

### Shortcodes Registrados (26)
Todos los shortcodes se registran con `add_shortcode()`:
- `gofast_auth`
- `gofast_recuperar_password`
- `gofast_cotizar`
- `gofast_resultado`
- `gofast_mensajero_cotizar`
- `gofast_admin_cotizar`
- `gofast_cotizar_intermunicipal`
- `gofast_solicitar_intermunicipal`
- `gofast_mensajero_cotizar_intermunicipal`
- `gofast_admin_cotizar_intermunicipal`
- `gofast_admin_solicitar_intermunicipal`
- `gofast_confirmacion`
- `gofast_pedidos`
- `gofast_registro_negocio`
- `gofast_compras`
- `gofast_transferencias`
- `gofast_dashboard_admin`
- `gofast_usuarios_admin`
- `gofast_admin_negocios`
- `gofast_recargos_admin`
- `gofast_reportes_admin`
- `gofast_admin_configuracion`
- `gofast_admin_solicitudes_trabajo`
- `gofast_home`
- `gofast_sobre_nosotros`
- `gofast_trabaja_con_nosotros`
- `gofast_app_movil`

### Hooks de WordPress Utilizados
- `init` - Inicialización (sesiones, auth logic)
- `template_redirect` - Redirecciones
- `generate_before_header` - Menú
- `generate_footer` - Footer
- `wp_head` - SEO meta tags

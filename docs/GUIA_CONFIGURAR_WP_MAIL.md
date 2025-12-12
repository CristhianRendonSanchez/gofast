# 📧 Guía para Activar wp_mail() en WordPress

## ¿Qué es wp_mail()?
`wp_mail()` es la función nativa de WordPress para enviar correos. Por defecto usa `mail()` de PHP, pero es más confiable configurarlo con SMTP.

---

## ✅ OPCIÓN 1: Plugin WP Mail SMTP (RECOMENDADO - MÁS FÁCIL)

### Paso 1: Instalar el Plugin
1. Ve a **Plugins → Añadir nuevo** en WordPress
2. Busca: **"WP Mail SMTP"** (de WPForms)
3. Instala y activa el plugin

### Paso 2: Configurar
1. Ve a **WP Mail SMTP → Settings**
2. En **Mailer**, elige una opción:

#### **A) Gmail (Gratis)**
- **SMTP Host**: `smtp.gmail.com`
- **Encryption**: TLS
- **SMTP Port**: 587
- **Authentication**: ON
- **SMTP Username**: Tu email de Gmail
- **SMTP Password**: Necesitas una "Contraseña de aplicación" (ver abajo)

**⚠️ Importante para Gmail:**
- Activa verificación en 2 pasos en tu cuenta Google
- Ve a: https://myaccount.google.com/apppasswords
- Genera una contraseña de aplicación (16 caracteres)
- Usa esa contraseña en el plugin, NO tu contraseña normal

#### **B) Otro SMTP (Gratis - Outlook, Yahoo, etc.)**
- **SMTP Host**: Depende del proveedor
  - Outlook: `smtp-mail.outlook.com`
  - Yahoo: `smtp.mail.yahoo.com`
- **Encryption**: TLS
- **SMTP Port**: 587
- **Authentication**: ON
- **SMTP Username**: Tu email
- **SMTP Password**: Tu contraseña

### Paso 3: Probar
1. Ve a **WP Mail SMTP → Email Test**
2. Envía un correo de prueba a tu email
3. Verifica que llegue correctamente

---

## ✅ OPCIÓN 2: Configuración Manual en Código

Si prefieres no usar plugins, puedes agregar configuración directamente en código.

### Paso 1: Editar functions.php
Abre el archivo `functions.php` de tu tema (o crea uno si no existe).

### Paso 2: Agregar Código
Abre el archivo `code/gofast_smtp_config.php` que ya creé, elige una opción y copia el código a tu `functions.php`, o incluye el archivo:

```php
// En tu functions.php, agrega:
require_once get_template_directory() . '/code/gofast_smtp_config.php';
```

Luego edita `code/gofast_smtp_config.php`:
1. Descomenta la línea `add_action()` de la opción que quieres usar
2. Completa los datos (email, contraseña, etc.)
3. Guarda el archivo

### Ejemplo para Gmail:
```php
function gofast_configure_smtp_gmail($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = 'smtp.gmail.com';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = 587;
    $phpmailer->SMTPSecure = 'tls';
    $phpmailer->Username = 'tu-email@gmail.com'; // ⚠️ CAMBIAR
    $phpmailer->Password = 'contraseña-app-16-caracteres'; // ⚠️ CAMBIAR
    $phpmailer->From = 'tu-email@gmail.com'; // ⚠️ CAMBIAR
    $phpmailer->FromName = 'GoFast';
    $phpmailer->CharSet = 'UTF-8';
}
add_action('phpmailer_init', 'gofast_configure_smtp_gmail');
```

---

## ✅ OPCIÓN 3: Mail Nativo de PHP (Sin configuración)

Si tu servidor permite `mail()` de PHP, `wp_mail()` funcionará automáticamente sin configurar nada.

**Problema**: Muchos servidores bloquean el envío directo, especialmente servicios de hosting compartido.

**Ventaja**: No requiere configuración.

**Prueba**: Intenta usar la recuperación de contraseña y verifica si llegan los correos.

---

## 🔍 Verificar si wp_mail() Funciona

### Método 1: Prueba con Recuperación de Contraseña
1. Ve a: `/recuperar-password`
2. Ingresa un email
3. Verifica si llega el correo

### Método 2: Agregar Código de Depuración
Agrega esto temporalmente en `functions.php` para ver errores:

```php
add_action('wp_mail_failed', 'gofast_log_mail_errors');
function gofast_log_mail_errors($wp_error) {
    error_log('WP Mail Error: ' . $wp_error->get_error_message());
}
```

Luego revisa los logs de WordPress para ver si hay errores.

---

## 📋 Servidores SMTP Comunes (Gratis)

| Proveedor | SMTP Host | Puerto | Encryption |
|-----------|-----------|--------|------------|
| Gmail | smtp.gmail.com | 587 | TLS |
| Outlook | smtp-mail.outlook.com | 587 | TLS |
| Yahoo | smtp.mail.yahoo.com | 587 | TLS |
| Zoho | smtp.zoho.com | 587 | TLS |

---

## ❓ Preguntas Frecuentes

### ¿Por qué usar SMTP en lugar de mail() nativo?
- **Más confiable**: Menos correos en spam
- **Mejor entrega**: Los proveedores confían más en SMTP autenticado
- **Trazabilidad**: Puedes ver si se enviaron correctamente

### ¿Cuál opción elegir?
- **Si eres principiante**: Opción 1 (Plugin)
- **Si prefieres código**: Opción 2 (Manual)
- **Si tu hosting lo permite**: Opción 3 (Nativo)

### ¿Gmail es gratis?
Sí, pero necesitas:
1. Verificación en 2 pasos activada
2. Contraseña de aplicación (no tu contraseña normal)

---

## 🚀 Después de Configurar

Una vez configurado, prueba el sistema de recuperación de contraseña:
1. Ve a `/auth`
2. Haz clic en "¿Olvidaste tu contraseña?"
3. Ingresa un email
4. Verifica que llegue el correo

¡Listo! 🎉


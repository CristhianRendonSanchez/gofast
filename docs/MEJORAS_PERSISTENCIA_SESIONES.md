# Mejoras en Persistencia de Sesiones - GoFast

## 🔧 Problemas Identificados y Solucionados

### Problemas Anteriores:
1. ❌ Las sesiones se cerraban al cerrar el navegador
2. ❌ El registro no creaba cookie persistente automáticamente
3. ❌ La restauración de sesión desde cookie ocurría después de iniciar sesión
4. ❌ No había configuración de tiempo de vida de sesión PHP
5. ❌ La cookie no tenía configuración SameSite adecuada

### Soluciones Implementadas:

## ✅ 1. Sistema de Sesiones Mejorado (`code/sesiones.php`)

### Características:
- **Tiempo de vida de sesión**: 30 días (2,592,000 segundos)
- **Cookie de sesión PHP**: Configurada para 30 días
- **Restauración automática**: Restaura sesión desde cookie al iniciar
- **SameSite**: Configurado como 'Lax' para compatibilidad moderna

### Funciones:
- `gofast_start_session()`: Inicia sesión con configuración mejorada
- `gofast_restore_session_from_cookie()`: Restaura sesión desde cookie persistente

## ✅ 2. Cookie Persistente Mejorada (`code/gofast_auth_logic.php`)

### Características:
- **Duración**: 30 días
- **HttpOnly**: true (protección XSS)
- **SameSite**: Lax (compatibilidad moderna)
- **Token único**: Generado con `wp_generate_uuid4()`
- **Almacenamiento**: Token guardado en base de datos (`remember_token`)

### Cuándo se crea:
1. ✅ **Login con "Mantener sesión"**: Si el usuario marca el checkbox
2. ✅ **Registro**: Automáticamente al registrarse (nuevo)
3. ✅ **Restauración**: Se restaura automáticamente al visitar el sitio

## ✅ 3. Función Centralizada

Nueva función `gofast_create_persistent_cookie()`:
- Crea token único
- Guarda en base de datos
- Configura cookie con parámetros modernos
- Compatible con PHP 7.3+ y versiones anteriores

## 📋 Flujo de Persistencia

### Al Iniciar Sesión:
1. Usuario marca "Mantener sesión" → Se crea cookie `gofast_token` (30 días)
2. Token se guarda en `usuarios_gofast.remember_token`
3. Sesión PHP se guarda con tiempo de vida de 30 días

### Al Cerrar Navegador:
1. Sesión PHP se mantiene en el servidor (30 días)
2. Cookie `gofast_token` permanece en el navegador (30 días)

### Al Volver a Visitar:
1. `gofast_start_session()` se ejecuta (prioridad 1)
2. `gofast_restore_session_from_cookie()` verifica cookie
3. Si existe cookie válida → Restaura sesión automáticamente
4. Usuario permanece logueado sin necesidad de login

### Al Cerrar Sesión:
1. Se elimina token de la base de datos
2. Se elimina cookie del navegador
3. Se destruye sesión PHP

## 🔒 Seguridad

### Protecciones Implementadas:
- ✅ **HttpOnly**: Cookie no accesible desde JavaScript (protección XSS)
- ✅ **SameSite Lax**: Protección CSRF básica
- ✅ **Token único**: Cada usuario tiene un token único
- ✅ **Validación en BD**: Token se valida contra base de datos
- ✅ **Limpieza en logout**: Token se elimina al cerrar sesión

### Recomendaciones:
- Si usas HTTPS, cambiar `$cookie_secure = true` en ambos archivos
- Considerar rotación de tokens periódica (futuro)

## 🚀 Instalación

### Archivos Modificados/Creados:
1. ✅ `code/sesiones.php` - **NUEVO** (reemplaza snippet)
2. ✅ `code/gofast_auth_logic.php` - **MODIFICADO**

### Pasos:
1. Reemplazar el snippet `sesiones` con el contenido de `code/sesiones.php`
2. Actualizar el snippet `gofast_auth_logic` con el contenido de `code/gofast_auth_logic.php`
3. Asegurar que el campo `remember_token` existe en la tabla (ejecutar `db/usuarios_gofast_alter_remember_token.sql`)

## ✅ Resultado Final

### Comportamiento Esperado:
- ✅ Usuario permanece logueado por 30 días
- ✅ Sesión persiste al cerrar navegador
- ✅ Sesión persiste al limpiar caché (pero NO al limpiar cookies)
- ✅ Restauración automática al visitar el sitio
- ✅ Funciona en registro y login

### Pruebas:
1. Login con "Mantener sesión" → Cerrar navegador → Abrir → Debe estar logueado
2. Registrarse → Cerrar navegador → Abrir → Debe estar logueado
3. Limpiar caché (sin cookies) → Debe estar logueado
4. Limpiar cookies → Debe pedir login

## 📝 Notas Técnicas

- **Prioridad de hooks**: `gofast_start_session` (1) se ejecuta antes que `gofast_handle_auth_requests` (5)
- **Compatibilidad PHP**: Funciona con PHP 7.0+ (con fallback para versiones antiguas)
- **Base de datos**: Requiere campo `remember_token` en `usuarios_gofast`


# Flujo: Login / Crear Cuenta / Recuperar Contraseña

## Descripción General

Este flujo documenta el proceso completo de autenticación del sistema GoFast, incluyendo login, registro de nuevos usuarios y recuperación de contraseña.

## Archivos Involucrados

- `gofast_auth.php` - Interfaz de login/registro
- `gofast_auth_logic.php` - Lógica de autenticación
- `gofast_recuperar_password.php` - Recuperación de contraseña

## 1. Flujo de Login

### 1.1 Acceso
- **URL:** `/auth`
- **Shortcode:** `[gofast_auth]`
- **Archivo:** `gofast_auth.php`

### 1.2 Proceso

1. **Usuario accede a `/auth`**
   - Si ya está logueado → redirige a home
   - Si no está logueado → muestra formulario de login

2. **Usuario completa formulario:**
   - Email o WhatsApp
   - Contraseña
   - Opcional: "Mantener sesión iniciada 30 días" (checkbox)

3. **Validación (gofast_auth_logic.php):**
   - Verifica honeypot anti-bot
   - Valida que campos no estén vacíos
   - Busca usuario por email o teléfono normalizado
   - Verifica contraseña con `password_verify()`
   - Aplica rate limiting (máx 5 intentos en 10 minutos)

4. **Si login exitoso:**
   - Crea sesión PHP (`$_SESSION['gofast_user_id']` y `$_SESSION['gofast_user_rol']`)
   - Si marcó "recordar" y aceptó cookies → crea cookie persistente (30 días)
   - Redirige a home (`/`)

5. **Si login falla:**
   - Incrementa contador de intentos
   - Muestra mensaje de error
   - Permite reintentar

### 1.3 Seguridad

- **Honeypot:** Campo oculto `gofast_extra_field` para detectar bots
- **Rate Limiting:** Máximo 5 intentos fallidos por IP en 10 minutos
- **Cookies Persistentes:** Solo se crean si el usuario aceptó cookies previamente
- **Tokens:** Se generan con `wp_generate_uuid4()` y se almacenan en BD

## 2. Flujo de Registro

### 2.1 Acceso
- **URL:** `/auth/?registro=1`
- **Shortcode:** `[gofast_auth]` (modo registro)
- **Archivo:** `gofast_auth.php`

### 2.2 Proceso

1. **Usuario accede a `/auth/?registro=1`**
   - Muestra formulario de registro

2. **Usuario completa formulario:**
   - Nombre completo
   - WhatsApp (mínimo 10 dígitos)
   - Email
   - Contraseña (mínimo 6 caracteres)
   - Confirmar contraseña

3. **Validación (gofast_auth_logic.php):**
   - Verifica honeypot anti-bot
   - Valida que todos los campos estén completos
   - Valida formato de email con `is_email()`
   - Valida que contraseñas coincidan
   - Valida longitud mínima de contraseña (6 caracteres)
   - Normaliza teléfono (solo dígitos)
   - Verifica que no exista usuario con mismo email o teléfono

4. **Si registro exitoso:**
   - Crea hash de contraseña con `password_hash()`
   - Inserta usuario en `usuarios_gofast` con rol 'cliente'
   - Login automático (crea sesión)
   - Si aceptó cookies → crea cookie persistente (30 días)
   - Redirige a home (`/`)

5. **Si registro falla:**
   - Muestra mensaje de error específico
   - Permite corregir y reintentar

### 2.3 Campos de Usuario Creado

- `nombre` - Nombre completo
- `telefono` - WhatsApp normalizado
- `email` - Email validado
- `password_hash` - Hash bcrypt
- `rol` - 'cliente' (por defecto)
- `activo` - 1 (activo)
- `fecha_registro` - Timestamp actual
- `remember_token` - NULL (se crea si acepta cookies)

## 3. Flujo de Recuperación de Contraseña

### 3.1 Acceso
- **URL:** `/recuperar-password`
- **Shortcode:** `[gofast_recuperar_password]`
- **Archivo:** `gofast_recuperar_password.php`

### 3.2 Proceso - Solicitar Recuperación

1. **Usuario accede a `/recuperar-password`**
   - Muestra formulario para solicitar recuperación

2. **Usuario ingresa email o teléfono:**
   - Campo: Email o número de teléfono

3. **Validación:**
   - Busca usuario por email o teléfono normalizado
   - Verifica que usuario exista y esté activo
   - Verifica que tenga email (necesario para enviar recuperación)

4. **Si usuario encontrado:**
   - Genera token único: `bin2hex(random_bytes(32))` (64 caracteres)
   - Establece expiración: 1 hora desde ahora
   - Guarda `reset_token` y `reset_token_expires` en BD
   - Envía email HTML con enlace de recuperación
   - Muestra mensaje de éxito (email enmascarado por seguridad)

5. **Si usuario no encontrado:**
   - Por seguridad, muestra mensaje genérico de éxito
   - No revela si el email/teléfono existe o no

### 3.3 Proceso - Resetear Contraseña

1. **Usuario hace clic en enlace del email:**
   - URL: `/recuperar-password?token=XXXXX`
   - Muestra formulario para nueva contraseña

2. **Validación del token:**
   - Busca usuario con token válido
   - Verifica que token no haya expirado
   - Si token inválido/expirado → muestra error y permite solicitar nuevo

3. **Usuario ingresa nueva contraseña:**
   - Nueva contraseña (mínimo 6 caracteres)
   - Confirmar contraseña

4. **Validación:**
   - Verifica que contraseñas coincidan
   - Verifica longitud mínima (6 caracteres)
   - Verifica que token siga siendo válido

5. **Si reset exitoso:**
   - Genera nuevo hash de contraseña
   - Actualiza `password_hash` en BD
   - Limpia `reset_token` y `reset_token_expires` (NULL)
   - Muestra mensaje de éxito
   - Usuario puede iniciar sesión con nueva contraseña

### 3.4 Email de Recuperación

- **Asunto:** "Recuperación de contraseña - GoFast"
- **Formato:** HTML con estilos
- **Contenido:**
  - Saludo personalizado
  - Botón de acción con enlace
  - Enlace de texto alternativo
  - Advertencia de seguridad
- **Enlace:** `/recuperar-password?token=TOKEN_GENERADO`
- **Expiración:** 1 hora

## 4. Flujo de Logout

### 4.1 Acceso
- **URL:** `/?gofast_logout=1`
- **Archivo:** `gofast_auth_logic.php`

### 4.2 Proceso

1. **Usuario hace clic en logout**
   - Se ejecuta `gofast_handle_auth_requests()`

2. **Eliminación de sesión:**
   - Elimina variables de sesión (`gofast_user_id`, `gofast_user_rol`)
   - Destruye sesión PHP

3. **Eliminación de cookie persistente:**
   - Si existe cookie `gofast_token`:
     - Limpia token en BD (establece `remember_token` = NULL)
     - Elimina cookie del navegador

4. **Redirección:**
   - Redirige a home (`/`)

## 5. Sesión Persistente (Cookies)

### 5.1 Creación de Cookie

- **Cuándo se crea:**
  - Login con checkbox "Mantener sesión" marcado
  - Registro exitoso (automático)
  - Solo si usuario aceptó cookies previamente

- **Proceso:**
  1. Genera token único: `wp_generate_uuid4()`
  2. Guarda token en BD: `usuarios_gofast.remember_token`
  3. Crea cookie: `gofast_token` con valor del token
  4. Expiración: 30 días
  5. Configuración: HttpOnly, SameSite=Lax

### 5.2 Validación de Cookie

- Se valida automáticamente en `sesiones.php`
- Si existe cookie válida → restaura sesión automáticamente
- Si token no existe en BD o usuario inactivo → elimina cookie

## 6. Diagrama de Flujo

```
LOGIN:
Usuario → /auth → Formulario Login → Validación → ¿Válido?
                                              ↓ NO
                                            Error
                                              ↓ SÍ
                                    Crear Sesión → ¿Recordar?
                                                      ↓ SÍ
                                            Crear Cookie (30 días)
                                                      ↓
                                              Redirigir a Home

REGISTRO:
Usuario → /auth/?registro=1 → Formulario Registro → Validación → ¿Válido?
                                                              ↓ NO
                                                            Error
                                                              ↓ SÍ
                                                    Crear Usuario → Login Automático
                                                                        ↓
                                                              Crear Cookie (30 días)
                                                                        ↓
                                                              Redirigir a Home

RECUPERAR:
Usuario → /recuperar-password → Ingresar Email/Tel → ¿Existe?
                                                      ↓ SÍ
                                    Generar Token → Enviar Email → Usuario hace clic
                                                                        ↓
                                    /recuperar-password?token=XXX → ¿Token válido?
                                                                        ↓ SÍ
                                    Formulario Nueva Contraseña → Actualizar → Éxito
```

## 7. Tablas de Base de Datos Utilizadas

- **usuarios_gofast:**
  - `id` - ID del usuario
  - `nombre` - Nombre completo
  - `telefono` - WhatsApp
  - `email` - Email
  - `password_hash` - Hash de contraseña
  - `rol` - Rol (cliente, mensajero, admin)
  - `activo` - Estado activo/inactivo
  - `fecha_registro` - Fecha de registro
  - `remember_token` - Token para cookie persistente
  - `reset_token` - Token para recuperación
  - `reset_token_expires` - Expiración del token de recuperación

## 8. Consideraciones de Seguridad

1. **Contraseñas:**
   - Se almacenan con hash bcrypt (`password_hash()`)
   - Mínimo 6 caracteres
   - No se muestran en ningún momento

2. **Tokens:**
   - Tokens de recuperación: 64 caracteres hexadecimales aleatorios
   - Tokens de sesión: UUID v4
   - Expiración de tokens de recuperación: 1 hora

3. **Rate Limiting:**
   - Máximo 5 intentos fallidos por IP en 10 minutos
   - Se bloquea temporalmente después de 5 intentos

4. **Honeypot:**
   - Campo oculto para detectar bots
   - Si se completa → bloquea acceso

5. **Cookies:**
   - HttpOnly: Previne acceso desde JavaScript
   - SameSite=Lax: Protección CSRF
   - Solo se crean si usuario acepta cookies


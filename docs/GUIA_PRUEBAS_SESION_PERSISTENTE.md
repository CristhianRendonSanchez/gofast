# Guía de Pruebas - Sesión Persistente (30 días)

## 🧪 Cómo Probar la Sesión Persistente

### Prerequisitos
1. ✅ Ejecutar el script SQL: `db/usuarios_gofast_alter_remember_token.sql`
2. ✅ Tener los archivos PHP actualizados en WordPress
3. ✅ Tener un usuario de prueba creado

---

## 📋 Prueba 1: Registro con Sesión Persistente

### Pasos:
1. **Abrir navegador en modo incógnito** (para empezar limpio)
2. **Ir a**: `/auth/?registro=1`
3. **Llenar formulario de registro**:
   - Nombre: Test Usuario
   - WhatsApp: 3001234567
   - Email: test@example.com
   - Contraseña: 123456 (mínimo 6 caracteres)
   - Confirmar contraseña: 123456
4. **Hacer clic en "Crear cuenta"**

### ✅ Resultado Esperado:
- Usuario se crea correctamente
- Se redirige a la página principal
- Usuario está logueado automáticamente
- Cookie `gofast_token` se crea automáticamente (30 días)

### 🔍 Verificar:
1. **Abrir DevTools** (F12)
2. **Ir a pestaña "Application"** (Chrome) o "Storage" (Firefox)
3. **Cookies** → Seleccionar tu dominio
4. **Buscar cookie `gofast_token`**:
   - ✅ Debe existir
   - ✅ Expires: Debe ser en 30 días
   - ✅ HttpOnly: true
   - ✅ SameSite: Lax

---

## 📋 Prueba 2: Login con "Mantener Sesión"

### Pasos:
1. **Cerrar sesión** (si estás logueado): Ir a `/?gofast_logout=1`
2. **Ir a**: `/auth`
3. **Llenar formulario de login**:
   - Email o WhatsApp: test@example.com
   - Contraseña: 123456
   - ✅ **MARCAR checkbox "Mantener sesión iniciada 30 días"**
4. **Hacer clic en "Ingresar"**

### ✅ Resultado Esperado:
- Login exitoso
- Redirección a home
- Cookie `gofast_token` creada
- Token guardado en BD en campo `remember_token`

### 🔍 Verificar:
1. **Cookie `gofast_token`** existe en DevTools
2. **Base de datos**: Verificar que el usuario tiene `remember_token` no nulo:
   ```sql
   SELECT id, nombre, email, remember_token 
   FROM usuarios_gofast 
   WHERE email = 'test@example.com';
   ```

---

## 📋 Prueba 3: Persistencia al Cerrar Navegador

### Pasos:
1. **Estar logueado** (desde Prueba 1 o 2)
2. **Cerrar completamente el navegador** (no solo la pestaña)
3. **Esperar 30 segundos**
4. **Abrir navegador nuevamente**
5. **Ir a**: Tu sitio (home)

### ✅ Resultado Esperado:
- **Usuario sigue logueado** automáticamente
- No necesita hacer login de nuevo
- Menú muestra opciones de usuario logueado
- Sesión restaurada desde cookie

### 🔍 Verificar:
1. **Verificar que aparece tu nombre** en el menú
2. **Ir a `/mis-pedidos`** → Debe mostrar tus pedidos (no pedir login)
3. **Cookie `gofast_token`** sigue existiendo

---

## 📋 Prueba 4: Persistencia al Limpiar Caché (SIN cookies)

### Pasos:
1. **Estar logueado**
2. **Abrir DevTools** (F12)
3. **Ir a pestaña "Application"** → **Clear storage**
4. **Marcar solo "Cache"** (NO marcar "Cookies")
5. **Hacer clic en "Clear site data"**
6. **Recargar la página** (F5)

### ✅ Resultado Esperado:
- **Usuario sigue logueado** (porque la cookie persiste)
- Sesión restaurada desde cookie

---

## 📋 Prueba 5: NO Persistencia al Limpiar Cookies

### Pasos:
1. **Estar logueado**
2. **Abrir DevTools** (F12)
3. **Ir a pestaña "Application"** → **Clear storage**
4. **Marcar "Cookies"** (y opcionalmente "Cache")
5. **Hacer clic en "Clear site data"**
6. **Recargar la página** (F5)

### ✅ Resultado Esperado:
- **Usuario NO está logueado**
- Redirige a login o muestra opciones de visitante
- Cookie `gofast_token` eliminada

---

## 📋 Prueba 6: Restauración Automática en Nueva Pestaña

### Pasos:
1. **Estar logueado en una pestaña**
2. **Abrir nueva pestaña** (Ctrl+T)
3. **Ir a**: Tu sitio

### ✅ Resultado Esperado:
- **Usuario está logueado** en la nueva pestaña
- Sesión compartida entre pestañas

---

## 📋 Prueba 7: Verificar Token en Base de Datos

### Pasos:
1. **Hacer login con "remember" marcado**
2. **Abrir phpMyAdmin o cliente SQL**
3. **Ejecutar consulta**:
   ```sql
   SELECT id, nombre, email, remember_token, activo
   FROM usuarios_gofast
   WHERE email = 'test@example.com';
   ```

### ✅ Resultado Esperado:
- `remember_token` tiene un valor UUID (ej: `a1b2c3d4-e5f6-7890-abcd-ef1234567890`)
- `activo` = 1

---

## 📋 Prueba 8: Logout Elimina Token

### Pasos:
1. **Estar logueado con cookie persistente**
2. **Hacer logout**: Ir a `/?gofast_logout=1`
3. **Verificar en BD**:
   ```sql
   SELECT remember_token
   FROM usuarios_gofast
   WHERE email = 'test@example.com';
   ```

### ✅ Resultado Esperado:
- `remember_token` = NULL (eliminado)
- Cookie `gofast_token` eliminada del navegador
- Usuario deslogueado

---

## 📋 Prueba 9: Token Inválido se Limpia

### Pasos:
1. **Estar logueado**
2. **Modificar token en BD** (simular token inválido):
   ```sql
   UPDATE usuarios_gofast
   SET remember_token = 'token_invalido_123'
   WHERE email = 'test@example.com';
   ```
3. **Cerrar navegador completamente**
4. **Abrir navegador nuevamente**
5. **Ir a**: Tu sitio

### ✅ Resultado Esperado:
- **Usuario NO está logueado** (token inválido)
- Cookie `gofast_token` eliminada automáticamente
- Sistema detecta token inválido y limpia

---

## 📋 Prueba 10: Usuario Inactivo No Restaura Sesión

### Pasos:
1. **Estar logueado**
2. **Desactivar usuario en BD**:
   ```sql
   UPDATE usuarios_gofast
   SET activo = 0
   WHERE email = 'test@example.com';
   ```
3. **Cerrar navegador completamente**
4. **Abrir navegador nuevamente**
5. **Ir a**: Tu sitio

### ✅ Resultado Esperado:
- **Usuario NO está logueado** (usuario inactivo)
- Cookie `gofast_token` eliminada
- Sistema detecta usuario inactivo

---

## 🔧 Herramientas de Verificación

### 1. Ver Cookies en Navegador

**Chrome/Edge:**
- F12 → Application → Cookies → Tu dominio
- Buscar `gofast_token`

**Firefox:**
- F12 → Storage → Cookies → Tu dominio
- Buscar `gofast_token`

**Safari:**
- Cmd+Option+I → Storage → Cookies
- Buscar `gofast_token`

### 2. Ver Sesión PHP

Agregar temporalmente en cualquier página:
```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
echo '<pre>';
print_r($_SESSION);
echo '</pre>';
?>
```

### 3. Verificar en Base de Datos

```sql
-- Ver todos los usuarios con tokens
SELECT id, nombre, email, remember_token, activo, fecha_registro
FROM usuarios_gofast
WHERE remember_token IS NOT NULL;

-- Ver usuario específico
SELECT * FROM usuarios_gofast WHERE email = 'test@example.com';
```

---

## ✅ Checklist de Pruebas

- [ ] Prueba 1: Registro crea cookie automáticamente
- [ ] Prueba 2: Login con "remember" crea cookie
- [ ] Prueba 3: Sesión persiste al cerrar navegador
- [ ] Prueba 4: Sesión persiste al limpiar caché (sin cookies)
- [ ] Prueba 5: Sesión NO persiste al limpiar cookies
- [ ] Prueba 6: Sesión compartida entre pestañas
- [ ] Prueba 7: Token guardado en BD
- [ ] Prueba 8: Logout elimina token
- [ ] Prueba 9: Token inválido se limpia
- [ ] Prueba 10: Usuario inactivo no restaura

---

## 🐛 Solución de Problemas

### Problema: Cookie no se crea
**Solución:**
1. Verificar que el campo `remember_token` existe en BD
2. Verificar que `gofast_create_persistent_cookie()` se ejecuta
3. Revisar logs de PHP (con WP_DEBUG activo)
4. Verificar que no hay errores de JavaScript en consola

### Problema: Sesión no se restaura
**Solución:**
1. Verificar que `gofast_restore_session_from_cookie()` se ejecuta
2. Verificar que la cookie existe en el navegador
3. Verificar que el token en BD coincide con la cookie
4. Verificar que el usuario está activo (`activo = 1`)

### Problema: Cookie se elimina inmediatamente
**Solución:**
1. Verificar configuración de `session_set_cookie_params()`
2. Verificar que no hay código que elimine cookies
3. Verificar que el dominio de la cookie es correcto
4. Si usas HTTPS, cambiar `secure => true` en `sesiones.php`

---

## 📝 Notas Importantes

1. **Tiempo de vida**: Las cookies duran 30 días (2,592,000 segundos)
2. **HttpOnly**: Las cookies no son accesibles desde JavaScript (seguridad)
3. **SameSite Lax**: Protección básica CSRF
4. **Token único**: Cada usuario tiene un token único en BD
5. **Limpieza automática**: Tokens inválidos se eliminan automáticamente

---

## 🎯 Resultado Final Esperado

Después de todas las pruebas, deberías poder:
- ✅ Registrarte y permanecer logueado por 30 días
- ✅ Hacer login con "remember" y permanecer logueado
- ✅ Cerrar navegador y seguir logueado al volver
- ✅ Limpiar caché y seguir logueado (pero NO al limpiar cookies)
- ✅ Ver que el token se guarda y elimina correctamente en BD


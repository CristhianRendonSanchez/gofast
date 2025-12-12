# Modal de Cookies - Implementación Completa

## ✅ Archivo Creado

**`code/gofast_cookies_modal.php`** - Modal emergente para aceptar/rechazar cookies

---

## 🎯 Características del Modal

### 1. **Apariencia**
- ✅ Modal centrado con overlay oscuro
- ✅ Diseño moderno y responsive
- ✅ Animación de entrada suave
- ✅ Bloquea el scroll mientras está abierto

### 2. **Funcionalidad**
- ✅ Aparece automáticamente al visitar el sitio (si no se ha aceptado/rechazado antes)
- ✅ Solo se muestra una vez (preferencia guardada en localStorage)
- ✅ Dos opciones: Aceptar o Rechazar
- ✅ No se puede cerrar sin tomar una decisión (no se cierra al hacer clic fuera)

### 3. **Información Mostrada**
- ✅ Explicación clara de qué cookies se usan
- ✅ Duración de las cookies (30 días)
- ✅ Lista de cookies utilizadas
- ✅ Nota sobre cambiar preferencias

---

## 🔄 Flujo de Funcionamiento

### Al Visitar el Sitio por Primera Vez:

1. **Modal aparece automáticamente** (después de 500ms)
2. **Usuario elige**:
   - **Aceptar** → Guarda `gofast_cookies_preference = 'accepted'` en localStorage
   - **Rechazar** → Guarda `gofast_cookies_preference = 'rejected'` y elimina cookie si existe

### Al Hacer Login/Registro:

1. **Formulario incluye campo hidden** `gofast_cookies_accepted`
2. **JavaScript actualiza el campo** según preferencia guardada
3. **Servidor verifica** si aceptó cookies antes de crear cookie persistente
4. **Si aceptó** → Se crea cookie `gofast_token` (30 días)
5. **Si rechazó** → NO se crea cookie (sesión solo dura mientras el navegador esté abierto)

---

## 📋 Integración con Formularios

### Login (`gofast_auth.php`):
- Campo hidden `gofast_cookies_accepted` agregado
- JavaScript actualiza el valor según preferencia
- Si aceptó cookies Y marca "remember" → Se crea cookie

### Registro (`gofast_auth.php`):
- Campo hidden `gofast_cookies_accepted` agregado
- JavaScript actualiza el valor según preferencia
- Si aceptó cookies → Se crea cookie automáticamente

---

## 🔒 Comportamiento Según Preferencia

### Si Acepta Cookies:
- ✅ Cookie `gofast_token` se crea al hacer login/registro
- ✅ Sesión persiste por 30 días
- ✅ Usuario permanece logueado al cerrar navegador

### Si Rechaza Cookies:
- ❌ Cookie `gofast_token` NO se crea
- ❌ Sesión solo dura mientras el navegador esté abierto
- ❌ Usuario debe hacer login cada vez que cierra navegador
- ✅ Cookie existente se elimina si había una

---

## 🎨 Personalización

### Cambiar Tiempo de Aparición:
En `gofast_cookies_modal.php`, línea ~220:
```javascript
setTimeout(function() {
    modal.style.display = 'block';
}, 500); // Cambiar 500 por el tiempo deseado (en milisegundos)
```

### Permitir Cerrar al Hacer Clic Fuera:
En `gofast_cookies_modal.php`, línea ~250:
```javascript
overlay.addEventListener('click', function(e) {
    gofastRejectCookies(); // Descomentar esta línea
});
```

### Cambiar Colores:
Modificar variables CSS en la sección `<style>` del modal.

---

## 🧪 Pruebas

### Prueba 1: Modal Aparece
1. Limpiar localStorage: `localStorage.clear()`
2. Recargar página
3. ✅ Modal debe aparecer después de 500ms

### Prueba 2: Aceptar Cookies
1. Hacer clic en "Aceptar todas las cookies"
2. ✅ Modal se cierra
3. ✅ `localStorage.getItem('gofast_cookies_preference')` = `'accepted'`
4. ✅ Al hacer login/registro, se crea cookie

### Prueba 3: Rechazar Cookies
1. Limpiar localStorage
2. Recargar página
3. Hacer clic en "Rechazar cookies"
4. ✅ Modal se cierra
5. ✅ `localStorage.getItem('gofast_cookies_preference')` = `'rejected'`
6. ✅ Al hacer login/registro, NO se crea cookie

### Prueba 4: No Aparece Después de Aceptar
1. Aceptar cookies
2. Recargar página
3. ✅ Modal NO aparece (ya se aceptó antes)

---

## 📝 Variables de localStorage

- `gofast_cookies_preference`: `'accepted'` o `'rejected'`
- `gofast_cookie_ok`: `'1'` (compatibilidad con código existente)

---

## 🔧 Instalación

1. **Agregar archivo a WordPress**:
   - Copiar `code/gofast_cookies_modal.php` a tu tema o plugin
   - O agregar el código al snippet `gofast_cookies_modal`

2. **Verificar que se carga**:
   - El modal se agrega automáticamente al footer de todas las páginas
   - Hook: `add_action('wp_footer', ..., 999)`

3. **Probar**:
   - Limpiar localStorage
   - Recargar página
   - Modal debe aparecer

---

## ✅ Estado

**IMPLEMENTACIÓN COMPLETA** ✅

- ✅ Modal creado y funcional
- ✅ Integrado con formularios de login/registro
- ✅ Verificación de preferencia antes de crear cookies
- ✅ Diseño responsive y moderno
- ✅ Compatible con código existente


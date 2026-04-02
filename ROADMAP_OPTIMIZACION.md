# GOFAST — Hoja de Ruta: Optimización y Mantenimiento
> WordPress · Code Snippets · Hostinger · Última revisión: 2026-03-27

---

## Índice
1. [Diagnóstico general](#1-diagnóstico-general)
2. [Plan de 4 días](#2-plan-de-4-días)
3. [Estimado de cobro](#3-estimado-de-cobro)
4. [Detalle técnico por tarea](#4-detalle-técnico-por-tarea)
5. [Mantenimiento periódico](#5-mantenimiento-periódico)
6. [Checklist de deploy](#6-checklist-de-deploy)

---

## 1. Diagnóstico general

| Área | Estado | Prioridad |
|------|--------|-----------|
| Seguridad SQL | ⚠️ Consultas sin `prepare()` en algunos archivos | 🔴 Alta |
| Índices en BD | ❌ Faltan en `servicios_gofast` | 🔴 Alta |
| Consultas N+1 | ❌ 3 queries por destino en cotizaciones | 🔴 Alta |
| Código duplicado | ❌ Lógica de recargos repetida en 6 archivos (~80 líneas c/u) | 🟠 Media-Alta |
| CSS `!important` | ❌ 556 ocurrencias | 🟠 Media |
| Estilos inline en PHP | ⚠️ 474+ atributos `style=""` mezclados en lógica | 🟠 Media |
| Tipos de datos BD | ⚠️ `whatsapp` como INT (overflow detectado) | 🟠 Media |
| Schema versioning | ⚠️ 6 ALTER sueltos sin orden documentado | 🟡 Baja-Media |
| `SHOW COLUMNS` en runtime | ⚠️ 17 ocurrencias que corren en cada request | 🟡 Baja |
| Archivos > 3.000 líneas | ⚠️ 3 archivos sin separación de capas | 🟡 Baja |

> **Fuera de alcance — no se toca:**
> Todo lo relacionado con sesiones, cookies, login, registro y persistencia del usuario logueado
> (`sesiones.php`, `gofast_auth_logic.php`, `gofast_auth.php`, manejo de `$_SESSION`).

---

## 2. Plan de 4 días

### Día 1 — Base de datos y seguridad SQL
*Objetivo: que las queries sean seguras y la BD esté indexada.*

| # | Tarea | Archivo(s) | Estimado |
|---|-------|-----------|----------|
| 1.1 | Añadir índices a `servicios_gofast` | `db/` | 1 h |
| 1.2 | Corregir queries sin `$wpdb->prepare()` | `gofast_solicitar_mensajero.php` y revisión de todos los demás | 2 h |
| 1.3 | Cambiar campo `whatsapp` de INT a VARCHAR(20) | `db/` + ajuste en PHP si aplica | 1 h |
| 1.4 | Documentar orden de migraciones en `db/MIGRACIONES.md` | `db/` | 30 min |

**Entregable del día 1:** BD más rápida, queries seguras, historial de migraciones documentado.

---

### Día 2 — Rendimiento PHP: eliminar N+1 y centralizar recargos
*Objetivo: reducir queries por carga de página en cotizaciones y pedidos.*

| # | Tarea | Archivo(s) | Estimado |
|---|-------|-----------|----------|
| 2.1 | Batch-load de barrios antes del loop de destinos | `gofast_solicitar_mensajero.php`, `gofast_admin_cotizar.php`, `gofast_mensajero_cotizar.php`, `gofast_confirmacion.php` | 3 h |
| 2.2 | Extraer lógica de recargos a función en `utils.php` | `utils.php` + los 6 archivos que la duplican | 3 h |

**Entregable del día 2:** Una cotización con 3 destinos pasa de ~15 queries a ~4 queries.

---

### Día 3 — CSS: limpiar !important y variables
*Objetivo: CSS mantenible y consistente con el sistema de diseño ya definido.*

| # | Tarea | Archivo(s) | Estimado |
|---|-------|-----------|----------|
| 3.1 | Auditar y eliminar `!important` innecesarios (meta: de 556 a <60) | `css.css` | 3 h |
| 3.2 | Reemplazar colores hardcodeados por variables CSS ya definidas (~40 ocurrencias) | `css.css` | 1.5 h |
| 3.3 | Verificar responsividad en componentes sin media queries (tabs, grids) | `css.css` | 1.5 h |

**Entregable del día 3:** CSS más corto, predecible y fácil de actualizar.

---

### Día 4 — Estilos inline, SHOW COLUMNS y pruebas finales
*Objetivo: separar presentación de lógica en los archivos más pesados, y limpiar queries de runtime.*

| # | Tarea | Archivo(s) | Estimado |
|---|-------|-----------|----------|
| 4.1 | Crear clases utilitarias en CSS y reemplazar `style=""` repetidos en finanzas y pedidos | `gofast_finanzas_admin.php`, `mis-pedidos.php`, `css.css` | 3 h |
| 4.2 | Reemplazar `SHOW COLUMNS` por flag en `wp_options` | `gofast_auth_logic.php` y otros | 1.5 h |
| 4.3 | Prueba end-to-end del flujo completo y revisión de logs | — | 1.5 h |

**Entregable del día 4:** Código más limpio, sin queries de introspección en runtime, sistema probado.

---

### Backlog (no urgente)
Tareas válidas pero que requieren más tiempo y análisis. Para un sprint posterior:

- [ ] Caché con `wp_transients` para barrios, tarifas y recargos (cambian poco)
- [ ] Separar `gofast_finanzas_admin.php` (5.947 líneas) en capas: datos / lógica / vista
- [ ] Evaluar si `gofast_finanzas_admin_dev.php` es duplicado y se puede eliminar
- [ ] Crear `db/schema_completo.sql` consolidado (CREATE final sin ALTERs históricos)

---

## 3. Estimado de cobro

### Horas por día

| Día | Horas estimadas |
|-----|----------------|
| Día 1 — BD y SQL | 4.5 h |
| Día 2 — Rendimiento PHP | 6 h |
| Día 3 — CSS | 6 h |
| Día 4 — Inline styles, SHOW COLUMNS, pruebas | 6 h |
| **Total** | **~22.5 horas** |

### Rangos de cobro

| Perfil | Tarifa/hora | Total estimado |
|--------|-------------|----------------|
| Desarrollador WordPress junior | $35.000 COP | $787.500 COP |
| Desarrollador PHP/WordPress mid | $60.000 COP | $1.350.000 COP |
| Desarrollador senior (sistema a medida) | $90.000 COP | $2.025.000 COP |

> El proyecto usa Code Snippets con lógica de negocio compleja (cotizaciones, recargos, finanzas,
> roles). No es mantenimiento de sitio genérico — aplica tarifa de desarrollador a medida.

### Recomendación de precio
**$1.500.000 – $1.800.000 COP** por el paquete de 4 días, incluyendo:
- Correcciones entregadas con documentación
- Archivos SQL listos para ejecutar en Hostinger
- Checklist de lo realizado por día
- 1 semana de soporte post-entrega para errores derivados de los cambios

---

## 4. Detalle técnico por tarea

### T1.1 — Índices en `servicios_gofast`
```sql
-- Guardar como: db/servicios_gofast_indexes.sql
ALTER TABLE servicios_gofast
  ADD INDEX idx_user_id         (user_id),
  ADD INDEX idx_mensajero_id    (mensajero_id),
  ADD INDEX idx_estado          (estado),
  ADD INDEX idx_fecha           (fecha),
  ADD INDEX idx_tracking_estado (tracking_estado);
```
> Sin estos, `WHERE user_id = X` y `WHERE estado = 'pendiente'` hacen full table scan.

---

### T1.2 — Queries sin `$wpdb->prepare()`
**`gofast_solicitar_mensajero.php` líneas ~101-102:**
```php
// ❌ Actual
$sector_destino = intval($wpdb->get_var("SELECT sector_id FROM barrios WHERE id = $destino"));
$nombre_destino = $wpdb->get_var("SELECT nombre FROM barrios WHERE id = $destino");

// ✅ Correcto
$sector_destino = intval($wpdb->get_var($wpdb->prepare(
    "SELECT sector_id FROM barrios WHERE id = %d", $destino
)));
$nombre_destino = $wpdb->get_var($wpdb->prepare(
    "SELECT nombre FROM barrios WHERE id = %d", $destino
));
```

---

### T1.3 — Campo `whatsapp` INT → VARCHAR
```sql
-- Guardar como: db/006_negocios_whatsapp_varchar.sql
ALTER TABLE negocios_gofast
  MODIFY COLUMN whatsapp VARCHAR(20);
```

---

### T2.1 — Eliminar patrón N+1 en cotizaciones
**Problema:** cada destino genera 3 queries. Con 3 destinos = 9 queries extra por página.
```php
// ✅ Cargar barrios en batch antes del loop
$ids = array_map('intval', array_column($destinos_array, 'barrio_id'));
$in  = implode(',', $ids);
$barrios_map = [];
foreach ($wpdb->get_results("SELECT id, nombre, sector_id FROM barrios WHERE id IN ($in)") as $b) {
    $barrios_map[$b->id] = $b;
}

// Dentro del foreach — solo memoria, cero queries adicionales
foreach ($destinos_array as $dest) {
    $barrio = $barrios_map[$dest['barrio_id']] ?? null;
}
```

---

### T2.2 — Centralizar lógica de recargos
Crear en `utils.php`:
```php
function gofast_cargar_datos_recargos() {
    global $wpdb;
    return [
        'fijos'     => $wpdb->get_results("SELECT * FROM recargos WHERE activo=1 AND tipo='fijo'"),
        'variables' => $wpdb->get_results("SELECT r.*, rr.desde, rr.hasta, rr.valor
                                           FROM recargos r
                                           JOIN recargos_rangos rr ON rr.recargo_id = r.id
                                           WHERE r.activo=1 AND r.tipo='por_valor'"),
    ];
}

function gofast_calcular_recargo_variable($precio, $recargos_data) {
    // Lógica unificada aquí — reemplaza el bloque duplicado en 6 archivos
}
```

---

### T3.1 — Reducir `!important` en CSS
- Conservar: reglas que ocultan el footer nativo de WordPress (~10 usos justificados)
- Eliminar: `!important` en botones, cards, badges, tipografía, paddings
- Meta: de **556 → menos de 60**

---

### T4.2 — Eliminar `SHOW COLUMNS` en runtime
```php
// ❌ Actual — corre en cada request
$col = $wpdb->get_results("SHOW COLUMNS FROM usuarios_gofast LIKE 'remember_token'");
if (!empty($col)) { ... }

// ✅ Guardar flag la primera vez, luego leer de options
if (get_option('gofast_schema_v2') !== '1') {
    $col = $wpdb->get_results("SHOW COLUMNS FROM usuarios_gofast LIKE 'remember_token'");
    if (!empty($col)) {
        update_option('gofast_schema_v2', '1');
    }
}
if (get_option('gofast_schema_v2') === '1') { ... }
```

---

## 5. Mantenimiento periódico

### Semanal
- [ ] Revisar logs de errores PHP en Hostinger (hPanel → Administrador de archivos → logs)
- [ ] Verificar que los snippets no lanzaron errores (`PHP fatal`, `wpdb errors`)
- [ ] Probar el flujo cotizar → solicitar → confirmar manualmente

### Mensual
- [ ] Backup de BD desde hPanel (Bases de datos → Exportar)
- [ ] Revisar `servicios_gofast`: archivar pedidos cancelados con más de 6 meses
- [ ] Verificar que los índices siguen activos (pueden borrarse en restauraciones)
- [ ] Actualizar `db/schema_completo.sql` si se hicieron ALTERs nuevos

### Por cada commit a producción
- [ ] Probar flujo completo en cuenta de prueba antes de subir
- [ ] Verificar responsividad en móvil (tablas de pedidos y formularios de cotización)
- [ ] Asegurarse de que no hay `var_dump()` ni `echo` de debug
- [ ] Queries nuevas usan `$wpdb->prepare()`
- [ ] Actualizar `RESUMEN_CAMBIOS_COMMIT.md`

### Trimestral
- [ ] Auditar usuarios inactivos en `usuarios_gofast` (+90 días sin login)
- [ ] Revisar tarifas y recargos desactualizados en BD
- [ ] Revisar CSS: eliminar selectores huérfanos de clases que ya no existen en PHP
- [ ] Confirmar que los índices de `servicios_gofast` siguen apareciendo en `SHOW INDEX FROM servicios_gofast`
- [ ] Evaluar si `gofast_finanzas_admin_dev.php` sigue en uso o se puede eliminar

---

## 6. Checklist de deploy

```
[ ] Backup de BD realizado (hPanel → Bases de datos → Exportar)
[ ] Snippet probado en cuenta de prueba
[ ] Sin var_dump(), print_r() ni echo de debug
[ ] Queries nuevas usan $wpdb->prepare()
[ ] $_POST/$_GET pasan por sanitize_text_field() o intval()
[ ] Flujo cotizar → solicitar → confirmar funciona correctamente
[ ] CSS nuevo sin !important innecesario
[ ] Commit creado con mensaje descriptivo
[ ] NO se modificaron: sesiones.php, gofast_auth_logic.php, gofast_auth.php
```

---

## Archivos de referencia

| Archivo | Propósito |
|---------|-----------|
| `CONFIGURACION_PAGINAS_GOFAST.txt` | Mapa de páginas, slugs y shortcodes |
| `RESUMEN_CAMBIOS_COMMIT.md` | Historial de cambios por release |
| `db/` | Esquemas SQL y migraciones |
| `code/utils.php` | Funciones helpers globales — aquí van las funciones centralizadas |
| `css.css` | Estilos globales del proyecto |

---

*Última actualización: 2026-03-27 · Actualizar tras completar cada día de trabajo.*

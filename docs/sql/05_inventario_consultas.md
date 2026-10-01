# 05 — Inventario de consultas SQL a tablas GoFast

Alcance: snippets activos en `code/*.php` (se excluyen los archivos cuyo nombre contiene `backup` o `_dev`).
Solo se inventarían tablas propias de GoFast (no `wp_*`). Motor: MariaDB 11.8.
Los números de línea corresponden a la línea donde empieza la cadena SQL o la llamada `$wpdb->…`.

> Nota: `code/gofast_mis_domicilios.php` es un archivo nuevo (sin commit) que apareció durante el análisis; se incluye al final de cada sección donde aplica.

Convenciones usadas:

- **SF** = `servicios_gofast`.
- **N** = número de usuarios con `rol = 'mensajero'` (incluye inactivos, porque la mayoría de reportes no filtra `activo`).
- **D** = días de la quincena transcurridos (1–16).
- "Recorre tabla" = no hay índice utilizable; MariaDB lee las ~78k filas de SF.

---

## 1. Resumen por archivo

Los conteos son **sitios de llamada** en el código (no ejecuciones). "Lecturas" = `get_var/get_row/get_results/get_col` que no son `SHOW`. Las columnas SHOW/ALTER indican sentencias DDL o de metadatos ejecutadas en tiempo de ejecución.

| Archivo | Líneas | Lecturas | INSERT | UPDATE | DELETE | SHOW COLUMNS | SHOW TABLES | ALTER | CREATE |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| gofast_finanzas_admin.php | 6041 | 70 | 8 | 7 | 8 | 0 | 0 | 0 | 0 |
| gofast_reportes_admin.php | 2905 | 44 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| gofast_reportes_financieros.php | 1799 | 42 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| gofast_admin_configuracion.php | 3307 | 37 | 10 | 12 | 6 | 0 | 0 | 0 | 0 |
| gofast_admin_cotizar.php | 1528 | 32 | 1 | 0 | 0 | 1 | 0 | 0 | 0 |
| mis-pedidos.php | 3606 | 29 | 0 | 2 | 1 | **4** | 0 | **1** | 0 |
| gofast_mensajero_cotizar.php | 1855 | 28 | 1 | 0 | 0 | 1 | 0 | 0 | 0 |
| gofast_compras.php | 1850 | 25 | 1 | 4 | 1 | 0 | 0 | 0 | 0 |
| gofast_solicitar_mensajero.php | 981 | 20 | 1 | 0 | 0 | 0 | 0 | 0 | 0 |
| gofast_transferencias.php | 1378 | 17 | 1 | 4 | 1 | 0 | 0 | 0 | 0 |
| gofast_dashboard_admin.php | 295 | 14 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| gofast_admin_cotizar_intermunicipal.php | 677 | 11 | 1 | 0 | 0 | 1 | 1 | 0 | 0 |
| gofast_admin_solicitar_intermunicipal.php | 717 | 10 | 2 | 0 | 0 | 2 | 1 | 0 | 0 |
| gofast_mensajero_cotizar_intermunicipal.php | 603 | 8 | 1 | 0 | 0 | 1 | 1 | 0 | 0 |
| gofast_admin_solicitudes_trabajo.php | 568 | 7 | 0 | 1 | 1 | 0 | 0 | 0 | 0 |
| gofast_solicitar_intermunicipal.php | 670 | 7 | 2 | 0 | 0 | 0 | 1 | 0 | 0 |
| gofast_confirmacion.php | 544 | 6 | 0 | 1 | 0 | 0 | 0 | 0 | 0 |
| gofast_cotizar.php | 1111 | 6 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| gofast_cotizar_intermunicipal.php | 414 | 6 | 0 | 0 | 0 | 0 | 1 | 0 | 0 |
| gofast_home.php | 668 | 6 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| gofast_recargos_admin.php | 1101 | 6 | 5 | 2 | 3 | **3** | 0 | 0 | 0 |
| gofast_usuarios_admin.php | 1541 | 6 | 1 | 3 | 1 | 1 | 0 | 0 | 0 |
| gofast_registro_negocio.php | 905 | 4 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| gofast_admin_listado_precios.php | 869 | 3 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| gofast_admin_negocios.php | 720 | 3 | 0 | 1 | 1 | 0 | 0 | 0 | 0 |
| gofast_recuperar_password.php | 491 | 3 | 0 | 3 | 0 | 0 | 0 | 0 | 0 |
| gofast_auth_logic.php | 382 | 2 | 1 | 2 | 0 | 3 | 0 | 0 | 0 |
| redireccion.php | 99 | 2 | 1 | 1 | 1 | 0 | 0 | 0 | 0 |
| gofast_menu.php | 224 | 1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| sesiones.php | 72 | 1 | 0 | 0 | 0 | 1 | 0 | 0 | 0 |
| gofast_trabaja_con_nosotros.php | 374 | 0 | 1 | 0 | 0 | 0 | 0 | 0 | 0 |
| gofast_mis_domicilios.php (nuevo) | 1074 | 5 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |

Sin SQL: `gofast-select2-loader.php`, `gofast_app_movil.php`, `gofast_auth.php`, `gofast_cookies_modal.php`, `gofast_footer.php`, `gofast_seo.php`, `gofast_sobre_nosotros.php`, `show_admin_bar.php`, `utils.php`.

Ningún archivo ejecuta `CREATE TABLE`.

### 1.1 Sentencias de metadatos / DDL en tiempo de ejecución

| Archivo:línea | Sentencia | Cuándo se ejecuta |
|---|---|---|
| sesiones.php:50 | `SHOW COLUMNS FROM usuarios_gofast LIKE 'remember_token'` | Hook `init` en **cada request** de un visitante sin sesión PHP que tiene cookie `gofast_token` |
| mis-pedidos.php:1588 | `SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'` | **Por cada fila** del listado (vista escritorio) con mensajero |
| mis-pedidos.php:1905 | ídem | **Por cada fila** del listado (vista móvil) con mensajero |
| mis-pedidos.php:594 / 602 | ídem | Al cambiar mensajero de un pedido (POST) |
| mis-pedidos.php:597 | `ALTER TABLE servicios_gofast ADD COLUMN asignado_por_user_id …` | Si el SHOW anterior no encuentra la columna (la columna ya existe) |
| gofast_recargos_admin.php:20, 23 | `SHOW COLUMNS FROM recargos WHERE Field='tipo'` (2 veces) | Cada carga de la página de recargos |
| gofast_recargos_admin.php:239 | ídem | Al crear recargo (POST) |
| gofast_cotizar_intermunicipal.php:26 | `SHOW TABLES LIKE 'destinos_intermunicipales'` | Cada render del shortcode |
| gofast_admin_cotizar_intermunicipal.php:74 | ídem | Cada render |
| gofast_mensajero_cotizar_intermunicipal.php:66 | ídem | Cada render |
| gofast_admin_solicitar_intermunicipal.php:92 | ídem | Cada render |
| gofast_solicitar_intermunicipal.php:57 | ídem | Cada render |
| gofast_admin_cotizar.php:271, gofast_mensajero_cotizar.php:263, gofast_admin_cotizar_intermunicipal.php:245, gofast_mensajero_cotizar_intermunicipal.php:226, gofast_admin_solicitar_intermunicipal.php:271 y 680 | `SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'` | Al crear servicio (POST) |
| gofast_auth_logic.php:26, 223, 276 | `SHOW COLUMNS FROM usuarios_gofast LIKE 'remember_token'` | Logout, registro, creación de cookie persistente |
| gofast_usuarios_admin.php:136 | ídem (`FROM $tabla`) | Al crear usuario desde admin |

Todas estas columnas/tablas ya existen en el esquema actual (`asignado_por_user_id`, `remember_token` con `idx_remember_token`, `destinos_intermunicipales`), por lo que las comprobaciones son redundantes.

---

## 2. `servicios_gofast` — consulta por consulta

Índices actuales: `PRIMARY(id)`, `idx_fecha_tracking(fecha, tracking_estado)`, `idx_mensajero_fecha(mensajero_id, fecha)`. Nuevo: `idx_user_fecha(user_id, fecha)`.

Columnas: **WHERE/ORDER** = columnas filtradas u ordenadas. **JSON** = usa `JSON_EXTRACT/JSON_LENGTH/JSON_CONTAINS/REGEXP` sobre `destinos`. **LIKE %** = LIKE con comodín inicial. **Función** = función sobre columna indexada que impide usar el índice.

### 2.1 Lecturas por clave primaria (sin problema)

| Archivo:línea | Consulta | Índice |
|---|---|---|
| mis-pedidos.php:246, 520, 823 | `SELECT * … WHERE id = %d` | PRIMARY |
| gofast_confirmacion.php:23 | `SELECT * FROM $table WHERE id = %d` | PRIMARY |
| mis-pedidos.php:226 / 479 / 610, gofast_confirmacion.php:42 | DELETE/UPDATE `WHERE id` | PRIMARY |

### 2.2 Listados de pedidos

| # | Archivo:línea | WHERE / ORDER BY | JSON | LIKE % | Función | Índice que usaría |
|---|---|---|---|---|---|---|
| L1 | mis-pedidos.php:779 (COUNT) y 794 (datos, `ORDER BY fecha DESC LIMIT 15`) — admin | `fecha` rango (por defecto hoy, L641-643) + opcionales `tracking_estado`, `mensajero_id` o `IS NULL OR = 0` (L703), `user_id` (filtro negocio L717), `asignado_por_user_id` + `EXISTS` usuarios (L725), `nombre_cliente/telefono_cliente` | Sí, opcional: `JSON_EXTRACT $.origen.barrio_id` (L733), `JSON_CONTAINS $.destinos` (L739), `$.tipo_servicio` (L745/748), `REGEXP` recargos (L756-766) | Sí, opcional: nombre/teléfono (L685), `direccion_origen` (L745/748), `destinos NOT LIKE` (L764-765) | No | `idx_fecha_tracking` (rango fecha); con filtro de mensajero, `idx_mensajero_fecha`; con filtro de negocio, `idx_user_fecha` (nuevo). El JSON/LIKE solo se evalúa sobre las filas del rango. |
| L2 | mis-pedidos.php:779/794 — mensajero | `mensajero_id = %d` (L659) + rango fecha | Igual que L1 si aplica | Opcional | No | `idx_mensajero_fecha` (óptimo, cubre también el ORDER BY) |
| L3 | mis-pedidos.php:779/794 — cliente | `user_id = %d` (L673) + rango fecha | No | Opcional | No | Hoy: `idx_fecha_tracking` (lee todos los pedidos del rango y filtra). **Con `idx_user_fecha`: óptimo** (igualdad + rango + ORDER BY fecha) |
| L4 | gofast_reportes_admin.php:468/483 (pedidos de hoy, `ORDER BY fecha DESC LIMIT`) y 446/453 (COUNT) | `DATE(fecha) = hoy` (L441) + opcional `mensajero_id`, `JSON_EXTRACT $.origen.negocio_id` (L436) | Opcional | No | **`DATE(fecha)`** | Admin: **ninguno, recorre tabla** (2 veces por carga). Mensajero: `idx_mensajero_fecha` solo por prefijo `mensajero_id` (lee todo su historial) |
| L5 | gofast_reportes_admin.php:1511 (export CSV, `LIMIT 10000`) | `$where` = rango fecha + filtros como en E1 | Opcional | Opcional | No | `idx_fecha_tracking` / `idx_mensajero_fecha` |
| L6 | gofast_mis_domicilios.php:155 (nuevo) | `user_id = %d AND fecha BETWEEN` `ORDER BY fecha DESC, id DESC` | No (se decodifica en PHP) | No | No | **`idx_user_fecha`** (diseñada para esta consulta) |

Nota: en mis-pedidos, si el usuario envía `desde`/`hasta` con formato inválido, L646-647 los vacían y **la consulta queda sin rango de fecha** (recorre tabla para admin).

### 2.3 Agregados de reportes (`gofast_reportes_admin.php`)

`$where` base (L67-118): `mensajero_id` (si es mensajero o admin filtra), `tracking_estado` opcional, `fecha >= / <=` (por defecto hoy, L62-64), LIKE `%x%` sobre `nombre_cliente/telefono_cliente` (L96), `JSON_EXTRACT(destinos,'$.tipo_servicio')` + `direccion_origen LIKE '%(Intermunicipal)%'` (L104/109), `JSON_EXTRACT $.origen.negocio_id` (L117).

| # | Línea | Qué calcula | WHERE | JSON | Función | Índice |
|---|---|---|---|---|---|---|
| E1 | 130/137 | `SUM(JSON_LENGTH(JSON_EXTRACT(destinos,'$.destinos')))` | `$where` | Sí (SELECT + opcional WHERE) | No | `idx_fecha_tracking` / `idx_mensajero_fecha` |
| E2 | 149/156 | `SUM(total)` no cancelados | `$where` + `tracking_estado != 'cancelado'` | Opcional | No | ídem |
| E3 | 215/222 | COUNT sin asignar | `mensajero_id IS NULL` (L212) + fecha + filtros | Opcional | No | `idx_mensajero_fecha` (NULL + rango fecha) |
| E4 | 599/607 y 620 | Top mensajeros: JOIN usuarios, `GROUP BY u.id` | `$where` + `tracking_estado='entregado'` | Opcional | No | `idx_fecha_tracking` |
| E5 | 680 | Pedidos por día, últimos 30 días | `fecha` rango 30 días (L659) + mensajero opcional; `GROUP BY DATE(fecha)` | Sí (SELECT) | `DATE()` solo en SELECT/GROUP (no impide índice) | `idx_fecha_tracking` / `idx_mensajero_fecha` |
| E6 | 830 | Ingresos quincena (global) | tracking + fecha quincena | No | No | `idx_fecha_tracking` |
| E7 | 943 (dentro de `foreach` L936) | Comisión quincena por mensajero | `mensajero_id` + tracking + fecha | No | No | `idx_mensajero_fecha` |
| E8 | 1048 (dentro de `foreach` L936 → `foreach` días L1042) | Ingresos del día por mensajero | `mensajero_id` + tracking + **`DATE(fecha) = %s`** | No | **Sí** | `idx_mensajero_fecha` **solo prefijo** (lee todo el historial del mensajero en cada día) |
| E9 | 1241 (dentro de `foreach` L936, condicional) | Ingresos de hoy por mensajero | ídem E8 | No | **Sí** | ídem E8 |

### 2.4 `gofast_reportes_financieros.php`

| # | Línea | Qué calcula | WHERE | JSON / LIKE % | Función | Índice |
|---|---|---|---|---|---|---|
| R1 | 79, 316 | Ingresos quincena | tracking + fecha | No | No | `idx_fecha_tracking` |
| R2 | 178, 233 (dentro de `foreach` L172) | Ingresos / comisión por mensajero | `mensajero_id` + tracking + fecha | No | No | `idx_mensajero_fecha` |
| R3 | 427 y 1219/1228 | Ingresos quincena anterior / q1 / q2 (con subconsulta a compras) | tracking + fecha | No | No | `idx_fecha_tracking` |
| R4 | 449 (dentro de `foreach` días L445, 15-16 iteraciones) | Ingresos del día | tracking + **`DATE(fecha) = %s`** | No | **Sí** | **Ninguno, recorre tabla** ×15-16 por carga |
| R5 | 485, 495, 508 (dentro de `foreach` L479) | Entregados / cancelados / comisión por mensajero | `mensajero_id` + `tracking_estado =` + fecha | No | No | `idx_mensajero_fecha` |
| R6 | 575 | Servicios "normales" | tracking + fecha + `JSON_EXTRACT $.tipo_servicio` + `direccion_origen NOT LIKE '%(Intermunicipal)%'` | JSON + LIKE % | No | `idx_fecha_tracking` (JSON/LIKE solo en el rango) |
| R7 | 586 | Servicios intermunicipales | ídem con `= 'intermunicipal' OR LIKE` | JSON + LIKE % | No | `idx_fecha_tracking` |
| R8 | 597 | Pedidos por negocio: `LEFT JOIN negocios ON JSON_EXTRACT(...) = n.id`, `GROUP BY` | tracking + fecha + `JSON_EXTRACT IS NOT NULL` | JSON | No | `idx_fecha_tracking` (negocios tiene 28 filas) |
| R9 | 615, 624 | Cancelaciones (COUNT / SUM) | `tracking_estado='cancelado'` + fecha | No | No | `idx_fecha_tracking` |
| R10 | 633 | Total pedidos | fecha | No | No | `idx_fecha_tracking` (cubre) |
| R11 | 644 | Cancelaciones por mensajero, JOIN usuarios | cancelado + fecha | No | No | `idx_fecha_tracking` |

Observación de corrección (no de rendimiento): en MariaDB `JSON_EXTRACT` devuelve el valor con comillas (`"intermunicipal"`). `gofast_reportes_admin.php:104/109` compara correctamente contra `'"intermunicipal"'`, pero `gofast_reportes_financieros.php:578/588` y `mis-pedidos.php:745/748` comparan contra `'intermunicipal'` sin comillas, así que esa rama nunca coincide y solo funciona el `LIKE '%(Intermunicipal)%'` de respaldo. Conviene usar `JSON_VALUE` o `JSON_UNQUOTE`.

### 2.5 `gofast_finanzas_admin.php`

| # | Línea | Qué calcula | WHERE | JSON | Función | Índice |
|---|---|---|---|---|---|---|
| F1 | 838 (fallback 839) | Ingresos del rango | tracking + fecha (fallback **sin fecha** si ambas vacías; en la práctica nunca, hay valores por defecto) | No | No | `idx_fecha_tracking` |
| F2 | 1003 | Ingresos **acumulados hasta** fecha_hasta (cuando desde == hasta) | tracking + `fecha <= %s` | No | No | Rango abierto desde el inicio: **en la práctica recorre tabla** |
| F3 | 1110 (solo tab ingresos) | Ingresos por día, `GROUP BY DATE(fecha)` | tracking + fecha rango | No | Solo en SELECT/GROUP | `idx_fecha_tracking` |
| F4 | 1649 (batch) | Ingresos + `SUM(JSON_LENGTH(...))` por mensajero | `mensajero_id IN (…)` + tracking + fecha rango (interpolado) | Sí (SELECT, con `JSON_VALID`) | No | `idx_mensajero_fecha` |
| F5 | 1718 (batch) | Histórico por mensajero | `mensajero_id IN (…)` + tracking + **`DATE(fecha) <= '…'`** (L1717) | No | **Sí** | `idx_mensajero_fecha` solo prefijo → lee **todo el historial de todos los mensajeros** (≈ tabla completa). **El resultado solo alimenta datos de depuración**: `$_map_hist_*` → L1954-1967 → `$debug_valores_mensajero` (L1970) → `'debug_valores'` (L2316), que nunca se muestra. Es código muerto que se ejecuta en cada carga |
| F6 | 1792 (batch) | Ingresos por mensajero × día | `mensajero_id IN (…)` + tracking + fecha rango; `GROUP BY mensajero_id, DATE(fecha)` | No | Solo en GROUP | `idx_mensajero_fecha` |
| F7 | 2199 (dentro de `foreach` L1850, condicional) | Ingresos de hoy por mensajero | `mensajero_id` + tracking + **`DATE(fecha) = %s`** | No | **Sí** | `idx_mensajero_fecha` solo prefijo |
| F8 | 2416 (fallback 2417) | Pedidos sin asignar | tracking + `mensajero_id IS NULL` + fecha | No | No | `idx_mensajero_fecha` (NULL + rango) |

El bloque batch (L1633-1845) y el historial de pagos (L2445-2529) se ejecutan en **cada carga** de finanzas, sin importar el tab activo.

Los `WHERE` construidos en L1536-1595 (`$where_saldos`, `$where_servicios_mensajero`, `$where_compras_mensajero`) y L1860-1925 (`$where_destinos`, `$where_compras`, `$where_transf`, `$where_descuentos_mensajero`) **nunca se ejecutan** (restos de la versión N+1 anterior).

### 2.6 Dashboard y home

| # | Archivo:línea | Qué calcula | WHERE | JSON | Función | Índice |
|---|---|---|---|---|---|---|
| D1 | gofast_dashboard_admin.php:41 | Total destinos histórico | **ninguno** | `SUM(JSON_LENGTH(JSON_EXTRACT(destinos)))` | — | **Ninguno, recorre tabla** y parsea 78k JSON |
| D2 | gofast_dashboard_admin.php:53 | Ingresos históricos | `tracking_estado != 'cancelado'` | No | — | **Ninguno, recorre tabla** |
| D3 | gofast_dashboard_admin.php:60 | Destinos de hoy | **`DATE(fecha) = %s`** + tracking | Sí | **Sí** | **Ninguno, recorre tabla** |
| D4 | gofast_dashboard_admin.php:70 | Ingresos de hoy | **`DATE(fecha) = %s`** + tracking | No | **Sí** | **Ninguno, recorre tabla** |
| D5 | gofast_dashboard_admin.php:97 | Ingresos del mes | fecha rango + tracking | No | No | `idx_fecha_tracking` |
| H1 | gofast_home.php:86-109 | Top mensajeros del día: subconsulta correlacionada por cada mensajero activo | `s.mensajero_id = u.id AND **DATE(s.fecha) = %s**` + tracking | No | **Sí** | `idx_mensajero_fecha` solo prefijo → lee todo el historial de cada mensajero ≈ tabla completa. Se ejecuta en **cada carga del home de mensajeros y admins** |
| H2 | gofast_home.php:118 | = D1 (admin) | ninguno | Sí | — | **Recorre tabla** |
| H3 | gofast_home.php:125 | = D2 (admin) | tracking | No | — | **Recorre tabla** |

### 2.7 Otras

| # | Archivo:línea | WHERE | Problema | Índice |
|---|---|---|---|---|
| O1 | gofast_solicitar_mensajero.php:80 | `direccion_origen <> '' AND (user_id = %d OR REPLACE(REPLACE(REPLACE(telefono_cliente,…))) = %s)` `ORDER BY id DESC LIMIT 10` + `DISTINCT` | `OR` con una función sobre columna; **sin rango de fecha** | **Ninguno, recorre tabla**. Se ejecuta en cada cotización de un cliente logueado que no usa negocio. `idx_user_fecha` no ayuda mientras exista el `OR` |

### 2.8 Consultas sobre `servicios_gofast` sin rango de fecha utilizable

1. gofast_dashboard_admin.php:41, 53, 60, 70 (D1-D4).
2. gofast_home.php:86 (H1, cada carga de mensajero/admin), 118, 125.
3. gofast_reportes_financieros.php:449 (R4, ×15-16 por carga).
4. gofast_reportes_admin.php:446/468/483 (L4) y 1048/1241 (E8/E9).
5. gofast_finanzas_admin.php:1003 (F2, rango abierto), 1718 (F5), 2199 (F7), y los fallbacks 839/2417.
6. gofast_solicitar_mensajero.php:80 (O1).
7. mis-pedidos.php:779/794 solo si llegan fechas con formato inválido.

---

## 3. Patrones N+1 (consultas dentro de bucles)

Ordenados por impacto aproximado en consultas por carga de página.

| Prioridad | Archivo:línea del bucle | Consultas dentro (archivo:línea) | Consultas por carga (aprox.) |
|---|---|---|---|
| 🔴 1 | gofast_reportes_admin.php:936 `foreach ($mensajeros_saldos)` → **anidado** L1042 `foreach ($periodo as $dia)` | Por mensajero: 943, 952, 963, 975, 985, 1026. Por mensajero × día: **1048 (SF con DATE), 1056 (compras con DATE), 1067 (transferencias), 1081 (descuentos)**. Condicional por mensajero: 1241, 1249, 1261, 1274 | Admin sin filtro: **N × (6 + 4·D + 4)** → con N=40 y D=15 ≈ 2.800 consultas. Mensajero: ~70 |
| 🔴 2 | gofast_reportes_financieros.php:172 `foreach ($mensajeros)` | 176 (SF), 185 (compras), 200 (transf), 211 (desc), 220 (pagos), 231 (SF), 240 (compras), 250 (transf + EXISTS pagos), 274 (desc), 284 (pagos) | **10·N** |
| 🔴 3 | gofast_reportes_financieros.php:479 `foreach ($mensajeros)` | 483, 493, 507 (SF, la última con subconsulta a compras) | **3·N** |
| 🔴 4 | gofast_reportes_financieros.php:445 `foreach ($periodo_dias)` | 447 (SF `DATE(fecha)`, recorre tabla), 454 (compras `DATE(fecha_creacion)`) | 2 × 16 = 32 (16 recorridos completos de SF) |
| 🔴 5 | gofast_solicitar_mensajero.php:707, gofast_admin_cotizar.php:1313, gofast_mensajero_cotizar.php:1625 — `array_map` sobre `$barrios_all` | `SELECT sector_id FROM barrios WHERE id = %d` (708 / 1314 / 1626) | **391 consultas** por render de la pantalla de resultado de cotización (la consulta de L549/1087/1401 ya trae los barrios; basta pedir también `sector_id`) |
| 🟠 6 | mis-pedidos.php:1461 `foreach ($pedidos)` (escritorio) y 1781 (móvil) — 15 filas cada una | Tarifa por destino (1488 / 1807, dentro de `foreach` destinos 1474 / 1793), nombre mensajero (1579 / 1898), **SHOW COLUMNS (1588 / 1905)**, nombre asignador (1596 / 1912), rol asignador (1602 / 1918), teléfono mensajero (1741 / 2039) | ~6 + nº destinos por fila, ×2 vistas ≈ **180-250 por página** |
| 🟠 7 | gofast_finanzas_admin.php:1850 `foreach ($mensajeros_saldos)` | Condicional (si hoy no está en el desglose): 2197 (SF), 2205 (compras), 2217 (transf), 2230 (desc) | hasta 4·N |
| 🟡 8 | Listas de negocios con nombre de barrio: gofast_admin_cotizar.php:483→484; gofast_admin_cotizar_intermunicipal.php:181→184, 381→382, 552→555; gofast_cotizar.php:137→140/156; gofast_cotizar_intermunicipal.php:131→134, 227→228; gofast_mensajero_cotizar.php:473→474; gofast_mensajero_cotizar_intermunicipal.php:163→166, 344→345, 497→500; gofast_registro_negocio.php:206→210 | `SELECT nombre FROM barrios WHERE id = %d` por negocio | hasta 28 por render (resolver con `JOIN barrios`) |
| 🟡 9 | gofast_reportes_admin.php:1952 `foreach ($pedidos_hoy)` | 1969 nombre del mensajero | ≤15 (resolver con `LEFT JOIN usuarios_gofast`) |
| 🟡 10 | Bucles por destino al cotizar/crear (N = nº destinos, normalmente 1-5): gofast_solicitar_mensajero.php:171→173/174/176 y 411→413/414; gofast_admin_cotizar.php:205→206/207/209/225 y 1037→1038/1039/1041/1056; gofast_mensajero_cotizar.php:197→198/199/201/217 y 1363→1364/1365/1367; gofast_confirmacion.php:96→109 y 225→254; mis-pedidos.php:94→102 y 308→311/315 | barrio (sector y nombre por separado) + tarifa | 3-4 por destino |
| ⚪ 11 | Acciones administrativas masivas (POST, poco frecuentes): gofast_admin_configuracion.php:50 (3-4 SELECT + UPDATE/INSERT por tarifa), 440, 531; gofast_recargos_admin.php:101/166/256 (`while` de unicidad de slug), 193, 308, 319, 331, 361, 387, 397; gofast_usuarios_admin.php:315, 355, 372 | Varias | Aceptable por ser acciones puntuales |

---

## 4. Tablas de finanzas y reportes

### 4.1 Índices que se planea eliminar y su reemplazo

| Tabla | Índice a eliminar | Índice que lo sustituye (misma columna líder) |
|---|---|---|
| compras_gofast | `fecha_creacion` | `idx_fecha_estado(fecha_creacion, estado)` |
| compras_gofast | `mensajero_id` | `idx_mensajero_estado(mensajero_id, estado)` |
| transferencias_gofast | `mensajero_id` | `idx_mensaj_est_fecha(mensajero_id, estado, fecha_creacion)` |
| transferencias_gofast | `estado` | `idx_estado_fecha_tipo(estado, fecha_creacion, tipo)` |
| pagos_mensajeros_gofast | `idx_mensajero` | `idx_mensaj_total_tipo(...)` y `idx_mensaj_fecha_tipo(mensajero_id, fecha, tipo_pago)` |
| egresos / transferencias_salidas / vales_empresa / vales_personal | `idx_descripcion` | No hace falta: solo se busca con `LIKE '%x%'` |
| destinos_intermunicipales | `activo` (plan 04) | 12 filas; irrelevante |

Si alguna de estas columnas tiene FOREIGN KEY, MariaDB acepta quitar el índice simple porque el compuesto tiene la misma columna como prefijo.

### 4.2 Consultas y cobertura (tras eliminar los índices anteriores)

**compras_gofast** (728 filas)

| Archivo:línea | WHERE principal | Índice tras el cambio |
|---|---|---|
| reportes_financieros 88, 325; reportes_admin 266/282 (admin), 839; dashboard 106; finanzas 856, 1133; reportes_financieros 428/509/1221/1230 (subconsultas) | `estado != 'cancelada'` + `fecha_creacion` rango | `idx_fecha_estado` ✅ |
| reportes_financieros 187, 242; reportes_admin 266/282 (mensajero), 952; finanzas 1669, 1806 (`mensajero_id IN` + rango) | `mensajero_id` + estado + rango `fecha_creacion` | `idx_mensajero_estado` (prefijo mensajero, `estado !=` filtra en índice) ✅ |
| finanzas 1011 | `fecha_creacion <= X` (acumulado) | `idx_fecha_estado` (rango abierto; tabla pequeña) ✅ |
| finanzas 1733 (hist., solo debug); 2207; reportes_admin 1056, 1249; home 97 (correlacionada) | `mensajero_id` + **`DATE(fecha_creacion)`** | `idx_mensajero_estado` por prefijo ✅ (igual que hoy) |
| dashboard 78; reportes_financieros 456; reportes_admin 524 (admin) | **`DATE(fecha_creacion) = X`** | Ninguno, hoy tampoco (tabla pequeña) |
| compras 358 | `mensajero_id` `GROUP BY barrio_id` | `idx_mensajero_estado` ✅ |
| compras 411/415 + 405 + 431 (listado) | `DATE(c.fecha_creacion)` rango + mensajero/estado opcional, `ORDER BY c.fecha_creacion DESC` | Con mensajero: `idx_mensajero_estado`; con estado: `estado`; admin sin filtros: recorre (728 filas) |
| compras 465; 475-480 | `mensajero_id =` / `estado =` | `idx_mensajero_estado` / `estado` ✅ |
| compras 120, 179, 227, 262, 294 | `id =` | PRIMARY |

**transferencias_gofast** (3.299 filas)

| Archivo:línea | WHERE principal | Índice tras el cambio |
|---|---|---|
| reportes_financieros 122, 341; finanzas 937, 1432 (tab entradas); reportes_admin 870 | `estado='aprobada'` + rango `fecha_creacion` + tipo (+ EXISTS pagos) | `idx_estado_fecha_tipo` ✅; el EXISTS usa `idx_mensaj_total_tipo` |
| finanzas 1047 | `estado='aprobada' AND DATE(t.fecha_creacion) <= X` | `idx_estado_fecha_tipo` por prefijo `estado` ✅ |
| reportes_financieros 202, 252; reportes_admin 340 (con mensajero), 963, 1067, 1261; finanzas 1687, 1820, 2219 | `mensajero_id` + `estado='aprobada'` + rango + tipo | `idx_mensaj_est_fecha` ✅ (óptimo) |
| finanzas 1748 (hist., solo debug) | `mensajero_id IN` + estado + `DATE(fecha_creacion) <=` | `idx_mensaj_est_fecha` por prefijo (mensajero, estado) ✅ |
| finanzas 166, 180, 223, 274 | `mensajero_id` + `tipo='pago'` + `valor` + `observaciones LIKE '%…%'` | `idx_mensaj_est_fecha` por prefijo `mensajero_id` ✅ |
| transferencias 339/343 + 349/353 + 369 + 375 (listado) | `mensajero_id` y/o `DATE(t.fecha_creacion)` y/o `estado` y/o `tipo`, `ORDER BY fecha_creacion DESC` | mensajero → `idx_mensaj_est_fecha`; estado (por defecto admin = 'pendiente') → `idx_estado_fecha_tipo` ✅ |
| transferencias 407 | `mensajero_id =` | `idx_mensaj_est_fecha` ✅ |
| transferencias 417-421 | `estado =` | `idx_estado_fecha_tipo` ✅ |
| transferencias 422-423 | `tipo =` | `idx_tipo` ✅ |
| transferencias 404, 416 | COUNT sin WHERE | cualquier índice |
| transferencias 192; finanzas 194/235/286 | `id =` | PRIMARY |

**pagos_mensajeros_gofast** (5.203 filas)

| Archivo:línea | WHERE principal | Índice tras el cambio |
|---|---|---|
| reportes_financieros 222, 286; reportes_admin 985, 1026 | `mensajero_id` + `tipo_pago` + rango `fecha` | `idx_mensaj_fecha_tipo` ✅ |
| finanzas 1633, 1778 (`mensajero_id IN`) | ídem | `idx_mensaj_fecha_tipo` ✅ |
| EXISTS en reportes_financieros 130/262/349, finanzas 945/1056, reportes_admin 878 | `mensajero_id` + `total_a_pagar` + `tipo_pago` | `idx_mensaj_total_tipo` ✅ (cubre) |
| transferencias 234 | `mensajero_id` + `tipo_pago` + `total_a_pagar` + `fecha` | `idx_mensaj_total_tipo` ✅ |
| reportes_financieros 374, 388; finanzas 2329; reportes_admin 1475 | `tipo_pago` + rango `fecha` (sin mensajero) | `idx_fecha` ✅ |
| reportes_admin 386/402 | mensajero opcional + rango + tipo | `idx_mensaj_fecha_tipo` o `idx_fecha` ✅ |
| finanzas 2445, 2457, 2474, 2518 (historial / resumen) | `tipo_pago IN` + mensajero opcional + rango `fecha`, `ORDER BY fecha DESC, fecha_pago DESC LIMIT 15` | `idx_fecha` o `idx_mensaj_fecha_tipo` ✅ |
| finanzas 143, 263 | `id =` | PRIMARY |

**descuentos_mensajeros_gofast** (107 filas; no cambia)

| Archivo:línea | WHERE | Índice |
|---|---|---|
| reportes_financieros 155, 365, 538; reportes_admin 896, 1395; finanzas 994, 1078 | rango `fecha` | `idx_fecha` |
| reportes_financieros 213, 276; reportes_admin 975, 1081, 1274; finanzas 1704, 1764, 1835, 2232 | `mensajero_id` + `fecha` | `idx_mensajero` (o `idx_fecha`) |
| finanzas 1496 | rango fecha + mensajero + `descripcion LIKE '%x%'` | `idx_fecha` / `idx_mensajero` |

Detalle: `reportes_financieros.php:541` usa la columna `d.motivo`, mientras que `finanzas_admin.php:1488` filtra por `d.descripcion`; verificar cuál existe realmente.

**egresos_gofast, vales_empresa_gofast, vales_personal_gofast, transferencias_salidas_gofast**

| Archivo:línea | WHERE | Índice tras quitar `idx_descripcion` |
|---|---|---|
| reportes_financieros 100, 109, 146, 566; finanzas 877, 896, 915, 975 y 1022, 1030, 1038, 1070 | rango o `<=` sobre `fecha` | `idx_fecha` ✅ |
| finanzas 1204+1209, 1246+1251, 1299+1313, 1337, 1374+1379 (listados) | rango `fecha` + `descripcion LIKE '%x%'` (+ `persona_id`) `ORDER BY fecha DESC, id DESC` | `idx_fecha` (o `idx_persona`); el LIKE con comodín inicial **nunca** podía usar `idx_descripcion` ✅ |
| reportes_financieros 554 | egresos: rango `fecha` `GROUP BY descripcion` | `idx_fecha` + ordenamiento en memoria de pocas filas ✅ |

**Conclusión de la sección 4: ninguna consulta pierde cobertura** con las eliminaciones planeadas. Cada columna que queda sin índice simple sigue siendo la primera columna de un índice compuesto, e `idx_descripcion` no era utilizable por ninguna consulta.

---

## 5. Inserciones y manejo de fechas

### 5.1 Cómo se insertan los datos

- **servicios_gofast** (9 sitios): siempre `$wpdb->insert('servicios_gofast', $array)` **sin arreglo de formatos**, así que todo se envía como `%s` (escapado, seguro; MariaDB convierte `total` a entero). `fecha` se asigna explícitamente en PHP:
  - `gofast_date_mysql()` → gofast_solicitar_mensajero.php:465, gofast_solicitar_intermunicipal.php:230 y 652, gofast_admin_solicitar_intermunicipal.php:283 y 692.
  - `gofast_current_time('mysql')` → gofast_mensajero_cotizar.php:314, gofast_mensajero_cotizar_intermunicipal.php:238.
  - Admin con fecha manual: `$_POST['fecha_servicio'] . ' ' . date('H:i:s')` → gofast_admin_cotizar.php:303, gofast_admin_cotizar_intermunicipal.php:251 (por defecto `gofast_current_time('mysql')`, L300/248).
  - Sitios del insert: gofast_admin_cotizar.php:325, gofast_admin_cotizar_intermunicipal.php:273, gofast_admin_solicitar_intermunicipal.php:291 y 700, gofast_mensajero_cotizar.php:323, gofast_mensajero_cotizar_intermunicipal.php:247, gofast_solicitar_intermunicipal.php:220 y 642, gofast_solicitar_mensajero.php:455.
- **Finanzas** (`pagos`, `transferencias`, `egresos`, `vales_*`, `transferencias_salidas`, `descuentos`, `compras`): `$wpdb->insert/update` **con arreglo de formatos** (`%d/%f/%s`). `fecha_creacion`, `fecha_actualizacion` y `fecha_pago` usan `gofast_date_mysql()` (finanzas_admin 79, 97, 198, 215, 323, 356, 413, 446, 505, 540, 597, 630, 689; compras 97, 154, 203, 240, 272; transferencias 56, 132, 158, 204). El campo `fecha` de negocio (egresos, vales, etc.) viene del formulario (`$_POST['fecha']`, formato Y-m-d).
  - transferencias.php:62 y 183: `$_POST['fecha_creacion'] . ' ' . date('H:i:s')`.
- **Otros sin formato**: gofast_confirmacion.php:42 (update user_id), mis-pedidos.php:610 (update estado/mensajero), redireccion.php:64 y 79 (negocios). El resto de insert/update (admin_configuracion, recargos, usuarios, auth, recuperar_password, solicitudes_trabajo, trabaja_con_nosotros, admin_negocios) sí pasa formatos.
- `created_at` / `updated_at` / `fecha_registro` también se llenan desde PHP con `gofast_current_time('mysql')` o `gofast_date_mysql()` (redireccion 73/90, usuarios_admin 132, auth_logic 219, admin_configuracion 855/890, admin_negocios 78, admin_solicitudes_trabajo 316, trabaja_con_nosotros 108).

`gofast_date_mysql()`, `gofast_current_time()` y `gofast_date_today()` (utils.php:22-82) crean un `DateTime` con `America/Bogota` explícito, así que son correctos aunque el servidor esté en UTC.

### 5.2 Funciones de fecha del servidor dentro del SQL

Búsqueda (sin distinguir mayúsculas) de `NOW()`, `CURDATE`, `CURRENT_DATE`, `CURRENT_TIMESTAMP`, `UNIX_TIMESTAMP`, `SYSDATE`, `DATE_SUB`, `DATE_ADD`, `INTERVAL n` en todos los snippets activos: **0 coincidencias**. Ninguna consulta depende del reloj UTC de MariaDB; todas las fechas "hoy", "quincena" y "mes" se calculan en PHP con zona Colombia y se pasan como parámetro.

Riesgos residuales relacionados con la zona horaria:

1. `date('H:i:s')` en gofast_admin_cotizar.php:303, gofast_admin_cotizar_intermunicipal.php:251 y gofast_transferencias.php:62, 183, y `date('Y-m-d H:i:s', strtotime('+1 hour'))` en gofast_recuperar_password.php:119 (comparado luego con `gofast_current_time('mysql')` en L44/250), dependen de que `utils.php:11` haya ejecutado `date_default_timezone_set('America/Bogota')` antes. Si ese snippet se desactiva o carga después, estas horas quedarían en UTC (+5 h). Conviene reemplazarlas por `gofast_date('H:i:s')` / `gofast_current_time(...)`.
2. `utils.php:11` cambia la zona horaria por defecto de PHP para todo WordPress. WordPress asume UTC internamente; es una fuente conocida de desfases en `wp_cron` y en funciones de fecha del core.
3. Si alguna tabla tiene columnas con `DEFAULT CURRENT_TIMESTAMP` y un INSERT las omite, se guardarían en UTC. En el código revisado todos los INSERT asignan su fecha explícitamente, pero conviene revisarlo en el DDL.

---

## 6. Consultas sin `prepare` que interpolan variables

| Riesgo | Archivo:línea | Variable interpolada | Origen / evaluación |
|---|---|---|---|
| 🔴 **Alto** | gofast_finanzas_admin.php:1638, 1647-1648 (→1649), 1667-1668 (→1669), 1685-1686 (→1687), 1702-1703 (→1704), 1717 (→1718), 1732 (→1733), 1747 (→1748), 1763 (→1764), 1777 (→1778), 1839 (→1835) | `$fecha_desde`, `$fecha_hasta`, `$_fd`, `$_fh` dentro de comillas simples | Vienen de `$_GET['fecha_desde'/'fecha_hasta']` con `sanitize_text_field` (L764-765), que **no escapa comillas**, y **sin validar formato** (a diferencia de reportes_admin L58-59 y mis-pedidos L646-647). **Inyección SQL** por GET (página solo para admin, pero explotable con un enlace enviado al admin). Solución: validar con `preg_match('/^\d{4}-\d{2}-\d{2}$/')` y usar `prepare` |
| 🟢 Bajo | gofast_finanzas_admin.php:1636, 1656, 1674, 1690, 1707, 1721, 1736, 1751, 1767, 1781, 1795, 1809, 1823, 1838 | `$_ids_in` | `implode` de `(int)` (L1627-1628): seguro |
| 🟢 Bajo | gofast_finanzas_admin.php:1618 | `$where_mensajeros` | Literal fijo (`rol = 'mensajero'`) cuando no hay params |
| 🟢 Bajo | gofast_solicitar_mensajero.php:48-51 | `$user_id` | `intval($_SESSION[...])` (L45) |
| 🟢 Bajo | gofast_solicitar_mensajero.php:101, 102 | `$origen` | `intval($_POST['origen'])` (L17) |
| 🟢 Bajo | gofast_solicitar_mensajero.php:173, 174 | `$destino` | `array_map('intval', …)` (L18) |
| 🟢 Bajo | gofast_solicitar_mensajero.php:413, 414 | `$dest_id` | ídem L18 |
| 🟢 Bajo | gofast_registro_negocio.php:210 | `{$n->barrio_id}` | Valor leído de la BD |
| 🟢 Bajo | gofast_recargos_admin.php:472-475 | `$ids` | `array_map('intval', …)` (L471) |
| 🟢 Bajo | gofast_recargos_admin.php:20, 23, 239, 448 | `{$tabla_recargos}`, `{$tabla_rangos}` | Constantes |
| 🟢 Bajo | gofast_usuarios_admin.php:136 y consultas con `$tabla` | `$tabla` | Constante `'usuarios_gofast'` |
| 🟢 Bajo | gofast_confirmacion.php:23 | `$table` | Constante (el `id` sí va con `%d`) |

Patrones seguros verificados: los `WHERE` dinámicos de reportes_admin, mis-pedidos, compras, transferencias, usuarios_admin, admin_configuracion, admin_negocios, admin_solicitudes_trabajo y finanzas (tabs) concatenan solo fragmentos fijos con placeholders y pasan los valores por `prepare`. Los LIMIT/OFFSET se castean con `(int)` o usan `%d`. Los LIKE usan `$wpdb->esc_like`. Los `str_replace(... '1=1' ...)` de finanzas_admin L957 y reportes_admin L890 solo se usan cuando no hay parámetros.

---

## 7. Conclusión: recomendaciones priorizadas

### Prioridad 1 — seguridad y mayor carga

1. **Corregir la inyección SQL en finanzas** (gofast_finanzas_admin.php:1638-1839): validar `fecha_desde/fecha_hasta` con regex tras L765 y pasar los valores por `prepare`.
2. **Eliminar el N+1 anidado de reportes_admin** (L936 → L1042): N × D × 4 consultas. Reusar el patrón batch de finanzas (L1633-1845): una consulta por tabla con `mensajero_id IN (…) AND fecha BETWEEN … GROUP BY mensajero_id, DATE(fecha)`.
3. **Convertir reportes_financieros a batch**: los bucles L172 (10·N), L479 (3·N) y L445 (16 recorridos completos de SF) pasan a 1-2 consultas `GROUP BY` cada uno.
4. **Home (H1, gofast_home.php:86)**: cambiar `DATE(s.fecha) = %s` por `s.fecha >= 'hoy 00:00:00' AND s.fecha <= 'hoy 23:59:59'` (y lo mismo en compras L100). Con eso usa `idx_mensajero_fecha` por rango en lugar de leer todo el historial. Se ejecuta en cada carga de mensajeros y admins.
5. **Mapa de barrios**: gofast_solicitar_mensajero.php:708, gofast_admin_cotizar.php:1314, gofast_mensajero_cotizar.php:1626 hacen **391 consultas por render**. Traer `sector_id` en la consulta `$barrios_all`.

### Prioridad 2 — índices

6. **Crear `idx_user_fecha(user_id, fecha)`**: beneficia mis-pedidos para clientes (L673 + L691-695, cubre el `ORDER BY fecha DESC`), el filtro por negocio de admin (L717) y `gofast_mis_domicilios.php:155`.
7. **Eliminar los índices confirmados como redundantes** (sección 4): `compras_gofast.fecha_creacion`, `compras_gofast.mensajero_id`, `transferencias_gofast.mensajero_id`, `transferencias_gofast.estado`, `pagos_mensajeros_gofast.idx_mensajero`, `idx_descripcion` en egresos, transferencias_salidas, vales_empresa y vales_personal. Ninguna consulta empeora.
8. No hacen falta más índices en SF si se corrigen las consultas con `DATE(fecha)`. Todas las consultas bien escritas caen en `idx_fecha_tracking`, `idx_mensajero_fecha` o `idx_user_fecha`.

### Prioridad 3 — consultas que no usan índice

9. **Reemplazar `DATE(col) = / >= / <=` por rangos** `col >= 'Y-m-d 00:00:00' AND col <= 'Y-m-d 23:59:59'`:
   - SF: dashboard 62, 71; home 94; reportes_financieros 450; reportes_admin 441, 1049, 1242; finanzas 1717, 2200.
   - compras: dashboard 79; home 100; reportes_financieros 457; reportes_admin 524, 1057, 1250; finanzas 1732, 2208; compras 411, 415.
   - transferencias: finanzas 1050, 1747; transferencias 349, 353.
10. **Totales históricos del dashboard/home** (D1, D2, H2, H3): recorren las 78k filas (D1 además parsea JSON) en cada carga del panel admin. Cachear con transient (p. ej. 10 min) o mantener una tabla de resumen diario.
11. **Quitar código muerto que se ejecuta**: finanzas L1717-1789 (5 consultas históricas cuyo resultado `$_map_hist_*` solo termina en `$debug_valores_mensajero`, que no se muestra; F5 recorre SF completa). Borrar también los `$where_*` sin uso en L1536-1595 y L1860-1925.
12. **Direcciones previas** (gofast_solicitar_mensajero.php:80): separar en `WHERE user_id = %d` (usa `idx_user_fecha`) y, para el teléfono, guardar una columna normalizada indexada o limitar por fecha reciente. Hoy recorre la tabla completa en cada cotización.
13. **mis-pedidos**: resolver en el SELECT principal (`LEFT JOIN usuarios_gofast m ON m.id = s.mensajero_id`, `LEFT JOIN usuarios_gofast a ON a.id = s.asignado_por_user_id`) el nombre y teléfono del mensajero y el nombre y rol del asignador. Precargar las tarifas necesarias con una sola consulta `IN (…)`, como hace `gofast_mis_domicilios.php:190`.
14. Resolver el nombre de barrio de negocios con `JOIN barrios` (sección 3, fila 8) y el nombre del mensajero en reportes_admin L1969 con `LEFT JOIN`.

### Prioridad 4 — limpieza de metadatos/DDL

15. **Quitar todos los `SHOW COLUMNS`** (las columnas ya existen): mis-pedidos 594, 602, **1588, 1905 (por fila)**; sesiones.php:50 (en `init`); recargos_admin 20, 23, 239; auth_logic 26, 223, 276; usuarios_admin 136; admin_cotizar 271; mensajero_cotizar 263; admin_cotizar_intermunicipal 245; mensajero_cotizar_intermunicipal 226; admin_solicitar_intermunicipal 271, 680.
16. **Quitar el `ALTER TABLE` en tiempo de ejecución** de mis-pedidos.php:597.
17. **Quitar los `SHOW TABLES LIKE 'destinos_intermunicipales'`**: cotizar_intermunicipal 26, admin_cotizar_intermunicipal 74, mensajero_cotizar_intermunicipal 66, admin_solicitar_intermunicipal 92, solicitar_intermunicipal 57.

### Otros (corrección)

18. Comparaciones `JSON_EXTRACT(destinos,'$.tipo_servicio') = 'intermunicipal'` sin comillas JSON (reportes_financieros 578/588, mis-pedidos 745/748): usar `JSON_VALUE(destinos,'$.tipo_servicio') = 'intermunicipal'`.
19. Reemplazar `date('H:i:s')` y `date(..., strtotime(...))` por los helpers `gofast_*` (sección 5.2) para no depender de `date_default_timezone_set`.

-- =====================================================================
-- GoFast · Análisis de base de datos para el plan de optimización
-- ---------------------------------------------------------------------
-- TODAS las consultas son de SOLO LECTURA (SELECT / SHOW / EXPLAIN).
-- No crean, modifican ni borran nada.
--
-- Cómo usarlo en phpMyAdmin:
--   1. Selecciona la base de datos u946523207_DyHix en el panel izquierdo
--      (NO information_schema). Las consultas ya traen el nombre fijo.
--   2. Pestaña "SQL". Pega UN bloque a la vez y ejecuta.
--   3. Exporta o copia el resultado de cada bloque (botón "Exportar"
--      debajo del resultado, formato CSV) y compártelo.
-- =====================================================================


-- =====================================================================
-- BLOQUE 1 · Versión y configuración del servidor
-- =====================================================================
SELECT VERSION() AS version_mysql, DATABASE() AS base_de_datos, NOW() AS hora_servidor, @@time_zone AS zona_horaria;

SHOW VARIABLES WHERE Variable_name IN (
  'innodb_buffer_pool_size', 'max_connections', 'slow_query_log', 'long_query_time',
  'query_cache_type', 'query_cache_size', 'tmp_table_size', 'max_heap_table_size',
  'innodb_log_file_size', 'sql_mode', 'character_set_server', 'collation_server',
  'innodb_file_per_table', 'max_allowed_packet'
);


-- =====================================================================
-- BLOQUE 2 · Tamaño, filas y motor de TODAS las tablas
-- =====================================================================
SELECT
  TABLE_NAME                                   AS tabla,
  ENGINE                                       AS motor,
  TABLE_ROWS                                   AS filas_aprox,
  ROUND(DATA_LENGTH  / 1024 / 1024, 2)         AS datos_mb,
  ROUND(INDEX_LENGTH / 1024 / 1024, 2)         AS indices_mb,
  ROUND(DATA_FREE    / 1024 / 1024, 2)         AS espacio_libre_mb,
  TABLE_COLLATION                              AS cotejamiento,
  AUTO_INCREMENT                               AS siguiente_id,
  CREATE_TIME                                  AS creada,
  UPDATE_TIME                                  AS ultima_actualizacion
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'u946523207_DyHix'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;


-- =====================================================================
-- BLOQUE 3 · Índices existentes en TODAS las tablas
-- =====================================================================
SELECT
  TABLE_NAME                                             AS tabla,
  INDEX_NAME                                             AS indice,
  IF(NON_UNIQUE = 0, 'UNICO', 'normal')                  AS tipo,
  GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)        AS columnas,
  MAX(CARDINALITY)                                       AS cardinalidad,
  INDEX_TYPE                                             AS estructura
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'u946523207_DyHix'
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE, INDEX_TYPE
ORDER BY TABLE_NAME, INDEX_NAME = 'PRIMARY' DESC, INDEX_NAME;


-- =====================================================================
-- BLOQUE 4 · Tablas SIN llave primaria
-- =====================================================================
SELECT t.TABLE_NAME AS tabla_sin_llave_primaria, t.TABLE_ROWS AS filas_aprox
FROM information_schema.TABLES t
LEFT JOIN information_schema.TABLE_CONSTRAINTS c
       ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
      AND c.TABLE_NAME   = t.TABLE_NAME
      AND c.CONSTRAINT_TYPE = 'PRIMARY KEY'
WHERE t.TABLE_SCHEMA = 'u946523207_DyHix'
  AND t.TABLE_TYPE = 'BASE TABLE'
  AND c.CONSTRAINT_NAME IS NULL;


-- =====================================================================
-- BLOQUE 5 · Estructura de columnas de las tablas GoFast
-- =====================================================================
SELECT
  TABLE_NAME      AS tabla,
  ORDINAL_POSITION AS pos,
  COLUMN_NAME     AS columna,
  COLUMN_TYPE     AS tipo,
  IS_NULLABLE     AS permite_null,
  COLUMN_DEFAULT  AS valor_defecto,
  COLUMN_KEY      AS llave,
  EXTRA           AS extra,
  COLLATION_NAME  AS cotejamiento
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'u946523207_DyHix'
  AND TABLE_NAME IN (
    'servicios_gofast', 'usuarios_gofast', 'negocios_gofast', 'barrios', 'sectores',
    'tarifas', 'recargos', 'recargos_rangos', 'destinos_intermunicipales',
    'compras_gofast', 'transferencias_gofast', 'transferencias_salidas_gofast',
    'pagos_mensajeros_gofast', 'descuentos_mensajeros_gofast', 'egresos_gofast',
    'vales_empresa_gofast', 'vales_personal_gofast', 'solicitudes_trabajo'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;


-- =====================================================================
-- BLOQUE 6 · Definición completa de las tablas principales
-- (ejecuta cada línea por separado; copia la columna "Create Table")
-- =====================================================================
SHOW CREATE TABLE servicios_gofast;
SHOW CREATE TABLE usuarios_gofast;
SHOW CREATE TABLE negocios_gofast;
SHOW CREATE TABLE tarifas;
SHOW CREATE TABLE barrios;
SHOW CREATE TABLE compras_gofast;
SHOW CREATE TABLE transferencias_gofast;
SHOW CREATE TABLE pagos_mensajeros_gofast;


-- =====================================================================
-- BLOQUE 7 · Columnas que el código usa para filtrar / ordenar
-- y si tienen índice (primera columna de algún índice)
-- =====================================================================
SELECT
  c.TABLE_NAME  AS tabla,
  c.COLUMN_NAME AS columna,
  IF(s.COLUMN_NAME IS NULL, 'SIN INDICE', 'con indice') AS estado
FROM information_schema.COLUMNS c
LEFT JOIN (
  SELECT DISTINCT TABLE_NAME, COLUMN_NAME
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'u946523207_DyHix' AND SEQ_IN_INDEX = 1
) s ON s.TABLE_NAME = c.TABLE_NAME AND s.COLUMN_NAME = c.COLUMN_NAME
WHERE c.TABLE_SCHEMA = 'u946523207_DyHix'
  AND (c.TABLE_NAME, c.COLUMN_NAME) IN (
    ('servicios_gofast', 'fecha'), ('servicios_gofast', 'user_id'),
    ('servicios_gofast', 'mensajero_id'), ('servicios_gofast', 'tracking_estado'),
    ('servicios_gofast', 'asignado_por_user_id'), ('servicios_gofast', 'telefono_cliente'),
    ('negocios_gofast', 'user_id'), ('negocios_gofast', 'activo'),
    ('usuarios_gofast', 'rol'), ('usuarios_gofast', 'activo'),
    ('usuarios_gofast', 'telefono'), ('usuarios_gofast', 'remember_token'),
    ('compras_gofast', 'mensajero_id'), ('compras_gofast', 'fecha'), ('compras_gofast', 'estado'),
    ('transferencias_gofast', 'mensajero_id'), ('transferencias_gofast', 'estado'),
    ('transferencias_gofast', 'fecha'), ('transferencias_gofast', 'tipo'),
    ('pagos_mensajeros_gofast', 'mensajero_id'), ('pagos_mensajeros_gofast', 'fecha'),
    ('descuentos_mensajeros_gofast', 'mensajero_id'), ('descuentos_mensajeros_gofast', 'fecha'),
    ('egresos_gofast', 'fecha'), ('vales_empresa_gofast', 'fecha'),
    ('vales_personal_gofast', 'fecha'), ('vales_personal_gofast', 'persona_id'),
    ('transferencias_salidas_gofast', 'fecha'),
    ('tarifas', 'origen_sector_id'), ('tarifas', 'destino_sector_id'),
    ('barrios', 'sector_id'), ('recargos_rangos', 'recargo_id'),
    ('solicitudes_trabajo', 'estado')
  )
ORDER BY estado DESC, c.TABLE_NAME, c.COLUMN_NAME;


-- =====================================================================
-- BLOQUE 8 · Índices duplicados o redundantes
-- (un índice cuyas columnas ya están al inicio de otro índice)
-- =====================================================================
SELECT
  a.tabla,
  a.indice   AS indice_redundante,
  a.columnas AS columnas_redundante,
  b.indice   AS cubierto_por,
  b.columnas AS columnas_cubre
FROM (
  SELECT TABLE_NAME AS tabla, INDEX_NAME AS indice,
         GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'u946523207_DyHix'
  GROUP BY TABLE_NAME, INDEX_NAME
) a
JOIN (
  SELECT TABLE_NAME AS tabla, INDEX_NAME AS indice,
         GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'u946523207_DyHix'
  GROUP BY TABLE_NAME, INDEX_NAME
) b ON a.tabla = b.tabla
   AND a.indice <> b.indice
   AND a.indice <> 'PRIMARY'
   AND (b.columnas = a.columnas OR b.columnas LIKE CONCAT(a.columnas, ',%'))
ORDER BY a.tabla;


-- =====================================================================
-- BLOQUE 9 · Volumen del histórico de servicios
-- =====================================================================
SELECT
  COUNT(*)                                          AS total_servicios,
  MIN(fecha)                                        AS primer_servicio,
  MAX(fecha)                                        AS ultimo_servicio,
  SUM(user_id IS NULL OR user_id = 0)               AS sin_cliente,
  SUM(mensajero_id IS NULL OR mensajero_id = 0)     AS sin_mensajero,
  SUM(tracking_estado = 'cancelado')                AS cancelados,
  ROUND(AVG(LENGTH(destinos)))                      AS json_destinos_bytes_prom,
  MAX(LENGTH(destinos))                             AS json_destinos_bytes_max,
  SUM(JSON_VALID(destinos) = 0)                     AS json_invalidos
FROM servicios_gofast;

-- Servicios por mes (crecimiento)
SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes,
       COUNT(*)                     AS servicios,
       SUM(total)                   AS total_pesos,
       COUNT(DISTINCT user_id)      AS clientes,
       COUNT(DISTINCT mensajero_id) AS mensajeros
FROM servicios_gofast
GROUP BY mes
ORDER BY mes;

-- Servicios por estado
SELECT tracking_estado, COUNT(*) AS servicios, SUM(total) AS total_pesos
FROM servicios_gofast
GROUP BY tracking_estado
ORDER BY servicios DESC;


-- =====================================================================
-- BLOQUE 10 · Datos del JSON que usará el módulo contable
-- =====================================================================
SELECT
  COUNT(*)                                                                         AS total,
  SUM(JSON_EXTRACT(destinos, '$.origen.negocio_id') IS NOT NULL
      AND JSON_UNQUOTE(JSON_EXTRACT(destinos, '$.origen.negocio_id')) NOT IN ('', '0', 'null')) AS con_negocio,
  SUM(JSON_EXTRACT(destinos, '$.origen.barrio_id') IS NOT NULL)                    AS con_barrio_origen,
  ROUND(AVG(JSON_LENGTH(destinos, '$.destinos')), 2)                               AS destinos_promedio,
  MAX(JSON_LENGTH(destinos, '$.destinos'))                                         AS destinos_max,
  SUM(JSON_EXTRACT(destinos, '$.destinos[0].recargo_total') IS NOT NULL)           AS con_recargo_por_destino
FROM servicios_gofast
WHERE JSON_VALID(destinos) = 1;

-- Claves que existen dentro del JSON (muestra de los 5 servicios más recientes)
SELECT id, fecha,
       JSON_KEYS(destinos)                     AS claves_raiz,
       JSON_KEYS(destinos, '$.origen')         AS claves_origen,
       JSON_KEYS(destinos, '$.destinos[0]')    AS claves_destino
FROM servicios_gofast
WHERE JSON_VALID(destinos) = 1
ORDER BY id DESC
LIMIT 5;

-- Clientes con más servicios (usa uno de estos user_id en el BLOQUE 12)
SELECT s.user_id, u.nombre, u.rol, COUNT(*) AS servicios, SUM(s.total) AS total_pesos
FROM servicios_gofast s
LEFT JOIN usuarios_gofast u ON u.id = s.user_id
GROUP BY s.user_id, u.nombre, u.rol
ORDER BY servicios DESC
LIMIT 15;


-- =====================================================================
-- BLOQUE 11 · Integridad de datos (registros huérfanos o duplicados)
-- =====================================================================
SELECT 'servicios con user_id inexistente' AS revision, COUNT(*) AS cantidad
FROM servicios_gofast s LEFT JOIN usuarios_gofast u ON u.id = s.user_id
WHERE s.user_id IS NOT NULL AND s.user_id <> 0 AND u.id IS NULL
UNION ALL
SELECT 'servicios con mensajero_id inexistente', COUNT(*)
FROM servicios_gofast s LEFT JOIN usuarios_gofast u ON u.id = s.mensajero_id
WHERE s.mensajero_id IS NOT NULL AND s.mensajero_id <> 0 AND u.id IS NULL
UNION ALL
SELECT 'negocios con dueño inexistente', COUNT(*)
FROM negocios_gofast n LEFT JOIN usuarios_gofast u ON u.id = n.user_id
WHERE u.id IS NULL
UNION ALL
SELECT 'negocios con barrio inexistente', COUNT(*)
FROM negocios_gofast n LEFT JOIN barrios b ON b.id = n.barrio_id
WHERE b.id IS NULL
UNION ALL
SELECT 'barrios con sector inexistente', COUNT(*)
FROM barrios b LEFT JOIN sectores s ON s.id = b.sector_id
WHERE s.id IS NULL
UNION ALL
SELECT 'tarifas duplicadas (mismo origen y destino)', COUNT(*)
FROM (SELECT origen_sector_id, destino_sector_id FROM tarifas
      GROUP BY origen_sector_id, destino_sector_id HAVING COUNT(*) > 1) d
UNION ALL
SELECT 'combinaciones de sectores sin tarifa',
       (SELECT COUNT(*) FROM sectores) * (SELECT COUNT(*) FROM sectores)
       - (SELECT COUNT(DISTINCT origen_sector_id, destino_sector_id) FROM tarifas)
UNION ALL
SELECT 'usuarios con teléfono repetido', COUNT(*)
FROM (SELECT telefono FROM usuarios_gofast WHERE telefono IS NOT NULL AND telefono <> ''
      GROUP BY telefono HAVING COUNT(*) > 1) d;


-- =====================================================================
-- BLOQUE 12 · Plan de ejecución de las consultas que usará el módulo
-- Cambia 123 por un user_id real del BLOQUE 10 y ajusta las fechas.
-- Columna clave del resultado: "type" (ALL = recorre toda la tabla)
-- y "rows" (filas que tiene que revisar).
-- =====================================================================

-- 12.1 Cliente: sus domicilios del mes (lista paginada)
EXPLAIN SELECT * FROM servicios_gofast
WHERE user_id = 123
  AND fecha BETWEEN '2026-09-01 00:00:00' AND '2026-09-30 23:59:59'
ORDER BY fecha DESC
LIMIT 20;

-- 12.2 Cliente: totales del mes
EXPLAIN SELECT COUNT(*), SUM(total) FROM servicios_gofast
WHERE user_id = 123
  AND fecha BETWEEN '2026-09-01 00:00:00' AND '2026-09-30 23:59:59'
  AND tracking_estado <> 'cancelado';

-- 12.3 Admin: totales del mes de toda la operación
EXPLAIN SELECT COUNT(*), SUM(total) FROM servicios_gofast
WHERE fecha BETWEEN '2026-09-01 00:00:00' AND '2026-09-30 23:59:59'
  AND tracking_estado <> 'cancelado';

-- 12.4 Admin: resumen por mensajero
EXPLAIN SELECT mensajero_id, COUNT(*), SUM(total) FROM servicios_gofast
WHERE fecha BETWEEN '2026-09-01 00:00:00' AND '2026-09-30 23:59:59'
GROUP BY mensajero_id;

-- 12.5 Lista actual de "Mis pedidos" (filtro por estado y fecha)
EXPLAIN SELECT * FROM servicios_gofast
WHERE tracking_estado = 'pendiente'
  AND fecha >= '2026-09-29 00:00:00' AND fecha <= '2026-09-29 23:59:59'
ORDER BY fecha DESC
LIMIT 20 OFFSET 0;

-- 12.6 Mensajero: sus pedidos
EXPLAIN SELECT * FROM servicios_gofast
WHERE mensajero_id = 123
ORDER BY fecha DESC
LIMIT 20;


-- =====================================================================
-- BLOQUE 13 · Estado del servidor (puede requerir permisos; si da
-- error de acceso, sáltalo)
-- =====================================================================
SHOW GLOBAL STATUS WHERE Variable_name IN (
  'Uptime', 'Questions', 'Slow_queries', 'Threads_connected', 'Max_used_connections',
  'Created_tmp_tables', 'Created_tmp_disk_tables', 'Select_full_join', 'Select_scan',
  'Sort_merge_passes', 'Innodb_buffer_pool_reads', 'Innodb_buffer_pool_read_requests'
);


-- =====================================================================
-- BLOQUE 14 · WordPress (tablas del sitio)
-- Primero averigua el prefijo de tus tablas de WordPress:
-- =====================================================================
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'u946523207_DyHix' AND TABLE_NAME LIKE '%options';

-- Si el prefijo NO es "wp_", reemplaza "wp_" en las consultas de abajo.

-- 14.1 Opciones que se cargan en CADA página (autoload)
SELECT COUNT(*) AS opciones_autoload,
       ROUND(SUM(LENGTH(option_value)) / 1024, 1) AS autoload_kb
FROM wp_options
WHERE autoload IN ('yes', 'on', 'auto', 'auto-on');

-- 14.2 Las 20 opciones autoload más pesadas
SELECT option_name, ROUND(LENGTH(option_value) / 1024, 1) AS kb, autoload
FROM wp_options
WHERE autoload IN ('yes', 'on', 'auto', 'auto-on')
ORDER BY LENGTH(option_value) DESC
LIMIT 20;

-- 14.3 Transients (caché temporal) totales y vencidos
SELECT
  SUM(option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%') AS transients_total,
  SUM((option_name LIKE '\_transient\_timeout\_%' OR option_name LIKE '\_site\_transient\_timeout\_%')
      AND CAST(option_value AS UNSIGNED) < UNIX_TIMESTAMP())                          AS transients_vencidos
FROM wp_options;

-- 14.4 Contenido por tipo (revisiones, borradores, papelera)
SELECT post_type, post_status, COUNT(*) AS cantidad
FROM wp_posts
GROUP BY post_type, post_status
ORDER BY cantidad DESC;

-- 14.5 Metadatos huérfanos (de entradas que ya no existen)
SELECT COUNT(*) AS postmeta_huerfanos
FROM wp_postmeta pm
LEFT JOIN wp_posts p ON p.ID = pm.post_id
WHERE p.ID IS NULL;

-- 14.6 Snippets del plugin Code Snippets (activos / inactivos)
SELECT active, COUNT(*) AS snippets, ROUND(SUM(LENGTH(code)) / 1024, 1) AS codigo_kb
FROM wp_snippets
GROUP BY active;

-- =====================================================================
-- GoFast · Detalle en filas cortas (phpMyAdmin no las recorta). SOLO LECTURA.
-- Ejecuta UNA consulta a la vez y pulsa "Mostrar todo" si hay más de 25 filas.
-- =====================================================================


-- ---------------------------------------------------------------------
-- C · Un índice por fila (solo tablas GoFast)
-- ---------------------------------------------------------------------
SELECT
  TABLE_NAME AS tabla,
  INDEX_NAME AS indice,
  GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas,
  IF(MAX(NON_UNIQUE) = 0, 'UNICO', '') AS unico,
  MAX(CARDINALITY) AS cardinalidad
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'u946523207_DyHix' AND TABLE_NAME NOT LIKE 'wp\_%'
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME = 'PRIMARY' DESC, INDEX_NAME;


-- ---------------------------------------------------------------------
-- D · Columnas de las tablas que usa el módulo nuevo (una por fila)
-- ---------------------------------------------------------------------
SELECT
  TABLE_NAME AS tabla,
  COLUMN_NAME AS columna,
  COLUMN_TYPE AS tipo,
  IS_NULLABLE AS acepta_null,
  COLUMN_DEFAULT AS por_defecto,
  COLUMN_KEY AS llave
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'u946523207_DyHix'
  AND TABLE_NAME IN ('servicios_gofast', 'usuarios_gofast', 'negocios_gofast', 'tarifas', 'barrios', 'sectores', 'recargos', 'recargos_rangos')
ORDER BY TABLE_NAME, ORDINAL_POSITION;


-- =====================================================================
-- Consultas partidas (cada una devuelve menos de 25 filas)
-- =====================================================================

-- E1 · Índices de las tablas principales
SELECT TABLE_NAME AS tabla, INDEX_NAME AS indice,
  GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas,
  IF(MAX(NON_UNIQUE) = 0, 'UNICO', '') AS unico, MAX(CARDINALITY) AS cardinalidad
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'u946523207_DyHix'
  AND TABLE_NAME IN ('servicios_gofast', 'usuarios_gofast', 'tarifas', 'solicitudes_trabajo', 'recargos', 'recargos_rangos', 'sectores')
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME = 'PRIMARY' DESC, INDEX_NAME;

-- E2 · Índices de las tablas de dinero
SELECT TABLE_NAME AS tabla, INDEX_NAME AS indice,
  GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas,
  IF(MAX(NON_UNIQUE) = 0, 'UNICO', '') AS unico, MAX(CARDINALITY) AS cardinalidad
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'u946523207_DyHix'
  AND TABLE_NAME IN ('pagos_mensajeros_gofast', 'transferencias_gofast', 'transferencias_salidas_gofast', 'vales_empresa_gofast', 'vales_personal_gofast')
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME = 'PRIMARY' DESC, INDEX_NAME;

-- F1 · Columnas de servicios_gofast
SELECT COLUMN_NAME AS columna, COLUMN_TYPE AS tipo, IS_NULLABLE AS acepta_null,
  COLUMN_DEFAULT AS por_defecto, COLUMN_KEY AS llave
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'u946523207_DyHix' AND TABLE_NAME = 'servicios_gofast'
ORDER BY ORDINAL_POSITION;

-- F2 · Columnas de usuarios_gofast
SELECT COLUMN_NAME AS columna, COLUMN_TYPE AS tipo, IS_NULLABLE AS acepta_null,
  COLUMN_DEFAULT AS por_defecto, COLUMN_KEY AS llave
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'u946523207_DyHix' AND TABLE_NAME = 'usuarios_gofast'
ORDER BY ORDINAL_POSITION;

-- G1 · ¿Hay tarifas repetidas para el mismo trayecto? (vacío = no hay)
SELECT origen_sector_id, destino_sector_id, COUNT(*) AS veces, GROUP_CONCAT(precio) AS precios
FROM tarifas
GROUP BY origen_sector_id, destino_sector_id
HAVING COUNT(*) > 1
LIMIT 20;

-- G2 · Cómo se reparten los servicios (sin cliente, sin mensajero, por estado)
SELECT
  COUNT(*) AS total,
  SUM(user_id IS NULL OR user_id = 0) AS sin_cliente,
  SUM(mensajero_id IS NULL OR mensajero_id = 0) AS sin_mensajero,
  COUNT(DISTINCT user_id) AS clientes_distintos,
  MIN(fecha) AS primer_servicio,
  MAX(fecha) AS ultimo_servicio,
  SUM(estado <> tracking_estado) AS estado_distinto_de_tracking
FROM servicios_gofast;

-- F3 · Columnas de tarifas y sectores
SELECT TABLE_NAME AS tabla, COLUMN_NAME AS columna, COLUMN_TYPE AS tipo, IS_NULLABLE AS acepta_null,
  COLUMN_DEFAULT AS por_defecto, COLUMN_KEY AS llave
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'u946523207_DyHix' AND TABLE_NAME IN ('tarifas', 'sectores')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

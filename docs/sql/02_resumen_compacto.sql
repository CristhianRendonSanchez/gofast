-- =====================================================================
-- GoFast · Resumen compacto (una fila por tabla). SOLO LECTURA.
-- Ejecuta UNA consulta a la vez.
-- Antes de ejecutar: en el resultado, "+ Opciones" > "Textos completos"
-- para que phpMyAdmin no recorte el texto largo.
-- =====================================================================


-- ---------------------------------------------------------------------
-- A · Índices de cada tabla GoFast en una sola fila
-- ---------------------------------------------------------------------
SELECT
  s.TABLE_NAME AS tabla,
  t.TABLE_ROWS AS filas,
  GROUP_CONCAT(CONCAT(s.INDEX_NAME, '(', s.cols, ')') ORDER BY s.INDEX_NAME = 'PRIMARY' DESC, s.INDEX_NAME SEPARATOR '  |  ') AS indices
FROM (
  SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'u946523207_DyHix' AND TABLE_NAME NOT LIKE 'wp\_%'
  GROUP BY TABLE_NAME, INDEX_NAME
) s
JOIN information_schema.TABLES t
  ON t.TABLE_SCHEMA = 'u946523207_DyHix' AND t.TABLE_NAME = s.TABLE_NAME
GROUP BY s.TABLE_NAME, t.TABLE_ROWS
ORDER BY t.TABLE_ROWS DESC;


-- ---------------------------------------------------------------------
-- B · Columnas de cada tabla GoFast en una sola fila
-- ---------------------------------------------------------------------
SELECT
  TABLE_NAME AS tabla,
  GROUP_CONCAT(
    CONCAT(COLUMN_NAME, ' ', COLUMN_TYPE,
           IF(IS_NULLABLE = 'YES', ' null', ''),
           IF(COLUMN_DEFAULT IS NOT NULL, CONCAT(' def=', COLUMN_DEFAULT), ''))
    ORDER BY ORDINAL_POSITION SEPARATOR ',  '
  ) AS columnas
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'u946523207_DyHix' AND TABLE_NAME NOT LIKE 'wp\_%'
GROUP BY TABLE_NAME
ORDER BY TABLE_NAME;

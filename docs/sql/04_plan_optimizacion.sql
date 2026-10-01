-- =====================================================================
-- GoFast · Plan de optimización de índices
-- Base: u946523207_DyHix · Basado en el export del 29-sep-2026
--
-- ANTES DE EJECUTAR:
--   1. Tener el respaldo (export completo de phpMyAdmin) guardado.
--   2. Ejecutar en horario de poco movimiento.
--   3. Ejecutar paso por paso, no todo junto.
--
-- Solo se tocan índices. No se modifica ni se borra ningún dato ni columna.
-- =====================================================================


-- ---------------------------------------------------------------------
-- PASO 0 · Medir ANTES (guardar el resultado para comparar)
-- Cambia 10 por el user_id de un cliente con muchos servicios.
-- ---------------------------------------------------------------------
EXPLAIN SELECT id, fecha, total, tracking_estado
FROM servicios_gofast
WHERE user_id = 10 AND fecha >= '2026-01-01 00:00:00' AND fecha <= '2026-09-30 23:59:59'
ORDER BY fecha DESC;


-- ---------------------------------------------------------------------
-- PASO 1 · Índice nuevo: servicios por cliente y fecha
-- Lo usan "Mis pedidos" (cliente) y el módulo nuevo "Mis domicilios".
-- ---------------------------------------------------------------------
ALTER TABLE `servicios_gofast`
  ADD KEY `idx_user_fecha` (`user_id`, `fecha`);


-- ---------------------------------------------------------------------
-- PASO 2 · Medir DESPUÉS: en la columna "key" debe salir idx_user_fecha
-- y "rows" debe ser mucho menor que en el paso 0.
-- ---------------------------------------------------------------------
EXPLAIN SELECT id, fecha, total, tracking_estado
FROM servicios_gofast
WHERE user_id = 10 AND fecha >= '2026-01-01 00:00:00' AND fecha <= '2026-09-30 23:59:59'
ORDER BY fecha DESC;


-- ---------------------------------------------------------------------
-- PASO 3 · Quitar índices repetidos o que nunca se usan
-- Ninguno es el único índice de una llave foránea (se verificó en el export).
-- ---------------------------------------------------------------------

-- compras_gofast: ya cubiertos por idx_fecha_estado e idx_mensajero_estado
ALTER TABLE `compras_gofast`
  DROP INDEX `fecha_creacion`,
  DROP INDEX `mensajero_id`;

-- transferencias_gofast: ya cubiertos por idx_mensaj_est_fecha e idx_estado_fecha_tipo
ALTER TABLE `transferencias_gofast`
  DROP INDEX `mensajero_id`,
  DROP INDEX `estado`;

-- pagos_mensajeros_gofast: ya cubierto por idx_mensaj_fecha_tipo
ALTER TABLE `pagos_mensajeros_gofast`
  DROP INDEX `idx_mensajero`;

-- Búsqueda por descripción usa LIKE '%texto%': el índice nunca se aprovecha
ALTER TABLE `egresos_gofast`                DROP INDEX `idx_descripcion`;
ALTER TABLE `transferencias_salidas_gofast` DROP INDEX `idx_descripcion`;
ALTER TABLE `vales_empresa_gofast`          DROP INDEX `idx_descripcion`;
ALTER TABLE `vales_personal_gofast`         DROP INDEX `idx_descripcion`;

-- destinos_intermunicipales: 12 filas, un solo valor en "activo"
ALTER TABLE `destinos_intermunicipales` DROP INDEX `activo`;


-- ---------------------------------------------------------------------
-- PASO 4 · Recuperar espacio libre de servicios_gofast (opcional)
-- Reconstruye la tabla; en 78 mil filas tarda pocos segundos.
-- ---------------------------------------------------------------------
-- OPTIMIZE TABLE `servicios_gofast`;


-- =====================================================================
-- DESHACER (solo si algo sale mal)
-- =====================================================================
-- ALTER TABLE `servicios_gofast` DROP INDEX `idx_user_fecha`;
-- ALTER TABLE `compras_gofast` ADD KEY `fecha_creacion` (`fecha_creacion`), ADD KEY `mensajero_id` (`mensajero_id`);
-- ALTER TABLE `transferencias_gofast` ADD KEY `mensajero_id` (`mensajero_id`), ADD KEY `estado` (`estado`);
-- ALTER TABLE `pagos_mensajeros_gofast` ADD KEY `idx_mensajero` (`mensajero_id`);
-- ALTER TABLE `egresos_gofast` ADD KEY `idx_descripcion` (`descripcion`);
-- ALTER TABLE `transferencias_salidas_gofast` ADD KEY `idx_descripcion` (`descripcion`);
-- ALTER TABLE `vales_empresa_gofast` ADD KEY `idx_descripcion` (`descripcion`);
-- ALTER TABLE `vales_personal_gofast` ADD KEY `idx_descripcion` (`descripcion`);
-- ALTER TABLE `destinos_intermunicipales` ADD KEY `activo` (`activo`);

-- =====================================================================
-- GoFast · NIT de los negocios
-- Agrega la columna nit a negocios_gofast. Los negocios existentes
-- quedan con NULL; los formularios lo piden al crear y al editar.
--
-- ORDEN: ejecutar este script ANTES de pegar los snippets actualizados
-- (gofast_registro_negocio, redireccion, gofast_admin_negocios y
-- gofast_mis_estadisticas). Si se pegan antes, guardar un negocio falla.
--
-- Hacer respaldo de la tabla antes de ejecutar.
-- =====================================================================

-- 1) Respaldo rápido (opcional, crea una copia de la tabla)
-- CREATE TABLE negocios_gofast_bk_20261001 AS SELECT * FROM negocios_gofast;

-- 2) Cambio
ALTER TABLE negocios_gofast
    ADD COLUMN nit VARCHAR(20) NULL DEFAULT NULL AFTER whatsapp;

-- 3) Verificación
SHOW COLUMNS FROM negocios_gofast LIKE 'nit';
SELECT COUNT(*) AS total, SUM(nit IS NULL) AS sin_nit FROM negocios_gofast;

-- 4) Reversa (solo si se necesita deshacer)
-- ALTER TABLE negocios_gofast DROP COLUMN nit;

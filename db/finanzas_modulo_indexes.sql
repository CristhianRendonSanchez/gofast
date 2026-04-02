-- Índices para optimizar el módulo de finanzas (gofast_finanzas_admin)
-- Fecha: 2026-04-02
--
-- Los dumps en db/*.sql están alineados con producción SIN estos índices extra.
-- Ejecutar este archivo cuando quieras mejorar rendimiento (ventana de bajo uso).
--
-- Ejecutar en phpMyAdmin / CLI contra la base de producción (ventana de bajo uso).
--
-- Orden si la BD es antigua (sin `tipo` en transferencias):
--   1) transferencias_gofast_alter_tipo.sql
--   2) este archivo
--
-- Si ya importaste los dumps actualizados del repo (servicios_gofast.sql, finanzas_gofast.sql,
-- transferencias_gofast.sql con índices incluidos), NO hace falta volver a ejecutar los ALTER
-- de esas tablas aquí; solo aplicar lo que falte en tu servidor (p. ej. solo pagos o solo servicios).
--
-- Si un índice ya existe: error "Duplicate key name"; omitir ese bloque o esa línea.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- servicios_gofast: rangos por fecha + tracking; saldos por mensajero + fecha
ALTER TABLE `servicios_gofast`
  ADD KEY `idx_fecha_tracking` (`fecha`, `tracking_estado`),
  ADD KEY `idx_mensajero_fecha` (`mensajero_id`, `fecha`);

-- pagos_mensajeros_gofast: EXISTS en finanzas (mensajero + total + tipo); listados por rango
ALTER TABLE `pagos_mensajeros_gofast`
  ADD KEY `idx_mensaj_total_tipo` (`mensajero_id`, `total_a_pagar`, `tipo_pago`),
  ADD KEY `idx_mensaj_fecha_tipo` (`mensajero_id`, `fecha`, `tipo_pago`);

-- transferencias_gofast: totales aprobados por fechas; saldos por mensajero (requiere columna `tipo`)
ALTER TABLE `transferencias_gofast`
  ADD KEY `idx_estado_fecha_tipo` (`estado`, `fecha_creacion`, `tipo`),
  ADD KEY `idx_mensaj_est_fecha` (`mensajero_id`, `estado`, `fecha_creacion`);

COMMIT;

-- phpMyAdmin SQL Dump
-- Alteración para agregar campo tipo a transferencias_gofast
-- Fecha: 2025-01-27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Agregar campo 'tipo' para identificar transferencias de pago vs normales
ALTER TABLE `transferencias_gofast`
ADD COLUMN `tipo` enum('normal','pago') DEFAULT 'normal' COMMENT 'Tipo de transferencia: normal o pago' AFTER `estado`;

-- Agregar índice para el campo tipo
ALTER TABLE `transferencias_gofast`
ADD KEY `idx_tipo` (`tipo`);

-- Actualizar transferencias existentes que son de pago
-- Se identifican por el patrón en observaciones: "Pago automático - Transferencia"
UPDATE `transferencias_gofast`
SET `tipo` = 'pago'
WHERE `observaciones` LIKE 'Pago automático - Transferencia%';

COMMIT;


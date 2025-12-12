-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 27-01-2025
-- Versión del servidor: 11.8.3-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u946523207_DyHix`
--

-- ============================================================
-- TABLA: egresos_gofast
-- Descripción: Almacena los egresos de la empresa
-- ============================================================

DROP TABLE IF EXISTS `egresos_gofast`;

CREATE TABLE `egresos_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL COMMENT 'Fecha del egreso',
  `descripcion` varchar(255) NOT NULL COMMENT 'Descripción del egreso',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor del egreso',
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Usuario que creó el egreso',
  `fecha_creacion` datetime DEFAULT current_timestamp() COMMENT 'Fecha y hora de creación',
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha y hora de última actualización',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_descripcion` (`descripcion`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `egresos_gofast_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_gofast` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='Tabla de egresos de la empresa';

-- ============================================================
-- TABLA: vales_empresa_gofast
-- Descripción: Almacena los vales de la empresa
-- ============================================================

DROP TABLE IF EXISTS `vales_empresa_gofast`;

CREATE TABLE `vales_empresa_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL COMMENT 'Fecha del vale',
  `descripcion` varchar(255) NOT NULL COMMENT 'Descripción del vale',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor del vale',
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Usuario que creó el vale',
  `fecha_creacion` datetime DEFAULT current_timestamp() COMMENT 'Fecha y hora de creación',
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha y hora de última actualización',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_descripcion` (`descripcion`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `vales_empresa_gofast_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_gofast` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='Tabla de vales de la empresa';

-- ============================================================
-- TABLA: vales_personal_gofast
-- Descripción: Almacena los vales del personal (4 personas activas)
-- ============================================================

DROP TABLE IF EXISTS `vales_personal_gofast`;

CREATE TABLE `vales_personal_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL COMMENT 'Fecha del vale',
  `persona_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID de la persona (usuario)',
  `descripcion` varchar(255) NOT NULL COMMENT 'Descripción del vale',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor del vale',
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Usuario que creó el vale',
  `fecha_creacion` datetime DEFAULT current_timestamp() COMMENT 'Fecha y hora de creación',
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha y hora de última actualización',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_persona` (`persona_id`),
  KEY `idx_descripcion` (`descripcion`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `vales_personal_gofast_ibfk_1` FOREIGN KEY (`persona_id`) REFERENCES `usuarios_gofast` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `vales_personal_gofast_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_gofast` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='Tabla de vales del personal';

-- ============================================================
-- TABLA: transferencias_salidas_gofast
-- Descripción: Almacena las transferencias salientes de la empresa
-- ============================================================

DROP TABLE IF EXISTS `transferencias_salidas_gofast`;

CREATE TABLE `transferencias_salidas_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL COMMENT 'Fecha de la transferencia',
  `descripcion` varchar(255) NOT NULL COMMENT 'Descripción de la transferencia',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor de la transferencia',
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Usuario que creó la transferencia',
  `fecha_creacion` datetime DEFAULT current_timestamp() COMMENT 'Fecha y hora de creación',
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha y hora de última actualización',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_descripcion` (`descripcion`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `transferencias_salidas_gofast_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_gofast` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='Tabla de transferencias salientes de la empresa';

-- ============================================================
-- TABLA: descuentos_mensajeros_gofast
-- Descripción: Almacena los descuentos aplicados a mensajeros
-- ============================================================

DROP TABLE IF EXISTS `descuentos_mensajeros_gofast`;

CREATE TABLE `descuentos_mensajeros_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL COMMENT 'Fecha del descuento',
  `mensajero_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID del mensajero',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor del descuento',
  `descripcion` varchar(255) DEFAULT NULL COMMENT 'Descripción del descuento',
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Usuario que creó el descuento',
  `fecha_creacion` datetime DEFAULT current_timestamp() COMMENT 'Fecha y hora de creación',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_mensajero` (`mensajero_id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `descuentos_mensajeros_gofast_ibfk_1` FOREIGN KEY (`mensajero_id`) REFERENCES `usuarios_gofast` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `descuentos_mensajeros_gofast_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_gofast` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='Tabla de descuentos aplicados a mensajeros';

-- ============================================================
-- TABLA: pagos_mensajeros_gofast
-- Descripción: Almacena los registros de pagos a mensajeros
-- ============================================================

DROP TABLE IF EXISTS `pagos_mensajeros_gofast`;

CREATE TABLE `pagos_mensajeros_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL COMMENT 'Fecha del pago',
  `mensajero_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID del mensajero',
  `comision_total` decimal(10,2) NOT NULL COMMENT 'Total de comisiones generadas',
  `transferencias_total` decimal(10,2) DEFAULT 0.00 COMMENT 'Total de transferencias aprobadas',
  `descuentos_total` decimal(10,2) DEFAULT 0.00 COMMENT 'Total de descuentos aplicados',
  `total_a_pagar` decimal(10,2) NOT NULL COMMENT 'Total a pagar (comision - transferencias - descuentos)',
  `tipo_pago` enum('efectivo','transferencia','pendiente') DEFAULT 'pendiente' COMMENT 'Tipo de pago realizado',
  `fecha_pago` datetime DEFAULT NULL COMMENT 'Fecha y hora del pago',
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Usuario que creó el registro',
  `fecha_creacion` datetime DEFAULT current_timestamp() COMMENT 'Fecha y hora de creación',
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha y hora de última actualización',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_mensajero` (`mensajero_id`),
  KEY `idx_tipo_pago` (`tipo_pago`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `pagos_mensajeros_gofast_ibfk_1` FOREIGN KEY (`mensajero_id`) REFERENCES `usuarios_gofast` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pagos_mensajeros_gofast_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_gofast` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='Tabla de pagos a mensajeros';

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


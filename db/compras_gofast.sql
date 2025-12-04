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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras_gofast`
--

-- ============================================================
-- TABLA: compras_gofast
-- Descripción: Almacena las compras realizadas por mensajeros
-- ============================================================

-- Eliminar tabla si existe (incluyendo foreign keys)
DROP TABLE IF EXISTS `compras_gofast`;

CREATE TABLE `compras_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mensajero_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Mensajero asignado a la compra',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor de la compra',
  `barrio_id` int(11) NOT NULL COMMENT 'ID del barrio destino de la compra',
  `estado` enum('pendiente','en_proceso','completada','cancelada') DEFAULT 'pendiente' COMMENT 'Estado de la compra',
  `observaciones` text DEFAULT NULL COMMENT 'Observaciones adicionales',
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Usuario que creó la compra (admin o mensajero)',
  `fecha_creacion` datetime DEFAULT current_timestamp() COMMENT 'Fecha y hora de creación',
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Fecha y hora de última actualización',
  PRIMARY KEY (`id`),
  KEY `mensajero_id` (`mensajero_id`),
  KEY `creado_por` (`creado_por`),
  KEY `barrio_id` (`barrio_id`),
  KEY `estado` (`estado`),
  KEY `fecha_creacion` (`fecha_creacion`),
  CONSTRAINT `compras_gofast_ibfk_1` FOREIGN KEY (`mensajero_id`) REFERENCES `usuarios_gofast` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compras_gofast_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_gofast` (`id`) ON DELETE SET NULL,
  CONSTRAINT `compras_gofast_ibfk_3` FOREIGN KEY (`barrio_id`) REFERENCES `barrios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci COMMENT='Tabla de compras realizadas por mensajeros';

-- ============================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ============================================================

-- Índice compuesto para búsquedas por mensajero y estado
CREATE INDEX `idx_mensajero_estado` ON `compras_gofast` (`mensajero_id`, `estado`);

-- Índice compuesto para búsquedas por fecha y estado
CREATE INDEX `idx_fecha_estado` ON `compras_gofast` (`fecha_creacion`, `estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `compras_gofast`
--
ALTER TABLE `compras_gofast`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


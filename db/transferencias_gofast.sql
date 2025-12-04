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
-- Estructura de tabla para la tabla `transferencias_gofast`
--

CREATE TABLE `transferencias_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mensajero_id` bigint(20) UNSIGNED NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `creado_por` bigint(20) UNSIGNED NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `transferencias_gofast`
--
ALTER TABLE `transferencias_gofast`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mensajero_id` (`mensajero_id`),
  ADD KEY `creado_por` (`creado_por`),
  ADD KEY `estado` (`estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `transferencias_gofast`
--
ALTER TABLE `transferencias_gofast`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `transferencias_gofast`
--
ALTER TABLE `transferencias_gofast`
  ADD CONSTRAINT `transferencias_gofast_ibfk_1` FOREIGN KEY (`mensajero_id`) REFERENCES `usuarios_gofast` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transferencias_gofast_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_gofast` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


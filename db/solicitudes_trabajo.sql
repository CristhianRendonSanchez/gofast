-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 28-11-2025
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
-- Estructura de tabla para la tabla `solicitudes_trabajo`
--

CREATE TABLE `solicitudes_trabajo` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `whatsapp` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `pregunta1` text NOT NULL COMMENT 'Experiencia en reparto',
  `pregunta2` text NOT NULL COMMENT 'Disponibilidad de tiempo',
  `pregunta3` text NOT NULL COMMENT 'Vehículo propio',
  `pregunta4` text NOT NULL COMMENT 'Tipo de motocicleta',
  `pregunta5` text NOT NULL COMMENT 'Ciudad de residencia',
  `archivo_cv` varchar(255) DEFAULT NULL COMMENT 'Ruta del archivo CV subido',
  `nombre_archivo` varchar(255) DEFAULT NULL COMMENT 'Nombre original del archivo',
  `estado` enum('pendiente','revisado','contactado','rechazado') NOT NULL DEFAULT 'pendiente',
  `notas` text DEFAULT NULL COMMENT 'Notas internas del admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `solicitudes_trabajo`
--
ALTER TABLE `solicitudes_trabajo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `solicitudes_trabajo`
--
ALTER TABLE `solicitudes_trabajo`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


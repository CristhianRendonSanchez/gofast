-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 28-11-2025 a las 14:18:35
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
-- Estructura de tabla para la tabla `usuarios_gofast`
--

CREATE TABLE `usuarios_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('cliente','mensajero','admin') DEFAULT 'cliente',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `usuarios_gofast`
--

INSERT INTO `usuarios_gofast` (`id`, `nombre`, `telefono`, `email`, `password_hash`, `rol`, `activo`, `fecha_registro`) VALUES
(1, 'cristhian', '3117631081', 'crsthe1@gmail.com', '$2y$10$uCkHqfawvyTby6JerVBpGe/I8AXCiSLE1p7DsF9O2ojm/oJMUq4Rq', 'cliente', 1, '2025-11-07 22:00:33'),
(3, 'Cristhian', '3136000160', 'cristhian.rendon.sanchez@gmail.com', '$2y$10$vvXR2KTcozYBDFfPVtYaC.tPtFa5QeWTJL/8EhTlZt9fAIz0ixr1K', 'admin', 1, '2025-11-07 18:36:31'),
(4, 'yeni', '3154003043', 'dasd@das.com', '$2y$10$RTNEOrFtCd0Em13PnaVvA.2SKvEuBjGAjpBtgBF/479NiGMpPjBdC', 'mensajero', 1, '2025-11-07 18:43:38'),
(5, 'Alexander Garcia', '3173167426', 'stoicfx125@gmail.com', '$2y$10$pM4WTpgU3noTge2DPGrE1.r3U/jLXxDNZK4pCEQ9QkDqnmvRsmlUq', 'cliente', 1, '2025-11-11 09:18:37'),
(6, 'Angela Maria Casas', '3177531714', 'angelacasas082@gmail.com', '$2y$10$MonqE385VkeBFr4bOySYDO4kPDA2JQ48NtFGX3jkMzzDIyKK2i/lO', 'cliente', 1, '2025-11-16 16:23:20');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `usuarios_gofast`
--
ALTER TABLE `usuarios_gofast`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telefono` (`telefono`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `usuarios_gofast`
--
ALTER TABLE `usuarios_gofast`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

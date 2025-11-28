-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 28-11-2025 a las 14:17:47
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
-- Estructura de tabla para la tabla `negocios_gofast`
--

CREATE TABLE `negocios_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `direccion_full` varchar(255) NOT NULL,
  `barrio_id` bigint(20) UNSIGNED NOT NULL,
  `sector_id` bigint(20) UNSIGNED NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `whatsapp` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `negocios_gofast`
--

INSERT INTO `negocios_gofast` (`id`, `user_id`, `nombre`, `direccion_full`, `barrio_id`, `sector_id`, `tipo`, `activo`, `created_at`, `updated_at`, `whatsapp`) VALUES
(2, 6, 'Distrisabanas', 'Calle 11 # 23 23', 127, 34, 'Otro', 1, '2025-11-16 16:24:12', NULL, 0),
(4, 1, 'sublixarte', 'calle 9 w', 149, 41, 'qweqwe', 1, '2025-11-21 14:29:01', '2025-11-24 14:42:21', 2147483647),
(6, 1, 'sublix2', 'calle25 6 23', 140, 38, 'sublimacion', 1, '2025-11-24 14:38:03', NULL, 2147483647);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `negocios_gofast`
--
ALTER TABLE `negocios_gofast`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_barrio` (`barrio_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `negocios_gofast`
--
ALTER TABLE `negocios_gofast`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 28-11-2025 a las 14:17:54
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
-- Estructura de tabla para la tabla `recargos`
--

CREATE TABLE `recargos` (
  `id` int(11) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('fijo','por_valor') NOT NULL,
  `valor_fijo` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recargos`
--

INSERT INTO `recargos` (`id`, `slug`, `nombre`, `tipo`, `valor_fijo`, `activo`) VALUES
(1, 'lluvia', 'Recargo por lluvia', 'por_valor', 0, 0);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `recargos`
--
ALTER TABLE `recargos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `recargos`
--
ALTER TABLE `recargos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

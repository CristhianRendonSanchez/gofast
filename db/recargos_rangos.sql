-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 28-11-2025 a las 14:18:00
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
-- Estructura de tabla para la tabla `recargos_rangos`
--

CREATE TABLE `recargos_rangos` (
  `id` int(11) NOT NULL,
  `recargo_id` int(11) NOT NULL,
  `monto_min` int(11) NOT NULL,
  `monto_max` int(11) DEFAULT 0,
  `recargo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recargos_rangos`
--

INSERT INTO `recargos_rangos` (`id`, `recargo_id`, `monto_min`, `monto_max`, `recargo`) VALUES
(1, 1, 3500, 4500, 500),
(2, 1, 5000, 11000, 1000),
(3, 1, 12000, 0, 2000);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `recargos_rangos`
--
ALTER TABLE `recargos_rangos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recargo_id` (`recargo_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `recargos_rangos`
--
ALTER TABLE `recargos_rangos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `recargos_rangos`
--
ALTER TABLE `recargos_rangos`
  ADD CONSTRAINT `recargos_rangos_ibfk_1` FOREIGN KEY (`recargo_id`) REFERENCES `recargos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

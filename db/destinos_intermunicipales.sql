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
-- Estructura de tabla para la tabla `destinos_intermunicipales`
--

CREATE TABLE `destinos_intermunicipales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `valor` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `orden` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `destinos_intermunicipales`
--

INSERT INTO `destinos_intermunicipales` (`id`, `nombre`, `valor`, `activo`, `orden`) VALUES
(1, 'Andalucía', 20000, 1, 1),
(2, 'Bugalagrande', 25000, 1, 2),
(3, 'Riofrío', 20000, 1, 3),
(4, 'Buga', 35000, 1, 4),
(5, 'San Pedro', 25000, 1, 5),
(6, 'Los chanchos', 20000, 1, 6),
(7, 'Salónica', 35000, 1, 7),
(8, 'La Marina', 25000, 1, 8),
(9, 'Presidente', 30000, 1, 9),
(10, 'La Paila', 35000, 1, 10),
(11, 'Zarzal', 50000, 1, 11);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `destinos_intermunicipales`
--
ALTER TABLE `destinos_intermunicipales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activo` (`activo`),
  ADD KEY `orden` (`orden`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `destinos_intermunicipales`
--
ALTER TABLE `destinos_intermunicipales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


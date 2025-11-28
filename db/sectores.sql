-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 28-11-2025 a las 14:18:10
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
-- Estructura de tabla para la tabla `sectores`
--

CREATE TABLE `sectores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sectores`
--

INSERT INTO `sectores` (`id`, `nombre`) VALUES
(1, 'Sector 1'),
(2, 'Sector 2'),
(3, 'Sector 3'),
(4, 'Sector 4'),
(5, 'Sector 5'),
(6, 'Sector 6'),
(7, 'Sector 7'),
(8, 'Sector 8'),
(9, 'Sector 9'),
(10, 'Sector 10'),
(11, 'Sector 11'),
(12, 'Sector 12'),
(13, 'Sector 13'),
(14, 'Sector 14'),
(15, 'Sector 15'),
(16, 'Sector 16'),
(17, 'Sector 17'),
(18, 'Sector 18'),
(19, 'Sector 19'),
(20, 'Sector 20'),
(21, 'Sector 21'),
(22, 'Sector 22'),
(23, 'Sector 23'),
(24, 'Sector 24'),
(25, 'Sector 25'),
(26, 'Sector 26'),
(27, 'Sector 27'),
(28, 'Sector 28'),
(29, 'Sector 29'),
(30, 'Sector 30'),
(31, 'Sector 31'),
(32, 'Sector 32'),
(33, 'Sector 33'),
(34, 'Sector 34'),
(35, 'Sector 35'),
(36, 'Sector 36'),
(37, 'Sector 37'),
(38, 'Sector 38'),
(39, 'Sector 39'),
(40, 'Sector 40'),
(41, 'Sector 41'),
(42, 'Sector 42'),
(43, 'Sector 43'),
(44, 'Sector 44'),
(45, 'Sector 45'),
(46, 'Sector 46'),
(47, 'Sector 47'),
(48, 'Sector 48'),
(49, 'Sector 49'),
(50, 'Sector 50'),
(51, 'Sector 51'),
(52, 'Sector 52'),
(53, 'Sector 53'),
(54, 'Sector 54'),
(55, 'Sector 55'),
(56, 'Sector 56'),
(57, 'Sector 57'),
(58, 'Sector 58'),
(59, 'Sector 59'),
(60, 'Sector 60'),
(61, 'Sector 61'),
(62, 'Sector 62'),
(63, 'Sector 63'),
(64, 'Sector 64'),
(65, 'Sector 65'),
(66, 'Sector 66'),
(67, 'Sector 67'),
(68, 'Sector 68'),
(69, 'Sector 69'),
(70, 'Sector 70'),
(71, 'Sector 71'),
(72, 'Sector 72'),
(73, 'Sector 73'),
(74, 'Sector 74'),
(75, 'Sector 75'),
(76, 'Sector 76'),
(77, 'Sector 77'),
(78, 'Sector 78'),
(79, 'Sector 79'),
(80, 'Sector 80'),
(81, 'Sector 81'),
(82, 'Sector 82'),
(83, 'Sector 83'),
(84, 'Sector 84'),
(85, 'Sector 85'),
(86, 'Sector 86'),
(87, 'Sector 87');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `sectores`
--
ALTER TABLE `sectores`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `sectores`
--
ALTER TABLE `sectores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

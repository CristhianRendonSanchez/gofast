-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 28-11-2025 a las 14:18:22
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
-- Estructura de tabla para la tabla `servicios_gofast`
--

CREATE TABLE `servicios_gofast` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `nombre_cliente` varchar(120) NOT NULL,
  `telefono_cliente` varchar(20) NOT NULL,
  `direccion_origen` varchar(255) NOT NULL,
  `destinos` longtext NOT NULL,
  `montos` longtext DEFAULT NULL,
  `total` int(11) NOT NULL,
  `estado` varchar(30) DEFAULT 'pendiente',
  `mensajero_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tracking_estado` enum('pendiente','asignado','en_ruta','entregado','cancelado') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `servicios_gofast`
--

INSERT INTO `servicios_gofast` (`id`, `fecha`, `nombre_cliente`, `telefono_cliente`, `direccion_origen`, `destinos`, `montos`, `total`, `estado`, `mensajero_id`, `user_id`, `tracking_estado`) VALUES
(1, '2025-11-07 23:27:08', 'Cristhian', '3117631081', 'Calle 25', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":\"21\",\"direccion\":\"Calle 25\"},\"destinos\":[{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"calle 25\",\"monto\":5000}]}', NULL, 9000, 'pendiente', NULL, 1, 'pendiente'),
(2, '2025-11-07 23:28:36', 'cristhin', '3136000160', 'Calle 25', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":\"21\",\"direccion\":\"Calle 25\"},\"destinos\":[{\"barrio_id\":251,\"barrio_nombre\":\"Agua Viva\",\"sector_id\":\"64\",\"direccion\":\"calle 25\",\"monto\":0}]}', NULL, 7000, 'pendiente', NULL, NULL, 'pendiente'),
(3, '2025-11-07 23:36:04', 'Cristhian', '3136000160', 'Calle 25', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":\"21\",\"direccion\":\"Calle 25\"},\"destinos\":[{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"calle 25\",\"monto\":0}]}', NULL, 9000, 'pendiente', NULL, NULL, 'pendiente'),
(4, '2025-11-07 23:43:03', 'yeni', '3154003043', 'calle 25', '{\"origen\":{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"calle 25\"},\"destinos\":[{\"barrio_id\":240,\"barrio_nombre\":\"Centro Comercial La Herradura\",\"sector_id\":\"61\",\"direccion\":\"la herradura\",\"monto\":0}]}', NULL, 7000, 'pendiente', NULL, 4, 'pendiente'),
(5, '2025-11-08 01:28:00', 'Cristhian', '3116278818', 'Calle 25 6 23', '{\"origen\":{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"Calle 25 6 23\"},\"destinos\":[{\"barrio_id\":260,\"barrio_nombre\":\"Aguaclara Después de la Iglesia\",\"sector_id\":\"67\",\"direccion\":\"Calle 23 8 30\",\"monto\":0}]}', NULL, 8000, 'pendiente', NULL, 1, 'pendiente'),
(6, '2025-11-08 01:49:53', 'Cristhian', '3116278818', 'Calle 25 6 23', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":\"21\",\"direccion\":\"Calle 25 6 23\"},\"destinos\":[{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":\"21\",\"direccion\":\"Calle 23 8 30\",\"monto\":0}]}', NULL, 5000, 'pendiente', NULL, NULL, 'pendiente'),
(7, '2025-11-08 05:07:14', 'Alexander', '3238049061', 'Cra 24 # 23 11', '{\"origen\":{\"barrio_id\":104,\"barrio_nombre\":\"Escobar \",\"sector_id\":\"29\",\"direccion\":\"Cra 24 # 23 11\"},\"destinos\":[{\"barrio_id\":35,\"barrio_nombre\":\"Alvernia\",\"sector_id\":\"10\",\"direccion\":\"Calle 24 # 37 52\",\"monto\":0}]}', NULL, 5000, 'pendiente', NULL, NULL, 'pendiente'),
(8, '2025-11-08 16:32:43', 'Alexander', '3173167426', 'Calle 11 # 23 23', '{\"origen\":{\"barrio_id\":21,\"barrio_nombre\":\"Acacias\",\"sector_id\":\"6\",\"direccion\":\"Calle 11 # 23 23\"},\"destinos\":[{\"barrio_id\":153,\"barrio_nombre\":\"Alameda 2\",\"sector_id\":\"42\",\"direccion\":\"Cra 24 # 23 23\",\"monto\":0}]}', NULL, 5500, 'pendiente', NULL, NULL, 'pendiente'),
(9, '2025-11-09 22:55:20', 'cristhian', '3117631081', 'calle 25', '{\"origen\":{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"calle 25\"},\"destinos\":[{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"calle 25\",\"monto\":0}]}', NULL, 5500, 'pendiente', NULL, 1, 'pendiente'),
(10, '2025-11-11 01:28:09', 'Cristhian', '3136000160', 'Calle 25', '{\"origen\":{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"Calle 25\"},\"destinos\":[{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"calle 25\",\"monto\":0},{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"calle 25\",\"monto\":0}]}', NULL, 9000, 'pendiente', NULL, 3, 'pendiente'),
(11, '2025-11-11 01:32:56', 'Cristhian', '3136000160', 'Calle 25', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":\"21\",\"direccion\":\"Calle 25\"},\"destinos\":[{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":\"21\",\"direccion\":\"\",\"monto\":0}]}', NULL, 4000, 'pendiente', NULL, 3, 'pendiente'),
(12, '2025-11-12 01:02:20', 'cristhian', '3117631081', 'Calle 25 6 23', '{\"origen\":{\"barrio_id\":140,\"barrio_nombre\":\"Américas\",\"sector_id\":\"38\",\"direccion\":\"Calle 25 6 23\"},\"destinos\":[{\"barrio_id\":149,\"barrio_nombre\":\"Nieves\",\"sector_id\":\"41\",\"direccion\":\"\",\"monto\":0}]}', NULL, 4000, 'pendiente', NULL, 1, 'pendiente'),
(13, '2025-11-12 01:20:05', 'cristhian', '3117631081', 'Calle 25 6 23', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":\"21\",\"direccion\":\"Calle 25 6 23\"},\"destinos\":[{\"barrio_id\":251,\"barrio_nombre\":\"Agua Viva\",\"sector_id\":\"64\",\"direccion\":\"\",\"monto\":0}]}', NULL, 6000, 'pendiente', NULL, 1, 'entregado'),
(14, '2025-11-12 01:37:50', 'Cristhian', '3116278818', 'Calle 25 6 23', '{\"origen\":{\"barrio_id\":140,\"barrio_nombre\":\"Américas\",\"sector_id\":\"38\",\"direccion\":\"Calle 25 6 23\"},\"destinos\":[{\"barrio_id\":186,\"barrio_nombre\":\"Clinica Alvernia\",\"sector_id\":\"51\",\"direccion\":\"\",\"monto\":0}]}', NULL, 5000, 'pendiente', NULL, NULL, 'pendiente'),
(15, '2025-11-12 02:04:57', 'Cristhian', '1234567890', 'Calle 45', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":\"21\",\"direccion\":\"Calle 45\"},\"destinos\":[{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"\",\"monto\":0}]}', NULL, 8000, 'pendiente', NULL, 1, 'pendiente'),
(16, '2025-11-12 02:49:14', 'Cristhian', '3116278818', 'Calle 25 6 23', '{\"origen\":{\"barrio_id\":186,\"barrio_nombre\":\"Clinica Alvernia\",\"sector_id\":\"51\",\"direccion\":\"Calle 25 6 23\"},\"destinos\":[{\"barrio_id\":35,\"barrio_nombre\":\"Alvernia\",\"sector_id\":\"10\",\"direccion\":\"\",\"monto\":0}]}', NULL, 4000, 'pendiente', NULL, NULL, 'pendiente'),
(17, '2025-11-13 01:25:33', 'Cristhian', '3116278818', 'Calle 25 6 23', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":\"21\",\"direccion\":\"Calle 25 6 23\"},\"destinos\":[{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":\"80\",\"direccion\":\"\",\"monto\":0}]}', NULL, 8000, 'pendiente', NULL, NULL, 'pendiente'),
(18, '2025-11-14 23:32:08', 'Cristhian', '3136000160', 'Calle 25', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":21,\"direccion\":\"Calle 25\"},\"destinos\":[{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":80,\"direccion\":\"calle 25\",\"monto\":0}]}', NULL, 8000, 'pendiente', NULL, NULL, 'pendiente'),
(19, '2025-11-14 23:41:49', 'cristhian', '3117631081', 'Calle 25 6 23', '{\"origen\":{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":80,\"direccion\":\"Calle 25 6 23\"},\"destinos\":[{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":21,\"direccion\":\"\",\"monto\":0}]}', NULL, 8000, 'pendiente', NULL, 1, 'pendiente'),
(20, '2025-11-14 23:55:33', 'cristhian', '3117631081', 'Calle 25 6 23', '{\"origen\":{\"barrio_id\":251,\"barrio_nombre\":\"Agua Viva\",\"sector_id\":64,\"direccion\":\"Calle 25 6 23\"},\"destinos\":[{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":80,\"direccion\":\"\",\"monto\":0}]}', NULL, 6000, 'pendiente', NULL, 1, 'pendiente'),
(21, '2025-11-15 00:01:55', 'cristhian', '3117631081', 'Calle 45', '{\"origen\":{\"barrio_id\":251,\"barrio_nombre\":\"Agua Viva\",\"sector_id\":64,\"direccion\":\"Calle 45\"},\"destinos\":[{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":21,\"direccion\":\"\",\"monto\":0}]}', NULL, 6000, 'pendiente', NULL, 1, 'pendiente'),
(22, '2025-11-15 00:08:47', 'cristhian', '3117631081', 'Calle 45', '{\"origen\":{\"barrio_id\":251,\"barrio_nombre\":\"Agua Viva\",\"sector_id\":64,\"direccion\":\"Calle 45\"},\"destinos\":[{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":21,\"direccion\":\"\",\"monto\":0}]}', NULL, 6000, 'pendiente', NULL, 1, 'pendiente'),
(23, '2025-11-15 00:09:43', 'cristhian', '3117631081', 'Calle 25 6 23', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":21,\"direccion\":\"Calle 25 6 23\"},\"destinos\":[{\"barrio_id\":262,\"barrio_nombre\":\"Callejon San Antonio Despues de la Carrilera\",\"sector_id\":67,\"direccion\":\"\",\"monto\":0}]}', NULL, 8000, 'pendiente', NULL, 1, 'en_ruta'),
(24, '2025-11-16 15:19:13', 'Alexander Garcia', '3173167426', 'Calle 11 # 23 23', '{\"origen\":{\"barrio_id\":251,\"barrio_nombre\":\"Agua Viva\",\"sector_id\":64,\"direccion\":\"Calle 11 # 23 23\"},\"destinos\":[{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":80,\"direccion\":\"\",\"monto\":0}]}', NULL, 6000, 'pendiente', NULL, 5, 'pendiente'),
(25, '2025-11-16 20:03:30', 'cristhian', '3117631081', 'Calle 45', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":21,\"direccion\":\"Calle 45\"},\"destinos\":[{\"barrio_id\":260,\"barrio_nombre\":\"Aguaclara Después de la Iglesia\",\"sector_id\":67,\"direccion\":\"\",\"monto\":0},{\"barrio_id\":251,\"barrio_nombre\":\"Agua Viva\",\"sector_id\":64,\"direccion\":\"\",\"monto\":0}]}', NULL, 14000, 'pendiente', NULL, 1, 'pendiente'),
(26, '2025-11-16 20:24:43', 'cristhian', '3117631081', 'calle 25', '{\"origen\":{\"barrio_id\":76,\"barrio_nombre\":\"San Benito Campestre\",\"sector_id\":21,\"direccion\":\"calle 25\"},\"destinos\":[{\"barrio_id\":260,\"barrio_nombre\":\"Aguaclara Después de la Iglesia\",\"sector_id\":67,\"direccion\":\"\",\"monto\":0}]}', NULL, 8000, 'pendiente', NULL, 1, 'pendiente'),
(27, '2025-11-16 20:28:25', 'Cristhian', '3117631081', 'Calle 25', '{\"origen\":{\"barrio_id\":91,\"barrio_nombre\":\"350 Años\",\"sector_id\":27,\"direccion\":\"Calle 25\"},\"destinos\":[{\"barrio_id\":91,\"barrio_nombre\":\"350 Años\",\"sector_id\":27,\"direccion\":\"\",\"monto\":0}]}', NULL, 3500, 'pendiente', NULL, 1, 'pendiente'),
(28, '2025-11-16 20:28:45', 'Alexander Garcia', '3173167426', 'Calle 11 # 23 23', '{\"origen\":{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":21,\"direccion\":\"Calle 11 # 23 23\"},\"destinos\":[{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":80,\"direccion\":\"\",\"monto\":0}]}', NULL, 8000, 'pendiente', NULL, 5, 'pendiente'),
(29, '2025-11-16 21:25:58', 'Angela Maria Casas', '3177531714', 'Calle 11 # 23 23', '{\"origen\":{\"barrio_id\":153,\"barrio_nombre\":\"Alameda 2\",\"sector_id\":42,\"direccion\":\"Calle 11 # 23 23\"},\"destinos\":[{\"barrio_id\":8,\"barrio_nombre\":\"Bastilla\",\"sector_id\":2,\"direccion\":\"Cra 27d # 40c 15\",\"monto\":80000}]}', NULL, 5000, 'pendiente', 4, 6, 'pendiente'),
(30, '2025-11-16 23:18:43', 'Alexander Garcia', '3173167426', 'Calle 11 # 23 23', '{\"origen\":{\"barrio_id\":6,\"barrio_nombre\":\"Avenida Cali\",\"sector_id\":2,\"direccion\":\"Calle 11 # 23 23\"},\"destinos\":[{\"barrio_id\":26,\"barrio_nombre\":\"Arboledas del Darien\",\"sector_id\":7,\"direccion\":\"\",\"monto\":0}]}', NULL, 4000, 'pendiente', 4, 5, 'pendiente'),
(31, '2025-11-21 20:22:10', 'sublixarte', '3117631081', 'calle 9 w', '{\"origen\":{\"barrio_id\":287,\"barrio_nombre\":\"Aeropuerto\",\"sector_id\":80,\"direccion\":\"calle 9 w\"},\"destinos\":[{\"barrio_id\":75,\"barrio_nombre\":\"Academia Militar\",\"sector_id\":21,\"direccion\":\"calle 25\",\"monto\":0}]}', NULL, 8000, 'pendiente', 4, 1, 'entregado');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `servicios_gofast`
--
ALTER TABLE `servicios_gofast`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `servicios_gofast`
--
ALTER TABLE `servicios_gofast`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

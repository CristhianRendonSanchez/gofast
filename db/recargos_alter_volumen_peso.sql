-- phpMyAdmin SQL Dump
-- Modificación para agregar recargos fijos seleccionables por volumen y peso
-- Fecha: 2025-01-XX
-- Estos recargos son fijos pero se seleccionan manualmente durante la cotización

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Modificar tabla `recargos` para agregar tipo 'por_volumen_peso'
-- Estos son recargos fijos que se seleccionan manualmente
--
ALTER TABLE `recargos`
  MODIFY `tipo` enum('fijo','por_valor','por_volumen_peso') NOT NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- phpMyAdmin SQL Dump
-- Alterar campo whatsapp de INT a VARCHAR para soportar números de teléfono completos
-- Versión del servidor: 11.8.3-MariaDB-log

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- Alterar la columna whatsapp de INT a VARCHAR(20)
ALTER TABLE `negocios_gofast` 
MODIFY `whatsapp` VARCHAR(20) NOT NULL DEFAULT '';

COMMIT;


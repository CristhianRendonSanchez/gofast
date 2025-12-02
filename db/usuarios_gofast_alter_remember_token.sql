-- Script para agregar el campo remember_token a la tabla usuarios_gofast
-- Este campo es necesario para las cookies persistentes (30 días)

ALTER TABLE `usuarios_gofast` 
ADD COLUMN `remember_token` varchar(255) DEFAULT NULL AFTER `fecha_registro`;

-- Crear índice para mejorar búsquedas por token
ALTER TABLE `usuarios_gofast`
ADD INDEX `idx_remember_token` (`remember_token`);


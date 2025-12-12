-- Agregar campos para recuperación de contraseña
-- Token único para resetear contraseña y fecha de expiración

ALTER TABLE `usuarios_gofast`
ADD COLUMN `reset_token` VARCHAR(64) NULL DEFAULT NULL AFTER `password_hash`,
ADD COLUMN `reset_token_expires` DATETIME NULL DEFAULT NULL AFTER `reset_token`;

-- Crear índice para búsquedas rápidas por token
ALTER TABLE `usuarios_gofast`
ADD INDEX `idx_reset_token` (`reset_token`);


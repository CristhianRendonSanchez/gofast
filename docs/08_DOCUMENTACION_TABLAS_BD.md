# Documentación de Tablas de Base de Datos

## Descripción General

Este documento describe todas las tablas de la base de datos del sistema GoFast, incluyendo su estructura, tipos de datos, campos y propósito.

## 1. usuarios_gofast

### Propósito
Almacena todos los usuarios del sistema: clientes, mensajeros y administradores.

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | bigint(20) UNSIGNED | ID único del usuario | PRIMARY KEY, AUTO_INCREMENT |
| `nombre` | varchar(120) | Nombre completo del usuario | NOT NULL |
| `telefono` | varchar(20) | Número de WhatsApp | NOT NULL, UNIQUE |
| `email` | varchar(120) | Correo electrónico | NOT NULL, UNIQUE |
| `password_hash` | varchar(255) | Hash de la contraseña (bcrypt) | NOT NULL |
| `rol` | enum('cliente','mensajero','admin') | Rol del usuario | DEFAULT 'cliente' |
| `activo` | tinyint(1) | Estado activo/inactivo | DEFAULT 1 |
| `fecha_registro` | datetime | Fecha de registro | DEFAULT current_timestamp() |
| `remember_token` | varchar(255) | Token para sesión persistente (cookie) | NULL (agregado en alter) |
| `reset_token` | varchar(255) | Token para recuperación de contraseña | NULL (agregado en alter) |
| `reset_token_expires` | datetime | Fecha de expiración del token de recuperación | NULL (agregado en alter) |

### Índices
- PRIMARY KEY: `id`
- UNIQUE: `telefono`
- UNIQUE: `email`

### Relaciones
- Referenciada por: `compras_gofast.mensajero_id`, `compras_gofast.creado_por`
- Referenciada por: `transferencias_gofast.mensajero_id`, `transferencias_gofast.creado_por`
- Referenciada por: `servicios_gofast.mensajero_id`, `servicios_gofast.user_id`
- Referenciada por: `negocios_gofast.user_id`

### Notas
- El campo `remember_token` se agregó para soportar sesiones persistentes (cookies de 30 días)
- Los campos `reset_token` y `reset_token_expires` se agregaron para recuperación de contraseña
- El hash de contraseña usa bcrypt (`password_hash()` con `PASSWORD_DEFAULT`)

## 2. servicios_gofast

### Propósito
Almacena todos los servicios de envío creados en el sistema (locales e intermunicipales).

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | bigint(20) UNSIGNED | ID único del servicio | PRIMARY KEY, AUTO_INCREMENT |
| `fecha` | datetime | Fecha y hora de creación | DEFAULT current_timestamp() |
| `nombre_cliente` | varchar(120) | Nombre del cliente | NOT NULL |
| `telefono_cliente` | varchar(20) | Teléfono del cliente | NOT NULL |
| `direccion_origen` | varchar(255) | Dirección de origen | NOT NULL |
| `destinos` | longtext | JSON con origen y destinos | NOT NULL |
| `montos` | longtext | JSON con montos (opcional) | NULL |
| `total` | int(11) | Total del servicio en pesos | NOT NULL |
| `estado` | varchar(30) | Estado del servicio | DEFAULT 'pendiente' |
| `mensajero_id` | bigint(20) UNSIGNED | ID del mensajero asignado | NULL |
| `user_id` | bigint(20) UNSIGNED | ID del usuario cliente | NULL |
| `tracking_estado` | enum('pendiente','asignado','en_ruta','entregado','cancelado') | Estado de tracking | DEFAULT 'pendiente' |

### Índices
- PRIMARY KEY: `id`

### Relaciones
- `mensajero_id` → `usuarios_gofast.id`
- `user_id` → `usuarios_gofast.id`

### Estructura JSON de `destinos`
```json
{
  "origen": {
    "barrio_id": 75,
    "barrio_nombre": "Academia Militar",
    "sector_id": "21",
    "direccion": "Calle 25"
  },
  "destinos": [
    {
      "barrio_id": 287,
      "barrio_nombre": "Aeropuerto",
      "sector_id": "80",
      "direccion": "calle 25",
      "monto": 5000
    }
  ]
}
```

### Estados
- **estado:** 'pendiente', 'en_proceso', 'completada', 'cancelada'
- **tracking_estado:** 'pendiente', 'asignado', 'en_ruta', 'entregado', 'cancelado'

## 3. negocios_gofast

### Propósito
Almacena los negocios registrados por los usuarios (clientes).

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | bigint(20) UNSIGNED | ID único del negocio | PRIMARY KEY, AUTO_INCREMENT |
| `user_id` | bigint(20) UNSIGNED | ID del usuario propietario | NOT NULL |
| `nombre` | varchar(150) | Nombre del negocio | NOT NULL |
| `direccion_full` | varchar(255) | Dirección completa | NOT NULL |
| `barrio_id` | bigint(20) UNSIGNED | ID del barrio | NOT NULL |
| `sector_id` | bigint(20) UNSIGNED | ID del sector | NOT NULL |
| `tipo` | varchar(100) | Tipo de negocio | NOT NULL |
| `activo` | tinyint(1) | Estado activo/inactivo | DEFAULT 1 |
| `created_at` | datetime | Fecha de creación | DEFAULT current_timestamp() |
| `updated_at` | datetime | Fecha de última actualización | NULL |
| `whatsapp` | int(11) | Número de WhatsApp del negocio | NOT NULL (agregado en alter) |

### Índices
- PRIMARY KEY: `id`
- KEY: `idx_user` (`user_id`)
- KEY: `idx_barrio` (`barrio_id`)

### Relaciones
- `user_id` → `usuarios_gofast.id`
- `barrio_id` → `barrios.id`

### Notas
- El campo `whatsapp` se agregó en una alteración posterior
- Los negocios se usan como origen en las cotizaciones

## 4. compras_gofast

### Propósito
Almacena las compras realizadas por mensajeros (compras que hacen los mensajeros en nombre de clientes).

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | bigint(20) UNSIGNED | ID único de la compra | PRIMARY KEY, AUTO_INCREMENT |
| `mensajero_id` | bigint(20) UNSIGNED | ID del mensajero asignado | NOT NULL |
| `valor` | decimal(10,2) | Valor de la compra | NOT NULL |
| `barrio_id` | int(11) | ID del barrio destino | NOT NULL |
| `estado` | enum('pendiente','en_proceso','completada','cancelada') | Estado de la compra | DEFAULT 'pendiente' |
| `observaciones` | text | Observaciones adicionales | NULL |
| `creado_por` | bigint(20) UNSIGNED | ID del usuario que creó la compra | NULL |
| `fecha_creacion` | datetime | Fecha de creación | DEFAULT current_timestamp() |
| `fecha_actualizacion` | datetime | Fecha de última actualización | DEFAULT current_timestamp() ON UPDATE |

### Índices
- PRIMARY KEY: `id`
- KEY: `mensajero_id`
- KEY: `creado_por`
- KEY: `barrio_id`
- KEY: `estado`
- KEY: `fecha_creacion`
- KEY compuesto: `idx_mensajero_estado` (`mensajero_id`, `estado`)
- KEY compuesto: `idx_fecha_estado` (`fecha_creacion`, `estado`)

### Relaciones
- `mensajero_id` → `usuarios_gofast.id` (ON DELETE CASCADE)
- `creado_por` → `usuarios_gofast.id` (ON DELETE SET NULL)
- `barrio_id` → `barrios.id` (ON DELETE RESTRICT)

### Estados
- **pendiente:** Compra creada, esperando procesamiento
- **en_proceso:** Compra en proceso
- **completada:** Compra completada
- **cancelada:** Compra cancelada

### Notas
- El valor debe estar entre $6,000 y $20,000 (validación en aplicación)
- Las compras se pueden crear por mensajeros o admins

## 5. transferencias_gofast

### Propósito
Almacena las solicitudes de transferencia de dinero de mensajeros.

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | bigint(20) UNSIGNED | ID único de la transferencia | PRIMARY KEY, AUTO_INCREMENT |
| `mensajero_id` | bigint(20) UNSIGNED | ID del mensajero solicitante | NOT NULL |
| `valor` | decimal(10,2) | Valor a transferir | NOT NULL |
| `estado` | enum('pendiente','aprobada','rechazada') | Estado de la transferencia | DEFAULT 'pendiente' |
| `creado_por` | bigint(20) UNSIGNED | ID del usuario que creó la solicitud | NOT NULL |
| `observaciones` | text | Observaciones (puede ser del admin) | NULL |
| `fecha_creacion` | datetime | Fecha de creación | DEFAULT current_timestamp() |
| `fecha_actualizacion` | datetime | Fecha de última actualización | DEFAULT current_timestamp() ON UPDATE |

### Índices
- PRIMARY KEY: `id`
- KEY: `mensajero_id`
- KEY: `creado_por`
- KEY: `estado`

### Relaciones
- `mensajero_id` → `usuarios_gofast.id` (ON DELETE CASCADE)
- `creado_por` → `usuarios_gofast.id` (ON DELETE CASCADE)

### Estados
- **pendiente:** Solicitud creada, esperando aprobación
- **aprobada:** Transferencia aprobada por admin
- **rechazada:** Transferencia rechazada por admin

### Notas
- Solo los mensajeros pueden crear solicitudes
- Solo los admins pueden aprobar/rechazar

## 6. tarifas

### Propósito
Almacena las tarifas base entre sectores para calcular precios de servicios locales.

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | int(11) | ID único de la tarifa | PRIMARY KEY, AUTO_INCREMENT |
| `origen_sector_id` | int(11) | ID del sector de origen | NOT NULL |
| `destino_sector_id` | int(11) | ID del sector de destino | NOT NULL |
| `precio` | int(11) | Precio en pesos colombianos | NOT NULL |

### Índices
- PRIMARY KEY: `id`

### Relaciones
- `origen_sector_id` → `sectores.id`
- `destino_sector_id` → `sectores.id`

### Notas
- Esta tabla contiene miles de registros (combinaciones de sectores)
- Se usa para calcular el precio base de servicios locales
- Los precios son en pesos colombianos (int)

## 7. sectores

### Propósito
Almacena los sectores de la ciudad (divisiones geográficas).

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | int(11) | ID único del sector | PRIMARY KEY, AUTO_INCREMENT |
| `nombre` | varchar(100) | Nombre del sector | NOT NULL |

### Índices
- PRIMARY KEY: `id`

### Relaciones
- Referenciada por: `barrios.sector_id`
- Referenciada por: `tarifas.origen_sector_id`, `tarifas.destino_sector_id`

### Notas
- Los sectores se usan para calcular tarifas
- Hay aproximadamente 80 sectores

## 8. barrios

### Propósito
Almacena los barrios de la ciudad y su sector asociado.

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | int(11) | ID único del barrio | PRIMARY KEY, AUTO_INCREMENT |
| `nombre` | varchar(100) | Nombre del barrio | NOT NULL |
| `sector_id` | int(11) | ID del sector al que pertenece | NOT NULL |

### Índices
- PRIMARY KEY: `id`

### Relaciones
- `sector_id` → `sectores.id`

### Notas
- Hay aproximadamente 377 barrios
- Cada barrio pertenece a un sector
- Se usan para seleccionar origen y destinos en cotizaciones

## 9. destinos_intermunicipales

### Propósito
Almacena los destinos intermunicipales predefinidos con sus valores fijos.

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | int(11) | ID único del destino | PRIMARY KEY, AUTO_INCREMENT |
| `nombre` | varchar(100) | Nombre del destino | NOT NULL |
| `valor` | int(11) | Valor fijo en pesos colombianos | NOT NULL |
| `activo` | tinyint(1) | Estado activo/inactivo | DEFAULT 1 |
| `orden` | int(11) | Orden de visualización | DEFAULT 0 |
| `created_at` | datetime | Fecha de creación | DEFAULT current_timestamp() |
| `updated_at` | datetime | Fecha de última actualización | DEFAULT current_timestamp() ON UPDATE |

### Índices
- PRIMARY KEY: `id`
- KEY: `activo`
- KEY: `orden`

### Notas
- Cada destino tiene un valor fijo (no se calcula por sectores)
- El campo `orden` se usa para ordenar en dropdowns
- Ejemplos: Andalucía ($20,000), Bugalagrande ($25,000), Buga ($35,000), etc.

## 10. recargos

### Propósito
Almacena los recargos configurados del sistema (lluvia, volumen, peso, etc.).

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | int(11) | ID único del recargo | PRIMARY KEY, AUTO_INCREMENT |
| `slug` | varchar(50) | Slug único del recargo | NOT NULL, UNIQUE |
| `nombre` | varchar(100) | Nombre del recargo | NOT NULL |
| `tipo` | enum('fijo','por_valor','por_volumen_peso') | Tipo de recargo | NOT NULL |
| `valor_fijo` | int(11) | Valor fijo (si tipo es 'fijo') | DEFAULT 0 |
| `activo` | tinyint(1) | Estado activo/inactivo | DEFAULT 1 |

### Índices
- PRIMARY KEY: `id`
- UNIQUE: `slug`

### Relaciones
- Referenciada por: `recargos_rangos.recargo_id`

### Tipos de Recargos
- **fijo:** Valor fijo que se suma siempre
- **por_valor:** Recargo según rangos de valor (configurado en `recargos_rangos`)
- **por_volumen_peso:** Recargos fijos seleccionables manualmente

### Notas
- El tipo `por_volumen_peso` se agregó en una alteración posterior
- Los recargos se aplican durante la cotización

## 11. recargos_rangos

### Propósito
Almacena los rangos de valores para recargos tipo 'por_valor'.

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | int(11) | ID único del rango | PRIMARY KEY, AUTO_INCREMENT |
| `recargo_id` | int(11) | ID del recargo | NOT NULL |
| `monto_min` | int(11) | Monto mínimo del rango | NOT NULL |
| `monto_max` | int(11) | Monto máximo del rango (0 = sin límite) | DEFAULT 0 |
| `recargo` | int(11) | Valor del recargo para este rango | NOT NULL |

### Índices
- PRIMARY KEY: `id`
- KEY: `recargo_id`

### Relaciones
- `recargo_id` → `recargos.id` (ON DELETE CASCADE)

### Notas
- Si `monto_max` es 0, significa que no hay límite superior
- Ejemplo: Si el servicio vale entre $3,500 y $4,500, aplicar recargo de $500

## 12. solicitudes_trabajo

### Propósito
Almacena las solicitudes de trabajo de usuarios que quieren ser mensajeros.

### Estructura

| Campo | Tipo | Descripción | Restricciones |
|-------|------|-------------|---------------|
| `id` | bigint(20) UNSIGNED | ID único de la solicitud | PRIMARY KEY, AUTO_INCREMENT |
| `nombre` | varchar(150) | Nombre del solicitante | NOT NULL |
| `whatsapp` | varchar(20) | Número de WhatsApp | NOT NULL |
| `email` | varchar(100) | Correo electrónico | NULL |
| `pregunta1` | text | Experiencia en reparto | NOT NULL |
| `pregunta2` | text | Disponibilidad de tiempo | NOT NULL |
| `pregunta3` | text | Vehículo propio | NOT NULL |
| `pregunta4` | text | Tipo de motocicleta | NOT NULL |
| `pregunta5` | text | Ciudad de residencia | NOT NULL |
| `archivo_cv` | varchar(255) | Ruta del archivo CV subido | NULL |
| `nombre_archivo` | varchar(255) | Nombre original del archivo | NULL |
| `estado` | enum('pendiente','revisado','contactado','rechazado') | Estado de la solicitud | DEFAULT 'pendiente' |
| `notas` | text | Notas internas del admin | NULL |
| `created_at` | datetime | Fecha de creación | DEFAULT current_timestamp() |
| `updated_at` | datetime | Fecha de última actualización | NULL |

### Índices
- PRIMARY KEY: `id`
- KEY: `idx_estado` (`estado`)
- KEY: `idx_created_at` (`created_at`)

### Estados
- **pendiente:** Solicitud nueva, sin revisar
- **revisado:** Solicitud revisada por admin
- **contactado:** Admin contactó al solicitante
- **rechazado:** Solicitud rechazada

### Notas
- Los CVs se suben a `/wp-content/uploads/gofast-cvs/`
- Solo admins pueden ver y gestionar estas solicitudes
- El campo `notas` es solo para uso interno de admins

## 13. Resumen de Relaciones

```
usuarios_gofast (1) ──→ (N) negocios_gofast
usuarios_gofast (1) ──→ (N) servicios_gofast (como user_id)
usuarios_gofast (1) ──→ (N) servicios_gofast (como mensajero_id)
usuarios_gofast (1) ──→ (N) compras_gofast (como mensajero_id)
usuarios_gofast (1) ──→ (N) compras_gofast (como creado_por)
usuarios_gofast (1) ──→ (N) transferencias_gofast (como mensajero_id)
usuarios_gofast (1) ──→ (N) transferencias_gofast (como creado_por)

sectores (1) ──→ (N) barrios
sectores (1) ──→ (N) tarifas (como origen_sector_id)
sectores (1) ──→ (N) tarifas (como destino_sector_id)

barrios (1) ──→ (N) negocios_gofast
barrios (1) ──→ (N) compras_gofast

recargos (1) ──→ (N) recargos_rangos
```

## 14. Archivos SQL de Alteración

### usuarios_gofast_alter_remember_token.sql
Agrega el campo `remember_token` para soportar sesiones persistentes.

### usuarios_gofast_alter_reset_password.sql
Agrega los campos `reset_token` y `reset_token_expires` para recuperación de contraseña.

### negocios_gofast_alter_whatsapp.sql
Agrega el campo `whatsapp` a la tabla de negocios.

### recargos_alter_volumen_peso.sql
Modifica el enum `tipo` en `recargos` para agregar la opción `'por_volumen_peso'`.

## 15. Consideraciones de Diseño

1. **Normalización:** Las tablas están normalizadas para evitar redundancia
2. **Índices:** Se han creado índices en campos frecuentemente consultados
3. **Foreign Keys:** Se usan para mantener integridad referencial
4. **JSON:** Se usa JSON en `servicios_gofast.destinos` para flexibilidad
5. **Enums:** Se usan enums para estados con valores fijos
6. **Timestamps:** Se usan campos de fecha para auditoría


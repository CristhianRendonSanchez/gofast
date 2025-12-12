# Flujo: Cotizar - Cliente / Mensajero / Admin

## Descripción General

Este flujo documenta el proceso de cotización de servicios locales (dentro de la ciudad) para los tres tipos de usuarios: clientes, mensajeros y administradores.

## Archivos Involucrados

- `gofast_cotizar.php` - Cotizador para clientes
- `gofast_mensajero_cotizar.php` - Cotizador para mensajeros
- `gofast_admin_cotizar.php` - Cotizador para administradores
- `gofast_solicitar_mensajero.php` - Formulario de solicitud (clientes)
- `gofast_confirmacion.php` - Página de confirmación

## 1. Flujo Cliente

### 1.1 Acceso
- **URL:** `/cotizar`
- **Shortcode:** `[gofast_cotizar]`
- **Archivo:** `gofast_cotizar.php`

### 1.2 Proceso

#### Paso 1: Selección de Origen y Destinos

1. **Usuario accede a `/cotizar`**
   - Puede estar logueado o no
   - Si está logueado, muestra sus negocios registrados

2. **Selección de origen:**
   - Opción 1: Seleccionar de sus negocios (si está logueado)
   - Opción 2: Seleccionar barrio manualmente
   - Opción 3: Ingresar dirección manual

3. **Selección de destinos:**
   - Puede agregar múltiples destinos
   - Cada destino: barrio + dirección
   - Botón "Agregar destino" para múltiples

4. **Cálculo de cotización:**
   - Busca tarifa en tabla `tarifas` (origen_sector_id → destino_sector_id)
   - Aplica recargos si aplican (lluvia, volumen, peso, etc.)
   - Calcula total por destino
   - Suma todos los destinos

#### Paso 2: Resumen y Solicitud

1. **Usuario hace clic en "Cotizar"**
   - Muestra resumen de cotización
   - Lista origen y todos los destinos con precios
   - Muestra total

2. **Usuario hace clic en "Solicitar Mensajero"**
   - Redirige a `/solicitar-mensajero` (POST con datos)

3. **Formulario de solicitud (`gofast_solicitar_mensajero.php`):**
   - Si no está logueado: pide nombre y teléfono
   - Si está logueado: usa datos de sesión
   - Muestra resumen de cotización
   - Botón "Confirmar Solicitud"

4. **Creación de servicio:**
   - Inserta en `servicios_gofast`:
     - `nombre_cliente` - Nombre del cliente
     - `telefono_cliente` - Teléfono
     - `direccion_origen` - Dirección origen
     - `destinos` - JSON con todos los destinos
     - `total` - Total calculado
     - `estado` - 'pendiente'
     - `user_id` - ID del usuario (si está logueado)
     - `tracking_estado` - 'pendiente'
   - Redirige a `/confirmacion`

5. **Confirmación (`gofast_confirmacion.php`):**
   - Muestra detalles del servicio creado
   - Muestra número de seguimiento
   - Opciones: Ver mis pedidos, Cotizar otro

### 1.3 Características Especiales

- **Negocios:** Si el usuario tiene negocios registrados, puede seleccionarlos como origen
- **Múltiples destinos:** Permite agregar varios destinos en una sola cotización
- **Recargos:** Se aplican automáticamente según configuración

## 2. Flujo Mensajero

### 2.1 Acceso
- **URL:** `/mensajero-cotizar`
- **Shortcode:** `[gofast_mensajero_cotizar]`
- **Archivo:** `gofast_mensajero_cotizar.php`
- **Requisito:** Debe estar logueado como mensajero o admin

### 2.2 Proceso

#### Paso 1: Selección de Origen y Destinos

1. **Mensajero accede a `/mensajero-cotizar`**
   - Valida que sea mensajero o admin
   - Muestra formulario de cotización

2. **Selección de origen:**
   - Puede seleccionar de TODOS los negocios registrados (no solo los suyos)
   - O seleccionar barrio manualmente
   - O ingresar dirección manual

3. **Selección de destinos:**
   - Múltiples destinos permitidos
   - Cada destino: barrio + dirección

4. **Cálculo de cotización:**
   - Mismo proceso que cliente
   - Busca tarifas y aplica recargos

#### Paso 2: Resumen Editable

1. **Mensajero hace clic en "Cotizar"**
   - Muestra resumen editable
   - Puede eliminar destinos sin recotizar
   - Puede agregar destinos nuevos (sin recotizar, usa tarifa base)
   - Muestra total actualizado

2. **Opciones:**
   - **Aceptar:** Crea servicio y se asigna automáticamente al mensajero
   - **Rechazar:** Cancela y vuelve a cotizar

3. **Si acepta:**
   - Crea servicio en `servicios_gofast`:
     - `mensajero_id` - ID del mensajero actual (asignado automáticamente)
     - `estado` - 'pendiente'
     - `tracking_estado` - 'pendiente'
   - Si el origen es un negocio, detecta el `user_id` del negocio
   - Redirige a confirmación

### 2.3 Características Especiales

- **Asignación automática:** El servicio se asigna al mensajero que lo crea
- **Resumen editable:** Puede modificar destinos después de cotizar
- **Acceso a todos los negocios:** Puede seleccionar cualquier negocio como origen

## 3. Flujo Admin

### 3.1 Acceso
- **URL:** `/admin-cotizar`
- **Shortcode:** `[gofast_admin_cotizar]`
- **Archivo:** `gofast_admin_cotizar.php`
- **Requisito:** Debe estar logueado como admin

### 3.2 Proceso

#### Paso 1: Selección de Mensajero, Origen y Destinos

1. **Admin accede a `/admin-cotizar`**
   - Valida que sea admin
   - Muestra formulario de cotización

2. **Selección de mensajero:**
   - Dropdown con todos los mensajeros activos
   - Campo obligatorio

3. **Selección de origen:**
   - Puede seleccionar de TODOS los negocios
   - O seleccionar barrio manualmente
   - O ingresar dirección manual

4. **Selección de destinos:**
   - Múltiples destinos permitidos
   - Cada destino: barrio + dirección

5. **Cálculo de cotización:**
   - Mismo proceso que cliente/mensajero
   - Busca tarifas y aplica recargos

#### Paso 2: Resumen Editable

1. **Admin hace clic en "Cotizar"**
   - Muestra resumen editable
   - Puede eliminar/agregar destinos
   - Muestra total

2. **Opciones:**
   - **Aceptar:** Crea servicio y lo asigna al mensajero seleccionado
   - **Rechazar:** Cancela

3. **Si acepta:**
   - Crea servicio en `servicios_gofast`:
     - `mensajero_id` - ID del mensajero seleccionado
     - `estado` - 'pendiente'
     - `tracking_estado` - 'pendiente'
   - Si el origen es un negocio, detecta el `user_id` del negocio
   - Redirige a confirmación

### 3.3 Características Especiales

- **Asignación manual:** Admin selecciona qué mensajero asignar
- **Resumen editable:** Puede modificar destinos después de cotizar
- **Control total:** Puede crear servicios para cualquier cliente/mensajero

## 4. Cálculo de Tarifas

### 4.1 Proceso

1. **Obtener sector de origen:**
   - Si es barrio → busca `barrios.sector_id`
   - Si es negocio → busca `negocios_gofast.sector_id`

2. **Obtener sector de destino:**
   - Busca `barrios.sector_id` del destino

3. **Buscar tarifa:**
   ```sql
   SELECT precio FROM tarifas 
   WHERE origen_sector_id = ? 
   AND destino_sector_id = ?
   ```

4. **Aplicar recargos:**
   - Recargo por lluvia (si está activo)
   - Recargo por volumen/peso (si aplica)
   - Recargos por valor (rangos)

5. **Calcular total:**
   - Suma tarifa base + recargos
   - Multiplica por cantidad de destinos
   - Suma todos los destinos

### 4.2 Recargos

- **Por lluvia:** Se aplica si está activo en `recargos`
- **Por valor:** Rangos definidos en `recargos_rangos`
- **Por volumen/peso:** Recargos fijos seleccionables manualmente

## 5. Estructura de Datos

### 5.1 Servicio Creado (`servicios_gofast`)

```json
{
  "fecha": "2025-01-15 10:30:00",
  "nombre_cliente": "Juan Pérez",
  "telefono_cliente": "3001234567",
  "direccion_origen": "Calle 25 # 6-23",
  "destinos": {
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
  },
  "total": 9000,
  "estado": "pendiente",
  "mensajero_id": 4,
  "user_id": 1,
  "tracking_estado": "pendiente"
}
```

## 6. Diagrama de Flujo

```
CLIENTE:
Usuario → /cotizar → Seleccionar Origen/Destinos → Cotizar
                                                          ↓
                                    Resumen → Solicitar Mensajero
                                                          ↓
                                    Formulario Datos → Confirmar
                                                          ↓
                                    Crear Servicio → Confirmación

MENSAJERO:
Mensajero → /mensajero-cotizar → Seleccionar Origen/Destinos → Cotizar
                                                                  ↓
                                            Resumen Editable → Aceptar/Rechazar
                                                                  ↓ Aceptar
                                            Crear Servicio (auto-asignado) → Confirmación

ADMIN:
Admin → /admin-cotizar → Seleccionar Mensajero → Seleccionar Origen/Destinos → Cotizar
                                                                                    ↓
                                                        Resumen Editable → Aceptar/Rechazar
                                                                                    ↓ Aceptar
                                                        Crear Servicio (asignado a mensajero) → Confirmación
```

## 7. Tablas de Base de Datos Utilizadas

- **servicios_gofast:** Almacena los servicios creados
- **tarifas:** Tarifas entre sectores
- **barrios:** Barrios y sus sectores
- **sectores:** Sectores de la ciudad
- **negocios_gofast:** Negocios registrados
- **recargos:** Recargos configurados
- **recargos_rangos:** Rangos de recargos por valor
- **usuarios_gofast:** Usuarios (clientes, mensajeros, admins)

## 8. Estados del Servicio

- **estado:** 'pendiente', 'en_proceso', 'completada', 'cancelada'
- **tracking_estado:** 'pendiente', 'asignado', 'en_ruta', 'entregado', 'cancelado'

## 9. Diferencias entre Flujos

| Característica | Cliente | Mensajero | Admin |
|---------------|---------|-----------|-------|
| Requiere login | No | Sí | Sí |
| Puede ver negocios | Solo los suyos | Todos | Todos |
| Asignación mensajero | Manual (admin) | Automática (sí mismo) | Manual (selecciona) |
| Resumen editable | No | Sí | Sí |
| Puede eliminar destinos | No | Sí | Sí |
| Puede agregar destinos | No | Sí | Sí |


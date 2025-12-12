# Flujo: Cotizar Intermunicipal - Cliente / Mensajero / Admin

## Descripción General

Este flujo documenta el proceso de cotización de servicios intermunicipales (envíos fuera de la ciudad) para los tres tipos de usuarios: clientes, mensajeros y administradores.

## Archivos Involucrados

- `gofast_cotizar_intermunicipal.php` - Cotizador intermunicipal para clientes
- `gofast_mensajero_cotizar_intermunicipal.php` - Cotizador intermunicipal para mensajeros
- `gofast_admin_cotizar_intermunicipal.php` - Cotizador intermunicipal para administradores
- `gofast_solicitar_intermunicipal.php` - Formulario de solicitud (clientes)
- `gofast_admin_solicitar_intermunicipal.php` - Formulario admin para crear servicios intermunicipales

## 1. Flujo Cliente

### 1.1 Acceso
- **URL:** `/cotizar-intermunicipal`
- **Shortcode:** `[gofast_cotizar_intermunicipal]`
- **Archivo:** `gofast_cotizar_intermunicipal.php`

### 1.2 Proceso

#### Paso 1: Selección de Origen y Destino Intermunicipal

1. **Usuario accede a `/cotizar-intermunicipal`**
   - Puede estar logueado o no
   - Si está logueado, muestra sus negocios registrados

2. **Selección de origen:**
   - Opción 1: Seleccionar de sus negocios (si está logueado)
   - Opción 2: Seleccionar barrio manualmente
   - Opción 3: Ingresar dirección manual

3. **Selección de destino intermunicipal:**
   - Dropdown con destinos predefinidos en `destinos_intermunicipales`
   - Ejemplos: Andalucía, Bugalagrande, Riofrío, Buga, San Pedro, etc.
   - Cada destino tiene un valor fijo

4. **Cálculo de cotización:**
   - Busca valor del destino en `destinos_intermunicipales.valor`
   - Aplica recargos si aplican
   - Muestra total

#### Paso 2: Solicitud

1. **Usuario hace clic en "Cotizar"**
   - Muestra resumen de cotización
   - Origen y destino con precio
   - Total

2. **Usuario hace clic en "Solicitar Servicio"**
   - Redirige a `/solicitar-intermunicipal` (POST con datos)

3. **Formulario de solicitud (`gofast_solicitar_intermunicipal.php`):**
   - Si no está logueado: pide nombre y teléfono
   - Si está logueado: usa datos de sesión
   - Muestra resumen de cotización
   - Botón "Confirmar Solicitud"

4. **Creación de servicio:**
   - Inserta en `servicios_gofast`:
     - `nombre_cliente` - Nombre del cliente
     - `telefono_cliente` - Teléfono
     - `direccion_origen` - Dirección origen
     - `destinos` - JSON con destino intermunicipal
     - `total` - Total calculado
     - `estado` - 'pendiente'
     - `user_id` - ID del usuario (si está logueado)
     - `tracking_estado` - 'pendiente'
   - Redirige a `/confirmacion`

### 1.3 Características Especiales

- **Destinos fijos:** Los destinos están predefinidos en la BD
- **Valores fijos:** Cada destino tiene un valor fijo (no se calcula por sectores)
- **Un solo destino:** Solo permite un destino por servicio

## 2. Flujo Mensajero

### 2.1 Acceso
- **URL:** `/mensajero-cotizar-intermunicipal`
- **Shortcode:** `[gofast_mensajero_cotizar_intermunicipal]`
- **Archivo:** `gofast_mensajero_cotizar_intermunicipal.php`
- **Requisito:** Debe estar logueado como mensajero o admin

### 2.2 Proceso

#### Paso 1: Selección de Origen y Destino

1. **Mensajero accede a `/mensajero-cotizar-intermunicipal`**
   - Valida que sea mensajero o admin
   - Muestra formulario de cotización

2. **Selección de origen:**
   - Puede seleccionar de TODOS los negocios registrados
   - O seleccionar barrio manualmente
   - O ingresar dirección manual

3. **Selección de destino intermunicipal:**
   - Dropdown con destinos predefinidos
   - Cada destino muestra su valor

4. **Cálculo de cotización:**
   - Busca valor del destino
   - Aplica recargos si aplican

#### Paso 2: Resumen y Aceptación

1. **Mensajero hace clic en "Cotizar"**
   - Muestra resumen
   - Origen y destino con precio
   - Total

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
- **Acceso a todos los negocios:** Puede seleccionar cualquier negocio como origen

## 3. Flujo Admin

### 3.1 Acceso
- **URL:** `/admin-cotizar-intermunicipal`
- **Shortcode:** `[gofast_admin_cotizar_intermunicipal]`
- **Archivo:** `gofast_admin_cotizar_intermunicipal.php`
- **Requisito:** Debe estar logueado como admin

### 3.2 Proceso

#### Paso 1: Selección de Mensajero, Origen y Destino

1. **Admin accede a `/admin-cotizar-intermunicipal`**
   - Valida que sea admin
   - Muestra formulario de cotización

2. **Selección de mensajero:**
   - Dropdown con todos los mensajeros activos
   - Campo obligatorio

3. **Selección de origen:**
   - Puede seleccionar de TODOS los negocios
   - O seleccionar barrio manualmente
   - O ingresar dirección manual

4. **Selección de destino intermunicipal:**
   - Dropdown con destinos predefinidos
   - Cada destino muestra su valor

5. **Cálculo de cotización:**
   - Busca valor del destino
   - Aplica recargos si aplican

#### Paso 2: Resumen y Aceptación

1. **Admin hace clic en "Cotizar"**
   - Muestra resumen
   - Mensajero seleccionado
   - Origen y destino con precio
   - Total

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
- **Control total:** Puede crear servicios para cualquier cliente/mensajero

## 4. Formulario Admin Directo

### 4.1 Acceso
- **URL:** `/admin-solicitar-intermunicipal`
- **Shortcode:** `[gofast_admin_solicitar_intermunicipal]`
- **Archivo:** `gofast_admin_solicitar_intermunicipal.php`
- **Requisito:** Debe estar logueado como admin

### 4.2 Proceso

1. **Admin accede a `/admin-solicitar-intermunicipal`**
   - Formulario completo para crear servicio intermunicipal
   - Campos: mensajero, origen, destino, datos del cliente

2. **Completa formulario:**
   - Selecciona mensajero
   - Selecciona origen (negocio o manual)
   - Selecciona destino intermunicipal
   - Ingresa datos del cliente (nombre, teléfono)

3. **Crea servicio:**
   - Valida todos los campos
   - Calcula total
   - Crea servicio en `servicios_gofast`
   - Asigna al mensajero seleccionado
   - Redirige a confirmación

## 5. Cálculo de Tarifas Intermunicipales

### 5.1 Proceso

1. **Obtener valor del destino:**
   ```sql
   SELECT valor FROM destinos_intermunicipales 
   WHERE id = ? AND activo = 1
   ```

2. **Aplicar recargos:**
   - Recargo por lluvia (si está activo)
   - Recargos por valor (rangos)
   - Recargos por volumen/peso (si aplican)

3. **Calcular total:**
   - Suma valor base + recargos

### 5.2 Destinos Predefinidos

Los destinos están en la tabla `destinos_intermunicipales`:
- Cada destino tiene un `valor` fijo
- Tienen un campo `orden` para ordenar en el dropdown
- Campo `activo` para habilitar/deshabilitar

## 6. Estructura de Datos

### 6.1 Servicio Intermunicipal Creado (`servicios_gofast`)

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
    "destino_intermunicipal": {
      "id": 4,
      "nombre": "Buga",
      "valor": 35000
    }
  },
  "total": 35000,
  "estado": "pendiente",
  "mensajero_id": 4,
  "user_id": 1,
  "tracking_estado": "pendiente"
}
```

## 7. Diagrama de Flujo

```
CLIENTE:
Usuario → /cotizar-intermunicipal → Seleccionar Origen/Destino → Cotizar
                                                                      ↓
                                            Resumen → Solicitar Servicio
                                                                      ↓
                                            Formulario Datos → Confirmar
                                                                      ↓
                                            Crear Servicio → Confirmación

MENSAJERO:
Mensajero → /mensajero-cotizar-intermunicipal → Seleccionar Origen/Destino → Cotizar
                                                                                  ↓
                                            Resumen → Aceptar/Rechazar
                                                                                  ↓ Aceptar
                                            Crear Servicio (auto-asignado) → Confirmación

ADMIN:
Admin → /admin-cotizar-intermunicipal → Seleccionar Mensajero → Seleccionar Origen/Destino → Cotizar
                                                                                                      ↓
                                                        Resumen → Aceptar/Rechazar
                                                                                                      ↓ Aceptar
                                                        Crear Servicio (asignado a mensajero) → Confirmación
```

## 8. Tablas de Base de Datos Utilizadas

- **servicios_gofast:** Almacena los servicios creados
- **destinos_intermunicipales:** Destinos predefinidos con valores fijos
- **barrios:** Barrios y sus sectores (para origen)
- **negocios_gofast:** Negocios registrados (para origen)
- **recargos:** Recargos configurados
- **recargos_rangos:** Rangos de recargos por valor
- **usuarios_gofast:** Usuarios (clientes, mensajeros, admins)

## 9. Diferencias con Cotización Local

| Característica | Local | Intermunicipal |
|---------------|-------|----------------|
| Destinos | Múltiples barrios | Un solo destino predefinido |
| Cálculo tarifa | Por sectores (tabla tarifas) | Valor fijo por destino |
| Múltiples destinos | Sí | No |
| Resumen editable (mensajero) | Sí | No |
| Tabla de tarifas | `tarifas` (origen_sector_id → destino_sector_id) | `destinos_intermunicipales` (valor fijo) |

## 10. Estados del Servicio

- **estado:** 'pendiente', 'en_proceso', 'completada', 'cancelada'
- **tracking_estado:** 'pendiente', 'asignado', 'en_ruta', 'entregado', 'cancelado'

## 11. Gestión de Destinos Intermunicipales

Los destinos intermunicipales se gestionan desde:
- **Admin:** Puede agregar/editar destinos (requiere acceso directo a BD o panel admin)
- **Valores:** Cada destino tiene un valor fijo en pesos colombianos
- **Orden:** Campo `orden` para ordenar en el dropdown
- **Activo:** Campo `activo` para habilitar/deshabilitar destinos


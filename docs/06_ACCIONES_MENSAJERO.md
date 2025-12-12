# Acciones Propias de Mensajero

## Descripción General

Este documento lista todas las funcionalidades y acciones disponibles exclusivamente para usuarios con rol "mensajero" en el sistema GoFast.

## Archivos Relacionados

- `gofast_mensajero_cotizar.php` - Cotización rápida
- `gofast_mensajero_cotizar_intermunicipal.php` - Cotización intermunicipal
- `gofast_compras.php` - Gestión de compras
- `gofast_transferencias.php` - Solicitud de transferencias
- `mis-pedidos.php` - Visualización de servicios asignados

## 1. Cotización y Aceptación de Servicios

### 1.1 Cotización Rápida Local
- **URL:** `/mensajero-cotizar`
- **Shortcode:** `[gofast_mensajero_cotizar]`
- **Funcionalidad:**
  - Cotizar servicios locales
  - Seleccionar origen de TODOS los negocios (no solo los suyos)
  - Agregar múltiples destinos
  - Resumen editable (puede eliminar/agregar destinos sin recotizar)
  - Aceptar servicio → se asigna automáticamente a sí mismo
  - Rechazar servicio → cancela y vuelve a cotizar

### 1.2 Cotización Intermunicipal
- **URL:** `/mensajero-cotizar-intermunicipal`
- **Shortcode:** `[gofast_mensajero_cotizar_intermunicipal]`
- **Funcionalidad:**
  - Cotizar servicios intermunicipales
  - Seleccionar origen de TODOS los negocios
  - Seleccionar destino intermunicipal predefinido
  - Aceptar servicio → se asigna automáticamente a sí mismo
  - Rechazar servicio → cancela

### 1.3 Características Especiales
- **Asignación automática:** Al aceptar, el servicio se asigna al mensajero que lo crea
- **Acceso ampliado:** Puede ver y seleccionar todos los negocios registrados
- **Resumen editable:** En cotización local, puede modificar destinos después de cotizar

## 2. Gestión de Compras

### 2.1 Crear Compras
- **URL:** `/compras`
- **Shortcode:** `[gofast_compras]`
- **Funcionalidad:**
  - Crear nuevas compras
  - Asignarse a sí mismo automáticamente
  - Campos:
    - Valor (entre $6,000 y $20,000)
    - Barrio destino
    - Observaciones (opcional)
  - Estados: pendiente, en_proceso, completada, cancelada

### 2.2 Ver Mis Compras
- **Funcionalidad:**
  - Lista todas sus compras
  - Filtros por estado
  - Ver detalles de cada compra
  - Cambiar estado de sus propias compras
  - Ver fecha de creación y última actualización

### 2.3 Características
- **Auto-asignación:** Las compras se asignan automáticamente al mensajero que las crea
- **Límites:** Valor mínimo $6,000, máximo $20,000
- **Estados:** Puede cambiar el estado de sus compras

## 3. Solicitud de Transferencias

### 3.1 Crear Transferencia
- **URL:** `/transferencias`
- **Shortcode:** `[gofast_transferencias]`
- **Funcionalidad:**
  - Solicitar transferencia de dinero
  - Campos:
    - Valor a transferir
    - Observaciones (opcional)
  - Estados: pendiente, aprobada, rechazada

### 3.2 Ver Mis Transferencias
- **Funcionalidad:**
  - Lista todas sus transferencias
  - Ver estado de cada transferencia
  - Ver fecha de creación
  - Ver observaciones del admin (si las hay)

### 3.3 Características
- **Solo solicitar:** El mensajero solo puede solicitar, no aprobar
- **Aprobación admin:** Solo los admins pueden aprobar/rechazar
- **Historial:** Puede ver todas sus transferencias pasadas

## 4. Visualización de Servicios

### 4.1 Mis Pedidos
- **URL:** `/mis-pedidos`
- **Shortcode:** `[gofast_pedidos]`
- **Funcionalidad:**
  - Ver servicios asignados a él
  - Filtrar por estado
  - Ver detalles completos:
    - Cliente
    - Origen y destinos
    - Total
    - Estado de tracking
    - Fecha de creación

### 4.2 Estados de Tracking
- **pendiente:** Servicio creado, esperando asignación
- **asignado:** Servicio asignado al mensajero
- **en_ruta:** Mensajero en camino
- **entregado:** Servicio completado
- **cancelado:** Servicio cancelado

### 4.3 Características
- **Solo asignados:** Ve solo los servicios asignados a él
- **Información completa:** Ve todos los detalles del servicio
- **Filtros:** Puede filtrar por estado de tracking

## 5. Resumen de Permisos

### 5.1 Puede Hacer
- ✅ Cotizar servicios (local e intermunicipal)
- ✅ Aceptar servicios y auto-asignarse
- ✅ Crear compras y asignarse a sí mismo
- ✅ Solicitar transferencias
- ✅ Ver sus servicios asignados
- ✅ Ver sus compras
- ✅ Ver sus transferencias
- ✅ Cambiar estado de sus compras
- ✅ Ver todos los negocios registrados

### 5.2 No Puede Hacer
- ❌ Asignar servicios a otros mensajeros
- ❌ Aprobar/rechazar transferencias
- ❌ Ver servicios de otros mensajeros
- ❌ Ver compras de otros mensajeros
- ❌ Gestionar usuarios
- ❌ Gestionar negocios de otros
- ❌ Configurar recargos
- ❌ Ver reportes administrativos

## 6. Flujo de Trabajo Típico

### 6.1 Aceptar y Completar Servicio

1. **Mensajero accede a `/mensajero-cotizar`**
2. **Cotiza servicio:**
   - Selecciona origen (negocio o manual)
   - Agrega destinos
   - Ve resumen
3. **Acepta servicio:**
   - Servicio se crea y se asigna automáticamente
   - Estado: `tracking_estado = 'asignado'`
4. **Completa servicio:**
   - Admin o sistema cambia estado a `'en_ruta'` → `'entregado'`

### 6.2 Crear y Gestionar Compra

1. **Mensajero accede a `/compras`**
2. **Crea compra:**
   - Ingresa valor ($6,000 - $20,000)
   - Selecciona barrio destino
   - Agrega observaciones (opcional)
3. **Gestiona compra:**
   - Puede cambiar estado: pendiente → en_proceso → completada
   - Puede cancelar si es necesario

### 6.3 Solicitar Transferencia

1. **Mensajero accede a `/transferencias`**
2. **Solicita transferencia:**
   - Ingresa valor a transferir
   - Agrega observaciones (opcional)
3. **Espera aprobación:**
   - Estado inicial: `'pendiente'`
   - Admin aprueba o rechaza
   - Mensajero ve el resultado

## 7. Tablas de Base de Datos Utilizadas

- **servicios_gofast:** Servicios asignados al mensajero
- **compras_gofast:** Compras creadas por el mensajero
- **transferencias_gofast:** Transferencias solicitadas
- **negocios_gofast:** Todos los negocios (para seleccionar origen)
- **barrios:** Barrios para seleccionar destinos
- **destinos_intermunicipales:** Destinos intermunicipales
- **tarifas:** Tarifas para calcular precios

## 8. Consideraciones Importantes

1. **Auto-asignación:** Los servicios y compras se asignan automáticamente al mensajero que los crea
2. **Acceso a negocios:** Puede ver todos los negocios, no solo los suyos
3. **Límites de compras:** Valor mínimo $6,000, máximo $20,000
4. **Transferencias:** Solo puede solicitar, no aprobar
5. **Servicios:** Solo ve los asignados a él, no todos los servicios del sistema


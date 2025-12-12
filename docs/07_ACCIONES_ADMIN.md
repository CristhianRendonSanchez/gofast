# Acciones Propias de Admin

## Descripción General

Este documento lista todas las funcionalidades y acciones disponibles exclusivamente para usuarios con rol "admin" en el sistema GoFast.

## Archivos Relacionados

- `gofast_dashboard_admin.php` - Dashboard principal
- `gofast_admin_cotizar.php` - Cotización con asignación de mensajero
- `gofast_admin_cotizar_intermunicipal.php` - Cotización intermunicipal
- `gofast_admin_solicitar_intermunicipal.php` - Crear servicio intermunicipal
- `gofast_usuarios_admin.php` - Gestión de usuarios
- `gofast_admin_negocios.php` - Gestión de negocios
- `gofast_recargos_admin.php` - Gestión de recargos
- `gofast_reportes_admin.php` - Reportes y estadísticas
- `gofast_admin_configuracion.php` - Configuración del sistema
- `gofast_admin_solicitudes_trabajo.php` - Gestión de solicitudes de trabajo
- `gofast_compras.php` - Gestión de compras (admin)
- `gofast_transferencias.php` - Gestión de transferencias (admin)

## 1. Dashboard Administrativo

### 1.1 Acceso
- **URL:** `/admin` o `/dashboard-admin`
- **Shortcode:** `[gofast_dashboard_admin]`
- **Funcionalidad:**
  - Panel de control principal
  - Estadísticas generales:
    - Total de servicios
    - Servicios pendientes
    - Servicios en proceso
    - Servicios completados
    - Total de ingresos
    - Mensajeros activos
    - Clientes activos
  - Gráficos y visualizaciones
  - Accesos rápidos a módulos

## 2. Cotización y Asignación de Servicios

### 2.1 Cotización Local con Asignación
- **URL:** `/admin-cotizar`
- **Shortcode:** `[gofast_admin_cotizar]`
- **Funcionalidad:**
  - Seleccionar mensajero a asignar
  - Seleccionar origen (todos los negocios o manual)
  - Agregar múltiples destinos
  - Resumen editable
  - Aceptar → crea servicio y asigna al mensajero seleccionado
  - Control total sobre asignación

### 2.2 Cotización Intermunicipal
- **URL:** `/admin-cotizar-intermunicipal`
- **Shortcode:** `[gofast_admin_cotizar_intermunicipal]`
- **Funcionalidad:**
  - Seleccionar mensajero a asignar
  - Seleccionar origen
  - Seleccionar destino intermunicipal
  - Aceptar → crea servicio y asigna al mensajero

### 2.3 Crear Servicio Intermunicipal Directo
- **URL:** `/admin-solicitar-intermunicipal`
- **Shortcode:** `[gofast_admin_solicitar_intermunicipal]`
- **Funcionalidad:**
  - Formulario completo para crear servicio intermunicipal
  - Seleccionar mensajero
  - Seleccionar origen
  - Seleccionar destino
  - Ingresar datos del cliente
  - Crear servicio directamente

## 3. Gestión de Usuarios

### 3.1 Acceso
- **URL:** `/admin-usuarios`
- **Shortcode:** `[gofast_usuarios_admin]`
- **Funcionalidad:**
  - Ver todos los usuarios del sistema
  - Crear nuevos usuarios:
    - Nombre, teléfono, email
    - Contraseña
    - Rol (cliente, mensajero, admin)
  - Editar usuarios existentes
  - Activar/desactivar usuarios
  - Cambiar rol de usuarios
  - Ver historial de usuarios

### 3.2 Características
- **Control total:** Puede crear, editar y eliminar usuarios
- **Cambio de roles:** Puede cambiar el rol de cualquier usuario
- **Activación/desactivación:** Puede activar o desactivar usuarios

## 4. Gestión de Negocios

### 4.1 Acceso
- **URL:** `/admin-negocios`
- **Shortcode:** `[gofast_admin_negocios]`
- **Funcionalidad:**
  - Ver todos los negocios registrados
  - Ver detalles de cada negocio:
    - Nombre
    - Dirección
    - Barrio y sector
    - Tipo de negocio
    - WhatsApp
    - Usuario propietario
  - Editar negocios
  - Activar/desactivar negocios
  - Buscar negocios

### 4.2 Características
- **Vista global:** Ve todos los negocios, no solo los suyos
- **Gestión completa:** Puede editar cualquier negocio
- **Control de activación:** Puede activar/desactivar negocios

## 5. Gestión de Recargos

### 5.1 Acceso
- **URL:** `/admin-recargos`
- **Shortcode:** `[gofast_recargos_admin]`
- **Funcionalidad:**
  - Ver todos los recargos configurados
  - Crear nuevos recargos:
    - Tipo: fijo, por_valor, por_volumen_peso
    - Nombre y slug
    - Valor fijo (si aplica)
    - Rangos de valores (si aplica)
  - Editar recargos existentes
  - Activar/desactivar recargos
  - Configurar rangos de recargos por valor

### 5.2 Tipos de Recargos
- **Fijo:** Valor fijo que se suma siempre
- **Por valor:** Recargos según rangos de valor del servicio
- **Por volumen/peso:** Recargos fijos seleccionables manualmente

### 5.3 Características
- **Configuración flexible:** Múltiples tipos de recargos
- **Rangos:** Puede configurar rangos de valores con diferentes recargos
- **Activación:** Puede activar/desactivar recargos sin eliminarlos

## 6. Reportes y Estadísticas

### 6.1 Acceso
- **URL:** `/admin-reportes`
- **Shortcode:** `[gofast_reportes_admin]`
- **Funcionalidad:**
  - Reportes de servicios:
    - Por fecha
    - Por mensajero
    - Por cliente
    - Por estado
    - Por tipo (local/intermunicipal)
  - Reportes de ingresos:
    - Total de ingresos
    - Por período
    - Por mensajero
    - Por cliente
  - Reportes de mensajeros:
    - Servicios completados
    - Ingresos generados
    - Compras realizadas
    - Transferencias
  - Exportar reportes (CSV, PDF)
  - Gráficos y visualizaciones

### 6.2 Características
- **Múltiples filtros:** Filtros por fecha, usuario, estado, etc.
- **Exportación:** Puede exportar reportes en diferentes formatos
- **Visualizaciones:** Gráficos y tablas interactivas

## 7. Configuración del Sistema

### 7.1 Acceso
- **URL:** `/admin-configuracion`
- **Shortcode:** `[gofast_admin_configuracion]`
- **Funcionalidad:**
  - Configurar parámetros generales del sistema
  - Configurar tarifas base
  - Configurar destinos intermunicipales
  - Configurar sectores y barrios
  - Configurar opciones de email
  - Configurar opciones de notificaciones
  - Backup y restauración

### 7.2 Características
- **Configuración centralizada:** Todas las configuraciones en un solo lugar
- **Cambios en tiempo real:** Los cambios se aplican inmediatamente

## 8. Gestión de Solicitudes de Trabajo

### 8.1 Acceso
- **URL:** `/admin-solicitudes-trabajo`
- **Shortcode:** `[gofast_admin_solicitudes_trabajo]`
- **Funcionalidad:**
  - Ver todas las solicitudes de trabajo
  - Filtrar por estado:
    - pendiente
    - revisado
    - contactado
    - rechazado
  - Ver detalles completos:
    - Nombre y contacto
    - Respuestas a preguntas
    - CV adjunto (si hay)
  - Cambiar estado de solicitudes
  - Agregar notas internas
  - Descargar CVs

### 8.2 Características
- **Gestión completa:** Puede gestionar todo el proceso de selección
- **Notas internas:** Puede agregar notas que solo ven los admins
- **Descarga de CVs:** Puede descargar los CVs subidos

## 9. Gestión de Compras (Admin)

### 9.1 Funcionalidades Adicionales
- **Ver todas las compras:** No solo las suyas
- **Asignar compras:** Puede crear compras y asignarlas a cualquier mensajero
- **Cambiar estado:** Puede cambiar el estado de cualquier compra
- **Filtros avanzados:** Por mensajero, estado, fecha, etc.

## 10. Gestión de Transferencias (Admin)

### 10.1 Funcionalidades
- **Ver todas las transferencias:** De todos los mensajeros
- **Aprobar transferencias:** Puede aprobar o rechazar transferencias
- **Agregar observaciones:** Puede agregar notas a las transferencias
- **Filtros:** Por mensajero, estado, fecha, etc.

## 11. Resumen de Permisos

### 11.1 Puede Hacer
- ✅ Ver dashboard con estadísticas
- ✅ Cotizar servicios y asignar a cualquier mensajero
- ✅ Crear servicios directamente
- ✅ Gestionar usuarios (crear, editar, activar/desactivar, cambiar roles)
- ✅ Gestionar negocios (ver todos, editar, activar/desactivar)
- ✅ Configurar recargos
- ✅ Ver y exportar reportes
- ✅ Configurar sistema
- ✅ Gestionar solicitudes de trabajo
- ✅ Ver todas las compras y transferencias
- ✅ Aprobar/rechazar transferencias
- ✅ Ver todos los servicios del sistema
- ✅ Cambiar estados de servicios
- ✅ Acceder a todas las funcionalidades del sistema

### 11.2 No Puede Hacer
- ❌ Nada (tiene acceso completo al sistema)

## 12. Flujo de Trabajo Típico

### 12.1 Asignar Servicio a Mensajero

1. **Admin accede a `/admin-cotizar`**
2. **Selecciona mensajero** del dropdown
3. **Cotiza servicio:**
   - Selecciona origen
   - Agrega destinos
   - Ve resumen
4. **Acepta:**
   - Servicio se crea
   - Se asigna al mensajero seleccionado
   - Estado: `tracking_estado = 'asignado'`

### 12.2 Gestionar Usuario

1. **Admin accede a `/admin-usuarios`**
2. **Crea/edita usuario:**
   - Completa datos
   - Selecciona rol
   - Activa/desactiva
3. **Guarda cambios**

### 12.3 Configurar Recargo

1. **Admin accede a `/admin-recargos`**
2. **Crea recargo:**
   - Selecciona tipo
   - Configura valores/rangos
   - Activa
3. **Guarda**

### 12.4 Aprobar Transferencia

1. **Admin accede a `/transferencias`**
2. **Ve solicitudes pendientes**
3. **Revisa solicitud:**
   - Ve valor
   - Ve observaciones del mensajero
4. **Aprueba o rechaza:**
   - Agrega observaciones (opcional)
   - Cambia estado

## 13. Tablas de Base de Datos Utilizadas

- **usuarios_gofast:** Gestión de usuarios
- **negocios_gofast:** Gestión de negocios
- **servicios_gofast:** Todos los servicios
- **compras_gofast:** Todas las compras
- **transferencias_gofast:** Todas las transferencias
- **recargos:** Configuración de recargos
- **recargos_rangos:** Rangos de recargos
- **tarifas:** Tarifas base
- **destinos_intermunicipales:** Destinos intermunicipales
- **solicitudes_trabajo:** Solicitudes de trabajo
- **barrios:** Barrios
- **sectores:** Sectores

## 14. Consideraciones Importantes

1. **Acceso total:** El admin tiene acceso a todas las funcionalidades
2. **Asignación manual:** Puede asignar servicios a cualquier mensajero
3. **Gestión completa:** Puede gestionar usuarios, negocios, recargos, etc.
4. **Reportes:** Acceso a reportes detallados del sistema
5. **Configuración:** Puede configurar todos los aspectos del sistema


# 📚 GoFast - Documentación de Funcionalidades por Rol

Documentación completa y detallada de todas las funcionalidades disponibles en el sistema GoFast según el rol del usuario.

---

## 📋 Índice

1. [Visitante (Sin Login)](#1-visitante-sin-login)
2. [Cliente](#2-cliente)
3. [Mensajero](#3-mensajero)
4. [Administrador](#4-administrador)
5. [Flujos de Trabajo](#5-flujos-de-trabajo)
6. [Estados de Pedidos](#6-estados-de-pedidos)

---

## 1. Visitante (Sin Login)

### 🎯 Descripción
Usuario no autenticado que puede acceder a funcionalidades básicas del sistema sin necesidad de crear una cuenta.

### ✅ Funcionalidades Disponibles

#### 1.1. Cotizar Envíos
**URL:** `/cotizar`  
**Shortcode:** `[gofast_cotizar]`

**Descripción:**
- Formulario para cotizar servicios de mensajería
- Selección de origen (barrio o negocio si tiene cuenta)
- Selección de destinos (múltiples destinos permitidos)
- Búsqueda inteligente de barrios con Select2
- Cálculo automático de tarifas y recargos

**Características:**
- ✅ Búsqueda de barrios sin tildes
- ✅ Autocompletado inteligente
- ✅ Múltiples destinos adicionales
- ✅ Priorización de barrios de negocios (si tiene cuenta)
- ✅ Guardado de última cotización en sesión

**Flujo:**
1. Seleccionar barrio de origen
2. Seleccionar primer destino
3. (Opcional) Agregar destinos adicionales
4. Hacer clic en "Cotizar 🚀"
5. Redirige a `/solicitar-mensajero` con los datos

---

#### 1.2. Ver Resultado de Cotización
**URL:** `/solicitar-mensajero`  
**Shortcode:** `[gofast_resultado]`

**Descripción:**
- Muestra el detalle completo de la cotización
- Lista todos los trayectos con sus precios
- Muestra recargos aplicados (fijos y por valor)
- Formulario para completar datos del servicio
- Autocompletado de datos si tiene cuenta

**Información Mostrada:**
- 📍 Origen y destinos
- 💰 Valor base por trayecto
- 📊 Recargos aplicados (si los hay)
- 💵 Total final del servicio

**Formulario de Datos:**
- Nombre del cliente
- WhatsApp (teléfono)
- Dirección de origen (con historial si tiene cuenta)
- Direcciones de destino (opcionales)
- Montos a pagar por destino (opcionales)

**Flujo:**
1. Revisar cotización
2. Completar datos del servicio
3. Hacer clic en "Solicitar servicio"
4. Redirige a `/servicio-registrado?id=XXX`

---

#### 1.3. Confirmación de Servicio
**URL:** `/servicio-registrado?id=XXX`  
**Shortcode:** `[gofast_confirmacion]`

**Descripción:**
- Página de confirmación después de solicitar un servicio
- Muestra número de servicio
- Botón para confirmar por WhatsApp
- Detalles del cliente y servicio
- Opción para hacer otra cotización

**Características:**
- ✅ Vinculación automática de usuario por teléfono (si existe cuenta)
- ✅ Botón directo a WhatsApp con mensaje prellenado
- ✅ Lista de destinos con montos
- ✅ Resumen del cliente

**Acciones Disponibles:**
- 📱 Confirmar por WhatsApp
- 🔄 Hacer otra cotización
- 👤 Crear cuenta (si no está logueado)
- 📦 Ver mis pedidos (si está logueado)

---

#### 1.4. Autenticación
**URL:** `/auth`  
**Shortcode:** `[gofast_auth]`

**Descripción:**
- Formulario de login y registro
- Modo según parámetro `?registro=1`
- Sesiones persistentes con cookies (30 días)

**Login:**
- Email o WhatsApp como usuario
- Contraseña
- Checkbox "Mantener sesión iniciada 30 días"
- Enlace a registro

**Registro:**
- Nombre completo
- WhatsApp
- Email
- Contraseña (mínimo 6 caracteres)
- Repetir contraseña
- Enlace a login

---

### 🚫 Limitaciones del Visitante

- ❌ No puede ver historial de pedidos
- ❌ No puede gestionar negocios
- ❌ No puede tomar pedidos como mensajero
- ❌ No puede acceder al panel administrativo
- ❌ No puede modificar estados de pedidos

---

## 2. Cliente

### 🎯 Descripción
Usuario autenticado con rol "cliente" que puede gestionar sus pedidos y negocios.

### ✅ Funcionalidades Disponibles

#### 2.1. Cotizar Envíos (Mejorado)
**URL:** `/cotizar`  
**Shortcode:** `[gofast_cotizar]`

**Ventajas sobre Visitante:**
- ✅ Autocompletado de origen desde negocios registrados
- ✅ Priorización de barrios de sus negocios
- ✅ Guardado de última cotización
- ✅ Historial de direcciones usadas

**Características Especiales:**
- Los negocios registrados aparecen primero en el selector de origen
- Si tiene negocios, el origen se pre-selecciona automáticamente
- Direcciones previas disponibles en formulario de solicitud

---

#### 2.2. Registrar y Gestionar Negocios
**URL:** `/mi-negocio`  
**Shortcode:** `[gofast_registro_negocio]`

**Descripción:**
- Gestión completa de múltiples negocios
- Crear, editar y eliminar negocios
- Los negocios se usan para autocompletar en cotizaciones

**Funcionalidades:**

**Listado de Negocios:**
- Tabla con todos los negocios del usuario
- Columnas: Nombre, Dirección, Barrio, Acciones
- Botón "Registrar nuevo negocio"

**Crear Negocio:**
- Nombre del negocio
- Tipo de negocio (Restaurante, Tienda, Cafetería, Papelería, Farmacia, Otro)
- Campo "Otro" personalizado si selecciona "Otro"
- Barrio (con Select2 para búsqueda)
- Dirección completa
- WhatsApp del negocio (opcional)

**Editar Negocio:**
- Mismo formulario que crear
- Acceso mediante `?edit=XXX`
- Guarda cambios y redirige al listado

**Eliminar Negocio:**
- Confirmación antes de eliminar
- Acceso mediante `?delete=XXX`
- Eliminación permanente

**Beneficios:**
- Los negocios aparecen en el selector de origen al cotizar
- Autocompletado de datos (nombre, teléfono, dirección)
- Priorización de barrios de negocios en búsquedas

---

#### 2.3. Seguimiento de Pedidos
**URL:** `/mis-pedidos`  
**Shortcode:** `[gofast_pedidos]`

**Descripción:**
- Listado completo de todos sus pedidos
- Filtros avanzados
- Vista detallada de cada pedido
- Estados en tiempo real

**Información Mostrada:**
- # ID del pedido
- 📅 Fecha y hora
- 👤 Nombre del cliente
- 📱 Teléfono
- 📍 Origen (barrio)
- 🎯 Destinos (barrios)
- 🚚 Mensajero asignado
- 💰 Total
- 📊 Estado actual
- 👁️ Ver detalles

**Filtros Disponibles:**
- Estado: Todos, Pendiente, Asignado, En Ruta, Entregado, Cancelado
- Búsqueda: Por nombre o teléfono
- Rango de fechas: Desde / Hasta
- Paginación: 15 pedidos por página

**Estados Visibles:**
- ⏳ Pendiente
- 👤 Asignado
- 🚚 En Ruta
- ✅ Entregado
- ❌ Cancelado

**Acciones:**
- Ver detalles del pedido (enlace a `/servicio-registrado?id=XXX`)
- Los estados son solo lectura (no puede modificarlos)

---

#### 2.4. Ver Detalles de Pedido
**URL:** `/servicio-registrado?id=XXX`  
**Shortcode:** `[gofast_confirmacion]`

**Mismo que Visitante, pero con:**
- ✅ Acceso directo a "Ver mis pedidos"
- ✅ Información completa del pedido
- ✅ Historial de todos sus servicios

---

### 🎨 Menú de Navegación (Cliente)

El menú superior muestra:
- 📦 **Mis pedidos** (botón principal)
- 🏪 **Mi negocio**
- 🛵 **Nuevo envío**
- 🚪 **Salir**

---

### 🚫 Limitaciones del Cliente

- ❌ No puede tomar pedidos como mensajero
- ❌ No puede modificar estados de pedidos
- ❌ No puede asignar mensajeros
- ❌ No puede acceder al panel administrativo
- ❌ No puede ver pedidos de otros clientes

---

## 3. Mensajero

### 🎯 Descripción
Usuario autenticado con rol "mensajero" que puede tomar y gestionar pedidos asignados.

### ✅ Funcionalidades Disponibles

#### 3.1. Ver y Tomar Pedidos
**URL:** `/mis-pedidos`  
**Shortcode:** `[gofast_pedidos]`

**Descripción:**
- Listado de pedidos pendientes (disponibles para tomar)
- Listado de pedidos asignados a él
- Auto-asignación al cambiar estado
- Cambio de estados de pedidos asignados

**Vista de Pedidos:**
- Ve pedidos con estado "pendiente" (disponibles)
- Ve pedidos asignados a él (cualquier estado)
- No ve pedidos de otros mensajeros

**Tomar Pedido:**
- Al cambiar el estado de un pedido "pendiente", se auto-asigna
- El sistema automáticamente asigna el mensajero al cambiar estado
- Puede tomar múltiples pedidos

**Cambiar Estados:**
- Puede cambiar estados de pedidos asignados a él
- Estados disponibles: Pendiente, Asignado, En Ruta, Entregado, Cancelado
- Cambio inmediato mediante dropdown

**Información Visible:**
- # ID del pedido
- 📅 Fecha
- 👤 Cliente
- 📱 Teléfono
- 📍 Origen y destinos
- 💰 Total
- 📊 Estado (editable)
- 👁️ Ver detalles

**Filtros:**
- Estado
- Búsqueda por nombre/teléfono
- Rango de fechas

---

#### 3.2. Cotizar Pedido
**URL:** `/cotizar`  
**Shortcode:** `[gofast_cotizar]`

**Descripción:**
- Misma funcionalidad que cliente/visitante
- Puede cotizar envíos para uso personal
- No tiene acceso a gestión de negocios

**Características:**
- ✅ Cotización completa
- ✅ Múltiples destinos
- ✅ Cálculo de tarifas y recargos
- ❌ No puede registrar negocios

---

#### 3.3. Ver Detalles de Pedido
**URL:** `/servicio-registrado?id=XXX`  
**Shortcode:** `[gofast_confirmacion]`

**Descripción:**
- Acceso a detalles completos del pedido
- Información del cliente
- Rutas y destinos
- Montos

---

### 🚧 Módulos Pendientes de Crear

#### 3.4. Módulo "Nuevo" (Por Crear)
**Descripción:**
- Módulo específico para mensajeros
- Funcionalidad a definir

**Sugerencias:**
- Crear pedidos manualmente
- Registro de entregas
- Notas y comentarios
- Fotos de entrega

---

#### 3.5. Revisión de Pedidos (Por Crear)
**Descripción:**
- Módulo de revisión y gestión avanzada de pedidos
- Funcionalidad a definir

**Sugerencias:**
- Vista de mapa de pedidos
- Ruta optimizada
- Historial de entregas
- Estadísticas personales
- Calificaciones recibidas

---

### 🎨 Menú de Navegación (Mensajero)

El menú superior muestra:
- 🚚 **Mis servicios** (botón principal)
- 🚪 **Salir**

**Nota:** El menú actualmente apunta a `/mis-servicios`, pero la funcionalidad está en `/mis-pedidos`. Esto puede requerir ajuste.

---

### 🚫 Limitaciones del Mensajero

- ❌ No puede gestionar negocios
- ❌ No puede asignar pedidos a otros mensajeros
- ❌ No puede ver pedidos de otros mensajeros (excepto pendientes)
- ❌ No puede modificar pedidos no asignados a él
- ❌ No puede acceder al panel administrativo
- ❌ No puede gestionar usuarios
- ❌ No puede configurar recargos

---

## 4. Administrador

### 🎯 Descripción
Usuario con rol "admin" que tiene acceso completo al sistema y todas las funcionalidades administrativas.

### ✅ Funcionalidades Disponibles

#### 4.1. Dashboard Administrativo
**URL:** `/dashboard-admin`  
**Shortcode:** `[gofast_dashboard_admin]`

**Descripción:**
- Panel principal con estadísticas en tiempo real
- Accesos rápidos a todas las secciones
- Vista general del sistema

**Estadísticas Mostradas:**
- 📦 **Total Pedidos**: Contador de todos los servicios
- ⏳ **Pendientes**: Pedidos sin asignar
- 🚚 **En Ruta**: Pedidos en proceso
- ✅ **Entregados**: Pedidos completados
- 👥 **Total Usuarios**: Todos los usuarios activos
- 🛒 **Clientes**: Usuarios con rol cliente
- 🚚 **Mensajeros**: Usuarios con rol mensajero
- 💰 **Ingresos Totales**: Suma de pedidos entregados
- 📅 **Pedidos Hoy**: Contador del día actual

**Enlaces Rápidos:**
- 📦 Gestión de Pedidos
- 👥 Gestión de Usuarios
- ⚙️ Administración de Recargos
- 📊 Reportes y Estadísticas

---

#### 4.2. Gestión de Pedidos
**URL:** `/mis-pedidos`  
**Shortcode:** `[gofast_pedidos]`

**Descripción:**
- Vista completa de TODOS los pedidos del sistema
- Asignación de mensajeros
- Cambio de estados
- Filtros avanzados

**Funcionalidades Especiales:**

**Asignar Mensajeros:**
- Dropdown con lista de mensajeros activos
- Asignación inmediata al seleccionar
- Puede cambiar mensajero en cualquier momento
- Opción "Sin asignar" para desasignar

**Cambiar Estados:**
- Puede cambiar estado de cualquier pedido
- Estados: Pendiente, Asignado, En Ruta, Entregado, Cancelado
- Cambio inmediato mediante dropdown

**Vista Completa:**
- Ve pedidos de todos los clientes
- Ve pedidos de todos los mensajeros
- Acceso a detalles completos

**Filtros:**
- Estado
- Mensajero (solo admin)
- Búsqueda por nombre/teléfono
- Rango de fechas
- Paginación (15 por página)

---

#### 4.3. Gestión de Usuarios
**URL:** `/admin-usuarios`  
**Shortcode:** `[gofast_usuarios_admin]`

**Descripción:**
- Crear, editar y desactivar usuarios
- Cambiar roles y contraseñas
- Gestión completa del sistema de usuarios

**Funcionalidades:**

**Crear Usuario:**
- Nombre completo
- Teléfono
- Email
- Contraseña (mínimo 6 caracteres)
- Rol: Cliente, Mensajero, Administrador
- Estado: Activo/Inactivo

**Editar Usuarios:**
- Edición en línea en tabla
- Campos editables:
  - Nombre
  - Email
  - Teléfono
  - Rol
  - Estado (activo/inactivo)
  - Contraseña (opcional, solo si se completa)

**Desactivar Usuarios:**
- Botón "Desactivar" por usuario
- Confirmación antes de desactivar
- No puede desactivarse a sí mismo
- Desactivación = poner activo = 0

**Filtros:**
- Rol: Todos, Cliente, Mensajero, Admin
- Estado: Todos, Activo, Inactivo
- Búsqueda: Por nombre, email o teléfono
- Paginación: 20 usuarios por página

**Características:**
- Protección: No puede desactivarse a sí mismo
- Validación: Verifica duplicados (email/teléfono)
- Seguridad: Nonces para todas las acciones

---

#### 4.4. Administración de Recargos
**URL:** `/recargos`  
**Shortcode:** `[gofast_recargos_admin]`

**Descripción:**
- Configurar recargos fijos y por valor
- Gestionar rangos de recargos variables
- Activar/desactivar recargos

**Tipos de Recargos:**

**Recargos Fijos:**
- Valor fijo que se aplica a cada trayecto
- Ejemplo: "Recargo nocturno" = $2,000 por envío
- Se suma al valor base de la tarifa

**Recargos por Valor:**
- Recargos que dependen del valor del trayecto
- Configurados por rangos de monto
- Ejemplo: "Recargo por lluvia"
  - Si el trayecto vale $0 - $10,000 → recargo $1,000
  - Si el trayecto vale $10,001 - $20,000 → recargo $2,000
  - Si el trayecto vale $20,001+ → recargo $3,000

**Funcionalidades:**

**Crear Recargo Fijo:**
- Nombre del recargo
- Valor fijo (COP)
- Estado (activo/inactivo)

**Crear Recargo por Valor:**
- Nombre del recargo
- Múltiples rangos:
  - Monto mínimo
  - Monto máximo (0 = sin límite)
  - Recargo (COP)
- Estado (activo/inactivo)

**Editar Recargos:**
- Modificar nombre
- Cambiar valores
- Agregar nuevos rangos
- Editar rangos existentes
- Activar/desactivar

**Eliminar:**
- Eliminar recargo completo (y sus rangos)
- Eliminar rangos individuales
- Confirmación antes de eliminar

**Características:**
- Los recargos se aplican automáticamente en cotizaciones
- Solo recargos activos se consideran
- Múltiples recargos pueden aplicarse simultáneamente

---

#### 4.5. Reportes y Estadísticas
**URL:** `/admin-reportes`  
**Shortcode:** `[gofast_reportes_admin]`

**Descripción:**
- Reportes detallados del sistema
- Estadísticas avanzadas
- Exportación a CSV
- Análisis de rendimiento

**Estadísticas Principales:**
- 📦 Total Pedidos (según filtros)
- 💰 Ingresos Totales
- 📊 Promedio por Pedido
- ✅ Entregados
- ⏳ Pendientes
- ❌ Cancelados

**Reportes Disponibles:**

**Top Mensajeros:**
- Lista de los 10 mensajeros más activos
- Pedidos entregados
- Total de ingresos generados
- Ordenados por cantidad de entregas

**Pedidos por Día:**
- Últimos 30 días
- Cantidad de pedidos por día
- Ingresos por día
- Gráfica de tendencias

**Filtros Avanzados:**
- Estado: Todos, Pendiente, Asignado, En Ruta, Entregado, Cancelado
- Rango de fechas: Desde / Hasta
- Mensajero: Todos o específico
- Búsqueda: Por cliente o teléfono

**Exportar a CSV:**
- Botón "Exportar CSV"
- Incluye todos los datos filtrados
- Formato compatible con Excel
- BOM UTF-8 para caracteres especiales

**Columnas del CSV:**
- ID
- Fecha
- Cliente
- Teléfono
- Origen
- Total
- Estado
- Mensajero

---

#### 4.6. Cotizar Envíos
**URL:** `/cotizar`  
**Shortcode:** `[gofast_cotizar]`

**Descripción:**
- Misma funcionalidad que otros roles
- Puede crear envíos desde el panel central
- Útil para registrar pedidos telefónicos

---

### 🎨 Menú de Navegación (Admin)

El menú superior muestra:
- 📊 **Panel admin** (botón principal)
- 👥 **Usuarios**
- 🚪 **Salir**

**Nota:** El menú actualmente apunta a `/usuarios`, pero la página real es `/admin-usuarios`. Esto puede requerir ajuste.

---

### 🔐 Permisos Especiales del Admin

- ✅ Acceso completo a todas las funcionalidades
- ✅ Puede ver todos los pedidos
- ✅ Puede asignar mensajeros
- ✅ Puede cambiar estados de cualquier pedido
- ✅ Puede crear y gestionar usuarios
- ✅ Puede configurar recargos
- ✅ Puede ver reportes y estadísticas
- ✅ Puede exportar datos
- ✅ No puede desactivarse a sí mismo

---

## 5. Flujos de Trabajo

### 5.1. Flujo de Cotización y Solicitud

```
1. Usuario (cualquier rol) → /cotizar
   ↓
2. Selecciona origen y destinos
   ↓
3. Hace clic en "Cotizar"
   ↓
4. Redirige a /solicitar-mensajero
   ↓
5. Ve cotización detallada
   ↓
6. Completa datos del servicio
   ↓
7. Hace clic en "Solicitar servicio"
   ↓
8. Redirige a /servicio-registrado?id=XXX
   ↓
9. Ve confirmación y puede confirmar por WhatsApp
```

### 5.2. Flujo de Asignación de Mensajero

```
1. Cliente solicita servicio
   ↓
2. Estado: "Pendiente"
   ↓
3. Admin o Mensajero ve el pedido
   ↓
4. Admin asigna mensajero O Mensajero se auto-asigna
   ↓
5. Estado cambia a "Asignado"
   ↓
6. Mensajero cambia estado a "En Ruta"
   ↓
7. Mensajero completa entrega
   ↓
8. Estado cambia a "Entregado"
```

### 5.3. Flujo de Gestión de Negocios (Cliente)

```
1. Cliente → /mi-negocio
   ↓
2. Ve listado de negocios (si tiene)
   ↓
3. Hace clic en "Registrar nuevo negocio"
   ↓
4. Completa formulario
   ↓
5. Guarda negocio
   ↓
6. Negocio aparece en selector de origen al cotizar
```

---

## 6. Estados de Pedidos

### 📊 Estados Disponibles

| Estado | Descripción | Quién puede cambiar | Color |
|--------|-------------|---------------------|-------|
| **Pendiente** | Pedido creado, sin mensajero asignado | Admin, Mensajero (auto-asignación) | ⏳ Amarillo |
| **Asignado** | Mensajero asignado, aún no en ruta | Admin, Mensajero asignado | 👤 Azul |
| **En Ruta** | Mensajero en camino a entregar | Admin, Mensajero asignado | 🚚 Azul claro |
| **Entregado** | Pedido completado exitosamente | Admin, Mensajero asignado | ✅ Verde |
| **Cancelado** | Pedido cancelado | Admin, Mensajero asignado | ❌ Rojo |

### 🔄 Transiciones de Estado

**Flujo Normal:**
```
Pendiente → Asignado → En Ruta → Entregado
```

**Flujo con Cancelación:**
```
Cualquier estado → Cancelado
```

**Reglas:**
- Solo admin puede asignar mensajeros manualmente
- Mensajero se auto-asigna al cambiar estado de "Pendiente"
- Mensajero solo puede cambiar estados de pedidos asignados a él
- Admin puede cambiar cualquier estado de cualquier pedido

---

## 📝 Notas Importantes

### Seguridad
- Todas las acciones requieren autenticación (excepto cotizar)
- Validación de nonces en formularios
- Sanitización de datos de entrada
- Protección contra auto-eliminación (admin)

### Sesiones
- Sesiones PHP nativas
- Cookies persistentes (30 días) con checkbox
- Restauración automática de sesión desde cookie

### Base de Datos
- Todas las tablas tienen prefijo configurable
- Relaciones entre tablas bien definidas
- Índices para optimización

### Responsive
- Todas las páginas son responsive
- Menú hamburguesa en móvil
- Tablas con scroll horizontal en móvil
- Select2 optimizado para móvil

---

## 🚧 Funcionalidades Pendientes

### Para Mensajero:
1. **Módulo "Nuevo"** - Por definir
2. **Revisión de Pedidos** - Por definir
3. **Vista de Mapa** - Sugerido
4. **Ruta Optimizada** - Sugerido
5. **Estadísticas Personales** - Sugerido

### Mejoras Futuras:
- Notificaciones push
- App móvil
- Integración con GPS
- Sistema de calificaciones
- Chat en tiempo real
- Pagos en línea

---

**Última actualización:** 2025  
**Versión del documento:** 1.0


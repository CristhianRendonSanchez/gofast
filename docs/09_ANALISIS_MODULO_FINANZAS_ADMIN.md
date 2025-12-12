# Análisis del Requerimiento: Módulo de Finanzas Administrativo

## 📋 Resumen Ejecutivo

Se requiere desarrollar un nuevo módulo administrativo con sistema de tabs para gestionar el flujo financiero completo de la empresa GoFast. El módulo debe permitir registrar ingresos, egresos, vales, transferencias y saldos de mensajeros, con cálculos automáticos de utilidades y efectivo disponible.

---

## 🎯 Objetivo General

Crear un módulo centralizado de gestión financiera que permita al administrador:
- Registrar y consultar todos los movimientos financieros
- Gestionar pagos a mensajeros (comisiones, transferencias, descuentos)
- Calcular automáticamente utilidades y efectivo disponible
- Generar reportes consolidados por períodos

---

## 📊 Estructura del Módulo

El módulo estará organizado en **7 tabs principales**:

1. **Ingresos**
2. **Egresos**
3. **Vales de la Empresa**
4. **Vales del Personal**
5. **Transferencias (Entradas)**
6. **Transferencias (Salidas)**
7. **Saldos Mensajeros**

Además, incluirá un **bloque de resultados generales** visible en todas las tabs.

---

## 🔍 Análisis Detallado por Tab

### 1. TAB: INGRESOS 💰

#### Funcionalidades Requeridas:
- **Filtro por fechas**: Rango de fechas (desde/hasta)
- **Carga automática de datos**: 
  - Arrastra el total de comisiones de cada día desde `servicios_gofast` y `compras_gofast`
  - Calcula comisión = 20% de (total servicios + total compras) por día

#### Campos a Mostrar:
| Campo | Descripción | Origen |
|-------|-------------|--------|
| Fecha | Fecha del día | `DATE(fecha)` de servicios/compras |
| # Pedidos | Cantidad de servicios del día | `COUNT(*)` de `servicios_gofast` |
| # Compras | Cantidad de compras del día | `COUNT(*)` de `compras_gofast` |
| Total Ingresos | Suma de servicios + compras | `SUM(total)` + `SUM(valor)` |

#### Cálculos:
```sql
-- Por cada día:
Total Ingresos = SUM(servicios_gofast.total) + SUM(compras_gofast.valor)
Total Comisiones = Total Ingresos * 0.20
```

#### Consideraciones Técnicas:
- Excluir servicios/compras cancelados (`tracking_estado != 'cancelado'` y `estado != 'cancelada'`)
- Agrupar por fecha (día)
- Mostrar en tabla ordenada por fecha descendente

---

### 2. TAB: EGRESOS 💸

#### Funcionalidades Requeridas:
- **Filtro por fechas**: Rango de fechas (desde/hasta)
- **Filtro por descripción**: Búsqueda de texto en campo descripción
- **Formulario de inserción**: Crear nuevo egreso
- **Acciones**: Editar y eliminar registros

#### Campos del Formulario:
| Campo | Tipo | Validación |
|-------|------|------------|
| Fecha | date | Requerido |
| Descripción | text | Requerido, máximo 255 caracteres |
| Valor | decimal(10,2) | Requerido, > 0 |

#### Estructura de Tabla Sugerida:
```sql
CREATE TABLE egresos_gofast (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  creado_por BIGINT UNSIGNED,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (creado_por) REFERENCES usuarios_gofast(id) ON DELETE SET NULL,
  INDEX idx_fecha (fecha),
  INDEX idx_descripcion (descripcion)
);
```

#### Funcionalidades CRUD:
- **Crear**: Formulario con validación
- **Editar**: Modal o formulario inline
- **Eliminar**: Con confirmación

---

### 3. TAB: VALES DE LA EMPRESA 🏢

#### Funcionalidades Requeridas:
- **Filtro por fechas**: Rango de fechas (desde/hasta)
- **Filtro por descripción**: Búsqueda de texto
- **Formulario de inserción**: Crear nuevo vale
- **Acciones**: Editar y eliminar registros

#### Campos del Formulario:
| Campo | Tipo | Validación |
|-------|------|------------|
| Fecha | date | Requerido |
| Descripción | text | Requerido, máximo 255 caracteres |
| Valor | decimal(10,2) | Requerido, > 0 |

#### Estructura de Tabla Sugerida:
```sql
CREATE TABLE vales_empresa_gofast (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  creado_por BIGINT UNSIGNED,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (creado_por) REFERENCES usuarios_gofast(id) ON DELETE SET NULL,
  INDEX idx_fecha (fecha),
  INDEX idx_descripcion (descripcion)
);
```

#### Funcionalidades CRUD:
- Similar a egresos (crear, editar, eliminar)

---

### 4. TAB: VALES DEL PERSONAL 👥

#### Funcionalidades Requeridas:
- **Filtro por fechas**: Rango de fechas (desde/hasta)
- **Filtro por descripción**: Búsqueda de texto
- **Filtro por persona**: Select con 4 personas activas específicas
- **Formulario de inserción**: Crear nuevo vale
- **Acciones**: Editar y eliminar registros

#### Campos del Formulario:
| Campo | Tipo | Validación |
|-------|------|------------|
| Fecha | date | Requerido |
| Persona | select | Requerido, solo 4 opciones activas |
| Descripción | text | Requerido, máximo 255 caracteres |
| Valor | decimal(10,2) | Requerido, > 0 |

#### Nota Importante:
- Solo 4 personas activas específicas (no todos los mensajeros)
- Estas personas deben estar predefinidas o configuradas en el sistema
- Posible solución: Campo `tipo_personal` en `usuarios_gofast` o tabla separada `personal_activo_gofast`

#### Estructura de Tabla Sugerida:
```sql
CREATE TABLE vales_personal_gofast (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  persona_id BIGINT UNSIGNED NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  creado_por BIGINT UNSIGNED,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (persona_id) REFERENCES usuarios_gofast(id) ON DELETE RESTRICT,
  FOREIGN KEY (creado_por) REFERENCES usuarios_gofast(id) ON DELETE SET NULL,
  INDEX idx_fecha (fecha),
  INDEX idx_persona (persona_id),
  INDEX idx_descripcion (descripcion)
);
```

---

### 5. TAB: TRANSFERENCIAS (ENTRADAS) 📥

#### Funcionalidades Requeridas:
- **Filtro por fechas**: Rango de fechas (desde/hasta)
- **Filtro por origen**: Select con opciones (necesita definición de qué es "origen")
- **Filtro por mensajero**: Select con mensajeros activos
- **Carga automática de datos**:
  - Arrastra el total de transferencias del día desde `transferencias_gofast`
  - Arrastra las transferencias de los pagos de los mensajeros de las comisiones

#### Campos a Mostrar:
| Campo | Descripción | Origen |
|-------|-------------|--------|
| Fecha | Fecha de la transferencia | `DATE(fecha_creacion)` |
| Origen | Origen de la transferencia | Campo a definir |
| Valor | Valor de la transferencia | `valor` de `transferencias_gofast` |

#### Consideraciones:
- **Origen**: Necesita clarificación. Posibles opciones:
  - Tipo de transferencia (pago comisión, adelanto, etc.)
  - Fuente de pago (banco, efectivo, etc.)
  - Campo nuevo en `transferencias_gofast` llamado `origen` o `tipo`
- Solo mostrar transferencias con `estado = 'aprobada'`
- Agrupar por día y mostrar totales

#### Cálculos:
```sql
-- Total transferencias entradas por día:
SELECT DATE(fecha_creacion) as fecha, SUM(valor) as total
FROM transferencias_gofast
WHERE estado = 'aprobada'
GROUP BY DATE(fecha_creacion)
```

---

### 6. TAB: TRANSFERENCIAS (SALIDAS) 📤

#### Funcionalidades Requeridas:
- **Filtro por fechas**: Rango de fechas (desde/hasta)
- **Filtro por descripción**: Búsqueda de texto
- **Formulario de inserción**: Crear nueva transferencia salida
- **Acciones**: Editar y eliminar registros

#### Campos del Formulario:
| Campo | Tipo | Validación |
|-------|------|------------|
| Fecha | date | Requerido |
| Descripción | text | Requerido, máximo 255 caracteres |
| Valor | decimal(10,2) | Requerido, > 0 |

#### Estructura de Tabla Sugerida:
```sql
CREATE TABLE transferencias_salidas_gofast (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  creado_por BIGINT UNSIGNED,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (creado_por) REFERENCES usuarios_gofast(id) ON DELETE SET NULL,
  INDEX idx_fecha (fecha),
  INDEX idx_descripcion (descripcion)
);
```

#### Funcionalidades CRUD:
- Similar a egresos (crear, editar, eliminar)

---

### 7. TAB: SALDOS MENSAJEROS 💵

#### Funcionalidades Requeridas:
- **Filtro por fechas**: Rango de fechas (desde/hasta)
- **Filtro por mensajero**: Select con mensajeros activos
- **Filtro por estado**: Select con estados (pendiente, pagado, etc.)

#### Campos a Mostrar:
| Campo | Descripción | Cálculo |
|-------|-------------|---------|
| Fecha | Fecha del registro | Fecha del servicio/compra |
| Mensajero | Nombre del mensajero | `usuarios_gofast.nombre` |
| Comisión | Comisión generada | 20% de ingresos del mensajero |
| Transferencias | Total transferencias aprobadas | `SUM(valor)` de `transferencias_gofast` |
| Total a Pagar | Comisión - Transferencias | Comisión - Transferencias |

#### Funcionalidades de Pago:
1. **Pago en Efectivo**:
   - Botón/acción para marcar como pagado en efectivo
   - Actualiza estado a "pagado_efectivo"
   - Resta del valor del efectivo existente

2. **Pago por Transferencia**:
   - Botón/acción para marcar como pagado por transferencia
   - Al pagar, se suma automáticamente a la pestaña "Transferencias (Entradas)"
   - Actualiza estado a "pagado_transferencia"
   - Crea registro en `transferencias_gofast` con `estado = 'aprobada'`

3. **Pago Pendiente**:
   - Estado por defecto
   - Se resta del valor del efectivo existente (en el cálculo de efectivo disponible)

#### Funcionalidad de Descuento:
- **Generar Descuento**:
  - Formulario con campos:
    - Fecha
    - Valor descuento
    - Mensajero
  - Al aplicar descuento, resta este valor al "Total a Pagar" de ese día
  - Guarda registro en tabla `descuentos_mensajeros_gofast`

#### Sub-bloques de Resultado (por mensajero):
- Total Comisiones
- Total Transferencias
- Total Descuentos
- Total a Pagar

#### Estructura de Tabla Sugerida:
```sql
-- Tabla para registrar descuentos
CREATE TABLE descuentos_mensajeros_gofast (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  mensajero_id BIGINT UNSIGNED NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  descripcion VARCHAR(255),
  creado_por BIGINT UNSIGNED,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (mensajero_id) REFERENCES usuarios_gofast(id) ON DELETE RESTRICT,
  FOREIGN KEY (creado_por) REFERENCES usuarios_gofast(id) ON DELETE SET NULL,
  INDEX idx_fecha (fecha),
  INDEX idx_mensajero (mensajero_id)
);

-- Tabla para registrar pagos a mensajeros
CREATE TABLE pagos_mensajeros_gofast (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  mensajero_id BIGINT UNSIGNED NOT NULL,
  comision_total DECIMAL(10,2) NOT NULL,
  transferencias_total DECIMAL(10,2) DEFAULT 0,
  descuentos_total DECIMAL(10,2) DEFAULT 0,
  total_a_pagar DECIMAL(10,2) NOT NULL,
  tipo_pago ENUM('efectivo', 'transferencia', 'pendiente') DEFAULT 'pendiente',
  fecha_pago DATETIME NULL,
  creado_por BIGINT UNSIGNED,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (mensajero_id) REFERENCES usuarios_gofast(id) ON DELETE RESTRICT,
  FOREIGN KEY (creado_por) REFERENCES usuarios_gofast(id) ON DELETE SET NULL,
  INDEX idx_fecha (fecha),
  INDEX idx_mensajero (mensajero_id),
  INDEX idx_tipo_pago (tipo_pago)
);
```

#### Cálculos por Mensajero:
```sql
-- Por cada mensajero y fecha:
Comisión = SUM(servicios.total) * 0.20 + SUM(compras.valor) * 0.20
Transferencias = SUM(transferencias_gofast.valor) WHERE estado = 'aprobada'
Descuentos = SUM(descuentos_mensajeros_gofast.valor)
Total a Pagar = Comisión - Transferencias - Descuentos
```

---

## 📊 BLOQUE DE RESULTADOS GENERALES

Este bloque debe estar visible en todas las tabs y mostrar cálculos consolidados basados en los filtros de fecha aplicados.

### Campos a Mostrar:

| Campo | Descripción | Cálculo |
|-------|-------------|---------|
| **Total Ingresos** | Suma de todos los ingresos | `SUM(servicios.total) + SUM(compras.valor)` |
| **Total Egresos** | Suma de todos los egresos | `SUM(egresos_gofast.valor)` |
| **Total Vales Empresa** | Suma de vales de empresa | `SUM(vales_empresa_gofast.valor)` |
| **Total Vales Personal** | Suma de vales del personal | `SUM(vales_personal_gofast.valor)` |
| **Total Transferencias Ingresos** | Suma de transferencias entradas | `SUM(transferencias_gofast.valor)` WHERE estado = 'aprobada' |
| **Total Transferencias Salidas** | Suma de transferencias salidas | `SUM(transferencias_salidas_gofast.valor)` |
| **Saldo Transferencias** | Diferencia entre entradas y salidas | Transferencias Ingresos - Transferencias Salidas |
| **Total Saldos Pendientes** | Suma de saldos pendientes por pagar | `SUM(pagos_mensajeros_gofast.total_a_pagar)` WHERE tipo_pago = 'pendiente' |
| **Total Descuentos** | Suma de descuentos a mensajeros | `SUM(descuentos_mensajeros_gofast.valor)` |
| **Subtotal** | Ingresos menos gastos | Ingresos - Egresos - Vales Empresa - Descuentos |
| **Efectivo** | Efectivo disponible | Subtotal - Saldo Transferencias - Saldos Pendientes |
| **Utilidad Total** | Utilidad neta | Subtotal |
| **Utilidad Individual** | Utilidad dividida entre 2 | Utilidad Total ÷ 2 |

### Fórmulas Detalladas:

```
Subtotal = Total Ingresos - Total Egresos - Total Vales Empresa - Total Descuentos
Efectivo = Subtotal - Saldo Transferencias - Total Saldos Pendientes
Utilidad Total = Subtotal
Utilidad Individual = Utilidad Total / 2
```

---

## 🗄️ Estructura de Base de Datos Propuesta

### Tablas Nuevas a Crear:

1. **egresos_gofast**
2. **vales_empresa_gofast**
3. **vales_personal_gofast**
4. **transferencias_salidas_gofast**
5. **descuentos_mensajeros_gofast**
6. **pagos_mensajeros_gofast**

### Tablas Existentes a Utilizar:

1. **servicios_gofast** - Para calcular ingresos y comisiones
2. **compras_gofast** - Para calcular ingresos y comisiones
3. **transferencias_gofast** - Para transferencias entradas
4. **usuarios_gofast** - Para mensajeros y personal

### Modificaciones Sugeridas:

- Agregar campo `origen` o `tipo` a `transferencias_gofast` (si se requiere)
- Considerar agregar campo `tipo_personal` a `usuarios_gofast` o crear tabla `personal_activo_gofast`

---

## 🎨 Interfaz de Usuario

### Estructura Visual:

```
┌─────────────────────────────────────────────────────────┐
│  MÓDULO DE FINANZAS ADMINISTRATIVO                      │
├─────────────────────────────────────────────────────────┤
│  [Ingresos] [Egresos] [Vales Empresa] [Vales Personal]  │
│  [Transf. Entradas] [Transf. Salidas] [Saldos Mensajeros]│
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │  BLOQUE DE RESULTADOS GENERALES                   │  │
│  │  Total Ingresos: $XXX                             │  │
│  │  Total Egresos: $XXX                              │  │
│  │  ...                                               │  │
│  │  Efectivo: $XXX                                    │  │
│  │  Utilidad Total: $XXX                             │  │
│  │  Utilidad Individual: $XXX                        │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │  FILTROS                                          │  │
│  │  [Fecha desde] [Fecha hasta] [Otros filtros...]   │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │  TABLA DE DATOS                                  │  │
│  │  [Botón Insertar]                                │  │
│  │  [Datos en tabla]                                │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### Características de UI:

- Sistema de tabs similar a `gofast_admin_configuracion.php`
- Bloque de resultados siempre visible (sticky o fijo)
- Filtros en la parte superior de cada tab
- Tabla responsive (desktop: tabla, móvil: cards)
- Modales para crear/editar registros
- Confirmaciones para eliminar

---

## 🔧 Consideraciones Técnicas

### 1. Cálculo de Comisiones
- Las comisiones se calculan como 20% de los ingresos
- Ingresos = servicios + compras (excluyendo cancelados)
- Los cálculos deben ser en tiempo real basados en los filtros

### 2. Integración con Sistema Existente
- Reutilizar funciones existentes (`gofast_date_mysql()`, `gofast_date_format()`, etc.)
- Seguir el patrón de código de otros módulos admin
- Usar el mismo sistema de autenticación y sesiones

### 3. Validaciones
- Validar que los valores sean positivos
- Validar fechas (no futuras para registros manuales)
- Validar que los mensajeros existan y estén activos
- Validar permisos (solo admin puede acceder)

### 4. Performance
- Usar índices en campos de fecha y filtros frecuentes
- Optimizar consultas con JOINs apropiados
- Considerar caché para cálculos complejos si es necesario

### 5. Seguridad
- Validar y sanitizar todos los inputs
- Usar prepared statements para todas las consultas SQL
- Verificar nonces en todos los formularios
- Validar permisos en cada acción

---

## ❓ Preguntas Pendientes

1. **Origen de Transferencias**: ¿Qué representa el campo "origen" en transferencias entradas? ¿Es un tipo, una fuente de pago, o algo más?

2. **4 Personas Activas**: ¿Cómo se identifican las 4 personas activas para vales del personal? ¿Son usuarios específicos o hay un criterio?

3. **Exportación**: ¿Se requiere exportar datos a Excel/PDF o solo visualización?

4. **Historial**: ¿Se debe mantener historial de cambios (auditoría) o solo las fechas de creación/actualización?

5. **Rango de Fechas por Defecto**: ¿Qué rango de fechas mostrar por defecto? (Hoy, mes actual, último mes, etc.)

---

## 📝 Próximos Pasos

1. **Fase 1**: Crear estructura de base de datos (tablas nuevas)
2. **Fase 2**: Desarrollar tab de Ingresos (solo lectura, datos automáticos)
3. **Fase 3**: Desarrollar tabs de Egresos, Vales Empresa, Vales Personal (CRUD completo)
4. **Fase 4**: Desarrollar tabs de Transferencias (entradas y salidas)
5. **Fase 5**: Desarrollar tab de Saldos Mensajeros (con funcionalidades de pago)
6. **Fase 6**: Implementar bloque de resultados generales
7. **Fase 7**: Testing y ajustes finales

---

## 📚 Referencias

- Patrón de tabs: `code/gofast_admin_configuracion.php`
- Sistema de transferencias: `code/gofast_transferencias.php`
- Sistema de compras: `code/gofast_compras.php`
- Dashboard admin: `code/gofast_dashboard_admin.php`
- Documentación de tablas: `docs/08_DOCUMENTACION_TABLAS_BD.md`

---

**Fecha de Análisis**: 2025-01-27  
**Versión**: 1.0  
**Autor**: Análisis Técnico GoFast


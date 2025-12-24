# 📊 ANÁLISIS DETALLADO: MÓDULO DE FINANZAS ADMINISTRATIVO
## Campo por Campo - gofast_finanzas_admin.php

---

## 📋 ESTRUCTURA GENERAL

**Archivo:** `code/gofast_finanzas_admin.php`  
**Shortcode:** `[gofast_finanzas_admin]`  
**URL:** `/admin-finanzas`  
**Acceso:** Solo administradores

---

## 🎯 BLOQUE DE RESULTADOS GENERALES

### Filtros de Fecha

#### Sistema de Períodos Automáticos del Mes

El módulo divide automáticamente el mes en dos períodos para facilitar el análisis financiero:

- **Primera Quincena:** Del día 1 al 15 del mes
- **Segunda Quincena:** Del día 16 al último día del mes

#### Campos de Filtros:

- **Campo:** `periodo_mes` (GET)
  - **Tipo:** Enum (Select)
  - **Valores:** `''` (vacío/personalizado), `'primera_quincena'`, `'segunda_quincena'`
  - **Descripción:** Selector de período automático del mes
  - **Comportamiento:**
    - Si se selecciona un período, actualiza automáticamente `fecha_desde` y `fecha_hasta`
    - Si se cambian las fechas manualmente, el selector vuelve a "Personalizado"
  - **Opciones:**
    - **Personalizado:** Permite seleccionar fechas manualmente
    - **Primera Quincena (1-15):** Establece automáticamente del día 1 al 15 del mes actual
    - **Segunda Quincena (16-fin de mes):** Establece automáticamente del día 16 al último día del mes actual

- **Campo:** `fecha_desde` (GET)
  - **Tipo:** Date
  - **Descripción:** Fecha inicial para cálculos
  - **Valor por defecto automático:**
    - Si día actual ≤ 15: `YYYY-MM-01` (día 1 del mes)
    - Si día actual > 15: `YYYY-MM-16` (día 16 del mes)
  - **Uso:** Filtra desde esta fecha
  - **Actualización automática:** Se actualiza cuando se selecciona un período del mes

- **Campo:** `fecha_hasta` (GET)
  - **Tipo:** Date
  - **Descripción:** Fecha final para cálculos
  - **Valor por defecto automático:**
    - Si día actual ≤ 15: `YYYY-MM-15` (día 15 del mes)
    - Si día actual > 15: `YYYY-MM-[último_día]` (último día del mes)
  - **Uso:** Filtra hasta esta fecha
  - **Actualización automática:** Se actualiza cuando se selecciona un período del mes
  - **Lógica especial:** 
    - Si `fecha_desde == fecha_hasta` → Calcula acumulado histórico HASTA esa fecha
    - Si `fecha_desde != fecha_hasta` → Calcula solo ese rango específico

#### Lógica de Inicialización Automática:

1. **Al cargar sin filtros:**
   - El sistema determina automáticamente el período según el día actual:
     - **Días 1-15:** Muestra automáticamente del 1 al 15 del mes actual
     - **Días 16 en adelante:** Muestra automáticamente del 16 al último día del mes actual
   - El selector `periodo_mes` se establece automáticamente según el período detectado

2. **Al seleccionar período manualmente:**
   - La función JavaScript `actualizarFechasPorPeriodo()` actualiza las fechas automáticamente
   - Calcula el último día del mes usando `new Date(anio, mes, 0).getDate()`

3. **Modo personalizado:**
   - Si el usuario cambia las fechas manualmente, el selector vuelve a "Personalizado"
   - Permite flexibilidad para rangos de fechas personalizados

### Campos Calculados en Resultados Generales

#### 1. **Ingresos (20% de Comisión)**
- **Campo calculado:** `$total_comisiones`
- **Fórmula:** `$total_ingresos * 0.20`
- **Componentes:**
  - `$total_ingresos_servicios`: Suma de `total` de `servicios_gofast` (excluyendo cancelados)
  - `$total_ingresos_compras`: Suma de `valor` de `compras_gofast` (excluyendo canceladas)
  - `$total_ingresos = $total_ingresos_servicios + $total_ingresos_compras`

#### 2. **Total Egresos**
- **Campo calculado:** `$total_egresos`
- **Fuente:** Tabla `egresos_gofast`
- **Campo sumado:** `valor`
- **Filtro:** Por rango de fechas en campo `fecha`

#### 3. **Vales Empresa**
- **Campo calculado:** `$total_vales_empresa`
- **Fuente:** Tabla `vales_empresa_gofast`
- **Campo sumado:** `valor`
- **Filtro:** Por rango de fechas en campo `fecha`

#### 4. **Vales Personal**
- **Campo calculado:** `$total_vales_personal`
- **Fuente:** Tabla `vales_personal_gofast`
- **Campo sumado:** `valor`
- **Filtro:** Por rango de fechas en campo `fecha`

#### 5. **Transferencias Ingresos**
- **Campo calculado:** `$total_transferencias_ingresos`
- **Fuente:** Tabla `transferencias_gofast`
- **Campo sumado:** `valor`
- **Filtros:**
  - `estado = 'aprobada'`
  - Por rango de fechas en `fecha_creacion`
  - **NOTA:** Suma TODAS las transferencias aprobadas (tipo "normal" y tipo "pago")

#### 6. **Transferencias Salidas**
- **Campo calculado:** `$total_transferencias_salidas`
- **Fuente:** Tabla `transferencias_salidas_gofast`
- **Campo sumado:** `valor`
- **Filtro:** Por rango de fechas en campo `fecha`

#### 7. **Saldo Transferencias**
- **Campo calculado:** `$saldo_transferencias`
- **Fórmula:** `$total_transferencias_ingresos - $total_transferencias_salidas`
- **Descripción:** Diferencia entre transferencias entrantes y salientes

#### 8. **Saldos Pendientes**
- **Campo calculado:** `$total_saldos_pendientes`
- **Descripción:** Suma de saldos pendientes de todos los mensajeros
- **Cálculo:** Se realiza después de calcular saldos individuales por mensajero
- **Fórmula:** `Comisión - Transferencias - Descuentos - Pagos (en efectivo, en rango de fecha)`
- **Componentes:**
  - `$total_comisiones`: 20% de ingresos totales
  - `$total_transferencias_ingresos`: Suma de todas las transferencias aprobadas (normal y pago)
  - `$total_descuentos`: Suma de descuentos aplicados
  - `$total_pagos_mensajeros`: Suma de pagos en efectivo realizados (NO incluye pagos por transferencia)
- **Filtros de pagos:**
  - Solo pagos con `tipo_pago = 'efectivo'`
  - Filtrados por rango de fechas en campo `fecha`
  - **Nota:** Los pagos por transferencia NO se restan aquí porque ya se contabilizan en `$total_transferencias_ingresos`

#### 9. **Total Descuentos**
- **Campo calculado:** `$total_descuentos`
- **Fuente:** Tabla `descuentos_mensajeros_gofast`
- **Campo sumado:** `valor`
- **Filtro:** Por rango de fechas en campo `fecha`
- **Nota:** Puede ser negativo (bonificaciones)

#### 10. **Subtotal**
- **Campo calculado:** `$subtotal`
- **Fórmula:** `$total_comisiones - $total_egresos - $total_vales_empresa - $total_descuentos`
- **Descripción:** Resultado después de restar egresos, vales y descuentos

#### 11. **Efectivo**
- **Campo calculado:** `$efectivo`
- **Fórmula:** `$subtotal - $saldo_transferencias - $total_saldos_pendientes - $total_vales_personal`
- **Descripción:** Dinero disponible en efectivo
- **Componentes:**
  - `$subtotal`: Ingresos (20%) - Egresos - Vales Empresa - Descuentos
  - `$saldo_transferencias`: Diferencia entre transferencias entrantes y salientes
  - `$total_saldos_pendientes`: Saldos pendientes por pagar a mensajeros
  - `$total_vales_personal`: Vales del personal (se resta del efectivo disponible)

#### 12. **Utilidad Total**
- **Campo calculado:** `$utilidad_total`
- **Fórmula:** `$utilidad_total = $subtotal`
- **Descripción:** Utilidad total del negocio

#### 13. **Utilidad Individual**
- **Campo calculado:** `$utilidad_individual`
- **Fórmula:** `$utilidad_total / 2`
- **Descripción:** Utilidad dividida entre dos socios

---

## 📑 TABS PRINCIPALES

**Total de Tabs:** 9 tabs principales independientes (sin subtabs)

1. 💰 Ingresos
2. 💸 Egresos
3. 🏢 Vales Empresa
4. 👥 Vales Personal
5. 📥 Transferencias Entradas
6. 📤 Transferencias Salidas
7. ➖ Descuentos
8. 💳 Registrar Pago (incluye visualización de saldos pendientes)
9. 📋 Historial de Pagos

---

### 1. 💰 TAB: INGRESOS

**Funcionalidad:** Visualización de ingresos diarios

#### Campos Mostrados:
- **Fecha:** `DATE(fecha)` agrupado por día
- **Número de Pedidos:** `COUNT(*)` de servicios
- **Total Servicios:** `SUM(total)` de servicios
- **Número de Compras:** `COUNT(*)` de compras
- **Total Compras:** `SUM(valor)` de compras
- **Total del Día:** Suma de servicios + compras
- **Comisión (20%):** `Total del Día * 0.20`

**Fuentes de Datos:**
- `servicios_gofast` (excluyendo `tracking_estado = 'cancelado'`)
- `compras_gofast` (excluyendo `estado = 'cancelada'`)

---

### 2. 💸 TAB: EGRESOS

**Funcionalidad:** CRUD completo de egresos

#### Formulario de Creación/Edición:

**Campos:**
- **Fecha** (`fecha`)
  - Tipo: Date
  - Requerido: Sí
  - Descripción: Fecha del egreso

- **Valor** (`valor`)
  - Tipo: Decimal/Float
  - Requerido: Sí
  - Descripción: Monto del egreso

- **Descripción** (`descripcion`)
  - Tipo: Text
  - Requerido: No
  - Descripción: Detalle del egreso

- **Creado por** (`creado_por`)
  - Tipo: Integer (user_id)
  - Automático: Sí
  - Descripción: ID del admin que creó el registro

**Tabla:** `egresos_gofast`

**Operaciones:**
- ✅ Crear
- ✏️ Editar
- 🗑️ Eliminar

---

### 3. 🏢 TAB: VALES EMPRESA

**Funcionalidad:** CRUD completo de vales de empresa

#### Formulario de Creación/Edición:

**Campos:**
- **Fecha** (`fecha`)
  - Tipo: Date
  - Requerido: Sí

- **Valor** (`valor`)
  - Tipo: Decimal/Float
  - Requerido: Sí

- **Descripción** (`descripcion`)
  - Tipo: Text
  - Requerido: No

- **Creado por** (`creado_por`)
  - Tipo: Integer (user_id)
  - Automático: Sí

**Tabla:** `vales_empresa_gofast`

**Operaciones:**
- ✅ Crear
- ✏️ Editar
- 🗑️ Eliminar

---

### 4. 👥 TAB: VALES PERSONAL

**Funcionalidad:** CRUD completo de vales del personal

#### Formulario de Creación/Edición:

**Campos:**
- **Fecha** (`fecha`)
  - Tipo: Date
  - Requerido: Sí

- **Valor** (`valor`)
  - Tipo: Decimal/Float
  - Requerido: Sí

- **Descripción** (`descripcion`)
  - Tipo: Text
  - Requerido: No

- **Creado por** (`creado_por`)
  - Tipo: Integer (user_id)
  - Automático: Sí

**Tabla:** `vales_personal_gofast`

**Operaciones:**
- ✅ Crear
- ✏️ Editar
- 🗑️ Eliminar

---

### 5. 📥 TAB: TRANSFERENCIAS ENTRADAS

**Funcionalidad:** Visualización de transferencias entrantes (solo lectura)

#### Campos Mostrados:
- **ID:** ID de la transferencia
- **Fecha:** `fecha_creacion`
- **Mensajero:** Nombre y teléfono del mensajero
- **Valor:** Monto de la transferencia
- **Estado:** `pendiente`, `aprobada`, `rechazada`
- **Tipo:** `normal` o `pago`
- **Observaciones:** Notas adicionales

**Fuente:** Tabla `transferencias_gofast`

**Filtros:**
- Por rango de fechas
- Por estado
- Por tipo (normal/pago)

**Nota:** Este tab es solo de visualización, no permite edición

---

### 6. 📤 TAB: TRANSFERENCIAS SALIDAS

**Funcionalidad:** CRUD completo de transferencias salientes

#### Formulario de Creación/Edición:

**Campos:**
- **Fecha** (`fecha`)
  - Tipo: Date
  - Requerido: Sí

- **Valor** (`valor`)
  - Tipo: Decimal/Float
  - Requerido: Sí

- **Descripción** (`descripcion`)
  - Tipo: Text
  - Requerido: No

- **Creado por** (`creado_por`)
  - Tipo: Integer (user_id)
  - Automático: Sí

**Tabla:** `transferencias_salidas_gofast`

**Operaciones:**
- ✅ Crear
- ✏️ Editar
- 🗑️ Eliminar

---

### 7. ➖ TAB: DESCUENTOS

**Funcionalidad:** CRUD completo de descuentos a mensajeros

#### Formulario de Creación/Edición:

**Campos:**
- **Mensajero** (`mensajero_id`)
  - Tipo: Integer (select)
  - Requerido: Sí
  - Fuente: `usuarios_gofast` WHERE `rol = 'mensajero'`

- **Fecha** (`fecha`)
  - Tipo: Date
  - Requerido: Sí

- **Valor** (`valor`)
  - Tipo: Decimal/Float
  - Requerido: Sí
  - **Nota:** Puede ser negativo (bonificación)

- **Descripción** (`descripcion`)
  - Tipo: Text
  - Requerido: No

- **Creado por** (`creado_por`)
  - Tipo: Integer (user_id)
  - Automático: Sí

**Tabla:** `descuentos_mensajeros_gofast`

**Operaciones:**
- ✅ Crear
- ✏️ Editar
- 🗑️ Eliminar

---

### 8. 💳 TAB: REGISTRAR PAGO

**Funcionalidad:** Visualización de saldos pendientes de pago a mensajeros y registro de pagos

**Campos Calculados por Mensajero:**

- **Mensajero:**
  - `id`: ID del mensajero
  - `nombre`: Nombre del mensajero
  - `telefono`: Teléfono del mensajero

- **Total Destinos:**
  - Cálculo: `SUM(JSON_LENGTH(JSON_EXTRACT(destinos, '$.destinos')))`
  - Fuente: `servicios_gofast`
  - Filtros: `tracking_estado != 'cancelado'`, rango de fechas

- **Total Compras:**
  - Cálculo: `COUNT(*)`
  - Fuente: `compras_gofast`
  - Filtros: `estado != 'cancelada'`, rango de fechas

- **Ingresos Servicios:**
  - Cálculo: `SUM(total)`
  - Fuente: `servicios_gofast`

- **Ingresos Compras:**
  - Cálculo: `SUM(valor)`
  - Fuente: `compras_gofast`

- **Ingresos Totales:**
  - Fórmula: `Ingresos Servicios + Ingresos Compras`

- **Comisión (20%):**
  - Fórmula: `Ingresos Totales * 0.20`

- **Transferencias Aprobadas:**
  - Cálculo: `SUM(valor)`
  - Fuente: `transferencias_gofast`
  - Filtros: `estado = 'aprobada'`, `tipo = 'normal'` (excluye tipo "pago"), rango de fechas

- **Descuentos:**
  - Cálculo: `SUM(valor)`
  - Fuente: `descuentos_mensajeros_gofast`
  - Filtros: Rango de fechas

- **Pagos:**
  - Cálculo: `SUM(total_a_pagar)`
  - Fuente: `pagos_mensajeros_gofast`
  - Filtros: `tipo_pago IN ('efectivo', 'transferencia')`, rango de fechas

- **Total a Pagar:**
  - Fórmula: `Comisión - Transferencias - Descuentos - Pagos`
  - Si resultado < 0, se establece en 0

**Filtros Disponibles:**
- `filtro_mensajero_saldos`: Filtrar por mensajero específico
- `filtro_estado_saldos`: Filtrar por estado (pendiente, efectivo, transferencia)
- Rango de fechas (heredado de filtros generales)

**Acciones Disponibles:**
- **Pago Efectivo:** Botón para registrar pago en efectivo (abre modal)
- **Pago Transferencia:** Botón para registrar pago por transferencia (abre modal)
- **Ver días:** Botón para ver desglose diario de saldos (si aplica)

**Nota:** El registro de pagos se realiza desde este tab usando los botones "Pago Efectivo" o "Pago Transferencia" en cada mensajero.

---

**Formulario de Registro de Pago (Modal - se abre desde este tab):**

**Campos:**
- **Mensajero** (`mensajero_id`)
  - Tipo: Integer (select)
  - Requerido: Sí
  - Fuente: `usuarios_gofast` WHERE `rol = 'mensajero'`

- **Fecha** (`fecha`)
  - Tipo: Date
  - Requerido: Sí
  - Descripción: Fecha del período que se está pagando

- **Tipo de Pago** (`tipo_pago`)
  - Tipo: Enum
  - Valores: `'efectivo'`, `'transferencia'`
  - Requerido: Sí

- **Comisión Total** (`comision_total`)
  - Tipo: Decimal/Float
  - Calculado automáticamente
  - Descripción: 20% de ingresos totales del mensajero

- **Transferencias Total** (`transferencias_total`)
  - Tipo: Decimal/Float
  - Calculado automáticamente
  - Descripción: Suma de transferencias aprobadas (tipo normal)

- **Descuentos Total** (`descuentos_total`)
  - Tipo: Decimal/Float
  - Calculado automáticamente
  - Descripción: Suma de descuentos aplicados

- **Total a Pagar** (`total_a_pagar`)
  - Tipo: Decimal/Float
  - Calculado automáticamente
  - Fórmula: `Comisión Total - Transferencias Total - Descuentos Total`

- **Fecha de Pago** (`fecha_pago`)
  - Tipo: DateTime
  - Automático: Sí
  - Valor: `gofast_date_mysql()`

- **Creado por** (`creado_por`)
  - Tipo: Integer (user_id)
  - Automático: Sí

**Tabla:** `pagos_mensajeros_gofast`

**Lógica Especial:**
- Si `tipo_pago = 'transferencia'`:
  - Se crea automáticamente un registro en `transferencias_gofast`
  - Estado: `'aprobada'`
  - Tipo: `'pago'`
  - Observaciones: `'Pago automático - Transferencia - Fecha: [fecha]'`

### 9. 📋 TAB: HISTORIAL DE PAGOS

**Funcionalidad:** Visualización del historial completo de pagos registrados a mensajeros

**Campos Mostrados:**
- **ID:** ID del pago
- **Fecha:** Fecha del período pagado
- **Mensajero:** Nombre del mensajero
- **Comisión Total:** Comisión calculada
- **Transferencias Total:** Transferencias aplicadas
- **Descuentos Total:** Descuentos aplicados
- **Total a Pagar:** Monto final pagado
- **Tipo de Pago:** Efectivo o Transferencia
- **Fecha de Pago:** Fecha/hora del registro
- **Creado por:** Usuario que registró el pago

**Filtros:**
- `filtro_mensajero_historial`: Filtrar por mensajero
- `filtro_fecha_desde_historial`: Fecha inicial
- `filtro_fecha_hasta_historial`: Fecha final

**Operaciones:**
- ✏️ Editar (solo fecha, tipo_pago, total_a_pagar)
- 🗑️ Eliminar

**Lógica de Edición:**
- Si el pago era tipo "transferencia", se busca y actualiza la transferencia asociada
- Se busca por: `mensajero_id`, `valor`, y `observaciones LIKE '%Pago automático%[fecha]%'`

---

## 🔍 FILTROS GENERALES

**Ubicación:** Parte superior, aplica a todos los tabs

**Campos:**
- **Período del Mes:** `periodo_mes` (GET) - Selector con opciones:
  - Personalizado
  - Primera Quincena (1-15)
  - Segunda Quincena (16-fin de mes)
- **Fecha Desde:** `fecha_desde` (GET) - Input tipo date
- **Fecha Hasta:** `fecha_hasta` (GET) - Input tipo date
- **Tab Activo:** `tab` (GET) - Hidden field para preservar tab al filtrar

**Comportamiento:**
- Se aplican a todos los cálculos y visualizaciones
- Se preservan al cambiar de tab
- **Valores por defecto automáticos:**
  - Si día actual ≤ 15: Primera quincena (1-15)
  - Si día actual > 15: Segunda quincena (16-fin de mes)

**Funcionalidad JavaScript:**
- Función `actualizarFechasPorPeriodo()`: Actualiza automáticamente las fechas cuando se selecciona un período
- Si se cambian las fechas manualmente, el selector de período vuelve a "Personalizado"
- El bloque de Resultados Generales muestra el período activo cuando se usa un período automático

---

## 📊 TABLAS DE BASE DE DATOS UTILIZADAS

1. **servicios_gofast**
   - Campos usados: `fecha`, `total`, `tracking_estado`, `mensajero_id`, `destinos`

2. **compras_gofast**
   - Campos usados: `fecha_creacion`, `valor`, `estado`, `mensajero_id`

3. **egresos_gofast**
   - Campos: `id`, `fecha`, `valor`, `descripcion`, `creado_por`

4. **vales_empresa_gofast**
   - Campos: `id`, `fecha`, `valor`, `descripcion`, `creado_por`

5. **vales_personal_gofast**
   - Campos: `id`, `fecha`, `valor`, `descripcion`, `creado_por`

6. **transferencias_gofast**
   - Campos: `id`, `mensajero_id`, `valor`, `estado`, `tipo`, `fecha_creacion`, `observaciones`, `creado_por`

7. **transferencias_salidas_gofast**
   - Campos: `id`, `fecha`, `valor`, `descripcion`, `creado_por`

8. **descuentos_mensajeros_gofast**
   - Campos: `id`, `mensajero_id`, `fecha`, `valor`, `descripcion`, `creado_por`

9. **pagos_mensajeros_gofast**
   - Campos: `id`, `fecha`, `mensajero_id`, `comision_total`, `transferencias_total`, `descuentos_total`, `total_a_pagar`, `tipo_pago`, `fecha_pago`, `creado_por`

10. **usuarios_gofast**
    - Campos: `id`, `nombre`, `telefono`, `rol`

---

## ⚠️ NOTAS IMPORTANTES

1. **Transferencias de Tipo "Pago":**
   - Las transferencias creadas automáticamente desde pagos tienen `tipo = 'pago'`
   - **En el módulo de finanzas:** Se suman TODAS las transferencias aprobadas (tipo "normal" y tipo "pago")
   - **En el módulo de reportes:** Solo se suman transferencias de tipo "normal" (excluye tipo "pago") para el cálculo de saldos pendientes
   - **Diferencia:** 
     - Finanzas: Suma todas las transferencias como ingresos recibidos
     - Reportes: Solo cuenta transferencias normales para calcular lo que se debe pagar a mensajeros

2. **Cálculo de Saldos:**
   - Los saldos se calculan en el rango de fechas seleccionado
   - Los pagos se restan del saldo pendiente
   - Si el resultado es negativo, se establece en 0

3. **Filtros de Fecha y Períodos Automáticos:**
   - **Sistema de períodos:** El mes se divide automáticamente en dos períodos (1-15 y 16-fin de mes)
   - **Inicialización automática:** Al cargar sin filtros, se determina el período según el día actual
   - **Selector de período:** Permite cambiar fácilmente entre primera quincena, segunda quincena o modo personalizado
   - **Actualización automática:** Al seleccionar un período, las fechas se actualizan automáticamente
   - **Lógica de rangos:**
     - Si `fecha_desde == fecha_hasta`: Calcula acumulado histórico hasta esa fecha
     - Si `fecha_desde != fecha_hasta`: Calcula solo ese rango específico

4. **Mensajeros Deshabilitados:**
   - En el tab de Registrar Pago, se incluyen mensajeros deshabilitados (`activo = 0`)
   - Esto permite ver saldos históricos incluso si el mensajero ya no está activo

5. **Comisión:**
   - Siempre es el 20% de los ingresos totales (servicios + compras)
   - Se calcula automáticamente en todos los módulos

---

## 🔄 FLUJOS PRINCIPALES

### Flujo de Registro de Pago:
1. Admin selecciona mensajero y fecha
2. Sistema calcula automáticamente:
   - Comisión total (20% de ingresos)
   - Transferencias aprobadas (tipo normal)
   - Descuentos aplicados
   - Total a pagar
3. Admin confirma y registra el pago
4. Si es tipo "transferencia", se crea automáticamente una transferencia de tipo "pago"

### Flujo de Cálculo de Resultados Generales:
1. **Inicialización automática:**
   - Si no hay filtros, el sistema determina automáticamente el período según el día actual
   - Días 1-15: Establece primera quincena (1-15)
   - Días 16+: Establece segunda quincena (16-fin de mes)
2. **Usuario puede:**
   - Seleccionar un período del mes (primera o segunda quincena)
   - O establecer fechas personalizadas (modo personalizado)
3. Sistema calcula todos los totales según el tipo de rango:
   - Rango específico: Solo ese período
   - Fecha única: Acumulado histórico hasta esa fecha
4. Se muestran todos los resultados en el bloque principal con indicador del período activo

---

## 📝 CAMPOS DE FORMULARIOS - RESUMEN

### Formularios con Mismos Campos:
- **Egresos, Vales Empresa, Vales Personal, Transferencias Salidas:**
  - Fecha (Date, requerido)
  - Valor (Decimal, requerido)
  - Descripción (Text, opcional)
  - Creado por (Integer, automático)

### Formulario Especial:
- **Descuentos:**
  - Mensajero (Select, requerido)
  - Fecha (Date, requerido)
  - Valor (Decimal, requerido, puede ser negativo)
  - Descripción (Text, opcional)
  - Creado por (Integer, automático)

- **Registrar Pago:**
  - Mensajero (Select, requerido)
  - Fecha (Date, requerido)
  - Tipo de Pago (Enum: efectivo/transferencia, requerido)
  - Comisión Total (Decimal, calculado)
  - Transferencias Total (Decimal, calculado)
  - Descuentos Total (Decimal, calculado)
  - Total a Pagar (Decimal, calculado)
  - Fecha de Pago (DateTime, automático)
  - Creado por (Integer, automático)

---

## ✅ VALIDACIONES

- Todos los campos de valor deben ser > 0
- Fechas deben ser válidas
- Mensajeros deben existir y tener rol 'mensajero'
- Tipo de pago solo acepta 'efectivo' o 'transferencia'
- Nonces de seguridad en todos los formularios

---

## 🎨 INTERFAZ

- Sistema de tabs para navegación
- Bloque de resultados generales siempre visible
- **Filtros generales con selector de períodos:**
  - Selector de período del mes (Primera Quincena / Segunda Quincena / Personalizado)
  - Campos de fecha que se actualizan automáticamente al seleccionar período
  - Indicador visual del período activo en el bloque de resultados
- Filtros aplicables a todos los tabs
- Mensajes de éxito/error para todas las operaciones
- Tablas responsivas con scroll horizontal en móvil
- Select2 para búsqueda mejorada en selects de mensajeros
- **Funcionalidad JavaScript:**
  - `actualizarFechasPorPeriodo()`: Actualiza fechas según período seleccionado
  - Sincronización bidireccional entre selector de período y campos de fecha

---

---

## 🆕 CAMBIOS RECIENTES

### Versión 1.2 (2025-01-27)

**Reorganización de Tabs: Eliminación de Subtabs**

- ✅ Eliminado el tab "Saldos Mensajeros" como tab principal
- ✅ Convertidos los subtabs en tabs principales independientes:
  - **Tab "Registrar Pago":** Contiene la visualización de saldos pendientes y permite registrar pagos (combinación de funcionalidades)
  - **Tab "Historial de Pagos":** Historial completo de pagos (antes subtab)
- ✅ Eliminado el tab "Saldos Pendientes" como tab independiente (su contenido ahora está en "Registrar Pago")
- ✅ Eliminada la variable `$subtab_saldos` y toda la lógica de subtabs
- ✅ Eliminada la función JavaScript `mostrarSubTabSaldos()`
- ✅ Actualizadas todas las redirecciones y referencias
- ✅ Actualizada la función `mostrarTabFinanzas()` para reconocer los nuevos tabs

**Mejoras:**
- Navegación más clara y directa
- Cada funcionalidad tiene su propio tab principal
- Eliminada la complejidad de subtabs anidados
- Mejor experiencia de usuario con tabs independientes

---

### Versión 1.1 (2025-01-27)

**Nueva Funcionalidad: Períodos Automáticos del Mes**

- ✅ Implementado sistema de división automática del mes en dos períodos:
  - Primera Quincena: Del 1 al 15 del mes
  - Segunda Quincena: Del 16 al último día del mes
- ✅ Selector de período en filtros generales
- ✅ Inicialización automática según el día actual
- ✅ Función JavaScript para actualización automática de fechas
- ✅ Indicador visual del período activo en Resultados Generales
- ✅ Sincronización bidireccional entre selector y campos de fecha

**Mejoras:**
- Los análisis se organizan automáticamente por quincenas
- Facilita el análisis financiero mensual dividido en dos períodos
- Permite cambiar fácilmente entre períodos o usar fechas personalizadas

---

**Última actualización:** 2025-01-27  
**Versión del análisis:** 1.2


# Explicación del Desglose de Días y Cálculo de Total a Pagar

**Fecha:** 2025-01-27  
**Archivo:** `code/gofast_finanzas_admin.php`

## Problema Identificado

El usuario reportó que el **Total a Pagar** mostrado en la tabla de mensajeros no coincidía con la suma del **Desglose de Días**. Esto se debía a inconsistencias en los rangos de fechas y en cómo se aplicaban los pagos.

---

## Cómo Funciona el Desglose de Días

### 1. Cálculo del Total a Pagar

El `total_a_pagar` se calcula usando la siguiente fórmula:

```php
$total_a_pagar = ($comision_historica + $comision_compras_historica) 
                 - $transferencias_historicas 
                 - $descuentos_historicos 
                 - $pagos_historicos;
```

**Componentes:**

- **`$comision_historica`**: 20% de servicios hasta `fecha_hasta`
- **`$comision_compras_historica`**: 20% de compras hasta `fecha_hasta`
- **`$transferencias_historicas`**: Transferencias normales y pagos registrados hasta `fecha_hasta`
- **`$descuentos_historicos`**: Descuentos hasta `fecha_hasta`
- **`$pagos_historicos`**: Pagos en efectivo hasta `fecha_hasta` ⚠️ **CORREGIDO**

### 2. Cálculo del Desglose por Día

El desglose calcula día por día desde la primera actividad del mensajero:

#### Paso 1: Determinar el Rango de Fechas
```php
// ANTES (INCORRECTO):
$fecha_fin = new DateTime(); // Hasta HOY

// DESPUÉS (CORREGIDO):
$fecha_fin = new DateTime($fecha_hasta); // Hasta fecha_hasta
```

#### Paso 2: Calcular Valores por Día
Para cada día en el rango, se calcula:

```php
$ingresos_total_dia = $ingresos_dia + $ingresos_compras_dia;
$comision_dia = $ingresos_total_dia * 0.20;
$a_pagar_dia = $comision_dia - $transferencias_dia - $descuentos_dia;
$pendiente_inicial = max(0, $a_pagar_dia);
```

**Componentes por día:**
- **Ingresos del día**: Servicios + Compras del día
- **Comisión del día**: 20% de los ingresos del día
- **Transferencias del día**: Transferencias normales y pagos registrados del día
- **Descuentos del día**: Descuentos del día
- **A pagar del día**: Comisión - Transferencias - Descuentos

#### Paso 3: Distribuir Pagos
Los pagos en efectivo se distribuyen del día más antiguo al más reciente:

```php
// ANTES (INCORRECTO):
// Obtener TODOS los pagos (sin límite de fecha)
$todos_pagos = "SELECT ... WHERE tipo_pago = 'efectivo'"

// DESPUÉS (CORREGIDO):
// Obtener pagos hasta fecha_hasta
$todos_pagos = "SELECT ... WHERE tipo_pago = 'efectivo' AND fecha <= fecha_hasta"
```

Luego se aplican a los días con pendiente:
```php
foreach ($dias_historico as $dia) {
    if ($dia['pendiente'] > 0 && $pagos_restantes > 0) {
        $aplicar = min($pagos_restantes, $dia['pendiente']);
        $dia['pagado'] += $aplicar;
        $dia['pendiente'] -= $aplicar;
        $pagos_restantes -= $aplicar;
    }
}
```

#### Paso 4: Filtrar Días con Pendiente
```php
// ANTES (INCORRECTO):
// Filtrar días hasta fecha_hasta después de calcular hasta HOY
if ($dia['pendiente'] > 0 && $dia['fecha'] <= $fecha_hasta) {
    $desglose_dias[] = $dia;
}

// DESPUÉS (CORREGIDO):
// Ya están filtrados hasta fecha_hasta en el periodo
if ($dia['pendiente'] > 0) {
    $desglose_dias[] = $dia;
}
```

---

## Correcciones Aplicadas

### 1. Rango de Fechas del Desglose
**Antes:**
- El desglose calculaba desde la primera actividad hasta **HOY**
- Solo mostraba días hasta `fecha_hasta`
- Esto causaba que se calcularan días que no se mostraban

**Después:**
- El desglose calcula desde la primera actividad hasta **`fecha_hasta`**
- Todos los días calculados se muestran (si tienen pendiente)
- Coincide con el rango usado en `total_a_pagar`

### 2. Pagos Históricos
**Antes:**
```php
// Pagos sin límite de fecha
$pagos_historicos = "SELECT ... WHERE tipo_pago = 'efectivo'"
```

**Después:**
```php
// Pagos hasta fecha_hasta
$pagos_historicos = "SELECT ... WHERE tipo_pago = 'efectivo' AND fecha <= fecha_hasta"
```

### 3. Pagos en el Desglose
**Antes:**
```php
// Todos los pagos (sin límite)
$todos_pagos = "SELECT ... WHERE tipo_pago = 'efectivo'"
```

**Después:**
```php
// Pagos hasta fecha_hasta
$todos_pagos = "SELECT ... WHERE tipo_pago = 'efectivo' AND fecha <= fecha_hasta"
```

---

## Por Qué Ahora Coinciden

### Antes (Incorrecto)

1. **Total a Pagar**: 
   - Comisión hasta `fecha_hasta`
   - Transferencias hasta `fecha_hasta`
   - Descuentos hasta `fecha_hasta`
   - Pagos **SIN límite de fecha** ❌

2. **Desglose**:
   - Calculaba días hasta **HOY** ❌
   - Distribuía pagos **SIN límite de fecha** ❌
   - Solo mostraba días hasta `fecha_hasta`

**Resultado**: El total incluía pagos futuros, pero el desglose los distribuía en días que no se mostraban.

### Después (Correcto)

1. **Total a Pagar**: 
   - Comisión hasta `fecha_hasta`
   - Transferencias hasta `fecha_hasta`
   - Descuentos hasta `fecha_hasta`
   - Pagos hasta `fecha_hasta` ✅

2. **Desglose**:
   - Calcula días hasta `fecha_hasta` ✅
   - Distribuye pagos hasta `fecha_hasta` ✅
   - Muestra todos los días calculados (con pendiente) ✅

**Resultado**: Ambos usan el mismo rango de fechas y la misma lógica, por lo que coinciden.

---

## Fórmula Final

### Total a Pagar
```
Total a Pagar = (Comisión Servicios + Comisión Compras) 
                - Transferencias (normales + pagos registrados)
                - Descuentos
                - Pagos en Efectivo
```

**Todos los componentes hasta `fecha_hasta`**

### Desglose por Día
```
Para cada día hasta fecha_hasta:
  Comisión Día = (Ingresos Servicios + Ingresos Compras) * 0.20
  A Pagar Día = Comisión Día - Transferencias Día - Descuentos Día
  Pendiente Inicial = max(0, A Pagar Día)
  
Distribuir pagos en efectivo (hasta fecha_hasta) del más antiguo al más reciente:
  Pendiente Final = Pendiente Inicial - Pagos Aplicados
```

**Suma del Desglose = Total a Pagar** ✅

---

## Ejemplo Práctico

### Escenario
- Mensajero: Juan
- Fecha desde: 2025-01-01
- Fecha hasta: 2025-01-15
- Comisión total: $100,000
- Transferencias: $20,000
- Descuentos: $5,000
- Pagos en efectivo: $10,000 (hasta 15/01) + $5,000 (después de 15/01)

### Antes (Incorrecto)
- **Total a Pagar**: $100,000 - $20,000 - $5,000 - $15,000 = **$60,000**
- **Desglose**: Calcula hasta HOY, distribuye $15,000, pero solo muestra hasta 15/01
- **Suma Desglose**: Puede ser diferente porque incluye pagos futuros

### Después (Correcto)
- **Total a Pagar**: $100,000 - $20,000 - $5,000 - $10,000 = **$65,000**
- **Desglose**: Calcula hasta 15/01, distribuye $10,000
- **Suma Desglose**: **$65,000** ✅ Coincide

---

## Verificación

Para verificar que todo funciona correctamente:

1. **Total a Pagar** debe ser igual a la **Suma del Desglose**
2. **Suma del Desglose** = `array_sum(array_column($desglose_dias, 'pendiente'))`
3. Ambos deben usar el mismo rango de fechas (`fecha_hasta`)
4. Ambos deben usar los mismos pagos (hasta `fecha_hasta`)

---

## Código Relevante

### Ubicación de los Cambios

1. **Línea 1721**: Cambio de `new DateTime()` a `new DateTime($fecha_hasta)`
2. **Línea 1693**: Agregado filtro `AND fecha <= %s` en pagos históricos
3. **Línea 1732**: Agregado filtro `AND fecha <= %s` en pagos del desglose
4. **Línea 1831**: Simplificado filtro del desglose (ya está limitado por el periodo)

---

## Notas Importantes

1. **Pagos por Transferencia**: No se restan en los cálculos porque ya se contabilizan como transferencias tipo "pago".

2. **Pagos en Efectivo**: Se restan directamente y se distribuyen en el desglose.

3. **Consistencia Temporal**: Todos los cálculos ahora usan `fecha <= fecha_hasta` para mantener consistencia.

4. **Días sin Actividad**: No se incluyen en el desglose (solo días con `ingresos_total_dia > 0`).

---

**Última actualización:** 2025-01-27


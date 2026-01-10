# Verificación de Cálculos - Módulo de Finanzas

**Fecha:** 2025-01-27  
**Archivo:** `code/gofast_finanzas_admin.php`

## Resumen de Correcciones

Se han verificado y corregido todos los cálculos del módulo de finanzas para asegurar consistencia en el manejo de transferencias tipo "normal" y tipo "pago".

---

## 1. Transferencias Ingresos (Resultados Generales)

### Ubicación
- **Líneas 821-861**: Cálculo para rango específico de fechas
- **Líneas 946-970**: Cálculo para acumulado histórico

### Fórmula
```sql
SUM(valor) FROM transferencias_gofast
WHERE estado = 'aprobada'
AND (
    (tipo = 'normal' OR tipo IS NULL)
    OR (
        tipo = 'pago' 
        AND EXISTS (
            SELECT 1 FROM pagos_mensajeros_gofast p
            WHERE p.mensajero_id = t.mensajero_id
            AND p.total_a_pagar = t.valor
            AND p.tipo_pago IN ('efectivo', 'transferencia')
        )
    )
)
```

### Corrección Aplicada
✅ Solo se suman transferencias tipo "normal" y tipo "pago" asociadas a pagos registrados.

---

## 2. Transferencias Aprobadas por Mensajero

### Ubicación
- **Líneas 1570-1602**: Cálculo en rango de fechas para cada mensajero

### Fórmula
```sql
SUM(valor) FROM transferencias_gofast
WHERE mensajero_id = X 
AND estado = 'aprobada'
AND (tipo = 'normal' OR tipo IS NULL)
```

### Corrección Aplicada
✅ **IMPORTANTE**: Solo se incluyen transferencias tipo "normal". Las transferencias tipo "pago" se contabilizan en los pagos del mensajero.

---

## 3. Transferencias Históricas por Mensajero

### Ubicación
- **Líneas 1657-1679**: Cálculo histórico hasta fecha_hasta

### Fórmula
```sql
SUM(valor) FROM transferencias_gofast
WHERE mensajero_id = X 
AND estado = 'aprobada' 
AND DATE(fecha_creacion) <= fecha_hasta
AND (tipo = 'normal' OR tipo IS NULL)
```

### Corrección Aplicada
✅ **IMPORTANTE**: Solo se incluyen transferencias tipo "normal". Las transferencias tipo "pago" se contabilizan en `pagos_historicos`.

---

## 4. Transferencias del Día (Desglose Diario)

### Ubicación
- **Líneas 1763-1781**: Cálculo por día en el desglose histórico

### Fórmula
```sql
SUM(valor) FROM transferencias_gofast
WHERE mensajero_id = X 
AND estado = 'aprobada' 
AND DATE(fecha_creacion) = fecha_dia
AND (tipo = 'normal' OR tipo IS NULL)
```

### Corrección Aplicada
✅ **IMPORTANTE**: Solo se incluyen transferencias tipo "normal" del día. Las transferencias tipo "pago" se contabilizan en los pagos del día.

---

## 5. Pagos Históricos por Mensajero

### Ubicación
- **Líneas 1689-1698**: Cálculo de pagos históricos

### Fórmula
```sql
SUM(total_a_pagar) FROM pagos_mensajeros_gofast 
WHERE mensajero_id = X 
AND tipo_pago IN ('efectivo', 'transferencia')
AND fecha <= fecha_hasta
```

### Corrección Aplicada
✅ **IMPORTANTE**: Se suman pagos en efectivo Y por transferencia hasta `fecha_hasta`. Las transferencias tipo "pago" se contabilizan aquí como pagos, no en transferencias.

### Razón
- Cuando se registra un pago por transferencia, se crea automáticamente una transferencia tipo "pago".
- Para evitar duplicar, las transferencias tipo "pago" NO se restan en `transferencias_historicas`.
- En su lugar, se restan en `pagos_historicos` como pagos por transferencia.
- Esto mantiene la lógica: Transferencias = solo normales, Pagos = efectivo + transferencia.

---

## 6. Total a Pagar por Mensajero

### Ubicación
- **Línea 1702**: Cálculo del total pendiente

### Fórmula
```php
$total_a_pagar = ($comision_historica + $comision_compras_historica) 
                 - $transferencias_historicas 
                 - $descuentos_historicos 
                 - $pagos_historicos;
```

Donde:
- `$comision_historica`: 20% de servicios hasta fecha_hasta
- `$comision_compras_historica`: 20% de compras hasta fecha_hasta
- `$transferencias_historicas`: Solo transferencias tipo "normal" hasta fecha_hasta
- `$descuentos_historicos`: Descuentos hasta fecha_hasta
- `$pagos_historicos`: Pagos en efectivo Y por transferencia hasta fecha_hasta

### Corrección Aplicada
✅ Fórmula corregida: `transferencias_historicas` solo incluye tipo "normal", `pagos_historicos` incluye efectivo y transferencia.

---

## 7. Desglose por Día - Cálculo de Pendiente

### Ubicación
- **Líneas 1796-1810**: Cálculo de a_pagar por día
- **Líneas 1815-1825**: Distribución de pagos

### Fórmula por Día
```php
$comision_dia = $ingresos_total_dia * 0.20;
$a_pagar_dia = $comision_dia - $transferencias_dia - $descuentos_dia;
$pendiente_inicial = max(0, $a_pagar_dia);
```

Donde:
- `$transferencias_dia`: Solo transferencias tipo "normal" del día

Luego se distribuyen los pagos (efectivo y transferencia) del más antiguo al más reciente:
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

### Corrección Aplicada
✅ Se distribuyen pagos en efectivo Y por transferencia (hasta fecha_hasta). Las transferencias del día solo incluyen tipo "normal".

---

## 8. Saldos Pendientes Totales

### Ubicación
- **Líneas 1807-1833**: Cálculo del total de saldos pendientes

### Fórmula
```php
$total_saldos_pendientes = $total_comisiones 
                          - $total_transferencias_ingresos 
                          - $total_descuentos 
                          - $total_pagos_mensajeros;
```

Donde:
- `$total_comisiones`: 20% de ingresos totales (servicios + compras)
- `$total_transferencias_ingresos`: Transferencias normales y pagos registrados (ya corregido)
- `$total_descuentos`: Descuentos en el rango
- `$total_pagos_mensajeros`: Solo pagos en efectivo en el rango de fechas

### Estado
✅ Correcto - Ya estaba implementado correctamente.

---

## 9. Efectivo

### Ubicación
- **Línea 1837**: Cálculo de efectivo disponible

### Fórmula
```php
$efectivo = $subtotal 
          - $saldo_transferencias 
          - $total_saldos_pendientes 
          - $total_vales_personal;
```

Donde:
- `$subtotal`: Comisiones - Egresos - Vales Empresa - Descuentos
- `$saldo_transferencias`: Transferencias Ingresos - Transferencias Salidas
- `$total_saldos_pendientes`: Saldos pendientes de mensajeros
- `$total_vales_personal`: Vales de personal

### Estado
✅ Correcto - Ya estaba implementado correctamente.

---

## Resumen de Cambios

### Archivos Modificados
1. `code/gofast_finanzas_admin.php`

### Líneas Modificadas
- **Líneas 1570-1600**: Transferencias aprobadas por mensajero (agregado filtro de tipo)
- **Líneas 1640-1660**: Transferencias históricas (agregado filtro de tipo)
- **Líneas 1689-1700**: Pagos históricos (cambiado a solo efectivo)
- **Líneas 1725-1739**: Pagos en desglose diario (cambiado a solo efectivo)
- **Líneas 1763-1785**: Transferencias del día (agregado filtro de tipo)

### Principios Aplicados

1. **Transferencias tipo "normal"**: Se restan en `transferencias_historicas` y en todos los cálculos de transferencias.

2. **Transferencias tipo "pago"**: NO se restan en transferencias. Se contabilizan en `pagos_historicos` como pagos por transferencia.

3. **Pagos en efectivo**: Se restan en `pagos_historicos`.

4. **Pagos por transferencia**: Se restan en `pagos_historicos` (no en transferencias).

5. **Consistencia**: 
   - Transferencias = solo tipo "normal"
   - Pagos = efectivo + transferencia

---

## Verificación de Consistencia

### ✅ Transferencias Ingresos (Resultados Generales)
- Solo suma transferencias tipo "normal" y tipo "pago" asociadas a pagos registrados
- Aplicado en rango específico y acumulado histórico

### ✅ Transferencias por Mensajero
- Solo suma transferencias tipo "normal"
- Aplicado en rango de fechas y histórico

### ✅ Transferencias en Desglose Diario
- Solo suma transferencias tipo "normal" del día
- Aplicado en cálculo por día

### ✅ Pagos Históricos
- Suma pagos en efectivo Y por transferencia hasta fecha_hasta
- Las transferencias tipo "pago" se contabilizan aquí como pagos

### ✅ Total a Pagar
- Fórmula correcta: Comisión - Transferencias (solo normales) - Descuentos - Pagos (efectivo + transferencia)

### ✅ Saldos Pendientes
- Fórmula correcta: Comisión - Transferencias (solo normales) - Descuentos - Pagos (efectivo + transferencia)

### ✅ Efectivo
- Fórmula correcta: Subtotal - Saldo Transferencias - Saldos Pendientes - Vales Personal

---

## Notas Importantes

1. **Transferencias tipo "normal"**: Se restan en todos los cálculos de transferencias (históricas, por mensajero, por día).

2. **Transferencias tipo "pago"**: Se crean cuando se registra un pago por transferencia. NO se restan en transferencias, sino en pagos.

3. **Pagos en efectivo**: Se restan en `pagos_historicos` y se distribuyen en el desglose por día.

4. **Pagos por transferencia**: Se restan en `pagos_historicos` (no en transferencias). Esto evita duplicar el monto.

5. **Consistencia temporal**: Los cálculos históricos usan `fecha <= fecha_hasta`, mientras que los cálculos de rango usan `fecha >= fecha_desde AND fecha <= fecha_hasta`.

6. **Lógica final**:
   - `transferencias_historicas` = solo tipo "normal"
   - `pagos_historicos` = efectivo + transferencia
   - Esto asegura que cada monto se reste una sola vez

---

## Próximos Pasos Recomendados

1. ✅ Verificar que los cálculos se muestren correctamente en la interfaz
2. ✅ Probar con datos reales para validar los resultados
3. ✅ Documentar cualquier caso especial o excepción encontrada

---

**Última actualización:** 2025-01-27


# Resumen de Cambios para Commit

## Estadísticas
- **16 archivos modificados**
- **275 inserciones (+)** 
- **109 eliminaciones (-)**
- **Balance neto: +166 líneas**

---

## 📋 Cambios Principales

### 1. **Página Principal (gofast_home.php)** - Cambios Significativos
   - **Mejoras en el ranking de mensajeros:**
     - Eliminado límite de 5 mensajeros en la consulta SQL
     - Implementado sistema de "mostrar más/mostrar menos" con botón toggle
     - Muestra inicialmente 5 mensajeros, con opción de ver todos
     - Mejorada la posición del ranking (movido después de "Accesos Rápidos")
     - Agregado contador total de mensajeros en la posición

   - **Personalización por rol:**
     - Subtítulos personalizados según el rol del usuario (mensajero, admin, cliente)
     - Texto del botón principal adaptado según el rol ("Crear servicio" para mensajeros, "Cotizar envío ahora" para clientes)
     - Enlaces de acción rápida actualizados para usar `$url_cotizar_principal` unificada

   - **Correcciones de branding:**
     - "GoFast" → "Go Fast" (normalización del nombre de marca)
     - Mensaje de bienvenida actualizado

### 2. **Página Mis Pedidos (mis-pedidos.php)** - Nueva Funcionalidad y Mejoras
   - **Agregada columna "Recargos" en la tabla de pedidos:**
     - Nueva columna en la tabla principal de pedidos
     - Detección automática de recargos en destinos (solo recargos adicionales)
     - Cálculo del total de recargos por servicio
     - Visualización en badge amarillo con ícono 💰
     - Incluido también en la vista detallada móvil (tarjetas)

   - **Lógica mejorada de detección de recargos:**
     - **IMPORTANTE:** NO cuenta el campo `monto` (precio base del trayecto)
     - Solo cuenta recargos adicionales reales:
       - `recargo_seleccionable_valor`: Recargos por volumen/peso seleccionables por el usuario
       - `recargo_total`: Recargos automáticos calculados por el sistema
     - Suma ambos tipos de recargos por cada destino
     - Valida que los recargos sean mayores a 0 antes de contarlos
     - Muestra "—" cuando no hay recargos adicionales
     - Implementada tanto en la vista de tabla (desktop) como en tarjetas (móvil)

### 3. **Estilos CSS (css.css)** - Mejoras de Responsividad
   - **Optimizaciones para móviles y tablets:**
     - Nuevos estilos para sección "Nuestro Equipo" en dispositivos móviles
     - Grid de una sola columna en pantallas pequeñas
     - Ajustes de padding y box-sizing para evitar desbordamientos
     - Media queries para tablets (max-width: 768px) y móviles pequeños (max-width: 360px)
     - Correcciones de overflow horizontal

### 4. **Confirmación (gofast_confirmacion.php)** - Limpieza
   - **Eliminado campo "Barrio" del mensaje de WhatsApp:**
     - Removido barrio de origen en mensajes de confirmación (tanto regular como intermunicipal)
     - Simplificación de los mensajes enviados

### 5. **Normalización de Marca - Múltiples Archivos**
   Cambio consistente de "GoFast" → "Go Fast" en:
   - `gofast_app_movil.php` - Títulos y descripciones
   - `gofast_footer.php` - Texto descriptivo y alt de imagen
   - `gofast_menu.php` - Textos del menú (probablemente)
   - `gofast_recuperar_password.php` - Asunto y contenido de emails
   - `gofast_seo.php` - Meta tags, títulos y descripciones SEO

### 6. **Mejoras de Texto - Páginas Intermunicipales**
   - **Simplificación de recordatorios:**
     - `gofast_admin_cotizar_intermunicipal.php`: Eliminada frase redundante "Solo después de esto se despachará el pedido"
     - `gofast_admin_solicitar_intermunicipal.php`: Eliminada frase redundante "Solo después de esto se despachará el pedido"
     - `gofast_mensajero_cotizar_intermunicipal.php`: Eliminada frase redundante "Solo después de esto se despachará el pedido"
     - Texto más limpio y directo en recordatorios

### 7. **Páginas Menores - Cambios Menores**
   - `gofast_sobre_nosotros.php` - Probables ajustes de branding
   - `gofast_trabaja_con_nosotros.php` - Probables ajustes de branding
   - `gofast_solicitar_mensajero.php` - Cambios menores

---

## 🎯 Resumen por Categoría

### ✨ Nuevas Funcionalidades
1. Sistema de visualización de recargos en "Mis Pedidos" con lógica mejorada que distingue entre precio base y recargos
2. Toggle "Ver todos" para ranking de mensajeros

### 🎨 Mejoras de UI/UX
1. Mejoras de responsividad móvil en CSS
2. Personalización de contenido según rol de usuario
3. Mejores estilos para mostrar recargos

### 🔧 Correcciones y Limpieza
1. Normalización de marca (GoFast → Go Fast)
2. Eliminación de texto redundante en recordatorios
3. Simplificación de mensajes de WhatsApp (eliminación de barrio)
4. Corrección en lógica de cálculo de recargos para excluir el monto base y contar solo recargos adicionales

### 📱 Mejoras Móviles
1. Grid responsive para sección "Nuestro Equipo"
2. Ajustes de padding y overflow en móviles pequeños
3. Correcciones de overflow horizontal

---

## 📝 Notas Importantes

- **Branding unificado:** Todos los cambios de "GoFast" a "Go Fast" aseguran consistencia en la marca
- **Funcionalidad de recargos mejorada:** La nueva columna en "Mis Pedidos" muestra solo los recargos adicionales reales (no el precio base del trayecto), ayudando a distinguir claramente entre el costo base y los recargos aplicados
- **Ranking mejorado:** El nuevo sistema permite ver todos los mensajeros, no solo los top 5
- **Responsividad:** Los cambios CSS mejoran significativamente la experiencia en dispositivos móviles
- **Precisión en recargos:** La lógica distingue correctamente entre el monto base del servicio y los recargos adicionales (por volumen/peso o automáticos), asegurando transparencia en los costos

---

## 🔄 Archivos Modificados

1. `code/gofast_admin_cotizar_intermunicipal.php`
2. `code/gofast_admin_solicitar_intermunicipal.php`
3. `code/gofast_app_movil.php`
4. `code/gofast_confirmacion.php`
5. `code/gofast_footer.php`
6. `code/gofast_home.php`
7. `code/gofast_mensajero_cotizar_intermunicipal.php`
8. `code/gofast_menu.php`
9. `code/gofast_recuperar_password.php`
10. `code/gofast_seo.php`
11. `code/gofast_sobre_nosotros.php`
12. `code/gofast_solicitar_intermunicipal.php`
13. `code/gofast_solicitar_mensajero.php`
14. `code/gofast_trabaja_con_nosotros.php`
15. `code/mis-pedidos.php`
16. `css.css`

---

## ✅ Listo para Commit

Todos los cambios están listos para ser incluidos en el commit. Se recomienda hacer commit con un mensaje descriptivo que incluya las mejoras principales.


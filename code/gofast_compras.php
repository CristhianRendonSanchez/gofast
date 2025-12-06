<?php
/***************************************************
 * GOFAST – MÓDULO DE COMPRAS
 * Shortcode: [gofast_compras]
 * URL: /compras
 * 
 * Funcionalidades:
 * - Mensajero: Crear compras (se asigna a sí mismo) y ver sus compras
 * - Admin: Crear compras (asignar mensajero), ver todas, cambiar estados
 ***************************************************/
function gofast_compras_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Validar sesión
    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión para acceder a las compras.</div>";
    }

    $user_id = (int) $_SESSION['gofast_user_id'];
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? 'cliente');

    // Solo mensajero y admin pueden acceder
    if ($rol !== 'mensajero' && $rol !== 'admin') {
        return "<div class='gofast-box'>⚠️ Solo mensajeros y administradores pueden acceder a las compras.</div>";
    }

    // Obtener datos del usuario
    $usuario = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, nombre, rol, activo FROM usuarios_gofast WHERE id = %d AND activo = 1",
            $user_id
        )
    );

    if (!$usuario) {
        return "<div class='gofast-box'>Usuario no encontrado.</div>";
    }

    $mensaje = '';
    $mensaje_tipo = ''; // success, error

    /*********************************************
     * PROCESAMIENTO DE FORMULARIOS
     *********************************************/

    // 1. CREAR COMPRA (Mensajero o Admin)
    if (isset($_POST['gofast_crear_compra']) && wp_verify_nonce($_POST['gofast_compra_nonce'], 'gofast_crear_compra')) {
        $valor = floatval($_POST['valor'] ?? 0);
        $barrio_id = isset($_POST['barrio_id']) ? (int) $_POST['barrio_id'] : 0;
        $mensajero_id = isset($_POST['mensajero_id']) ? (int) $_POST['mensajero_id'] : $user_id;

        if ($valor < 6000 || $valor > 20000) {
            $mensaje = 'El valor debe estar entre 6000 y 20000.';
            $mensaje_tipo = 'error';
        } elseif (empty($barrio_id)) {
            $mensaje = 'El destino es obligatorio.';
            $mensaje_tipo = 'error';
        } else {
            // Validar que el barrio existe
            $barrio_existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM barrios WHERE id = %d", $barrio_id));
            
            if (!$barrio_existe) {
                $mensaje = 'El destino seleccionado no existe.';
                $mensaje_tipo = 'error';
            } else {
                // Si es admin creando para otro mensajero, validar que existe
                if ($rol === 'admin' && $mensajero_id !== $user_id) {
                    $mensajero_valido = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM usuarios_gofast WHERE id = %d AND rol = 'mensajero' AND activo = 1",
                            $mensajero_id
                        )
                    );
                    if (!$mensajero_valido) {
                        $mensaje = 'El mensajero seleccionado no existe o está inactivo.';
                        $mensaje_tipo = 'error';
                    }
                } else {
                    // Mensajero crea para sí mismo
                    $mensajero_id = $user_id;
                }

                if (empty($mensaje)) {
                $insertado = $wpdb->insert(
                    'compras_gofast',
                    [
                        'mensajero_id' => $mensajero_id,
                        'valor' => $valor,
                        'barrio_id' => $barrio_id,
                        'estado' => 'pendiente',
                        'creado_por' => $user_id,
                        'observaciones' => null
                    ],
                    ['%d', '%f', '%d', '%s', '%d', '%s']
                );

                    if ($insertado) {
                        $mensaje = 'Compra creada correctamente.';
                        $mensaje_tipo = 'success';
                    } else {
                        $mensaje = 'Error al crear la compra.';
                        $mensaje_tipo = 'error';
                    }
                }
            }
        }
    }

    // 2. CAMBIAR ESTADO (Mensajero y Admin)
    if (isset($_POST['gofast_cambiar_estado']) && wp_verify_nonce($_POST['gofast_estado_nonce'], 'gofast_cambiar_estado')) {
        $compra_id = (int) $_POST['compra_id'];
        $nuevo_estado = sanitize_text_field($_POST['nuevo_estado'] ?? '');

        // Obtener la compra para validar permisos
        $compra = $wpdb->get_row($wpdb->prepare("SELECT * FROM compras_gofast WHERE id = %d", $compra_id));

        if (!$compra) {
            $mensaje = 'Compra no encontrada.';
            $mensaje_tipo = 'error';
        } else {
            // Validar permisos
            $puede_cambiar = false;
            $estados_permitidos = [];

            if ($rol === 'admin') {
                // Admin puede cambiar a cualquier estado
                $puede_cambiar = true;
                $estados_permitidos = ['pendiente', 'en_proceso', 'completada', 'cancelada'];
            } elseif ($rol === 'mensajero') {
                // Mensajero solo puede cambiar estados de sus propias compras
                if ((int) $compra->mensajero_id === $user_id) {
                    $puede_cambiar = true;
                    // Mensajero solo puede cambiar a: pendiente, en_proceso, completada (NO cancelada)
                    $estados_permitidos = ['pendiente', 'en_proceso', 'completada'];
                }
            }

            if (!$puede_cambiar) {
                $mensaje = 'No tienes permisos para modificar esta compra.';
                $mensaje_tipo = 'error';
            } elseif (!in_array($nuevo_estado, $estados_permitidos, true)) {
                $mensaje = 'Estado no permitido para tu rol.';
                $mensaje_tipo = 'error';
            } else {
                $actualizado = $wpdb->update(
                    'compras_gofast',
                    ['estado' => $nuevo_estado],
                    ['id' => $compra_id],
                    ['%s'],
                    ['%d']
                );

                if ($actualizado !== false) {
                    $mensaje = 'Estado de la compra actualizado correctamente.';
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al actualizar el estado.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    // 3. EDITAR COMPRA (Solo Admin) - Modal unificado
    if (isset($_POST['gofast_editar_compra']) && wp_verify_nonce($_POST['gofast_editar_compra_nonce'], 'gofast_editar_compra') && $rol === 'admin') {
        $compra_id = (int) $_POST['compra_id'];
        $nuevo_valor = isset($_POST['nuevo_valor']) ? floatval($_POST['nuevo_valor']) : 0;
        $nuevo_barrio_id = isset($_POST['nuevo_barrio_id']) ? (int) $_POST['nuevo_barrio_id'] : 0;

        // Validar que la compra existe
        $compra_existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM compras_gofast WHERE id = %d", $compra_id));
        
        if (!$compra_existe) {
            $mensaje = 'La compra no existe.';
            $mensaje_tipo = 'error';
        } elseif ($nuevo_valor < 6000 || $nuevo_valor > 20000) {
            $mensaje = 'El valor debe estar entre 6000 y 20000.';
            $mensaje_tipo = 'error';
        } elseif ($nuevo_barrio_id <= 0) {
            $mensaje = 'El destino es obligatorio.';
            $mensaje_tipo = 'error';
        } else {
            // Validar que el barrio existe
            $barrio_existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM barrios WHERE id = %d", $nuevo_barrio_id));
            
            if (!$barrio_existe) {
                $mensaje = 'El destino seleccionado no existe.';
                $mensaje_tipo = 'error';
            } else {
                $actualizado = $wpdb->update(
                    'compras_gofast',
                    [
                        'valor' => $nuevo_valor,
                        'barrio_id' => $nuevo_barrio_id
                    ],
                    ['id' => $compra_id],
                    ['%f', '%d'],
                    ['%d']
                );

                if ($actualizado !== false) {
                    $mensaje = 'Compra actualizada correctamente.';
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al actualizar la compra.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    // 4. EDITAR VALOR (Solo Admin) - Vista móvil
    if (isset($_POST['gofast_editar_valor']) && wp_verify_nonce($_POST['gofast_editar_valor_nonce'], 'gofast_editar_valor') && $rol === 'admin') {
        $compra_id = (int) $_POST['compra_id'];
        $nuevo_valor = isset($_POST['nuevo_valor']) ? floatval($_POST['nuevo_valor']) : 0;

        // Validar que la compra existe
        $compra_existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM compras_gofast WHERE id = %d", $compra_id));
        
        if (!$compra_existe) {
            $mensaje = 'La compra no existe.';
            $mensaje_tipo = 'error';
        } elseif ($nuevo_valor < 6000 || $nuevo_valor > 20000) {
            $mensaje = 'El valor debe estar entre 6000 y 20000.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'compras_gofast',
                ['valor' => $nuevo_valor],
                ['id' => $compra_id],
                ['%f'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Valor de la compra actualizado correctamente.';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al actualizar el valor.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 5. CANCELAR COMPRA (Solo Admin)
    if (isset($_POST['gofast_cancelar_compra']) && wp_verify_nonce($_POST['gofast_cancelar_compra_nonce'], 'gofast_cancelar_compra') && $rol === 'admin') {
        $compra_id = (int) $_POST['compra_id'];

        // Validar que la compra existe
        $compra_existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM compras_gofast WHERE id = %d", $compra_id));
        
        if (!$compra_existe) {
            $mensaje = 'La compra no existe.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'compras_gofast',
                ['estado' => 'cancelada'],
                ['id' => $compra_id],
                ['%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Compra cancelada correctamente.';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al cancelar la compra.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 6. ELIMINAR COMPRA (Solo Admin)
    if (isset($_POST['gofast_eliminar_compra']) && wp_verify_nonce($_POST['gofast_eliminar_compra_nonce'], 'gofast_eliminar_compra') && $rol === 'admin') {
        $compra_id = (int) $_POST['compra_id'];

        // Validar que la compra existe
        $compra_existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM compras_gofast WHERE id = %d", $compra_id));
        
        if (!$compra_existe) {
            $mensaje = 'La compra no existe.';
            $mensaje_tipo = 'error';
        } else {
            $eliminado = $wpdb->delete('compras_gofast', ['id' => $compra_id], ['%d']);

            if ($eliminado) {
                $mensaje = 'Compra eliminada correctamente.';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al eliminar la compra.';
                $mensaje_tipo = 'error';
            }
        }
    }

    /*********************************************
     * FILTROS
     *********************************************/

    // Filtros desde URL o POST
    $fecha_desde = isset($_GET['fecha_desde']) ? sanitize_text_field($_GET['fecha_desde']) : '';
    $fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize_text_field($_GET['fecha_hasta']) : '';
    $valor_min = isset($_GET['valor_min']) ? floatval($_GET['valor_min']) : 0;
    $valor_max = isset($_GET['valor_max']) ? floatval($_GET['valor_max']) : 0;
    $estado_filtro = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';

    // Valores por defecto según rol
    if (empty($_GET['fecha_desde']) && empty($_GET['fecha_hasta']) && empty($_GET['estado'])) {
        if ($rol === 'admin') {
            // Admin: mostrar pendientes por defecto
            $estado_filtro = 'pendiente';
        } else {
            // Mensajero: mostrar día actual por defecto (zona horaria Colombia)
            $fecha_desde = gofast_date_today();
            $fecha_hasta = gofast_date_today();
        }
    }

    /*********************************************
     * OBTENER DATOS PARA MOSTRAR
     *********************************************/

    // Lista de mensajeros (solo para admin al crear)
    $mensajeros = [];
    if ($rol === 'admin') {
        $mensajeros = $wpdb->get_results(
            "SELECT id, nombre, telefono 
             FROM usuarios_gofast 
             WHERE rol = 'mensajero' AND activo = 1 
             ORDER BY nombre ASC"
        );
    }

    /*******************************************************
     * OBTENER BARRIOS CON PRIORIZACIÓN DE BARRIOS FRECUENTES
     * (Igual que el cotizador, sin agrupación por sectores)
     *******************************************************/
    
    // 1. Obtener barrios frecuentes del mensajero (si es mensajero)
    $barrios_prioritarios = [];
    if ($rol === 'mensajero') {
        $barrios_frecuentes = $wpdb->get_results($wpdb->prepare(
            "SELECT barrio_id, COUNT(*) as veces
             FROM compras_gofast
             WHERE mensajero_id = %d
             GROUP BY barrio_id
             ORDER BY veces DESC
             LIMIT 10",
            $user_id
        ));
        foreach ($barrios_frecuentes as $bf) {
            $barrios_prioritarios[] = (int) $bf->barrio_id;
        }
    }
    
    // 2. Obtener todos los barrios
    $barrios_all = $wpdb->get_results("SELECT id, nombre FROM barrios ORDER BY nombre ASC");
    $barrios = [];
    
    // Asegurar que siempre haya barrios (incluso si la consulta falla, usar array vacío)
    if (!$barrios_all) {
        $barrios_all = [];
    }
    
    // Primero los barrios prioritarios
    foreach ($barrios_all as $b) {
        if (in_array($b->id, $barrios_prioritarios, true)) {
            $barrios[] = $b;
        }
    }
    // Luego los demás
    foreach ($barrios_all as $b) {
        if (!in_array($b->id, $barrios_prioritarios, true)) {
            $barrios[] = $b;
        }
    }
    
    // Si no hay barrios prioritarios, usar todos los barrios directamente
    if (empty($barrios_prioritarios) && !empty($barrios_all)) {
        $barrios = $barrios_all;
    }

    // Construir consulta con filtros
    $where_parts = [];
    $where_values = [];

    // Filtro por rol
    if ($rol === 'mensajero') {
        $where_parts[] = "c.mensajero_id = %d";
        $where_values[] = $user_id;
    }

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_parts[] = "DATE(c.fecha_creacion) >= %s";
        $where_values[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_parts[] = "DATE(c.fecha_creacion) <= %s";
        $where_values[] = $fecha_hasta;
    }

    // Filtro por valor
    if ($valor_min > 0) {
        $where_parts[] = "c.valor >= %f";
        $where_values[] = $valor_min;
    }
    if ($valor_max > 0) {
        $where_parts[] = "c.valor <= %f";
        $where_values[] = $valor_max;
    }

    // Filtro por estado
    if (!empty($estado_filtro) && in_array($estado_filtro, ['pendiente', 'en_proceso', 'completada', 'cancelada'])) {
        $where_parts[] = "c.estado = %s";
        $where_values[] = $estado_filtro;
    }

    // Construir consulta SQL
    $sql = "SELECT c.*, 
                   m.nombre as mensajero_nombre, 
                   m.telefono as mensajero_telefono,
                   u.nombre as creador_nombre,
                   b.nombre as barrio_nombre
            FROM compras_gofast c
            LEFT JOIN usuarios_gofast m ON c.mensajero_id = m.id
            LEFT JOIN usuarios_gofast u ON c.creado_por = u.id
            LEFT JOIN barrios b ON c.barrio_id = b.id";
    
    if (!empty($where_parts)) {
        $sql .= " WHERE " . implode(' AND ', $where_parts);
    }
    
    $sql .= " ORDER BY c.fecha_creacion DESC";

    // Ejecutar consulta con prepared statement
    if (!empty($where_values)) {
        $compras = $wpdb->get_results($wpdb->prepare($sql, $where_values));
    } else {
        $compras = $wpdb->get_results($sql);
    }

    // Contar total sin filtros (para estadísticas)
    $total_sin_filtros = 0;
    if ($rol === 'admin') {
        $total_sin_filtros = (int) $wpdb->get_var("SELECT COUNT(*) FROM compras_gofast");
    } else {
        $total_sin_filtros = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM compras_gofast WHERE mensajero_id = %d",
            $user_id
        ));
    }

    // Estadísticas (solo para admin)
    $stats = null;
    if ($rol === 'admin') {
        $stats = [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM compras_gofast"),
            'pendientes' => (int) $wpdb->get_var("SELECT COUNT(*) FROM compras_gofast WHERE estado = 'pendiente'"),
            'en_proceso' => (int) $wpdb->get_var("SELECT COUNT(*) FROM compras_gofast WHERE estado = 'en_proceso'"),
            'completadas' => (int) $wpdb->get_var("SELECT COUNT(*) FROM compras_gofast WHERE estado = 'completada'"),
            'canceladas' => (int) $wpdb->get_var("SELECT COUNT(*) FROM compras_gofast WHERE estado = 'cancelada'"),
            'total_valor_pendiente' => (float) $wpdb->get_var("SELECT SUM(valor) FROM compras_gofast WHERE estado = 'pendiente'"),
            'total_valor_completada' => (float) $wpdb->get_var("SELECT SUM(valor) FROM compras_gofast WHERE estado = 'completada'")
        ];
    }

    ob_start();
    ?>

<div class="gofast-home">
    <?php if ($rol === 'admin'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="margin-bottom:8px;">🛒 Compras</h1>
                <p class="gofast-home-text" style="margin:0;">
                    Gestiona las compras de los mensajeros.
                </p>
            </div>
            <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;">
                ← Volver al Dashboard
            </a>
        </div>
    <?php endif; ?>
    
    <!-- Mensaje de resultado -->
    <?php if ($mensaje): ?>
        <div class="gofast-box" style="background: <?= $mensaje_tipo === 'success' ? '#d4edda' : '#f8d7da' ?>; border-left: 4px solid <?= $mensaje_tipo === 'success' ? '#28a745' : '#dc3545' ?>; color: <?= $mensaje_tipo === 'success' ? '#155724' : '#721c24' ?>; margin-bottom: 20px;">
            <?= esc_html($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- Estadísticas (Solo Admin) -->
    <?php if ($rol === 'admin' && $stats): ?>
        <div class="gofast-box" style="margin-bottom: 20px;">
            <h3>📊 Estadísticas de Compras</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-top: 15px;">
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: #333;"><?= $stats['total'] ?></div>
                    <div style="font-size: 13px; color: #666;">Total</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #fff3cd; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: #856404;"><?= $stats['pendientes'] ?></div>
                    <div style="font-size: 13px; color: #666;">Pendientes</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #cfe2ff; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: #084298;"><?= $stats['en_proceso'] ?></div>
                    <div style="font-size: 13px; color: #666;">En Proceso</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: #155724;"><?= $stats['completadas'] ?></div>
                    <div style="font-size: 13px; color: #666;">Completadas</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: #721c24;"><?= $stats['canceladas'] ?></div>
                    <div style="font-size: 13px; color: #666;">Canceladas</div>
                </div>
            </div>
            <?php if ($stats['total_valor_pendiente'] > 0 || $stats['total_valor_completada'] > 0): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <div style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <strong>Pendiente:</strong> $<?= number_format($stats['total_valor_pendiente'], 0, ',', '.') ?>
                        </div>
                        <div>
                            <strong>Completada:</strong> $<?= number_format($stats['total_valor_completada'], 0, ',', '.') ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Formulario para crear compra -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <h3>🛒 Crear Compra</h3>
        
        <form method="post" id="form-crear-compra">
            <?php wp_nonce_field('gofast_crear_compra', 'gofast_compra_nonce'); ?>
            
            <?php if ($rol === 'admin'): ?>
                <label><strong>Mensajero</strong> <span style="color: #dc3545;">*</span></label>
                <select name="mensajero_id" class="gofast-select" id="mensajero-select" required>
                    <option value="">— Selecciona mensajero —</option>
                    <?php foreach ($mensajeros as $m): ?>
                        <option value="<?= esc_attr($m->id) ?>">
                            <?= esc_html($m->nombre) ?> — <?= esc_html($m->telefono) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <div style="background: #e7f3ff; padding: 12px; border-radius: 6px; margin-bottom: 16px;">
                    <strong>Mensajero:</strong> <?= esc_html($usuario->nombre) ?>
                </div>
            <?php endif; ?>

            <label><strong>Valor de la compra</strong></label>
            <input type="number" 
                   name="valor" 
                   id="valor-compra"
                   step="1" 
                   min="6000" 
                   max="20000"
                   required 
                   placeholder="Ej: 10000"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; margin-bottom: 16px;">

            <label><strong>Destino</strong></label>
            <select name="barrio_id" class="gofast-select" id="destino-compra" required>
                <option value="">Buscar destino...</option>
                <?php if (!empty($barrios)): ?>
                    <?php foreach ($barrios as $b): ?>
                        <option value="<?= esc_attr($b->id) ?>">
                            <?= esc_html($b->nombre) ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback: mostrar todos los barrios directamente si $barrios está vacío -->
                    <?php 
                    $barrios_fallback = $wpdb->get_results("SELECT id, nombre FROM barrios ORDER BY nombre ASC");
                    if (!empty($barrios_fallback)): 
                    ?>
                        <?php foreach ($barrios_fallback as $b): ?>
                            <option value="<?= esc_attr($b->id) ?>">
                                <?= esc_html($b->nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </select>

            <div class="gofast-btn-group" style="margin-top: 20px;">
                <button type="submit" name="gofast_crear_compra" class="gofast-submit">
                    ✅ Crear Compra
                </button>
            </div>
        </form>
    </div>

    <!-- Filtros -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <h3>🔍 Filtros</h3>
        <form method="get" action="" class="gofast-filtros-form">
            <!-- Mantener otros parámetros GET si existen -->
            <?php foreach ($_GET as $key => $value): ?>
                <?php if (!in_array($key, ['fecha_desde', 'fecha_hasta', 'valor_min', 'valor_max', 'estado'])): ?>
                    <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha desde</label>
                    <input type="date" 
                           name="fecha_desde" 
                           value="<?= esc_attr($fecha_desde) ?>"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha hasta</label>
                    <input type="date" 
                           name="fecha_hasta" 
                           value="<?= esc_attr($fecha_hasta) ?>"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor mínimo</label>
                    <input type="number" 
                           name="valor_min" 
                           value="<?= $valor_min > 0 ? esc_attr($valor_min) : '' ?>"
                           step="0.01"
                           min="0"
                           placeholder="Ej: 10000"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor máximo</label>
                    <input type="number" 
                           name="valor_max" 
                           value="<?= $valor_max > 0 ? esc_attr($valor_max) : '' ?>"
                           step="0.01"
                           min="0"
                           placeholder="Ej: 100000"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Estado</label>
                    <select name="estado" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        <option value="">Todos</option>
                        <option value="pendiente" <?= $estado_filtro === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                        <option value="en_proceso" <?= $estado_filtro === 'en_proceso' ? 'selected' : '' ?>>🔄 En Proceso</option>
                        <option value="completada" <?= $estado_filtro === 'completada' ? 'selected' : '' ?>>✅ Completada</option>
                        <option value="cancelada" <?= $estado_filtro === 'cancelada' ? 'selected' : '' ?>>❌ Cancelada</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); flex: 1; min-width: 120px;">
                    🔍 Filtrar
                </button>
                <?php
                // Construir URL sin los parámetros de filtro
                $clean_url = remove_query_arg(['fecha_desde', 'fecha_hasta', 'valor_min', 'valor_max', 'estado']);
                if (empty($clean_url) || $clean_url === home_url('/')) {
                    $clean_url = home_url('/compras');
                }
                ?>
                <a href="<?= esc_url($clean_url) ?>" 
                   class="gofast-btn gofast-secondary" 
                   style="flex: 1; min-width: 120px; text-align: center; text-decoration: none; display: inline-block;">
                    🔄 Limpiar
                </a>
            </div>
        </form>
        
        <?php if (!empty($compras) || !empty($fecha_desde) || !empty($fecha_hasta) || $valor_min > 0 || $valor_max > 0 || !empty($estado_filtro)): ?>
            <div style="margin-top: 12px; padding: 10px; background: #e7f3ff; border-radius: 6px; font-size: 13px;">
                <strong>Resultados:</strong> 
                <?= count($compras) ?> compra(s) encontrada(s)
                <?php if (isset($total_sin_filtros) && $total_sin_filtros > count($compras)): ?>
                    (de <?= $total_sin_filtros ?> total)
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Listado de compras -->
    <div class="gofast-box">
        <h3><?= $rol === 'admin' ? '📋 Todas las Compras' : '📋 Mis Compras' ?></h3>
        
        <?php if (empty($compras)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                No hay compras registradas.
            </p>
        <?php else: ?>
            <!-- Vista Desktop: Tabla -->
            <div class="gofast-compras-table-wrapper gofast-compras-desktop">
                <div class="gofast-table-wrap">
                    <table class="gofast-table gofast-compras-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <?php if ($rol === 'admin'): ?>
                                <th>Mensajero</th>
                                <th>Creado por</th>
                            <?php endif; ?>
                            <th>Valor</th>
                            <th>Destino</th>
                            <th>Estado</th>
                            <?php if ($rol === 'admin'): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $c): ?>
                            <tr>
                                <td>#<?= esc_html($c->id) ?></td>
                                <td><?= gofast_date_format($c->fecha_creacion, 'd/m/Y H:i') ?></td>
                                <?php if ($rol === 'admin'): ?>
                                    <td>
                                        <?= esc_html($c->mensajero_nombre) ?><br>
                                        <small style="color: #666;"><?= esc_html($c->mensajero_telefono) ?></small>
                                    </td>
                                    <td><?= esc_html($c->creador_nombre) ?></td>
                                <?php endif; ?>
                                <td>
                                    <strong>$<?= number_format($c->valor, 0, ',', '.') ?></strong>
                                </td>
                                <td>
                                    <?= esc_html($c->barrio_nombre ?? 'N/A') ?>
                                </td>
                                <td>
                                    <?php
                                    $estado_class = '';
                                    $estado_text = '';
                                    switch ($c->estado) {
                                        case 'pendiente':
                                            $estado_class = 'gofast-badge-estado-pendiente';
                                            $estado_text = '⏳ Pendiente';
                                            break;
                                        case 'en_proceso':
                                            $estado_class = 'gofast-badge-estado-en-ruta';
                                            $estado_text = '🔄 En Proceso';
                                            break;
                                        case 'completada':
                                            $estado_class = 'gofast-badge-estado-entregado';
                                            $estado_text = '✅ Completada';
                                            break;
                                        case 'cancelada':
                                            $estado_class = 'gofast-badge-estado-cancelado';
                                            $estado_text = '❌ Cancelada';
                                            break;
                                    }
                                    
                                    // Mostrar select de estado si es admin o mensajero (solo sus compras)
                                    $puede_cambiar_estado = false;
                                    if ($rol === 'admin') {
                                        $puede_cambiar_estado = true;
                                    } elseif ($rol === 'mensajero' && (int) $c->mensajero_id === $user_id) {
                                        $puede_cambiar_estado = true;
                                    }
                                    ?>
                                    
                                    <?php if ($puede_cambiar_estado): ?>
                                        <form method="post" style="display: inline-block;" class="gofast-estado-form">
                                            <?php wp_nonce_field('gofast_cambiar_estado', 'gofast_estado_nonce'); ?>
                                            <input type="hidden" name="gofast_cambiar_estado" value="1">
                                            <input type="hidden" name="compra_id" value="<?= esc_attr($c->id) ?>">
                                            <select name="nuevo_estado" 
                                                    onchange="this.form.submit();"
                                                    style="padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; cursor: pointer;">
                                                <option value="pendiente" <?= $c->estado === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                                                <option value="en_proceso" <?= $c->estado === 'en_proceso' ? 'selected' : '' ?>>🔄 En Proceso</option>
                                                <option value="completada" <?= $c->estado === 'completada' ? 'selected' : '' ?>>✅ Completada</option>
                                                <?php if ($rol === 'admin'): ?>
                                                    <option value="cancelada" <?= $c->estado === 'cancelada' ? 'selected' : '' ?>>❌ Cancelada</option>
                                                <?php endif; ?>
                                            </select>
                                        </form>
                                    <?php else: ?>
                                        <span class="gofast-badge-estado <?= $estado_class ?>">
                                            <?= $estado_text ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($rol === 'admin'): ?>
                                    <td style="white-space:nowrap;">
                                        <button type="button" 
                                                class="gofast-btn-mini gofast-btn-editar-compra" 
                                                data-compra-id="<?= esc_attr($c->id) ?>"
                                                data-compra-valor="<?= esc_attr($c->valor) ?>"
                                                data-compra-barrio-id="<?= esc_attr($c->barrio_id) ?>"
                                                data-compra-barrio-nombre="<?= esc_attr($c->barrio_nombre ?? 'N/A') ?>"
                                                data-compra-mensajero="<?= esc_attr($c->mensajero_nombre) ?>"
                                                data-compra-fecha="<?= esc_attr(gofast_date_format($c->fecha_creacion, 'd/m/Y H:i')) ?>"
                                                data-compra-estado="<?= esc_attr($c->estado) ?>">
                                            ✏️ Editar
                                        </button>
                                        <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar esta compra? Esta acción no se puede deshacer.');">
                                            <?php wp_nonce_field('gofast_eliminar_compra', 'gofast_eliminar_compra_nonce'); ?>
                                            <input type="hidden" name="gofast_eliminar_compra" value="1">
                                            <input type="hidden" name="compra_id" value="<?= esc_attr($c->id) ?>">
                                            <button type="submit" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                🗑️ Eliminar
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Vista Móvil: Cards -->
            <div class="gofast-compras-cards gofast-compras-mobile">
                <?php foreach ($compras as $c): ?>
                    <?php
                    $estado_class = '';
                    $estado_text = '';
                    $estado_color = '';
                    switch ($c->estado) {
                        case 'pendiente':
                            $estado_class = 'gofast-badge-estado-pendiente';
                            $estado_text = '⏳ Pendiente';
                            $estado_color = '#fff3cd';
                            break;
                        case 'en_proceso':
                            $estado_class = 'gofast-badge-estado-en-ruta';
                            $estado_text = '🔄 En Proceso';
                            $estado_color = '#cfe2ff';
                            break;
                        case 'completada':
                            $estado_class = 'gofast-badge-estado-entregado';
                            $estado_text = '✅ Completada';
                            $estado_color = '#d4edda';
                            break;
                        case 'cancelada':
                            $estado_class = 'gofast-badge-estado-cancelado';
                            $estado_text = '❌ Cancelada';
                            $estado_color = '#f8d7da';
                            break;
                    }
                    ?>
                    <div class="gofast-compra-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 12px; border-left: 4px solid <?= $estado_color ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div>
                                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">ID: #<?= esc_html($c->id) ?></div>
                                <div style="font-size: 11px; color: #999;"><?= gofast_date_format($c->fecha_creacion, 'd/m/Y H:i') ?></div>
                            </div>
                            <span class="gofast-badge-estado <?= $estado_class ?>" style="font-size: 12px; padding: 4px 10px;">
                                <?= $estado_text ?>
                            </span>
                        </div>

                        <div style="margin-bottom: 12px;">
            <?php if ($rol === 'admin'): ?>
                <!-- Admin puede editar valor -->
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px;">Valor:</label>
                <form method="post">
                    <?php wp_nonce_field('gofast_editar_valor', 'gofast_editar_valor_nonce'); ?>
                    <input type="hidden" name="gofast_editar_valor" value="1">
                    <input type="hidden" name="compra_id" value="<?= esc_attr($c->id) ?>">
                    <input type="number" 
                           name="nuevo_valor" 
                           value="<?= esc_attr($c->valor) ?>"
                           step="1"
                           min="6000"
                           max="20000"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; font-weight: 700;"
                           onchange="if(confirm('¿Actualizar valor de esta compra?')) this.form.submit();">
                </form>
            <?php else: ?>
                                <div style="font-size: 24px; font-weight: 700; color: #000;">
                                    $<?= number_format($c->valor, 0, ',', '.') ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Destino:</div>
                            <div style="font-size: 14px; font-weight: 600; color: #000;">
                                <?= esc_html($c->barrio_nombre ?? 'N/A') ?>
                            </div>
                        </div>

                        <?php if ($rol === 'admin'): ?>
                            <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Mensajero:</div>
                                <div style="font-size: 14px; font-weight: 600; color: #000;">
                                    <?= esc_html($c->mensajero_nombre) ?>
                                </div>
                                <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                    <?= esc_html($c->mensajero_telefono) ?>
                                </div>
                            </div>
                            <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Creado por:</div>
                                <div style="font-size: 14px; font-weight: 600; color: #000;">
                                    <?= esc_html($c->creador_nombre) ?>
                                </div>
                            </div>
                            
                            <!-- Cambiar estado -->
                            <form method="post" style="margin-top: 12px; margin-bottom: 12px;" class="gofast-estado-form">
                                <?php wp_nonce_field('gofast_cambiar_estado', 'gofast_estado_nonce'); ?>
                                <input type="hidden" name="gofast_cambiar_estado" value="1">
                                <input type="hidden" name="compra_id" value="<?= esc_attr($c->id) ?>">
                                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px;">Cambiar estado:</label>
                                <select name="nuevo_estado" 
                                        onchange="this.form.submit();"
                                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                    <option value="pendiente" <?= $c->estado === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                                    <option value="en_proceso" <?= $c->estado === 'en_proceso' ? 'selected' : '' ?>>🔄 En Proceso</option>
                                    <option value="completada" <?= $c->estado === 'completada' ? 'selected' : '' ?>>✅ Completada</option>
                                    <option value="cancelada" <?= $c->estado === 'cancelada' ? 'selected' : '' ?>>❌ Cancelada</option>
                                </select>
                            </form>
                            
                            <!-- Botones de acción -->
                            <div style="display: flex; gap: 8px; margin-top: 12px;">
                                <button type="button" 
                                        class="gofast-btn-mini gofast-btn-editar-compra" 
                                        data-compra-id="<?= esc_attr($c->id) ?>"
                                        data-compra-valor="<?= esc_attr($c->valor) ?>"
                                        data-compra-barrio-id="<?= esc_attr($c->barrio_id) ?>"
                                        data-compra-barrio-nombre="<?= esc_attr($c->barrio_nombre ?? 'N/A') ?>"
                                        data-compra-mensajero="<?= esc_attr($c->mensajero_nombre) ?>"
                                        data-compra-fecha="<?= esc_attr(gofast_date_format($c->fecha_creacion, 'd/m/Y H:i')) ?>"
                                        data-compra-estado="<?= esc_attr($c->estado) ?>"
                                        style="flex: 1; padding: 10px; background: var(--gofast-yellow); color: #000; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                    ✏️ Editar
                                </button>
                                
                                <form method="post" style="flex: 1;" onsubmit="return confirm('¿Estás seguro de eliminar esta compra? Esta acción no se puede deshacer.');">
                                    <?php wp_nonce_field('gofast_eliminar_compra', 'gofast_eliminar_compra_nonce'); ?>
                                    <input type="hidden" name="gofast_eliminar_compra" value="1">
                                    <input type="hidden" name="compra_id" value="<?= esc_attr($c->id) ?>">
                                    <button type="submit" 
                                            style="width: 100%; padding: 10px; background: #dc3545; color: white; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                        🗑️ Eliminar
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Mensajero: solo puede cambiar estado de sus compras -->
                            <?php if ((int) $c->mensajero_id === $user_id): ?>
                                <form method="post" style="margin-top: 12px;" class="gofast-estado-form">
                                    <?php wp_nonce_field('gofast_cambiar_estado', 'gofast_estado_nonce'); ?>
                                    <input type="hidden" name="gofast_cambiar_estado" value="1">
                                    <input type="hidden" name="compra_id" value="<?= esc_attr($c->id) ?>">
                                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px;">Cambiar estado:</label>
                                    <select name="nuevo_estado" 
                                            onchange="this.form.submit();"
                                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                        <option value="pendiente" <?= $c->estado === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                                        <option value="en_proceso" <?= $c->estado === 'en_proceso' ? 'selected' : '' ?>>🔄 En Proceso</option>
                                        <option value="completada" <?= $c->estado === 'completada' ? 'selected' : '' ?>>✅ Completada</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php if ($rol === 'admin'): ?>
<!-- Modal para editar compra -->
<div id="modal-editar-compra" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
    <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Compra</h2>
        
        <!-- Información de la compra -->
        <div id="compra-info" style="background:#f8f9fa;border-left:4px solid var(--gofast-yellow);padding:12px;border-radius:6px;margin-bottom:16px;font-size:13px;">
            <div style="display:grid;grid-template-columns:auto 1fr;gap:8px 12px;align-items:start;">
                <strong style="color:#666;">Compra #:</strong>
                <span id="info-compra-id" style="font-weight:600;color:#000;"></span>
                
                <strong style="color:#666;">Mensajero:</strong>
                <span id="info-mensajero" style="font-weight:600;color:#000;"></span>
                
                <strong style="color:#666;">Fecha:</strong>
                <span id="info-fecha" style="color:#000;"></span>
                
                <strong style="color:#666;">Estado:</strong>
                <span id="info-estado" style="font-weight:600;color:#000;"></span>
            </div>
        </div>
        
        <form method="post" id="form-editar-compra" action="">
            <?php wp_nonce_field('gofast_editar_compra', 'gofast_editar_compra_nonce'); ?>
            <input type="hidden" name="gofast_editar_compra" value="1">
            <input type="hidden" name="compra_id" id="editar-compra-id">
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Valor de la compra:</label>
                <input type="number" 
                       name="nuevo_valor" 
                       id="editar-compra-valor"
                       step="1"
                       min="6000"
                       max="20000"
                       required
                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Destino:</label>
                <select name="nuevo_barrio_id" 
                        id="editar-compra-barrio"
                        class="gofast-select" 
                        required>
                    <option value="">Buscar destino...</option>
                    <?php if (!empty($barrios)): ?>
                        <?php foreach ($barrios as $b): ?>
                            <option value="<?= esc_attr($b->id) ?>">
                                <?= esc_html($b->nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Fallback: mostrar todos los barrios directamente si $barrios está vacío -->
                        <?php 
                        $barrios_fallback = $wpdb->get_results("SELECT id, nombre FROM barrios ORDER BY nombre ASC");
                        if (!empty($barrios_fallback)): 
                        ?>
                            <?php foreach ($barrios_fallback as $b): ?>
                                <option value="<?= esc_attr($b->id) ?>">
                                    <?= esc_html($b->nombre) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditarCompra()">Cancelar</button>
                <button type="submit" class="gofast-btn-mini">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
/* Los estilos de gofast-home ya están en css.css */

/* Estilos para tabla de compras */
.gofast-compras-table-wrapper .gofast-table-wrap {
    width: 100%;
    max-width: 100%;
    overflow-x: auto !important;
    overflow-y: visible !important;
    -webkit-overflow-scrolling: touch;
    display: block;
    margin: 0;
    padding: 0;
}

.gofast-compras-table-wrapper .gofast-compras-table {
    min-width: 800px;
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

/* Vista Desktop: Mostrar tabla, ocultar cards */
.gofast-compras-desktop {
    display: block;
}

/* Vista Móvil: Cards (oculta en desktop) */
.gofast-compras-mobile {
    display: none;
}

.gofast-compras-cards {
    width: 100%;
    max-width: 100%;
}

.gofast-compra-card {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Estilos para formulario de filtros */
.gofast-filtros-form {
    width: 100%;
}

.gofast-filtros-form label {
    display: block;
    font-weight: 600;
    margin-bottom: 4px;
    font-size: 13px;
    color: #000;
}

.gofast-filtros-form input[type="date"],
.gofast-filtros-form input[type="number"],
.gofast-filtros-form select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
    color: #000;
    box-sizing: border-box;
}

/* Responsive para móvil - tabla de compras */
@media (max-width: 768px) {
    
    .gofast-compras-table-wrapper {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: visible !important;
        margin: 0;
        padding: 0;
        display: block !important;
        visibility: visible !important;
    }
    
    .gofast-compras-table-wrapper .gofast-table-wrap {
        overflow-x: scroll !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        margin: 0;
        padding: 0;
        width: 100%;
        max-width: 100%;
        display: block !important;
        visibility: visible !important;
    }
    
    .gofast-compras-table-wrapper .gofast-table-wrap::-webkit-scrollbar {
        height: 8px;
    }
    
    .gofast-compras-table-wrapper .gofast-table-wrap::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .gofast-compras-table-wrapper .gofast-table-wrap::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    .gofast-compras-table-wrapper .gofast-compras-table {
        min-width: 850px;
        font-size: 13px;
        display: table !important;
        visibility: visible !important;
    }
    
    .gofast-compras-table-wrapper .gofast-compras-table th,
    .gofast-compras-table-wrapper .gofast-compras-table td {
        padding: 10px 8px;
        font-size: 12px;
        white-space: nowrap;
        display: table-cell !important;
        visibility: visible !important;
    }
    
    /* Ocultar columna "Creado por" en móvil para admin */
    .gofast-compras-table-wrapper .gofast-compras-table th:nth-child(4),
    .gofast-compras-table-wrapper .gofast-compras-table td:nth-child(4) {
        display: none !important;
    }
    
    /* Ocultar tabla en móvil, mostrar cards */
    .gofast-compras-desktop {
        display: none !important;
    }
    
    .gofast-compras-mobile {
        display: block !important;
    }
    
    .gofast-compras-cards {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }
    
    .gofast-compra-card {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    .gofast-compra-card * {
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    
    /* Filtros en móvil */
    .gofast-filtros-form > div {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
    
    .gofast-filtros-form button,
    .gofast-filtros-form a {
        width: 100% !important;
        min-width: 100% !important;
    }
    
}

/* Para pantallas muy pequeñas */
@media (max-width: 480px) {
    
    .gofast-compras-table-wrapper .gofast-compras-table {
        min-width: 750px;
        font-size: 12px;
    }
    
    .gofast-compras-table-wrapper .gofast-compras-table th,
    .gofast-compras-table-wrapper .gofast-compras-table td {
        padding: 8px 6px;
        font-size: 11px;
    }
    
    /* Ocultar ID en móvil muy pequeño */
    .gofast-compras-table-wrapper .gofast-compras-table th:first-child,
    .gofast-compras-table-wrapper .gofast-compras-table td:first-child {
        display: none !important;
    }
}

/* Desktop: Mostrar tabla, ocultar cards */
@media (min-width: 769px) {
    .gofast-compras-desktop {
        display: block !important;
    }
    
    .gofast-compras-mobile {
        display: none !important;
    }
}
</style>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_compras', 'gofast_compras_shortcode');

/***************************************************
 * JavaScript para Select2 en destino de compras
 * Igual que el cotizador, sin optgroups
 ***************************************************/
add_action('wp_footer', function() {
    if (is_page() && has_shortcode(get_post()->post_content, 'gofast_compras')) {
        ?>
        <script>
        (function(){
        
          /***************************************************
           *  Proteger contra errores de toggleOtro
           ***************************************************/
          if (typeof toggleOtro === 'function') {
            const originalToggleOtro = toggleOtro;
            window.toggleOtro = function() {
              try {
                const tipoSelect = document.getElementById("tipo_negocio");
                const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                if (tipoSelect && wrapperOtro && tipoSelect.parentNode && wrapperOtro.parentNode) {
                  originalToggleOtro();
                }
              } catch(e) {
                // Silenciar error completamente
                return;
              }
            };
          }
          
          // También prevenir que setTimeout ejecute toggleOtro si no están los elementos
          const originalSetTimeout = window.setTimeout;
          window.setTimeout = function(func, delay) {
            if (typeof func === 'function' && func.toString().includes('toggleOtro')) {
              const tipoSelect = document.getElementById("tipo_negocio");
              const wrapperOtro = document.getElementById("tipo_otro_wrapper");
              if (!tipoSelect || !wrapperOtro) {
                return null; // No ejecutar si no existen los elementos
              }
            }
            return originalSetTimeout.apply(this, arguments);
          };
        
          /***************************************************
           *  Normalizador (quita tildes) - GLOBAL
           ***************************************************/
          window.normalize = function(s) {
            return (s || "")
              .toLowerCase()
              .normalize("NFD")
              .replace(/[\u0300-\u036f]/g, "")
              .trim();
          };
          
          const normalize = window.normalize;
        
          /***************************************************
           *  MATCHER UNIFICADO (para destinos) - GLOBAL
           *  Maneja optgroups pero con lógica de búsqueda simple
           ***************************************************/
          window.matcherDestinos = function(params, data) {
            // Si no hay data, retornar null
            if (!data) return null;
            
            // Si es un optgroup (tiene children), dejarlo pasar para que Select2 lo maneje
            // Select2 procesará los hijos individualmente con este mismo matcher
            if (data.children && Array.isArray(data.children)) {
              return data;
            }
            
            // Si no tiene id, es un optgroup label o separador
            // Sin búsqueda, mostrarlo; con búsqueda, ocultarlo
            if (!data.id) {
              if (!params.term || !params.term.trim()) {
                return data;
              }
              return null;
            }
            
            // Si no tiene text, no mostrar
            if (!data.text) return null;
            
            // Si no hay término de búsqueda, mostrar todas las opciones
            if (!params.term || !params.term.trim()) {
              data.matchScore = 0;
              return data;
            }
        
            const term = normalize(params.term);
            if (!term) {
              data.matchScore = 0;
              return data;
            }
        
            const text = normalize(data.text);
            
            // PRIMERO: Verificar coincidencia exacta (ignorando mayúsculas y tildes)
            if (text === term) {
              data.matchScore = 10000;
              return data;
            }
            
            // SEGUNDO: Verificar si el texto comienza exactamente con el término
            if (text.indexOf(term) === 0) {
              data.matchScore = 9500;
              return data;
            }
            
            // Palabras comunes a ignorar en la búsqueda
            const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
            
            const searchWords = term.split(/\s+/).filter(Boolean).filter(word => {
              return word.length > 2 && !stopWords.includes(word);
            });
            
            // Detectar si el término de búsqueda parece ser completo (múltiples palabras o palabra larga)
            const isCompleteSearch = term.split(/\s+/).length >= 2 || term.length >= 10;
            
            // Si después de filtrar no quedan palabras, buscar el término completo
            if (searchWords.length === 0) {
              if (text.indexOf(term) !== -1) {
                data.matchScore = 7000;
                return data;
              }
              return null;
            }
            
            // Verificar que al menos una palabra significativa esté presente
            const significantMatches = searchWords.filter(word => {
              if (word.length <= 2) {
                return text.split(/\s+/).some(textWord => textWord.indexOf(word) === 0);
              }
              return text.indexOf(word) !== -1;
            });
            
            if (significantMatches.length === 0) return null;
            
            const allSignificantMatch = searchWords.length === significantMatches.length;
            
            // Si la búsqueda parece completa y no hay coincidencia exacta, ser más estricto
            if (isCompleteSearch && text !== term && text.indexOf(term) !== 0) {
              // Solo mostrar si el término completo está presente (aunque no al inicio)
              if (text.indexOf(term) === -1) {
                // Verificar si al menos todas las palabras significativas están presentes
                const allWordsPresent = searchWords.every(word => text.indexOf(word) !== -1);
                if (!allWordsPresent) {
                  return null; // Filtrar si no están todas las palabras
                }
              }
            }
        
            // Sistema de puntuación
            let score = 0;
            
            const textWithoutStopWords = text.split(/\s+/).filter(w => !stopWords.includes(w)).join(' ');
            const termWithoutStopWords = searchWords.join(' ');
            
            // Coincidencia exacta sin stop words
            if (textWithoutStopWords === termWithoutStopWords) {
              score = 10000;
            } 
            // El término completo (sin stop words) está al inicio
            else if (textWithoutStopWords.indexOf(termWithoutStopWords) === 0) {
              score = 9000;
            } 
            // El término completo (sin stop words) está en cualquier parte
            else if (textWithoutStopWords.indexOf(termWithoutStopWords) !== -1) {
              score = 8000;
            } 
            // Al menos una palabra significativa al inicio
            else if (searchWords.some(word => text.indexOf(word) === 0)) {
              score = 7000;
            } 
            // El término completo está en cualquier parte
            else if (text.indexOf(term) !== -1) {
              score = 6000;
            } 
            // Coincidencias parciales
            else {
              score = allSignificantMatch ? 5000 : 3000;
              
              let lastIndex = -1;
              let wordsInOrder = true;
              searchWords.forEach(word => {
                const wordIndex = text.indexOf(word, lastIndex + 1);
                if (wordIndex === -1) {
                  wordsInOrder = false;
                } else {
                  if (wordIndex < lastIndex) wordsInOrder = false;
                  lastIndex = wordIndex;
                  if (text.indexOf(word) === 0) score += 500;
                }
              });
              
              if (wordsInOrder) score += 1000;
            }
            
            data.matchScore = score;
            return data;
          };
          
          const matcherDestinos = window.matcherDestinos;
        
          /***************************************************
           *  INIT SELECT2 (UNIFICADO + ALWAYS DOWN)
           *  selector: selector del select
           *  dropdownParent: (opcional) elemento padre para el dropdown (por defecto: body)
           ***************************************************/
          function initSelect2(selector, dropdownParent){
            if (window.jQuery && jQuery.fn.select2) {
              const $select = jQuery(selector);
              
              if (!$select.length) return;
              
              // Si ya está inicializado, destruirlo primero
              if ($select.data('select2')) {
                try {
                  $select.select2('destroy');
                } catch(e) {
                  // Ignorar errores
                }
                // Limpiar contenedor
                $select.next('.select2-container').remove();
                $select.removeClass('select2-hidden-accessible');
                $select.removeAttr('data-select2-id');
              }
              
              // Determinar el parent del dropdown
              const $dropdownParent = dropdownParent ? jQuery(dropdownParent) : jQuery('body');
              
              // Usar el mismo matcher para todos (origen y destinos)
              // La presentación de optgroups se mantiene en el HTML
              $select.select2({
                  placeholder: "🔍 Escribe para buscar dirección...",
                  width: '100%',
                  dropdownParent: $dropdownParent,
                  allowClear: true,
                  minimumResultsForSearch: 0,  // SIEMPRE mostrar campo de búsqueda
                  selectOnClose: true,
                  dropdownAutoWidth: true,
                  dropdownCssClass: "gofast-select-down",
                  // Forzar que Select2 procese optgroups correctamente
                  templateSelection: function(data) {
                    return data.text || data.id;
                  },
        
                // Usar el mismo matcher para todos (simplificado, igual que destino)
                matcher: matcherDestinos,
        
                sorter: function(results){
                  return results.sort(function(a,b){
                    return (b.matchScore || 0) - (a.matchScore || 0);
                  });
                },
                
        
                templateResult: function(data, container){
                  // Manejar optgroups (no tienen id pero tienen children)
                  if (!data || !data.text) {
                    if (data && data.children) return data.text; // Retornar label del optgroup
                    return data ? data.text : '';
                  }
                  
                  // Si no tiene id, es un optgroup label, retornar sin modificar
                  if (!data.id) return data.text;
        
                  let originalText = data.text;
                  
                  // Obtener el término de búsqueda del campo activo
                  let searchTerm = "";
                  const $activeField = jQuery('.select2-container--open .select2-search__field');
                  if ($activeField.length) {
                    searchTerm = $activeField.val() || "";
                  }
                  
                  if (!searchTerm || !searchTerm.trim()) {
                    const $result = jQuery('<span>' + originalText + '</span>');
                    if (data.matchScore !== undefined) {
                      $result.attr('data-match-score', data.matchScore);
                    }
                    return $result;
                  }
        
                  // Normalizar para búsqueda sin tildes
                  const normalizedSearch = normalize(searchTerm);
                  const normalizedText = normalize(originalText);
                  
                  // Dividir término en palabras significativas
                  const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
                  const searchWords = normalizedSearch.split(/\s+/).filter(Boolean).filter(word => {
                    return word.length > 2 && !stopWords.includes(word);
                  });
                  
                  // Si no hay palabras significativas, buscar el término completo
                  const wordsToHighlight = searchWords.length > 0 ? searchWords : [normalizedSearch];
                  
                  // Encontrar coincidencias en el texto normalizado y mapear a texto original
                  const highlightRanges = [];
                  
                  wordsToHighlight.forEach(function(word) {
                    let searchPos = 0;
                    while ((searchPos = normalizedText.indexOf(word, searchPos)) !== -1) {
                      const endPos = searchPos + word.length;
                      
                      // Mapear posiciones normalizadas a originales
                      // Construir texto normalizado carácter por carácter para mapear correctamente
                      let origStart = -1;
                      let origEnd = -1;
                      let normPos = 0;
                      
                      // Encontrar inicio
                      for (let i = 0; i < originalText.length && origStart === -1; i++) {
                        const charNorm = normalize(originalText[i]);
                        if (normPos === searchPos) {
                          origStart = i;
                        }
                        normPos += charNorm.length;
                      }
                      
                      // Encontrar fin
                      if (origStart >= 0) {
                        normPos = searchPos;
                        for (let i = origStart; i < originalText.length; i++) {
                          const charNorm = normalize(originalText[i]);
                          normPos += charNorm.length;
                          if (normPos >= endPos) {
                            origEnd = i + 1;
                            break;
                          }
                        }
                        
                        if (origStart >= 0 && origEnd > origStart) {
                          highlightRanges.push({ start: origStart, end: origEnd });
                        }
                      }
                      
                      searchPos = endPos;
                    }
                  });
                  
                  // Fusionar rangos solapados
                  if (highlightRanges.length > 0) {
                    highlightRanges.sort((a, b) => a.start - b.start);
                    const mergedRanges = [highlightRanges[0]];
                    
                    for (let i = 1; i < highlightRanges.length; i++) {
                      const current = highlightRanges[i];
                      const last = mergedRanges[mergedRanges.length - 1];
                      
                      if (current.start <= last.end) {
                        last.end = Math.max(last.end, current.end);
                      } else {
                        mergedRanges.push(current);
                      }
                    }
                    
                    // Construir resultado con resaltados
                    const parts = [];
                    let lastIndex = 0;
                    
                    mergedRanges.forEach(function(range) {
                      // Agregar texto antes del rango
                      if (range.start > lastIndex) {
                        parts.push(originalText.substring(lastIndex, range.start));
                      }
                      
                      // Agregar texto resaltado
                      const matchText = originalText.substring(range.start, range.end);
                      parts.push('<span style="background-color:#F4C524;color:#000;font-weight:bold;padding:1px 2px;">' + 
                                 matchText + '</span>');
                      
                      lastIndex = range.end;
                    });
                    
                    // Agregar texto restante después del último rango
                    if (lastIndex < originalText.length) {
                      parts.push(originalText.substring(lastIndex));
                    }
                    
                    const result = parts.join('');
                    
                    // Crear elemento jQuery con el HTML renderizado correctamente
                    const $result = jQuery('<span>').html(result);
                    // Agregar atributo data con el score para poder filtrar después
                    if (data.matchScore !== undefined) {
                      $result.attr('data-match-score', data.matchScore);
                    }
                    return $result;
                  }
                  
                  // Si no hay coincidencias, retornar texto sin resaltar
                  const $result = jQuery('<span>').text(originalText);
                  if (data.matchScore !== undefined) {
                    $result.attr('data-match-score', data.matchScore);
                  }
                  return $result;
                }
                }).on('select2:open', function(e) {
                  const $select = jQuery(this);
                  const $container = $select.next('.select2-container');
                  
                  // FORZAR que el dropdown siempre abra hacia abajo
                  // Usar requestAnimationFrame para asegurar que se ejecute después de que Select2 posicione el dropdown
                  requestAnimationFrame(function() {
                    // Remover clase --above si existe y agregar --below
                    $container.removeClass('select2-container--above').addClass('select2-container--below');
                  });
                });
            }
          }
        
          // Inicializar cuando el DOM esté listo
          jQuery(document).ready(function($) {
            if ($('#destino-compra').length) {
              initSelect2('#destino-compra');
            }
            
            // Asegurar que el formulario de crear compra se envíe correctamente
            $('#form-crear-compra').on('submit', function(e) {
              // Verificar que el Select2 tenga un valor seleccionado
              const $select = $('#destino-compra');
              if ($select.length && $select.data('select2')) {
                const selectedValue = $select.val();
                if (!selectedValue || selectedValue === '') {
                  e.preventDefault();
                  alert('Por favor selecciona un destino.');
                  $select.next('.select2-container').find('.select2-selection').focus();
                  return false;
                }
              }
              
              // Verificar que el valor esté en el rango correcto
              const valor = parseFloat($('#valor-compra').val() || 0);
              if (valor < 6000 || valor > 20000) {
                e.preventDefault();
                alert('El valor debe estar entre 6000 y 20000.');
                $('#valor-compra').focus();
                return false;
              }
              
              // Si es admin, verificar que haya seleccionado un mensajero
              const $mensajeroSelect = $('#mensajero-select');
              if ($mensajeroSelect.length) {
                const mensajeroValue = $mensajeroSelect.val();
                if (!mensajeroValue || mensajeroValue === '') {
                  e.preventDefault();
                  alert('Por favor selecciona un mensajero.');
                  $mensajeroSelect.focus();
                  return false;
                }
              }
              
              return true;
            });
            
            // Abrir modal de editar compra
            $(document).on('click', '.gofast-btn-editar-compra', function(e) {
              e.preventDefault();
              e.stopPropagation();
              
              const btn = $(this);
              const compraId = btn.attr('data-compra-id');
              const compraValor = btn.attr('data-compra-valor');
              const compraBarrioId = btn.attr('data-compra-barrio-id');
              const compraBarrioNombre = btn.attr('data-compra-barrio-nombre');
              const compraMensajero = btn.attr('data-compra-mensajero');
              const compraFecha = btn.attr('data-compra-fecha');
              const compraEstado = btn.attr('data-compra-estado');
              
              // Verificar que el modal existe
              const $modal = $('#modal-editar-compra');
              if (!$modal.length) {
                console.error('Modal #modal-editar-compra no encontrado');
                alert('Error: El modal de edición no se encontró en la página.');
                return false;
              }
              
              // Llenar información del formulario
              $('#editar-compra-id').val(compraId || '');
              $('#editar-compra-valor').val(compraValor || '');
              
              // Llenar información de visualización
              $('#info-compra-id').text('#' + (compraId || ''));
              $('#info-mensajero').text(compraMensajero || '—');
              $('#info-fecha').text(compraFecha || '—');
              
              // Estado con emoji
              let estadoText = '';
              switch(compraEstado) {
                case 'pendiente': estadoText = '⏳ Pendiente'; break;
                case 'en_proceso': estadoText = '🔄 En Proceso'; break;
                case 'completada': estadoText = '✅ Completada'; break;
                case 'cancelada': estadoText = '❌ Cancelada'; break;
                default: estadoText = compraEstado || '—';
              }
              $('#info-estado').text(estadoText);
              
              // Mostrar modal
              $modal.fadeIn(200, function() {
                // Inicializar Select2 después de mostrar el modal
                // Usar un timeout para asegurar que el modal esté completamente renderizado
                setTimeout(function() {
                  if (window.initSelect2Modal && typeof window.initSelect2Modal === 'function') {
                    try {
                      // Inicializar Select2 usando la misma función que crear compra
                      window.initSelect2Modal();
                      
                      // Establecer el valor del barrio después de inicializar Select2
                      const $select = $('#editar-compra-barrio');
                      if (compraBarrioId && $select.length) {
                        setTimeout(function() {
                          $select.val(compraBarrioId).trigger('change');
                        }, 150);
                      }
                    } catch(err) {
                      console.error('Error al inicializar Select2:', err);
                      // Si falla Select2, establecer el valor directamente
                      if (compraBarrioId) {
                        $('#editar-compra-barrio').val(compraBarrioId);
                      }
                    }
                  } else {
                    // Si initSelect2Modal no está disponible, usar initSelect2 directamente
                    if (typeof initSelect2 === 'function') {
                      initSelect2('#editar-compra-barrio', $modal);
                      if (compraBarrioId) {
                        setTimeout(function() {
                          $('#editar-compra-barrio').val(compraBarrioId).trigger('change');
                        }, 150);
                      }
                    } else if (compraBarrioId) {
                      $('#editar-compra-barrio').val(compraBarrioId);
                    }
                  }
                }, 200);
              });
              
              return false;
            });
            
            // Cerrar modal
            window.cerrarModalEditarCompra = function() {
              $('#modal-editar-compra').fadeOut(200);
            };
            
            // Cerrar modal al hacer clic fuera
            $(document).on('click', '#modal-editar-compra', function(e) {
              if (e.target === this) {
                window.cerrarModalEditarCompra();
              }
            });
            
            <?php if ($rol === 'admin'): ?>
            // Función para inicializar Select2 en el modal de edición
            // Usa la misma función initSelect2 pero con el modal como dropdownParent
            window.initSelect2Modal = function() {
              const $modal = jQuery('#modal-editar-compra');
              if ($modal.length) {
                // Usar la misma función initSelect2 pero con el modal como parent
                initSelect2('#editar-compra-barrio', $modal);
              }
            };
            <?php endif; ?>
          });
        
        })();
        </script>
        <?php
    }
});


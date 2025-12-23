<?php
/***************************************************
 * GOFAST – MÓDULO DE FINANZAS ADMINISTRATIVO
 * Shortcode: [gofast_finanzas_admin]
 * URL: /admin-finanzas
 * 
 * Funcionalidades:
 * - Tab Ingresos: Visualizar ingresos y comisiones diarias
 * - Tab Egresos: CRUD de egresos
 * - Tab Vales Empresa: CRUD de vales de empresa
 * - Tab Vales Personal: CRUD de vales del personal
 * - Tab Transferencias Entradas: Visualizar transferencias entrantes
 * - Tab Transferencias Salidas: CRUD de transferencias salientes
 * - Tab Saldos Mensajeros: Gestión de pagos y descuentos a mensajeros
 * - Bloque de Resultados Generales: Cálculos consolidados
 ***************************************************/
function gofast_finanzas_admin_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Validar usuario admin
    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión para acceder a esta sección.</div>";
    }

    $user_id = (int) $_SESSION['gofast_user_id'];
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? 'cliente');

    if ($rol !== 'admin') {
        return "<div class='gofast-box'>⚠️ Solo los administradores pueden acceder a esta sección.</div>";
    }

    $mensaje = '';
    $mensaje_tipo = '';
    $tab_activo = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'ingresos';
    $subtab_saldos = isset($_GET['subtab_saldos']) ? sanitize_text_field($_GET['subtab_saldos']) : 'saldos_pendientes';
    
    // Mostrar mensajes de éxito
    if (isset($_GET['mensaje'])) {
        if ($_GET['mensaje'] === 'pago_registrado') {
            $mensaje = '✅ Pago registrado correctamente.';
            $mensaje_tipo = 'success';
        }
    }

    /*********************************************
     * PROCESAMIENTO DE FORMULARIOS
     *********************************************/

    // ========== PAGOS MENSAJEROS ==========
    
    // Registrar pago a mensajero
    if (isset($_POST['gofast_registrar_pago_mensajero']) && wp_verify_nonce($_POST['gofast_pago_mensajero_nonce'], 'gofast_registrar_pago_mensajero')) {
        $mensajero_id = (int) ($_POST['mensajero_id'] ?? 0);
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $tipo_pago = sanitize_text_field($_POST['tipo_pago'] ?? '');
        $comision_total = floatval($_POST['comision_total'] ?? 0);
        $transferencias_total = floatval($_POST['transferencias_total'] ?? 0);
        $descuentos_total = floatval($_POST['descuentos_total'] ?? 0);
        $total_a_pagar = floatval($_POST['total_a_pagar'] ?? 0);

        if ($mensajero_id <= 0 || empty($fecha) || !in_array($tipo_pago, ['efectivo', 'transferencia'])) {
            $mensaje = 'Datos inválidos para registrar el pago.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'pagos_mensajeros_gofast',
                [
                    'fecha' => $fecha,
                    'mensajero_id' => $mensajero_id,
                    'comision_total' => $comision_total,
                    'transferencias_total' => $transferencias_total,
                    'descuentos_total' => $descuentos_total,
                    'total_a_pagar' => $total_a_pagar,
                    'tipo_pago' => $tipo_pago,
                    'fecha_pago' => gofast_date_mysql(),
                    'creado_por' => $user_id
                ],
                ['%s', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%d']
            );

            if ($insertado) {
                // Solo crear transferencia aprobada cuando el pago es por transferencia
                if ($tipo_pago === 'transferencia') {
                    $wpdb->insert(
                        'transferencias_gofast',
                        [
                            'mensajero_id' => $mensajero_id,
                            'valor' => $total_a_pagar,
                            'estado' => 'aprobada',
                            'tipo' => 'pago',
                            'creado_por' => $user_id,
                            'observaciones' => 'Pago automático - Transferencia - Fecha: ' . $fecha,
                            'fecha_creacion' => gofast_date_mysql()
                        ],
                        ['%d', '%f', '%s', '%s', '%d', '%s', '%s']
                    );
                }
                
                // Redirigir para mostrar mensaje de éxito
                $current_url = remove_query_arg('mensaje', $_SERVER['REQUEST_URI']);
                $redirect_url = add_query_arg([
                    'mensaje' => 'pago_registrado',
                    'tab' => 'saldos_mensajeros'
                ], $current_url);
                
                // Si no hay subtab_saldos en la URL, agregarlo
                if (strpos($redirect_url, 'subtab_saldos') === false) {
                    $redirect_url = add_query_arg('subtab_saldos', 'saldos_pendientes', $redirect_url);
                }
                
                echo '<script>window.location.href = "' . esc_js($redirect_url) . '";</script>';
                return '';
            } else {
                $mensaje = 'Error al registrar el pago: ' . $wpdb->last_error;
                $mensaje_tipo = 'error';
            }
        }
    }

    // Editar pago mensajero
    if (isset($_POST['gofast_editar_pago_mensajero']) && wp_verify_nonce($_POST['gofast_editar_pago_nonce'], 'gofast_editar_pago_mensajero')) {
        $pago_id = (int) ($_POST['pago_id'] ?? 0);
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $tipo_pago = sanitize_text_field($_POST['tipo_pago'] ?? '');
        $total_a_pagar = floatval($_POST['total_a_pagar'] ?? 0);

        if ($pago_id <= 0 || empty($fecha) || !in_array($tipo_pago, ['efectivo', 'transferencia'])) {
            $mensaje = 'Datos inválidos para editar el pago.';
            $mensaje_tipo = 'error';
        } else {
            // Obtener datos del pago antes de actualizar
            $pago_anterior = $wpdb->get_row($wpdb->prepare(
                "SELECT mensajero_id, tipo_pago, total_a_pagar, fecha FROM pagos_mensajeros_gofast WHERE id = %d",
                $pago_id
            ));
            
            $actualizado = $wpdb->update(
                'pagos_mensajeros_gofast',
                [
                    'fecha' => $fecha,
                    'tipo_pago' => $tipo_pago,
                    'total_a_pagar' => $total_a_pagar
                ],
                ['id' => $pago_id],
                ['%s', '%s', '%f'],
                ['%d']
            );

            if ($actualizado !== false) {
                // Si era transferencia, buscar y actualizar la transferencia asociada
                if ($pago_anterior && $pago_anterior->tipo_pago === 'transferencia') {
                    // Buscar transferencia con el mismo valor y fecha aproximada
                    $transferencia_asociada = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM transferencias_gofast 
                         WHERE mensajero_id = %d 
                         AND valor = %f 
                         AND observaciones LIKE %s
                         LIMIT 1",
                        $pago_anterior->mensajero_id,
                        $pago_anterior->total_a_pagar,
                        '%Pago automático%' . $pago_anterior->fecha . '%'
                    ));
                    
                    if ($transferencia_asociada) {
                        // Actualizar valor de la transferencia
                        $wpdb->update(
                            'transferencias_gofast',
                            ['valor' => $total_a_pagar],
                            ['id' => $transferencia_asociada->id],
                            ['%f'],
                            ['%d']
                        );
                    }
                }
                
                $mensaje = '✅ Pago actualizado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'saldos_mensajeros';
                $subtab_saldos = 'historial_pagos';
            } else {
                $mensaje = 'Error al actualizar el pago: ' . $wpdb->last_error;
                $mensaje_tipo = 'error';
            }
        }
    }

    // Eliminar pago mensajero
    if (isset($_POST['gofast_eliminar_pago_mensajero']) && wp_verify_nonce($_POST['gofast_eliminar_pago_nonce'], 'gofast_eliminar_pago_mensajero')) {
        $pago_id = (int) ($_POST['pago_id'] ?? 0);

        if ($pago_id <= 0) {
            $mensaje = 'ID de pago inválido.';
            $mensaje_tipo = 'error';
        } else {
            $eliminado = $wpdb->delete('pagos_mensajeros_gofast', ['id' => $pago_id], ['%d']);

            if ($eliminado) {
                $mensaje = '✅ Pago eliminado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'saldos_mensajeros';
                $subtab_saldos = 'historial_pagos';
            } else {
                $mensaje = 'Error al eliminar el pago.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // ========== EGRESOS ==========
    
    // 1. CREAR EGRESO
    if (isset($_POST['gofast_crear_egreso']) && wp_verify_nonce($_POST['gofast_egreso_nonce'], 'gofast_crear_egreso')) {
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'egresos_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'creado_por' => $user_id,
                    'fecha_creacion' => gofast_date_mysql()
                ],
                ['%s', '%s', '%f', '%d', '%s']
            );

            if ($insertado) {
                $mensaje = 'Egreso creado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'egresos';
            } else {
                $mensaje = 'Error al crear el egreso.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 2. EDITAR EGRESO
    if (isset($_POST['gofast_editar_egreso']) && wp_verify_nonce($_POST['gofast_editar_egreso_nonce'], 'gofast_editar_egreso')) {
        $egreso_id = (int) $_POST['egreso_id'];
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'egresos_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'fecha_actualizacion' => gofast_date_mysql()
                ],
                ['id' => $egreso_id],
                ['%s', '%s', '%f', '%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Egreso actualizado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'egresos';
            } else {
                $mensaje = 'Error al actualizar el egreso.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR EGRESO
    if (isset($_POST['gofast_eliminar_egreso']) && wp_verify_nonce($_POST['gofast_eliminar_egreso_nonce'], 'gofast_eliminar_egreso')) {
        $egreso_id = (int) $_POST['egreso_id'];

        $eliminado = $wpdb->delete(
            'egresos_gofast',
            ['id' => $egreso_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Egreso eliminado correctamente.';
            $mensaje_tipo = 'success';
            $tab_activo = 'egresos';
        } else {
            $mensaje = 'Error al eliminar el egreso.';
            $mensaje_tipo = 'error';
        }
    }

    // ========== VALES EMPRESA ==========
    
    // 1. CREAR VALE EMPRESA
    if (isset($_POST['gofast_crear_vale_empresa']) && wp_verify_nonce($_POST['gofast_vale_empresa_nonce'], 'gofast_crear_vale_empresa')) {
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'vales_empresa_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'creado_por' => $user_id,
                    'fecha_creacion' => gofast_date_mysql()
                ],
                ['%s', '%s', '%f', '%d', '%s']
            );

            if ($insertado) {
                $mensaje = 'Vale de empresa creado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'vales_empresa';
            } else {
                $mensaje = 'Error al crear el vale de empresa.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 2. EDITAR VALE EMPRESA
    if (isset($_POST['gofast_editar_vale_empresa']) && wp_verify_nonce($_POST['gofast_editar_vale_empresa_nonce'], 'gofast_editar_vale_empresa')) {
        $vale_id = (int) $_POST['vale_id'];
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'vales_empresa_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'fecha_actualizacion' => gofast_date_mysql()
                ],
                ['id' => $vale_id],
                ['%s', '%s', '%f', '%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Vale de empresa actualizado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'vales_empresa';
            } else {
                $mensaje = 'Error al actualizar el vale de empresa.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR VALE EMPRESA
    if (isset($_POST['gofast_eliminar_vale_empresa']) && wp_verify_nonce($_POST['gofast_eliminar_vale_empresa_nonce'], 'gofast_eliminar_vale_empresa')) {
        $vale_id = (int) $_POST['vale_id'];

        $eliminado = $wpdb->delete(
            'vales_empresa_gofast',
            ['id' => $vale_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Vale de empresa eliminado correctamente.';
            $mensaje_tipo = 'success';
            $tab_activo = 'vales_empresa';
        } else {
            $mensaje = 'Error al eliminar el vale de empresa.';
            $mensaje_tipo = 'error';
        }
    }

    // ========== VALES PERSONAL ==========
    
    // 1. CREAR VALE PERSONAL
    if (isset($_POST['gofast_crear_vale_personal']) && wp_verify_nonce($_POST['gofast_vale_personal_nonce'], 'gofast_crear_vale_personal')) {
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $persona_id = (int) ($_POST['persona_id'] ?? 0);
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || $persona_id <= 0 || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'vales_personal_gofast',
                [
                    'fecha' => $fecha,
                    'persona_id' => $persona_id,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'creado_por' => $user_id,
                    'fecha_creacion' => gofast_date_mysql()
                ],
                ['%s', '%d', '%s', '%f', '%d', '%s']
            );

            if ($insertado) {
                $mensaje = 'Vale de personal creado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'vales_personal';
            } else {
                $mensaje = 'Error al crear el vale de personal.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 2. EDITAR VALE PERSONAL
    if (isset($_POST['gofast_editar_vale_personal']) && wp_verify_nonce($_POST['gofast_editar_vale_personal_nonce'], 'gofast_editar_vale_personal')) {
        $vale_id = (int) $_POST['vale_id'];
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $persona_id = (int) ($_POST['persona_id'] ?? 0);
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || $persona_id <= 0 || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'vales_personal_gofast',
                [
                    'fecha' => $fecha,
                    'persona_id' => $persona_id,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'fecha_actualizacion' => gofast_date_mysql()
                ],
                ['id' => $vale_id],
                ['%s', '%d', '%s', '%f', '%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Vale de personal actualizado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'vales_personal';
            } else {
                $mensaje = 'Error al actualizar el vale de personal.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR VALE PERSONAL
    if (isset($_POST['gofast_eliminar_vale_personal']) && wp_verify_nonce($_POST['gofast_eliminar_vale_personal_nonce'], 'gofast_eliminar_vale_personal')) {
        $vale_id = (int) $_POST['vale_id'];

        $eliminado = $wpdb->delete(
            'vales_personal_gofast',
            ['id' => $vale_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Vale de personal eliminado correctamente.';
            $mensaje_tipo = 'success';
            $tab_activo = 'vales_personal';
        } else {
            $mensaje = 'Error al eliminar el vale de personal.';
            $mensaje_tipo = 'error';
        }
    }

    // ========== TRANSFERENCIAS SALIDAS ==========
    
    // 1. CREAR TRANSFERENCIA SALIDA
    if (isset($_POST['gofast_crear_transferencia_salida']) && wp_verify_nonce($_POST['gofast_transferencia_salida_nonce'], 'gofast_crear_transferencia_salida')) {
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'transferencias_salidas_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'creado_por' => $user_id,
                    'fecha_creacion' => gofast_date_mysql()
                ],
                ['%s', '%s', '%f', '%d', '%s']
            );

            if ($insertado) {
                $mensaje = 'Transferencia salida creada correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'transferencias_salidas';
            } else {
                $mensaje = 'Error al crear la transferencia salida.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 2. EDITAR TRANSFERENCIA SALIDA
    if (isset($_POST['gofast_editar_transferencia_salida']) && wp_verify_nonce($_POST['gofast_editar_transferencia_salida_nonce'], 'gofast_editar_transferencia_salida')) {
        $transferencia_id = (int) $_POST['transferencia_id'];
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);

        if (empty($fecha) || empty($descripcion) || $valor <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'transferencias_salidas_gofast',
                [
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'valor' => $valor,
                    'fecha_actualizacion' => gofast_date_mysql()
                ],
                ['id' => $transferencia_id],
                ['%s', '%s', '%f', '%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Transferencia salida actualizada correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'transferencias_salidas';
            } else {
                $mensaje = 'Error al actualizar la transferencia salida.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR TRANSFERENCIA SALIDA
    if (isset($_POST['gofast_eliminar_transferencia_salida']) && wp_verify_nonce($_POST['gofast_eliminar_transferencia_salida_nonce'], 'gofast_eliminar_transferencia_salida')) {
        $transferencia_id = (int) $_POST['transferencia_id'];

        $eliminado = $wpdb->delete(
            'transferencias_salidas_gofast',
            ['id' => $transferencia_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Transferencia salida eliminada correctamente.';
            $mensaje_tipo = 'success';
            $tab_activo = 'transferencias_salidas';
        } else {
            $mensaje = 'Error al eliminar la transferencia salida.';
            $mensaje_tipo = 'error';
        }
    }

    // ========== DESCUENTOS MENSAJEROS ==========
    
    // 1. CREAR DESCUENTO
    if (isset($_POST['gofast_crear_descuento']) && wp_verify_nonce($_POST['gofast_descuento_nonce'], 'gofast_crear_descuento')) {
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $mensajero_id = (int) ($_POST['mensajero_id'] ?? 0);
        $valor = floatval($_POST['valor'] ?? 0);
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');

        if (empty($fecha) || $mensajero_id <= 0 || $valor == 0) {
            $mensaje = 'Fecha, mensajero y valor son obligatorios. El valor debe ser diferente de cero (puede ser positivo o negativo).';
            $mensaje_tipo = 'error';
        } else {
            $insertado = $wpdb->insert(
                'descuentos_mensajeros_gofast',
                [
                    'fecha' => $fecha,
                    'mensajero_id' => $mensajero_id,
                    'valor' => $valor,
                    'descripcion' => $descripcion,
                    'creado_por' => $user_id,
                    'fecha_creacion' => gofast_date_mysql()
                ],
                ['%s', '%d', '%f', '%s', '%d', '%s']
            );

            if ($insertado) {
                $mensaje = 'Descuento aplicado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'descuentos';
            } else {
                $mensaje = 'Error al aplicar el descuento: ' . $wpdb->last_error;
                $mensaje_tipo = 'error';
            }
        }
    }

    // 2. EDITAR DESCUENTO
    if (isset($_POST['gofast_editar_descuento']) && wp_verify_nonce($_POST['gofast_editar_descuento_nonce'], 'gofast_editar_descuento')) {
        $descuento_id = (int) $_POST['descuento_id'];
        $fecha = sanitize_text_field($_POST['fecha'] ?? '');
        $mensajero_id = (int) ($_POST['mensajero_id'] ?? 0);
        $valor = floatval($_POST['valor'] ?? 0);
        $descripcion = sanitize_text_field($_POST['descripcion'] ?? '');

        if (empty($fecha) || $mensajero_id <= 0 || $valor == 0) {
            $mensaje = 'Todos los campos son obligatorios. El valor debe ser diferente de cero (puede ser positivo o negativo).';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'descuentos_mensajeros_gofast',
                [
                    'fecha' => $fecha,
                    'mensajero_id' => $mensajero_id,
                    'valor' => $valor,
                    'descripcion' => $descripcion
                ],
                ['id' => $descuento_id],
                ['%s', '%d', '%f', '%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Descuento actualizado correctamente.';
                $mensaje_tipo = 'success';
                $tab_activo = 'descuentos';
            } else {
                $mensaje = 'Error al actualizar el descuento.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR DESCUENTO
    if (isset($_POST['gofast_eliminar_descuento']) && wp_verify_nonce($_POST['gofast_eliminar_descuento_nonce'], 'gofast_eliminar_descuento')) {
        $descuento_id = (int) $_POST['descuento_id'];

        $eliminado = $wpdb->delete(
            'descuentos_mensajeros_gofast',
            ['id' => $descuento_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Descuento eliminado correctamente.';
            $mensaje_tipo = 'success';
            $tab_activo = 'descuentos';
        } else {
            $mensaje = 'Error al eliminar el descuento.';
            $mensaje_tipo = 'error';
        }
    }

    /*********************************************
     * FILTROS GENERALES
     *********************************************/
    $fecha_desde = isset($_GET['fecha_desde']) ? sanitize_text_field($_GET['fecha_desde']) : '';
    $fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize_text_field($_GET['fecha_hasta']) : '';

    // Valores por defecto: día actual
    $fecha_actual = gofast_current_time('Y-m-d');
    
    if (empty($fecha_desde) && empty($fecha_hasta)) {
        $fecha_desde = $fecha_actual;
        $fecha_hasta = $fecha_actual;
    }
    
    // Determinar si es un rango específico (fechas diferentes) o acumulado hasta fecha_hasta
    // Si fecha_desde == fecha_hasta -> mostrar acumulado histórico hasta esa fecha
    // Si fecha_desde != fecha_hasta -> mostrar solo ese rango
    $es_rango_especifico = ($fecha_desde !== $fecha_hasta);

    /*********************************************
     * CÁLCULOS DEL BLOQUE DE RESULTADOS GENERALES
     *********************************************/
    
    // Si es un rango específico (fechas diferentes) -> calcular solo ese rango
    // Si NO es rango específico (misma fecha) -> calcular acumulado histórico HASTA fecha_hasta
    
    if ($es_rango_especifico) {
        // TOTALES DEL RANGO DE FECHAS SELECCIONADO
        
        // Construir WHERE para filtros de fecha
        $params_fecha = [];
        $where_conditions = [];

        // Total Ingresos (servicios + compras, excluyendo cancelados)
        $where_ingresos_servicios = ["tracking_estado != 'cancelado'"];
        $params_ingresos_servicios = [];
        
        if (!empty($fecha_desde)) {
            $where_ingresos_servicios[] = "fecha >= %s";
            $params_ingresos_servicios[] = $fecha_desde . ' 00:00:00';
        }
        if (!empty($fecha_hasta)) {
            $where_ingresos_servicios[] = "fecha <= %s";
            $params_ingresos_servicios[] = $fecha_hasta . ' 23:59:59';
        }

        $total_ingresos_servicios = (float) ($wpdb->get_var(
            !empty($params_ingresos_servicios)
                ? $wpdb->prepare("SELECT COALESCE(SUM(total), 0) FROM servicios_gofast WHERE " . implode(' AND ', $where_ingresos_servicios), $params_ingresos_servicios)
                : "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast WHERE tracking_estado != 'cancelado'"
        ) ?? 0);

        $where_ingresos_compras = ["estado != 'cancelada'"];
        $params_ingresos_compras = [];
        
        if (!empty($fecha_desde)) {
            $where_ingresos_compras[] = "fecha_creacion >= %s";
            $params_ingresos_compras[] = $fecha_desde . ' 00:00:00';
        }
        if (!empty($fecha_hasta)) {
            $where_ingresos_compras[] = "fecha_creacion <= %s";
            $params_ingresos_compras[] = $fecha_hasta . ' 23:59:59';
        }

        $total_ingresos_compras = (float) ($wpdb->get_var(
            !empty($params_ingresos_compras)
                ? $wpdb->prepare("SELECT COALESCE(SUM(valor), 0) FROM compras_gofast WHERE " . implode(' AND ', $where_ingresos_compras), $params_ingresos_compras)
                : "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast WHERE estado != 'cancelada'"
        ) ?? 0);

        $total_ingresos = $total_ingresos_servicios + $total_ingresos_compras;

        // Total Egresos
        $where_egresos = [];
        $params_egresos = [];
        
        if (!empty($fecha_desde)) {
            $where_egresos[] = "fecha >= %s";
            $params_egresos[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_egresos[] = "fecha <= %s";
            $params_egresos[] = $fecha_hasta;
        }

        $total_egresos = (float) ($wpdb->get_var(
            !empty($params_egresos)
                ? $wpdb->prepare("SELECT COALESCE(SUM(valor), 0) FROM egresos_gofast WHERE " . implode(' AND ', $where_egresos), $params_egresos)
                : "SELECT COALESCE(SUM(valor), 0) FROM egresos_gofast"
        ) ?? 0);

        // Total Vales Empresa
        $where_vales_empresa = [];
        $params_vales_empresa = [];
        
        if (!empty($fecha_desde)) {
            $where_vales_empresa[] = "fecha >= %s";
            $params_vales_empresa[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_vales_empresa[] = "fecha <= %s";
            $params_vales_empresa[] = $fecha_hasta;
        }

        $total_vales_empresa = (float) ($wpdb->get_var(
            !empty($params_vales_empresa)
                ? $wpdb->prepare("SELECT COALESCE(SUM(valor), 0) FROM vales_empresa_gofast WHERE " . implode(' AND ', $where_vales_empresa), $params_vales_empresa)
                : "SELECT COALESCE(SUM(valor), 0) FROM vales_empresa_gofast"
        ) ?? 0);

        // Total Vales Personal
        $where_vales_personal = [];
        $params_vales_personal = [];
        
        if (!empty($fecha_desde)) {
            $where_vales_personal[] = "fecha >= %s";
            $params_vales_personal[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_vales_personal[] = "fecha <= %s";
            $params_vales_personal[] = $fecha_hasta;
        }

        $total_vales_personal = (float) ($wpdb->get_var(
            !empty($params_vales_personal)
                ? $wpdb->prepare("SELECT COALESCE(SUM(valor), 0) FROM vales_personal_gofast WHERE " . implode(' AND ', $where_vales_personal), $params_vales_personal)
                : "SELECT COALESCE(SUM(valor), 0) FROM vales_personal_gofast"
        ) ?? 0);

        // Total Transferencias Ingresos (aprobadas)
        $where_transf_entradas = ["estado = 'aprobada'"];
        $params_transf_entradas = [];
        
        if (!empty($fecha_desde)) {
            $where_transf_entradas[] = "fecha_creacion >= %s";
            $params_transf_entradas[] = $fecha_desde . ' 00:00:00';
        }
        if (!empty($fecha_hasta)) {
            $where_transf_entradas[] = "fecha_creacion <= %s";
            $params_transf_entradas[] = $fecha_hasta . ' 23:59:59';
        }

        $total_transferencias_ingresos = (float) ($wpdb->get_var(
            !empty($params_transf_entradas)
                ? $wpdb->prepare("SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast WHERE " . implode(' AND ', $where_transf_entradas), $params_transf_entradas)
                : "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast WHERE estado = 'aprobada'"
        ) ?? 0);

        // Total Transferencias Salidas
        $where_transf_salidas = [];
        $params_transf_salidas = [];
        
        if (!empty($fecha_desde)) {
            $where_transf_salidas[] = "fecha >= %s";
            $params_transf_salidas[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_transf_salidas[] = "fecha <= %s";
            $params_transf_salidas[] = $fecha_hasta;
        }

        $total_transferencias_salidas = (float) ($wpdb->get_var(
            !empty($params_transf_salidas)
                ? $wpdb->prepare("SELECT COALESCE(SUM(valor), 0) FROM transferencias_salidas_gofast WHERE " . implode(' AND ', $where_transf_salidas), $params_transf_salidas)
                : "SELECT COALESCE(SUM(valor), 0) FROM transferencias_salidas_gofast"
        ) ?? 0);

        // Total Descuentos
        $where_descuentos = [];
        $params_descuentos = [];
        
        if (!empty($fecha_desde)) {
            $where_descuentos[] = "fecha >= %s";
            $params_descuentos[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_descuentos[] = "fecha <= %s";
            $params_descuentos[] = $fecha_hasta;
        }

        $total_descuentos = (float) ($wpdb->get_var(
            !empty($params_descuentos)
                ? $wpdb->prepare("SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast WHERE " . implode(' AND ', $where_descuentos), $params_descuentos)
                : "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast"
        ) ?? 0);
    } else {
        // TOTALES ACUMULADOS HASTA fecha_hasta (cuando fecha_desde == fecha_hasta)
        
        // Total Ingresos hasta fecha_hasta
        $total_ingresos_servicios = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
                 WHERE tracking_estado != 'cancelado' AND DATE(fecha) <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        $total_ingresos_compras = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast 
                 WHERE estado != 'cancelada' AND DATE(fecha_creacion) <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        $total_ingresos = $total_ingresos_servicios + $total_ingresos_compras;
        
        // Total Egresos hasta fecha_hasta
        $total_egresos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM egresos_gofast WHERE fecha <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        // Total Vales Empresa hasta fecha_hasta
        $total_vales_empresa = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM vales_empresa_gofast WHERE fecha <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        // Total Vales Personal hasta fecha_hasta
        $total_vales_personal = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM vales_personal_gofast WHERE fecha <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        // Total Transferencias Ingresos hasta fecha_hasta
        $total_transferencias_ingresos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast 
                 WHERE estado = 'aprobada' AND DATE(fecha_creacion) <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        // Total Transferencias Salidas hasta fecha_hasta
        $total_transferencias_salidas = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_salidas_gofast WHERE fecha <= %s",
                $fecha_hasta
            )
        ) ?? 0);
        
        // Total Descuentos hasta fecha_hasta
        $total_descuentos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast WHERE fecha <= %s",
                $fecha_hasta
            )
        ) ?? 0);
    }

    // Saldo Transferencias
    $saldo_transferencias = $total_transferencias_ingresos - $total_transferencias_salidas;

    // Total Saldos Pendientes por Pagar - se calculará después de obtener saldos de todos los mensajeros
    // Este cálculo temporal será reemplazado más abajo con el acumulado histórico real
    $total_saldos_pendientes = 0;

    // Calcular comisiones (20% de los ingresos totales)
    $total_comisiones = $total_ingresos * 0.20;
    
    // Cálculos finales
    $subtotal = $total_comisiones - $total_egresos - $total_vales_empresa - $total_descuentos;
    $efectivo = $subtotal - $saldo_transferencias - $total_saldos_pendientes;
    $utilidad_total = $subtotal;
    $utilidad_individual = $utilidad_total > 0 ? ($utilidad_total / 2) : 0;

    /*********************************************
     * DATOS PARA TAB DE INGRESOS
     *********************************************/
    $ingresos_diarios = [];
    
    if (!empty($fecha_desde) && !empty($fecha_hasta)) {
        // Obtener ingresos agrupados por día
        // Usar rangos completos de fecha/hora para respetar zona horaria GMT-5
        $sql_ingresos = $wpdb->prepare(
            "SELECT 
                DATE(fecha) as fecha_dia,
                COUNT(*) as num_pedidos,
                SUM(total) as total_servicios
            FROM servicios_gofast
            WHERE fecha >= %s 
            AND fecha <= %s 
            AND tracking_estado != 'cancelado'
            GROUP BY DATE(fecha)
            ORDER BY fecha_dia DESC",
            $fecha_desde . ' 00:00:00',
            $fecha_hasta . ' 23:59:59'
        );

        $ingresos_servicios = $wpdb->get_results($sql_ingresos);

        $sql_compras = $wpdb->prepare(
            "SELECT 
                DATE(fecha_creacion) as fecha_dia,
                COUNT(*) as num_compras,
                SUM(valor) as total_compras
            FROM compras_gofast
            WHERE fecha_creacion >= %s 
            AND fecha_creacion <= %s 
            AND estado != 'cancelada'
            GROUP BY DATE(fecha_creacion)
            ORDER BY fecha_dia DESC",
            $fecha_desde . ' 00:00:00',
            $fecha_hasta . ' 23:59:59'
        );

        $ingresos_compras = $wpdb->get_results($sql_compras);

        // Combinar datos por fecha
        $ingresos_por_fecha = [];
        
        foreach ($ingresos_servicios as $row) {
            $fecha = $row->fecha_dia;
            $ingresos_por_fecha[$fecha] = [
                'fecha' => $fecha,
                'num_pedidos' => (int) $row->num_pedidos,
                'num_compras' => 0,
                'total_servicios' => (float) $row->total_servicios,
                'total_compras' => 0,
                'total_ingresos' => (float) $row->total_servicios,
                'total_comisiones' => (float) $row->total_servicios * 0.20
            ];
        }

        foreach ($ingresos_compras as $row) {
            $fecha = $row->fecha_dia;
            if (isset($ingresos_por_fecha[$fecha])) {
                $ingresos_por_fecha[$fecha]['num_compras'] = (int) $row->num_compras;
                $ingresos_por_fecha[$fecha]['total_compras'] = (float) $row->total_compras;
                $ingresos_por_fecha[$fecha]['total_ingresos'] += (float) $row->total_compras;
                $ingresos_por_fecha[$fecha]['total_comisiones'] = $ingresos_por_fecha[$fecha]['total_ingresos'] * 0.20;
            } else {
                $ingresos_por_fecha[$fecha] = [
                    'fecha' => $fecha,
                    'num_pedidos' => 0,
                    'num_compras' => (int) $row->num_compras,
                    'total_servicios' => 0,
                    'total_compras' => (float) $row->total_compras,
                    'total_ingresos' => (float) $row->total_compras,
                    'total_comisiones' => (float) $row->total_compras * 0.20
                ];
            }
        }

        $ingresos_diarios = array_values($ingresos_por_fecha);
    }

    /*********************************************
     * DATOS PARA TAB DE EGRESOS
     *********************************************/
    $where_egresos = [];
    $params_egresos = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_egresos[] = "e.fecha >= %s";
        $params_egresos[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_egresos[] = "e.fecha <= %s";
        $params_egresos[] = $fecha_hasta;
    }

    // Filtro por descripción
    $filtro_descripcion = isset($_GET['filtro_descripcion']) ? sanitize_text_field($_GET['filtro_descripcion']) : '';
    if (!empty($filtro_descripcion)) {
        $where_egresos[] = "e.descripcion LIKE %s";
        $params_egresos[] = '%' . $wpdb->esc_like($filtro_descripcion) . '%';
    }

    $sql_egresos = "SELECT e.*, u.nombre as creador_nombre
                    FROM egresos_gofast e
                    LEFT JOIN usuarios_gofast u ON e.creado_por = u.id";
    
    if (!empty($where_egresos)) {
        $sql_egresos .= " WHERE " . implode(' AND ', $where_egresos);
    }
    
    $sql_egresos .= " ORDER BY e.fecha DESC, e.id DESC";

    if (!empty($params_egresos)) {
        $egresos = $wpdb->get_results($wpdb->prepare($sql_egresos, $params_egresos));
    } else {
        $egresos = $wpdb->get_results($sql_egresos);
    }

    /*********************************************
     * DATOS PARA TAB DE VALES EMPRESA
     *********************************************/
    $where_vales_empresa_list = [];
    $params_vales_empresa_list = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_vales_empresa_list[] = "v.fecha >= %s";
        $params_vales_empresa_list[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_vales_empresa_list[] = "v.fecha <= %s";
        $params_vales_empresa_list[] = $fecha_hasta;
    }

    // Filtro por descripción
    $filtro_descripcion_vales_empresa = isset($_GET['filtro_descripcion_vales_empresa']) ? sanitize_text_field($_GET['filtro_descripcion_vales_empresa']) : '';
    if (!empty($filtro_descripcion_vales_empresa)) {
        $where_vales_empresa_list[] = "v.descripcion LIKE %s";
        $params_vales_empresa_list[] = '%' . $wpdb->esc_like($filtro_descripcion_vales_empresa) . '%';
    }

    $sql_vales_empresa = "SELECT v.*, u.nombre as creador_nombre
                          FROM vales_empresa_gofast v
                          LEFT JOIN usuarios_gofast u ON v.creado_por = u.id";
    
    if (!empty($where_vales_empresa_list)) {
        $sql_vales_empresa .= " WHERE " . implode(' AND ', $where_vales_empresa_list);
    }
    
    $sql_vales_empresa .= " ORDER BY v.fecha DESC, v.id DESC";

    if (!empty($params_vales_empresa_list)) {
        $vales_empresa = $wpdb->get_results($wpdb->prepare($sql_vales_empresa, $params_vales_empresa_list));
    } else {
        $vales_empresa = $wpdb->get_results($sql_vales_empresa);
    }

    /*********************************************
     * DATOS PARA TAB DE VALES PERSONAL
     *********************************************/
    // Obtener las 4 personas activas (usuarios admin activos, limitado a 4)
    $personas_activas = $wpdb->get_results(
        "SELECT id, nombre 
         FROM usuarios_gofast
         WHERE rol = 'admin' AND activo = 1
         ORDER BY nombre ASC
         LIMIT 4
        "
    );

    $where_vales_personal_list = [];
    $params_vales_personal_list = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_vales_personal_list[] = "v.fecha >= %s";
        $params_vales_personal_list[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_vales_personal_list[] = "v.fecha <= %s";
        $params_vales_personal_list[] = $fecha_hasta;
    }

    // Filtro por descripción
    $filtro_descripcion_vales_personal = isset($_GET['filtro_descripcion_vales_personal']) ? sanitize_text_field($_GET['filtro_descripcion_vales_personal']) : '';
    if (!empty($filtro_descripcion_vales_personal)) {
        $where_vales_personal_list[] = "v.descripcion LIKE %s";
        $params_vales_personal_list[] = '%' . $wpdb->esc_like($filtro_descripcion_vales_personal) . '%';
    }

    // Filtro por persona
    $filtro_persona = isset($_GET['filtro_persona']) ? (int) $_GET['filtro_persona'] : 0;
    if ($filtro_persona > 0) {
        $where_vales_personal_list[] = "v.persona_id = %d";
        $params_vales_personal_list[] = $filtro_persona;
    }

    $sql_vales_personal = "SELECT v.*, 
                                  p.nombre as persona_nombre,
                                  u.nombre as creador_nombre
                           FROM vales_personal_gofast v
                           LEFT JOIN usuarios_gofast p ON v.persona_id = p.id
                           LEFT JOIN usuarios_gofast u ON v.creado_por = u.id";
    
    if (!empty($where_vales_personal_list)) {
        $sql_vales_personal .= " WHERE " . implode(' AND ', $where_vales_personal_list);
    }
    
    $sql_vales_personal .= " ORDER BY v.fecha DESC, v.id DESC";

    if (!empty($params_vales_personal_list)) {
        $vales_personal = $wpdb->get_results($wpdb->prepare($sql_vales_personal, $params_vales_personal_list));
    } else {
        $vales_personal = $wpdb->get_results($sql_vales_personal);
    }

    /*********************************************
     * DATOS PARA TAB DE TRANSFERENCIAS SALIDAS
     *********************************************/
    $where_transf_salidas_list = [];
    $params_transf_salidas_list = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_transf_salidas_list[] = "t.fecha >= %s";
        $params_transf_salidas_list[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_transf_salidas_list[] = "t.fecha <= %s";
        $params_transf_salidas_list[] = $fecha_hasta;
    }

    // Filtro por descripción
    $filtro_descripcion_transf_salidas = isset($_GET['filtro_descripcion_transf_salidas']) ? sanitize_text_field($_GET['filtro_descripcion_transf_salidas']) : '';
    if (!empty($filtro_descripcion_transf_salidas)) {
        $where_transf_salidas_list[] = "t.descripcion LIKE %s";
        $params_transf_salidas_list[] = '%' . $wpdb->esc_like($filtro_descripcion_transf_salidas) . '%';
    }

    $sql_transf_salidas = "SELECT t.*, u.nombre as creador_nombre
                           FROM transferencias_salidas_gofast t
                           LEFT JOIN usuarios_gofast u ON t.creado_por = u.id";
    
    if (!empty($where_transf_salidas_list)) {
        $sql_transf_salidas .= " WHERE " . implode(' AND ', $where_transf_salidas_list);
    }
    
    $sql_transf_salidas .= " ORDER BY t.fecha DESC, t.id DESC";

    if (!empty($params_transf_salidas_list)) {
        $transferencias_salidas = $wpdb->get_results($wpdb->prepare($sql_transf_salidas, $params_transf_salidas_list));
    } else {
        $transferencias_salidas = $wpdb->get_results($sql_transf_salidas);
    }

    /*********************************************
     * DATOS PARA TAB DE TRANSFERENCIAS ENTRADAS
     *********************************************/
    $where_transf_entradas = [];
    $params_transf_entradas = [];

    // Solo transferencias aprobadas
    $where_transf_entradas[] = "t.estado = 'aprobada'";

    // Filtro por fecha - usar rangos completos de fecha/hora para respetar zona horaria GMT-5
    if (!empty($fecha_desde)) {
        $where_transf_entradas[] = "t.fecha_creacion >= %s";
        $params_transf_entradas[] = $fecha_desde . ' 00:00:00';
    }
    if (!empty($fecha_hasta)) {
        $where_transf_entradas[] = "t.fecha_creacion <= %s";
        $params_transf_entradas[] = $fecha_hasta . ' 23:59:59';
    }

    // Filtro por mensajero
    $filtro_mensajero = isset($_GET['filtro_mensajero']) ? (int) $_GET['filtro_mensajero'] : 0;
    if ($filtro_mensajero > 0) {
        $where_transf_entradas[] = "t.mensajero_id = %d";
        $params_transf_entradas[] = $filtro_mensajero;
    }

    // Filtro por origen (si existe el campo en la tabla)
    // Por ahora no hay campo origen, pero dejamos preparado
    $filtro_origen = isset($_GET['filtro_origen']) ? sanitize_text_field($_GET['filtro_origen']) : '';
    // TODO: Implementar cuando se defina qué es "origen"

    $sql_transf_entradas = "SELECT t.*, 
                                   m.nombre as mensajero_nombre, 
                                   m.telefono as mensajero_telefono,
                                   u.nombre as creador_nombre
                            FROM transferencias_gofast t
                            LEFT JOIN usuarios_gofast m ON t.mensajero_id = m.id
                            LEFT JOIN usuarios_gofast u ON t.creado_por = u.id";
    
    if (!empty($where_transf_entradas)) {
        $sql_transf_entradas .= " WHERE " . implode(' AND ', $where_transf_entradas);
    }
    
    $sql_transf_entradas .= " ORDER BY t.fecha_creacion DESC";

    if (!empty($params_transf_entradas)) {
        $transferencias_entradas = $wpdb->get_results($wpdb->prepare($sql_transf_entradas, $params_transf_entradas));
    } else {
        $transferencias_entradas = $wpdb->get_results($sql_transf_entradas);
    }

    // Obtener lista de mensajeros para el filtro
    // NOTA: No filtramos por activo=1 para incluir mensajeros deshabilitados en filtros de transferencias
    $mensajeros = [];
    $mensajeros = $wpdb->get_results(
        "SELECT id, nombre 
         FROM usuarios_gofast
         WHERE rol = 'mensajero'
         ORDER BY nombre ASC
        "
    );

    /*********************************************
     * DATOS PARA TAB DE DESCUENTOS
     *********************************************/
    $where_descuentos_list = [];
    $params_descuentos_list = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_descuentos_list[] = "d.fecha >= %s";
        $params_descuentos_list[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_descuentos_list[] = "d.fecha <= %s";
        $params_descuentos_list[] = $fecha_hasta;
    }

    // Filtro por mensajero
    $filtro_mensajero_descuentos = isset($_GET['filtro_mensajero_descuentos']) ? (int) $_GET['filtro_mensajero_descuentos'] : 0;
    if ($filtro_mensajero_descuentos > 0) {
        $where_descuentos_list[] = "d.mensajero_id = %d";
        $params_descuentos_list[] = $filtro_mensajero_descuentos;
    }

    // Filtro por descripción
    $filtro_descripcion_descuentos = isset($_GET['filtro_descripcion_descuentos']) ? sanitize_text_field($_GET['filtro_descripcion_descuentos']) : '';
    if (!empty($filtro_descripcion_descuentos)) {
        $where_descuentos_list[] = "d.descripcion LIKE %s";
        $params_descuentos_list[] = '%' . $wpdb->esc_like($filtro_descripcion_descuentos) . '%';
    }

    $sql_descuentos = "SELECT d.*, 
                              m.nombre as mensajero_nombre,
                              m.telefono as mensajero_telefono,
                              u.nombre as creador_nombre
                       FROM descuentos_mensajeros_gofast d
                       LEFT JOIN usuarios_gofast m ON d.mensajero_id = m.id
                       LEFT JOIN usuarios_gofast u ON d.creado_por = u.id";
    
    if (!empty($where_descuentos_list)) {
        $sql_descuentos .= " WHERE " . implode(' AND ', $where_descuentos_list);
    }
    
    $sql_descuentos .= " ORDER BY d.fecha DESC, d.id DESC";

    if (!empty($params_descuentos_list)) {
        $descuentos = $wpdb->get_results($wpdb->prepare($sql_descuentos, $params_descuentos_list));
    } else {
        $descuentos = $wpdb->get_results($sql_descuentos);
    }

    /*********************************************
     * DATOS PARA TAB DE SALDOS MENSAJEROS
     *********************************************/
    $filtro_mensajero_saldos = isset($_GET['filtro_mensajero_saldos']) ? (int) $_GET['filtro_mensajero_saldos'] : 0;
    $filtro_estado_saldos = isset($_GET['filtro_estado_saldos']) ? sanitize_text_field($_GET['filtro_estado_saldos']) : '';
    
    // Filtros para historial de pagos
    $filtro_mensajero_historial = isset($_GET['filtro_mensajero_historial']) ? (int) $_GET['filtro_mensajero_historial'] : 0;
    $filtro_fecha_desde_historial = isset($_GET['filtro_fecha_desde_historial']) ? sanitize_text_field($_GET['filtro_fecha_desde_historial']) : '';
    $filtro_fecha_hasta_historial = isset($_GET['filtro_fecha_hasta_historial']) ? sanitize_text_field($_GET['filtro_fecha_hasta_historial']) : '';
    
    // Obtener lista de mensajeros para filtros de saldos (incluye deshabilitados)
    // NOTA: Si $mensajeros ya está definida (desde transferencias), no la sobrescribimos
    if (!isset($mensajeros) || empty($mensajeros)) {
        $mensajeros = $wpdb->get_results(
            "SELECT id, nombre 
             FROM usuarios_gofast
             WHERE rol = 'mensajero'
             ORDER BY nombre ASC
            "
        );
    }

    // Construir WHERE para saldos mensajeros
    $where_saldos = [];
    $params_saldos = [];

    // Filtro por fecha
    if (!empty($fecha_desde)) {
        $where_saldos[] = "DATE(s.fecha) >= %s";
        $params_saldos[] = $fecha_desde;
    }
    if (!empty($fecha_hasta)) {
        $where_saldos[] = "DATE(s.fecha) <= %s";
        $params_saldos[] = $fecha_hasta;
    }

    // Filtro por mensajero
    if ($filtro_mensajero_saldos > 0) {
        $where_saldos[] = "s.mensajero_id = %d";
        $params_saldos[] = $filtro_mensajero_saldos;
    }

    // Filtro por estado
    if (!empty($filtro_estado_saldos) && in_array($filtro_estado_saldos, ['pendiente', 'efectivo', 'transferencia'])) {
        $where_saldos[] = "p.tipo_pago = %s";
        $params_saldos[] = $filtro_estado_saldos;
    }

    // Calcular estadísticas por mensajero (similar a reportes)
    // Por cada mensajero, calcular: destinos, compras, ingresos, comisión, transferencias, descuentos, total a pagar
    
    $where_servicios_mensajero = "tracking_estado != 'cancelado'";
    $params_servicios_mensajero = [];
    
    if (!empty($fecha_desde)) {
        $where_servicios_mensajero .= " AND fecha >= %s";
        $params_servicios_mensajero[] = $fecha_desde . ' 00:00:00';
    }
    if (!empty($fecha_hasta)) {
        $where_servicios_mensajero .= " AND fecha <= %s";
        $params_servicios_mensajero[] = $fecha_hasta . ' 23:59:59';
    }
    if ($filtro_mensajero_saldos > 0) {
        $where_servicios_mensajero .= " AND mensajero_id = %d";
        $params_servicios_mensajero[] = $filtro_mensajero_saldos;
    }

    $where_compras_mensajero = "estado != 'cancelada'";
    $params_compras_mensajero = [];
    
    if (!empty($fecha_desde)) {
        $where_compras_mensajero .= " AND fecha_creacion >= %s";
        $params_compras_mensajero[] = $fecha_desde . ' 00:00:00';
    }
    if (!empty($fecha_hasta)) {
        $where_compras_mensajero .= " AND fecha_creacion <= %s";
        $params_compras_mensajero[] = $fecha_hasta . ' 23:59:59';
    }
    if ($filtro_mensajero_saldos > 0) {
        $where_compras_mensajero .= " AND mensajero_id = %d";
        $params_compras_mensajero[] = $filtro_mensajero_saldos;
    }

    // Obtener datos agrupados por mensajero
    // NOTA: No filtramos por activo=1 para incluir mensajeros deshabilitados en cálculos y saldos
    $where_mensajeros = "rol = 'mensajero'";
    $params_mensajeros = [];
    
    if ($filtro_mensajero_saldos > 0) {
        $where_mensajeros .= " AND id = %d";
        $params_mensajeros[] = $filtro_mensajero_saldos;
    }

    // Obtener lista de mensajeros para cálculos (puede estar filtrada)
    // Nota: Incluimos telefono porque se muestra en la tabla de saldos
    if (!empty($params_mensajeros)) {
        $mensajeros_saldos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, nombre, telefono FROM usuarios_gofast WHERE $where_mensajeros ORDER BY nombre ASC",
                $params_mensajeros
            )
        );
    } else {
        $mensajeros_saldos = $wpdb->get_results(
            "SELECT id, nombre, telefono FROM usuarios_gofast WHERE $where_mensajeros ORDER BY nombre ASC"
        );
    }

    // Calcular estadísticas para cada mensajero
    $saldos_mensajeros = [];
    foreach ($mensajeros_saldos as $mensajero) {
        $mensajero_id = (int) $mensajero->id;
        
        // Obtener TODOS los pagos en el rango de fechas para restarlos
        $fecha_desde_para_pagos = !empty($fecha_desde) ? $fecha_desde : '1900-01-01';
        $fecha_hasta_para_pagos = !empty($fecha_hasta) ? $fecha_hasta : '9999-12-31';
        
        $pagos_en_rango_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT fecha, fecha_pago, total_a_pagar 
                 FROM pagos_mensajeros_gofast 
                 WHERE mensajero_id = %d 
                 AND tipo_pago IN ('efectivo', 'transferencia')
                 AND fecha >= %s 
                 AND fecha <= %s
                 ORDER BY fecha ASC, fecha_pago ASC",
                $mensajero_id,
                $fecha_desde_para_pagos,
                $fecha_hasta_para_pagos
            )
        );
        
        // Total destinos - calcular directamente en el rango de fechas
        $where_destinos = "mensajero_id = %d AND tracking_estado != 'cancelado'";
        $params_destinos = [$mensajero_id];
        
        if (!empty($fecha_desde)) {
            $where_destinos .= " AND DATE(fecha) >= %s";
            $params_destinos[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_destinos .= " AND DATE(fecha) <= %s";
            $params_destinos[] = $fecha_hasta;
        }
        
        $total_destinos = (int) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(
                    CASE 
                        WHEN destinos IS NULL OR destinos = '' THEN 0
                        WHEN JSON_VALID(destinos) = 0 THEN 0
                        ELSE COALESCE(JSON_LENGTH(JSON_EXTRACT(destinos, '$.destinos')), 0)
                    END
                ), 0) FROM servicios_gofast WHERE $where_destinos",
                $params_destinos
            ) ?? 0)
        );

        // Total compras - calcular directamente en el rango de fechas
        $where_compras = "mensajero_id = %d AND estado != 'cancelada'";
        $params_compras = [$mensajero_id];
        
        if (!empty($fecha_desde)) {
            $where_compras .= " AND DATE(fecha_creacion) >= %s";
            $params_compras[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_compras .= " AND DATE(fecha_creacion) <= %s";
            $params_compras[] = $fecha_hasta;
        }
        
        $total_compras = (int) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM compras_gofast WHERE $where_compras",
                $params_compras
            ) ?? 0)
        );

        // Ingresos servicios
        $ingresos_servicios = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(total) FROM servicios_gofast WHERE $where_destinos",
                $params_destinos
            ) ?? 0)
        );

        // Ingresos compras
        $ingresos_compras = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(valor) FROM compras_gofast WHERE $where_compras",
                $params_compras
            ) ?? 0)
        );

        $ingresos_totales = $ingresos_servicios + $ingresos_compras;

        // Transferencias aprobadas - calcular directamente en el rango de fechas
        $where_transf = "mensajero_id = %d AND estado = 'aprobada'";
        $params_transf = [$mensajero_id];
        
        if (!empty($fecha_desde)) {
            $where_transf .= " AND DATE(fecha_creacion) >= %s";
            $params_transf[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_transf .= " AND DATE(fecha_creacion) <= %s";
            $params_transf[] = $fecha_hasta;
        }
        
        $transferencias_aprobadas = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast WHERE $where_transf",
                $params_transf
            ) ?? 0)
        );

        // Descuentos - calcular directamente en el rango de fechas
        $where_descuentos_mensajero = "mensajero_id = %d";
        $params_descuentos_mensajero = [$mensajero_id];
        if (!empty($fecha_desde)) {
            $where_descuentos_mensajero .= " AND fecha >= %s";
            $params_descuentos_mensajero[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where_descuentos_mensajero .= " AND fecha <= %s";
            $params_descuentos_mensajero[] = $fecha_hasta;
        }
        
        $total_descuentos_mensajero = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast WHERE $where_descuentos_mensajero",
                $params_descuentos_mensajero
            ) ?? 0)
        );
        
        // Calcular comisión del rango seleccionado
        $comision_generada = $ingresos_totales * 0.20;
        $utilidad_neta = $ingresos_totales - $comision_generada;
        
        // Para el total a pagar, usar el cálculo basado en todo el historial
        // Esto asegura consistencia con el desglose por día
        $total_pagos_en_rango = 0;
        foreach ($pagos_en_rango_raw as $pago_rango) {
            $total_pagos_en_rango += (float) $pago_rango->total_a_pagar;
        }
        
        // Calcular total_a_pagar basado en historial completo
        // Obtener total de comisiones históricas hasta fecha_hasta
        $comision_historica = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) * 0.20 FROM servicios_gofast 
                 WHERE mensajero_id = %d AND tracking_estado != 'cancelado' 
                 AND DATE(fecha) <= %s",
                $mensajero_id, $fecha_hasta
            )
        ) ?? 0);
        
        $comision_compras_historica = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) * 0.20 FROM compras_gofast 
                 WHERE mensajero_id = %d AND estado != 'cancelada' 
                 AND DATE(fecha_creacion) <= %s",
                $mensajero_id, $fecha_hasta
            )
        ) ?? 0);
        
        $transferencias_historicas = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast 
                 WHERE mensajero_id = %d AND estado = 'aprobada' 
                 AND DATE(fecha_creacion) <= %s",
                $mensajero_id, $fecha_hasta
            )
        ) ?? 0);
        
        $descuentos_historicos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND fecha <= %s",
                $mensajero_id, $fecha_hasta
            )
        ) ?? 0);
        
        $pagos_historicos = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
                 WHERE mensajero_id = %d AND tipo_pago IN ('efectivo', 'transferencia')",
                $mensajero_id
            )
        ) ?? 0);
        
        // Total pendiente histórico hasta la fecha seleccionada
        $total_a_pagar = ($comision_historica + $comision_compras_historica) - $transferencias_historicas - $descuentos_historicos - $pagos_historicos;

        // Calcular desglose por día considerando TODO el historial (sin importar rango de fechas)
        $desglose_dias = [];
        
        // Obtener la primera fecha con actividad del mensajero
        $primera_fecha = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(fecha_actividad) FROM (
                SELECT DATE(fecha) as fecha_actividad FROM servicios_gofast 
                WHERE mensajero_id = %d AND tracking_estado != 'cancelado'
                UNION ALL
                SELECT DATE(fecha_creacion) as fecha_actividad FROM compras_gofast 
                WHERE mensajero_id = %d AND estado != 'cancelada'
            ) as fechas",
            $mensajero_id, $mensajero_id
        ));
        
        if ($primera_fecha) {
            // Calcular desde la primera actividad hasta hoy
            $fecha_inicio_historico = new DateTime($primera_fecha);
            $fecha_fin = new DateTime(); // Hoy
            $fecha_fin->modify('+1 day');
            $intervalo = new DateInterval('P1D');
            $periodo = new DatePeriod($fecha_inicio_historico, $intervalo, $fecha_fin);
            
            // Obtener TODOS los pagos del mensajero
            $todos_pagos = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT fecha, total_a_pagar FROM pagos_mensajeros_gofast 
                     WHERE mensajero_id = %d AND tipo_pago IN ('efectivo', 'transferencia')
                     ORDER BY fecha ASC, fecha_pago ASC",
                    $mensajero_id
                )
            );
            
            // Sumar todos los pagos
            $total_pagos_historico = 0;
            foreach ($todos_pagos as $pago) {
                $total_pagos_historico += (float) $pago->total_a_pagar;
            }
            
            // Calcular todos los días con sus valores
            $dias_historico = [];
            foreach ($periodo as $dia) {
                $fecha_dia = $dia->format('Y-m-d');
                
                // Ingresos del día (servicios + compras)
                $ingresos_dia = (float) ($wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(total), 0) FROM servicios_gofast 
                         WHERE mensajero_id = %d AND tracking_estado != 'cancelado' AND DATE(fecha) = %s",
                        $mensajero_id, $fecha_dia
                    )
                ) ?? 0);
                
                $ingresos_compras_dia = (float) ($wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(valor), 0) FROM compras_gofast 
                         WHERE mensajero_id = %d AND estado != 'cancelada' AND DATE(fecha_creacion) = %s",
                        $mensajero_id, $fecha_dia
                    )
                ) ?? 0);
                
                // Transferencias del día
                $transferencias_dia = (float) ($wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(valor), 0) FROM transferencias_gofast 
                         WHERE mensajero_id = %d AND estado = 'aprobada' AND DATE(fecha_creacion) = %s",
                        $mensajero_id, $fecha_dia
                    )
                ) ?? 0);
                
                // Descuentos del día
                $descuentos_dia = (float) ($wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(valor), 0) FROM descuentos_mensajeros_gofast 
                         WHERE mensajero_id = %d AND fecha = %s",
                        $mensajero_id, $fecha_dia
                    )
                ) ?? 0);
                
                $ingresos_total_dia = $ingresos_dia + $ingresos_compras_dia;
                $comision_dia = $ingresos_total_dia * 0.20;
                $a_pagar_dia = $comision_dia - $transferencias_dia - $descuentos_dia;
                
                // Solo agregar días con actividad
                if ($ingresos_total_dia > 0) {
                    $dias_historico[] = [
                        'fecha' => $fecha_dia,
                        'ingresos' => $ingresos_total_dia,
                        'comision' => $comision_dia,
                        'transferencias' => $transferencias_dia,
                        'descuentos' => $descuentos_dia,
                        'a_pagar' => max(0, $a_pagar_dia),
                        'pagado' => 0,
                        'pendiente' => max(0, $a_pagar_dia)
                    ];
                }
            }
            
            // Distribuir pagos del día más antiguo al más reciente
            $pagos_restantes = $total_pagos_historico;
            for ($i = 0; $i < count($dias_historico) && $pagos_restantes > 0; $i++) {
                if ($dias_historico[$i]['pendiente'] > 0) {
                    $aplicar = min($pagos_restantes, $dias_historico[$i]['pendiente']);
                    $dias_historico[$i]['pagado'] += $aplicar;
                    $dias_historico[$i]['pendiente'] -= $aplicar;
                    $pagos_restantes -= $aplicar;
                }
            }
            
            // Agregar días con saldo pendiente hasta la fecha_hasta seleccionada
            foreach ($dias_historico as $dia) {
                if ($dia['pendiente'] > 0 && $dia['fecha'] <= $fecha_hasta) {
                    $desglose_dias[] = (object) $dia;
                }
            }
        }

        // Agregar mensajero a la lista (incluso si todos los valores son 0)
        $saldos_mensajeros[] = (object) [
            'mensajero_id' => $mensajero_id,
            'mensajero_nombre' => $mensajero->nombre,
            'mensajero_telefono' => $mensajero->telefono,
            'total_destinos' => $total_destinos,
            'total_compras' => $total_compras,
            'ingresos_totales' => $ingresos_totales,
            'comision_generada' => $comision_generada,
            'utilidad_neta' => $utilidad_neta,
            'transferencias_aprobadas' => $transferencias_aprobadas,
            'total_descuentos' => $total_descuentos_mensajero,
            'total_pagos_rango' => $total_pagos_en_rango,
            'total_a_pagar' => $total_a_pagar,
            'desglose_dias' => $desglose_dias
        ];
    }
    
    // Calcular el total de saldos pendientes
    // Fórmula: Comisión(20% de ingresos) - Transferencias Ingresos - Descuentos - Pagos realizados
    // Esto representa lo que se debe a los mensajeros
    
    // Total de pagos en efectivo realizados a mensajeros (solo efectivo, no transferencia)
    if ($es_rango_especifico) {
        $total_pagos_mensajeros = (float) ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
                 WHERE tipo_pago = 'efectivo' 
                 AND fecha >= %s AND fecha <= %s",
                $fecha_desde, $fecha_hasta
            )
        ) ?? 0);
    } else {
        $total_pagos_mensajeros = (float) ($wpdb->get_var(
            "SELECT COALESCE(SUM(total_a_pagar), 0) FROM pagos_mensajeros_gofast 
             WHERE tipo_pago = 'efectivo'"
        ) ?? 0);
    }
    
    // Saldos Pendientes = Comisión - Transferencias Ingresos - Descuentos - Pagos
    $total_saldos_pendientes = $total_comisiones - $total_transferencias_ingresos - $total_descuentos - $total_pagos_mensajeros;
    if ($total_saldos_pendientes < 0) {
        $total_saldos_pendientes = 0;
    }
    
    // Recalcular efectivo ahora que tenemos el valor real de saldos pendientes
    // Efectivo = Subtotal - Saldo Transferencias - Saldos Pendientes
    $efectivo = $subtotal - $saldo_transferencias - $total_saldos_pendientes;
    
    // Filtrar solo mensajeros con saldo pendiente (total_a_pagar > 0)
    $saldos_mensajeros = array_filter($saldos_mensajeros, function($saldo) {
        return $saldo->total_a_pagar > 0;
    });
    // Reindexar el array después del filtro
    $saldos_mensajeros = array_values($saldos_mensajeros);
    
    // Paginación para saldos pendientes
    $por_pagina_saldos = 15;
    $total_saldos_count = count($saldos_mensajeros);
    $pg_saldos = isset($_GET['pg_saldos']) ? max(1, (int) $_GET['pg_saldos']) : 1;
    $total_paginas_saldos = max(1, (int) ceil($total_saldos_count / $por_pagina_saldos));
    $offset_saldos = ($pg_saldos - 1) * $por_pagina_saldos;
    $saldos_mensajeros_paginados = array_slice($saldos_mensajeros, $offset_saldos, $por_pagina_saldos);

    // Contar pedidos sin asignar (es el mismo para todos los mensajeros)
    $where_pedidos_sin_asignar = "tracking_estado != 'cancelado' AND mensajero_id IS NULL";
    $params_pedidos_sin_asignar = [];
    
    if (!empty($fecha_desde)) {
        $where_pedidos_sin_asignar .= " AND fecha >= %s";
        $params_pedidos_sin_asignar[] = $fecha_desde . ' 00:00:00';
    }
    if (!empty($fecha_hasta)) {
        $where_pedidos_sin_asignar .= " AND fecha <= %s";
        $params_pedidos_sin_asignar[] = $fecha_hasta . ' 23:59:59';
    }

    $pedidos_sin_asignar_saldos = (int) ($wpdb->get_var(
        !empty($params_pedidos_sin_asignar)
            ? $wpdb->prepare("SELECT COUNT(*) FROM servicios_gofast WHERE $where_pedidos_sin_asignar", $params_pedidos_sin_asignar)
            : "SELECT COUNT(*) FROM servicios_gofast WHERE tracking_estado != 'cancelado' AND mensajero_id IS NULL"
    ) ?? 0);

    // Obtener todos los pagos de mensajeros para mostrar en el historial (solo efectivo y transferencia)
    $where_historial = ["p.tipo_pago IN ('efectivo', 'transferencia')"];
    $params_historial = [];
    
    if ($filtro_mensajero_historial > 0) {
        $where_historial[] = "p.mensajero_id = %d";
        $params_historial[] = $filtro_mensajero_historial;
    }
    if (!empty($filtro_fecha_desde_historial)) {
        $where_historial[] = "p.fecha >= %s";
        $params_historial[] = $filtro_fecha_desde_historial;
    }
    if (!empty($filtro_fecha_hasta_historial)) {
        $where_historial[] = "p.fecha <= %s";
        $params_historial[] = $filtro_fecha_hasta_historial;
    }
    
    $sql_historial = "SELECT p.*, 
                m.nombre as mensajero_nombre,
                m.telefono as mensajero_telefono,
                u.nombre as creador_nombre
         FROM pagos_mensajeros_gofast p
         LEFT JOIN usuarios_gofast m ON p.mensajero_id = m.id
         LEFT JOIN usuarios_gofast u ON p.creado_por = u.id
         WHERE " . implode(' AND ', $where_historial) . "
         ORDER BY p.fecha DESC, p.fecha_pago DESC";
    
    if (!empty($params_historial)) {
        $pagos_mensajeros_all = $wpdb->get_results($wpdb->prepare($sql_historial, $params_historial));
    } else {
        $pagos_mensajeros_all = $wpdb->get_results($sql_historial);
    }
    
    // Paginación para historial de pagos
    $por_pagina_historial = 15;
    $total_pagos_count = count($pagos_mensajeros_all);
    $pg_historial = isset($_GET['pg_historial']) ? max(1, (int) $_GET['pg_historial']) : 1;
    $total_paginas_historial = max(1, (int) ceil($total_pagos_count / $por_pagina_historial));
    $offset_historial = ($pg_historial - 1) * $por_pagina_historial;
    $pagos_mensajeros_paginados = array_slice($pagos_mensajeros_all, $offset_historial, $por_pagina_historial);
    
    // Organizar pagos por mensajero_id para fácil acceso
    $pagos_por_mensajero = [];
    foreach ($pagos_mensajeros_all as $pago) {
        $mensajero_id = (int) $pago->mensajero_id;
        if (!isset($pagos_por_mensajero[$mensajero_id])) {
            $pagos_por_mensajero[$mensajero_id] = [];
        }
        $pagos_por_mensajero[$mensajero_id][] = $pago;
    }

    // Totales generales (suma de todos los mensajeros o del mensajero filtrado)
    $total_destinos_saldos = array_sum(array_column($saldos_mensajeros, 'total_destinos'));
    $total_compras_saldos = array_sum(array_column($saldos_mensajeros, 'total_compras'));
    $ingresos_totales_saldos = array_sum(array_column($saldos_mensajeros, 'ingresos_totales'));
    $comision_generada_saldos = array_sum(array_column($saldos_mensajeros, 'comision_generada'));
    $utilidad_neta_saldos = array_sum(array_column($saldos_mensajeros, 'utilidad_neta'));
    $transferencias_aprobadas_saldos = array_sum(array_column($saldos_mensajeros, 'transferencias_aprobadas'));
    $total_a_pagar_saldos = array_sum(array_column($saldos_mensajeros, 'total_a_pagar'));

    // Calcular totales por día
    $transferencias_por_dia = [];
    foreach ($transferencias_entradas as $transf) {
        $fecha = gofast_date_format($transf->fecha_creacion, 'Y-m-d');
        if (!isset($transferencias_por_dia[$fecha])) {
            $transferencias_por_dia[$fecha] = [
                'fecha' => $fecha,
                'total' => 0,
                'cantidad' => 0
            ];
        }
        $transferencias_por_dia[$fecha]['total'] += (float) $transf->valor;
        $transferencias_por_dia[$fecha]['cantidad']++;
    }
    krsort($transferencias_por_dia); // Ordenar por fecha descendente

    ob_start();
    ?>

<div class="gofast-home">
    <script>
    // Función global para cambiar sub-tabs de saldos mensajeros
    window.mostrarSubTabSaldos = function(subtab, element) {
        // Ocultar todos los sub-tabs
        document.querySelectorAll('.gofast-subtab-content').forEach(function(content) {
            content.style.display = 'none';
        });
        
        // Remover clase activa de todos los botones de sub-tab
        document.querySelectorAll('.gofast-subtab-btn').forEach(function(btn) {
            btn.classList.remove('gofast-subtab-active');
            btn.style.background = '#f0f0f0';
            btn.style.fontWeight = '400';
            btn.style.color = '#666';
        });
        
        // Mostrar el sub-tab seleccionado
        const subtabContent = document.getElementById('subtab-' + subtab);
        if (subtabContent) {
            subtabContent.style.display = 'block';
        }
        
        // Agregar clase activa al botón
        if (element) {
            element.classList.add('gofast-subtab-active');
            element.style.background = 'var(--gofast-yellow)';
            element.style.fontWeight = '600';
            element.style.color = '#000';
        }
        
        // Actualizar URL sin recargar
        const url = new URL(window.location);
        url.searchParams.set('subtab_saldos', subtab);
        window.history.pushState({}, '', url);
    };

    // Función global para cambiar tabs - debe estar disponible inmediatamente
    window.mostrarTabFinanzas = function(tab, element) {
        // Ocultar todos los tabs
        document.querySelectorAll('.gofast-config-tab-content').forEach(function(content) {
            content.style.display = 'none';
        });
        
        // Remover clase activa de todos los botones
        document.querySelectorAll('.gofast-config-tab').forEach(function(btn) {
            btn.classList.remove('gofast-config-tab-active');
        });
        
        // Mostrar el tab seleccionado
        const tabContent = document.getElementById('tab-' + tab);
        if (tabContent) {
            tabContent.style.display = 'block';
        }
        
        // Agregar clase activa al botón
        if (element) {
            element.classList.add('gofast-config-tab-active');
        } else {
            // Si no hay element, buscar el botón correspondiente
            document.querySelectorAll('.gofast-config-tab').forEach(function(btn) {
                if (btn.textContent.includes(tab === 'ingresos' ? 'Ingresos' : 
                                             tab === 'egresos' ? 'Egresos' : 
                                             tab === 'vales_empresa' ? 'Vales Empresa' : 
                                             tab === 'vales_personal' ? 'Vales Personal' : 
                                             tab === 'transferencias_entradas' ? 'Transferencias Entradas' : 
                                             tab === 'transferencias_salidas' ? 'Transferencias Salidas' : 
                                             tab === 'descuentos' ? 'Descuentos' :
                                             'Saldos Mensajeros')) {
                    btn.classList.add('gofast-config-tab-active');
                }
            });
        }
        
        // Actualizar el campo hidden del formulario de filtros para preservar el tab
        const filtroTabActivo = document.getElementById('filtro-tab-activo');
        if (filtroTabActivo) {
            filtroTabActivo.value = tab;
        }
        
        // Actualizar URL sin recargar
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);
        
        // Inicializar Select2 cuando se muestre cualquier tab
        if (typeof inicializarSelect2Filtros === 'function') {
            setTimeout(function() {
                inicializarSelect2Filtros();
            }, 100);
        }
    };
    </script>
    
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin-bottom:8px;">💰 Módulo de Finanzas</h1>
            <p class="gofast-home-text" style="margin:0;">
                Gestiona ingresos, egresos, vales, transferencias y saldos de mensajeros.
            </p>
        </div>
        <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;">
            ← Volver al Dashboard
        </a>
    </div>

    <!-- Mensaje de resultado -->
    <?php if ($mensaje): ?>
        <div class="gofast-box" style="background: <?= $mensaje_tipo === 'success' ? '#d4edda' : '#f8d7da' ?>; border-left: 4px solid <?= $mensaje_tipo === 'success' ? '#28a745' : '#dc3545' ?>; color: <?= $mensaje_tipo === 'success' ? '#155724' : '#721c24' ?>; margin-bottom: 20px;">
            <?= esc_html($mensaje) ?>
        </div>
    <?php endif; ?>


    <!-- BLOQUE DE RESULTADOS GENERALES -->
    <div class="gofast-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <h3 style="margin-top: 0; color: #fff; margin-bottom: 0;">📊 Resultados Generales</h3>
            <div style="background: rgba(255,255,255,0.2); padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 600;">
                📅 Rango de fechas: 
                <strong><?= gofast_date_format($fecha_desde, 'd/m/Y') ?></strong> 
                <?php if ($fecha_desde !== $fecha_hasta): ?>
                    hasta <strong><?= gofast_date_format($fecha_hasta, 'd/m/Y') ?></strong>
                <?php endif; ?>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 16px;">
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">
                    Ingresos
                </div>
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 6px;">$<?= number_format($total_comisiones, 0, ',', '.') ?></div>
                <div style="font-size: 10px; opacity: 0.8; line-height: 1.4; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 6px; margin-top: 6px;">
                    <strong>Detalle:</strong><br>
                    • Servicios: $<?= number_format($total_ingresos_servicios, 0, ',', '.') ?><br>
                    • Compras: $<?= number_format($total_ingresos_compras, 0, ',', '.') ?><br>
                    • Total: $<?= number_format($total_ingresos, 0, ',', '.') ?> × 20%
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">
                    Total Egresos
                </div>
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 6px;">$<?= number_format($total_egresos, 0, ',', '.') ?></div>
                <div style="font-size: 10px; opacity: 0.8; line-height: 1.4; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 6px; margin-top: 6px;">
                    <strong>Detalle:</strong><br>
                    Suma de egresos registrados
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">
                    Vales Empresa
                </div>
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 6px;">$<?= number_format($total_vales_empresa, 0, ',', '.') ?></div>
                <div style="font-size: 10px; opacity: 0.8; line-height: 1.4; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 6px; margin-top: 6px;">
                    <strong>Detalle:</strong><br>
                    Suma de vales de empresa
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">
                    Vales Personal
                </div>
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 6px;">$<?= number_format($total_vales_personal, 0, ',', '.') ?></div>
                <div style="font-size: 10px; opacity: 0.8; line-height: 1.4; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 6px; margin-top: 6px;">
                    <strong>Detalle:</strong><br>
                    Suma de vales del personal
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">
                    Transf. Ingresos
                </div>
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 6px;">$<?= number_format($total_transferencias_ingresos, 0, ',', '.') ?></div>
                <div style="font-size: 10px; opacity: 0.8; line-height: 1.4; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 6px; margin-top: 6px;">
                    <strong>Detalle:</strong><br>
                    Transferencias aprobadas
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">
                    Transf. Salidas
                </div>
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 6px;">$<?= number_format($total_transferencias_salidas, 0, ',', '.') ?></div>
                <div style="font-size: 10px; opacity: 0.8; line-height: 1.4; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 6px; margin-top: 6px;">
                    <strong>Detalle:</strong><br>
                    Transferencias salientes
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">
                    Saldo Transferencias
                </div>
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 6px;">$<?= number_format($saldo_transferencias, 0, ',', '.') ?></div>
                <div style="font-size: 10px; opacity: 0.8; line-height: 1.4; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 6px; margin-top: 6px;">
                    <strong>Detalle:</strong><br>
                    Ingresos - Salidas<br>
                    <small style="opacity: 0.7;">$<?= number_format($total_transferencias_ingresos, 0, ',', '.') ?> - $<?= number_format($total_transferencias_salidas, 0, ',', '.') ?></small>
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">
                    Saldos Pendientes
                </div>
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 6px;">$<?= number_format($total_saldos_pendientes, 0, ',', '.') ?></div>
                <div style="font-size: 10px; opacity: 0.8; line-height: 1.4; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 6px; margin-top: 6px;">
                    <strong>Detalle:</strong><br>
                    Comisión - Transf. - Desc. - Pagos
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 4px;">
                    Total Descuentos
                </div>
                <div style="font-size: 20px; font-weight: 700; margin-bottom: 6px; color: <?= $total_descuentos < 0 ? '#28a745' : '#fff' ?>;">
                    $<?= number_format(abs($total_descuentos), 0, ',', '.') ?>
                    <?php if ($total_descuentos < 0): ?>
                        <small style="font-size: 12px; opacity: 0.8;">(Bonificación)</small>
                    <?php endif; ?>
                </div>
                <div style="font-size: 10px; opacity: 0.8; line-height: 1.4; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 6px; margin-top: 6px;">
                    <strong>Detalle:</strong><br>
                    Descuentos a mensajeros
                    <?php if (!empty($fecha_desde) && !empty($fecha_hasta)): ?>
                        <br><small style="opacity: 0.7;">Rango: <?= gofast_date_format($fecha_desde, 'd/m/Y') ?> - <?= gofast_date_format($fecha_hasta, 'd/m/Y') ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div style="background: rgba(255,255,255,0.2); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 6px;">
                        Subtotal
                    </div>
                    <div style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">$<?= number_format($subtotal, 0, ',', '.') ?></div>
                    <div style="font-size: 11px; opacity: 0.85; line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.3); padding-top: 8px; margin-top: 8px;">
                        <strong>Detalle:</strong><br>
                        Ingresos (20%) - Egresos - Vales Empresa - Descuentos<br>
                        <small style="opacity: 0.7;">$<?= number_format($total_comisiones, 0, ',', '.') ?> - $<?= number_format($total_egresos, 0, ',', '.') ?> - $<?= number_format($total_vales_empresa, 0, ',', '.') ?> - $<?= number_format($total_descuentos, 0, ',', '.') ?></small>
                    </div>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 6px;">
                        Efectivo
                    </div>
                    <div style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">$<?= number_format($efectivo, 0, ',', '.') ?></div>
                    <div style="font-size: 11px; opacity: 0.85; line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.3); padding-top: 8px; margin-top: 8px;">
                        <strong>Detalle:</strong><br>
                        Subtotal - Transferencias - Pendientes<br>
                        <small style="opacity: 0.7;">$<?= number_format($subtotal, 0, ',', '.') ?> - $<?= number_format($saldo_transferencias, 0, ',', '.') ?> - $<?= number_format($total_saldos_pendientes, 0, ',', '.') ?></small>
                    </div>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 6px;">
                        Utilidad Total
                    </div>
                    <div style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">$<?= number_format($utilidad_total, 0, ',', '.') ?></div>
                    <div style="font-size: 11px; opacity: 0.85; line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.3); padding-top: 8px; margin-top: 8px;">
                        <strong>Detalle:</strong><br>
                        Utilidad Total = Subtotal
                    </div>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 16px; border-radius: 8px;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 6px;">
                        Utilidad Individual
                    </div>
                    <div style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">$<?= number_format($utilidad_individual, 0, ',', '.') ?></div>
                    <div style="font-size: 11px; opacity: 0.85; line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.3); padding-top: 8px; margin-top: 8px;">
                        <strong>Detalle:</strong><br>
                        Utilidad Total ÷ 2<br>
                        <small style="opacity: 0.7;">$<?= number_format($utilidad_total, 0, ',', '.') ?> ÷ 2</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs de navegación -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 2px solid #ddd; margin-bottom: 20px;">
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'ingresos' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('ingresos', this)">
                💰 Ingresos
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'egresos' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('egresos', this)">
                💸 Egresos
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'vales_empresa' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('vales_empresa', this)">
                🏢 Vales Empresa
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'vales_personal' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('vales_personal', this)">
                👥 Vales Personal
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'transferencias_entradas' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('transferencias_entradas', this)">
                📥 Transferencias Entradas
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'transferencias_salidas' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('transferencias_salidas', this)">
                📤 Transferencias Salidas
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'descuentos' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('descuentos', this)">
                ➖ Descuentos
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'saldos_mensajeros' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTabFinanzas('saldos_mensajeros', this)">
                💵 Saldos Mensajeros
            </button>
        </div>

        <!-- Filtros generales -->
        <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
            <h4 style="margin-top: 0; margin-bottom: 12px;">🔍 Filtros</h4>
            <form method="get" action="" id="form-filtros-generales" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                <!-- Mantener otros parámetros GET (excepto los que vamos a actualizar) -->
                <?php foreach ($_GET as $key => $value): ?>
                    <?php if (!in_array($key, ['fecha_desde', 'fecha_hasta', 'tab', 'filtro_descripcion', 'filtro_mensajero', 'filtro_origen', 'filtro_mensajero_descuentos', 'filtro_descripcion_descuentos'])): ?>
                        <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <!-- SIEMPRE preservar el tab activo -->
                <input type="hidden" name="tab" id="filtro-tab-activo" value="<?= esc_attr($tab_activo) ?>">
                
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
                    <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                        🔍 Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB: INGRESOS -->
        <div id="tab-ingresos" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'ingresos' ? 'block' : 'none' ?>;">
            <h3>💰 Ingresos</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Ingresos diarios calculados automáticamente desde servicios y compras. Las comisiones se calculan como el 20% del total de ingresos.
            </p>

            <?php if (empty($ingresos_diarios)): ?>
                <div class="gofast-box" style="text-align: center; padding: 40px;">
                    <p style="color: #666; margin: 0;">No hay ingresos registrados en el rango de fechas seleccionado.</p>
                </div>
            <?php else: ?>
                <div class="gofast-table-wrap">
                    <table class="gofast-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th># Pedidos</th>
                                <th># Compras</th>
                                <th>Total Servicios</th>
                                <th>Total Compras</th>
                                <th>Ingresos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ingresos_diarios as $ingreso): ?>
                                <tr>
                                    <td><?= gofast_date_format($ingreso['fecha'], 'd/m/Y') ?></td>
                                    <td><?= $ingreso['num_pedidos'] ?></td>
                                    <td><?= $ingreso['num_compras'] ?></td>
                                    <td>$<?= number_format($ingreso['total_servicios'], 0, ',', '.') ?></td>
                                    <td>$<?= number_format($ingreso['total_compras'], 0, ',', '.') ?></td>
                                    <td><strong style="color: #28a745;">$<?= number_format($ingreso['total_comisiones'], 0, ',', '.') ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: EGRESOS -->
        <div id="tab-egresos" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'egresos' ? 'block' : 'none' ?>;">
            <h3>💸 Egresos</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona los egresos de la empresa. Puedes crear, editar y eliminar registros.
            </p>

            <!-- Formulario crear egreso -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Insertar Egreso</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_crear_egreso', 'gofast_egreso_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha <span style="color: #dc3545;">*</span></label>
                            <input type="date" 
                                   name="fecha" 
                                   value="<?= esc_attr(gofast_date_today()) ?>"
                                   required
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Descripción <span style="color: #dc3545;">*</span></label>
                            <input type="text" 
                                   name="descripcion" 
                                   required
                                   maxlength="255"
                                   placeholder="Ej: Pago de servicios"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor <span style="color: #dc3545;">*</span></label>
                            <input type="number" 
                                   name="valor" 
                                   step="0.01"
                                   min="0.01"
                                   required
                                   placeholder="Ej: 50000"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_crear_egreso" class="gofast-btn-mini">✅ Crear Egreso</button>
                </form>
            </div>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_descripcion'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtro por descripción</label>
                        <input type="text" 
                               name="filtro_descripcion" 
                               value="<?= esc_attr($filtro_descripcion) ?>"
                               placeholder="Buscar en descripción..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if (!empty($filtro_descripcion)): ?>
                        <div>
                            <a href="<?= esc_url(remove_query_arg('filtro_descripcion')) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Listado de egresos -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Listado de Egresos</h4>
                
                <?php if (empty($egresos)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay egresos registrados.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Valor</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($egresos as $egreso): ?>
                                    <tr>
                                        <td>#<?= esc_html($egreso->id) ?></td>
                                        <td><?= gofast_date_format($egreso->fecha, 'd/m/Y') ?></td>
                                        <td><?= esc_html($egreso->descripcion) ?></td>
                                        <td><strong>$<?= number_format($egreso->valor, 0, ',', '.') ?></strong></td>
                                        <td><?= esc_html($egreso->creador_nombre ?? '—') ?></td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" 
                                                    class="gofast-btn-mini gofast-btn-editar-egreso" 
                                                    data-egreso-id="<?= esc_attr($egreso->id) ?>"
                                                    data-egreso-fecha="<?= esc_attr($egreso->fecha) ?>"
                                                    data-egreso-descripcion="<?= esc_attr($egreso->descripcion) ?>"
                                                    data-egreso-valor="<?= esc_attr($egreso->valor) ?>"
                                                    style="background: var(--gofast-yellow); color: #000;">
                                                ✏️ Editar
                                            </button>
                                            <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar este egreso? Esta acción no se puede deshacer.');">
                                                <?php wp_nonce_field('gofast_eliminar_egreso', 'gofast_eliminar_egreso_nonce'); ?>
                                                <input type="hidden" name="egreso_id" value="<?= esc_attr($egreso->id) ?>">
                                                <button type="submit" name="gofast_eliminar_egreso" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                    🗑️ Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal para editar egreso -->
        <div id="modal-editar-egreso" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
            <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Egreso</h2>
                
                <form method="post" id="form-editar-egreso">
                    <?php wp_nonce_field('gofast_editar_egreso', 'gofast_editar_egreso_nonce'); ?>
                    <input type="hidden" name="egreso_id" id="editar-egreso-id">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Fecha:</label>
                        <input type="date" 
                               name="fecha" 
                               id="editar-egreso-fecha"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Descripción:</label>
                        <input type="text" 
                               name="descripcion" 
                               id="editar-egreso-descripcion"
                               required
                               maxlength="255"
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Valor:</label>
                        <input type="number" 
                               name="valor" 
                               id="editar-egreso-valor"
                               step="0.01"
                               min="0.01"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                        <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditarEgreso()">Cancelar</button>
                        <button type="submit" name="gofast_editar_egreso" class="gofast-btn-mini">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Abrir modal de editar egreso
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.gofast-btn-editar-egreso').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const egresoId = this.getAttribute('data-egreso-id');
                    const egresoFecha = this.getAttribute('data-egreso-fecha');
                    const egresoDescripcion = this.getAttribute('data-egreso-descripcion');
                    const egresoValor = this.getAttribute('data-egreso-valor');
                    
                    document.getElementById('editar-egreso-id').value = egresoId;
                    document.getElementById('editar-egreso-fecha').value = egresoFecha;
                    document.getElementById('editar-egreso-descripcion').value = egresoDescripcion;
                    document.getElementById('editar-egreso-valor').value = egresoValor;
                    
                    document.getElementById('modal-editar-egreso').style.display = 'block';
                });
            });
            
            // Cerrar modal al hacer clic fuera
            document.getElementById('modal-editar-egreso').addEventListener('click', function(e) {
                if (e.target === this) {
                    cerrarModalEditarEgreso();
                }
            });
        });
        
        function cerrarModalEditarEgreso() {
            document.getElementById('modal-editar-egreso').style.display = 'none';
            document.getElementById('form-editar-egreso').reset();
        }
        </script>

        <!-- TAB: VALES EMPRESA -->
        <div id="tab-vales_empresa" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'vales_empresa' ? 'block' : 'none' ?>;">
            <h3>🏢 Vales de la Empresa</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona los vales de la empresa. Puedes crear, editar y eliminar registros.
            </p>

            <!-- Formulario crear vale empresa -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Insertar Vale de la Empresa</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_crear_vale_empresa', 'gofast_vale_empresa_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha <span style="color: #dc3545;">*</span></label>
                            <input type="date" 
                                   name="fecha" 
                                   value="<?= esc_attr(gofast_date_today()) ?>"
                                   required
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Descripción <span style="color: #dc3545;">*</span></label>
                            <input type="text" 
                                   name="descripcion" 
                                   required
                                   maxlength="255"
                                   placeholder="Ej: Vale para combustible"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor <span style="color: #dc3545;">*</span></label>
                            <input type="number" 
                                   name="valor" 
                                   step="0.01"
                                   min="0.01"
                                   required
                                   placeholder="Ej: 50000"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_crear_vale_empresa" class="gofast-btn-mini">✅ Crear Vale</button>
                </form>
            </div>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_descripcion_vales_empresa'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtro por descripción</label>
                        <input type="text" 
                               name="filtro_descripcion_vales_empresa" 
                               value="<?= esc_attr($filtro_descripcion_vales_empresa) ?>"
                               placeholder="Buscar en descripción..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if (!empty($filtro_descripcion_vales_empresa)): ?>
                        <div>
                            <a href="<?= esc_url(remove_query_arg('filtro_descripcion_vales_empresa')) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Listado de vales empresa -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Listado de Vales de la Empresa</h4>
                
                <?php if (empty($vales_empresa)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay vales de empresa registrados.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Valor</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vales_empresa as $vale): ?>
                                    <tr>
                                        <td>#<?= esc_html($vale->id) ?></td>
                                        <td><?= gofast_date_format($vale->fecha, 'd/m/Y') ?></td>
                                        <td><?= esc_html($vale->descripcion) ?></td>
                                        <td><strong>$<?= number_format($vale->valor, 0, ',', '.') ?></strong></td>
                                        <td><?= esc_html($vale->creador_nombre ?? '—') ?></td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" 
                                                    class="gofast-btn-mini gofast-btn-editar-vale-empresa" 
                                                    data-vale-id="<?= esc_attr($vale->id) ?>"
                                                    data-vale-fecha="<?= esc_attr($vale->fecha) ?>"
                                                    data-vale-descripcion="<?= esc_attr($vale->descripcion) ?>"
                                                    data-vale-valor="<?= esc_attr($vale->valor) ?>"
                                                    style="background: var(--gofast-yellow); color: #000;">
                                                ✏️ Editar
                                            </button>
                                            <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar este vale? Esta acción no se puede deshacer.');">
                                                <?php wp_nonce_field('gofast_eliminar_vale_empresa', 'gofast_eliminar_vale_empresa_nonce'); ?>
                                                <input type="hidden" name="vale_id" value="<?= esc_attr($vale->id) ?>">
                                                <button type="submit" name="gofast_eliminar_vale_empresa" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                    🗑️ Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal para editar vale empresa -->
        <div id="modal-editar-vale-empresa" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
            <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Vale de la Empresa</h2>
                
                <form method="post" id="form-editar-vale-empresa">
                    <?php wp_nonce_field('gofast_editar_vale_empresa', 'gofast_editar_vale_empresa_nonce'); ?>
                    <input type="hidden" name="vale_id" id="editar-vale-empresa-id">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Fecha:</label>
                        <input type="date" 
                               name="fecha" 
                               id="editar-vale-empresa-fecha"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Descripción:</label>
                        <input type="text" 
                               name="descripcion" 
                               id="editar-vale-empresa-descripcion"
                               required
                               maxlength="255"
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Valor:</label>
                        <input type="number" 
                               name="valor" 
                               id="editar-vale-empresa-valor"
                               step="0.01"
                               min="0.01"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                        <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditarValeEmpresa()">Cancelar</button>
                        <button type="submit" name="gofast_editar_vale_empresa" class="gofast-btn-mini">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Abrir modal de editar vale empresa
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.gofast-btn-editar-vale-empresa').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const valeId = this.getAttribute('data-vale-id');
                    const valeFecha = this.getAttribute('data-vale-fecha');
                    const valeDescripcion = this.getAttribute('data-vale-descripcion');
                    const valeValor = this.getAttribute('data-vale-valor');
                    
                    document.getElementById('editar-vale-empresa-id').value = valeId;
                    document.getElementById('editar-vale-empresa-fecha').value = valeFecha;
                    document.getElementById('editar-vale-empresa-descripcion').value = valeDescripcion;
                    document.getElementById('editar-vale-empresa-valor').value = valeValor;
                    
                    document.getElementById('modal-editar-vale-empresa').style.display = 'block';
                });
            });
            
            // Cerrar modal al hacer clic fuera
            const modalEditarValeEmpresa = document.getElementById('modal-editar-vale-empresa');
            if (modalEditarValeEmpresa) {
                modalEditarValeEmpresa.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cerrarModalEditarValeEmpresa();
                    }
                });
            }
        });
        
        function cerrarModalEditarValeEmpresa() {
            document.getElementById('modal-editar-vale-empresa').style.display = 'none';
            document.getElementById('form-editar-vale-empresa').reset();
        }
        </script>

        <!-- TAB: VALES PERSONAL -->
        <div id="tab-vales_personal" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'vales_personal' ? 'block' : 'none' ?>;">
            <h3>👥 Vales del Personal</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona los vales del personal (4 personas activas). Puedes crear, editar y eliminar registros.
            </p>

            <!-- Formulario crear vale personal -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Insertar Vale del Personal</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_crear_vale_personal', 'gofast_vale_personal_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha <span style="color: #dc3545;">*</span></label>
                            <input type="date" 
                                   name="fecha" 
                                   value="<?= esc_attr(gofast_date_today()) ?>"
                                   required
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Persona <span style="color: #dc3545;">*</span></label>
                            <select name="persona_id" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">Seleccione una persona</option>
                                <?php foreach ($personas_activas as $persona): ?>
                                    <option value="<?= (int) $persona->id; ?>"><?= esc_html($persona->nombre); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Descripción <span style="color: #dc3545;">*</span></label>
                            <input type="text" 
                                   name="descripcion" 
                                   required
                                   maxlength="255"
                                   placeholder="Ej: Vale para viáticos"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor <span style="color: #dc3545;">*</span></label>
                            <input type="number" 
                                   name="valor" 
                                   step="0.01"
                                   min="0.01"
                                   required
                                   placeholder="Ej: 50000"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_crear_vale_personal" class="gofast-btn-mini">✅ Crear Vale</button>
                </form>
            </div>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_descripcion_vales_personal', 'filtro_persona'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtro por persona</label>
                        <select name="filtro_persona" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="0">Todas las personas</option>
                            <?php foreach ($personas_activas as $persona): ?>
                                <option value="<?= (int) $persona->id; ?>"<?php selected($filtro_persona, $persona->id); ?>>
                                    <?= esc_html($persona->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtro por descripción</label>
                        <input type="text" 
                               name="filtro_descripcion_vales_personal" 
                               value="<?= esc_attr($filtro_descripcion_vales_personal) ?>"
                               placeholder="Buscar en descripción..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if ($filtro_persona > 0 || !empty($filtro_descripcion_vales_personal)): ?>
                        <div>
                            <a href="<?= esc_url(remove_query_arg(['filtro_persona', 'filtro_descripcion_vales_personal'])) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Listado de vales personal -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Listado de Vales del Personal</h4>
                
                <?php if (empty($vales_personal)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay vales de personal registrados.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Persona</th>
                                    <th>Descripción</th>
                                    <th>Valor</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vales_personal as $vale): ?>
                                    <tr>
                                        <td>#<?= esc_html($vale->id) ?></td>
                                        <td><?= gofast_date_format($vale->fecha, 'd/m/Y') ?></td>
                                        <td><strong><?= esc_html($vale->persona_nombre ?? '—') ?></strong></td>
                                        <td><?= esc_html($vale->descripcion) ?></td>
                                        <td><strong>$<?= number_format($vale->valor, 0, ',', '.') ?></strong></td>
                                        <td><?= esc_html($vale->creador_nombre ?? '—') ?></td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" 
                                                    class="gofast-btn-mini gofast-btn-editar-vale-personal" 
                                                    data-vale-id="<?= esc_attr($vale->id) ?>"
                                                    data-vale-fecha="<?= esc_attr($vale->fecha) ?>"
                                                    data-vale-persona-id="<?= esc_attr($vale->persona_id) ?>"
                                                    data-vale-descripcion="<?= esc_attr($vale->descripcion) ?>"
                                                    data-vale-valor="<?= esc_attr($vale->valor) ?>"
                                                    style="background: var(--gofast-yellow); color: #000;">
                                                ✏️ Editar
                                            </button>
                                            <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar este vale? Esta acción no se puede deshacer.');">
                                                <?php wp_nonce_field('gofast_eliminar_vale_personal', 'gofast_eliminar_vale_personal_nonce'); ?>
                                                <input type="hidden" name="vale_id" value="<?= esc_attr($vale->id) ?>">
                                                <button type="submit" name="gofast_eliminar_vale_personal" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                    🗑️ Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal para editar vale personal -->
        <div id="modal-editar-vale-personal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
            <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Vale del Personal</h2>
                
                <form method="post" id="form-editar-vale-personal">
                    <?php wp_nonce_field('gofast_editar_vale_personal', 'gofast_editar_vale_personal_nonce'); ?>
                    <input type="hidden" name="vale_id" id="editar-vale-personal-id">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Fecha:</label>
                        <input type="date" 
                               name="fecha" 
                               id="editar-vale-personal-fecha"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Persona:</label>
                        <select name="persona_id" id="editar-vale-personal-persona-id" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                            <option value="">Seleccione una persona</option>
                            <?php foreach ($personas_activas as $persona): ?>
                                <option value="<?= (int) $persona->id; ?>"><?= esc_html($persona->nombre); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Descripción:</label>
                        <input type="text" 
                               name="descripcion" 
                               id="editar-vale-personal-descripcion"
                               required
                               maxlength="255"
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Valor:</label>
                        <input type="number" 
                               name="valor" 
                               id="editar-vale-personal-valor"
                               step="0.01"
                               min="0.01"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                        <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditarValePersonal()">Cancelar</button>
                        <button type="submit" name="gofast_editar_vale_personal" class="gofast-btn-mini">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Abrir modal de editar vale personal
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.gofast-btn-editar-vale-personal').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const valeId = this.getAttribute('data-vale-id');
                    const valeFecha = this.getAttribute('data-vale-fecha');
                    const valePersonaId = this.getAttribute('data-vale-persona-id');
                    const valeDescripcion = this.getAttribute('data-vale-descripcion');
                    const valeValor = this.getAttribute('data-vale-valor');
                    
                    document.getElementById('editar-vale-personal-id').value = valeId;
                    document.getElementById('editar-vale-personal-fecha').value = valeFecha;
                    document.getElementById('editar-vale-personal-persona-id').value = valePersonaId;
                    document.getElementById('editar-vale-personal-descripcion').value = valeDescripcion;
                    document.getElementById('editar-vale-personal-valor').value = valeValor;
                    
                    document.getElementById('modal-editar-vale-personal').style.display = 'block';
                });
            });
            
            // Cerrar modal al hacer clic fuera
            const modalEditarValePersonal = document.getElementById('modal-editar-vale-personal');
            if (modalEditarValePersonal) {
                modalEditarValePersonal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cerrarModalEditarValePersonal();
                    }
                });
            }
        });
        
        function cerrarModalEditarValePersonal() {
            document.getElementById('modal-editar-vale-personal').style.display = 'none';
            document.getElementById('form-editar-vale-personal').reset();
        }
        </script>

        <!-- TAB: TRANSFERENCIAS ENTRADAS -->
        <div id="tab-transferencias_entradas" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'transferencias_entradas' ? 'block' : 'none' ?>;">
            <h3>📥 Transferencias (Entradas)</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Visualiza las transferencias entrantes desde los mensajeros (transferencias aprobadas). Los datos se cargan automáticamente desde el sistema de transferencias.
            </p>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_mensajero', 'filtro_origen'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label>Mensajero</label>
                        <select name="filtro_mensajero" id="filtro-mensajero-entradas" class="gofast-select-filtro" data-placeholder="Todos los mensajeros">
                            <option value="0">Todos</option>
                            <?php foreach ($mensajeros as $m): ?>
                                <option value="<?= (int) $m->id; ?>"<?php selected($filtro_mensajero, $m->id); ?>>
                                    <?= esc_html($m->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if ($filtro_mensajero > 0): ?>
                        <div>
                            <a href="<?= esc_url(remove_query_arg('filtro_mensajero')) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Resumen por día -->
            <?php if (!empty($transferencias_por_dia)): ?>
                <div class="gofast-box" style="background: #e7f3ff; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">📊 Resumen por Día</h4>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cantidad</th>
                                    <th>Total del Día</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transferencias_por_dia as $dia): ?>
                                    <tr>
                                        <td><?= gofast_date_format($dia['fecha'], 'd/m/Y') ?></td>
                                        <td><?= $dia['cantidad'] ?> transferencia(s)</td>
                                        <td><strong>$<?= number_format($dia['total'], 0, ',', '.') ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Listado detallado de transferencias -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Listado Detallado de Transferencias</h4>
                
                <?php if (empty($transferencias_entradas)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay transferencias aprobadas en el rango de fechas seleccionado.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Mensajero</th>
                                    <th>Valor</th>
                                    <th>Creado por</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transferencias_entradas as $transf): ?>
                                    <tr>
                                        <td>#<?= esc_html($transf->id) ?></td>
                                        <td><?= gofast_date_format($transf->fecha_creacion, 'd/m/Y H:i') ?></td>
                                        <td>
                                            <?= esc_html($transf->mensajero_nombre) ?><br>
                                            <small style="color: #666;"><?= esc_html($transf->mensajero_telefono) ?></small>
                                        </td>
                                        <td><strong>$<?= number_format($transf->valor, 0, ',', '.') ?></strong></td>
                                        <td><?= esc_html($transf->creador_nombre ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: 700;">
                                    <td colspan="3" style="text-align: right;">Total:</td>
                                    <td>$<?= number_format(array_sum(array_column($transferencias_entradas, 'valor')), 0, ',', '.') ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB: TRANSFERENCIAS SALIDAS -->
        <div id="tab-transferencias_salidas" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'transferencias_salidas' ? 'block' : 'none' ?>;">
            <h3>📤 Transferencias (Salidas)</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona las transferencias salientes de la empresa. Puedes crear, editar y eliminar registros.
            </p>

            <!-- Formulario crear transferencia salida -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Insertar Transferencia Salida</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_crear_transferencia_salida', 'gofast_transferencia_salida_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha <span style="color: #dc3545;">*</span></label>
                            <input type="date" 
                                   name="fecha" 
                                   value="<?= esc_attr(gofast_date_today()) ?>"
                                   required
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Descripción <span style="color: #dc3545;">*</span></label>
                            <input type="text" 
                                   name="descripcion" 
                                   required
                                   maxlength="255"
                                   placeholder="Ej: Transferencia a proveedor"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor <span style="color: #dc3545;">*</span></label>
                            <input type="number" 
                                   name="valor" 
                                   step="0.01"
                                   min="0.01"
                                   required
                                   placeholder="Ej: 50000"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_crear_transferencia_salida" class="gofast-btn-mini">✅ Crear Transferencia</button>
                </form>
            </div>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_descripcion_transf_salidas'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtro por descripción</label>
                        <input type="text" 
                               name="filtro_descripcion_transf_salidas" 
                               value="<?= esc_attr($filtro_descripcion_transf_salidas) ?>"
                               placeholder="Buscar en descripción..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if (!empty($filtro_descripcion_transf_salidas)): ?>
                        <div>
                            <a href="<?= esc_url(remove_query_arg('filtro_descripcion_transf_salidas')) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Listado de transferencias salidas -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Listado de Transferencias Salidas</h4>
                
                <?php if (empty($transferencias_salidas)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay transferencias salidas registradas.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Valor</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transferencias_salidas as $transf): ?>
                                    <tr>
                                        <td>#<?= esc_html($transf->id) ?></td>
                                        <td><?= gofast_date_format($transf->fecha, 'd/m/Y') ?></td>
                                        <td><?= esc_html($transf->descripcion) ?></td>
                                        <td><strong>$<?= number_format($transf->valor, 0, ',', '.') ?></strong></td>
                                        <td><?= esc_html($transf->creador_nombre ?? '—') ?></td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" 
                                                    class="gofast-btn-mini gofast-btn-editar-transf-salida" 
                                                    data-transf-id="<?= esc_attr($transf->id) ?>"
                                                    data-transf-fecha="<?= esc_attr($transf->fecha) ?>"
                                                    data-transf-descripcion="<?= esc_attr($transf->descripcion) ?>"
                                                    data-transf-valor="<?= esc_attr($transf->valor) ?>"
                                                    style="background: var(--gofast-yellow); color: #000;">
                                                ✏️ Editar
                                            </button>
                                            <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar esta transferencia? Esta acción no se puede deshacer.');">
                                                <?php wp_nonce_field('gofast_eliminar_transferencia_salida', 'gofast_eliminar_transferencia_salida_nonce'); ?>
                                                <input type="hidden" name="transferencia_id" value="<?= esc_attr($transf->id) ?>">
                                                <button type="submit" name="gofast_eliminar_transferencia_salida" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                    🗑️ Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal para editar transferencia salida -->
        <div id="modal-editar-transf-salida" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
            <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Transferencia Salida</h2>
                
                <form method="post" id="form-editar-transf-salida">
                    <?php wp_nonce_field('gofast_editar_transferencia_salida', 'gofast_editar_transferencia_salida_nonce'); ?>
                    <input type="hidden" name="transferencia_id" id="editar-transf-salida-id">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Fecha:</label>
                        <input type="date" 
                               name="fecha" 
                               id="editar-transf-salida-fecha"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Descripción:</label>
                        <input type="text" 
                               name="descripcion" 
                               id="editar-transf-salida-descripcion"
                               required
                               maxlength="255"
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Valor:</label>
                        <input type="number" 
                               name="valor" 
                               id="editar-transf-salida-valor"
                               step="0.01"
                               min="0.01"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                        <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditarTransfSalida()">Cancelar</button>
                        <button type="submit" name="gofast_editar_transferencia_salida" class="gofast-btn-mini">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Abrir modal de editar transferencia salida
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.gofast-btn-editar-transf-salida').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const transfId = this.getAttribute('data-transf-id');
                    const transfFecha = this.getAttribute('data-transf-fecha');
                    const transfDescripcion = this.getAttribute('data-transf-descripcion');
                    const transfValor = this.getAttribute('data-transf-valor');
                    
                    document.getElementById('editar-transf-salida-id').value = transfId;
                    document.getElementById('editar-transf-salida-fecha').value = transfFecha;
                    document.getElementById('editar-transf-salida-descripcion').value = transfDescripcion;
                    document.getElementById('editar-transf-salida-valor').value = transfValor;
                    
                    document.getElementById('modal-editar-transf-salida').style.display = 'block';
                });
            });
            
            // Cerrar modal al hacer clic fuera
            const modalEditarTransfSalida = document.getElementById('modal-editar-transf-salida');
            if (modalEditarTransfSalida) {
                modalEditarTransfSalida.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cerrarModalEditarTransfSalida();
                    }
                });
            }
        });
        
        function cerrarModalEditarTransfSalida() {
            document.getElementById('modal-editar-transf-salida').style.display = 'none';
            document.getElementById('form-editar-transf-salida').reset();
        }
        </script>

        <!-- TAB: DESCUENTOS -->
        <div id="tab-descuentos" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'descuentos' ? 'block' : 'none' ?>;">
            <h3>➖ Descuentos a Mensajeros</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona los descuentos aplicados a mensajeros por día. Los descuentos se restan del total a pagar de cada mensajero.
            </p>

            <!-- Formulario crear descuento -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Insertar Descuento</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_crear_descuento', 'gofast_descuento_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha <span style="color: #dc3545;">*</span></label>
                            <input type="date" 
                                   name="fecha" 
                                   value="<?= esc_attr(gofast_date_today()) ?>"
                                   required
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Mensajero <span style="color: #dc3545;">*</span></label>
                            <select name="mensajero_id" id="mensajero-descuento-crear" class="gofast-select-filtro" data-placeholder="Seleccione un mensajero" required>
                                <option value="">Seleccione un mensajero</option>
                                <?php foreach ($mensajeros as $m): ?>
                                    <option value="<?= (int) $m->id; ?>"><?= esc_html($m->nombre); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor <span style="color: #dc3545;">*</span></label>
                            <input type="number" 
                                   name="valor" 
                                   step="0.01"
                                   required
                                   placeholder="Ej: 50000 o -50000"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <small style="color: #666; font-size: 11px;">Puede ser positivo (descuento) o negativo (bonificación)</small>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Descripción</label>
                            <input type="text" 
                                   name="descripcion" 
                                   maxlength="255"
                                   placeholder="Ej: Descuento por daño en vehículo"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_crear_descuento" class="gofast-btn-mini">✅ Crear Descuento</button>
                </form>
            </div>

            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                    <!-- Mantener otros parámetros GET -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_mensajero_descuentos', 'filtro_descripcion_descuentos'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div>
                        <label style="display: block; margin-bottom: 4px;">Mensajero</label>
                        <select name="filtro_mensajero_descuentos" id="filtro-mensajero-descuentos" class="gofast-select-filtro" data-placeholder="Todos los mensajeros" style="height: 38px;">
                            <option value="0">Todos</option>
                            <?php foreach ($mensajeros as $m): ?>
                                <option value="<?= (int) $m->id; ?>"<?php selected($filtro_mensajero_descuentos, $m->id); ?>>
                                    <?= esc_html($m->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 4px;">Descripción</label>
                        <input type="text" 
                               name="filtro_descripcion_descuentos" 
                               value="<?= esc_attr($filtro_descripcion_descuentos) ?>"
                               placeholder="Buscar en descripción..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; height: 38px; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 4px;">&nbsp;</label>
                        <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); width: 100%; height: 38px;">
                            🔍 Filtrar
                        </button>
                    </div>
                    <?php if ($filtro_mensajero_descuentos > 0 || !empty($filtro_descripcion_descuentos)): ?>
                        <div>
                            <label style="display: block; margin-bottom: 4px;">&nbsp;</label>
                            <a href="<?= esc_url(remove_query_arg(['filtro_mensajero_descuentos', 'filtro_descripcion_descuentos'])) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: block; text-align: center; height: 38px; line-height: 38px;">
                                🔄 Limpiar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Listado de descuentos -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Listado de Descuentos</h4>
                
                <?php if (empty($descuentos)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay descuentos registrados.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Mensajero</th>
                                    <th>Valor</th>
                                    <th>Descripción</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($descuentos as $descuento): ?>
                                    <tr>
                                        <td>#<?= esc_html($descuento->id) ?></td>
                                        <td><?= gofast_date_format($descuento->fecha, 'd/m/Y') ?></td>
                                        <td>
                                            <strong><?= esc_html($descuento->mensajero_nombre ?? '—') ?></strong><br>
                                            <small style="color: #666;"><?= esc_html($descuento->mensajero_telefono ?? '') ?></small>
                                        </td>
                                        <td><strong style="color: #dc3545;">$<?= number_format($descuento->valor, 0, ',', '.') ?></strong></td>
                                        <td><?= esc_html($descuento->descripcion ?: '—') ?></td>
                                        <td><?= esc_html($descuento->creador_nombre ?? '—') ?></td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" 
                                                    class="gofast-btn-mini gofast-btn-editar-descuento" 
                                                    data-descuento-id="<?= esc_attr($descuento->id) ?>"
                                                    data-descuento-fecha="<?= esc_attr($descuento->fecha) ?>"
                                                    data-descuento-mensajero-id="<?= esc_attr($descuento->mensajero_id) ?>"
                                                    data-descuento-valor="<?= esc_attr($descuento->valor) ?>"
                                                    data-descuento-descripcion="<?= esc_attr($descuento->descripcion) ?>"
                                                    style="background: var(--gofast-yellow); color: #000;">
                                                ✏️ Editar
                                            </button>
                                            <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar este descuento? Esta acción no se puede deshacer.');">
                                                <?php wp_nonce_field('gofast_eliminar_descuento', 'gofast_eliminar_descuento_nonce'); ?>
                                                <input type="hidden" name="descuento_id" value="<?= esc_attr($descuento->id) ?>">
                                                <button type="submit" name="gofast_eliminar_descuento" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                    🗑️ Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal para editar descuento -->
        <div id="modal-editar-descuento" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
            <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Descuento</h2>
                
                <form method="post" id="form-editar-descuento">
                    <?php wp_nonce_field('gofast_editar_descuento', 'gofast_editar_descuento_nonce'); ?>
                    <input type="hidden" name="descuento_id" id="editar-descuento-id">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Fecha:</label>
                        <input type="date" 
                               name="fecha" 
                               id="editar-descuento-fecha"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Mensajero:</label>
                        <select name="mensajero_id" id="editar-descuento-mensajero-id" class="gofast-select-filtro" data-placeholder="Seleccione un mensajero" required>
                            <option value="">Seleccione un mensajero</option>
                            <?php foreach ($mensajeros as $m): ?>
                                <option value="<?= (int) $m->id; ?>"><?= esc_html($m->nombre); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Valor:</label>
                        <input type="number" 
                               name="valor" 
                               id="editar-descuento-valor"
                               step="0.01"
                               required
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                        <small style="color: #666; font-size: 11px;">Puede ser positivo (descuento) o negativo (bonificación)</small>
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Descripción:</label>
                        <input type="text" 
                               name="descripcion" 
                               id="editar-descuento-descripcion"
                               maxlength="255"
                               style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                        <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditarDescuento()">Cancelar</button>
                        <button type="submit" name="gofast_editar_descuento" class="gofast-btn-mini">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Abrir modal de editar descuento
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.gofast-btn-editar-descuento').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const descuentoId = this.getAttribute('data-descuento-id');
                    const descuentoFecha = this.getAttribute('data-descuento-fecha');
                    const descuentoMensajeroId = this.getAttribute('data-descuento-mensajero-id');
                    const descuentoValor = this.getAttribute('data-descuento-valor');
                    const descuentoDescripcion = this.getAttribute('data-descuento-descripcion');
                    
                    document.getElementById('editar-descuento-id').value = descuentoId;
                    document.getElementById('editar-descuento-fecha').value = descuentoFecha;
                    document.getElementById('editar-descuento-mensajero-id').value = descuentoMensajeroId;
                    document.getElementById('editar-descuento-valor').value = descuentoValor;
                    document.getElementById('editar-descuento-descripcion').value = descuentoDescripcion || '';
                    
                    document.getElementById('modal-editar-descuento').style.display = 'block';
                });
            });
            
            // Cerrar modal al hacer clic fuera
            const modalEditarDescuento = document.getElementById('modal-editar-descuento');
            if (modalEditarDescuento) {
                modalEditarDescuento.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cerrarModalEditarDescuento();
                    }
                });
            }
        });
        
        function cerrarModalEditarDescuento() {
            document.getElementById('modal-editar-descuento').style.display = 'none';
            document.getElementById('form-editar-descuento').reset();
        }
        </script>

        <!-- TAB: SALDOS MENSAJEROS -->
        <div id="tab-saldos_mensajeros" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'saldos_mensajeros' ? 'block' : 'none' ?>;">
            <h3>💵 Saldos Mensajeros</h3>
            <p style="font-size:13px;color:#666;margin:8px 0 16px;">
                Gestiona los pagos a mensajeros, descuentos y estados de pago (efectivo, transferencia, pendiente).
            </p>

            <!-- Sub-tabs para Saldos Mensajeros -->
            <div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #ddd;">
                <button type="button" 
                        class="gofast-subtab-btn <?= $subtab_saldos === 'saldos_pendientes' ? 'gofast-subtab-active' : '' ?>" 
                        onclick="mostrarSubTabSaldos('saldos_pendientes', this)"
                        style="padding: 10px 20px; background: <?= $subtab_saldos === 'saldos_pendientes' ? 'var(--gofast-yellow)' : '#f0f0f0' ?>; border: none; border-radius: 6px 6px 0 0; cursor: pointer; font-weight: <?= $subtab_saldos === 'saldos_pendientes' ? '600' : '400' ?>; color: <?= $subtab_saldos === 'saldos_pendientes' ? '#000' : '#666' ?>;">
                    📊 Saldos Pendientes
                </button>
                <button type="button" 
                        class="gofast-subtab-btn <?= $subtab_saldos === 'historial_pagos' ? 'gofast-subtab-active' : '' ?>" 
                        onclick="mostrarSubTabSaldos('historial_pagos', this)"
                        style="padding: 10px 20px; background: <?= $subtab_saldos === 'historial_pagos' ? 'var(--gofast-yellow)' : '#f0f0f0' ?>; border: none; border-radius: 6px 6px 0 0; cursor: pointer; font-weight: <?= $subtab_saldos === 'historial_pagos' ? '600' : '400' ?>; color: <?= $subtab_saldos === 'historial_pagos' ? '#000' : '#666' ?>;">
                    📋 Historial de Pagos
                </button>
            </div>

            <!-- SUB-TAB: SALDOS PENDIENTES -->
            <div id="subtab-saldos_pendientes" class="gofast-subtab-content" style="display: <?= $subtab_saldos === 'saldos_pendientes' ? 'block' : 'none' ?>;">
            <!-- Filtros adicionales -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <style>
                    .filtro-saldos-row .select2-container .select2-selection--single {
                        height: 38px !important;
                        line-height: 36px;
                    }
                    .filtro-saldos-row .select2-container--default .select2-selection--single .select2-selection__rendered {
                        line-height: 38px;
                    }
                    .filtro-saldos-row .select2-container--default .select2-selection--single .select2-selection__arrow {
                        height: 36px !important;
                    }
                </style>
                <h4 style="margin-top: 0;">🔍 Filtros Adicionales</h4>
                <form method="get" action="">
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_mensajero_saldos', 'filtro_estado_saldos'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="subtab_saldos" value="<?= esc_attr($subtab_saldos) ?>">
                    
                    <div class="filtro-saldos-row" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                        <div style="width: 220px;">
                            <label style="display: block; margin-bottom: 4px;">Mensajero</label>
                            <select name="filtro_mensajero_saldos" class="gofast-select-filtro" data-placeholder="Todos los mensajeros" style="width: 100%;">
                                <option value="0">Todos</option>
                                <?php foreach ($mensajeros as $m): ?>
                                    <option value="<?= (int) $m->id; ?>"<?php selected($filtro_mensajero_saldos, $m->id); ?>>
                                        <?= esc_html($m->nombre); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="width: 220px;">
                            <label style="display: block; margin-bottom: 4px;">Estado</label>
                            <select name="filtro_estado_saldos" class="gofast-select-filtro" data-placeholder="Todos los estados" style="width: 100%;">
                                <option value="">Todos los estados</option>
                                <option value="pendiente" <?= $filtro_estado_saldos === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                <option value="efectivo" <?= $filtro_estado_saldos === 'efectivo' ? 'selected' : '' ?>>Pago en Efectivo</option>
                                <option value="transferencia" <?= $filtro_estado_saldos === 'transferencia' ? 'selected' : '' ?>>Pago por Transferencia</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); height: 38px; padding: 0 20px;">
                                🔍 Filtrar
                            </button>
                        </div>
                        <?php if ($filtro_mensajero_saldos > 0 || !empty($filtro_estado_saldos)): ?>
                            <div>
                                <a href="<?= esc_url(remove_query_arg(['filtro_mensajero_saldos', 'filtro_estado_saldos'])) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 20px;">
                                    🔄 Limpiar
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabla de saldos por mensajero -->
            <div class="gofast-box">
                <h4 style="margin-top: 0;">📋 Saldos por Mensajero <small style="font-weight: normal; color: #666;">(<?= $total_saldos_count ?> mensajeros)</small></h4>
                <p style="font-size: 12px; color: #666; margin: 8px 0 16px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                    ℹ️ <strong>Nota:</strong> Total a Pagar = Comisión - Transferencias - Descuentos - Pagos Registrados (en el rango de fechas)
                </p>
                
                <?php if (empty($saldos_mensajeros_paginados)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        No hay mensajeros con saldo pendiente en el rango de fechas seleccionado.
                    </p>
                <?php else: ?>
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>Mensajero</th>
                                    <th>💰 Comisión</th>
                                    <th>💸 Transferencias</th>
                                    <th>➖ Descuentos</th>
                                    <th>✅ Pagos Registrados</th>
                                    <th>💳 Total a Pagar</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saldos_mensajeros_paginados as $saldo): ?>
                                    <tr>
                                        <td>
                                            <strong><?= esc_html($saldo->mensajero_nombre) ?></strong><br>
                                            <small style="color: #666;"><?= esc_html($saldo->mensajero_telefono) ?></small>
                                            <?php if (!empty($saldo->desglose_dias)): ?>
                                                <button type="button" class="gofast-btn-mini btn-ver-desglose" style="background: #6c757d; color: #fff; margin-top: 4px; font-size: 10px; padding: 2px 6px;"
                                                        onclick="toggleDesglose(<?= (int) $saldo->mensajero_id ?>)">
                                                    📅 Ver días
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <strong style="color: #17a2b8;">$<?= number_format($saldo->comision_generada, 0, ',', '.') ?></strong>
                                        </td>
                                        <td style="text-align: right;">$<?= number_format($saldo->transferencias_aprobadas, 0, ',', '.') ?></td>
                                        <td style="text-align: right;">
                                            <strong style="color: #dc3545;">$<?= number_format($saldo->total_descuentos, 0, ',', '.') ?></strong>
                                        </td>
                                        <td style="text-align: right;">
                                            <strong style="color: #28a745;">$<?= number_format($saldo->total_pagos_rango ?? 0, 0, ',', '.') ?></strong>
                                        </td>
                                        <td style="text-align: right;">
                                            <strong style="color: <?= $saldo->total_a_pagar >= 0 ? '#4CAF50' : '#f44336'; ?>; font-size: 16px;">
                                                $<?= number_format($saldo->total_a_pagar, 0, ',', '.') ?>
                                            </strong>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <button type="button" 
                                                    class="gofast-btn-mini btn-pago-efectivo" 
                                                    style="background: #28a745; color: #fff; margin-right: 4px; margin-bottom: 4px;"
                                                    data-mensajero-id="<?= (int) $saldo->mensajero_id ?>" 
                                                    data-mensajero-nombre="<?= esc_attr($saldo->mensajero_nombre ?? '') ?>"
                                                    data-comision="<?= (float)($saldo->comision_generada ?? 0) ?>"
                                                    data-transferencias="<?= (float)($saldo->transferencias_aprobadas ?? 0) ?>"
                                                    data-descuentos="<?= (float)($saldo->total_descuentos ?? 0) ?>"
                                                    data-total="<?= (float)($saldo->total_a_pagar ?? 0) ?>">
                                                💵 Pago Efectivo
                                            </button>
                                            <button type="button" 
                                                    class="gofast-btn-mini btn-pago-transferencia" 
                                                    style="background: #2196F3; color: #fff; margin-right: 4px; margin-bottom: 4px;"
                                                    data-mensajero-id="<?= (int) $saldo->mensajero_id ?>"
                                                    data-mensajero-nombre="<?= esc_attr($saldo->mensajero_nombre ?? '') ?>"
                                                    data-comision="<?= (float)($saldo->comision_generada ?? 0) ?>"
                                                    data-transferencias="<?= (float)($saldo->transferencias_aprobadas ?? 0) ?>"
                                                    data-descuentos="<?= (float)($saldo->total_descuentos ?? 0) ?>"
                                                    data-total="<?= (float)($saldo->total_a_pagar ?? 0) ?>">
                                                💸 Pago Transferencia
                                            </button>
                                        </td>
                                    </tr>
                                    <?php if (!empty($saldo->desglose_dias)): ?>
                                    <?php
                                    $total_dias_pendientes = count($saldo->desglose_dias);
                                    $suma_pendiente_dias = array_sum(array_column($saldo->desglose_dias, 'pendiente'));
                                    $primer_dia = $saldo->desglose_dias[0]->fecha ?? '';
                                    $ultimo_dia = end($saldo->desglose_dias)->fecha ?? '';
                                    ?>
                                    <tr id="desglose-<?= (int) $saldo->mensajero_id ?>" class="desglose-row" style="display: none;">
                                        <td colspan="7" style="background: #f8f9fa; padding: 8px;">
                                            <!-- Resumen compacto -->
                                            <div class="desglose-resumen">
                                                <div class="desglose-info">
                                                    <strong>📅 <?= $total_dias_pendientes ?> día<?= $total_dias_pendientes > 1 ? 's' : '' ?></strong>
                                                    <span class="desglose-fechas">
                                                        <?php if ($total_dias_pendientes > 1): ?>
                                                            <?= date('d/m', strtotime($primer_dia)) ?> - <?= date('d/m', strtotime($ultimo_dia)) ?>
                                                        <?php else: ?>
                                                            <?= date('d/m/Y', strtotime($primer_dia)) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="desglose-total">
                                                    <span class="desglose-monto">$<?= number_format($suma_pendiente_dias, 0, ',', '.') ?></span>
                                                </div>
                                                <button type="button" onclick="toggleDetalleDias(<?= (int) $saldo->mensajero_id ?>)" class="desglose-toggle-btn">
                                                    📋 <?= $total_dias_pendientes > 3 ? 'Ver días' : 'Detalle' ?>
                                                </button>
                                            </div>

                                            <!-- Detalle por días -->
                                            <div id="detalle-dias-<?= (int) $saldo->mensajero_id ?>" class="detalle-dias-container" style="display: none;">
                                                <div class="detalle-dias-scroll">
                                                    <?php foreach ($saldo->desglose_dias as $dia): ?>
                                                    <div class="dia-item">
                                                        <div class="dia-fecha">
                                                            <strong><?= date('d/m', strtotime($dia->fecha)) ?></strong>
                                                            <span><?= strftime('%a', strtotime($dia->fecha)) ?></span>
                                                        </div>
                                                        <div class="dia-detalle">
                                                            <div class="dia-linea">
                                                                <span class="dia-label">Comisión:</span>
                                                                <span class="dia-valor">$<?= number_format($dia->comision, 0, ',', '.') ?></span>
                                                            </div>
                                                            <?php if ($dia->transferencias > 0): ?>
                                                            <div class="dia-linea">
                                                                <span class="dia-label">Transferencias:</span>
                                                                <span class="dia-valor dia-transf">-$<?= number_format($dia->transferencias, 0, ',', '.') ?></span>
                                                            </div>
                                                            <?php endif; ?>
                                                            <?php if ($dia->descuentos > 0): ?>
                                                            <div class="dia-linea">
                                                                <span class="dia-label">Descuentos:</span>
                                                                <span class="dia-valor dia-desc">-$<?= number_format($dia->descuentos, 0, ',', '.') ?></span>
                                                            </div>
                                                            <?php endif; ?>
                                                            <div class="dia-linea dia-total">
                                                                <span class="dia-label">Pendiente:</span>
                                                                <span class="dia-valor dia-pendiente">$<?= number_format($dia->pendiente, 0, ',', '.') ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="dia-acciones">
                                                            <button type="button" class="btn-pago-dia btn-efectivo-mini"
                                                                    data-mensajero-id="<?= (int) $saldo->mensajero_id ?>"
                                                                    data-mensajero-nombre="<?= esc_attr($saldo->mensajero_nombre ?? '') ?>"
                                                                    data-fecha="<?= esc_attr($dia->fecha) ?>"
                                                                    data-comision="<?= (float)$dia->comision ?>"
                                                                    data-transferencias="<?= (float)$dia->transferencias ?>"
                                                                    data-descuentos="<?= (float)$dia->descuentos ?>"
                                                                    data-total="<?= (float)$dia->pendiente ?>"
                                                                    data-tipo="efectivo">💵 Efectivo</button>
                                                            <button type="button" class="btn-pago-dia btn-transf-mini"
                                                                    data-mensajero-id="<?= (int) $saldo->mensajero_id ?>"
                                                                    data-mensajero-nombre="<?= esc_attr($saldo->mensajero_nombre ?? '') ?>"
                                                                    data-fecha="<?= esc_attr($dia->fecha) ?>"
                                                                    data-comision="<?= (float)$dia->comision ?>"
                                                                    data-transferencias="<?= (float)$dia->transferencias ?>"
                                                                    data-descuentos="<?= (float)$dia->descuentos ?>"
                                                                    data-total="<?= (float)$dia->pendiente ?>"
                                                                    data-tipo="transferencia">💸 Transf</button>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <?php if ($total_paginas_saldos > 1): ?>
                        <div class="gofast-pagination" style="margin-top: 20px; display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
                            <?php
                            $url_base = remove_query_arg('pg_saldos');
                            for ($i = 1; $i <= $total_paginas_saldos; $i++):
                            ?>
                                <a href="<?= esc_url(add_query_arg('pg_saldos', $i, $url_base)) ?>" 
                                   class="gofast-page-link <?= $i === $pg_saldos ? 'gofast-page-current' : '' ?>"
                                   style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: <?= $i === $pg_saldos ? '#000' : '#333' ?>; background: <?= $i === $pg_saldos ? 'var(--gofast-yellow, #F4C524)' : '#fff' ?>; font-weight: <?= $i === $pg_saldos ? '700' : '400' ?>;">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            </div>
            <!-- FIN SUB-TAB: SALDOS PENDIENTES -->

            <!-- SUB-TAB: HISTORIAL DE PAGOS -->
            <div id="subtab-historial_pagos" class="gofast-subtab-content" style="display: <?= $subtab_saldos === 'historial_pagos' ? 'block' : 'none' ?>;">
                <!-- Filtro por mensajero para historial de pagos -->
                <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">🔍 Filtrar por Mensajero</h4>
                    <form method="get" action="" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: end;">
                        <!-- Mantener otros parámetros GET -->
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if (!in_array($key, ['filtro_mensajero_historial'])): ?>
                                <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <input type="hidden" name="tab" value="saldos_mensajeros">
                        <input type="hidden" name="subtab_saldos" value="historial_pagos">
                        
                        <div style="min-width: 200px;">
                            <label style="display: block; margin-bottom: 4px;">Mensajero</label>
                            <select name="filtro_mensajero_historial" class="gofast-select-filtro" data-placeholder="Todos los mensajeros" style="width: 100%; height: 38px;">
                                <option value="0">Todos</option>
                                <?php foreach ($mensajeros as $m): ?>
                                    <option value="<?= (int) $m->id; ?>"<?php selected($filtro_mensajero_historial, $m->id); ?>>
                                        <?= esc_html($m->nombre); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); height: 38px; padding: 0 20px;">
                                🔍 Filtrar
                            </button>
                        </div>
                        <?php if ($filtro_mensajero_historial > 0): ?>
                            <div>
                                <a href="<?= esc_url(add_query_arg(['tab' => 'saldos_mensajeros', 'subtab_saldos' => 'historial_pagos'], remove_query_arg(['filtro_mensajero_historial']))) ?>" class="gofast-btn gofast-secondary" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 20px;">
                                    🔄 Limpiar
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                    <p style="margin: 12px 0 0; font-size: 12px; color: #666;">
                        ℹ️ El rango de fechas se controla con los filtros globales en la parte superior.
                    </p>
                </div>

                <div class="gofast-box">
                    <h4 style="margin-top: 0;">📋 Historial de Pagos Registrados</h4>
                    <p style="font-size: 12px; color: #666; margin: 8px 0 16px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                        ℹ️ <strong>Nota:</strong> Se muestran todos los pagos registrados a mensajeros (efectivo y transferencia).
                    </p>
                    
                    <?php if (empty($pagos_mensajeros_paginados)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">
                            No hay pagos registrados.
                        </p>
                    <?php else: ?>
                        <div class="gofast-table-wrap">
                            <table class="gofast-table">
                                <thead>
                                    <tr>
                                        <th>Fecha Pago</th>
                                        <th>Mensajero</th>
                                        <th>Tipo</th>
                                        <th>Total Pagado</th>
                                        <th>Registrado por</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagos_mensajeros_paginados as $pago): ?>
                                        <tr>
                                            <td>
                                                <strong><?= gofast_date_format($pago->fecha, 'd/m/Y') ?></strong><br>
                                                <?php if ($pago->fecha_pago): ?>
                                                    <small style="color: #666;">
                                                        <?= gofast_date_format($pago->fecha_pago, 'H:i') ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= esc_html($pago->mensajero_nombre ?? 'N/A') ?></strong><br>
                                                <?php if ($pago->mensajero_telefono): ?>
                                                    <small style="color: #666;"><?= esc_html($pago->mensajero_telefono) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="color: <?= $pago->tipo_pago === 'efectivo' ? '#28a745' : '#2196F3'; ?>; font-weight: 600;">
                                                    <?= $pago->tipo_pago === 'efectivo' ? '💵 Efectivo' : '💸 Transferencia' ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right;">
                                                <strong style="color: #4CAF50; font-size: 16px;">
                                                    $<?= number_format((float)$pago->total_a_pagar, 0, ',', '.') ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <small><?= esc_html($pago->creador_nombre ?? 'N/A') ?></small>
                                            </td>
                                            <td style="white-space: nowrap;">
                                                <button type="button" 
                                                        class="gofast-btn-mini btn-editar-pago"
                                                        style="background: #ffc107; color: #000; margin-right: 4px;"
                                                        data-pago-id="<?= (int)$pago->id ?>"
                                                        data-fecha="<?= esc_attr($pago->fecha) ?>"
                                                        data-tipo="<?= esc_attr($pago->tipo_pago) ?>"
                                                        data-total="<?= (float)$pago->total_a_pagar ?>"
                                                        data-mensajero="<?= esc_attr($pago->mensajero_nombre ?? '') ?>">
                                                    ✏️ Editar
                                                </button>
                                                <button type="button"
                                                        class="gofast-btn-mini btn-eliminar-pago"
                                                        style="background: #dc3545; color: #fff;"
                                                        data-pago-id="<?= (int)$pago->id ?>"
                                                        data-mensajero="<?= esc_attr($pago->mensajero_nombre ?? '') ?>"
                                                        data-total="<?= (float)$pago->total_a_pagar ?>">
                                                    🗑️ Eliminar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación historial -->
                        <?php if ($total_paginas_historial > 1): ?>
                            <div class="gofast-pagination" style="margin-top: 20px; display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
                                <?php
                                $url_base_historial = remove_query_arg('pg_historial');
                                for ($i = 1; $i <= $total_paginas_historial; $i++):
                                ?>
                                    <a href="<?= esc_url(add_query_arg('pg_historial', $i, $url_base_historial)) ?>" 
                                       class="gofast-page-link <?= $i === $pg_historial ? 'gofast-page-current' : '' ?>"
                                       style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: <?= $i === $pg_historial ? '#000' : '#333' ?>; background: <?= $i === $pg_historial ? 'var(--gofast-yellow, #F4C524)' : '#fff' ?>; font-weight: <?= $i === $pg_historial ? '700' : '400' ?>;">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- FIN SUB-TAB: HISTORIAL DE PAGOS -->
        </div>

    </div>

    <!-- Botón de descarga al final de la página -->
    <div class="gofast-box" style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; margin-top: 20px;">
        <a href="<?php echo esc_url( home_url('/reportes-financieros?fecha_desde=' . $fecha_desde . '&fecha_hasta=' . $fecha_hasta) ); ?>" class="gofast-btn" style="text-decoration:none;white-space:nowrap;background:#6f42c1;color:#fff;">
            📊 Descargar Reportes
        </a>
    </div>
</div>

<!-- Modal Editar Pago -->
<div id="modal-editar-pago" class="gofast-modal" style="display: none;">
    <div class="gofast-modal-content" style="max-width: 420px;">
        <div class="gofast-modal-header" style="border-bottom-color: #ffc107;">
            <h3 style="color: #333;"><span style="margin-right: 8px;">✏️</span>Editar Pago</h3>
            <span class="gofast-modal-close" onclick="cerrarModalEditarPago()">&times;</span>
        </div>
        <div class="gofast-modal-body">
            <p id="editar-pago-info" style="font-size: 14px; color: #666; margin-bottom: 16px;"></p>
            
            <div style="margin-bottom: 12px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 600;">Fecha:</label>
                <input type="date" name="fecha" id="editar-pago-fecha" form="form-editar-pago" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            
            <div style="margin-bottom: 12px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 600;">Tipo de Pago:</label>
                <select name="tipo_pago" id="editar-pago-tipo" form="form-editar-pago" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                    <option value="efectivo">💵 Efectivo</option>
                    <option value="transferencia">💸 Transferencia</option>
                </select>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 600;">Total Pagado:</label>
                <input type="number" name="total_a_pagar" id="editar-pago-total" form="form-editar-pago" required min="0" step="1" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
        </div>
        <div class="gofast-modal-footer">
            <form id="form-editar-pago" method="post" action="" style="display: flex; gap: 12px; width: 100%;">
                <?php wp_nonce_field('gofast_editar_pago_mensajero', 'gofast_editar_pago_nonce'); ?>
                <input type="hidden" name="pago_id" id="editar-pago-id">
                <input type="hidden" name="tab" value="saldos_mensajeros">
                <input type="hidden" name="subtab_saldos" value="historial_pagos">
                
                <button type="submit" name="gofast_editar_pago_mensajero" class="gofast-btn" style="flex: 1; background: #ffc107; color: #000; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    💾 Guardar Cambios
                </button>
                <button type="button" onclick="cerrarModalEditarPago()" class="gofast-btn" style="flex: 1; background: #6c757d; color: #fff; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Cancelar
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Eliminar Pago -->
<div id="modal-eliminar-pago" class="gofast-modal" style="display: none;">
    <div class="gofast-modal-content" style="max-width: 420px;">
        <div class="gofast-modal-header" style="border-bottom-color: #dc3545;">
            <h3 style="color: #dc3545;"><span style="margin-right: 8px;">🗑️</span>Eliminar Pago</h3>
            <span class="gofast-modal-close" onclick="cerrarModalEliminarPago()">&times;</span>
        </div>
        <div class="gofast-modal-body">
            <p id="eliminar-pago-info" style="font-size: 14px; color: #666; margin-bottom: 16px;"></p>
            <p style="color: #dc3545; font-weight: 600; margin: 0;">¿Estás seguro de que deseas eliminar este pago? Esta acción no se puede deshacer.</p>
        </div>
        <div class="gofast-modal-footer">
            <form method="post" action="" style="display: flex; gap: 12px; width: 100%;">
                <?php wp_nonce_field('gofast_eliminar_pago_mensajero', 'gofast_eliminar_pago_nonce'); ?>
                <input type="hidden" name="pago_id" id="eliminar-pago-id">
                <input type="hidden" name="tab" value="saldos_mensajeros">
                <input type="hidden" name="subtab_saldos" value="historial_pagos">
                
                <button type="submit" name="gofast_eliminar_pago_mensajero" class="gofast-btn" style="flex: 1; background: #dc3545; color: #fff; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    🗑️ Sí, Eliminar
                </button>
                <button type="button" onclick="cerrarModalEliminarPago()" class="gofast-btn" style="flex: 1; background: #6c757d; color: #fff; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Cancelar
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Estilos CSS para el modal -->
<style>
/* Estilos para alinear Select2 con otros campos */
.select2-container--default .select2-selection--single {
    height: 38px !important;
    padding: 4px 8px !important;
    border: 1px solid #ddd !important;
    border-radius: 6px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 28px !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
}

/* Overlay oscuro de fondo - oculto por defecto */
.gofast-modal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0, 0, 0, 0.6) !important;
    z-index: 999999 !important;
    align-items: center !important;
    justify-content: center !important;
    backdrop-filter: blur(4px);
    display: none !important; /* Oculto por defecto */
}

/* Solo mostrar flex cuando el modal tenga display: block */
.gofast-modal[style*="display: block"] {
    display: flex !important;
}

/* Contenedor del modal */
.gofast-modal-content {
    position: relative !important;
    background: #fff !important;
    border-radius: 12px !important;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3) !important;
    width: 90% !important;
    max-width: 500px !important;
    max-height: 90vh !important;
    overflow-y: auto !important;
    animation: gofastModalSlideIn 0.3s ease-out;
}

@keyframes gofastModalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Header del modal */
.gofast-modal-header {
    padding: 20px 24px !important;
    border-bottom: 2px solid #f0f0f0 !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
}

.gofast-modal-header h3 {
    margin: 0 !important;
    font-size: 20px !important;
    font-weight: 700 !important;
    color: #333 !important;
}

.gofast-modal-close {
    font-size: 28px !important;
    font-weight: 300 !important;
    color: #999 !important;
    cursor: pointer !important;
    line-height: 1 !important;
    transition: color 0.2s;
}

.gofast-modal-close:hover {
    color: #333 !important;
}

/* Body del modal */
.gofast-modal-body {
    padding: 24px !important;
}

/* Footer del modal */
.gofast-modal-footer {
    padding: 16px 24px !important;
    border-top: 2px solid #f0f0f0 !important;
    display: flex !important;
    justify-content: flex-end !important;
    gap: 12px !important;
}

.gofast-modal-footer .gofast-btn {
    padding: 10px 20px !important;
    border-radius: 6px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    border: none !important;
    transition: all 0.2s !important;
}

.gofast-modal-footer .gofast-btn-secondary {
    background: #6c757d !important;
    color: #fff !important;
}

.gofast-modal-footer .gofast-btn-secondary:hover {
    background: #5a6268 !important;
}

/* Estilos para desglose de días - Mobile First */
.desglose-resumen {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.desglose-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.desglose-info strong {
    font-size: 13px;
}

.desglose-fechas {
    font-size: 11px;
    color: #666;
}

.desglose-total {
    text-align: right;
}

.desglose-monto {
    font-size: 18px;
    font-weight: 700;
    color: #dc3545;
}

.desglose-toggle-btn {
    background: #6c757d;
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
    white-space: nowrap;
}

.detalle-dias-container {
    margin-top: 8px;
}

.detalle-dias-scroll {
    max-height: 250px;
    overflow-y: auto;
    background: #fff;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
}

.dia-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 10px;
    border-bottom: 1px solid #f0f0f0;
    gap: 8px;
}

.dia-item:last-child {
    border-bottom: none;
}

.dia-fecha {
    display: flex;
    flex-direction: column;
    min-width: 45px;
}

.dia-fecha strong {
    font-size: 12px;
    color: #333;
}

.dia-fecha span {
    font-size: 9px;
    color: #999;
    text-transform: uppercase;
}

.dia-detalle {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 0 10px;
}

.dia-linea {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
}

.dia-label {
    color: #666;
}

.dia-valor {
    font-weight: 500;
    color: #333;
}

.dia-transf {
    color: #2196F3;
}

.dia-desc {
    color: #ff9800;
}

.dia-total {
    border-top: 1px dashed #ddd;
    padding-top: 3px;
    margin-top: 2px;
}

.dia-pendiente {
    font-size: 13px;
    font-weight: 700;
    color: #dc3545;
}

.dia-acciones {
    display: flex;
    gap: 4px;
}

.btn-efectivo-mini,
.btn-transf-mini {
    border: none;
    padding: 6px 8px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    line-height: 1;
}

.btn-efectivo-mini {
    background: #28a745;
    color: #fff;
}

.btn-transf-mini {
    background: #2196F3;
    color: #fff;
}

/* Responsive para móvil */
@media (max-width: 480px) {
    .desglose-resumen {
        padding: 8px;
    }
    
    .desglose-monto {
        font-size: 16px;
    }
    
    .dia-item {
        padding: 6px 8px;
    }
    
    .dia-fecha strong {
        font-size: 11px;
    }
    
    .dia-pendiente {
        font-size: 12px;
    }
    
    .btn-efectivo-mini,
    .btn-transf-mini {
        padding: 5px 6px;
        font-size: 11px;
    }
}
</style>

<!-- Modal para registrar pago -->
<div id="modal-registrar-pago" class="gofast-modal" style="display: none;">
    <div class="gofast-modal-content" style="max-width: 500px;">
        <div class="gofast-modal-header">
            <h3 id="modal-pago-titulo">Registrar Pago</h3>
            <span class="gofast-modal-close" onclick="cerrarModalPago()">&times;</span>
        </div>
        <form id="form-registrar-pago" method="post">
            <?php wp_nonce_field('gofast_registrar_pago_mensajero', 'gofast_pago_mensajero_nonce'); ?>
            <input type="hidden" name="gofast_registrar_pago_mensajero" value="1">
            <input type="hidden" name="mensajero_id" id="pago-mensajero-id">
            <input type="hidden" name="tipo_pago" id="pago-tipo">
            <input type="hidden" name="comision_total" id="pago-comision">
            <input type="hidden" name="transferencias_total" id="pago-transferencias">
            <input type="hidden" name="descuentos_total" id="pago-descuentos">
            <input type="hidden" name="total_a_pagar" id="pago-total">
            
            <div class="gofast-modal-body">
                <!-- Contenido unificado para ambos tipos de pago -->
                <div style="text-align: center; margin-bottom: 24px;">
                    <div id="pago-icono-tipo" style="font-size: 48px; margin-bottom: 16px;">💵</div>
                    <h3 id="pago-titulo-tipo" style="margin: 0 0 8px 0; color: #333;">Confirmar Pago</h3>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #666;">Mensajero</label>
                        <div style="padding: 12px; background: #fff; border-radius: 6px; font-weight: 600; font-size: 16px; text-align: center; color: #000;" id="pago-mensajero-nombre"></div>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #666;">Tipo de Pago</label>
                        <div style="padding: 12px; background: #fff; border-radius: 6px; font-weight: 600; font-size: 16px; text-align: center;" id="pago-tipo-display"></div>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #666;">Fecha de Pago <span style="color: #dc3545;">*</span></label>
                        <input type="date" 
                               name="fecha" 
                               id="pago-fecha" 
                               required
                               value="<?= gofast_date_today() ?>"
                               style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px;">
                    </div>
                    
                    <div style="padding-top: 16px; border-top: 2px solid #ddd;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #666;">Valor a Pagar</label>
                        <div style="padding: 8px; background: #fff; border-radius: 6px; text-align: center;">
                            <div id="pago-total-sugerido" style="font-size: 12px; color: #666; margin-bottom: 8px;">Sugerido: <span id="pago-total-display">$0</span></div>
                            <input type="number" 
                                   name="total_a_pagar_input" 
                                   id="pago-total-input" 
                                   min="0" 
                                   step="100"
                                   required
                                   style="width: 100%; padding: 12px; border: 2px solid #28a745; border-radius: 6px; font-size: 24px; font-weight: 700; text-align: center; color: #28a745;">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="gofast-modal-footer">
                <button type="button" class="gofast-btn gofast-secondary" onclick="cerrarModalPago()">Cancelar</button>
                <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow);">💾 Registrar Pago</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para ver pagos registrados -->
<div id="modal-ver-pagos" class="gofast-modal" style="display: none;">
    <div class="gofast-modal-content" style="max-width: 900px;">
        <div class="gofast-modal-header">
            <h3 id="modal-pagos-titulo">Pagos Registrados</h3>
            <span class="gofast-modal-close" onclick="cerrarModalVerPagos()">&times;</span>
        </div>
        <div class="gofast-modal-body">
            <div id="pagos-mensajero-nombre" style="font-size: 18px; font-weight: 600; margin-bottom: 20px; padding: 12px; background: #f8f9fa; border-radius: 6px;"></div>
            <div id="pagos-lista" style="max-height: 500px; overflow-y: auto;">
                <!-- Los pagos se cargarán aquí dinámicamente -->
            </div>
        </div>
        <div class="gofast-modal-footer">
            <button type="button" class="gofast-btn gofast-secondary" onclick="cerrarModalVerPagos()">Cerrar</button>
        </div>
    </div>
</div>

<script>
// Datos de pagos disponibles en JavaScript
var pagosPorMensajero = <?= json_encode($pagos_por_mensajero) ?>;
// Función para abrir modal de pago
function abrirModalPago(tipoPago, mensajeroId, mensajeroNombre, comision, transferencias, descuentos, total, fechaPago) {
    var modal = document.getElementById('modal-registrar-pago');

    if (!modal) {
        console.error('Modal no encontrado');
        return;
    }

    // Limpiar valores anteriores y establecer nuevos
    document.getElementById('pago-mensajero-id').value = mensajeroId || '';
    document.getElementById('pago-tipo').value = tipoPago || '';
    document.getElementById('pago-comision').value = comision || '0';
    document.getElementById('pago-transferencias').value = transferencias || '0';
    document.getElementById('pago-descuentos').value = descuentos || '0';
    document.getElementById('pago-total').value = total || '0';

    // Establecer fecha: si viene fechaPago, usar esa; si no, fecha actual
    var fechaInput = document.getElementById('pago-fecha');
    if (fechaInput) {
        if (fechaPago) {
            fechaInput.value = fechaPago;
        } else {
            var today = new Date();
            var year = today.getFullYear();
            var month = String(today.getMonth() + 1).padStart(2, '0');
            var day = String(today.getDate()).padStart(2, '0');
            fechaInput.value = year + '-' + month + '-' + day;
        }
    }
    
    // Función para convertir string a número de forma segura
    var convertirANumero = function(str) {
        if (str === null || str === undefined) return 0;
        // Convertir a string y limpiar
        var strLimpio = String(str).trim();
        if (strLimpio === '' || strLimpio === 'null' || strLimpio === 'undefined') return 0;
        // Remover comas de miles y espacios
        strLimpio = strLimpio.replace(/,/g, '').replace(/\s/g, '');
        // Convertir a número
        var num = parseFloat(strLimpio);
        return isNaN(num) ? 0 : num;
    };
    
    // Convertir valor total a número de forma segura
    var totalNum = convertirANumero(String(total || '0'));
    
    // Obtener elementos del modal unificado
    var nombreDisplay = document.getElementById('pago-mensajero-nombre');
    var tipoDisplay = document.getElementById('pago-tipo-display');
    var totalDisplay = document.getElementById('pago-total-display');
    var iconoTipo = document.getElementById('pago-icono-tipo');
    var tituloTipo = document.getElementById('pago-titulo-tipo');
    var tituloModal = document.getElementById('modal-pago-titulo');
    
    // Asegurar que el nombre se muestre correctamente
    var nombreMostrar = String(mensajeroNombre || '').trim();
    if (nombreDisplay) {
        nombreDisplay.textContent = nombreMostrar || 'Sin nombre';
    }
    
    // Configurar tipo de pago y visualización
    if (tipoPago === 'efectivo') {
        if (tipoDisplay) {
            tipoDisplay.textContent = '💵 Pago en Efectivo';
            tipoDisplay.style.color = '#28a745';
        }
        if (iconoTipo) iconoTipo.textContent = '💵';
        if (tituloTipo) tituloTipo.textContent = 'Confirmar Pago en Efectivo';
        if (tituloModal) tituloModal.textContent = 'Registrar Pago en Efectivo';
    } else {
        if (tipoDisplay) {
            tipoDisplay.textContent = '💸 Pago por Transferencia';
            tipoDisplay.style.color = '#2196F3';
        }
        if (iconoTipo) iconoTipo.textContent = '💸';
        if (tituloTipo) tituloTipo.textContent = 'Confirmar Pago por Transferencia';
        if (tituloModal) tituloModal.textContent = 'Registrar Pago por Transferencia';
    }
    
    // Mostrar valor total sugerido
    if (totalDisplay) {
        var colorTotal = totalNum >= 0 ? '#28a745' : '#f44336';
        totalDisplay.textContent = '$' + Math.abs(totalNum).toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0});
        totalDisplay.style.color = colorTotal;
    }
    
    // Establecer valor en el input editable
    var totalInput = document.getElementById('pago-total-input');
    if (totalInput) {
        totalInput.value = Math.abs(totalNum);
    }

    // Mostrar el modal usando style.display = 'block' para que el CSS lo detecte
    modal.style.display = 'block';
}

// Función para cerrar modal de pago
function cerrarModalPago() {
    document.getElementById('modal-registrar-pago').style.display = 'none';
    document.getElementById('form-registrar-pago').reset();
}

// Sincronizar input editable con hidden field (se llama desde DOMContentLoaded principal)
function inicializarSincronizacionPago() {
    var formPago = document.getElementById('form-registrar-pago');
    if (formPago) {
        formPago.addEventListener('submit', function(e) {
            var inputEditable = document.getElementById('pago-total-input');
            var hiddenTotal = document.getElementById('pago-total');
            if (inputEditable && hiddenTotal) {
                hiddenTotal.value = inputEditable.value;
            }
        });
    }
    
    // También sincronizar cuando cambie el input
    var inputEditable = document.getElementById('pago-total-input');
    if (inputEditable) {
        inputEditable.addEventListener('input', function() {
            var hiddenTotal = document.getElementById('pago-total');
            if (hiddenTotal) {
                hiddenTotal.value = this.value;
            }
        });
    }
}

// Funciones para editar pago
function abrirModalEditarPago(pagoId, fecha, tipo, total, mensajero) {
    document.getElementById('editar-pago-id').value = pagoId;
    document.getElementById('editar-pago-fecha').value = fecha;
    document.getElementById('editar-pago-tipo').value = tipo;
    document.getElementById('editar-pago-total').value = total;
    document.getElementById('editar-pago-info').innerHTML = 'Editando pago de <strong>' + mensajero + '</strong>';
    document.getElementById('modal-editar-pago').style.display = 'block';
}

function cerrarModalEditarPago() {
    document.getElementById('modal-editar-pago').style.display = 'none';
}

// Funciones para eliminar pago
function abrirModalEliminarPago(pagoId, mensajero, total) {
    document.getElementById('eliminar-pago-id').value = pagoId;
    document.getElementById('eliminar-pago-info').innerHTML = 'Pago a <strong>' + mensajero + '</strong> por <strong style="color:#4CAF50;">$' + Number(total).toLocaleString('es-CO') + '</strong>';
    document.getElementById('modal-eliminar-pago').style.display = 'block';
}

function cerrarModalEliminarPago() {
    document.getElementById('modal-eliminar-pago').style.display = 'none';
}

// Función para mostrar/ocultar desglose por día
function toggleDesglose(mensajeroId) {
    var row = document.getElementById('desglose-' + mensajeroId);
    if (row) {
        row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
    }
}

// Función para mostrar/ocultar detalle de días
function toggleDetalleDias(mensajeroId) {
    var detalle = document.getElementById('detalle-dias-' + mensajeroId);
    if (detalle) {
        detalle.style.display = detalle.style.display === 'none' ? 'block' : 'none';
    }
}

// Función para abrir modal de ver pagos
function abrirModalVerPagos(mensajeroId, mensajeroNombre) {
    var modal = document.getElementById('modal-ver-pagos');
    if (!modal) {
        console.error('Modal de pagos no encontrado');
        return;
    }
    
    // Establecer nombre del mensajero
    document.getElementById('pagos-mensajero-nombre').textContent = 'Pagos de: ' + (mensajeroNombre || 'Sin nombre');
    
    // Obtener pagos del mensajero
    var pagos = pagosPorMensajero[mensajeroId] || [];
    var listaPagos = document.getElementById('pagos-lista');
    
    if (pagos.length === 0) {
        listaPagos.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No hay pagos registrados para este mensajero.</p>';
    } else {
        var html = '<table class="gofast-table" style="width: 100%;">';
        html += '<thead><tr>';
        html += '<th>Fecha</th>';
        html += '<th>Tipo</th>';
        html += '<th>Comisión</th>';
        html += '<th>Transferencias</th>';
        html += '<th>Descuentos</th>';
        html += '<th>Total Pagado</th>';
        html += '<th>Registrado por</th>';
        html += '</tr></thead><tbody>';
        
        pagos.forEach(function(pago) {
            var tipoColor = pago.tipo_pago === 'efectivo' ? '#28a745' : '#2196F3';
            var tipoIcono = pago.tipo_pago === 'efectivo' ? '💵' : '💸';
            
            // Formatear fecha (formato YYYY-MM-DD)
            var fechaFormato = '-';
            if (pago.fecha) {
                try {
                    var fechaParts = pago.fecha.split('-');
                    if (fechaParts.length === 3) {
                        fechaFormato = fechaParts[2] + '/' + fechaParts[1] + '/' + fechaParts[0];
                    }
                } catch(e) {
                    fechaFormato = pago.fecha;
                }
            }
            
            // Formatear fecha_pago (formato datetime YYYY-MM-DD HH:MM:SS)
            var fechaPagoFormato = '-';
            if (pago.fecha_pago) {
                try {
                    var fechaPagoParts = pago.fecha_pago.split(' ');
                    if (fechaPagoParts.length === 2) {
                        var fecha = fechaPagoParts[0].split('-');
                        var hora = fechaPagoParts[1].substring(0, 5); // HH:MM
                        fechaPagoFormato = fecha[2] + '/' + fecha[1] + '/' + fecha[0] + ' ' + hora;
                    } else {
                        fechaPagoFormato = pago.fecha_pago;
                    }
                } catch(e) {
                    fechaPagoFormato = pago.fecha_pago;
                }
            }
            
            html += '<tr>';
            html += '<td><strong>' + fechaFormato + '</strong><br><small style="color: #666;">' + fechaPagoFormato + '</small></td>';
            html += '<td><span style="color: ' + tipoColor + '; font-weight: 600;">' + tipoIcono + ' ' + (pago.tipo_pago === 'efectivo' ? 'Efectivo' : 'Transferencia') + '</span></td>';
            html += '<td style="text-align: right;">$' + parseFloat(pago.comision_total || 0).toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + '</td>';
            html += '<td style="text-align: right;">$' + parseFloat(pago.transferencias_total || 0).toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + '</td>';
            html += '<td style="text-align: right; color: #dc3545;">$' + parseFloat(pago.descuentos_total || 0).toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + '</td>';
            html += '<td style="text-align: right;"><strong style="color: #4CAF50; font-size: 16px;">$' + parseFloat(pago.total_a_pagar || 0).toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + '</strong></td>';
            html += '<td><small>' + (pago.creador_nombre || '-') + '</small></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        listaPagos.innerHTML = html;
    }
    
    // Mostrar el modal
    modal.style.display = 'block';
}

// Función para cerrar modal de ver pagos
function cerrarModalVerPagos() {
    document.getElementById('modal-ver-pagos').style.display = 'none';
}

// Asegurar que las funciones normalize y matcherDestinos estén disponibles para Select2
if (typeof window.normalize === 'undefined') {
    window.normalize = function(s) {
        return (s || "")
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    };
}

if (typeof window.matcherDestinos === 'undefined') {
    window.matcherDestinos = function(params, data) {
        if (!data) return null;
        if (data.children && Array.isArray(data.children)) return data;
        if (!data.id) {
            if (!params.term || !params.term.trim()) return data;
            return null;
        }
        if (!data.text) return null;
        if (!params.term || !params.term.trim()) {
            data.matchScore = 0;
            return data;
        }
        const term = window.normalize(params.term);
        if (!term) {
            data.matchScore = 0;
            return data;
        }
        const text = window.normalize(data.text);
        if (text === term) {
            data.matchScore = 10000;
            return data;
        }
        if (text.indexOf(term) === 0) {
            data.matchScore = 9500;
            return data;
        }
        if (text.indexOf(term) !== -1) {
            data.matchScore = 7000;
            return data;
        }
        const searchWords = term.split(/\s+/).filter(Boolean);
        const significantMatches = searchWords.filter(word => text.indexOf(word) !== -1);
        if (significantMatches.length === 0) return null;
        data.matchScore = significantMatches.length === searchWords.length ? 5000 : 3000;
        return data;
    };
}

// Función para inicializar Select2 en todos los filtros (igual que en reportes y pedidos)
function inicializarSelect2Filtros() {
    if (window.jQuery && jQuery.fn.select2 && typeof window.matcherDestinos === 'function' && typeof window.normalize === 'function') {
        jQuery('.gofast-select-filtro').each(function() {
            if (jQuery(this).data('select2')) {
                return;
            }
            
            jQuery(this).select2({
                placeholder: function() {
                    return jQuery(this).data('placeholder') || '🔍 Escribe para buscar...';
                },
                width: '100%',
                allowClear: false,
                minimumResultsForSearch: 0,
                matcher: window.matcherDestinos,
                sorter: function(results) {
                    return results.sort(function(a, b) {
                        return (b.matchScore || 0) - (a.matchScore || 0);
                    });
                },
                templateResult: function(data, container) {
                    if (!data || !data.text) {
                        return data ? data.text : '';
                    }
                    
                    if (!data.id) return data.text;
                    
                    let originalText = data.text;
                    let searchTerm = "";
                    const $activeField = jQuery('.select2-container--open .select2-search__field');
                    if ($activeField.length) {
                        searchTerm = $activeField.val() || "";
                    }
                    
                    if (!searchTerm || !searchTerm.trim()) {
                        const $result = jQuery('<span>').text(originalText);
                        if (data.matchScore !== undefined) {
                            $result.attr('data-match-score', data.matchScore);
                        }
                        return $result;
                    }
                    
                    const normalizedSearch = window.normalize(searchTerm);
                    const normalizedText = window.normalize(originalText);
                    const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
                    const searchWords = normalizedSearch.split(/\s+/).filter(Boolean).filter(word => {
                        return word.length > 2 && !stopWords.includes(word);
                    });
                    const wordsToHighlight = searchWords.length > 0 ? searchWords : [normalizedSearch];
                    const highlightRanges = [];
                    
                    wordsToHighlight.forEach(function(word) {
                        let searchPos = 0;
                        while ((searchPos = normalizedText.indexOf(word, searchPos)) !== -1) {
                            const endPos = searchPos + word.length;
                            let origStart = -1;
                            let origEnd = -1;
                            let normPos = 0;
                            
                            for (let i = 0; i < originalText.length && origStart === -1; i++) {
                                const charNorm = window.normalize(originalText[i]);
                                if (normPos === searchPos) {
                                    origStart = i;
                                }
                                normPos += charNorm.length;
                            }
                            
                            if (origStart >= 0) {
                                normPos = searchPos;
                                for (let i = origStart; i < originalText.length; i++) {
                                    const charNorm = window.normalize(originalText[i]);
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
                        
                        const parts = [];
                        let lastIndex = 0;
                        
                        mergedRanges.forEach(function(range) {
                            if (range.start > lastIndex) {
                                parts.push(originalText.substring(lastIndex, range.start));
                            }
                            
                            const matchText = originalText.substring(range.start, range.end);
                            parts.push('<span style="background-color:#F4C524;color:#000;font-weight:bold;padding:1px 2px;">' + 
                                       matchText + '</span>');
                            
                            lastIndex = range.end;
                        });
                        
                        if (lastIndex < originalText.length) {
                            parts.push(originalText.substring(lastIndex));
                        }
                        
                        const result = parts.join('');
                        const $result = jQuery('<span>').html(result);
                        if (data.matchScore !== undefined) {
                            $result.attr('data-match-score', data.matchScore);
                        }
                        return $result;
                    }
                    
                    const $result = jQuery('<span>').text(originalText);
                    if (data.matchScore !== undefined) {
                        $result.attr('data-match-score', data.matchScore);
                    }
                    return $result;
                }
            }).on('select2:open', function(e) {
                setTimeout(function() {
                    const $dropdown = jQuery('.select2-dropdown');
                    const $searchContainer = $dropdown.find('.select2-search--dropdown');
                    const $searchField = $searchContainer.find('.select2-search__field');
                    
                    if ($searchContainer.length) {
                        $searchContainer.css({
                            'display': 'block',
                            'visibility': 'visible',
                            'opacity': '1'
                        });
                    }
                    
                    if ($searchField.length) {
                        $searchField.css({
                            'display': 'block',
                            'visibility': 'visible',
                            'opacity': '1'
                        });
                        
                        setTimeout(function() {
                            $searchField.focus();
                        }, 100);
                    }
                }, 50);
            });
        });
        
        // Ocultar select original cuando Select2 está activo
        jQuery('.gofast-select-filtro').each(function() {
            if (jQuery(this).data('select2')) {
                jQuery(this).css({
                    'visibility': 'hidden',
                    'position': 'absolute',
                    'width': '1px',
                    'height': '1px',
                    'opacity': '0',
                    'pointer-events': 'none'
                });
            }
        });
    } else {
        // Reintentar después de un breve delay
        setTimeout(function() {
            if (window.jQuery && jQuery.fn.select2 && typeof window.matcherDestinos === 'function' && typeof window.normalize === 'function') {
                inicializarSelect2Filtros();
            }
        }, 500);
    }
}

    // Asegurar que al enviar el formulario de filtros, se preserve el tab activo
    document.addEventListener('DOMContentLoaded', function() {
        const formFiltros = document.getElementById('form-filtros-generales');
        if (formFiltros) {
            formFiltros.addEventListener('submit', function() {
                // El tab ya está en el input hidden, pero nos aseguramos de que esté actualizado
                const urlParams = new URLSearchParams(window.location.search);
                const tabActivo = urlParams.get('tab') || 'ingresos';
                const filtroTabActivo = document.getElementById('filtro-tab-activo');
                if (filtroTabActivo) {
                    filtroTabActivo.value = tabActivo;
                }
            });
        }
        
        // Los tabs ahora usan onclick directamente en el HTML, no necesitan event listeners adicionales
        
        // Agregar event listeners a los botones de pago
        document.querySelectorAll('.btn-pago-efectivo').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var mensajeroId = this.getAttribute('data-mensajero-id') || '';
                var mensajeroNombre = this.getAttribute('data-mensajero-nombre') || '';
                var comision = this.getAttribute('data-comision') || '0';
                var transferencias = this.getAttribute('data-transferencias') || '0';
                var descuentos = this.getAttribute('data-descuentos') || '0';
                var total = this.getAttribute('data-total') || '0';
                
                // Debug: verificar que los datos se estén leyendo
                console.log('Datos del botón:', {
                    mensajeroId: mensajeroId,
                    mensajeroNombre: mensajeroNombre,
                    comision: comision,
                    transferencias: transferencias,
                    descuentos: descuentos,
                    total: total
                });
                
                abrirModalPago(
                    'efectivo',
                    mensajeroId,
                    mensajeroNombre,
                    comision,
                    transferencias,
                    descuentos,
                    total
                );
            });
        });
        
        document.querySelectorAll('.btn-pago-transferencia').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var mensajeroId = this.getAttribute('data-mensajero-id') || '';
                var mensajeroNombre = this.getAttribute('data-mensajero-nombre') || '';
                var comision = this.getAttribute('data-comision') || '0';
                var transferencias = this.getAttribute('data-transferencias') || '0';
                var descuentos = this.getAttribute('data-descuentos') || '0';
                var total = this.getAttribute('data-total') || '0';
                
                abrirModalPago(
                    'transferencia',
                    mensajeroId,
                    mensajeroNombre,
                    comision,
                    transferencias,
                    descuentos,
                    total
                );
            });
        });
        
        // Botones de pago por día (efectivo o transferencia) - usando delegación de eventos
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.btn-pago-dia');
            if (!btn) return;
            
            e.preventDefault();
            e.stopPropagation();

            var mensajeroId = btn.getAttribute('data-mensajero-id') || '';
            var mensajeroNombre = btn.getAttribute('data-mensajero-nombre') || '';
            var fecha = btn.getAttribute('data-fecha') || '';
            var comision = btn.getAttribute('data-comision') || '0';
            var transferencias = btn.getAttribute('data-transferencias') || '0';
            var descuentos = btn.getAttribute('data-descuentos') || '0';
            var total = btn.getAttribute('data-total') || '0';
            var tipoPago = btn.getAttribute('data-tipo') || 'efectivo';

            abrirModalPago(
                tipoPago,
                mensajeroId,
                mensajeroNombre,
                comision,
                transferencias,
                descuentos,
                total,
                fecha
            );
        });
        
        // Cerrar modal al hacer clic fuera
        var modalRegistrarPago = document.getElementById('modal-registrar-pago');
        if (modalRegistrarPago) {
            modalRegistrarPago.addEventListener('click', function(e) {
                if (e.target === this) {
                    cerrarModalPago();
                }
            });
        }
        
        var modalVerPagos = document.getElementById('modal-ver-pagos');
        if (modalVerPagos) {
            modalVerPagos.addEventListener('click', function(e) {
                if (e.target === this) {
                    cerrarModalVerPagos();
                }
            });
        }
        
        // Botones editar pago
        document.querySelectorAll('.btn-editar-pago').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var pagoId = this.getAttribute('data-pago-id');
                var fecha = this.getAttribute('data-fecha');
                var tipo = this.getAttribute('data-tipo');
                var total = this.getAttribute('data-total');
                var mensajero = this.getAttribute('data-mensajero');
                abrirModalEditarPago(pagoId, fecha, tipo, total, mensajero);
            });
        });
        
        // Botones eliminar pago
        document.querySelectorAll('.btn-eliminar-pago').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var pagoId = this.getAttribute('data-pago-id');
                var mensajero = this.getAttribute('data-mensajero');
                var total = this.getAttribute('data-total');
                abrirModalEliminarPago(pagoId, mensajero, total);
            });
        });
        
        // Cerrar modales al hacer clic fuera
        var modalEditarPago = document.getElementById('modal-editar-pago');
        if (modalEditarPago) {
            modalEditarPago.addEventListener('click', function(e) {
                if (e.target === this) cerrarModalEditarPago();
            });
        }
        var modalEliminarPago = document.getElementById('modal-eliminar-pago');
        if (modalEliminarPago) {
            modalEliminarPago.addEventListener('click', function(e) {
                if (e.target === this) cerrarModalEliminarPago();
            });
        }
        
        // Inicializar Select2 para todos los filtros con clase gofast-select-filtro (igual que en reportes y pedidos)
        inicializarSelect2Filtros();
        
        // Inicializar sincronización del input de pago
        inicializarSincronizacionPago();
        
        // Reintentar después de un breve delay si no se inicializó
        setTimeout(function() {
            inicializarSelect2Filtros();
        }, 500);
    });
</script>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_finanzas_admin', 'gofast_finanzas_admin_shortcode');


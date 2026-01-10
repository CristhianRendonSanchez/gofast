/*******************************************************
 * GOFAST – LISTADO DE PEDIDOS (CLIENTE / ADMIN / MENSAJERO)
 * Shortcode: [gofast_pedidos]
 *******************************************************/

/**
 * Función helper para generar mensaje de WhatsApp para mensajeros
 */
function gofast_generar_mensaje_whatsapp_mensajero($pedido, $wpdb) {
    if (empty($pedido->destinos)) {
        return '';
    }
    
    $json = json_decode($pedido->destinos, true);
    if (!is_array($json)) {
        return '';
    }
    
    $destinos = $json['destinos'] ?? [];
    $origen_data = $json['origen'] ?? [];
    
    // Detectar si es servicio intermunicipal
    $es_intermunicipal = !empty($json['tipo_servicio']) && $json['tipo_servicio'] === 'intermunicipal';
    
    // Obtener datos del origen
    $direccion_recogida = $pedido->direccion_origen;
    if (!empty($origen_data['direccion']) && $origen_data['direccion'] !== $pedido->direccion_origen) {
        $direccion_recogida = $origen_data['direccion'];
    }
    
    // Cargar recargos para calcular totales
    $recargos_fijos = $wpdb->get_results("SELECT id, nombre, valor_fijo FROM recargos WHERE activo = 1 AND tipo = 'fijo'");
    $recargo_fijo_por_envio = 0;
    foreach ((array) $recargos_fijos as $r) {
        $monto = intval($r->valor_fijo);
        if ($monto > 0) {
            $recargo_fijo_por_envio += $monto;
        }
    }
    
    $recargos_variables = $wpdb->get_results("SELECT r.id, r.nombre, rr.monto_min, rr.monto_max, rr.recargo FROM recargos r JOIN recargos_rangos rr ON rr.recargo_id = r.id WHERE r.activo = 1 AND r.tipo = 'por_valor' ORDER BY rr.monto_min ASC");
    $calcular_recargos_variables = function($valor) use ($recargos_variables) {
        $valor = intval($valor);
        $total_variable = 0;
        foreach ((array) $recargos_variables as $r) {
            $min = intval($r->monto_min);
            $max = intval($r->monto_max);
            $rec = intval($r->recargo);
            $cumple_min = ($valor >= $min);
            $cumple_max = ($max <= 0) ? true : ($valor <= $max);
            if ($cumple_min && $cumple_max && $rec > 0) {
                $total_variable += $rec;
            }
        }
        return $total_variable;
    };
    
    // Calcular total
    $total_para_mensaje = ($pedido->total > 0) ? $pedido->total : 0;
    
    if ($es_intermunicipal) {
        $dir_destino_inter = !empty($destinos[0]['direccion']) ? trim($destinos[0]['direccion']) : '';
        $barrio_destino_inter = !empty($destinos[0]['barrio_nombre']) ? trim($destinos[0]['barrio_nombre']) : '';
        $whatsapp_destino_inter = !empty($destinos[0]['whatsapp']) ? trim($destinos[0]['whatsapp']) : '';
        $destino_display_inter = $dir_destino_inter ?: ($barrio_destino_inter ?: 'No especificado');
        
        $mensaje = "🚚 Servicio INTERMUNICIPAL #{$pedido->id}\n\n" .
            "📦 Servicio: #{$pedido->id}\n" .
            "📍 Recogida: " . ($direccion_recogida ?: 'No especificada') . "\n" .
            "👤 Cliente: " . ($pedido->nombre_cliente ?: 'No especificado') . "\n" .
            "📞 Contacto: " . ($pedido->telefono_cliente ?: 'No especificado') . "\n\n" .
            "📍 Destino: $destino_display_inter\n";
        
        if ($barrio_destino_inter && $barrio_destino_inter !== $dir_destino_inter && $dir_destino_inter) {
            $mensaje .= "🏙 Barrio: $barrio_destino_inter\n";
        }
        
        if (!empty($whatsapp_destino_inter)) {
            $mensaje .= "📱 WhatsApp destino: $whatsapp_destino_inter\n";
        }
        
        $mensaje .= "\n💲 Total: $" . number_format($total_para_mensaje, 0, ',', '.');
    } else {
        // Mensaje para servicios normales (múltiples destinos posibles) - formato idéntico a confirmacion.php
        $mensaje = "🚀 Servicio #{$pedido->id}\n\n";
        $mensaje .= "📦 Servicio: #{$pedido->id}\n";
        $mensaje .= "📍 Recogida: " . ($direccion_recogida ?: 'No especificada') . "\n";
        $mensaje .= "👤 Envía: " . ($pedido->nombre_cliente ?: 'No especificado') . "\n";
        $mensaje .= "📞 Contacto: " . ($pedido->telefono_cliente ?: 'No especificado') . "\n\n";
        
        // Agregar información de cada destino con su dirección, monto adicional, valor del envío y total
        if (!empty($destinos)) {
            foreach ($destinos as $idx => $dest) {
                $dir_destino = !empty($dest['direccion']) ? trim($dest['direccion']) : '';
                $barrio_destino = !empty($dest['barrio_nombre']) ? trim($dest['barrio_nombre']) : '';
                $whatsapp_destino = !empty($dest['whatsapp']) ? trim($dest['whatsapp']) : '';
                
                // Calcular valores para este destino
                $precio_base = 0;
                if (!empty($origen_data['sector_id']) && !empty($dest['sector_id'])) {
                    $precio = $wpdb->get_var($wpdb->prepare(
                        "SELECT precio FROM tarifas WHERE origen_sector_id=%d AND destino_sector_id=%d",
                        intval($origen_data['sector_id']),
                        intval($dest['sector_id'])
                    ));
                    $precio_base = $precio ? intval($precio) : 0;
                }
                
                $recargo_variable = $calcular_recargos_variables($precio_base);
                $recargo_automatico = $recargo_fijo_por_envio + $recargo_variable;
                $recargo_seleccionable_valor = !empty($dest['recargo_seleccionable_valor']) ? intval($dest['recargo_seleccionable_valor']) : 0;
                
                // Monto adicional manual (lo que el cliente ingresó)
                $monto_adicional = !empty($dest['monto']) ? intval($dest['monto']) : 0;
                
                // Valor del envío (domicilio: precio base + recargos automáticos + recargo seleccionable)
                $valor_envio = $precio_base + $recargo_automatico + $recargo_seleccionable_valor;
                
                // Total a pagar (monto adicional + valor del envío)
                $total_destino = $monto_adicional + $valor_envio;
                
                if ($idx > 0) {
                    $mensaje .= "\n";
                }
                
                $mensaje .= "📍 Destino " . ($idx + 1) . ":\n";
                
                // Dirección: usar dirección si existe, sino el barrio
                $destino_display = $dir_destino ?: ($barrio_destino ?: 'No especificado');
                $mensaje .= "   📍 Dirección: $destino_display\n";
                
                // Barrio: solo mostrar si es diferente de la dirección
                if ($barrio_destino && $barrio_destino !== $dir_destino && $dir_destino) {
                    $mensaje .= "   🏙 Barrio: $barrio_destino\n";
                }
                
                // WhatsApp de destino: mostrar solo si existe
                if (!empty($whatsapp_destino)) {
                    $mensaje .= "   📱 WhatsApp: $whatsapp_destino\n";
                }
                
                // Monto adicional (lo que el cliente ingresó manualmente)
                $mensaje .= "   💰 Monto: $" . number_format($monto_adicional, 0, ',', '.') . "\n";
                
                // Valor del envío (domicilio)
                $mensaje .= "   💸 Valor del envío: $" . number_format($valor_envio, 0, ',', '.') . "\n";
                
                // Total a pagar (monto + valor del envío)
                $mensaje .= "\n💲 Total a pagar: $" . number_format($total_destino, 0, ',', '.') . "\n";
            }
        }
    }
    
    return $mensaje;
}

function gofast_pedidos_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión para ver tus pedidos.</div>";
    }

    $user_id = (int) $_SESSION['gofast_user_id'];
    $rol     = strtolower($_SESSION['gofast_user_rol'] ?? 'cliente');

    $mensaje_estado = '';
    
    // Detectar modo edición de servicio
    $editar_servicio_id = isset($_GET['editar_servicio']) ? (int) $_GET['editar_servicio'] : 0;

    // Lista de mensajeros (solo para admin)
    $mensajeros = [];
    // Lista de barrios para filtros (solo para admin)
    $barrios = [];
    // Lista de usuarios con negocios (solo para admin)
    $usuarios_negocios = [];
    
    if ($rol === 'admin') {
        // Mensajeros para filtros (incluye deshabilitados para ver pedidos históricos)
        $mensajeros = $wpdb->get_results("
            SELECT id, nombre 
            FROM usuarios_gofast
            WHERE rol = 'mensajero'
            ORDER BY nombre ASC
        ");
        
        // Mensajeros para asignación (solo activos)
        $mensajeros_asignacion = $wpdb->get_results("
            SELECT id, nombre 
            FROM usuarios_gofast
            WHERE rol = 'mensajero' AND activo = 1
            ORDER BY nombre ASC
        ");
        
        $barrios = $wpdb->get_results("
            SELECT id, nombre, sector_id 
            FROM barrios
            ORDER BY nombre ASC
        ");
        
        // Obtener negocios (no usuarios) para el filtro
        $negocios = $wpdb->get_results("
            SELECT DISTINCT n.id, n.nombre, n.user_id
            FROM negocios_gofast n
            WHERE n.activo = 1
            ORDER BY n.nombre ASC
        ");
    }

    /****************************************
     * 0. GESTIÓN DE ELIMINAR SERVICIO (POST)
     ****************************************/
    if (
        !empty($_POST['gofast_eliminar_id']) &&
        !empty($_POST['gofast_eliminar_nonce']) &&
        wp_verify_nonce($_POST['gofast_eliminar_nonce'], 'gofast_eliminar_servicio') &&
        $rol === 'admin'
    ) {
        $servicio_id = (int) $_POST['gofast_eliminar_id'];
        $eliminado = $wpdb->delete('servicios_gofast', ['id' => $servicio_id]);
        if ($eliminado) {
            $mensaje_estado = 'Servicio eliminado correctamente.';
        } else {
            $mensaje_estado = 'Error al eliminar el servicio.';
        }
    }

    /****************************************
     * 0.1. GESTIÓN DE EDITAR DESTINOS (POST)
     ****************************************/
    if (
        (!empty($_POST['gofast_editar_destinos']) || !empty($_POST['gofast_editar_destinos_id'])) &&
        !empty($_POST['gofast_editar_destinos_nonce']) &&
        wp_verify_nonce($_POST['gofast_editar_destinos_nonce'], 'gofast_editar_destinos') &&
        $rol === 'admin'
    ) {
        $servicio_id = !empty($_POST['gofast_editar_destinos_id']) ? (int) $_POST['gofast_editar_destinos_id'] : 0;
        
        if ($servicio_id > 0) {
            $servicio = $wpdb->get_row($wpdb->prepare("SELECT * FROM servicios_gofast WHERE id = %d", $servicio_id));
            
            if ($servicio) {
                $json_destinos = json_decode($servicio->destinos, true);
                
                if (is_array($json_destinos) && !empty($_POST['destinos_editados'])) {
                    $destinos_editados_json = stripslashes($_POST['destinos_editados']);
                    $destinos_editados = json_decode($destinos_editados_json, true);
                    
                    // Verificar errores de JSON
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $mensaje_estado = 'Error al decodificar JSON de destinos: ' . json_last_error_msg() . '. JSON recibido: ' . substr($destinos_editados_json, 0, 200);
                    } elseif (is_array($destinos_editados) && !empty($destinos_editados['destinos'])) {
                    // Verificar si se editó el origen
                    $sector_origen = !empty($json_destinos['origen']['sector_id']) 
                        ? (int) $json_destinos['origen']['sector_id'] 
                        : 0;
                    
                    if (!empty($destinos_editados['origen'])) {
                        $origen_editado = $destinos_editados['origen'];
                        $nuevo_origen_barrio_id = (int) ($origen_editado['barrio_id'] ?? 0);
                        
                        if ($nuevo_origen_barrio_id > 0) {
                            // Obtener datos del nuevo barrio de origen
                            $nuevo_origen_data = $wpdb->get_row($wpdb->prepare(
                                "SELECT id, nombre, sector_id FROM barrios WHERE id = %d", $nuevo_origen_barrio_id
                            ));
                            
                            if ($nuevo_origen_data) {
                                $sector_origen = (int) $nuevo_origen_data->sector_id;
                                $json_destinos['origen'] = [
                                    'barrio_id' => $nuevo_origen_barrio_id,
                                    'barrio_nombre' => $nuevo_origen_data->nombre,
                                    'sector_id' => $sector_origen,
                                    'direccion' => $origen_editado['direccion'] ?? ''
                                ];
                            }
                        }
                    }
                    
                    // Obtener destinos originales para conservar recargos
                    $destinos_originales = $json_destinos['destinos'] ?? [];
                    
                    // Obtener recargo global del servicio si existe
                    $recargo_global_servicio = $json_destinos['recargo_global'] ?? null;
                    $recargo_global_valor_servicio = isset($json_destinos['recargo_global_valor']) ? (int)$json_destinos['recargo_global_valor'] : 0;
                    
                    // Recalcular total
                    $total_nuevo = 0;
                    $destinos_finales = [];
                    
                    foreach ($destinos_editados['destinos'] as $idx => $dest) {
                        if (!empty($dest['barrio_id'])) {
                            $barrio_id = (int) $dest['barrio_id'];
                            $sector_destino = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT sector_id FROM barrios WHERE id = %d", $barrio_id
                            ));
                            
                            $precio = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT precio FROM tarifas WHERE origen_sector_id=%d AND destino_sector_id=%d",
                                $sector_origen, $sector_destino
                            ));
                            
                            // Monto adicional: solo se guarda para los mensajes, NO se suma al total
                            $monto_extra = isset($dest['monto']) ? (int) $dest['monto'] : 0;
                            
                            // Buscar recargos del destino original por índice
                            $orig = isset($destinos_originales[$idx]) ? $destinos_originales[$idx] : null;
                            
                            // Extraer todos los campos de recargo del destino original
                            $recargo_seleccionable_id = $orig['recargo_seleccionable_id'] ?? null;
                            $recargo_seleccionable_nombre = $orig['recargo_seleccionable_nombre'] ?? null;
                            $recargo_seleccionable_valor = (int)($orig['recargo_seleccionable_valor'] ?? 0);
                            $recargo_total_auto = (int)($orig['recargo_total'] ?? 0);
                            $recargo_global = $orig['recargo_global'] ?? null;
                            $recargo_global_valor = (int)($orig['recargo_global_valor'] ?? 0);
                            $recargos_detalle = $orig['recargos_detalle'] ?? [];
                            $recargo_seleccionable = $orig['recargo_seleccionable'] ?? null;
                            
                            // Sumar al total: precio base + recargos (NO incluir monto adicional)
                            $total_nuevo += $precio + $recargo_seleccionable_valor + $recargo_total_auto + $recargo_global_valor;
                            
                            // Construir destino final conservando TODOS los campos originales de recargos
                            $destino_final = [
                                'barrio_id' => $barrio_id,
                                'barrio_nombre' => $dest['barrio_nombre'] ?? '',
                                'sector_id' => $sector_destino,
                                'direccion' => $dest['direccion'] ?? '',
                                'monto' => $monto_extra
                            ];
                            
                            // Conservar TODOS los campos de recargos del destino original
                            if ($recargo_seleccionable_id !== null) {
                                $destino_final['recargo_seleccionable_id'] = $recargo_seleccionable_id;
                            }
                            if ($recargo_seleccionable_nombre !== null) {
                                $destino_final['recargo_seleccionable_nombre'] = $recargo_seleccionable_nombre;
                            }
                            if ($recargo_seleccionable !== null) {
                                $destino_final['recargo_seleccionable'] = $recargo_seleccionable;
                            }
                            if ($recargo_seleccionable_valor > 0) {
                                $destino_final['recargo_seleccionable_valor'] = $recargo_seleccionable_valor;
                            }
                            if ($recargo_total_auto > 0) {
                                $destino_final['recargo_total'] = $recargo_total_auto;
                            }
                            if ($recargo_global !== null) {
                                $destino_final['recargo_global'] = $recargo_global;
                            }
                            if ($recargo_global_valor > 0) {
                                $destino_final['recargo_global_valor'] = $recargo_global_valor;
                            }
                            if (!empty($recargos_detalle)) {
                                $destino_final['recargos_detalle'] = $recargos_detalle;
                            }
                            
                            $destinos_finales[] = $destino_final;
                        }
                    }
                    
                    // Conservar recargo global del servicio si existe
                    if ($recargo_global_servicio !== null) {
                        $json_destinos['recargo_global'] = $recargo_global_servicio;
                    }
                    if ($recargo_global_valor_servicio > 0) {
                        $json_destinos['recargo_global_valor'] = $recargo_global_valor_servicio;
                        $total_nuevo += $recargo_global_valor_servicio;
                    }
                    
                    $json_destinos['destinos'] = $destinos_finales;
                    $json_final = json_encode($json_destinos, JSON_UNESCAPED_UNICODE);
                    
                        $actualizado = $wpdb->update(
                            'servicios_gofast',
                            [
                                'destinos' => $json_final,
                                'total' => $total_nuevo
                            ],
                            ['id' => $servicio_id],
                            ['%s', '%d'],
                            ['%d']
                        );
                        
                        if ($actualizado !== false) {
                            $mensaje_estado = '✅ Destinos actualizados correctamente.';
                        } else {
                            $mensaje_estado = '❌ Error al actualizar destinos: ' . esc_html($wpdb->last_error);
                        }
                    } else {
                        $mensaje_estado = 'Error: Los destinos editados no tienen el formato correcto.';
                    }
                } else {
                    $mensaje_estado = 'Error: No se recibieron destinos editados.';
                }
            } else {
                $mensaje_estado = 'Error: Servicio no encontrado.';
            }
        } else {
            $mensaje_estado = 'Error: ID de servicio inválido.';
        }
    }

    /****************************************
     * 0.2. GESTIÓN DE CAMBIO DE ESTADO / MENSAJERO (POST)
     ****************************************/
    if (
        !empty($_POST['gofast_estado_id']) &&
        !empty($_POST['gofast_estado_nonce']) &&
        wp_verify_nonce($_POST['gofast_estado_nonce'], 'gofast_cambiar_estado')
    ) {
        $pedido_id = (int) $_POST['gofast_estado_id'];

        $pedido = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM servicios_gofast WHERE id = %d", $pedido_id)
        );

        if (!$pedido) {
            $mensaje_estado = 'Pedido no encontrado.';
        } else {
            $estados_validos = ['pendiente','asignado','en_ruta','entregado','cancelado'];

            // Estado nuevo (si viene en POST, si no, se deja igual)
            $nuevo_estado = $pedido->tracking_estado;
            if (!empty($_POST['gofast_estado_nuevo'])) {
                $tmp_estado = sanitize_text_field($_POST['gofast_estado_nuevo']);
                if (in_array($tmp_estado, $estados_validos, true)) {
                    $nuevo_estado = $tmp_estado;
                } else {
                    $mensaje_estado = 'Estado no válido.';
                }
            }

            // Mensajero nuevo
            $nuevo_mensajero_id = $pedido->mensajero_id;
            $asignado_por_user_id = null; // Quién asignó el mensajero

            if ($rol === 'admin') {
                // Admin puede asignar cualquier mensajero
                if (isset($_POST['gofast_mensajero_id'])) {
                    $tmp_m = (int) $_POST['gofast_mensajero_id'];
                    $nuevo_mensajero_id = $tmp_m > 0 ? $tmp_m : null;
                    // Si se está asignando un mensajero (no quitándolo), guardar quién lo asignó
                    if ($nuevo_mensajero_id > 0 && $nuevo_mensajero_id != $pedido->mensajero_id) {
                        $asignado_por_user_id = $user_id; // Admin asignó
                    }
                }
            } elseif ($rol === 'mensajero') {
                // Mensajero se auto-asigna si el pedido no tiene mensajero y está tocando el estado
                if (empty($pedido->mensajero_id) && !empty($_POST['gofast_estado_nuevo'])) {
                    $nuevo_mensajero_id = $user_id;
                    $asignado_por_user_id = $user_id; // Mensajero se auto-asignó
                }
            }

            // Permisos
            $puede = false;
            if ($rol === 'admin') {
                $puede = true;
            } elseif ($rol === 'mensajero') {
                // puede tocar pendientes o los suyos
                if ($pedido->tracking_estado === 'pendiente' || (int) $pedido->mensajero_id === $user_id) {
                    $puede = true;
                }
                // Mensajero NO puede cancelar
                if ($nuevo_estado === 'cancelado') {
                    $puede = false;
                    $mensaje_estado = 'Los mensajeros no pueden cancelar pedidos.';
                }
            } else {
                $puede = false;
            }

            if (!$puede) {
                $mensaje_estado = 'No tienes permisos para modificar este pedido.';
            } else {
                $data = [];

                if ($nuevo_estado !== $pedido->tracking_estado) {
                    $data['tracking_estado'] = $nuevo_estado;
                }

                // Comparación con null/int
                if ((string) $nuevo_mensajero_id !== (string) $pedido->mensajero_id) {
                    $data['mensajero_id'] = $nuevo_mensajero_id;
                    // Guardar quién asignó el mensajero (si se está asignando)
                    if ($asignado_por_user_id !== null && $nuevo_mensajero_id > 0) {
                        // Verificar si el campo existe, si no, intentar agregarlo
                        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'");
                        if (empty($column_exists)) {
                            // Agregar el campo si no existe
                            $wpdb->query("ALTER TABLE servicios_gofast ADD COLUMN asignado_por_user_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER mensajero_id");
                        }
                        $data['asignado_por_user_id'] = $asignado_por_user_id;
                    } elseif ($nuevo_mensajero_id === null || $nuevo_mensajero_id == 0) {
                        // Si se quita el mensajero, limpiar quién lo asignó
                        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'");
                        if (!empty($column_exists)) {
                            $data['asignado_por_user_id'] = null;
                        }
                    }
                }

                if (!empty($data)) {
                    $ok = $wpdb->update('servicios_gofast', $data, ['id' => $pedido_id]);
                    if ($ok === false) {
                        $mensaje_estado = 'Error al actualizar: ' . esc_html($wpdb->last_error);
                    } else {
                        $mensaje_estado = 'Pedido actualizado correctamente.';
                    }
                }
            }
        }
    }

    /****************************************
     * 1. Filtros (GET)
     ****************************************/
    $estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
    $buscar = isset($_GET['q'])      ? sanitize_text_field($_GET['q'])      : '';
    $desde  = isset($_GET['desde'])  ? sanitize_text_field($_GET['desde'])  : '';
    $hasta  = isset($_GET['hasta'])  ? sanitize_text_field($_GET['hasta'])  : '';
    
    // Filtros adicionales solo para admin
    $filtro_mensajero = isset($_GET['filtro_mensajero']) ? (int) $_GET['filtro_mensajero'] : 0;
    $filtro_sin_mensajero = isset($_GET['filtro_sin_mensajero']) ? sanitize_text_field($_GET['filtro_sin_mensajero']) : '';
    $filtro_origen = isset($_GET['filtro_origen']) ? (int) $_GET['filtro_origen'] : 0;
    $filtro_destino = isset($_GET['filtro_destino']) ? (int) $_GET['filtro_destino'] : 0;
    $filtro_negocio = isset($_GET['filtro_negocio']) ? (int) $_GET['filtro_negocio'] : 0;
    $filtro_asignado_por = isset($_GET['filtro_asignado_por']) ? sanitize_text_field($_GET['filtro_asignado_por']) : '';
    $filtro_intermunicipal = isset($_GET['filtro_intermunicipal']) ? sanitize_text_field($_GET['filtro_intermunicipal']) : '';
    $filtro_recargos = isset($_GET['filtro_recargos']) ? sanitize_text_field($_GET['filtro_recargos']) : '';

    // Predefinir fecha al día actual si no hay filtros de fecha (para todos los usuarios)
    // Usar zona horaria de Colombia
    if (empty($_GET['desde']) && empty($_GET['hasta'])) {
        $desde = gofast_current_time('Y-m-d');
        $hasta = gofast_current_time('Y-m-d');
    }

    if ($desde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = '';
    if ($hasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = '';

    /****************************************
     * 2. WHERE según rol
     ****************************************/
    $where  = "1=1";
    $params = [];

    if ($rol === 'admin') {
        // Admin ve todo (incluyendo pedidos sin asignar)
    } elseif ($rol === 'mensajero') {
        // Mensajero solo ve pedidos asignados a él (NO ve pedidos sin asignar)
        $where   .= " AND mensajero_id = %d";
        $params[] = $user_id;
    } else {
        // Cliente: ver servicios que creó O servicios de sus negocios
        // Obtener IDs de negocios del cliente
        $negocios_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM negocios_gofast WHERE user_id = %d AND activo = 1",
            $user_id
        ));
        
        // Construir condición: servicios creados por el cliente O servicios de sus negocios
        // Como los servicios se asocian por user_id (dueño del negocio), y el cliente es dueño de sus negocios,
        // los servicios de sus negocios también tendrán user_id = cliente_id
        // Pero por si acaso hay alguna otra relación, mantenemos el filtro simple
        $where   .= " AND user_id = %d";
        $params[] = $user_id;
    }

    if ($estado !== '' && $estado !== 'todos') {
        $where   .= " AND tracking_estado = %s";
        $params[] = $estado;
    }

    // búsqueda por nombre o teléfono
    if ($buscar !== '') {
        $like = '%' . $wpdb->esc_like($buscar) . '%';
        $where   .= " AND (nombre_cliente LIKE %s OR telefono_cliente LIKE %s)";
        $params[] = $like;
        $params[] = $like;
    }

    if ($desde !== '') {
        $where   .= " AND fecha >= %s";
        $params[] = $desde . ' 00:00:00';
    }
    if ($hasta !== '') {
        $where   .= " AND fecha <= %s";
        $params[] = $hasta . ' 23:59:59';
    }
    
    // Filtros adicionales solo para admin
    if ($rol === 'admin') {
        // Filtro por pedidos sin mensajero asignado (tiene prioridad sobre filtro_mensajero)
        if ($filtro_sin_mensajero === 'si') {
            $where .= " AND (mensajero_id IS NULL OR mensajero_id = 0)";
        } elseif ($filtro_mensajero > 0) {
            // Filtro por mensajero específico (solo si no se está filtrando por "sin mensajero")
            $where   .= " AND mensajero_id = %d";
            $params[] = $filtro_mensajero;
        }
        
        if ($filtro_negocio > 0) {
            // Buscar servicios donde el user_id corresponde al dueño del negocio
            $negocio = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id FROM negocios_gofast WHERE id = %d AND activo = 1",
                $filtro_negocio
            ));
            if ($negocio) {
                $where   .= " AND user_id = %d";
                $params[] = $negocio->user_id;
            }
        }
        
        // Filtro por asignado por (admin o mensajero auto-asignado)
        if ($filtro_asignado_por === 'admin') {
            // Servicios donde un admin asignó el mensajero (asignado_por_user_id != mensajero_id y es admin)
            $where .= " AND asignado_por_user_id IS NOT NULL AND asignado_por_user_id != COALESCE(mensajero_id, 0) AND EXISTS (SELECT 1 FROM usuarios_gofast WHERE id = asignado_por_user_id AND rol = 'admin')";
        } elseif ($filtro_asignado_por === 'mensajero') {
            // Servicios donde el mensajero se auto-asignó (asignado_por_user_id = mensajero_id)
            $where .= " AND asignado_por_user_id IS NOT NULL AND asignado_por_user_id = mensajero_id";
        }
        
        // Filtro por origen (buscar en JSON)
        if ($filtro_origen > 0) {
            $where   .= " AND JSON_EXTRACT(destinos, '$.origen.barrio_id') = %d";
            $params[] = $filtro_origen;
        }
        
        // Filtro por destino (buscar en JSON array)
        if ($filtro_destino > 0) {
            $where   .= " AND JSON_CONTAINS(destinos, JSON_OBJECT('barrio_id', %d), '$.destinos')";
            $params[] = $filtro_destino;
        }
        
        // Filtro por envíos intermunicipales
        if ($filtro_intermunicipal === 'si') {
            $where .= " AND (JSON_EXTRACT(destinos, '$.tipo_servicio') = 'intermunicipal' OR direccion_origen LIKE %s)";
            $params[] = '%(Intermunicipal)%';
        } elseif ($filtro_intermunicipal === 'no') {
            $where .= " AND (JSON_EXTRACT(destinos, '$.tipo_servicio') IS NULL OR JSON_EXTRACT(destinos, '$.tipo_servicio') != 'intermunicipal') AND (direccion_origen IS NULL OR direccion_origen NOT LIKE %s)";
            $params[] = '%(Intermunicipal)%';
        }
        
        // Filtro por recargos (NO incluir 'monto' que es el precio base, solo recargos adicionales)
        if ($filtro_recargos === 'si') {
            // Servicios con recargos: buscar si el JSON contiene recargo_seleccionable_valor > 0 o recargo_total > 0
            // NO incluir 'monto' porque es el precio base, no un recargo
            $where .= " AND (
                destinos REGEXP '\"recargo_seleccionable_valor\":[1-9][0-9]*' OR
                destinos REGEXP '\"recargo_total\":[1-9][0-9]*'
            )";
        } elseif ($filtro_recargos === 'no') {
            // Servicios sin recargos: verificar que no haya recargo_seleccionable_valor > 0 ni recargo_total > 0
            // NO incluir 'monto' porque es el precio base, no un recargo
            $where .= " AND (
                (destinos NOT LIKE '%\"recargo_seleccionable_valor\":%' OR destinos NOT REGEXP '\"recargo_seleccionable_valor\":[1-9][0-9]*') AND
                (destinos NOT LIKE '%\"recargo_total\":%' OR destinos NOT REGEXP '\"recargo_total\":[1-9][0-9]*')
            )";
        }
    }

    /****************************************
     * 3. Paginación
     ****************************************/
    $por_pagina = 15;
    $pagina     = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
    $offset     = ($pagina - 1) * $por_pagina;

    if (!empty($params)) {
        $sql_count = $wpdb->prepare(
            "SELECT COUNT(*) FROM servicios_gofast WHERE $where",
            $params
        );
    } else {
        $sql_count = "SELECT COUNT(*) FROM servicios_gofast WHERE $where";
    }

    $total_registros = (int) $wpdb->get_var($sql_count);
    $total_paginas   = max(1, (int) ceil($total_registros / $por_pagina));

    $params_datos   = $params;
    $params_datos[] = $por_pagina;
    $params_datos[] = $offset;

    $sql_datos = $wpdb->prepare(
        "SELECT * FROM servicios_gofast 
         WHERE $where
         ORDER BY fecha DESC
         LIMIT %d OFFSET %d",
        $params_datos
    );

    $pedidos = $wpdb->get_results($sql_datos);

    // opciones de estado reutilizables
    $estado_opts = [
        'pendiente' => 'Pendiente',
        'asignado'  => 'Asignado',
        'en_ruta'   => 'En Ruta',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
    ];
    
    // Para mensajeros, quitar la opción de cancelar
    $estado_opts_mensajero = $estado_opts;
    unset($estado_opts_mensajero['cancelado']);

    /****************************************
     * 4. Render
     ****************************************/
    ob_start();
    
    // Si estamos en modo edición, mostrar página de edición
    if ($editar_servicio_id > 0 && $rol === 'admin'):
        $servicio_editar = $wpdb->get_row($wpdb->prepare("SELECT * FROM servicios_gofast WHERE id = %d", $editar_servicio_id));
        if ($servicio_editar):
            $json_servicio = json_decode($servicio_editar->destinos, true);
    ?>
<div class="gofast-home">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin-bottom:8px;">✏️ Editar Servicio #<?php echo $editar_servicio_id; ?></h1>
            <p class="gofast-home-text" style="margin:0;">
                Modifica el origen y destinos del servicio.
            </p>
        </div>
        <a href="<?php echo esc_url(remove_query_arg('editar_servicio')); ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;">
            ← Volver a Pedidos
        </a>
    </div>
    
    <?php if (!empty($mensaje_estado)): ?>
        <div class="gofast-box" style="background: <?= strpos($mensaje_estado, '✅') !== false ? '#d4edda' : '#f8d7da' ?>; border-left: 4px solid <?= strpos($mensaje_estado, '✅') !== false ? '#28a745' : '#dc3545' ?>; color: <?= strpos($mensaje_estado, '✅') !== false ? '#155724' : '#721c24' ?>; margin-bottom: 20px;">
            <?= esc_html($mensaje_estado) ?>
        </div>
    <?php endif; ?>
    
    <div class="gofast-box">
        <!-- Información del servicio -->
        <div style="background:#f8f9fa;border-left:4px solid var(--gofast-yellow);padding:16px;border-radius:6px;margin-bottom:20px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;">
                <div>
                    <strong style="color:#666;font-size:13px;">Cliente:</strong>
                    <div style="font-weight:600;color:#000;"><?php echo esc_html($servicio_editar->nombre_cliente ?: '—'); ?></div>
                </div>
                <div>
                    <strong style="color:#666;font-size:13px;">Teléfono:</strong>
                    <div style="color:#000;"><?php echo esc_html($servicio_editar->telefono_cliente ?: '—'); ?></div>
                </div>
                <div>
                    <strong style="color:#666;font-size:13px;">Fecha:</strong>
                    <div style="color:#000;"><?php echo esc_html(gofast_date_format($servicio_editar->fecha, 'd/m/Y H:i')); ?></div>
                </div>
                <div>
                    <strong style="color:#666;font-size:13px;">Total actual:</strong>
                    <div style="font-weight:700;color:var(--gofast-yellow);font-size:18px;">$<?php echo number_format($servicio_editar->total, 0, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        
        <form method="post" id="form-editar-servicio">
            <?php wp_nonce_field('gofast_editar_destinos', 'gofast_editar_destinos_nonce'); ?>
            <input type="hidden" name="gofast_editar_destinos" value="1">
            <input type="hidden" name="gofast_editar_destinos_id" value="<?php echo $editar_servicio_id; ?>">
            <input type="hidden" name="destinos_editados" id="destinos-editados-json">
            
            <!-- Origen -->
            <div style="margin-bottom:24px;">
                <h3 style="margin-bottom:12px;color:#2e7d32;">📍 Origen del Servicio</h3>
                <div style="background:#e8f5e9;padding:16px;border-radius:8px;border:1px solid #c8e6c9;">
                    <div style="margin-bottom:12px;">
                        <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px;">Barrio de origen:</label>
                        <select id="origen-barrio-select" class="gofast-select-origen" style="width:100%;padding:10px;font-size:14px;border:1px solid #ddd;border-radius:6px;">
                            <option value="">Seleccionar barrio...</option>
                            <?php foreach ($barrios as $b): ?>
                                <option value="<?php echo esc_attr($b->id); ?>" 
                                        data-sector="<?php echo esc_attr($b->sector_id); ?>"
                                        <?php echo (isset($json_servicio['origen']['barrio_id']) && $json_servicio['origen']['barrio_id'] == $b->id) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($b->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;margin-bottom:6px;font-weight:600;font-size:14px;">Dirección específica (opcional):</label>
                        <input type="text" id="origen-direccion" 
                               value="<?php echo esc_attr($json_servicio['origen']['direccion'] ?? ''); ?>" 
                               placeholder="Ej: Calle 10 # 5-20, Local 3"
                               style="width:100%;padding:10px;font-size:14px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                </div>
            </div>
            
            <!-- Destinos -->
            <div style="margin-bottom:24px;">
                <h3 style="margin-bottom:12px;color:#1976d2;">🎯 Destinos (<span id="count-destinos"><?php echo count($json_servicio['destinos'] ?? []); ?></span>)</h3>
                <div id="destinos-container">
                    <?php if (!empty($json_servicio['destinos'])): ?>
                        <?php foreach ($json_servicio['destinos'] as $idx => $dest): ?>
                            <div class="destino-item" style="background:#e3f2fd;padding:16px;border-radius:8px;border:1px solid #bbdefb;margin-bottom:12px;">
                                <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;" class="destino-grid">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;">Destino <?php echo $idx + 1; ?>:</label>
                                        <select class="destino-barrio-select" data-index="<?php echo $idx; ?>" style="width:100%;padding:10px;font-size:14px;border:1px solid #ddd;border-radius:6px;">
                                            <option value="">Seleccionar barrio...</option>
                                            <?php foreach ($barrios as $b): ?>
                                                <option value="<?php echo esc_attr($b->id); ?>" 
                                                        data-nombre="<?php echo esc_attr($b->nombre); ?>"
                                                        data-sector="<?php echo esc_attr($b->sector_id); ?>"
                                                        <?php echo ($dest['barrio_id'] == $b->id) ? 'selected' : ''; ?>>
                                                    <?php echo esc_html($b->nombre); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-size:13px;">Dirección:</label>
                                        <input type="text" class="destino-direccion" value="<?php echo esc_attr($dest['direccion'] ?? ''); ?>" 
                                               placeholder="Dirección específica" style="width:100%;padding:10px;font-size:14px;border:1px solid #ddd;border-radius:6px;">
                                    </div>
                                    <div>
                                        <button type="button" class="btn-eliminar-destino" style="background:#dc3545;color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer;font-size:14px;">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                                <div style="margin-top:12px;display:flex;gap:16px;flex-wrap:wrap;align-items:end;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-size:13px;">Monto adicional:</label>
                                        <input type="number" class="destino-monto" value="<?php echo (int)($dest['monto'] ?? 0); ?>" min="0" 
                                               placeholder="0" style="width:120px;padding:10px;font-size:14px;border:1px solid #ddd;border-radius:6px;">
                                    </div>
                                    <?php 
                                    // Mostrar recargos existentes (solo lectura)
                                    $recargo_sel = (int)($dest['recargo_seleccionable_valor'] ?? 0);
                                    $recargo_total = (int)($dest['recargo_total'] ?? 0);
                                    $recargo_global_dest = (int)($dest['recargo_global_valor'] ?? 0);
                                    $total_recargos_dest = $recargo_sel + $recargo_total + $recargo_global_dest;
                                    if ($total_recargos_dest > 0): 
                                    ?>
                                    <div style="background:#fff3cd;padding:8px 12px;border-radius:6px;font-size:12px;color:#856404;">
                                        <strong>💰 Recargos:</strong> $<?php echo number_format($total_recargos_dest, 0, ',', '.'); ?>
                                        <span style="display:block;font-size:11px;color:#666;margin-top:2px;">
                                            <?php 
                                            $detalles = [];
                                            if ($recargo_sel > 0) $detalles[] = "Peso/Vol: $" . number_format($recargo_sel, 0, ',', '.');
                                            if ($recargo_total > 0) $detalles[] = "Dinámico: $" . number_format($recargo_total, 0, ',', '.');
                                            if ($recargo_global_dest > 0) $detalles[] = "Global: $" . number_format($recargo_global_dest, 0, ',', '.');
                                            echo implode(' | ', $detalles);
                                            ?>
                                        </span>
                                        <span style="display:block;font-size:10px;color:#4CAF50;margin-top:4px;font-weight:600;">✓ Se conservarán al guardar</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <button type="button" id="btn-agregar-destino" class="gofast-btn-mini" style="margin-top:12px;padding:12px 20px;">
                    ➕ Agregar Destino
                </button>
                
                <?php 
                // Mostrar recargo global del servicio si existe
                $recargo_global_servicio = $json_servicio['recargo_global'] ?? null;
                $recargo_global_valor_servicio = (int)($json_servicio['recargo_global_valor'] ?? 0);
                if ($recargo_global_valor_servicio > 0): 
                ?>
                <div style="margin-top:16px;background:#e8f5e9;padding:12px;border-radius:6px;border:1px solid #c8e6c9;">
                    <strong style="color:#2e7d32;">🌐 Recargo Global del Servicio:</strong> 
                    <span style="font-weight:700;font-size:16px;">$<?php echo number_format($recargo_global_valor_servicio, 0, ',', '.'); ?></span>
                    <?php if ($recargo_global_servicio): ?>
                        <span style="color:#666;font-size:12px;margin-left:8px;">(<?php echo esc_html($recargo_global_servicio); ?>)</span>
                    <?php endif; ?>
                    <div style="font-size:11px;color:#666;margin-top:4px;">ℹ️ Este recargo se conservará al guardar</div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Botones de acción -->
            <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:20px;border-top:1px solid #ddd;">
                <a href="<?php echo esc_url(remove_query_arg('editar_servicio')); ?>" class="gofast-btn-mini gofast-btn-outline" style="padding:12px 24px;text-decoration:none;">
                    Cancelar
                </a>
                <button type="submit" class="gofast-btn-mini" style="padding:12px 24px;">
                    💾 Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const barrios = <?php echo json_encode($barrios); ?>;
    
    // Función para normalizar texto (sin tildes, minúsculas)
    function normalize(s) {
        return (s || "").toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    
    // Función para inicializar Select2 en un elemento
    function initSelect2(selectElement) {
        if (!window.jQuery || !jQuery.fn.select2) return;
        if (jQuery(selectElement).data('select2')) return;
        
        jQuery(selectElement).select2({
            placeholder: '🔍 Buscar barrio...',
            width: '100%',
            allowClear: false,
            minimumResultsForSearch: 0,
            matcher: function(params, data) {
                if (!params.term || !params.term.trim()) {
                    return data;
                }
                const searchTerm = normalize(params.term);
                const text = normalize(data.text || '');
                
                if (text.indexOf(searchTerm) > -1) {
                    return data;
                }
                return null;
            }
        });
    }
    
    // Función para actualizar contador
    function actualizarContador() {
        const count = document.querySelectorAll('.destino-item').length;
        document.getElementById('count-destinos').textContent = count;
    }
    
    // Inicializar Select2 en selectores existentes
    initSelect2(document.getElementById('origen-barrio-select'));
    document.querySelectorAll('.destino-barrio-select').forEach(function(select) {
        initSelect2(select);
    });
    
    // Agregar destino
    document.getElementById('btn-agregar-destino').addEventListener('click', function() {
        const container = document.getElementById('destinos-container');
        const index = container.querySelectorAll('.destino-item').length;
        
        const div = document.createElement('div');
        div.className = 'destino-item';
        div.style.cssText = 'background:#e3f2fd;padding:16px;border-radius:8px;border:1px solid #bbdefb;margin-bottom:12px;';
        div.innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;" class="destino-grid">
                <div>
                    <label style="display:block;margin-bottom:6px;font-weight:600;font-size:13px;">Destino ${index + 1}:</label>
                    <select class="destino-barrio-select" data-index="${index}" style="width:100%;padding:10px;font-size:14px;border:1px solid #ddd;border-radius:6px;">
                        <option value="">Seleccionar barrio...</option>
                        ${barrios.map(b => `<option value="${b.id}" data-nombre="${b.nombre}" data-sector="${b.sector_id}">${b.nombre}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label style="display:block;margin-bottom:6px;font-size:13px;">Dirección:</label>
                    <input type="text" class="destino-direccion" placeholder="Dirección específica" style="width:100%;padding:10px;font-size:14px;border:1px solid #ddd;border-radius:6px;">
                </div>
                <div>
                    <button type="button" class="btn-eliminar-destino" style="background:#dc3545;color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer;font-size:14px;">
                        🗑️
                    </button>
                </div>
            </div>
            <div style="margin-top:12px;">
                <label style="display:block;margin-bottom:6px;font-size:13px;">Monto adicional (recargo):</label>
                <input type="number" class="destino-monto" value="0" min="0" placeholder="0" style="width:150px;padding:10px;font-size:14px;border:1px solid #ddd;border-radius:6px;">
            </div>
        `;
        container.appendChild(div);
        
        const newSelect = div.querySelector('.destino-barrio-select');
        initSelect2(newSelect);
        
        actualizarContador();
    });
    
    // Eliminar destino
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-eliminar-destino')) {
            const item = e.target.closest('.destino-item');
            if (item && document.querySelectorAll('.destino-item').length > 1) {
                item.remove();
                actualizarContador();
            } else if (document.querySelectorAll('.destino-item').length <= 1) {
                alert('Debe haber al menos un destino.');
            }
        }
    });
    
    // Submit del formulario
    document.getElementById('form-editar-servicio').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const origenSelect = document.getElementById('origen-barrio-select');
        const origenDireccion = document.getElementById('origen-direccion');
        const selectedOrigen = origenSelect.options[origenSelect.selectedIndex];
        
        const origen = {
            barrio_id: parseInt(origenSelect.value) || 0,
            barrio_nombre: selectedOrigen ? selectedOrigen.text : '',
            direccion: origenDireccion.value.trim()
        };
        
        if (origen.barrio_id === 0) {
            alert('Debe seleccionar un barrio de origen.');
            return;
        }
        
        const destinos = [];
        document.querySelectorAll('.destino-item').forEach(function(item) {
            const select = item.querySelector('.destino-barrio-select');
            const direccion = item.querySelector('.destino-direccion');
            const monto = item.querySelector('.destino-monto');
            const selectedOption = select.options[select.selectedIndex];
            
            if (select.value) {
                destinos.push({
                    barrio_id: parseInt(select.value),
                    barrio_nombre: selectedOption ? selectedOption.getAttribute('data-nombre') || selectedOption.text : '',
                    direccion: direccion ? direccion.value.trim() : '',
                    monto: monto ? parseInt(monto.value) || 0 : 0
                });
            }
        });
        
        if (destinos.length === 0) {
            alert('Debe haber al menos un destino.');
            return;
        }
        
        document.getElementById('destinos-editados-json').value = JSON.stringify({ origen: origen, destinos: destinos });
        this.submit();
    });
});
</script>

<style>
@media (max-width: 768px) {
    .destino-grid {
        grid-template-columns: 1fr !important;
    }
    .destino-grid > div:last-child {
        justify-self: start;
    }
}
</style>

<?php
        else:
            ?>
            <div class="gofast-home">
                <div class="gofast-box" style="background:#f8d7da;border-left:4px solid #dc3545;color:#721c24;">
                    Servicio no encontrado.
                    <a href="<?php echo esc_url(remove_query_arg('editar_servicio')); ?>">Volver a pedidos</a>
                </div>
            </div>
            <?php
        endif;
        return ob_get_clean();
    endif;
    // Fin modo edición
    ?>

<div class="gofast-home">
    <?php if ($rol === 'admin'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="margin-bottom:8px;">📦 Pedidos</h1>
                <p class="gofast-home-text" style="margin:0;">
                    Gestiona todos los pedidos del sistema.
                </p>
            </div>
            <a href="<?php echo esc_url( home_url('/dashboard-admin') ); ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;">
                ← Volver al Dashboard
            </a>
        </div>
    <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="margin-bottom:8px;">📦 Pedidos</h1>
                <p class="gofast-home-text" style="margin:0;">
                    <?php if ($rol === 'mensajero'): ?>
                        Pedidos pendientes y asignados a ti.
                    <?php else: ?>
                        Tus pedidos y servicios solicitados.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Mensaje de resultado -->
    <?php if (!empty($mensaje_estado)): ?>
        <div class="gofast-box" style="background: <?= strpos($mensaje_estado, '✅') !== false ? '#d4edda' : '#f8d7da' ?>; border-left: 4px solid <?= strpos($mensaje_estado, '✅') !== false ? '#28a745' : '#dc3545' ?>; color: <?= strpos($mensaje_estado, '✅') !== false ? '#155724' : '#721c24' ?>; margin-bottom: 20px;">
            <?= esc_html($mensaje_estado) ?>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <h3>🔍 Filtros</h3>
        <form method="get" class="gofast-filtros-form">
            <!-- Mantener otros parámetros GET si existen -->
            <?php foreach ($_GET as $key => $value): ?>
                <?php if (!in_array($key, ['estado', 'q', 'desde', 'hasta', 'filtro_mensajero', 'filtro_sin_mensajero', 'filtro_origen', 'filtro_destino', 'filtro_negocio', 'filtro_asignado_por', 'filtro_intermunicipal', 'filtro_recargos', 'pg'])): ?>
                    <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            
            <div class="gofast-filtros-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Estado</label>
                    <select name="estado" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        <option value="todos"<?php selected($estado, 'todos'); ?>>Todos</option>
                        <?php foreach ($estado_opts as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>"<?php selected($estado, $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Nombre / Teléfono</label>
                    <input type="text" 
                           name="q" 
                           placeholder="Ej: Juan o 300..." 
                           value="<?php echo esc_attr($buscar); ?>"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha desde</label>
                    <input type="date" 
                           name="desde" 
                           value="<?php echo esc_attr($desde); ?>"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>

                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Fecha hasta</label>
                    <input type="date" 
                           name="hasta" 
                           value="<?php echo esc_attr($hasta); ?>"
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>

                <?php if ($rol === 'admin'): ?>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Mensajero</label>
                        <select name="filtro_mensajero" class="gofast-select-filtro" data-placeholder="Todos los mensajeros" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="0">Todos</option>
                            <?php foreach ($mensajeros as $m): ?>
                                <option value="<?php echo (int) $m->id; ?>"<?php selected($filtro_mensajero, $m->id); ?>>
                                    <?php echo esc_html($m->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Sin Mensajero</label>
                        <select name="filtro_sin_mensajero" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="">Todos</option>
                            <option value="si"<?php selected($filtro_sin_mensajero, 'si'); ?>>Solo sin asignar</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Origen</label>
                        <select name="filtro_origen" class="gofast-select-filtro" data-placeholder="Todos los orígenes" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="0">Todos</option>
                            <?php foreach ($barrios as $b): ?>
                                <option value="<?php echo (int) $b->id; ?>"<?php selected($filtro_origen, $b->id); ?>>
                                    <?php echo esc_html($b->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Destino</label>
                        <select name="filtro_destino" class="gofast-select-filtro" data-placeholder="Todos los destinos" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="0">Todos</option>
                            <?php foreach ($barrios as $b): ?>
                                <option value="<?php echo (int) $b->id; ?>"<?php selected($filtro_destino, $b->id); ?>>
                                    <?php echo esc_html($b->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Negocio</label>
                        <select name="filtro_negocio" class="gofast-select-filtro" data-placeholder="Todos los negocios" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="0">Todos</option>
                            <?php if (!empty($negocios)): ?>
                                <?php foreach ($negocios as $neg): ?>
                                    <option value="<?php echo (int) $neg->id; ?>"<?php selected($filtro_negocio, $neg->id); ?>>
                                        <?php echo esc_html($neg->nombre); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Asignado por</label>
                        <select name="filtro_asignado_por" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="">Todos</option>
                            <option value="admin"<?php selected($filtro_asignado_por, 'admin'); ?>>Admin</option>
                            <option value="mensajero"<?php selected($filtro_asignado_por, 'mensajero'); ?>>Mensajero (Auto-asignado)</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Envío Intermunicipal</label>
                        <select name="filtro_intermunicipal" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="">Todos</option>
                            <option value="si"<?php selected($filtro_intermunicipal, 'si'); ?>>Sí</option>
                            <option value="no"<?php selected($filtro_intermunicipal, 'no'); ?>>No</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Recargos</label>
                        <select name="filtro_recargos" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="">Todos</option>
                            <option value="si"<?php selected($filtro_recargos, 'si'); ?>>Con recargos</option>
                            <option value="no"<?php selected($filtro_recargos, 'no'); ?>>Sin recargos</option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="gofast-btn" style="background: var(--gofast-yellow); flex: 1; min-width: 120px;">
                    🔍 Filtrar
                </button>
                <?php
                // Construir URL sin los parámetros de filtro
                $clean_url = remove_query_arg(['estado', 'q', 'desde', 'hasta', 'filtro_mensajero', 'filtro_sin_mensajero', 'filtro_origen', 'filtro_destino', 'filtro_negocio', 'filtro_asignado_por', 'filtro_intermunicipal', 'filtro_recargos', 'pg']);
                if (empty($clean_url) || $clean_url === home_url('/')) {
                    $clean_url = get_permalink();
                }
                ?>
                <a href="<?= esc_url($clean_url) ?>" 
                   class="gofast-btn gofast-secondary" 
                   style="flex: 1; min-width: 120px; text-align: center; text-decoration: none; display: inline-block;">
                    🔄 Limpiar
                </a>
            </div>
        </form>
        
        <?php if ($total_registros > 0): ?>
            <div style="margin-top: 12px; padding: 10px; background: #e7f3ff; border-radius: 6px; font-size: 13px;">
                <strong>Resultados:</strong> 
                <?= $total_registros ?> pedido(s) encontrado(s)
                <?php if ($total_paginas > 1): ?>
                    (página <?= $pagina ?> de <?= $total_paginas ?>)
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Listado de pedidos -->
    <div class="gofast-box">
        <h3><?= $rol === 'admin' ? '📋 Todos los Pedidos' : ($rol === 'mensajero' ? '📋 Pedidos Asignados' : '📋 Mis Pedidos') ?></h3>
        
        <?php if ($total_registros === 0): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                No se encontraron pedidos con los filtros seleccionados.
            </p>
        <?php else: ?>
            <!-- Vista Desktop: Tabla -->
            <div class="gofast-pedidos-table-wrapper gofast-pedidos-desktop">
                <div class="gofast-table-wrap">
                    <table class="gofast-table gofast-pedidos-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Origen</th>
                            <th>Destinos</th>
                            <th>Mensajero</th>
                            <th>Total</th>
                            <th>Recargos</th>
                            <th>Estado</th>
                            <th>Ver</th>
                            <?php if ($rol === 'admin'): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pedidos as $p):

                        // Decodificar JSON
                        $origen_barrio   = '';
                        $destinos_detalle = [];

                        if (!empty($p->destinos)) {
                            $json = json_decode($p->destinos, true);
                            if (is_array($json)) {
                                if (!empty($json['origen']['barrio_nombre'])) {
                                    $origen_barrio = $json['origen']['barrio_nombre'];
                                }
                                if (!empty($json['destinos']) && is_array($json['destinos'])) {
                                    foreach ($json['destinos'] as $idx => $d) {
                                        $destino_info = [];
                                        if (!empty($d['barrio_nombre'])) {
                                            $destino_info['barrio'] = $d['barrio_nombre'];
                                        }
                                        if (!empty($d['direccion'])) {
                                            $destino_info['direccion'] = $d['direccion'];
                                        }
                                        if (!empty($d['whatsapp'])) {
                                            $destino_info['whatsapp'] = $d['whatsapp'];
                                        }
                                        // Calcular monto individual
                                        $monto_individual = 0;
                                        if (!empty($json['origen']['sector_id']) && !empty($d['sector_id'])) {
                                            $precio = $wpdb->get_var($wpdb->prepare(
                                                "SELECT precio FROM tarifas WHERE origen_sector_id=%d AND destino_sector_id=%d",
                                                intval($json['origen']['sector_id']),
                                                intval($d['sector_id'])
                                            ));
                                            $monto_base = $precio ? intval($precio) : 0;
                                            $recargo_seleccionable = !empty($d['recargo_seleccionable_valor']) ? intval($d['recargo_seleccionable_valor']) : 0;
                                            $recargo_total = !empty($d['recargo_total']) ? intval($d['recargo_total']) : 0;
                                            $monto_adicional = !empty($d['monto']) ? intval($d['monto']) : 0;
                                            $monto_individual = $monto_base + $recargo_seleccionable + $recargo_total + $monto_adicional;
                                        }
                                        $destino_info['monto'] = $monto_individual;
                                        $destinos_detalle[] = $destino_info;
                                    }
                                }
                            }
                        }

                        if ($origen_barrio === '') {
                            $origen_barrio = $p->direccion_origen ?: '—';
                        }
                        
                        // Generar texto de destinos (sin montos para que sea más compacto)
                        $destinos_text_parts = [];
                        foreach ($destinos_detalle as $idx => $dest) {
                            $part = ($idx + 1) . '. ';
                            if (!empty($dest['direccion'])) {
                                $part .= $dest['direccion'];
                            } elseif (!empty($dest['barrio'])) {
                                $part .= $dest['barrio'];
                            } else {
                                $part .= 'No especificado';
                            }
                            $destinos_text_parts[] = $part;
                        }
                        $destinos_text = !empty($destinos_text_parts)
                            ? implode(' | ', $destinos_text_parts)
                            : '—';
                        
                        // Detectar si es servicio intermunicipal
                        $es_intermunicipal = false;
                        if (!empty($p->destinos)) {
                            $json = json_decode($p->destinos, true);
                            if (is_array($json) && !empty($json['tipo_servicio']) && $json['tipo_servicio'] === 'intermunicipal') {
                                $es_intermunicipal = true;
                            }
                        }
                        if (!$es_intermunicipal && strpos($p->direccion_origen, '(Intermunicipal)') !== false) {
                            $es_intermunicipal = true;
                        }

                        // Detectar si tiene recargos (solo recargos adicionales, NO el monto base)
                        // El campo 'monto' es el precio base del trayecto, NO es un recargo
                        $tiene_recargos = false;
                        $total_recargos = 0;
                        if (!empty($p->destinos)) {
                            $json_recargos = json_decode($p->destinos, true);
                            if (is_array($json_recargos) && !empty($json_recargos['destinos']) && is_array($json_recargos['destinos'])) {
                                foreach ($json_recargos['destinos'] as $dest) {
                                    // Recargo seleccionable (por volumen/peso) - solo si existe y es > 0
                                    $recargo_seleccionable = 0;
                                    if (isset($dest['recargo_seleccionable_valor'])) {
                                        $recargo_seleccionable = (int) $dest['recargo_seleccionable_valor'];
                                        if ($recargo_seleccionable <= 0) $recargo_seleccionable = 0;
                                    }
                                    
                                    // Recargos automáticos totales - solo si existe y es > 0
                                    // NOTA: recargo_total puede estar guardado pero si es 0, no cuenta
                                    $recargo_total_auto = 0;
                                    if (isset($dest['recargo_total'])) {
                                        $recargo_total_auto = (int) $dest['recargo_total'];
                                        if ($recargo_total_auto <= 0) $recargo_total_auto = 0;
                                    }
                                    
                                    // Sumar solo recargos (NO el monto base)
                                    $recargo_destino = $recargo_seleccionable + $recargo_total_auto;
                                    
                                    if ($recargo_destino > 0) {
                                        $tiene_recargos = true;
                                        $total_recargos += $recargo_destino;
                                    }
                                }
                            }
                        }

                        // Mensajero y quién lo asignó
                        $mensajero_nombre = '—';
                        $asignado_por = '—';
                        $asignado_por_tipo = '';
                        
                        if (!empty($p->mensajero_id)) {
                            $mensajero_nombre = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT nombre FROM usuarios_gofast WHERE id = %d",
                                    $p->mensajero_id
                                )
                            ) ?: '—';
                            
                            // Determinar quién asignó el mensajero
                            // Primero verificar si existe el campo asignado_por_user_id
                            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'");
                            
                            if (!empty($column_exists) && !empty($p->asignado_por_user_id)) {
                                // Usar el campo de la base de datos
                                if ((int) $p->asignado_por_user_id === (int) $p->mensajero_id) {
                                    $asignado_por = 'Auto-asignado';
                                    $asignado_por_tipo = 'auto';
                                } else {
                                    $asignador_nombre = $wpdb->get_var(
                                        $wpdb->prepare(
                                            "SELECT nombre FROM usuarios_gofast WHERE id = %d",
                                            $p->asignado_por_user_id
                                        )
                                    );
                                    $asignador_rol = $wpdb->get_var(
                                        $wpdb->prepare(
                                            "SELECT rol FROM usuarios_gofast WHERE id = %d",
                                            $p->asignado_por_user_id
                                        )
                                    );
                                    if (strtolower($asignador_rol) === 'admin') {
                                        $asignado_por = 'Asignado por: ' . ($asignador_nombre ?: 'Admin');
                                        $asignado_por_tipo = 'admin';
                                    } else {
                                        $asignado_por = 'Asignado por: ' . ($asignador_nombre ?: 'Usuario');
                                        $asignado_por_tipo = 'otro';
                                    }
                                }
                            } else {
                                // Fallback: inferir basado en lógica antigua
                                if ((int) $p->mensajero_id === (int) $p->user_id) {
                                    $asignado_por = 'Auto-asignado';
                                    $asignado_por_tipo = 'auto';
                                } else {
                                    $asignado_por = 'Asignado por Admin';
                                    $asignado_por_tipo = 'admin';
                                }
                            }
                        }

                        $estado_actual = $p->tracking_estado;
                        $estado_class  = 'gofast-badge-estado-' . esc_attr($estado_actual);
                        $detalle_url   = esc_url( home_url('/servicio-registrado?id=' . $p->id) );
                        
                        // Detectar si el pedido está sin asignar (solo para admin)
                        $sin_asignar = ($rol === 'admin' && (empty($p->mensajero_id) || $p->mensajero_id == 0));
                        $clase_sin_asignar = $sin_asignar ? 'gofast-pedido-sin-asignar' : '';
                        ?>
                        <tr class="<?= $clase_sin_asignar ?>">
                            <td>
                                #<?php echo (int) $p->id; ?>
                                <?php if ($sin_asignar): ?>
                                    <br><span style="display:inline-block;background:#ffc107;color:#000;padding:2px 6px;border-radius:4px;font-size:9px;font-weight:700;margin-top:2px;" title="Pedido sin asignar">⚠️ SIN ASIGNAR</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( gofast_date_format($p->fecha, 'Y-m-d H:i') ); ?></td>
                            <td><?php echo esc_html($p->nombre_cliente ?: '—'); ?></td>
                            <td><?php echo esc_html($p->telefono_cliente ?: '—'); ?></td>

                            <td><?php echo esc_html($origen_barrio); ?></td>
                            <td><?php echo esc_html($destinos_text); ?></td>

                            <!-- Mensajero -->
                            <td>
                                <?php if ($rol === 'admin'): ?>
                                    <form method="post" class="gofast-estado-form">
                                        <?php wp_nonce_field('gofast_cambiar_estado', 'gofast_estado_nonce'); ?>
                                        <input type="hidden" name="gofast_estado_id" value="<?php echo (int) $p->id; ?>">
                                        <!-- mantener estado actual para que no cambie si solo se asigna mensajero -->
                                        <input type="hidden" name="gofast_estado_nuevo" value="<?php echo esc_attr($estado_actual); ?>">

                                        <select name="gofast_mensajero_id" class="gofast-mensajero-select" onchange="this.form.submit()">
                                            <option value="">Sin asignar</option>
                                            <?php foreach ($mensajeros_asignacion as $m): ?>
                                                <option value="<?php echo (int) $m->id; ?>"<?php selected($p->mensajero_id, $m->id); ?>>
                                                    <?php echo esc_html($m->nombre); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (!empty($p->mensajero_id) && $asignado_por !== '—'): ?>
                                            <div style="font-size:10px;color:#666;margin-top:2px;">
                                                <?php if ($asignado_por_tipo === 'auto'): ?>
                                                    <span style="color:#28a745;">✓ Auto-asignado</span>
                                                <?php else: ?>
                                                    <span style="color:#007bff;">✓ Por Admin</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <div>
                                        <?php echo esc_html($mensajero_nombre); ?>
                                        <?php if (!empty($p->mensajero_id) && $asignado_por !== '—'): ?>
                                            <div style="font-size:10px;color:#666;margin-top:2px;">
                                                <?php if ($asignado_por_tipo === 'auto'): ?>
                                                    <span style="color:#28a745;">(Auto-asignado)</span>
                                                <?php else: ?>
                                                    <span style="color:#007bff;">(Asignado por Admin)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Total -->
                            <td>$<?php echo number_format($p->total, 0, ',', '.'); ?></td>

                            <!-- Recargos -->
                            <td>
                                <?php if ($tiene_recargos): ?>
                                    <span style="display:inline-block;background:#fff3cd;color:#856404;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;" title="Total de recargos: $<?php echo number_format($total_recargos, 0, ',', '.'); ?>">
                                        💰 $<?php echo number_format($total_recargos, 0, ',', '.'); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#999;font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Estado -->
                            <td>
                                <?php if ($rol === 'admin' || $rol === 'mensajero'): ?>
                                    <form method="post" class="gofast-estado-form">
                                        <?php wp_nonce_field('gofast_cambiar_estado', 'gofast_estado_nonce'); ?>
                                        <input type="hidden" name="gofast_estado_id" value="<?php echo (int) $p->id; ?>">
                                        <!-- si hay mensajero, lo mantenemos; para mensajero auto-asignado ya lo pone el backend -->
                                        <?php if ($rol === 'admin' && !empty($p->mensajero_id)): ?>
                                            <input type="hidden" name="gofast_mensajero_id" value="<?php echo (int) $p->mensajero_id; ?>">
                                        <?php endif; ?>

                                        <select name="gofast_estado_nuevo" onchange="this.form.submit()">
                                            <?php 
                                            $opciones_estado = ($rol === 'mensajero') ? $estado_opts_mensajero : $estado_opts;
                                            foreach ($opciones_estado as $val => $label): ?>
                                                <option value="<?php echo esc_attr($val); ?>"<?php selected($estado_actual, $val); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <span class="gofast-badge-estado <?php echo $estado_class; ?>">
                                        <?php echo esc_html($estado_opts[$estado_actual] ?? $estado_actual); ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <a href="<?php echo $detalle_url; ?>" class="gofast-link-ver">Ver</a>
                                <?php if ($rol === 'admin' || $rol === 'mensajero'): ?>
                                    <?php 
                                    $mensaje_whatsapp = gofast_generar_mensaje_whatsapp_mensajero($p, $wpdb);
                                    if (!empty($mensaje_whatsapp)):
                                        $telefono_mensajero = !empty($p->mensajero_id) ? $wpdb->get_var($wpdb->prepare("SELECT telefono FROM usuarios_gofast WHERE id = %d", $p->mensajero_id)) : '';
                                        $telefono_empresa = "573194642513";
                                        $telefono_destino = !empty($telefono_mensajero) ? preg_replace('/[^0-9]/', '', $telefono_mensajero) : $telefono_empresa;
                                    ?>
                                    <br>
                                    <a href="#" 
                                       class="gofast-link-ver btn-whatsapp-msg" 
                                       data-phone="<?php echo esc_attr($telefono_destino); ?>"
                                       data-msg="<?php echo htmlspecialchars(json_encode($mensaje_whatsapp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
                                       style="color:#25D366;font-size:11px;margin-top:4px;display:inline-block;">
                                        💬 Mensaje
                                    </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            
                            <?php if ($rol === 'admin'): ?>
                                <td style="white-space:nowrap;">
                                    <a href="<?php echo esc_url(add_query_arg('editar_servicio', $p->id)); ?>" 
                                       class="gofast-btn-mini" style="text-decoration:none;display:inline-block;">
                                        ✏️ Editar
                                    </a>
                                    <form method="post" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('¿Estás seguro de eliminar este servicio? Esta acción no se puede deshacer.');">
                                        <?php wp_nonce_field('gofast_eliminar_servicio', 'gofast_eliminar_nonce'); ?>
                                        <input type="hidden" name="gofast_eliminar_id" value="<?php echo (int) $p->id; ?>">
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
            <div class="gofast-pedidos-cards gofast-pedidos-mobile">
                <?php foreach ($pedidos as $p): 
                    // Reutilizar la misma lógica de decodificación que en la tabla
                    $origen_barrio   = '';
                    $destinos_detalle_mobile = [];

                    if (!empty($p->destinos)) {
                        $json = json_decode($p->destinos, true);
                        if (is_array($json)) {
                            if (!empty($json['origen']['barrio_nombre'])) {
                                $origen_barrio = $json['origen']['barrio_nombre'];
                            }
                            if (!empty($json['destinos']) && is_array($json['destinos'])) {
                                foreach ($json['destinos'] as $idx => $d) {
                                    $destino_info = [];
                                    if (!empty($d['barrio_nombre'])) {
                                        $destino_info['barrio'] = $d['barrio_nombre'];
                                    }
                                    if (!empty($d['direccion'])) {
                                        $destino_info['direccion'] = $d['direccion'];
                                    }
                                    if (!empty($d['whatsapp'])) {
                                        $destino_info['whatsapp'] = $d['whatsapp'];
                                    }
                                    // Calcular monto individual
                                    $monto_individual = 0;
                                    if (!empty($json['origen']['sector_id']) && !empty($d['sector_id'])) {
                                        $precio = $wpdb->get_var($wpdb->prepare(
                                            "SELECT precio FROM tarifas WHERE origen_sector_id=%d AND destino_sector_id=%d",
                                            intval($json['origen']['sector_id']),
                                            intval($d['sector_id'])
                                        ));
                                        $monto_base = $precio ? intval($precio) : 0;
                                        $recargo_seleccionable = !empty($d['recargo_seleccionable_valor']) ? intval($d['recargo_seleccionable_valor']) : 0;
                                        $recargo_total = !empty($d['recargo_total']) ? intval($d['recargo_total']) : 0;
                                        $monto_adicional = !empty($d['monto']) ? intval($d['monto']) : 0;
                                        $monto_individual = $monto_base + $recargo_seleccionable + $recargo_total + $monto_adicional;
                                    }
                                    $destino_info['monto'] = $monto_individual;
                                    $destinos_detalle_mobile[] = $destino_info;
                                }
                            }
                        }
                    }

                    if ($origen_barrio === '') {
                        $origen_barrio = $p->direccion_origen ?: '—';
                    }
                    
                    // Generar texto de destinos (sin montos para que sea más compacto)
                    $destinos_text_parts_mobile = [];
                    foreach ($destinos_detalle_mobile as $idx => $dest) {
                        $part = ($idx + 1) . '. ';
                        if (!empty($dest['direccion'])) {
                            $part .= $dest['direccion'];
                        } elseif (!empty($dest['barrio'])) {
                            $part .= $dest['barrio'];
                        } else {
                            $part .= 'No especificado';
                        }
                        $destinos_text_parts_mobile[] = $part;
                    }
                    $destinos_text = !empty($destinos_text_parts_mobile)
                        ? implode(' | ', $destinos_text_parts_mobile)
                        : '—';
                    
                    // Detectar si es servicio intermunicipal
                    $es_intermunicipal = false;
                    if (!empty($p->destinos)) {
                        $json = json_decode($p->destinos, true);
                        if (is_array($json) && !empty($json['tipo_servicio']) && $json['tipo_servicio'] === 'intermunicipal') {
                            $es_intermunicipal = true;
                        }
                    }
                    if (!$es_intermunicipal && strpos($p->direccion_origen, '(Intermunicipal)') !== false) {
                        $es_intermunicipal = true;
                    }

                    // Detectar si tiene recargos (solo recargos adicionales, NO el monto base)
                    // El campo 'monto' es el precio base del trayecto, NO es un recargo
                    $tiene_recargos = false;
                    $total_recargos = 0;
                    if (!empty($p->destinos)) {
                        $json_recargos = json_decode($p->destinos, true);
                        if (is_array($json_recargos) && !empty($json_recargos['destinos']) && is_array($json_recargos['destinos'])) {
                            foreach ($json_recargos['destinos'] as $dest) {
                                // Recargo seleccionable (por volumen/peso) - solo si existe y es > 0
                                $recargo_seleccionable = 0;
                                if (isset($dest['recargo_seleccionable_valor'])) {
                                    $recargo_seleccionable = (int) $dest['recargo_seleccionable_valor'];
                                    if ($recargo_seleccionable <= 0) $recargo_seleccionable = 0;
                                }
                                
                                // Recargos automáticos totales - solo si existe y es > 0
                                // NOTA: recargo_total puede estar guardado pero si es 0, no cuenta
                                $recargo_total_auto = 0;
                                if (isset($dest['recargo_total'])) {
                                    $recargo_total_auto = (int) $dest['recargo_total'];
                                    if ($recargo_total_auto <= 0) $recargo_total_auto = 0;
                                }
                                
                                // Sumar solo recargos (NO el monto base)
                                $recargo_destino = $recargo_seleccionable + $recargo_total_auto;
                                
                                if ($recargo_destino > 0) {
                                    $tiene_recargos = true;
                                    $total_recargos += $recargo_destino;
                                }
                            }
                        }
                    }

                    // Mensajero y quién lo asignó
                    $mensajero_nombre = '—';
                    $asignado_por = '—';
                    $asignado_por_tipo = '';
                    
                    if (!empty($p->mensajero_id)) {
                        $mensajero_nombre = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT nombre FROM usuarios_gofast WHERE id = %d",
                                $p->mensajero_id
                            )
                        ) ?: '—';
                        
                        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM servicios_gofast LIKE 'asignado_por_user_id'");
                        
                        if (!empty($column_exists) && !empty($p->asignado_por_user_id)) {
                            if ((int) $p->asignado_por_user_id === (int) $p->mensajero_id) {
                                $asignado_por = 'Auto-asignado';
                                $asignado_por_tipo = 'auto';
                            } else {
                                $asignador_nombre = $wpdb->get_var(
                                    $wpdb->prepare(
                                        "SELECT nombre FROM usuarios_gofast WHERE id = %d",
                                        $p->asignado_por_user_id
                                    )
                                );
                                $asignador_rol = $wpdb->get_var(
                                    $wpdb->prepare(
                                        "SELECT rol FROM usuarios_gofast WHERE id = %d",
                                        $p->asignado_por_user_id
                                    )
                                );
                                if (strtolower($asignador_rol) === 'admin') {
                                    $asignado_por = 'Asignado por: ' . ($asignador_nombre ?: 'Admin');
                                    $asignado_por_tipo = 'admin';
                                } else {
                                    $asignado_por = 'Asignado por: ' . ($asignador_nombre ?: 'Usuario');
                                    $asignado_por_tipo = 'otro';
                                }
                            }
                        } else {
                            if ((int) $p->mensajero_id === (int) $p->user_id) {
                                $asignado_por = 'Auto-asignado';
                                $asignado_por_tipo = 'auto';
                            } else {
                                $asignado_por = 'Asignado por Admin';
                                $asignado_por_tipo = 'admin';
                            }
                        }
                    }

                    $estado_actual = $p->tracking_estado;
                    $estado_class  = 'gofast-badge-estado-' . esc_attr($estado_actual);
                    $detalle_url   = esc_url( home_url('/servicio-registrado?id=' . $p->id) );
                    
                    // Detectar si el pedido está sin asignar (solo para admin)
                    $sin_asignar = ($rol === 'admin' && (empty($p->mensajero_id) || $p->mensajero_id == 0));
                    
                    // Colores según estado
                    $estado_color = '';
                    $estado_text = '';
                    switch ($estado_actual) {
                        case 'pendiente':
                            $estado_color = '#fff3cd';
                            $estado_text = '⏳ Pendiente';
                            break;
                        case 'asignado':
                            $estado_color = '#cfe2ff';
                            $estado_text = '📋 Asignado';
                            break;
                        case 'en_ruta':
                            $estado_color = '#d1ecf1';
                            $estado_text = '🚚 En Ruta';
                            break;
                        case 'entregado':
                            $estado_color = '#d4edda';
                            $estado_text = '✅ Entregado';
                            break;
                        case 'cancelado':
                            $estado_color = '#f8d7da';
                            $estado_text = '❌ Cancelado';
                            break;
                        default:
                            $estado_color = '#f8f9fa';
                            $estado_text = esc_html($estado_opts[$estado_actual] ?? $estado_actual);
                    }
                    ?>
                    <div class="gofast-pedido-card <?= $sin_asignar ? 'gofast-pedido-sin-asignar' : '' ?>" style="background: <?= $sin_asignar ? '#fff3cd' : '#fff' ?>; border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 12px; border-left: 4px solid <?= $estado_color ?>;">
                        <?php if ($sin_asignar): ?>
                            <div style="background: #ffc107; color: #000; padding: 8px; border-radius: 6px; margin-bottom: 12px; font-weight: 700; text-align: center; font-size: 13px;">
                                ⚠️ PEDIDO SIN ASIGNAR
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div>
                                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                    ID: #<?= esc_html($p->id) ?>
                                    <?php if ($es_intermunicipal): ?>
                                        <span style="display:inline-block;background:#e0a800;color:#000;padding:2px 6px;border-radius:4px;font-size:9px;font-weight:700;margin-left:4px;" title="Servicio Intermunicipal">🚚 INTER</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 11px; color: #999;"><?= esc_html(gofast_date_format($p->fecha, 'Y-m-d H:i')) ?></div>
                            </div>
                            <span class="gofast-badge-estado <?= $estado_class ?>" style="font-size: 12px; padding: 4px 10px;">
                                <?= $estado_text ?>
                            </span>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <div style="font-size: 24px; font-weight: 700; color: #000;">
                                $<?= number_format($p->total, 0, ',', '.') ?>
                            </div>
                            <?php if ($tiene_recargos): ?>
                                <div style="margin-top: 6px;">
                                    <span style="display:inline-block;background:#fff3cd;color:#856404;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;" title="Total de recargos: $<?= number_format($total_recargos, 0, ',', '.'); ?>">
                                        💰 Recargos: $<?= number_format($total_recargos, 0, ',', '.'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Cliente:</div>
                            <div style="font-size: 14px; font-weight: 600; color: #000;">
                                <?= esc_html($p->nombre_cliente ?: '—') ?>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                <?= esc_html($p->telefono_cliente ?: '—') ?>
                            </div>
                        </div>

                        <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Origen:</div>
                            <div style="font-size: 14px; font-weight: 600; color: #000;">
                                <?= esc_html($origen_barrio) ?>
                            </div>
                        </div>

                        <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Destinos:</div>
                            <div style="font-size: 14px; font-weight: 600; color: #000;">
                                <?= esc_html($destinos_text) ?>
                            </div>
                            <?php if ($rol === 'admin' || $rol === 'mensajero'): ?>
                                <?php 
                                $mensaje_whatsapp_mobile = gofast_generar_mensaje_whatsapp_mensajero($p, $wpdb);
                                if (!empty($mensaje_whatsapp_mobile)):
                                    $telefono_mensajero_mobile = !empty($p->mensajero_id) ? $wpdb->get_var($wpdb->prepare("SELECT telefono FROM usuarios_gofast WHERE id = %d", $p->mensajero_id)) : '';
                                    $telefono_empresa_mobile = "573194642513";
                                    $telefono_destino_mobile = !empty($telefono_mensajero_mobile) ? preg_replace('/[^0-9]/', '', $telefono_mensajero_mobile) : $telefono_empresa_mobile;
                                ?>
                                <div style="margin-top: 8px;">
                                    <a href="#" 
                                       class="btn-whatsapp-msg-mobile"
                                       data-phone="<?php echo esc_attr($telefono_destino_mobile); ?>"
                                       data-msg="<?php echo htmlspecialchars(json_encode($mensaje_whatsapp_mobile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
                                       style="display:inline-block;background:#25D366;color:#fff;padding:6px 12px;border-radius:6px;text-decoration:none;font-size:12px;">
                                        💬 Enviar mensaje WhatsApp
                                    </a>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Mensajero:</div>
                            <?php if ($rol === 'admin'): ?>
                                <form method="post" class="gofast-estado-form">
                                    <?php wp_nonce_field('gofast_cambiar_estado', 'gofast_estado_nonce'); ?>
                                    <input type="hidden" name="gofast_estado_id" value="<?= (int) $p->id ?>">
                                    <input type="hidden" name="gofast_estado_nuevo" value="<?= esc_attr($estado_actual) ?>">
                                    <select name="gofast_mensajero_id" 
                                            class="gofast-mensajero-select" 
                                            onchange="this.form.submit()"
                                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; margin-bottom: 4px;">
                                        <option value="">Sin asignar</option>
                                        <?php foreach ($mensajeros_asignacion as $m): ?>
                                            <option value="<?= (int) $m->id ?>"<?php selected($p->mensajero_id, $m->id) ?>>
                                                <?= esc_html($m->nombre) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($p->mensajero_id) && $asignado_por !== '—'): ?>
                                        <div style="font-size: 11px; color: #666; margin-top: 4px;">
                                            <?php if ($asignado_por_tipo === 'auto'): ?>
                                                <span style="color: #28a745;">✓ Auto-asignado</span>
                                            <?php else: ?>
                                                <span style="color: #007bff;">✓ Por Admin</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <div style="font-size: 14px; font-weight: 600; color: #000;">
                                    <?= esc_html($mensajero_nombre) ?>
                                </div>
                                <?php if (!empty($p->mensajero_id) && $asignado_por !== '—'): ?>
                                    <div style="font-size: 11px; color: #666; margin-top: 4px;">
                                        <?php if ($asignado_por_tipo === 'auto'): ?>
                                            <span style="color: #28a745;">(Auto-asignado)</span>
                                        <?php else: ?>
                                            <span style="color: #007bff;">(Asignado por Admin)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <div style="font-size: 13px; color: #666; margin-bottom: 8px;">Estado:</div>
                            <?php if ($rol === 'admin' || $rol === 'mensajero'): ?>
                                <form method="post" class="gofast-estado-form">
                                    <?php wp_nonce_field('gofast_cambiar_estado', 'gofast_estado_nonce'); ?>
                                    <input type="hidden" name="gofast_estado_id" value="<?= (int) $p->id ?>">
                                    <?php if ($rol === 'admin' && !empty($p->mensajero_id)): ?>
                                        <input type="hidden" name="gofast_mensajero_id" value="<?= (int) $p->mensajero_id ?>">
                                    <?php endif; ?>
                                    <select name="gofast_estado_nuevo" 
                                            onchange="this.form.submit()"
                                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                        <?php 
                                        $opciones_estado = ($rol === 'mensajero') ? $estado_opts_mensajero : $estado_opts;
                                        foreach ($opciones_estado as $val => $label): ?>
                                            <option value="<?= esc_attr($val) ?>"<?php selected($estado_actual, $val) ?>>
                                                <?= esc_html($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="gofast-badge-estado <?= $estado_class ?>" style="font-size: 12px; padding: 4px 10px;">
                                    <?= esc_html($estado_opts[$estado_actual] ?? $estado_actual) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; gap: 8px; margin-top: 12px;">
                            <a href="<?= $detalle_url ?>" 
                               class="gofast-btn-mini" 
                               style="flex: 1; padding: 10px; background: var(--gofast-yellow); color: #000; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; text-align: center; text-decoration: none; display: inline-block;">
                                👁️ Ver Detalle
                            </a>
                            
                            <?php if ($rol === 'admin'): ?>
                                <a href="<?= esc_url(add_query_arg('editar_servicio', $p->id)) ?>" 
                                   class="gofast-btn-mini"
                                   style="flex: 1; padding: 10px; background: var(--gofast-yellow); color: #000; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; text-align: center; text-decoration: none;">
                                    ✏️ Editar
                                </a>
                                
                                <form method="post" style="flex: 1;" onsubmit="return confirm('¿Estás seguro de eliminar este servicio? Esta acción no se puede deshacer.');">
                                    <?php wp_nonce_field('gofast_eliminar_servicio', 'gofast_eliminar_nonce'); ?>
                                    <input type="hidden" name="gofast_eliminar_id" value="<?= (int) $p->id ?>">
                                    <button type="submit" 
                                            style="width: 100%; padding: 10px; background: #dc3545; color: white; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                        🗑️ Eliminar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_paginas > 1): ?>
                <div class="gofast-pagination" style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                    <?php
                    $base_url   = get_permalink();
                    $query_args = $_GET;

                    for ($i = 1; $i <= $total_paginas; $i++):
                        $query_args['pg'] = $i;
                        $url    = esc_url( add_query_arg($query_args, $base_url) );
                        $active = ($i === $pagina) ? 'gofast-page-current' : '';
                        ?>
                        <a href="<?php echo $url; ?>" class="gofast-page-link <?php echo $active; ?>" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;<?php echo $active ? 'background:var(--gofast-yellow);font-weight:700;' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php if ($rol === 'admin'): ?>
<!-- Modal eliminado - ahora se usa página completa para editar (ver ?editar_servicio=ID) -->

<script>
(function() {
    // Proteger contra errores de toggleOtro si se ejecuta desde otro archivo
    // Verificar primero si los elementos existen en esta página
    const tipoSelectExists = document.getElementById("tipo_negocio");
    const wrapperOtroExists = document.getElementById("tipo_otro_wrapper");
    
    // Si los elementos no existen, crear una función segura desde el inicio
    if (!tipoSelectExists || !wrapperOtroExists) {
        window.toggleOtro = function() {
            // Función vacía segura - no hacer nada
            return;
        };
    } else if (typeof window.toggleOtro === 'function') {
        // Si existe y los elementos están presentes, mantener la función original con protección
        const originalToggleOtro = window.toggleOtro;
        window.toggleOtro = function() {
            try {
                const tipoSelect = document.getElementById("tipo_negocio");
                const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                if (tipoSelect && wrapperOtro && tipoSelect.parentNode && wrapperOtro.parentNode) {
                    return originalToggleOtro();
                }
            } catch(e) {
                // Silenciar error completamente
                return;
            }
        };
    }
    
    // También prevenir que setTimeout ejecute toggleOtro si no están los elementos
    (function() {
        const originalSetTimeout = window.setTimeout;
        window.setTimeout = function(func, delay) {
            if (typeof func === 'function') {
                try {
                    const funcStr = func.toString();
                    if (funcStr.includes('toggleOtro')) {
                        const tipoSelect = document.getElementById("tipo_negocio");
                        const wrapperOtro = document.getElementById("tipo_otro_wrapper");
                        if (!tipoSelect || !wrapperOtro) {
                            // Retornar un timeout vacío en lugar de null para evitar errores
                            return originalSetTimeout(function() {}, 0);
                        }
                    }
                } catch(e) {
                    // Si hay algún error al verificar, continuar normalmente
                }
            }
            return originalSetTimeout.apply(this, arguments);
        };
    })();
    
    const barrios = <?php echo json_encode($barrios); ?>;
    
    // Función para normalizar texto (sin tildes, minúsculas) - HACER GLOBAL
    window.normalize = s => (s || "")
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
    
    // Función matcher mejorada para búsqueda de barrios - HACER GLOBAL
    window.matcherDestinos = function(params, data) {
        // Si no hay data, retornar null
        if (!data) return null;
        
        // Si es un optgroup (tiene children), dejarlo pasar
        if (data.children && Array.isArray(data.children)) {
            return data;
        }
        
        // Si no tiene id, es un optgroup label o separador
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

        const term = window.normalize(params.term);
        if (!term) {
            data.matchScore = 0;
            return data;
        }

        const text = window.normalize(data.text);
        
        // PRIMERO: Verificar coincidencia exacta
        if (text === term) {
            data.matchScore = 10000;
            return data;
        }
        
        // SEGUNDO: Verificar si el texto comienza exactamente con el término
        if (text.indexOf(term) === 0) {
            data.matchScore = 9500;
            return data;
        }
        
        // Palabras comunes a ignorar
        const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
        
        const searchWords = term.split(/\s+/).filter(Boolean).filter(word => {
            return word.length > 2 && !stopWords.includes(word);
        });
        
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
    
    // Función para inicializar Select2 en los selects de barrios
    function initSelect2Barrios(selectElement) {
        if (!window.jQuery || !jQuery.fn.select2) return;
        
        // Evitar inicializar dos veces
        if (jQuery(selectElement).data('select2')) {
            return;
        }
        
        jQuery(selectElement).select2({
            placeholder: "🔍 Escribe para buscar barrio...",
            width: '100%',
            dropdownParent: jQuery('#modal-editar-destinos'),
            allowClear: true,
            minimumResultsForSearch: 0,
            dropdownAutoWidth: false,
            dropdownCssClass: "gofast-select-down-modal",
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
                
                // Obtener el término de búsqueda
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
                
                // Normalizar para búsqueda
                const normalizedSearch = window.normalize(searchTerm);
                const normalizedText = window.normalize(originalText);
                
                // Dividir término en palabras significativas
                const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
                const searchWords = normalizedSearch.split(/\s+/).filter(Boolean).filter(word => {
                    return word.length > 2 && !stopWords.includes(word);
                });
                
                const wordsToHighlight = searchWords.length > 0 ? searchWords : [normalizedSearch];
                
                // Encontrar coincidencias y mapear a texto original
                const highlightRanges = [];
                
                wordsToHighlight.forEach(function(word) {
                    let searchPos = 0;
                    while ((searchPos = normalizedText.indexOf(word, searchPos)) !== -1) {
                        const endPos = searchPos + word.length;
                        
                        // Mapear posiciones normalizadas a originales
                        let origStart = -1;
                        let origEnd = -1;
                        let normPos = 0;
                        
                        // Encontrar inicio
                        for (let i = 0; i < originalText.length && origStart === -1; i++) {
                            const charNorm = window.normalize(originalText[i]);
                            if (normPos === searchPos) {
                                origStart = i;
                            }
                            normPos += charNorm.length;
                        }
                        
                        // Encontrar fin
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
                
                // Si no hay coincidencias, retornar texto sin resaltar
                const $result = jQuery('<span>').text(originalText);
                if (data.matchScore !== undefined) {
                    $result.attr('data-match-score', data.matchScore);
                }
                return $result;
            }
        }).on('select2:open', function(e) {
            // Asegurar que el dropdown se posicione correctamente en el modal
            setTimeout(function() {
                const $select = jQuery(selectElement);
                const $selectContainer = $select.closest('.select2-container');
                const $dropdown = jQuery('.select2-dropdown');
                const $modal = jQuery('#modal-editar-destinos');
                const $modalContent = $select.closest('.modal-editar-destinos-content');
                const $searchContainer = $dropdown.find('.select2-search--dropdown');
                const $searchField = $searchContainer.find('.select2-search__field');
                
                // Asegurar z-index alto para que aparezca sobre el modal
                $dropdown.css({
                    'z-index': '10001',
                    'position': 'absolute'
                });
                
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
                
                // Calcular posición y ajustar si es necesario
                setTimeout(function() {
                    const selectOffset = $selectContainer.offset();
                    const selectHeight = $selectContainer.outerHeight();
                    const dropdownHeight = $dropdown.outerHeight() || 200; // altura estimada si aún no se ha renderizado
                    const windowHeight = jQuery(window).height();
                    const windowScrollTop = jQuery(window).scrollTop();
                    const modalScrollTop = $modal.scrollTop();
                    
                    // Calcular posición relativa al viewport (no al documento)
                    const selectTopRelative = selectOffset.top - windowScrollTop;
                    const selectBottomRelative = selectTopRelative + selectHeight;
                    
                    // Calcular espacio disponible
                    const spaceBelow = windowHeight - selectBottomRelative;
                    const spaceAbove = selectTopRelative;
                    
                    // Si no hay suficiente espacio debajo pero sí arriba, forzar que se abra hacia arriba
                    if (spaceBelow < Math.min(dropdownHeight, 200) && spaceAbove > Math.min(dropdownHeight, 200)) {
                        $selectContainer.addClass('select2-container--above');
                        $dropdown.addClass('select2-dropdown--above');
                    } else {
                        $selectContainer.removeClass('select2-container--above');
                        $dropdown.removeClass('select2-dropdown--above');
                    }
                    
                    // Asegurar que el select sea visible en el viewport haciendo scroll del modal si es necesario
                    const selectTopInModal = selectOffset.top - $modal.offset().top + modalScrollTop;
                    const modalViewportTop = modalScrollTop;
                    const modalViewportBottom = modalScrollTop + $modal.height();
                    
                    // Si el select está cerca del borde inferior del viewport del modal, hacer scroll
                    if (selectTopInModal + selectHeight > modalViewportBottom - 50) {
                        const scrollAmount = (selectTopInModal + selectHeight) - (modalViewportBottom - 50);
                        $modal.animate({ scrollTop: modalScrollTop + scrollAmount }, 200);
                    } else if (selectTopInModal < modalViewportTop + 50) {
                        const scrollAmount = (modalViewportTop + 50) - selectTopInModal;
                        $modal.animate({ scrollTop: modalScrollTop - scrollAmount }, 200);
                    }
                    
                    // En móvil, asegurar que el dropdown sea visible y tenga el ancho correcto
                    if (window.innerWidth <= 768) {
                        if ($modalContent.length) {
                            const contentWidth = $modalContent.width();
                            $dropdown.css({
                                'max-width': contentWidth + 'px',
                                'width': 'auto',
                                'min-width': '200px',
                                'left': selectOffset.left + 'px !important'
                            });
                        }
                    } else {
                        // En desktop, ajustar posición left para que esté alineado con el select
                        $dropdown.css({
                            'left': selectOffset.left + 'px !important',
                            'width': Math.max($selectContainer.outerWidth(), 200) + 'px'
                        });
                    }
                }, 150);
            }, 50);
        });
    }
    
    // El modal de edición fue reemplazado por una página completa
    // Ver parámetro GET editar_servicio=ID
    
    // Inicializar Select2 para filtros de barrios con buscador mejorado
    // Las funciones ya están disponibles globalmente (window.normalize y window.matcherDestinos)
    if (window.jQuery && jQuery.fn.select2 && typeof window.matcherDestinos === 'function' && typeof window.normalize === 'function') {
        jQuery('.gofast-select-filtro').each(function() {
            // Evitar inicializar dos veces
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
            matcher: matcherDestinos,
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
                
                // Obtener el término de búsqueda
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
                
                // Normalizar para búsqueda
                const normalizedSearch = window.normalize(searchTerm);
                const normalizedText = window.normalize(originalText);
                
                // Dividir término en palabras significativas
                const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
                const searchWords = normalizedSearch.split(/\s+/).filter(Boolean).filter(word => {
                    return word.length > 2 && !stopWords.includes(word);
                });
                
                const wordsToHighlight = searchWords.length > 0 ? searchWords : [normalizedSearch];
                
                // Encontrar coincidencias y mapear a texto original
                const highlightRanges = [];
                
                wordsToHighlight.forEach(function(word) {
                    let searchPos = 0;
                    while ((searchPos = normalizedText.indexOf(word, searchPos)) !== -1) {
                        const endPos = searchPos + word.length;
                        
                        // Mapear posiciones normalizadas a originales
                        let origStart = -1;
                        let origEnd = -1;
                        let normPos = 0;
                        
                        // Encontrar inicio
                        for (let i = 0; i < originalText.length && origStart === -1; i++) {
                            const charNorm = window.normalize(originalText[i]);
                            if (normPos === searchPos) {
                                origStart = i;
                            }
                            normPos += charNorm.length;
                        }
                        
                        // Encontrar fin
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
                
                // Si no hay coincidencias, retornar texto sin resaltar
                const $result = jQuery('<span>').text(originalText);
                if (data.matchScore !== undefined) {
                    $result.attr('data-match-score', data.matchScore);
                }
                return $result;
            }
        }).on('select2:open', function(e) {
            // Asegurar que el campo de búsqueda sea visible
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
        
        // Ocultar todos los selects con Select2 inmediatamente después de inicializarlos
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
        // Si las funciones no están disponibles, reintentar después de un breve delay
        setTimeout(function() {
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
            }
        }, 500);
    }
})();

// Manejar clics en botones de WhatsApp (mismo método que en confirmacion.php)
document.addEventListener('DOMContentLoaded', function() {
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    
    // Manejar botones de WhatsApp en la tabla
    document.querySelectorAll('.btn-whatsapp-msg').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const phone = this.getAttribute('data-phone');
            const msgJson = this.getAttribute('data-msg');
            
            if (!phone || !msgJson) return;
            
            try {
                // El mensaje viene como JSON string escapado, parsearlo y luego codificar (mismo método que confirmacion.php)
                const msg = JSON.parse(msgJson);
                // Codificar el mensaje correctamente para WhatsApp (maneja emojis Unicode)
                const msgEncoded = encodeURIComponent(msg);
                const url = isMobile
                    ? `https://wa.me/${phone}?text=${msgEncoded}`
                    : `https://web.whatsapp.com/send?phone=${phone}&text=${msgEncoded}`;
                window.open(url, '_blank');
            } catch(e) {
                console.error('Error al procesar mensaje WhatsApp:', e);
            }
        });
    });
    
    // Manejar botones de WhatsApp en cards móviles
    document.querySelectorAll('.btn-whatsapp-msg-mobile').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const phone = this.getAttribute('data-phone');
            const msgJson = this.getAttribute('data-msg');
            
            if (!phone || !msgJson) return;
            
            try {
                // El mensaje viene como JSON string escapado, parsearlo y luego codificar (mismo método que confirmacion.php)
                const msg = JSON.parse(msgJson);
                // Codificar el mensaje correctamente para WhatsApp (maneja emojis Unicode)
                const msgEncoded = encodeURIComponent(msg);
                const url = isMobile
                    ? `https://wa.me/${phone}?text=${msgEncoded}`
                    : `https://web.whatsapp.com/send?phone=${phone}&text=${msgEncoded}`;
                window.open(url, '_blank');
            } catch(e) {
                console.error('Error al procesar mensaje WhatsApp:', e);
            }
        });
    });
});
</script>
<?php endif; ?>

<style>
/* Los estilos de gofast-home ya están en css.css */

/* Estilos para resaltar pedidos sin asignar (solo admin) - Solo color de fondo */
.gofast-pedido-sin-asignar {
    background-color: #fff3cd !important;
}

.gofast-pedido-sin-asignar td {
    background-color: #fff3cd !important;
}

/* Estilos para modal editar destinos */
#modal-editar-destinos {
    /* Asegurar que esté oculto por defecto */
    display: none !important;
    align-items: flex-start;
    justify-content: center;
    padding-top: 20px;
    padding-bottom: 20px;
}

/* Solo aplicar flex cuando el modal esté visible (cuando se cambia a display: block) */
#modal-editar-destinos[style*="display: block"] {
    display: flex !important;
}

/* Estilos para tabla de pedidos */
.gofast-pedidos-table-wrapper .gofast-table-wrap {
    width: 100%;
    max-width: 100%;
    overflow-x: auto !important;
    overflow-y: visible !important;
    -webkit-overflow-scrolling: touch;
    display: block;
    margin: 0;
    padding: 0;
}

.gofast-pedidos-table-wrapper .gofast-pedidos-table {
    min-width: 1000px;
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

/* Vista Desktop: Mostrar tabla, ocultar cards */
.gofast-pedidos-desktop {
    display: block;
}

/* Vista Móvil: Cards (oculta en desktop) */
.gofast-pedidos-mobile {
    display: none;
}

.gofast-pedidos-cards {
    width: 100%;
    max-width: 100%;
}

.gofast-pedido-card {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Estilos para formulario de filtros */
.gofast-filtros-form {
    width: 100%;
    box-sizing: border-box;
}

.gofast-filtros-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
    width: 100%;
    box-sizing: border-box;
}

.gofast-filtros-form label {
    display: block;
    font-weight: 600;
    margin-bottom: 4px;
    font-size: 13px;
    color: #000;
    width: 100%;
    box-sizing: border-box;
}

.gofast-filtros-form input[type="date"],
.gofast-filtros-form input[type="text"],
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
    display: block;
}

/* Ocultar select original cuando Select2 está activo - INMEDIATAMENTE */
.gofast-filtros-form select.gofast-select-filtro {
    visibility: hidden !important;
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    opacity: 0 !important;
    pointer-events: none !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
}

/* Mostrar el contenedor de Select2 */
.gofast-filtros-form .select2-container {
    width: 100% !important;
    display: block !important;
    margin-bottom: 16px !important;
}

/* Estilos unificados para Select2 en filtros (desktop) - igual que en cotizador */
.gofast-filtros-form .select2-container--default .select2-selection--single {
    background: #fff !important;
    border: 1px solid var(--gofast-gray-400) !important;
    border-radius: var(--radius-s) !important;
    height: 42px !important;
    padding: 0 !important;
    padding-left: 10px !important;
    padding-right: 30px !important;
    display: flex !important;
    align-items: center !important;
    position: relative !important;
    box-sizing: border-box !important;
}

.gofast-filtros-form .select2-container--default .select2-selection--single .select2-selection__rendered {
    background: #fff !important;
    color: #000 !important;
    line-height: 30px !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    height: 100% !important;
    width: calc(100% - 30px) !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

.gofast-filtros-form .select2-selection__rendered {
    color: #000 !important;
    line-height: 30px !important;
    background: #fff !important;
    display: flex !important;
    align-items: center !important;
    padding: 0 !important;
    width: calc(100% - 30px) !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

.gofast-filtros-form .select2-container--default.select2-container--focus .select2-selection--single,
.gofast-filtros-form .select2-container--default.select2-container--open .select2-selection--single,
.gofast-filtros-form .select2-container--default .select2-selection--single[aria-expanded="false"],
.gofast-filtros-form .select2-container--default .select2-selection--single[aria-expanded="true"] {
    background: #fff !important;
}

.gofast-filtros-form .select2-selection__arrow {
    height: 42px !important;
    top: 0 !important;
    right: 8px !important;
    width: 20px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: absolute !important;
}

.gofast-filtros-form .select2-selection__arrow b {
    border-color: #555 transparent transparent transparent !important;
    margin-top: 0 !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
}

/* Responsive para móvil - tabla de pedidos */
@media (max-width: 768px) {
    
    .gofast-pedidos-table-wrapper {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: visible !important;
        margin: 0;
        padding: 0;
        display: block !important;
        visibility: visible !important;
    }
    
    .gofast-pedidos-table-wrapper .gofast-table-wrap {
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
    
    .gofast-pedidos-table-wrapper .gofast-table-wrap::-webkit-scrollbar {
        height: 8px;
    }
    
    .gofast-pedidos-table-wrapper .gofast-table-wrap::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .gofast-pedidos-table-wrapper .gofast-table-wrap::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    .gofast-pedidos-table-wrapper .gofast-pedidos-table {
        min-width: 1000px;
        font-size: 13px;
        display: table !important;
        visibility: visible !important;
    }
    
    .gofast-pedidos-table-wrapper .gofast-pedidos-table th,
    .gofast-pedidos-table-wrapper .gofast-pedidos-table td {
        padding: 10px 8px;
        font-size: 12px;
        white-space: nowrap;
        display: table-cell !important;
        visibility: visible !important;
    }
    
    /* Ocultar tabla en móvil, mostrar cards */
    .gofast-pedidos-desktop {
        display: none !important;
    }
    
    .gofast-pedidos-mobile {
        display: block !important;
    }
    
    .gofast-pedidos-cards {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }
    
    .gofast-pedido-card {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    .gofast-pedido-card * {
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    
    /* Filtros en móvil - Layout corregido */
    .gofast-filtros-grid {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
    
    .gofast-filtros-grid > div {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        float: none !important;
        clear: both !important;
    }
    
    .gofast-filtros-form label {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin-bottom: 4px !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        box-sizing: border-box !important;
    }
    
    .gofast-filtros-form select,
    .gofast-filtros-form input[type="text"],
    .gofast-filtros-form input[type="date"] {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        display: block !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        float: none !important;
    }
    
    /* Asegurar que Select2 en filtros móviles tenga el ancho correcto */
    .gofast-filtros-form .select2-container {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        display: block !important;
        margin: 0 !important;
    }
    
    /* Estilos Select2 en móvil - EXACTAMENTE igual que en el cotizador */
    .gofast-filtros-form .select2-container--default .select2-selection--single {
        height: 50px !important;
        padding: 0 !important;
        padding-left: 12px !important;
        padding-right: 35px !important;
        font-size: 16px !important;
        display: flex !important;
        align-items: center !important;
        position: relative !important;
        box-sizing: border-box !important;
    }
    
    .gofast-filtros-form .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 34px !important;
        font-size: 16px !important;
        padding: 0 !important;
        display: flex !important;
        align-items: center !important;
        height: 100% !important;
        width: 100% !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        margin-right: 35px !important;
    }
    
    .gofast-filtros-form .select2-selection__rendered {
        line-height: 34px !important;
        font-size: 16px !important;
        display: flex !important;
        align-items: center !important;
        padding: 0 !important;
        width: 100% !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        margin-right: 35px !important;
    }
    
    /* Asegurar que el span dentro del rendered tenga el tamaño correcto */
    .gofast-filtros-form .select2-selection__rendered span,
    .gofast-filtros-form .select2-container--default .select2-selection--single .select2-selection__rendered span {
        font-size: 16px !important;
        line-height: 34px !important;
        display: inline-block !important;
        width: 100% !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }
    
    .gofast-filtros-form .select2-selection__arrow {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        position: absolute !important;
        right: 10px !important;
        width: 20px !important;
    }
    
    .gofast-filtros-form .select2-selection__arrow b {
        margin-top: 0 !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
    }
    
    .gofast-filtros-form button,
    .gofast-filtros-form a {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    
    /* Modal editar destinos en móvil */
    #modal-editar-destinos {
        padding: 10px !important;
        /* No forzar display aquí - respetar el display:none del inline style */
        align-items: flex-start !important;
        justify-content: center !important;
    }
    
    /* Solo aplicar flex cuando el modal esté visible en móvil */
    #modal-editar-destinos[style*="display: block"] {
        display: flex !important;
        scroll-behavior: auto;
    }
    
    .modal-editar-destinos-content {
        max-width: 100% !important;
        margin: 10px auto !important;
        padding: 16px !important;
        border-radius: 8px !important;
    }
    
    .modal-editar-destinos-title {
        font-size: 18px !important;
        margin-bottom: 12px !important;
    }
    
    .modal-servicio-info {
        padding: 10px !important;
        font-size: 12px !important;
    }
    
    .modal-servicio-info-grid {
        grid-template-columns: 1fr !important;
        gap: 6px 8px !important;
    }
    
    .modal-servicio-info-grid strong {
        display: block;
        margin-top: 4px;
    }
    
    .modal-servicio-info-grid strong:first-child {
        margin-top: 0;
    }
    
    .destino-item-grid {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
    }
    
    .destino-item-grid > div {
        width: 100% !important;
    }
    
    .modal-editar-destinos-buttons {
        flex-direction: column !important;
        gap: 8px !important;
    }
    
    .modal-editar-destinos-buttons button {
        width: 100% !important;
    }
    
    /* Asegurar que Select2 funcione bien en móvil dentro del modal */
    #modal-editar-destinos .select2-container {
        width: 100% !important;
        z-index: auto !important;
    }
    
    #modal-editar-destinos .select2-dropdown {
        z-index: 10001 !important;
        position: absolute !important;
        max-width: 100% !important;
        max-height: 300px !important;
        overflow-y: auto !important;
    }
    
    /* Cuando el modal tiene scroll, el dropdown debe posicionarse correctamente */
    #modal-editar-destinos .select2-container--open {
        z-index: 10002 !important;
    }
    
    #modal-editar-destinos .select2-container--open .select2-dropdown {
        z-index: 10001 !important;
    }
    
    /* Ajustar posicionamiento cuando el dropdown se abre hacia arriba */
    #modal-editar-destinos .select2-container--above .select2-dropdown {
        margin-top: 0 !important;
        margin-bottom: 4px !important;
    }
    
    /* Asegurar que el dropdown sea visible en móvil */
    .gofast-select-down-modal {
        z-index: 10001 !important;
    }
    
    /* Ajustar posicionamiento cuando el dropdown se abre hacia arriba */
    #modal-editar-destinos .select2-container--above .select2-dropdown {
        margin-top: 0 !important;
        margin-bottom: 4px !important;
    }
    
    /* Asegurar que el dropdown no se corte por overflow */
    #modal-editar-destinos .select2-dropdown {
        position: fixed !important;
        max-height: 300px !important;
        overflow-y: auto !important;
    }
    
}

/* Para pantallas muy pequeñas */
@media (max-width: 480px) {
    
    .gofast-pedidos-table-wrapper .gofast-pedidos-table {
        min-width: 900px;
        font-size: 12px;
    }
    
    .gofast-pedidos-table-wrapper .gofast-pedidos-table th,
    .gofast-pedidos-table-wrapper .gofast-pedidos-table td {
        padding: 8px 6px;
        font-size: 11px;
    }
    
    /* Ocultar algunas columnas en móvil muy pequeño */
    .gofast-pedidos-table-wrapper .gofast-pedidos-table th:nth-child(4),
    .gofast-pedidos-table-wrapper .gofast-pedidos-table td:nth-child(4) {
        display: none !important;
    }
    
    .gofast-pedidos-table-wrapper .gofast-pedidos-table th:nth-child(5),
    .gofast-pedidos-table-wrapper .gofast-pedidos-table td:nth-child(5) {
        display: none !important;
    }
}

/* Desktop: Mostrar tabla, ocultar cards */
@media (min-width: 769px) {
    .gofast-pedidos-desktop {
        display: block !important;
    }
    
    .gofast-pedidos-mobile {
        display: none !important;
    }
}
</style>

<?php
    return ob_get_clean();
}
add_shortcode('gofast_pedidos', 'gofast_pedidos_shortcode');



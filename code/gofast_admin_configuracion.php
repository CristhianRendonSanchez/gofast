/***************************************************
 * GOFAST – CONFIGURACIÓN DEL SISTEMA (SOLO ADMIN)
 * Shortcode: [gofast_admin_configuracion]
 * URL: /admin-configuracion
 * 
 * Funcionalidades:
 * - Gestionar tarifas (agregar, editar, eliminar)
 * - Gestionar barrios (agregar, editar, eliminar)
 * - Gestionar sectores (agregar, editar, eliminar)
 * - Gestionar destinos intermunicipales (agregar, editar, eliminar)
 ***************************************************/
function gofast_admin_configuracion_shortcode() {
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
    $tab_activo = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'tarifas';

    /*********************************************
     * PROCESAMIENTO DE FORMULARIOS - TARIFAS
     *********************************************/

    // 1. AGREGAR TARIFAS MASIVAS (desde tabla)
    if (isset($_POST['gofast_crear_tarifas_masivas']) && wp_verify_nonce($_POST['gofast_tarifas_masivas_nonce'], 'gofast_crear_tarifas_masivas')) {
        $tarifas_creadas = 0;
        $tarifas_actualizadas = 0;
        $errores = 0;
        
        if (!empty($_POST['tarifas']) && is_array($_POST['tarifas'])) {
            // Array para rastrear combinaciones procesadas (evitar duplicados)
            $procesadas = [];
            
            foreach ($_POST['tarifas'] as $key => $data) {
                $precio = isset($data['precio']) ? (int) $data['precio'] : 0;
                
                if ($precio <= 0) {
                    continue; // Saltar si no hay precio
                }
                
                // Parsear la clave (formato: origen_destino)
                $partes = explode('_', $key);
                if (count($partes) !== 2) continue;
                
                $origen_id = (int) $partes[0];
                $destino_id = (int) $partes[1];
                
                if ($origen_id <= 0 || $destino_id <= 0) continue;
                
                // Crear clave única para la combinación (ordenar para evitar duplicados)
                $key_combinacion = $origen_id < $destino_id ? $origen_id . '_' . $destino_id : $destino_id . '_' . $origen_id;
                
                // Si ya procesamos esta combinación (o su recíproca), saltar
                if (isset($procesadas[$key_combinacion])) {
                    continue;
                }
                $procesadas[$key_combinacion] = true;
                
                // Verificar si ya existe la tarifa principal
                $existe_principal = $wpdb->get_var($wpdb->prepare(
                    "SELECT id, precio FROM tarifas WHERE origen_sector_id = %d AND destino_sector_id = %d",
                    $origen_id, $destino_id
                ));
                
                // Verificar si existe la recíproca (solo si no es A→A)
                $existe_inversa = null;
                $precio_inversa = 0;
                if ($origen_id != $destino_id) {
                    $existe_inversa = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM tarifas WHERE origen_sector_id = %d AND destino_sector_id = %d",
                        $destino_id, $origen_id
                    ));
                    
                    if ($existe_inversa) {
                        $precio_inversa = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT precio FROM tarifas WHERE id = %d",
                            $existe_inversa
                        ));
                    }
                }
                
                // Determinar el precio final (usar el mayor si ambas existen)
                $precio_final = $precio;
                if ($existe_inversa && $precio_inversa > 0) {
                    $precio_final = max($precio, $precio_inversa);
                }
                
                // Crear o actualizar tarifa principal
                if ($existe_principal) {
                    $actualizado = $wpdb->update(
                        'tarifas',
                        ['precio' => $precio_final],
                        ['id' => $existe_principal],
                        ['%d'],
                        ['%d']
                    );
                    
                    if ($actualizado !== false) {
                        $tarifas_actualizadas++;
                    } else {
                        $errores++;
                    }
                } else {
                    $insertado = $wpdb->insert(
                        'tarifas',
                        [
                            'origen_sector_id' => $origen_id,
                            'destino_sector_id' => $destino_id,
                            'precio' => $precio_final
                        ],
                        ['%d', '%d', '%d']
                    );
                    
                    if ($insertado) {
                        $tarifas_creadas++;
                    } else {
                        $errores++;
                    }
                }
                
                // Si no es la misma ruta (A→A), crear/actualizar la recíproca
                if ($origen_id != $destino_id) {
                    if ($existe_inversa) {
                        // Actualizar la recíproca con el valor mayor
                        $wpdb->update(
                            'tarifas',
                            ['precio' => $precio_final],
                            ['id' => $existe_inversa],
                            ['%d'],
                            ['%d']
                        );
                    } else {
                        // Crear la recíproca con el mismo precio
                        $insertado_inversa = $wpdb->insert(
                            'tarifas',
                            [
                                'origen_sector_id' => $destino_id,
                                'destino_sector_id' => $origen_id,
                                'precio' => $precio_final
                            ],
                            ['%d', '%d', '%d']
                        );
                        
                        if ($insertado_inversa) {
                            $tarifas_creadas++;
                        }
                    }
                }
            }
        }
        
        if ($tarifas_creadas > 0 || $tarifas_actualizadas > 0) {
            $mensaje = "✅ Proceso completado: {$tarifas_creadas} tarifa(s) creada(s), {$tarifas_actualizadas} tarifa(s) actualizada(s)";
            if ($errores > 0) {
                $mensaje .= ", {$errores} error(es)";
            }
            $mensaje .= ". Las tarifas recíprocas se crearon automáticamente.";
            $mensaje_tipo = 'success';
        } else if ($errores > 0) {
            $mensaje = "❌ Se encontraron {$errores} error(es) al procesar las tarifas.";
            $mensaje_tipo = 'error';
        } else {
            $mensaje = "ℹ️ No se procesaron tarifas. Verifica que hayas ingresado precios.";
            $mensaje_tipo = 'error';
        }
    }

    // 1. AGREGAR TARIFA (formulario individual - mantener para compatibilidad)
    if (isset($_POST['gofast_agregar_tarifa']) && wp_verify_nonce($_POST['gofast_tarifa_nonce'], 'gofast_agregar_tarifa')) {
        $origen_sector_id = isset($_POST['origen_sector_id']) ? (int) $_POST['origen_sector_id'] : 0;
        $destino_sector_id = isset($_POST['destino_sector_id']) ? (int) $_POST['destino_sector_id'] : 0;
        $precio = isset($_POST['precio']) ? (int) $_POST['precio'] : 0;

        if ($origen_sector_id <= 0 || $destino_sector_id <= 0 || $precio <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el precio debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            // Verificar si ya existe
            $existe = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM tarifas WHERE origen_sector_id = %d AND destino_sector_id = %d",
                $origen_sector_id, $destino_sector_id
            ));

            if ($existe) {
                $mensaje = 'Ya existe una tarifa para esta combinación de sectores.';
                $mensaje_tipo = 'error';
            } else {
                // Insertar tarifa principal
                $insertado = $wpdb->insert(
                    'tarifas',
                    [
                        'origen_sector_id' => $origen_sector_id,
                        'destino_sector_id' => $destino_sector_id,
                        'precio' => $precio
                    ],
                    ['%d', '%d', '%d']
                );

                if ($insertado) {
                    $user_id = (int) $wpdb->insert_id;
                    
                    // Verificar si ya existe la tarifa inversa
                    $tarifa_inversa = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, precio FROM tarifas WHERE origen_sector_id = %d AND destino_sector_id = %d",
                        $destino_sector_id, $origen_sector_id
                    ));
                    
                    // Si no es la misma ruta (origen != destino)
                    if ($origen_sector_id != $destino_sector_id) {
                        if (!$tarifa_inversa) {
                            // Si no existe la tarifa inversa, crearla con el mismo precio
                            $insertado_inversa = $wpdb->insert(
                                'tarifas',
                                [
                                    'origen_sector_id' => $destino_sector_id,
                                    'destino_sector_id' => $origen_sector_id,
                                    'precio' => $precio
                                ],
                                ['%d', '%d', '%d']
                            );
                            
                            if ($insertado_inversa) {
                                $mensaje = 'Tarifa agregada correctamente. También se creó la tarifa inversa automáticamente.';
                            } else {
                                $mensaje = 'Tarifa agregada correctamente, pero hubo un error al crear la tarifa inversa.';
                            }
                        } else {
                            // Si existe la tarifa inversa, usar el valor mayor y actualizar ambas
                            $precio_inversa = (int) $tarifa_inversa->precio;
                            $precio_mayor = max($precio, $precio_inversa);
                            
                            // Actualizar ambas tarifas con el valor mayor
                            $wpdb->update(
                                'tarifas',
                                ['precio' => $precio_mayor],
                                ['id' => $user_id],
                                ['%d'],
                                ['%d']
                            );
                            
                            $wpdb->update(
                                'tarifas',
                                ['precio' => $precio_mayor],
                                ['id' => $tarifa_inversa->id],
                                ['%d'],
                                ['%d']
                            );
                            
                            $mensaje = 'Tarifa agregada correctamente. Ambas tarifas recíprocas se sincronizaron con el valor mayor (' . number_format($precio_mayor, 0, ',', '.') . ').';
                        }
                    } else {
                        $mensaje = 'Tarifa agregada correctamente.';
                    }
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al agregar la tarifa.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    // 2. EDITAR TARIFA
    if (isset($_POST['gofast_editar_tarifa']) && wp_verify_nonce($_POST['gofast_editar_tarifa_nonce'], 'gofast_editar_tarifa')) {
        $tarifa_id = (int) $_POST['tarifa_id'];
        $origen_sector_id = isset($_POST['origen_sector_id']) ? (int) $_POST['origen_sector_id'] : 0;
        $destino_sector_id = isset($_POST['destino_sector_id']) ? (int) $_POST['destino_sector_id'] : 0;
        $precio = isset($_POST['precio']) ? (int) $_POST['precio'] : 0;

        if ($origen_sector_id <= 0 || $destino_sector_id <= 0 || $precio <= 0) {
            $mensaje = 'Todos los campos son obligatorios y el precio debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            // Verificar si ya existe otra tarifa con la misma combinación (excepto la actual)
            $existe = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM tarifas WHERE origen_sector_id = %d AND destino_sector_id = %d AND id != %d",
                $origen_sector_id, $destino_sector_id, $tarifa_id
            ));

            if ($existe) {
                $mensaje = 'Ya existe una tarifa para esta combinación de sectores.';
                $mensaje_tipo = 'error';
            } else {
                // Obtener la tarifa actual antes de actualizar
                $tarifa_actual = $wpdb->get_row($wpdb->prepare(
                    "SELECT origen_sector_id, destino_sector_id FROM tarifas WHERE id = %d",
                    $tarifa_id
                ));
                
                // Verificar si los sectores cambiaron
                $sectores_cambiaron = false;
                if ($tarifa_actual) {
                    $sectores_cambiaron = (
                        $tarifa_actual->origen_sector_id != $origen_sector_id ||
                        $tarifa_actual->destino_sector_id != $destino_sector_id
                    );
                    
                    // Si los sectores cambiaron, eliminar la tarifa inversa antigua (si existe y no es la misma que la nueva)
                    if ($sectores_cambiaron && $tarifa_actual->origen_sector_id != $tarifa_actual->destino_sector_id) {
                        $tarifa_inversa_antigua = $wpdb->get_row($wpdb->prepare(
                            "SELECT id FROM tarifas WHERE origen_sector_id = %d AND destino_sector_id = %d AND id != %d",
                            $tarifa_actual->destino_sector_id, $tarifa_actual->origen_sector_id, $tarifa_id
                        ));
                        
                        // Solo eliminar si la nueva combinación es diferente a la antigua
                        if ($tarifa_inversa_antigua && 
                            !($tarifa_inversa_antigua->id == $tarifa_id || 
                              ($origen_sector_id == $tarifa_actual->destino_sector_id && 
                               $destino_sector_id == $tarifa_actual->origen_sector_id))) {
                            $wpdb->delete('tarifas', ['id' => $tarifa_inversa_antigua->id], ['%d']);
                        }
                    }
                }
                
                $actualizado = $wpdb->update(
                    'tarifas',
                    [
                        'origen_sector_id' => $origen_sector_id,
                        'destino_sector_id' => $destino_sector_id,
                        'precio' => $precio
                    ],
                    ['id' => $tarifa_id],
                    ['%d', '%d', '%d'],
                    ['%d']
                );

                if ($actualizado !== false) {
                    // Buscar y actualizar/crear la tarifa inversa nueva
                    if ($origen_sector_id != $destino_sector_id) {
                        $tarifa_inversa = $wpdb->get_row($wpdb->prepare(
                            "SELECT id FROM tarifas WHERE origen_sector_id = %d AND destino_sector_id = %d AND id != %d",
                            $destino_sector_id, $origen_sector_id, $tarifa_id
                        ));
                        
                        if ($tarifa_inversa) {
                            // Obtener el precio actual de la tarifa inversa
                            $precio_inversa_actual = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT precio FROM tarifas WHERE id = %d",
                                $tarifa_inversa->id
                            ));
                            
                            // Usar el valor mayor entre ambas tarifas
                            $precio_mayor = max($precio, $precio_inversa_actual);
                            
                            // Actualizar ambas tarifas con el valor mayor
                            $actualizado_inversa = $wpdb->update(
                                'tarifas',
                                ['precio' => $precio_mayor],
                                ['id' => $tarifa_inversa->id],
                                ['%d'],
                                ['%d']
                            );
                            
                            // También actualizar la tarifa principal con el valor mayor si es diferente
                            if ($precio != $precio_mayor) {
                                $wpdb->update(
                                    'tarifas',
                                    ['precio' => $precio_mayor],
                                    ['id' => $tarifa_id],
                                    ['%d'],
                                    ['%d']
                                );
                            }
                            
                            if ($actualizado_inversa !== false) {
                                $mensaje = 'Tarifa actualizada correctamente. Ambas tarifas recíprocas se sincronizaron con el valor mayor (' . number_format($precio_mayor, 0, ',', '.') . ').';
                            } else {
                                $mensaje = 'Tarifa actualizada correctamente, pero hubo un error al actualizar la tarifa inversa.';
                            }
                        } else {
                            // Si no existe la tarifa inversa, crearla
                            $insertado_inversa = $wpdb->insert(
                                'tarifas',
                                [
                                    'origen_sector_id' => $destino_sector_id,
                                    'destino_sector_id' => $origen_sector_id,
                                    'precio' => $precio
                                ],
                                ['%d', '%d', '%d']
                            );
                            
                            if ($insertado_inversa) {
                                $mensaje = 'Tarifa actualizada correctamente. También se creó la tarifa inversa automáticamente.';
                            } else {
                                $mensaje = 'Tarifa actualizada correctamente, pero hubo un error al crear la tarifa inversa.';
                            }
                        }
                    } else {
                        $mensaje = 'Tarifa actualizada correctamente.';
                    }
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al actualizar la tarifa.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    // 3.1. CREAR TARIFAS RECÍPROCAS FALTANTES
    if (isset($_POST['gofast_crear_tarifas_reciprocas']) && wp_verify_nonce($_POST['gofast_reciprocas_nonce'], 'gofast_crear_tarifas_reciprocas')) {
            // Obtener todas las tarifas existentes
            $tarifas_existentes = $wpdb->get_results("SELECT origen_sector_id, destino_sector_id, precio FROM tarifas");
            
            // Crear un mapa de tarifas existentes
            $mapa_tarifas = [];
            foreach ($tarifas_existentes as $t) {
                $key = $t->origen_sector_id . '_' . $t->destino_sector_id;
                $mapa_tarifas[$key] = $t->precio;
            }
            
            // Obtener todos los sectores
            $sectores = $wpdb->get_results("SELECT id FROM sectores ORDER BY id ASC");
            $sectores_ids = array_map(function($s) { return (int) $s->id; }, $sectores);
            
            $creadas = 0;
            $actualizadas = 0;
            $errores = 0;
            
            // Revisar cada tarifa y crear/actualizar su recíproca
            // Usar un array de procesadas para evitar procesar la misma combinación dos veces
            $procesadas = [];
            
            foreach ($tarifas_existentes as $tarifa) {
                $origen = (int) $tarifa->origen_sector_id;
                $destino = (int) $tarifa->destino_sector_id;
                $precio = (int) $tarifa->precio;
                
                // Crear clave única para la combinación (ordenar para evitar duplicados)
                $key_combinacion = $origen < $destino ? $origen . '_' . $destino : $destino . '_' . $origen;
                
                // Si ya procesamos esta combinación, saltar
                if (isset($procesadas[$key_combinacion])) {
                    continue;
                }
                $procesadas[$key_combinacion] = true;
                
                // Si no es la misma ruta (origen != destino), crear/actualizar la recíproca
                if ($origen != $destino) {
                    $key_inversa = $destino . '_' . $origen;
                    
                    if (!isset($mapa_tarifas[$key_inversa])) {
                        // Crear la tarifa recíproca con el mismo precio
                        $insertado = $wpdb->insert(
                            'tarifas',
                            [
                                'origen_sector_id' => $destino,
                                'destino_sector_id' => $origen,
                                'precio' => $precio
                            ],
                            ['%d', '%d', '%d']
                        );
                        
                        if ($insertado) {
                            $creadas++;
                            $mapa_tarifas[$key_inversa] = $precio; // Actualizar mapa para evitar duplicados
                        } else {
                            $errores++;
                        }
                    } else if ($mapa_tarifas[$key_inversa] != $precio) {
                        // Sincronizar usando el valor mayor entre ambas tarifas
                        $precio_mayor = max($precio, $mapa_tarifas[$key_inversa]);
                        
                        // Actualizar ambas tarifas con el valor mayor
                        $actualizado1 = $wpdb->update(
                            'tarifas',
                            ['precio' => $precio_mayor],
                            [
                                'origen_sector_id' => $origen,
                                'destino_sector_id' => $destino
                            ],
                            ['%d'],
                            ['%d', '%d']
                        );
                        
                        $actualizado2 = $wpdb->update(
                            'tarifas',
                            ['precio' => $precio_mayor],
                            [
                                'origen_sector_id' => $destino,
                                'destino_sector_id' => $origen
                            ],
                            ['%d'],
                            ['%d', '%d']
                        );
                        
                        if ($actualizado1 !== false && $actualizado2 !== false) {
                            $actualizadas++;
                            $mapa_tarifas[$key_inversa] = $precio_mayor; // Actualizar mapa
                            $mapa_tarifas[$origen . '_' . $destino] = $precio_mayor; // Actualizar mapa
                        } else {
                            $errores++;
                        }
                    }
                }
            }
            
            // Calcular precio para tarifas del mismo sector (usar precio máximo)
            $precios_mismo_sector = [];
            foreach ($tarifas_existentes as $t) {
                if ((int)$t->origen_sector_id == (int)$t->destino_sector_id) {
                    $precios_mismo_sector[] = (int)$t->precio;
                }
            }
            
            $precio_mismo_sector = 3500;
            if (!empty($precios_mismo_sector)) {
                $precio_mismo_sector = (int) max($precios_mismo_sector);
            } else if (!empty($tarifas_existentes)) {
                $precios_todos_mismo = array_map(function($t) { return (int)$t->precio; }, $tarifas_existentes);
                $precio_mismo_sector = (int) max($precios_todos_mismo);
            }
            
            // Crear tarifas del mismo sector si faltan
            foreach ($sectores_ids as $sector_id) {
                $key_mismo = $sector_id . '_' . $sector_id;
                if (!isset($mapa_tarifas[$key_mismo])) {
                    $insertado = $wpdb->insert(
                        'tarifas',
                        [
                            'origen_sector_id' => $sector_id,
                            'destino_sector_id' => $sector_id,
                            'precio' => $precio_mismo_sector
                        ],
                        ['%d', '%d', '%d']
                    );
                    
                    if ($insertado) {
                        $creadas++;
                    } else {
                        $errores++;
                    }
                }
            }
            
            if ($creadas > 0 || $actualizadas > 0) {
                $mensaje = "✅ Proceso completado: {$creadas} tarifa(s) creada(s), {$actualizadas} tarifa(s) actualizada(s)";
                if ($errores > 0) {
                    $mensaje .= ", {$errores} error(es)";
                }
                $mensaje .= ".";
                $mensaje_tipo = 'success';
            } else if ($errores > 0) {
                $mensaje = "❌ Se encontraron {$errores} error(es) al procesar las tarifas.";
                $mensaje_tipo = 'error';
            } else {
                $mensaje = "ℹ️ Todas las tarifas recíprocas ya están creadas y sincronizadas.";
                $mensaje_tipo = 'success';
            }
    }

    // 3. ELIMINAR TARIFA
    if (isset($_POST['gofast_eliminar_tarifa']) && wp_verify_nonce($_POST['gofast_eliminar_tarifa_nonce'], 'gofast_eliminar_tarifa')) {
        $tarifa_id = (int) $_POST['tarifa_id'];
        
        // Obtener la tarifa antes de eliminar para buscar la inversa
        $tarifa = $wpdb->get_row($wpdb->prepare(
            "SELECT origen_sector_id, destino_sector_id FROM tarifas WHERE id = %d",
            $tarifa_id
        ));

        $eliminado = $wpdb->delete('tarifas', ['id' => $tarifa_id], ['%d']);

        if ($eliminado) {
            // Buscar y eliminar la tarifa inversa si existe
            if ($tarifa && $tarifa->origen_sector_id != $tarifa->destino_sector_id) {
                $tarifa_inversa = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM tarifas WHERE origen_sector_id = %d AND destino_sector_id = %d",
                    $tarifa->destino_sector_id, $tarifa->origen_sector_id
                ));
                
                if ($tarifa_inversa) {
                    $eliminado_inversa = $wpdb->delete('tarifas', ['id' => $tarifa_inversa->id], ['%d']);
                    if ($eliminado_inversa) {
                        $mensaje = 'Tarifa eliminada correctamente. También se eliminó la tarifa inversa automáticamente.';
                    } else {
                        $mensaje = 'Tarifa eliminada correctamente, pero hubo un error al eliminar la tarifa inversa.';
                    }
                } else {
                    $mensaje = 'Tarifa eliminada correctamente.';
                }
            } else {
                $mensaje = 'Tarifa eliminada correctamente.';
            }
            $mensaje_tipo = 'success';
        } else {
            $mensaje = 'Error al eliminar la tarifa.';
            $mensaje_tipo = 'error';
        }
    }

    /*********************************************
     * PROCESAMIENTO DE FORMULARIOS - BARRIOS
     *********************************************/

    // 1. AGREGAR BARRIO
    if (isset($_POST['gofast_agregar_barrio']) && wp_verify_nonce($_POST['gofast_barrio_nonce'], 'gofast_agregar_barrio')) {
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $sector_id = isset($_POST['sector_id']) ? (int) $_POST['sector_id'] : 0;

        if (empty($nombre) || $sector_id <= 0) {
            $mensaje = 'Todos los campos son obligatorios.';
            $mensaje_tipo = 'error';
        } else {
            // Verificar si ya existe
            $existe = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM barrios WHERE nombre = %s",
                $nombre
            ));

            if ($existe) {
                $mensaje = 'Ya existe un barrio con ese nombre.';
                $mensaje_tipo = 'error';
            } else {
                // Obtener el último ID
                $ultimo_id = (int) $wpdb->get_var("SELECT MAX(id) FROM barrios");
                $nuevo_id = $ultimo_id + 1;

                $insertado = $wpdb->insert(
                    'barrios',
                    [
                        'id' => $nuevo_id,
                        'nombre' => $nombre,
                        'sector_id' => $sector_id
                    ],
                    ['%d', '%s', '%d']
                );

                if ($insertado) {
                    $mensaje = 'Barrio agregado correctamente.';
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al agregar el barrio.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    // 2. EDITAR BARRIO
    if (isset($_POST['gofast_editar_barrio']) && wp_verify_nonce($_POST['gofast_editar_barrio_nonce'], 'gofast_editar_barrio')) {
        $barrio_id = (int) $_POST['barrio_id'];
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $sector_id = isset($_POST['sector_id']) ? (int) $_POST['sector_id'] : 0;

        if (empty($nombre) || $sector_id <= 0) {
            $mensaje = 'Todos los campos son obligatorios.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'barrios',
                [
                    'nombre' => $nombre,
                    'sector_id' => $sector_id
                ],
                ['id' => $barrio_id],
                ['%s', '%d'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Barrio actualizado correctamente.';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al actualizar el barrio.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR BARRIO
    if (isset($_POST['gofast_eliminar_barrio']) && wp_verify_nonce($_POST['gofast_eliminar_barrio_nonce'], 'gofast_eliminar_barrio')) {
        $barrio_id = (int) $_POST['barrio_id'];

        // Verificar si hay negocios o servicios usando este barrio
        $usado_negocios = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM negocios_gofast WHERE barrio_id = %d",
            $barrio_id
        ));

        if ($usado_negocios > 0) {
            $mensaje = 'No se puede eliminar el barrio porque está siendo usado por ' . $usado_negocios . ' negocio(s).';
            $mensaje_tipo = 'error';
        } else {
            $eliminado = $wpdb->delete('barrios', ['id' => $barrio_id], ['%d']);

            if ($eliminado) {
                $mensaje = 'Barrio eliminado correctamente.';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al eliminar el barrio.';
                $mensaje_tipo = 'error';
            }
        }
    }

    /*********************************************
     * PROCESAMIENTO DE FORMULARIOS - SECTORES
     *********************************************/

    // 1. AGREGAR SECTOR
    if (isset($_POST['gofast_agregar_sector']) && wp_verify_nonce($_POST['gofast_sector_nonce'], 'gofast_agregar_sector')) {
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $sector_id = isset($_POST['sector_id']) ? (int) $_POST['sector_id'] : 0;

        if (empty($nombre)) {
            $mensaje = 'El nombre del sector es obligatorio.';
            $mensaje_tipo = 'error';
        } elseif ($sector_id <= 0) {
            $mensaje = 'El ID del sector debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            // Verificar si ya existe un sector con ese ID
            $existe_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM sectores WHERE id = %d",
                $sector_id
            ));

            if ($existe_id) {
                $mensaje = 'Ya existe un sector con ese ID.';
                $mensaje_tipo = 'error';
            } else {
                // Verificar si ya existe un sector con ese nombre
                $existe_nombre = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM sectores WHERE nombre = %s",
                    $nombre
                ));

                if ($existe_nombre) {
                    $mensaje = 'Ya existe un sector con ese nombre.';
                    $mensaje_tipo = 'error';
                } else {
                    $insertado = $wpdb->insert(
                        'sectores',
                        [
                            'id' => $sector_id,
                            'nombre' => $nombre
                        ],
                        ['%d', '%s']
                    );

                    if ($insertado) {
                        $mensaje = 'Sector agregado correctamente.';
                        $mensaje_tipo = 'success';
                    } else {
                        $mensaje = 'Error al agregar el sector.';
                        $mensaje_tipo = 'error';
                    }
                }
            }
        }
    }

    // 2. EDITAR SECTOR
    if (isset($_POST['gofast_editar_sector']) && wp_verify_nonce($_POST['gofast_editar_sector_nonce'], 'gofast_editar_sector')) {
        $sector_id = (int) $_POST['sector_id'];
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');

        if (empty($nombre)) {
            $mensaje = 'El nombre del sector es obligatorio.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'sectores',
                ['nombre' => $nombre],
                ['id' => $sector_id],
                ['%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Sector actualizado correctamente.';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al actualizar el sector.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR SECTOR
    if (isset($_POST['gofast_eliminar_sector']) && wp_verify_nonce($_POST['gofast_eliminar_sector_nonce'], 'gofast_eliminar_sector')) {
        $sector_id = (int) $_POST['sector_id'];

        // Verificar si hay barrios usando este sector
        $usado_barrios = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM barrios WHERE sector_id = %d",
            $sector_id
        ));

        if ($usado_barrios > 0) {
            $mensaje = 'No se puede eliminar el sector porque está siendo usado por ' . $usado_barrios . ' barrio(s).';
            $mensaje_tipo = 'error';
        } else {
            $eliminado = $wpdb->delete('sectores', ['id' => $sector_id], ['%d']);

            if ($eliminado) {
                $mensaje = 'Sector eliminado correctamente.';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al eliminar el sector.';
                $mensaje_tipo = 'error';
            }
        }
    }

    /*********************************************
     * PROCESAMIENTO DE FORMULARIOS - DESTINOS INTERMUNICIPALES
     *********************************************/

    // 1. AGREGAR DESTINO INTERMUNICIPAL
    if (isset($_POST['gofast_agregar_destino_intermunicipal']) && wp_verify_nonce($_POST['gofast_destino_intermunicipal_nonce'], 'gofast_agregar_destino_intermunicipal')) {
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $valor = isset($_POST['valor']) ? (int) $_POST['valor'] : 0;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $orden = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;

        if (empty($nombre) || $valor <= 0) {
            $mensaje = 'El nombre y el valor son obligatorios. El valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            // Verificar si ya existe
            $existe = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM destinos_intermunicipales WHERE nombre = %s",
                $nombre
            ));

            if ($existe) {
                $mensaje = 'Ya existe un destino intermunicipal con ese nombre.';
                $mensaje_tipo = 'error';
            } else {
                $insertado = $wpdb->insert(
                    'destinos_intermunicipales',
                    [
                        'nombre' => $nombre,
                        'valor' => $valor,
                        'activo' => $activo,
                        'orden' => $orden,
                        'created_at' => gofast_date_mysql()
                    ],
                    ['%s', '%d', '%d', '%d', '%s']
                );

                if ($insertado) {
                    $mensaje = 'Destino intermunicipal agregado correctamente.';
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al agregar el destino intermunicipal.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    // 2. EDITAR DESTINO INTERMUNICIPAL
    if (isset($_POST['gofast_editar_destino_intermunicipal']) && wp_verify_nonce($_POST['gofast_editar_destino_intermunicipal_nonce'], 'gofast_editar_destino_intermunicipal')) {
        $destino_id = (int) $_POST['destino_id'];
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $valor = isset($_POST['valor']) ? (int) $_POST['valor'] : 0;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $orden = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;

        if (empty($nombre) || $valor <= 0) {
            $mensaje = 'El nombre y el valor son obligatorios. El valor debe ser mayor a cero.';
            $mensaje_tipo = 'error';
        } else {
            $actualizado = $wpdb->update(
                'destinos_intermunicipales',
                [
                    'nombre' => $nombre,
                    'valor' => $valor,
                    'activo' => $activo,
                    'orden' => $orden,
                    'updated_at' => gofast_current_time('mysql')
                ],
                ['id' => $destino_id],
                ['%s', '%d', '%d', '%d', '%s'],
                ['%d']
            );

            if ($actualizado !== false) {
                $mensaje = 'Destino intermunicipal actualizado correctamente.';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al actualizar el destino intermunicipal.';
                $mensaje_tipo = 'error';
            }
        }
    }

    // 3. ELIMINAR DESTINO INTERMUNICIPAL
    if (isset($_POST['gofast_eliminar_destino_intermunicipal']) && wp_verify_nonce($_POST['gofast_eliminar_destino_intermunicipal_nonce'], 'gofast_eliminar_destino_intermunicipal')) {
        $destino_id = (int) $_POST['destino_id'];

        $eliminado = $wpdb->delete('destinos_intermunicipales', ['id' => $destino_id], ['%d']);

        if ($eliminado) {
            $mensaje = 'Destino intermunicipal eliminado correctamente.';
            $mensaje_tipo = 'success';
        } else {
            $mensaje = 'Error al eliminar el destino intermunicipal.';
            $mensaje_tipo = 'error';
        }
    }

    /*********************************************
     * OBTENER DATOS
     *********************************************/

    // Primero definir todas las variables de filtros y paginación
    
    // Filtros de búsqueda para barrios
    $filtro_barrio_sector = isset($_GET['filtro_barrio_sector']) ? (int) $_GET['filtro_barrio_sector'] : 0;
    $filtro_barrio_texto = isset($_GET['filtro_barrio_texto']) ? sanitize_text_field($_GET['filtro_barrio_texto']) : '';
    
    // Paginación para barrios
    $por_pagina_barrios = 15;
    $pg_barrios = isset($_GET['pg_barrios']) ? max(1, (int) $_GET['pg_barrios']) : 1;
    
    // Filtros de búsqueda para sectores
    $filtro_sector_texto = isset($_GET['filtro_sector_texto']) ? sanitize_text_field($_GET['filtro_sector_texto']) : '';
    
    // Paginación para sectores
    $por_pagina_sectores = 15;
    $pg_sectores = isset($_GET['pg_sectores']) ? max(1, (int) $_GET['pg_sectores']) : 1;

    // Construir WHERE clause para filtros de sectores
    $where_sectores = "1=1";
    $params_sectores = [];
    
    if (!empty($filtro_sector_texto)) {
        // Intentar convertir el texto a número para buscar por ID
        $filtro_sector_id = is_numeric($filtro_sector_texto) ? (int) $filtro_sector_texto : 0;
        if ($filtro_sector_id > 0) {
            // Si es un número, buscar por ID o nombre
            $where_sectores .= " AND (nombre LIKE %s OR id = %d)";
            $params_sectores[] = '%' . $wpdb->esc_like($filtro_sector_texto) . '%';
            $params_sectores[] = $filtro_sector_id;
        } else {
            // Si no es número, solo buscar por nombre
            $where_sectores .= " AND nombre LIKE %s";
            $params_sectores[] = '%' . $wpdb->esc_like($filtro_sector_texto) . '%';
        }
    }
    
    // Contar total de sectores con filtros
    if (!empty($params_sectores)) {
        $sql_count_sectores = $wpdb->prepare(
            "SELECT COUNT(*) FROM sectores WHERE $where_sectores",
            $params_sectores
        );
    } else {
        $sql_count_sectores = "SELECT COUNT(*) FROM sectores WHERE $where_sectores";
    }
    $total_sectores = (int) $wpdb->get_var($sql_count_sectores);
    $total_paginas_sectores = max(1, (int) ceil($total_sectores / $por_pagina_sectores));
    $offset_sectores = ($pg_sectores - 1) * $por_pagina_sectores;
    
    // Obtener sectores con paginación y filtros
    $params_query_sectores = $params_sectores;
    $params_query_sectores[] = $por_pagina_sectores;
    $params_query_sectores[] = $offset_sectores;
    
    if (!empty($params_sectores)) {
        $sectores = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, nombre FROM sectores WHERE $where_sectores ORDER BY id ASC LIMIT %d OFFSET %d",
                $params_query_sectores
            )
        );
    } else {
        $sectores = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, nombre FROM sectores WHERE $where_sectores ORDER BY id ASC LIMIT %d OFFSET %d",
                $por_pagina_sectores,
                $offset_sectores
            )
        );
    }
    
    // Obtener todos los sectores para los formularios (sin paginación)
    $sectores_todos = $wpdb->get_results("SELECT id, nombre FROM sectores ORDER BY id ASC");

    // Filtros de búsqueda para tarifas
    $filtro_origen_id = isset($_GET['filtro_origen']) ? (int) $_GET['filtro_origen'] : 0;
    $filtro_destino_id = isset($_GET['filtro_destino']) ? (int) $_GET['filtro_destino'] : 0;
    $filtro_precio = isset($_GET['filtro_precio']) ? (int) $_GET['filtro_precio'] : 0;
    
    // Paginación para tarifas
    $por_pagina_tarifas = 15;
    $pg_tarifas = isset($_GET['pg_tarifas']) ? max(1, (int) $_GET['pg_tarifas']) : 1;
    
    // Construir WHERE clause para filtros de tarifas
    $where_tarifas = "1=1";
    $params_tarifas = [];
    
    if ($filtro_origen_id > 0) {
        $where_tarifas .= " AND t.origen_sector_id = %d";
        $params_tarifas[] = $filtro_origen_id;
    }
    
    if ($filtro_destino_id > 0) {
        $where_tarifas .= " AND t.destino_sector_id = %d";
        $params_tarifas[] = $filtro_destino_id;
    }
    
    if ($filtro_precio > 0) {
        $where_tarifas .= " AND t.precio = %d";
        $params_tarifas[] = $filtro_precio;
    }
    
    // Contar total de tarifas con filtros
    if (!empty($params_tarifas)) {
        $sql_count = $wpdb->prepare(
            "SELECT COUNT(*) FROM tarifas t WHERE $where_tarifas",
            $params_tarifas
        );
    } else {
        $sql_count = "SELECT COUNT(*) FROM tarifas t WHERE $where_tarifas";
    }
    $total_tarifas = (int) $wpdb->get_var($sql_count);
    $total_paginas_tarifas = max(1, (int) ceil($total_tarifas / $por_pagina_tarifas));
    $offset_tarifas = ($pg_tarifas - 1) * $por_pagina_tarifas;
    
    // Obtener tarifas con información de sectores (con paginación y filtros)
    $params_query = $params_tarifas;
    $params_query[] = $por_pagina_tarifas;
    $params_query[] = $offset_tarifas;
    
    if (!empty($params_tarifas)) {
        $tarifas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, 
                        so.nombre as origen_nombre,
                        sd.nombre as destino_nombre
                 FROM tarifas t
                 LEFT JOIN sectores so ON t.origen_sector_id = so.id
                 LEFT JOIN sectores sd ON t.destino_sector_id = sd.id
                 WHERE $where_tarifas
                 ORDER BY t.origen_sector_id, t.destino_sector_id
                 LIMIT %d OFFSET %d",
                $params_query
            )
        );
    } else {
        $tarifas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, 
                        so.nombre as origen_nombre,
                        sd.nombre as destino_nombre
                 FROM tarifas t
                 LEFT JOIN sectores so ON t.origen_sector_id = so.id
                 LEFT JOIN sectores sd ON t.destino_sector_id = sd.id
                 WHERE $where_tarifas
                 ORDER BY t.origen_sector_id, t.destino_sector_id
                 LIMIT %d OFFSET %d",
                $por_pagina_tarifas,
                $offset_tarifas
            )
        );
    }
    
    // Obtener todas las tarifas para el mapa de recíprocas (sin paginación)
    $todas_las_tarifas = $wpdb->get_results(
        "SELECT t.*, 
                so.nombre as origen_nombre,
                sd.nombre as destino_nombre
         FROM tarifas t
         LEFT JOIN sectores so ON t.origen_sector_id = so.id
         LEFT JOIN sectores sd ON t.destino_sector_id = sd.id"
    );
    
    // Crear mapa de tarifas para buscar recíprocas rápidamente
    $mapa_tarifas_para_reciproca = [];
    foreach ($todas_las_tarifas as $t) {
        $key = (int)$t->origen_sector_id . '_' . (int)$t->destino_sector_id;
        $mapa_tarifas_para_reciproca[$key] = $t;
    }
    
    // Construir matriz de tarifas existentes: origen_id => [destino_ids]
    $tarifas_existentes = [];
    foreach ($tarifas as $t) {
        $origen_id = (int) $t->origen_sector_id;
        $destino_id = (int) $t->destino_sector_id;
        if (!isset($tarifas_existentes[$origen_id])) {
            $tarifas_existentes[$origen_id] = [];
        }
        $tarifas_existentes[$origen_id][] = $destino_id;
    }
    
    // Generar combinaciones faltantes (solo A→B donde A <= B para evitar duplicados)
    // Incluye mismo sector (A→A) y solo una dirección por par (A→B donde A < B)
    // Ya que las tarifas recíprocas se crean automáticamente
    $combinaciones_faltantes = [];
    $sectores_ids = array_map(function($s) { return (int) $s->id; }, $sectores_todos);
    
    foreach ($sectores_ids as $origen_id) {
        foreach ($sectores_ids as $destino_id) {
            // Solo considerar combinaciones donde origen_id <= destino_id para evitar duplicados
            // Esto incluye: mismo sector (A→A) y solo una dirección por par (A→B donde A < B)
            // (las recíprocas se crean automáticamente)
            if ($origen_id > $destino_id) {
                continue;
            }
            
            // Verificar si esta combinación ya existe (en cualquier dirección)
            $existe = false;
            // Verificar A→B
            if (isset($tarifas_existentes[$origen_id]) && in_array($destino_id, $tarifas_existentes[$origen_id])) {
                $existe = true;
            }
            // Verificar B→A (recíproca) solo si no es mismo sector
            if (!$existe && $origen_id != $destino_id && isset($tarifas_existentes[$destino_id]) && in_array($origen_id, $tarifas_existentes[$destino_id])) {
                $existe = true;
            }
            
            if (!$existe) {
                // Esta combinación falta (mostramos A→B donde A <= B)
                if (!isset($combinaciones_faltantes[$origen_id])) {
                    $combinaciones_faltantes[$origen_id] = [];
                }
                $combinaciones_faltantes[$origen_id][] = $destino_id;
            }
        }
    }
    
    // Convertir a JSON para usar en JavaScript
    $combinaciones_faltantes_json = json_encode($combinaciones_faltantes);

    // Obtener barrios con información de sectores (limitado para optimización)
    // Construir WHERE clause para filtros de barrios
    $where_barrios = "1=1";
    $params_barrios = [];
    
    if ($filtro_barrio_sector > 0) {
        $where_barrios .= " AND b.sector_id = %d";
        $params_barrios[] = $filtro_barrio_sector;
    }
    
    if (!empty($filtro_barrio_texto)) {
        $where_barrios .= " AND b.nombre LIKE %s";
        $params_barrios[] = '%' . $wpdb->esc_like($filtro_barrio_texto) . '%';
    }
    
    // Contar total de barrios con filtros
    if (!empty($params_barrios)) {
        $sql_count_barrios = $wpdb->prepare(
            "SELECT COUNT(*) FROM barrios b WHERE $where_barrios",
            $params_barrios
        );
    } else {
        $sql_count_barrios = "SELECT COUNT(*) FROM barrios b WHERE $where_barrios";
    }
    $total_barrios = (int) $wpdb->get_var($sql_count_barrios);
    $total_paginas_barrios = max(1, (int) ceil($total_barrios / $por_pagina_barrios));
    $offset_barrios = ($pg_barrios - 1) * $por_pagina_barrios;
    
    // Obtener barrios con paginación y filtros
    $params_query_barrios = $params_barrios;
    $params_query_barrios[] = $por_pagina_barrios;
    $params_query_barrios[] = $offset_barrios;
    
    if (!empty($params_barrios)) {
        $barrios = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.*, s.nombre as sector_nombre
                 FROM barrios b
                 LEFT JOIN sectores s ON b.sector_id = s.id
                 WHERE $where_barrios
                 ORDER BY b.nombre ASC
                 LIMIT %d OFFSET %d",
                $params_query_barrios
            )
        );
    } else {
        $barrios = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.*, s.nombre as sector_nombre
                 FROM barrios b
                 LEFT JOIN sectores s ON b.sector_id = s.id
                 WHERE $where_barrios
                 ORDER BY b.nombre ASC
                 LIMIT %d OFFSET %d",
                $por_pagina_barrios,
                $offset_barrios
            )
        );
    }

    // Obtener destinos intermunicipales
    $destinos_intermunicipales = $wpdb->get_results(
        "SELECT * FROM destinos_intermunicipales ORDER BY orden ASC, nombre ASC"
    );

    ob_start();
    ?>

<div class="gofast-home">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin-bottom:8px;">⚙️ Configuración del Sistema</h1>
            <p class="gofast-home-text" style="margin:0;">
                Gestiona tarifas, barrios, sectores y destinos intermunicipales.
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

    <!-- Tabs de navegación -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 2px solid #ddd; margin-bottom: 20px;">
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'tarifas' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTab('tarifas')">
                💰 Tarifas
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'barrios' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTab('barrios')">
                📍 Barrios
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'sectores' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTab('sectores')">
                🗺️ Sectores
            </button>
            <button type="button" 
                    class="gofast-config-tab <?= $tab_activo === 'intermunicipales' ? 'gofast-config-tab-active' : '' ?>"
                    onclick="mostrarTab('intermunicipales')">
                🌐 Destinos Intermunicipales
            </button>
        </div>

        <!-- TAB: TARIFAS -->
        <div id="tab-tarifas" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'tarifas' ? 'block' : 'none' ?>;">
            <?php 
            // Si hay paginación de tarifas, asumir que estamos en 'gestion', de lo contrario usar 'agregar' por defecto
            $sub_tab_default = 'agregar';
            if (isset($_GET['pg_tarifas']) || (isset($_GET['sub_tab']) && $_GET['sub_tab'] === 'gestion')) {
                $sub_tab_default = 'gestion';
            }
            $sub_tab_tarifas = isset($_GET['sub_tab']) ? sanitize_text_field($_GET['sub_tab']) : $sub_tab_default;
            ?>
            <!-- Sub-tabs para tarifas -->
            <div style="display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 2px solid #ddd; margin-bottom: 20px;">
                <button type="button" 
                        class="gofast-config-tab <?= $sub_tab_tarifas === 'agregar' ? 'gofast-config-tab-active' : '' ?>"
                        onclick="mostrarSubTabTarifas('agregar')">
                    ➕ Agregar Tarifa
                </button>
                <button type="button" 
                        class="gofast-config-tab <?= $sub_tab_tarifas === 'gestion' ? 'gofast-config-tab-active' : '' ?>"
                        onclick="mostrarSubTabTarifas('gestion')">
                    💰 Gestión de Tarifas
                </button>
                <button type="button" 
                        class="gofast-config-tab <?= $sub_tab_tarifas === 'faltantes' ? 'gofast-config-tab-active' : '' ?>"
                        onclick="mostrarSubTabTarifas('faltantes')">
                    🔍 Tarifas Faltantes
                </button>
            </div>
            
            <!-- SUB-TAB: AGREGAR TARIFA -->
            <div id="sub-tab-tarifas-agregar" style="display: <?= $sub_tab_tarifas === 'agregar' ? 'block' : 'none' ?>;">
            <h3>➕ Crear Tarifas Faltantes</h3>
            
            <?php
            // Verificar que haya sectores
            if (empty($sectores)) {
                echo '<div class="gofast-box" style="background: #f8d7da; border-left: 4px solid #dc3545; margin-bottom: 20px;">';
                echo '<p style="margin: 0; color: #721c24;">⚠️ No hay sectores registrados. Por favor, crea sectores primero.</p>';
                echo '</div>';
            } else {
                // Obtener todas las tarifas existentes para identificar las faltantes
                $tarifas_existentes_agregar = $wpdb->get_results("SELECT origen_sector_id, destino_sector_id FROM tarifas");
                $mapa_tarifas_existentes = [];
                foreach ($tarifas_existentes_agregar as $t) {
                    $key = (int)$t->origen_sector_id . '_' . (int)$t->destino_sector_id;
                    $mapa_tarifas_existentes[$key] = true;
                }
                
                // Generar combinaciones faltantes (solo A→B donde A <= B para evitar duplicados)
                // Incluye mismo sector (A→A) y solo una dirección por par (A→B donde A < B)
                // Ya que las tarifas recíprocas se crean automáticamente
                $tuplas_faltantes = [];
                $sectores_ids_agregar = array_map(function($s) { return (int) $s->id; }, $sectores_todos);
                
                foreach ($sectores_ids_agregar as $origen_id) {
                    foreach ($sectores_ids_agregar as $destino_id) {
                        // Solo considerar combinaciones donde origen_id <= destino_id para evitar duplicados
                        // Esto incluye: mismo sector (A→A) y solo una dirección por par (A→B donde A < B)
                        // (las recíprocas se crean automáticamente)
                        if ($origen_id > $destino_id) {
                            continue;
                        }
                        
                        $key = $origen_id . '_' . $destino_id;
                        $key_reciproca = $destino_id . '_' . $origen_id;
                        
                        // Verificar si existe A→B o B→A (recíproca, solo si no es mismo sector)
                        $existe = isset($mapa_tarifas_existentes[$key]);
                        if (!$existe && $origen_id != $destino_id) {
                            $existe = isset($mapa_tarifas_existentes[$key_reciproca]);
                        }
                        
                        // Si no existe ninguna de las dos direcciones, agregarla a la lista
                        if (!$existe) {
                            $origen_nombre = '';
                            $destino_nombre = '';
                            foreach ($sectores_todos as $s) {
                                if ((int)$s->id == $origen_id) $origen_nombre = $s->nombre;
                                if ((int)$s->id == $destino_id) $destino_nombre = $s->nombre;
                            }
                            
                            $tuplas_faltantes[] = [
                                'origen_id' => $origen_id,
                                'origen_nombre' => $origen_nombre,
                                'destino_id' => $destino_id,
                                'destino_nombre' => $destino_nombre,
                                'key' => $key,
                                'es_mismo_sector' => ($origen_id == $destino_id)
                            ];
                        }
                    }
                }
                
                // Calcular precio sugerido (valor mayor de tarifas del mismo sector o máximo general)
                $tarifas_con_precio = $wpdb->get_results("SELECT origen_sector_id, destino_sector_id, precio FROM tarifas");
                $precios_mismo_sector_sug = [];
                $precios_todos_sug = [];
                
                foreach ($tarifas_con_precio as $t) {
                    $precio = (int) $t->precio;
                    if ((int)$t->origen_sector_id == (int)$t->destino_sector_id) {
                        $precios_mismo_sector_sug[] = $precio;
                    }
                    $precios_todos_sug[] = $precio;
                }
                
                $precio_sugerido = 3500;
                if (!empty($precios_mismo_sector_sug)) {
                    $precio_sugerido = (int) max($precios_mismo_sector_sug);
                } else if (!empty($precios_todos_sug)) {
                    $precio_sugerido = (int) max($precios_todos_sug);
                }
            }
            ?>
            
            <?php if (!empty($sectores)): ?>
            <div class="gofast-box" style="background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px;">
                <p style="margin: 0; color: #856404; font-size: 13px;">
                    <strong>ℹ️ Información:</strong> Esta tabla muestra todas las combinaciones de sectores que aún no tienen tarifa. 
                    Al crear una tarifa A→B, automáticamente se creará la recíproca B→A con el mismo precio (excepto A→A que es única).
                </p>
            </div>
            
            <?php if (empty($tuplas_faltantes)): ?>
                <div class="gofast-box" style="background: #d4edda; border-left: 4px solid #28a745; margin-bottom: 20px;">
                    <p style="margin: 0; color: #155724;">
                        ✅ <strong>¡Perfecto!</strong> Todas las combinaciones de sectores ya tienen tarifa creada.
                    </p>
                </div>
            <?php else: ?>
                <form method="post" id="form-crear-tarifas-masivas">
                    <?php wp_nonce_field('gofast_crear_tarifas_masivas', 'gofast_tarifas_masivas_nonce'); ?>
                    <div class="gofast-box" style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin: 0;">📋 Tarifas Faltantes (<?= count($tuplas_faltantes) ?>)</h4>
                            <button type="submit" 
                                    name="gofast_crear_tarifas_masivas" 
                                    class="gofast-btn-mini"
                                    style="background: #28a745; color: #fff; padding: 10px 20px; font-weight: 600;"
                                    onclick="return confirm('¿Estás seguro de crear las tarifas con precio ingresado? Las recíprocas se crearán automáticamente.');">
                                ✅ Guardar Todas las Tarifas
                            </button>
                        </div>
                        
                        <div class="gofast-table-wrap" style="overflow-x: auto; max-height: 600px; overflow-y: auto;">
                            <table class="gofast-table" style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Sector Origen</th>
                                        <th>Sector Destino</th>
                                        <th>Tipo</th>
                                        <th style="width: 200px;">Precio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tuplas_faltantes as $idx => $tupla): ?>
                                        <tr>
                                            <td><strong><?= $idx + 1 ?></strong></td>
                                            <td>
                                                <strong>Sector <?= $tupla['origen_id'] ?></strong>
                                                <br><small style="color: #666;"><?= esc_html($tupla['origen_nombre'] ?: 'N/A') ?></small>
                                            </td>
                                            <td>
                                                <strong>Sector <?= $tupla['destino_id'] ?></strong>
                                                <br><small style="color: #666;"><?= esc_html($tupla['destino_nombre'] ?: 'N/A') ?></small>
                                            </td>
                                            <td>
                                                <?php if ($tupla['es_mismo_sector']): ?>
                                                    <span style="background: #17a2b8; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Mismo Sector</span>
                                                <?php else: ?>
                                                    <span style="background: #6c757d; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Recíproca</span>
                                                    <br><small style="color: #666; font-size: 10px;">Se creará también <?= $tupla['destino_id'] ?>→<?= $tupla['origen_id'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       name="tarifas[<?= esc_attr($tupla['key']) ?>][precio]" 
                                                       min="1" 
                                                       step="1"
                                                       placeholder="<?= number_format($precio_sugerido, 0, ',', '.') ?>"
                                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; text-align: right;">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
            <?php endif; ?>
            </div>
            
            <!-- SUB-TAB: GESTIÓN -->
            <div id="sub-tab-tarifas-gestion" style="display: <?= $sub_tab_tarifas === 'gestion' ? 'block' : 'none' ?>;">
            <h3>💰 Gestión de Tarifas</h3>

            <!-- Filtros de búsqueda -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <form method="get" action="" id="form-filtros-tarifas">
                    <input type="hidden" name="tab" value="tarifas">
                    <input type="hidden" name="sub_tab" value="gestion">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h4 style="margin: 0;">🔍 Buscar Tarifas</h4>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" 
                                    class="gofast-btn-mini" 
                                    style="background: #28a745; color: #fff; padding: 6px 12px; font-size: 12px;">
                                🔍 Buscar
                            </button>
                            <a href="<?php echo esc_url(remove_query_arg(['filtro_origen', 'filtro_destino', 'filtro_precio', 'pg_tarifas'], add_query_arg(['tab' => 'tarifas', 'sub_tab' => 'gestion']))); ?>" 
                               class="gofast-btn-mini" 
                               style="background: #6c757d; color: #fff; padding: 6px 12px; font-size: 12px; text-decoration: none;">
                                🗑️ Limpiar Filtros
                            </a>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">ID Sector Origen:</label>
                            <input type="number" 
                                   name="filtro_origen" 
                                   id="filtro-tarifa-origen" 
                                   value="<?= $filtro_origen_id > 0 ? esc_attr($filtro_origen_id) : '' ?>"
                                   placeholder="Ej: 12"
                                   min="1"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">ID Sector Destino:</label>
                            <input type="number" 
                                   name="filtro_destino" 
                                   id="filtro-tarifa-destino" 
                                   value="<?= $filtro_destino_id > 0 ? esc_attr($filtro_destino_id) : '' ?>"
                                   placeholder="Ej: 15"
                                   min="1"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Buscar por precio (exacto):</label>
                            <input type="number" 
                                   name="filtro_precio" 
                                   id="filtro-tarifa-precio" 
                                   value="<?= $filtro_precio > 0 ? esc_attr($filtro_precio) : '' ?>"
                                   placeholder="Ej: 3500"
                                   min="1"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                </form>
            </div>

            <!-- Listado de tarifas -->
            <?php if (empty($tarifas)): ?>
                <p style="text-align: center; color: #666; padding: 20px;">
                    No hay tarifas registradas.
                </p>
            <?php else: ?>
                <div class="gofast-table-wrap" style="overflow-x: auto;">
                    <table class="gofast-table" id="tabla-tarifas" style="min-width: 600px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Sector Origen</th>
                                <th>Sector Destino</th>
                                <th>Precio</th>
                                <th>Recíproca</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tarifas as $t): 
                                // Buscar tarifa recíproca
                                $origen_id = (int) $t->origen_sector_id;
                                $destino_id = (int) $t->destino_sector_id;
                                $key_reciproca = $destino_id . '_' . $origen_id;
                                $tarifa_reciproca = null;
                                $es_mismo_sector = ($origen_id == $destino_id);
                                
                                if (!$es_mismo_sector && isset($mapa_tarifas_para_reciproca[$key_reciproca])) {
                                    $tarifa_reciproca = $mapa_tarifas_para_reciproca[$key_reciproca];
                                }
                            ?>
                                <tr data-origen-id="<?= esc_attr($t->origen_sector_id) ?>" 
                                    data-destino-id="<?= esc_attr($t->destino_sector_id) ?>"
                                    data-origen-nombre="<?= esc_attr(strtolower($t->origen_nombre ?? '')) ?>"
                                    data-destino-nombre="<?= esc_attr(strtolower($t->destino_nombre ?? '')) ?>"
                                    data-precio="<?= esc_attr($t->precio) ?>"
                                    <?php if ($tarifa_reciproca): ?>
                                        data-reciproca-id="<?= esc_attr($tarifa_reciproca->id) ?>"
                                        data-reciproca-precio="<?= esc_attr($tarifa_reciproca->precio) ?>"
                                    <?php endif; ?>>
                                    <td>#<?= esc_html($t->id) ?></td>
                                    <td>
                                        <strong><?= esc_html($t->origen_nombre ?? 'N/A') ?></strong>
                                        <br><small style="color: #666;">ID: <?= $origen_id ?></small>
                                    </td>
                                    <td>
                                        <strong><?= esc_html($t->destino_nombre ?? 'N/A') ?></strong>
                                        <br><small style="color: #666;">ID: <?= $destino_id ?></small>
                                    </td>
                                    <td><strong>$<?= number_format($t->precio, 0, ',', '.') ?></strong></td>
                                    <td>
                                        <?php if ($es_mismo_sector): ?>
                                            <span style="background: #17a2b8; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Mismo Sector</span>
                                        <?php elseif ($tarifa_reciproca): ?>
                                            <span style="background: #28a745; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">✓ Recíproca</span>
                                            <br><small style="color: #666; font-size: 10px;">
                                                ID #<?= $tarifa_reciproca->id ?>: <?= esc_html($tarifa_reciproca->destino_nombre ?? 'N/A') ?> → <?= esc_html($tarifa_reciproca->origen_nombre ?? 'N/A') ?>
                                                <br>Precio: $<?= number_format($tarifa_reciproca->precio, 0, ',', '.') ?>
                                            </small>
                                        <?php else: ?>
                                            <span style="background: #ffc107; color: #000; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">⚠ Sin recíproca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" 
                                                class="gofast-btn-mini gofast-btn-editar-tarifa" 
                                                data-tarifa-id="<?= esc_attr($t->id) ?>"
                                                data-tarifa-origen-id="<?= esc_attr($t->origen_sector_id) ?>"
                                                data-tarifa-destino-id="<?= esc_attr($t->destino_sector_id) ?>"
                                                data-tarifa-precio="<?= esc_attr($t->precio) ?>"
                                                <?php if ($tarifa_reciproca): ?>
                                                    data-tarifa-reciproca-id="<?= esc_attr($tarifa_reciproca->id) ?>"
                                                    data-tarifa-reciproca-precio="<?= esc_attr($tarifa_reciproca->precio) ?>"
                                                <?php endif; ?>
                                                style="background: var(--gofast-yellow); color: #000; margin-right: 4px;">
                                            ✏️ Editar
                                        </button>
                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar esta tarifa?<?= $tarifa_reciproca ? ' También se eliminará la recíproca.' : '' ?>');">
                                            <?php wp_nonce_field('gofast_eliminar_tarifa', 'gofast_eliminar_tarifa_nonce'); ?>
                                            <input type="hidden" name="gofast_eliminar_tarifa" value="1">
                                            <input type="hidden" name="tarifa_id" value="<?= esc_attr($t->id) ?>">
                                            <button type="submit" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                🗑️ Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_paginas_tarifas > 1): ?>
                    <div class="gofast-pagination" style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;align-items:center;">
                        <?php
                        $base_url_tarifas = get_permalink();
                        $query_args_tarifas = [];
                        
                        // Asegurar que se mantengan los parámetros de tab y sub_tab
                        $query_args_tarifas['tab'] = 'tarifas';
                        $query_args_tarifas['sub_tab'] = isset($_GET['sub_tab']) ? sanitize_text_field($_GET['sub_tab']) : 'gestion';
                        
                        // Preservar filtros activos
                        if ($filtro_origen_id > 0) {
                            $query_args_tarifas['filtro_origen'] = $filtro_origen_id;
                        }
                        if ($filtro_destino_id > 0) {
                            $query_args_tarifas['filtro_destino'] = $filtro_destino_id;
                        }
                        if ($filtro_precio > 0) {
                            $query_args_tarifas['filtro_precio'] = $filtro_precio;
                        }
                        
                        // Preservar filtros activos
                        if ($filtro_origen_id > 0) {
                            $query_args_tarifas['filtro_origen'] = $filtro_origen_id;
                        }
                        if ($filtro_destino_id > 0) {
                            $query_args_tarifas['filtro_destino'] = $filtro_destino_id;
                        }
                        if ($filtro_precio > 0) {
                            $query_args_tarifas['filtro_precio'] = $filtro_precio;
                        }
                        
                        // Calcular rango de páginas a mostrar (máximo 10 páginas alrededor de la actual)
                        $rango = 5;
                        $inicio = max(1, $pg_tarifas - $rango);
                        $fin = min($total_paginas_tarifas, $pg_tarifas + $rango);
                        ?>
                        
                        <?php
                        // Botón "Primera" si no estamos cerca del inicio
                        if ($pg_tarifas > $rango + 1):
                            $query_args_tarifas['pg_tarifas'] = 1;
                            $url_primera = esc_url( add_query_arg($query_args_tarifas, $base_url_tarifas) );
                            ?>
                            <a href="<?php echo $url_primera; ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                « Primera
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        // Botón "Anterior"
                        if ($pg_tarifas > 1):
                            $query_args_tarifas['pg_tarifas'] = $pg_tarifas - 1;
                            $url_anterior = esc_url( add_query_arg($query_args_tarifas, $base_url_tarifas) );
                            ?>
                            <a href="<?php echo $url_anterior; ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                ‹ Anterior
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        // Mostrar "..." si hay páginas antes del rango
                        if ($inicio > 1): ?>
                            <span style="padding:8px 4px;color:#666;">...</span>
                        <?php endif; ?>
                        
                        <?php
                        // Mostrar páginas del rango
                        for ($i = $inicio; $i <= $fin; $i++):
                            $query_args_tarifas['pg_tarifas'] = $i;
                            $url_tarifas = esc_url( add_query_arg($query_args_tarifas, $base_url_tarifas) );
                            $active_tarifas = ($i === $pg_tarifas) ? 'gofast-page-current' : '';
                            ?>
                            <a href="<?php echo $url_tarifas; ?>" class="gofast-page-link <?php echo $active_tarifas; ?>" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;<?php echo $active_tarifas ? 'background:var(--gofast-yellow);font-weight:700;' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php
                        // Mostrar "..." si hay páginas después del rango
                        if ($fin < $total_paginas_tarifas): ?>
                            <span style="padding:8px 4px;color:#666;">...</span>
                        <?php endif; ?>
                        
                        <?php
                        // Botón "Siguiente"
                        if ($pg_tarifas < $total_paginas_tarifas):
                            $query_args_tarifas['pg_tarifas'] = $pg_tarifas + 1;
                            $url_siguiente = esc_url( add_query_arg($query_args_tarifas, $base_url_tarifas) );
                            ?>
                            <a href="<?php echo $url_siguiente; ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                Siguiente ›
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        // Botón "Última" si no estamos cerca del final
                        if ($pg_tarifas < $total_paginas_tarifas - $rango):
                            $query_args_tarifas['pg_tarifas'] = $total_paginas_tarifas;
                            $url_ultima = esc_url( add_query_arg($query_args_tarifas, $base_url_tarifas) );
                            ?>
                            <a href="<?php echo $url_ultima; ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                Última »
                            </a>
                        <?php endif; ?>
                        
                        <span style="padding:8px 12px;color:#666;font-size:13px;margin-left:8px;">
                            Página <?php echo $pg_tarifas; ?> de <?php echo $total_paginas_tarifas; ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($tarifas) && ($filtro_origen_id > 0 || $filtro_destino_id > 0 || $filtro_precio > 0)): ?>
                    <div style="text-align:center;padding:20px;color:#666;background:#fff3cd;border-left:4px solid #ffc107;border-radius:4px;margin-top:20px;">
                        <strong>⚠️ No se encontraron tarifas que coincidan con los filtros aplicados.</strong>
                        <br><small>Intenta ajustar los criterios de búsqueda o <a href="<?php echo esc_url(remove_query_arg(['filtro_origen', 'filtro_destino', 'filtro_precio', 'pg_tarifas'], add_query_arg(['tab' => 'tarifas', 'sub_tab' => 'gestion']))); ?>" style="color:#007bff;">limpiar los filtros</a>.</small>
                    </div>
                <?php elseif (empty($tarifas)): ?>
                    <div style="text-align:center;padding:20px;color:#666;">
                        No hay tarifas registradas.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            </div>
            
            <!-- SUB-TAB: TARIFAS FALTANTES -->
            <div id="sub-tab-tarifas-faltantes" style="display: <?= $sub_tab_tarifas === 'faltantes' ? 'block' : 'none' ?>;">
            <h3>🔍 Revisión y Asignación de Tarifas Faltantes</h3>
            
            <!-- Herramienta de Revisión de Tarifas Recíprocas -->
            <?php
            // Analizar tarifas recíprocas faltantes
            $tarifas_existentes_analisis = $wpdb->get_results("SELECT origen_sector_id, destino_sector_id, precio FROM tarifas");
            $mapa_tarifas_analisis = [];
            $tarifas_faltantes = [];
            $tarifas_desincronizadas = [];
            
            foreach ($tarifas_existentes_analisis as $t) {
                $key = (int)$t->origen_sector_id . '_' . (int)$t->destino_sector_id;
                $mapa_tarifas_analisis[$key] = (int)$t->precio;
            }
            
            // Obtener sectores para análisis
            $sectores_analisis = $wpdb->get_results("SELECT id, nombre FROM sectores ORDER BY id ASC");
            $sectores_ids_analisis = array_map(function($s) { return (int) $s->id; }, $sectores_analisis);
            
            // Buscar tarifas recíprocas faltantes
            foreach ($tarifas_existentes_analisis as $tarifa) {
                $origen = (int) $tarifa->origen_sector_id;
                $destino = (int) $tarifa->destino_sector_id;
                $precio = (int) $tarifa->precio;
                
                // Si no es la misma ruta, verificar recíproca
                if ($origen != $destino) {
                    $key_inversa = $destino . '_' . $origen;
                    if (!isset($mapa_tarifas_analisis[$key_inversa])) {
                        $tarifas_faltantes[] = [
                            'origen' => $destino,
                            'destino' => $origen,
                            'precio_sugerido' => $precio,
                            'tipo' => 'reciproca'
                        ];
                    } else if ($mapa_tarifas_analisis[$key_inversa] != $precio) {
                        $tarifas_desincronizadas[] = [
                            'origen' => $origen,
                            'destino' => $destino,
                            'precio_actual' => $precio,
                            'origen_inv' => $destino,
                            'destino_inv' => $origen,
                            'precio_inv' => $mapa_tarifas_analisis[$key_inversa],
                            'tipo' => 'desincronizada'
                        ];
                    }
                }
            }
            
            // Calcular precio sugerido para tarifas del mismo sector
            $precios_mismo_sector = [];
            foreach ($tarifas_existentes_analisis as $t) {
                if ((int)$t->origen_sector_id == (int)$t->destino_sector_id) {
                    $precios_mismo_sector[] = (int)$t->precio;
                }
            }
            
            // Priorizar el valor mayor: usar el máximo de tarifas del mismo sector
            $precio_sugerido_mismo_sector = 3500;
            if (!empty($precios_mismo_sector)) {
                $precio_sugerido_mismo_sector = (int) max($precios_mismo_sector);
            } else {
                $precios_todos = array_map(function($t) { return (int)$t->precio; }, $tarifas_existentes_analisis);
                if (!empty($precios_todos)) {
                    $precio_sugerido_mismo_sector = (int) max($precios_todos);
                }
            }
            
            // Buscar tarifas del mismo sector faltantes
            foreach ($sectores_ids_analisis as $sector_id) {
                $key_mismo = $sector_id . '_' . $sector_id;
                if (!isset($mapa_tarifas_analisis[$key_mismo])) {
                    $sector_nombre = '';
                    foreach ($sectores_analisis as $s) {
                        if ((int)$s->id == $sector_id) {
                            $sector_nombre = $s->nombre;
                            break;
                        }
                    }
                    $tarifas_faltantes[] = [
                        'origen' => $sector_id,
                        'destino' => $sector_id,
                        'precio_sugerido' => $precio_sugerido_mismo_sector,
                        'tipo' => 'mismo_sector',
                        'sector_nombre' => $sector_nombre
                    ];
                }
            }
            
            $total_faltantes = count($tarifas_faltantes);
            $total_desincronizadas = count($tarifas_desincronizadas);
            ?>
            <div class="gofast-box" style="background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px;">
                <h4 style="margin-top: 0; color: #856404;">📊 Resumen</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 15px;">
                    <div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #ddd;">
                        <div style="font-size: 24px; font-weight: 700; color: #dc3545;"><?= $total_faltantes ?></div>
                        <div style="font-size: 12px; color: #666;">Tarifas Faltantes</div>
                    </div>
                    <div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #ddd;">
                        <div style="font-size: 24px; font-weight: 700; color: #ffc107;"><?= $total_desincronizadas ?></div>
                        <div style="font-size: 12px; color: #666;">Precios Desincronizados</div>
                    </div>
                </div>
                
                <?php if ($total_faltantes > 0 || $total_desincronizadas > 0): ?>
                    <form method="post" style="margin-top: 15px;">
                        <?php wp_nonce_field('gofast_crear_tarifas_reciprocas', 'gofast_reciprocas_nonce'); ?>
                        <div style="text-align: center;">
                            <button type="submit" 
                                    name="gofast_crear_tarifas_reciprocas" 
                                    class="gofast-btn-mini"
                                    style="background: #28a745; color: #fff; padding: 12px 30px; font-weight: 600; font-size: 16px;"
                                    onclick="return confirm('¿Estás seguro de crear/actualizar <?= $total_faltantes + $total_desincronizadas ?> tarifa(s)?\n\nLas tarifas desincronizadas se sincronizarán con el valor mayor.');">
                                ✅ Sincronizar Todas las Tarifas
                            </button>
                            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                                Al sincronizar, las tarifas recíprocas usarán el valor mayor entre ambas.
                            </p>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; border: 1px solid #c3e6cb; text-align: center;">
                        ✅ <strong>¡Perfecto!</strong> Todas las tarifas recíprocas están creadas y sincronizadas.
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($total_faltantes > 0): ?>
                <div class="gofast-box" style="margin-bottom: 20px;">
                    <h4 style="margin-top: 0; margin-bottom: 15px;">📋 Tarifas Faltantes (<?= $total_faltantes ?>)</h4>
                    <div class="gofast-table-wrap" style="overflow-x: auto;">
                        <table class="gofast-table" style="min-width: 800px;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Sector Origen</th>
                                    <th>Sector Destino</th>
                                    <th>Tipo</th>
                                    <th>Precio Sugerido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tarifas_faltantes as $idx => $faltante): 
                                    $origen_nombre = '';
                                    $destino_nombre = '';
                                    foreach ($sectores_analisis as $s) {
                                        if ((int)$s->id == $faltante['origen']) $origen_nombre = $s->nombre;
                                        if ((int)$s->id == $faltante['destino']) $destino_nombre = $s->nombre;
                                    }
                                ?>
                                    <tr>
                                        <td><strong>#<?= $idx + 1 ?></strong></td>
                                        <td>
                                            <strong>Sector <?= $faltante['origen'] ?></strong>
                                            <br><small style="color: #666;"><?= esc_html($origen_nombre ?: 'N/A') ?></small>
                                        </td>
                                        <td>
                                            <strong>Sector <?= $faltante['destino'] ?></strong>
                                            <br><small style="color: #666;"><?= esc_html($destino_nombre ?: 'N/A') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($faltante['tipo'] === 'mismo_sector'): ?>
                                                <span style="background: #17a2b8; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Mismo Sector</span>
                                            <?php else: ?>
                                                <span style="background: #dc3545; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Recíproca</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong style="color: #28a745; font-size: 16px;">$<?= number_format($faltante['precio_sugerido'], 0, ',', '.') ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($total_desincronizadas > 0): 
                // Filtrar duplicados: solo mostrar una vez cada par A->B (sin importar el orden)
                $tarifas_desincronizadas_unicas = [];
                $pares_procesados = [];
                
                foreach ($tarifas_desincronizadas as $des) {
                    // Crear clave única para el par (ordenar para que A->B y B->A sean el mismo par)
                    $origen = (int) $des['origen'];
                    $destino = (int) $des['destino'];
                    $par_key = $origen < $destino ? $origen . '_' . $destino : $destino . '_' . $origen;
                    
                    if (!isset($pares_procesados[$par_key])) {
                        $pares_procesados[$par_key] = true;
                        // Determinar qué precio corresponde a cada dirección
                        if ($origen < $destino) {
                            // Mostrar A->B
                            $tarifas_desincronizadas_unicas[] = [
                                'origen' => $origen,
                                'destino' => $destino,
                                'precio_ab' => $des['precio_actual'],
                                'precio_ba' => $des['precio_inv']
                            ];
                        } else {
                            // Mostrar A->B (invertido)
                            $tarifas_desincronizadas_unicas[] = [
                                'origen' => $destino,
                                'destino' => $origen,
                                'precio_ab' => $des['precio_inv'],
                                'precio_ba' => $des['precio_actual']
                            ];
                        }
                    }
                }
                
                $total_desincronizadas_unicas = count($tarifas_desincronizadas_unicas);
            ?>
                <div class="gofast-box" style="margin-bottom: 20px;">
                    <h4 style="margin-top: 0; margin-bottom: 15px;">⚠️ Precios Desincronizados (<?= $total_desincronizadas_unicas ?>)</h4>
                    <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                        Las siguientes rutas tienen precios diferentes en cada dirección. Al sincronizar, se usará el valor mayor.
                    </p>
                    <div class="gofast-table-wrap" style="overflow-x: auto;">
                        <table class="gofast-table" style="min-width: 700px;">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th style="min-width: 200px;">Ruta</th>
                                    <th style="text-align: center; min-width: 120px;">Precio A→B</th>
                                    <th style="text-align: center; min-width: 120px;">Precio B→A</th>
                                    <th style="text-align: center; min-width: 120px;">Diferencia</th>
                                    <th style="text-align: center; min-width: 120px;">Valor a Usar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tarifas_desincronizadas_unicas as $idx => $des): 
                                    $origen_nombre = '';
                                    $destino_nombre = '';
                                    foreach ($sectores_analisis as $s) {
                                        if ((int)$s->id == $des['origen']) $origen_nombre = $s->nombre;
                                        if ((int)$s->id == $des['destino']) $destino_nombre = $s->nombre;
                                    }
                                    $diferencia = abs($des['precio_ab'] - $des['precio_ba']);
                                    $precio_mayor = max($des['precio_ab'], $des['precio_ba']);
                                ?>
                                    <tr>
                                        <td style="text-align: center;"><strong><?= $idx + 1 ?></strong></td>
                                        <td>
                                            <div style="font-weight: 600; font-size: 14px;">
                                                <?= esc_html($origen_nombre ?: 'Sector ' . $des['origen']) ?>
                                            </div>
                                            <div style="color: #666; font-size: 12px; margin-top: 2px;">
                                                → <?= esc_html($destino_nombre ?: 'Sector ' . $des['destino']) ?>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <strong style="font-size: 15px;">$<?= number_format($des['precio_ab'], 0, ',', '.') ?></strong>
                                        </td>
                                        <td style="text-align: center;">
                                            <strong style="color: #dc3545; font-size: 15px;">$<?= number_format($des['precio_ba'], 0, ',', '.') ?></strong>
                                        </td>
                                        <td style="text-align: center;">
                                            <strong style="color: #ff9800; font-size: 15px;">$<?= number_format($diferencia, 0, ',', '.') ?></strong>
                                        </td>
                                        <td style="text-align: center;">
                                            <strong style="color: #28a745; font-size: 16px;">$<?= number_format($precio_mayor, 0, ',', '.') ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>

        <!-- TAB: BARRIOS -->
        <div id="tab-barrios" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'barrios' ? 'block' : 'none' ?>;">
            <h3>📍 Gestión de Barrios</h3>
            
            <!-- Formulario agregar barrio -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Agregar Nuevo Barrio</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_agregar_barrio', 'gofast_barrio_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Nombre del Barrio:</label>
                            <input type="text" 
                                   name="nombre" 
                                   required 
                                   placeholder="Ej: Centro"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">ID del Sector:</label>
                            <input type="number" 
                                   name="sector_id" 
                                   required 
                                   min="1"
                                   placeholder="Ej: 12"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_agregar_barrio" class="gofast-btn-mini">✅ Agregar Barrio</button>
                </form>
            </div>

            <!-- Filtros de búsqueda -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h4 style="margin: 0;">🔍 Buscar Barrios</h4>
                    <a href="<?php echo esc_url(remove_query_arg(['filtro_barrio_sector', 'filtro_barrio_texto', 'pg_barrios'], add_query_arg(['tab' => 'barrios']))); ?>" 
                       class="gofast-btn-mini" 
                       style="background: #6c757d; color: #fff; padding: 6px 12px; font-size: 12px; text-decoration: none;">
                        🗑️ Limpiar Filtros
                    </a>
                </div>
                <form method="get" action="">
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_barrio_sector', 'filtro_barrio_texto', 'pg_barrios'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="tab" value="barrios">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Buscar por sector:</label>
                            <select name="filtro_barrio_sector" id="filtro-barrio-sector" class="gofast-select-search" style="width: 100%; font-size: 14px;">
                                <option value="">Todos los sectores</option>
                                <?php foreach ($sectores_todos as $s): ?>
                                    <option value="<?= esc_attr($s->id) ?>" <?= $filtro_barrio_sector == $s->id ? 'selected' : '' ?>><?= esc_html($s->nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Buscar por nombre:</label>
                            <input type="text" 
                                   name="filtro_barrio_texto"
                                   id="filtro-barrio-texto" 
                                   value="<?= esc_attr($filtro_barrio_texto) ?>"
                                   placeholder="Buscar por nombre de barrio..."
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <button type="submit" class="gofast-btn-mini" style="width: 100%;">🔍 Filtrar</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Listado de barrios -->
            <?php if (empty($barrios)): ?>
                <p style="text-align: center; color: #666; padding: 20px;">
                    No hay barrios registrados.
                </p>
            <?php else: ?>
                <div class="gofast-table-wrap" style="overflow-x: auto;">
                    <table class="gofast-table" id="tabla-barrios" style="min-width: 500px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Sector</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($barrios as $b): ?>
                                <tr data-sector-id="<?= esc_attr($b->sector_id) ?>"
                                    data-sector-nombre="<?= esc_attr(strtolower($b->sector_nombre ?? '')) ?>"
                                    data-barrio-nombre="<?= esc_attr($b->nombre) ?>">
                                    <td>#<?= esc_html($b->id) ?></td>
                                    <td><strong><?= esc_html($b->nombre) ?></strong></td>
                                    <td><?= esc_html($b->sector_nombre ?? 'N/A') ?></td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" 
                                                class="gofast-btn-mini gofast-btn-editar-barrio" 
                                                data-barrio-id="<?= esc_attr($b->id) ?>"
                                                data-barrio-nombre="<?= esc_attr($b->nombre) ?>"
                                                data-barrio-sector-id="<?= esc_attr($b->sector_id) ?>"
                                                style="background: var(--gofast-yellow); color: #000; margin-right: 4px;">
                                            ✏️ Editar
                                        </button>
                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar este barrio?');">
                                            <?php wp_nonce_field('gofast_eliminar_barrio', 'gofast_eliminar_barrio_nonce'); ?>
                                            <input type="hidden" name="gofast_eliminar_barrio" value="1">
                                            <input type="hidden" name="barrio_id" value="<?= esc_attr($b->id) ?>">
                                            <button type="submit" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                🗑️ Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_paginas_barrios > 1): ?>
                    <div class="gofast-pagination" style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;align-items:center;">
                        <?php
                        $base_url_barrios = get_permalink();
                        $query_args_barrios = [];
                        
                        // Asegurar que se mantengan los parámetros de tab
                        $query_args_barrios['tab'] = 'barrios';
                        
                        // Preservar filtros activos
                        if ($filtro_barrio_sector > 0) {
                            $query_args_barrios['filtro_barrio_sector'] = $filtro_barrio_sector;
                        }
                        if (!empty($filtro_barrio_texto)) {
                            $query_args_barrios['filtro_barrio_texto'] = $filtro_barrio_texto;
                        }
                        
                        // Calcular rango de páginas a mostrar
                        $rango = 5;
                        $inicio = max(1, $pg_barrios - $rango);
                        $fin = min($total_paginas_barrios, $pg_barrios + $rango);
                        ?>
                        
                        <?php if ($pg_barrios > $rango + 1): ?>
                            <?php $query_args_barrios['pg_barrios'] = 1; ?>
                            <a href="<?php echo esc_url(add_query_arg($query_args_barrios, $base_url_barrios)); ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                « Primera
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($pg_barrios > 1): ?>
                            <?php $query_args_barrios['pg_barrios'] = $pg_barrios - 1; ?>
                            <a href="<?php echo esc_url(add_query_arg($query_args_barrios, $base_url_barrios)); ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                ‹ Anterior
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($inicio > 1): ?>
                            <span style="padding:8px 4px;color:#666;">...</span>
                        <?php endif; ?>
                        
                        <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                            <?php $query_args_barrios['pg_barrios'] = $i; ?>
                            <?php $active_barrios = ($i === $pg_barrios) ? 'gofast-page-current' : ''; ?>
                            <a href="<?php echo esc_url(add_query_arg($query_args_barrios, $base_url_barrios)); ?>" class="gofast-page-link <?php echo $active_barrios; ?>" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;<?php echo $active_barrios ? 'background:var(--gofast-yellow);font-weight:700;' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($fin < $total_paginas_barrios): ?>
                            <span style="padding:8px 4px;color:#666;">...</span>
                        <?php endif; ?>
                        
                        <?php if ($pg_barrios < $total_paginas_barrios): ?>
                            <?php $query_args_barrios['pg_barrios'] = $pg_barrios + 1; ?>
                            <a href="<?php echo esc_url(add_query_arg($query_args_barrios, $base_url_barrios)); ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                Siguiente ›
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($pg_barrios < $total_paginas_barrios - $rango): ?>
                            <?php $query_args_barrios['pg_barrios'] = $total_paginas_barrios; ?>
                            <a href="<?php echo esc_url(add_query_arg($query_args_barrios, $base_url_barrios)); ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                Última »
                            </a>
                        <?php endif; ?>
                        
                        <div style="margin-left: 12px; font-size: 13px; color: #666;">
                            Página <?php echo $pg_barrios; ?> de <?php echo $total_paginas_barrios; ?>
                            <br><small>Total: <?php echo $total_barrios; ?> barrio(s)</small>
                        </div>
                    </div>
                <?php elseif ($total_barrios > 0): ?>
                    <div style="margin-top: 15px; text-align: center; color: #666; font-size: 13px;">
                        Total: <?php echo $total_barrios; ?> barrio(s)
                    </div>
                <?php endif; ?>
                
                <?php if (empty($barrios) && ($filtro_barrio_sector > 0 || !empty($filtro_barrio_texto))): ?>
                    <div style="text-align:center;padding:20px;color:#666;">
                        No se encontraron barrios que coincidan con los filtros.
                        <br><small>Intenta ajustar los criterios de búsqueda o <a href="<?php echo esc_url(remove_query_arg(['filtro_barrio_sector', 'filtro_barrio_texto', 'pg_barrios'], add_query_arg(['tab' => 'barrios']))); ?>" style="color:#007bff;">limpiar los filtros</a>.</small>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- TAB: SECTORES -->
        <div id="tab-sectores" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'sectores' ? 'block' : 'none' ?>;">
            <h3>🗺️ Gestión de Sectores</h3>
            
            <!-- Formulario agregar sector -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Agregar Nuevo Sector</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_agregar_sector', 'gofast_sector_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">ID del Sector:</label>
                            <input type="number" 
                                   name="sector_id" 
                                   required 
                                   min="1"
                                   placeholder="Ej: 12"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Nombre del Sector:</label>
                            <input type="text" 
                                   name="nombre" 
                                   required 
                                   placeholder="Ej: Sector 12"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_agregar_sector" class="gofast-btn-mini">✅ Agregar Sector</button>
                </form>
            </div>

            <!-- Filtros de búsqueda -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h4 style="margin: 0;">🔍 Buscar Sectores</h4>
                    <a href="<?php echo esc_url(remove_query_arg(['filtro_sector_texto', 'pg_sectores'], add_query_arg(['tab' => 'sectores']))); ?>" 
                       class="gofast-btn-mini" 
                       style="background: #6c757d; color: #fff; padding: 6px 12px; font-size: 12px; text-decoration: none;">
                        🗑️ Limpiar Filtros
                    </a>
                </div>
                <form method="get" action="">
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (!in_array($key, ['filtro_sector_texto', 'pg_sectores'])): ?>
                            <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="tab" value="sectores">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Buscar por nombre o ID:</label>
                            <input type="text" 
                                   name="filtro_sector_texto"
                                   id="filtro-sector-texto" 
                                   value="<?= esc_attr($filtro_sector_texto) ?>"
                                   placeholder="Buscar por nombre o ID de sector..."
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <button type="submit" class="gofast-btn-mini" style="width: 100%;">🔍 Filtrar</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Listado de sectores -->
            <?php if (empty($sectores)): ?>
                <p style="text-align: center; color: #666; padding: 20px;">
                    No hay sectores registrados.
                </p>
            <?php else: ?>
                <div class="gofast-table-wrap" style="overflow-x: auto;">
                    <table class="gofast-table" id="tabla-sectores" style="min-width: 400px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sectores as $s): ?>
                                <tr data-sector-id="<?= esc_attr($s->id) ?>"
                                    data-sector-nombre="<?= esc_attr(strtolower($s->nombre)) ?>">
                                    <td>#<?= esc_html($s->id) ?></td>
                                    <td><strong><?= esc_html($s->nombre) ?></strong></td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" 
                                                class="gofast-btn-mini gofast-btn-editar-sector" 
                                                data-sector-id="<?= esc_attr($s->id) ?>"
                                                data-sector-nombre="<?= esc_attr($s->nombre) ?>"
                                                style="background: var(--gofast-yellow); color: #000; margin-right: 4px;">
                                            ✏️ Editar
                                        </button>
                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar este sector?');">
                                            <?php wp_nonce_field('gofast_eliminar_sector', 'gofast_eliminar_sector_nonce'); ?>
                                            <input type="hidden" name="gofast_eliminar_sector" value="1">
                                            <input type="hidden" name="sector_id" value="<?= esc_attr($s->id) ?>">
                                            <button type="submit" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                🗑️ Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_paginas_sectores > 1): ?>
                    <div class="gofast-pagination" style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;align-items:center;">
                        <?php
                        $base_url_sectores = get_permalink();
                        $query_args_sectores = [];
                        
                        // Asegurar que se mantengan los parámetros de tab
                        $query_args_sectores['tab'] = 'sectores';
                        
                        // Preservar filtros activos
                        if (!empty($filtro_sector_texto)) {
                            $query_args_sectores['filtro_sector_texto'] = $filtro_sector_texto;
                        }
                        
                        // Calcular rango de páginas a mostrar
                        $rango = 5;
                        $inicio = max(1, $pg_sectores - $rango);
                        $fin = min($total_paginas_sectores, $pg_sectores + $rango);
                        ?>
                        
                        <?php if ($pg_sectores > $rango + 1): ?>
                            <?php $query_args_sectores['pg_sectores'] = 1; ?>
                            <a href="<?php echo esc_url(add_query_arg($query_args_sectores, $base_url_sectores)); ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                « Primera
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($pg_sectores > 1): ?>
                            <?php $query_args_sectores['pg_sectores'] = $pg_sectores - 1; ?>
                            <a href="<?php echo esc_url(add_query_arg($query_args_sectores, $base_url_sectores)); ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                ‹ Anterior
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($inicio > 1): ?>
                            <span style="padding:8px 4px;color:#666;">...</span>
                        <?php endif; ?>
                        
                        <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                            <?php $query_args_sectores['pg_sectores'] = $i; ?>
                            <?php $active_sectores = ($i === $pg_sectores) ? 'gofast-page-current' : ''; ?>
                            <a href="<?php echo esc_url(add_query_arg($query_args_sectores, $base_url_sectores)); ?>" class="gofast-page-link <?php echo $active_sectores; ?>" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;<?php echo $active_sectores ? 'background:var(--gofast-yellow);font-weight:700;' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($fin < $total_paginas_sectores): ?>
                            <span style="padding:8px 4px;color:#666;">...</span>
                        <?php endif; ?>
                        
                        <?php if ($pg_sectores < $total_paginas_sectores): ?>
                            <?php $query_args_sectores['pg_sectores'] = $pg_sectores + 1; ?>
                            <a href="<?php echo esc_url(add_query_arg($query_args_sectores, $base_url_sectores)); ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                Siguiente ›
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($pg_sectores < $total_paginas_sectores - $rango): ?>
                            <?php $query_args_sectores['pg_sectores'] = $total_paginas_sectores; ?>
                            <a href="<?php echo esc_url(add_query_arg($query_args_sectores, $base_url_sectores)); ?>" class="gofast-page-link" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;background:#fff;">
                                Última »
                            </a>
                        <?php endif; ?>
                        
                        <div style="margin-left: 12px; font-size: 13px; color: #666;">
                            Página <?php echo $pg_sectores; ?> de <?php echo $total_paginas_sectores; ?>
                            <br><small>Total: <?php echo $total_sectores; ?> sector(es)</small>
                        </div>
                    </div>
                <?php elseif ($total_sectores > 0): ?>
                    <div style="margin-top: 15px; text-align: center; color: #666; font-size: 13px;">
                        Total: <?php echo $total_sectores; ?> sector(es)
                    </div>
                <?php endif; ?>
                
                <?php if (empty($sectores) && !empty($filtro_sector_texto)): ?>
                    <div style="text-align:center;padding:20px;color:#666;">
                        No se encontraron sectores que coincidan con los filtros.
                        <br><small>Intenta ajustar los criterios de búsqueda o <a href="<?php echo esc_url(remove_query_arg(['filtro_sector_texto', 'pg_sectores'], add_query_arg(['tab' => 'sectores']))); ?>" style="color:#007bff;">limpiar los filtros</a>.</small>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- TAB: DESTINOS INTERMUNICIPALES -->
        <div id="tab-intermunicipales" class="gofast-config-tab-content" style="display: <?= $tab_activo === 'intermunicipales' ? 'block' : 'none' ?>;">
            <h3>🌐 Gestión de Destinos Intermunicipales</h3>
            
            <!-- Formulario agregar destino intermunicipal -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Agregar Nuevo Destino</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_agregar_destino_intermunicipal', 'gofast_destino_intermunicipal_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Nombre del Destino:</label>
                            <input type="text" 
                                   name="nombre" 
                                   required 
                                   placeholder="Ej: Buga"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Valor:</label>
                            <input type="number" 
                                   name="valor" 
                                   required 
                                   min="1"
                                   placeholder="Ej: 35000"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Orden:</label>
                            <input type="number" 
                                   name="orden" 
                                   min="0"
                                   value="0"
                                   placeholder="Ej: 1"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div style="display: flex; align-items: center; padding-top: 24px;">
                            <label class="gofast-switch">
                                <input type="checkbox" name="activo" value="1" checked>
                                <span class="gofast-switch-slider"></span>
                                <span class="gofast-switch-label">Activo</span>
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="gofast_agregar_destino_intermunicipal" class="gofast-btn-mini">✅ Agregar Destino</button>
                </form>
            </div>

            <!-- Filtros de búsqueda -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0; margin-bottom: 12px;">🔍 Buscar Destinos Intermunicipales</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Buscar por nombre:</label>
                        <input type="text" 
                               id="filtro-destino-texto" 
                               placeholder="Buscar por nombre de destino..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Filtrar por estado:</label>
                        <select id="filtro-destino-estado" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="">Todos los estados</option>
                            <option value="1">Activos</option>
                            <option value="0">Inactivos</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Listado de destinos intermunicipales -->
            <?php if (empty($destinos_intermunicipales)): ?>
                <p style="text-align: center; color: #666; padding: 20px;">
                    No hay destinos intermunicipales registrados.
                </p>
            <?php else: ?>
                <div class="gofast-table-wrap" style="overflow-x: auto;">
                    <table class="gofast-table" id="tabla-destinos" style="min-width: 600px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Valor</th>
                                <th>Orden</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($destinos_intermunicipales as $d): ?>
                                <tr class="<?= $d->activo == 0 ? 'gofast-row-inactive' : '' ?>"
                                    data-destino-nombre="<?= esc_attr(strtolower($d->nombre)) ?>"
                                    data-destino-activo="<?= esc_attr($d->activo) ?>">
                                    <td>#<?= esc_html($d->id) ?></td>
                                    <td><strong><?= esc_html($d->nombre) ?></strong></td>
                                    <td><strong>$<?= number_format($d->valor, 0, ',', '.') ?></strong></td>
                                    <td><?= esc_html($d->orden) ?></td>
                                    <td>
                                        <span class="gofast-badge-estado <?= $d->activo == 1 ? 'gofast-badge-estado-entregado' : 'gofast-badge-estado-cancelado' ?>">
                                            <?= $d->activo == 1 ? '✅ Activo' : '❌ Inactivo' ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" 
                                                class="gofast-btn-mini gofast-btn-editar-destino-intermunicipal" 
                                                data-destino-id="<?= esc_attr($d->id) ?>"
                                                data-destino-nombre="<?= esc_attr($d->nombre) ?>"
                                                data-destino-valor="<?= esc_attr($d->valor) ?>"
                                                data-destino-orden="<?= esc_attr($d->orden) ?>"
                                                data-destino-activo="<?= esc_attr($d->activo) ?>"
                                                style="background: var(--gofast-yellow); color: #000; margin-right: 4px;">
                                            ✏️ Editar
                                        </button>
                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar este destino intermunicipal?');">
                                            <?php wp_nonce_field('gofast_eliminar_destino_intermunicipal', 'gofast_eliminar_destino_intermunicipal_nonce'); ?>
                                            <input type="hidden" name="gofast_eliminar_destino_intermunicipal" value="1">
                                            <input type="hidden" name="destino_id" value="<?= esc_attr($d->id) ?>">
                                            <button type="submit" class="gofast-btn-mini" style="background:#dc3545;color:#fff;">
                                                🗑️ Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="mensaje-sin-resultados-destinos" style="display:none;text-align:center;padding:20px;color:#666;">
                    No se encontraron destinos que coincidan con los filtros.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para editar tarifa -->
<div id="modal-editar-tarifa" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
    <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Tarifa</h2>
        <form method="post" id="form-editar-tarifa">
            <?php wp_nonce_field('gofast_editar_tarifa', 'gofast_editar_tarifa_nonce'); ?>
            <input type="hidden" name="gofast_editar_tarifa" value="1">
            <input type="hidden" name="tarifa_id" id="editar-tarifa-id">
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Sector Origen:</label>
                <select name="origen_sector_id" id="editar-tarifa-origen" class="gofast-select" required style="width:100%;font-size:16px;">
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($sectores_todos as $s): ?>
                        <option value="<?= esc_attr($s->id) ?>"><?= esc_html($s->nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Sector Destino:</label>
                <select name="destino_sector_id" id="editar-tarifa-destino" class="gofast-select" required style="width:100%;font-size:16px;">
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($sectores_todos as $s): ?>
                        <option value="<?= esc_attr($s->id) ?>"><?= esc_html($s->nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Precio:</label>
                <input type="number" name="precio" id="editar-tarifa-precio" required min="1" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            <div id="info-reciproca-tarifa" style="display:none;margin-bottom:16px;padding:12px;background:#e7f3ff;border-left:4px solid #2196F3;border-radius:4px;">
                <strong style="display:block;margin-bottom:4px;color:#1976D2;">ℹ️ Tarifa Recíproca:</strong>
                <div id="info-reciproca-contenido" style="font-size:13px;color:#424242;"></div>
                <small style="display:block;margin-top:4px;color:#666;">Al guardar, ambas tarifas (A→B y B→A) se actualizarán con el valor mayor entre ambas.</small>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditar('tarifa')">Cancelar</button>
                <button type="submit" class="gofast-btn-mini">Guardar (Actualizar Ambas)</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para editar barrio -->
<div id="modal-editar-barrio" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
    <div style="max-width:500px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Barrio</h2>
        <form method="post" id="form-editar-barrio">
            <?php wp_nonce_field('gofast_editar_barrio', 'gofast_editar_barrio_nonce'); ?>
            <input type="hidden" name="gofast_editar_barrio" value="1">
            <input type="hidden" name="barrio_id" id="editar-barrio-id">
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Nombre:</label>
                <input type="text" name="nombre" id="editar-barrio-nombre" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Sector:</label>
                <select name="sector_id" id="editar-barrio-sector" class="gofast-select" required style="width:100%;font-size:16px;">
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($sectores_todos as $s): ?>
                        <option value="<?= esc_attr($s->id) ?>"><?= esc_html($s->nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditar('barrio')">Cancelar</button>
                <button type="submit" class="gofast-btn-mini">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para editar sector -->
<div id="modal-editar-sector" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
    <div style="max-width:500px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Sector</h2>
        <form method="post" id="form-editar-sector">
            <?php wp_nonce_field('gofast_editar_sector', 'gofast_editar_sector_nonce'); ?>
            <input type="hidden" name="gofast_editar_sector" value="1">
            <input type="hidden" name="sector_id" id="editar-sector-id">
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Nombre:</label>
                <input type="text" name="nombre" id="editar-sector-nombre" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditar('sector')">Cancelar</button>
                <button type="submit" class="gofast-btn-mini">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para editar destino intermunicipal -->
<div id="modal-editar-destino-intermunicipal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
    <div style="max-width:500px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Destino Intermunicipal</h2>
        <form method="post" id="form-editar-destino-intermunicipal">
            <?php wp_nonce_field('gofast_editar_destino_intermunicipal', 'gofast_editar_destino_intermunicipal_nonce'); ?>
            <input type="hidden" name="gofast_editar_destino_intermunicipal" value="1">
            <input type="hidden" name="destino_id" id="editar-destino-id">
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Nombre:</label>
                <input type="text" name="nombre" id="editar-destino-nombre" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Valor:</label>
                <input type="number" name="valor" id="editar-destino-valor" required min="1" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Orden:</label>
                <input type="number" name="orden" id="editar-destino-orden" min="0" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            <div style="margin-bottom:16px;">
                <label class="gofast-switch">
                    <input type="checkbox" name="activo" id="editar-destino-activo" value="1">
                    <span class="gofast-switch-slider"></span>
                    <span class="gofast-switch-label">Activo</span>
                </label>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditar('destino-intermunicipal')">Cancelar</button>
                <button type="submit" class="gofast-btn-mini">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function cerrarModalEditar(tipo) {
    document.getElementById('modal-editar-' + tipo).style.display = 'none';
    document.getElementById('form-editar-' + tipo).reset();
    
    // Destruir Select2 si existe
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('#modal-editar-' + tipo + ' .gofast-select').each(function() {
            if (jQuery(this).data('select2')) {
                jQuery(this).select2('destroy');
            }
        });
    }
}

// Función global para cambiar tabs
function mostrarTab(tab) {
    // Ocultar todos los tabs
    document.querySelectorAll('.gofast-config-tab-content').forEach(function(content) {
        content.style.display = 'none';
    });
    
    // Remover clase activa de todos los botones
    document.querySelectorAll('.gofast-config-tab').forEach(function(btn) {
        btn.classList.remove('gofast-config-tab-active');
    });
    
    // Mostrar el tab seleccionado
    document.getElementById('tab-' + tab).style.display = 'block';
    
    // Agregar clase activa al botón
    if (event && event.target) {
        event.target.classList.add('gofast-config-tab-active');
    } else {
        // Si no hay event, buscar el botón correspondiente
        document.querySelectorAll('.gofast-config-tab').forEach(function(btn) {
            if (btn.textContent.includes(tab === 'tarifas' ? 'Tarifas' : 
                                         tab === 'barrios' ? 'Barrios' : 
                                         tab === 'sectores' ? 'Sectores' : 'Destinos')) {
                btn.classList.add('gofast-config-tab-active');
            }
        });
    }
    
    // Actualizar URL sin recargar
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);
    
    // Reinicializar Select2 en el nuevo tab
    setTimeout(function() {
        if (typeof window.initSelect2Config === 'function') {
            window.initSelect2Config();
        } else if (window.jQuery && jQuery.fn.select2) {
            // Si la función no está disponible, inicializar Select2 directamente
            jQuery('.gofast-select-search').each(function() {
                if (!jQuery(this).data('select2')) {
                    jQuery(this).select2({
                        placeholder: '🔍 Escribe para buscar...',
                        width: '100%',
                        dropdownParent: jQuery('body'),
                        allowClear: true,
                        minimumResultsForSearch: 0
                    });
                }
            });
            jQuery('.gofast-select').not('.gofast-select-search').each(function() {
                if (!jQuery(this).data('select2')) {
                    jQuery(this).select2({
                        placeholder: '🔍 Escribe para buscar...',
                        width: '100%',
                        dropdownParent: jQuery('body'),
                        allowClear: true,
                        minimumResultsForSearch: 0
                    });
                }
            });
        }
    }, 100);
}

// Función para cambiar sub-tabs de tarifas
function mostrarSubTabTarifas(subTab) {
    // Ocultar todos los sub-tabs
    document.getElementById('sub-tab-tarifas-agregar').style.display = 'none';
    document.getElementById('sub-tab-tarifas-gestion').style.display = 'none';
    document.getElementById('sub-tab-tarifas-faltantes').style.display = 'none';
    
    // Remover clase activa de todos los botones de sub-tabs de tarifas
    const tabTarifas = document.getElementById('tab-tarifas');
    if (tabTarifas) {
        tabTarifas.querySelectorAll('.gofast-config-tab').forEach(function(btn) {
            if (btn.textContent.includes('Agregar') || btn.textContent.includes('Gestión') || btn.textContent.includes('Faltantes')) {
                btn.classList.remove('gofast-config-tab-active');
            }
        });
    }
    
    // Mostrar el sub-tab seleccionado
    document.getElementById('sub-tab-tarifas-' + subTab).style.display = 'block';
    
    // Agregar clase activa al botón
    if (event && event.target) {
        event.target.classList.add('gofast-config-tab-active');
    } else {
        if (tabTarifas) {
            tabTarifas.querySelectorAll('.gofast-config-tab').forEach(function(btn) {
                if ((subTab === 'agregar' && btn.textContent.includes('Agregar')) ||
                    (subTab === 'gestion' && btn.textContent.includes('Gestión')) ||
                    (subTab === 'faltantes' && btn.textContent.includes('Faltantes'))) {
                    btn.classList.add('gofast-config-tab-active');
                }
            });
        }
    }
    
    // Actualizar URL sin recargar
    const url = new URL(window.location);
    url.searchParams.set('sub_tab', subTab);
    window.history.pushState({}, '', url);
    
    // Reinicializar Select2 si es necesario
    setTimeout(function() {
        if (typeof window.initSelect2Config === 'function') {
            window.initSelect2Config();
        }
    }, 100);
}

jQuery(document).ready(function($) {
    // Datos de combinaciones faltantes y sectores desde PHP
    const combinacionesFaltantes = <?= $combinaciones_faltantes_json ?>;
    const sectoresMap = {
        <?php 
        foreach ($sectores_todos as $s): 
            echo (int)$s->id . ': "' . esc_js($s->nombre) . '",';
        endforeach; 
        ?>
    };
    
    // Normalizador para búsqueda (quita tildes)
    const normalize = s => (s || "")
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim();
    
    // Matcher personalizado para Select2 (búsqueda exacta y parcial)
    function matcherGofast(params, data) {
        if (!data || !data.id) return null;
        if (!data.text) return null;
        
        // Si no hay término de búsqueda, mostrar todas las opciones
        if (!params.term || !params.term.trim()) {
            return data;
        }
        
        const term = normalize(params.term);
        const text = normalize(data.text);
        
        // Búsqueda exacta primero
        if (text === term) {
            data.matchScore = 1000;
            return data;
        }
        
        // Búsqueda que empieza con el término
        if (text.indexOf(term) === 0) {
            data.matchScore = 500;
            return data;
        }
        
        // Búsqueda parcial (contiene el término)
        if (text.indexOf(term) !== -1) {
            data.matchScore = 100;
            return data;
        }
        
        return null;
    }
    
    // Sorter para ordenar por relevancia
    function sorterGofast(results) {
        return results.sort(function(a, b) {
            const scoreA = a.matchScore || 0;
            const scoreB = b.matchScore || 0;
            return scoreB - scoreA;
        });
    }
    
    // Inicializar Select2 en todos los dropdowns (función global)
    window.initSelect2Config = function() {
        if (window.jQuery && jQuery.fn.select2) {
            // Inicializar campos con clase gofast-select-search (con matcher personalizado)
            $('.gofast-select-search').each(function() {
                if ($(this).data('select2')) {
                    return; // Ya está inicializado
                }
                
                $(this).select2({
                    placeholder: '🔍 Escribe para buscar...',
                    width: '100%',
                    dropdownParent: $('body'),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    matcher: matcherGofast,
                    sorter: sorterGofast,
                    language: {
                        noResults: function() {
                            return 'No se encontraron resultados';
                        },
                        searching: function() {
                            return 'Buscando...';
                        }
                    }
                });
            });
            
            // Inicializar campos con clase gofast-select (sin matcher personalizado)
            $('.gofast-select').not('.gofast-select-search').each(function() {
                if ($(this).data('select2')) {
                    return; // Ya está inicializado
                }
                
                $(this).select2({
                    placeholder: '🔍 Escribe para buscar...',
                    width: '100%',
                    dropdownParent: $('body'),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    language: {
                        noResults: function() {
                            return 'No se encontraron resultados';
                        },
                        searching: function() {
                            return 'Buscando...';
                        }
                    }
                });
            });
        }
    }
    
    // Función para actualizar el dropdown de destino según el origen seleccionado
    function actualizarDestinoTarifa(origenId) {
        const $destinoSelect = $('#agregar-tarifa-destino');
        
        // Limpiar opciones actuales
        $destinoSelect.empty();
        
        if (!origenId || origenId === '') {
            $destinoSelect.append('<option value="">— Primero selecciona un origen —</option>');
            $destinoSelect.prop('disabled', true);
            if ($destinoSelect.data('select2')) {
                $destinoSelect.select2('destroy');
            }
            if (typeof window.initSelect2Config === 'function') {
                window.initSelect2Config();
            }
            return;
        }
        
        // Obtener destinos faltantes para este origen
        const destinosFaltantes = combinacionesFaltantes[origenId] || [];
        
        if (destinosFaltantes.length === 0) {
            $destinoSelect.append('<option value="">— No hay combinaciones faltantes para este origen —</option>');
            $destinoSelect.prop('disabled', true);
        } else {
            $destinoSelect.append('<option value="">— Seleccionar —</option>');
            destinosFaltantes.forEach(function(destinoId) {
                const nombreDestino = sectoresMap[destinoId] || 'Sector ' + destinoId;
                $destinoSelect.append('<option value="' + destinoId + '">' + nombreDestino + '</option>');
            });
            $destinoSelect.prop('disabled', false);
        }
        
        // Reinicializar Select2
        if ($destinoSelect.data('select2')) {
            $destinoSelect.select2('destroy');
        }
        if (typeof window.initSelect2Config === 'function') {
            window.initSelect2Config();
        }
    }
    
    // Listener para el cambio del dropdown de origen
    $(document).on('change', '#agregar-tarifa-origen', function() {
        const origenId = $(this).val();
        actualizarDestinoTarifa(origenId);
    });
    
    // Inicializar Select2 al cargar
    if (typeof window.initSelect2Config === 'function') {
        window.initSelect2Config();
    }
    
    // Funciones de filtrado
    function filtrarTabla(tablaId, filtros) {
        const $tabla = $('#' + tablaId);
        const $filas = $tabla.find('tbody tr');
        const $mensaje = $('#mensaje-sin-resultados-' + tablaId.replace('tabla-', ''));
        let visible = 0;
        
        $filas.each(function() {
            let mostrar = true;
            const $fila = $(this);
            
            // Aplicar cada filtro
            for (const key in filtros) {
                if (filtros.hasOwnProperty(key)) {
                    const valor = filtros[key];
                    if (valor && valor !== '') {
                        const datoFila = $fila.attr('data-' + key);
                        if (!datoFila || datoFila.indexOf(valor.toLowerCase()) === -1) {
                            mostrar = false;
                            break;
                        }
                    }
                }
            }
            
            if (mostrar) {
                $fila.show();
                visible++;
            } else {
                $fila.hide();
            }
        });
        
        // Mostrar/ocultar mensaje de sin resultados
        if (visible === 0) {
            $mensaje.show();
        } else {
            $mensaje.hide();
        }
    }
    
    // Los filtros ahora se envían al servidor, no se necesita filtrado JavaScript
    // El formulario se envía automáticamente al hacer clic en "Buscar" o al presionar Enter
    
    // Limpiar filtros de barrios
    $('#limpiar-filtros-barrios').on('click', function() {
        $('#filtro-barrio-sector').val('').trigger('change');
        $('#filtro-barrio-texto').val('');
        $('#tabla-barrios tbody tr').show();
        $('#mensaje-sin-resultados-barrios').hide();
    });
    
    // Función para normalizar texto (quitar tildes y convertir a minúsculas)
    function normalizarTexto(texto) {
        if (!texto) return '';
        return texto
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }
    
    // Filtros para barrios (búsqueda con ILIKE en nombre, normalizando tildes)
    function filtrarBarrios() {
        const sectorId = $('#filtro-barrio-sector').val();
        const texto = $('#filtro-barrio-texto').val().trim();
        
        // Normalizar el texto de búsqueda
        const textoNormalizado = normalizarTexto(texto);
        
        $('#tabla-barrios tbody tr').each(function() {
            const $fila = $(this);
            let mostrar = true;
            
            // Filtro por sector (exacto)
            if (sectorId && $fila.attr('data-sector-id') !== sectorId) {
                mostrar = false;
            }
            
            // Filtro por nombre (ILIKE - búsqueda parcial case-insensitive, sin tildes)
            if (textoNormalizado) {
                const barrioNombre = $fila.attr('data-barrio-nombre') || '';
                const barrioNombreNormalizado = normalizarTexto(barrioNombre);
                
                // Búsqueda parcial (como ILIKE '%texto%')
                if (barrioNombreNormalizado.indexOf(textoNormalizado) === -1) {
                    mostrar = false;
                }
            }
            
            if (mostrar) {
                $fila.show();
            } else {
                $fila.hide();
            }
        });
        
        const visible = $('#tabla-barrios tbody tr:visible').length;
        if (visible === 0) {
            $('#mensaje-sin-resultados-barrios').show();
        } else {
            $('#mensaje-sin-resultados-barrios').hide();
        }
    }
    
    $('#filtro-barrio-sector').on('change', filtrarBarrios);
    $('#filtro-barrio-texto').on('input', filtrarBarrios);
    
    // Filtros para sectores
    $('#filtro-sector-texto').on('input', function() {
        const texto = $(this).val().toLowerCase();
        
        $('#tabla-sectores tbody tr').each(function() {
            const $fila = $(this);
            const sectorId = $fila.attr('data-sector-id') || '';
            const sectorNombre = $fila.attr('data-sector-nombre') || '';
            const textoFila = sectorId + ' ' + sectorNombre;
            
            if (textoFila.indexOf(texto) === -1) {
                $fila.hide();
            } else {
                $fila.show();
            }
        });
        
        const visible = $('#tabla-sectores tbody tr:visible').length;
        if (visible === 0) {
            $('#mensaje-sin-resultados-sectores').show();
        } else {
            $('#mensaje-sin-resultados-sectores').hide();
        }
    });
    
    // Filtros para destinos intermunicipales
    $('#filtro-destino-texto, #filtro-destino-estado').on('change input', function() {
        const texto = $('#filtro-destino-texto').val().toLowerCase();
        const estado = $('#filtro-destino-estado').val();
        
        $('#tabla-destinos tbody tr').each(function() {
            const $fila = $(this);
            let mostrar = true;
            
            if (estado && $fila.attr('data-destino-activo') !== estado) {
                mostrar = false;
            }
            
            if (texto) {
                const destinoNombre = $fila.attr('data-destino-nombre') || '';
                if (destinoNombre.indexOf(texto) === -1) {
                    mostrar = false;
                }
            }
            
            if (mostrar) {
                $fila.show();
            } else {
                $fila.hide();
            }
        });
        
        const visible = $('#tabla-destinos tbody tr:visible').length;
        if (visible === 0) {
            $('#mensaje-sin-resultados-destinos').show();
        } else {
            $('#mensaje-sin-resultados-destinos').hide();
        }
    });
    
    // Editar tarifa
    $(document).on('click', '.gofast-btn-editar-tarifa', function() {
        const btn = $(this);
        const tarifaId = btn.attr('data-tarifa-id');
        const tarifaOrigenId = btn.attr('data-tarifa-origen-id');
        const tarifaDestinoId = btn.attr('data-tarifa-destino-id');
        const tarifaPrecio = btn.attr('data-tarifa-precio');
        const reciprocaId = btn.attr('data-tarifa-reciproca-id');
        const reciprocaPrecio = btn.attr('data-tarifa-reciproca-precio');
        
        // Obtener información de la fila para mostrar nombres de sectores
        const fila = btn.closest('tr');
        const origenNombre = fila.find('td').eq(1).find('strong').text().trim();
        const destinoNombre = fila.find('td').eq(2).find('strong').text().trim();
        
        $('#editar-tarifa-id').val(tarifaId);
        $('#editar-tarifa-precio').val(tarifaPrecio);
        
        // Mostrar información de recíproca si existe
        if (reciprocaId && tarifaOrigenId !== tarifaDestinoId) {
            // Buscar la fila de la recíproca por su ID
            const filaReciproca = $('tr').filter(function() {
                return $(this).find('td').first().text().trim() === '#' + reciprocaId;
            });
            let reciprocaOrigenNombre = '';
            let reciprocaDestinoNombre = '';
            
            if (filaReciproca.length > 0) {
                reciprocaOrigenNombre = filaReciproca.find('td').eq(1).find('strong').text().trim();
                reciprocaDestinoNombre = filaReciproca.find('td').eq(2).find('strong').text().trim();
            } else {
                // Si no encontramos la fila, usar los nombres invertidos
                reciprocaOrigenNombre = destinoNombre;
                reciprocaDestinoNombre = origenNombre;
            }
            
            const precioMayor = Math.max(parseInt(tarifaPrecio) || 0, parseInt(reciprocaPrecio) || 0);
            $('#info-reciproca-contenido').html(
                'ID #' + reciprocaId + ': <strong>' + reciprocaOrigenNombre + ' → ' + reciprocaDestinoNombre + '</strong><br>' +
                'Precio actual: <strong>$' + parseInt(reciprocaPrecio || 0).toLocaleString('es-CO') + '</strong><br>' +
                'Precio de esta tarifa: <strong>$' + parseInt(tarifaPrecio || 0).toLocaleString('es-CO') + '</strong><br>' +
                '<span style="color: #28a745; font-weight: 600;">Se aplicará el valor mayor: $' + precioMayor.toLocaleString('es-CO') + '</span>'
            );
            $('#info-reciproca-tarifa').show();
        } else {
            $('#info-reciproca-tarifa').hide();
        }
        
        $('#modal-editar-tarifa').fadeIn(200);
        
        // Inicializar Select2 en los dropdowns del modal
        setTimeout(function() {
            $('#editar-tarifa-origen, #editar-tarifa-destino').each(function() {
                if ($(this).data('select2')) {
                    $(this).select2('destroy');
                }
                $(this).select2({
                    placeholder: '🔍 Escribe para buscar...',
                    width: '100%',
                    dropdownParent: $('#modal-editar-tarifa'),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    matcher: matcherGofast,
                    sorter: sorterGofast
                });
            });
            
            // Establecer valores después de inicializar
            $('#editar-tarifa-origen').val(tarifaOrigenId).trigger('change');
            $('#editar-tarifa-destino').val(tarifaDestinoId).trigger('change');
        }, 100);
    });
    
    // Editar barrio
    $(document).on('click', '.gofast-btn-editar-barrio', function() {
        const btn = $(this);
        $('#editar-barrio-id').val(btn.attr('data-barrio-id'));
        $('#editar-barrio-nombre').val(btn.attr('data-barrio-nombre'));
        $('#modal-editar-barrio').fadeIn(200);
        
        // Inicializar Select2 en el dropdown del modal
        setTimeout(function() {
            if ($('#editar-barrio-sector').data('select2')) {
                $('#editar-barrio-sector').select2('destroy');
            }
            $('#editar-barrio-sector').select2({
                placeholder: '🔍 Escribe para buscar...',
                width: '100%',
                dropdownParent: $('#modal-editar-barrio'),
                allowClear: true,
                minimumResultsForSearch: 0,
                matcher: matcherGofast,
                sorter: sorterGofast
            });
            
            // Establecer valor después de inicializar
            $('#editar-barrio-sector').val(btn.attr('data-barrio-sector-id')).trigger('change');
        }, 100);
    });
    
    // Editar sector
    $(document).on('click', '.gofast-btn-editar-sector', function() {
        const btn = $(this);
        $('#editar-sector-id').val(btn.attr('data-sector-id'));
        $('#editar-sector-nombre').val(btn.attr('data-sector-nombre'));
        $('#modal-editar-sector').fadeIn(200);
    });
    
    // Editar destino intermunicipal
    $(document).on('click', '.gofast-btn-editar-destino-intermunicipal', function() {
        const btn = $(this);
        $('#editar-destino-id').val(btn.attr('data-destino-id'));
        $('#editar-destino-nombre').val(btn.attr('data-destino-nombre'));
        $('#editar-destino-valor').val(btn.attr('data-destino-valor'));
        $('#editar-destino-orden').val(btn.attr('data-destino-orden'));
        $('#editar-destino-activo').prop('checked', btn.attr('data-destino-activo') == '1');
        $('#modal-editar-destino-intermunicipal').fadeIn(200);
    });
    
    // Cerrar modales al hacer clic fuera
    $('[id^="modal-editar-"]').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(200);
        }
    });
});
</script>

<style>
/* Estilos para tabs */
.gofast-config-tab {
    padding: 12px 20px;
    border: none;
    background: #f8f9fa;
    color: #666;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    border-radius: 8px 8px 0 0;
    transition: all 0.2s ease;
}

.gofast-config-tab:hover {
    background: #e9ecef;
}

.gofast-config-tab-active {
    background: #fff;
    color: #000;
    border-bottom: 3px solid var(--gofast-yellow);
}

.gofast-config-tab-content {
    padding-top: 20px;
}

.gofast-config-tab-content h3 {
    margin-top: 0;
    margin-bottom: 20px;
}

.gofast-config-tab-content h4 {
    margin-top: 0;
    margin-bottom: 12px;
    font-size: 16px;
    color: #000;
}
</style>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_admin_configuracion', 'gofast_admin_configuracion_shortcode');


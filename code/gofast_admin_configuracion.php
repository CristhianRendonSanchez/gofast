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

    // 1. AGREGAR TARIFA
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
                    $mensaje = 'Tarifa agregada correctamente.';
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
                    $mensaje = 'Tarifa actualizada correctamente.';
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al actualizar la tarifa.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    // 3. ELIMINAR TARIFA
    if (isset($_POST['gofast_eliminar_tarifa']) && wp_verify_nonce($_POST['gofast_eliminar_tarifa_nonce'], 'gofast_eliminar_tarifa')) {
        $tarifa_id = (int) $_POST['tarifa_id'];

        $eliminado = $wpdb->delete('tarifas', ['id' => $tarifa_id], ['%d']);

        if ($eliminado) {
            $mensaje = 'Tarifa eliminada correctamente.';
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
                        'orden' => $orden
                    ],
                    ['%s', '%d', '%d', '%d']
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

    // Obtener sectores para los formularios
    $sectores = $wpdb->get_results("SELECT id, nombre FROM sectores ORDER BY id ASC");

    // Obtener tarifas con información de sectores (limitado para optimización)
    $tarifas = $wpdb->get_results(
        "SELECT t.*, 
                so.nombre as origen_nombre,
                sd.nombre as destino_nombre
         FROM tarifas t
         LEFT JOIN sectores so ON t.origen_sector_id = so.id
         LEFT JOIN sectores sd ON t.destino_sector_id = sd.id
         ORDER BY t.origen_sector_id, t.destino_sector_id
         LIMIT 1000"
    );
    
    // Obtener sectores que ya tienen tarifas configuradas (como origen o destino)
    // Solo para mostrar en el formulario de agregar tarifa
    $sectores_con_tarifa_ids = $wpdb->get_col(
        "SELECT DISTINCT origen_sector_id FROM tarifas
         UNION
         SELECT DISTINCT destino_sector_id FROM tarifas"
    );
    $sectores_con_tarifa = array_map('intval', $sectores_con_tarifa_ids);

    // Obtener barrios con información de sectores (limitado para optimización)
    $barrios = $wpdb->get_results(
        "SELECT b.*, s.nombre as sector_nombre
         FROM barrios b
         LEFT JOIN sectores s ON b.sector_id = s.id
         ORDER BY b.nombre ASC
         LIMIT 1000"
    );

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
            <h3>💰 Gestión de Tarifas</h3>
            
            <!-- Formulario agregar tarifa -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <h4 style="margin-top: 0;">➕ Agregar Nueva Tarifa</h4>
                <form method="post">
                    <?php wp_nonce_field('gofast_agregar_tarifa', 'gofast_tarifa_nonce'); ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Sector Origen:</label>
                            <select name="origen_sector_id" id="agregar-tarifa-origen" class="gofast-select-search" required style="width: 100%; font-size: 14px;">
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($sectores as $s): ?>
                                    <?php if (!in_array($s->id, $sectores_con_tarifa)): ?>
                                        <option value="<?= esc_attr($s->id) ?>"><?= esc_html($s->nombre) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <small style="display: block; color: #666; font-size: 11px; margin-top: 4px;">
                                Solo sectores sin tarifa configurada
                            </small>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Sector Destino:</label>
                            <select name="destino_sector_id" id="agregar-tarifa-destino" class="gofast-select-search" required style="width: 100%; font-size: 14px;">
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($sectores as $s): ?>
                                    <?php if (!in_array($s->id, $sectores_con_tarifa)): ?>
                                        <option value="<?= esc_attr($s->id) ?>"><?= esc_html($s->nombre) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <small style="display: block; color: #666; font-size: 11px; margin-top: 4px;">
                                Solo sectores sin tarifa configurada
                            </small>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Precio:</label>
                            <input type="number" 
                                   name="precio" 
                                   required 
                                   min="1"
                                   placeholder="Ej: 3500"
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                    <button type="submit" name="gofast_agregar_tarifa" class="gofast-btn-mini">✅ Agregar Tarifa</button>
                </form>
            </div>

            <!-- Filtros de búsqueda -->
            <div class="gofast-box" style="background: #f8f9fa; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h4 style="margin: 0;">🔍 Buscar Tarifas</h4>
                    <button type="button" 
                            id="limpiar-filtros-tarifas" 
                            class="gofast-btn-mini" 
                            style="background: #6c757d; color: #fff; padding: 6px 12px; font-size: 12px;">
                        🗑️ Limpiar Filtros
                    </button>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">ID Sector Origen:</label>
                        <input type="number" 
                               id="filtro-tarifa-origen" 
                               placeholder="Ej: 12"
                               min="1"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">ID Sector Destino:</label>
                        <input type="number" 
                               id="filtro-tarifa-destino" 
                               placeholder="Ej: 15"
                               min="1"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Buscar por precio (exacto):</label>
                        <input type="number" 
                               id="filtro-tarifa-precio" 
                               placeholder="Ej: 3500"
                               min="1"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
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
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tarifas as $t): ?>
                                <tr data-origen-id="<?= esc_attr($t->origen_sector_id) ?>" 
                                    data-destino-id="<?= esc_attr($t->destino_sector_id) ?>"
                                    data-origen-nombre="<?= esc_attr(strtolower($t->origen_nombre ?? '')) ?>"
                                    data-destino-nombre="<?= esc_attr(strtolower($t->destino_nombre ?? '')) ?>"
                                    data-precio="<?= esc_attr($t->precio) ?>">
                                    <td>#<?= esc_html($t->id) ?></td>
                                    <td><?= esc_html($t->origen_nombre ?? 'N/A') ?></td>
                                    <td><?= esc_html($t->destino_nombre ?? 'N/A') ?></td>
                                    <td><strong>$<?= number_format($t->precio, 0, ',', '.') ?></strong></td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" 
                                                class="gofast-btn-mini gofast-btn-editar-tarifa" 
                                                data-tarifa-id="<?= esc_attr($t->id) ?>"
                                                data-tarifa-origen-id="<?= esc_attr($t->origen_sector_id) ?>"
                                                data-tarifa-destino-id="<?= esc_attr($t->destino_sector_id) ?>"
                                                data-tarifa-precio="<?= esc_attr($t->precio) ?>"
                                                style="background: var(--gofast-yellow); color: #000; margin-right: 4px;">
                                            ✏️ Editar
                                        </button>
                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar esta tarifa?');">
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
                <div id="mensaje-sin-resultados-tarifas" style="display:none;text-align:center;padding:20px;color:#666;">
                    No se encontraron tarifas que coincidan con los filtros.
                </div>
            <?php endif; ?>
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
                    <button type="button" 
                            id="limpiar-filtros-barrios" 
                            class="gofast-btn-mini" 
                            style="background: #6c757d; color: #fff; padding: 6px 12px; font-size: 12px;">
                        🗑️ Limpiar Filtros
                    </button>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Buscar por sector:</label>
                        <select id="filtro-barrio-sector" class="gofast-select-search" style="width: 100%; font-size: 14px;">
                            <option value="">Todos los sectores</option>
                            <?php foreach ($sectores as $s): ?>
                                <option value="<?= esc_attr($s->id) ?>"><?= esc_html($s->nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Buscar por nombre (búsqueda parcial):</label>
                        <input type="text" 
                               id="filtro-barrio-texto" 
                               placeholder="Buscar por nombre de barrio..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
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
                <div id="mensaje-sin-resultados-barrios" style="display:none;text-align:center;padding:20px;color:#666;">
                    No se encontraron barrios que coincidan con los filtros.
                </div>
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
                <h4 style="margin-top: 0; margin-bottom: 12px;">🔍 Buscar Sectores</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 13px;">Buscar por nombre o ID:</label>
                        <input type="text" 
                               id="filtro-sector-texto" 
                               placeholder="Buscar por nombre o ID de sector..."
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
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
                <div id="mensaje-sin-resultados-sectores" style="display:none;text-align:center;padding:20px;color:#666;">
                    No se encontraron sectores que coincidan con los filtros.
                </div>
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
                    <?php foreach ($sectores as $s): ?>
                        <option value="<?= esc_attr($s->id) ?>"><?= esc_html($s->nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Sector Destino:</label>
                <select name="destino_sector_id" id="editar-tarifa-destino" class="gofast-select" required style="width:100%;font-size:16px;">
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($sectores as $s): ?>
                        <option value="<?= esc_attr($s->id) ?>"><?= esc_html($s->nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Precio:</label>
                <input type="number" name="precio" id="editar-tarifa-precio" required min="1" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditar('tarifa')">Cancelar</button>
                <button type="submit" class="gofast-btn-mini">Guardar</button>
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
                    <?php foreach ($sectores as $s): ?>
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
        initSelect2Config();
    }, 100);
}

jQuery(document).ready(function($) {
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
    
    // Inicializar Select2 en todos los dropdowns
    function initSelect2Config() {
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
    
    // Inicializar Select2 al cargar
    initSelect2Config();
    
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
    
    // Función para filtrar tarifas
    function filtrarTarifas() {
        const origenId = $('#filtro-tarifa-origen').val();
        const destinoId = $('#filtro-tarifa-destino').val();
        const precio = $('#filtro-tarifa-precio').val();
        
        $('#tabla-tarifas tbody tr').each(function() {
            const $fila = $(this);
            let mostrar = true;
            
            // Filtro por origen (exacto)
            if (origenId && $fila.attr('data-origen-id') !== origenId) {
                mostrar = false;
            }
            
            // Filtro por destino (exacto)
            if (destinoId && $fila.attr('data-destino-id') !== destinoId) {
                mostrar = false;
            }
            
            // Filtro por precio (exacto)
            if (precio && $fila.attr('data-precio') !== precio) {
                mostrar = false;
            }
            
            if (mostrar) {
                $fila.show();
            } else {
                $fila.hide();
            }
        });
        
        // Actualizar mensaje de sin resultados
        const visible = $('#tabla-tarifas tbody tr:visible').length;
        if (visible === 0) {
            $('#mensaje-sin-resultados-tarifas').show();
        } else {
            $('#mensaje-sin-resultados-tarifas').hide();
        }
    }
    
    // Limpiar filtros de tarifas
    $('#limpiar-filtros-tarifas').on('click', function() {
        $('#filtro-tarifa-origen').val('');
        $('#filtro-tarifa-destino').val('');
        $('#filtro-tarifa-precio').val('');
        $('#tabla-tarifas tbody tr').show();
        $('#mensaje-sin-resultados-tarifas').hide();
    });
    
    // Filtros para tarifas (búsqueda exacta)
    $('#filtro-tarifa-origen, #filtro-tarifa-destino, #filtro-tarifa-precio').on('input', filtrarTarifas);
    
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
        $('#editar-tarifa-id').val(btn.attr('data-tarifa-id'));
        $('#editar-tarifa-precio').val(btn.attr('data-tarifa-precio'));
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
            $('#editar-tarifa-origen').val(btn.attr('data-tarifa-origen-id')).trigger('change');
            $('#editar-tarifa-destino').val(btn.attr('data-tarifa-destino-id')).trigger('change');
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


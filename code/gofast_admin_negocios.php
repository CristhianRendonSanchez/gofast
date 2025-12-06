
/***************************************************
 * GOFAST – ADMINISTRACIÓN DE NEGOCIOS (SOLO ADMIN)
 * Shortcode: [gofast_admin_negocios]
 * URL: /admin-negocios
 * 
 * Funcionalidades:
 * - Ver todos los negocios registrados
 * - Editar cualquier negocio
 * - Eliminar negocios
 * - Activar/desactivar negocios
 ***************************************************/
function gofast_admin_negocios_shortcode() {
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

    /*********************************************
     * PROCESAMIENTO DE FORMULARIOS
     *********************************************/

    // 1. EDITAR NEGOCIO
    if (isset($_POST['gofast_editar_negocio']) && wp_verify_nonce($_POST['gofast_editar_negocio_nonce'], 'gofast_editar_negocio')) {
        $negocio_id = (int) $_POST['negocio_id'];
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $tipo = sanitize_text_field($_POST['tipo'] ?? '');
        $tipo_otro = sanitize_text_field($_POST['tipo_otro'] ?? '');
        $barrio_id = isset($_POST['barrio_id']) ? (int) $_POST['barrio_id'] : 0;
        $direccion = sanitize_text_field($_POST['direccion_full'] ?? '');
        $whatsapp = gofast_clean_whatsapp($_POST['whatsapp'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre) || empty($direccion) || $barrio_id <= 0) {
            $mensaje = 'Todos los campos obligatorios deben estar completos.';
            $mensaje_tipo = 'error';
        } else {
            // Si el tipo es "Otro", usar el valor escrito
            if ($tipo === 'Otro' && !empty($tipo_otro)) {
                $tipo = $tipo_otro;
            }

            // Obtener sector del barrio
            $sector_id = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT sector_id FROM barrios WHERE id = %d", $barrio_id)
            );

            if ($sector_id <= 0) {
                $mensaje = 'El barrio seleccionado no tiene un sector válido.';
                $mensaje_tipo = 'error';
            } else {
                $actualizado = $wpdb->update(
                    'negocios_gofast',
                    [
                        'nombre' => $nombre,
                        'tipo' => $tipo,
                        'barrio_id' => $barrio_id,
                        'sector_id' => $sector_id,
                        'direccion_full' => $direccion,
                        'whatsapp' => $whatsapp,
                        'activo' => $activo,
                        'updated_at' => gofast_current_time('mysql')
                    ],
                    ['id' => $negocio_id],
                    ['%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s'],
                    ['%d']
                );

                if ($actualizado !== false) {
                    $mensaje = 'Negocio actualizado correctamente.';
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al actualizar el negocio.';
                    $mensaje_tipo = 'error';
                }
            }
        }
    }

    // 2. ELIMINAR NEGOCIO
    if (isset($_POST['gofast_eliminar_negocio']) && wp_verify_nonce($_POST['gofast_eliminar_negocio_nonce'], 'gofast_eliminar_negocio')) {
        $negocio_id = (int) $_POST['negocio_id'];

        $eliminado = $wpdb->delete(
            'negocios_gofast',
            ['id' => $negocio_id],
            ['%d']
        );

        if ($eliminado) {
            $mensaje = 'Negocio eliminado correctamente.';
            $mensaje_tipo = 'success';
        } else {
            $mensaje = 'Error al eliminar el negocio.';
            $mensaje_tipo = 'error';
        }
    }

    /*********************************************
     * OBTENER DATOS
     *********************************************/

    // Obtener todos los negocios con información del cliente
    $negocios = $wpdb->get_results(
        "SELECT n.*, 
                u.nombre as cliente_nombre, 
                u.telefono as cliente_telefono,
                u.email as cliente_email,
                b.nombre as barrio_nombre,
                s.nombre as sector_nombre
         FROM negocios_gofast n
         LEFT JOIN usuarios_gofast u ON n.user_id = u.id
         LEFT JOIN barrios b ON n.barrio_id = b.id
         LEFT JOIN sectores s ON n.sector_id = s.id
         ORDER BY n.created_at DESC"
    );

    // Obtener barrios para el formulario de edición
    $barrios = $wpdb->get_results("SELECT id, nombre FROM barrios ORDER BY nombre ASC");

    // Tipos de negocio
    $tipos_negocio = ["Restaurante", "Tienda", "Cafetería", "Papelería", "Farmacia", "Otro"];

    ob_start();
    ?>

<div class="gofast-home">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin-bottom:8px;">🏪 Gestión de Negocios</h1>
            <p class="gofast-home-text" style="margin:0;">
                Administra todos los negocios registrados en el sistema.
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

    <!-- Estadísticas -->
    <div class="gofast-box" style="margin-bottom: 20px;">
        <h3>📊 Estadísticas</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-top: 15px;">
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: 700; color: #333;"><?= count($negocios) ?></div>
                <div style="font-size: 13px; color: #666;">Total Negocios</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: 700; color: #155724;"><?= count(array_filter($negocios, function($n) { return $n->activo == 1; })) ?></div>
                <div style="font-size: 13px; color: #666;">Activos</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 8px;">
                <div style="font-size: 24px; font-weight: 700; color: #721c24;"><?= count(array_filter($negocios, function($n) { return $n->activo == 0; })) ?></div>
                <div style="font-size: 13px; color: #666;">Inactivos</div>
            </div>
        </div>
    </div>

    <!-- Listado de negocios -->
    <div class="gofast-box">
        <h3>📋 Todos los Negocios</h3>
        
        <?php if (empty($negocios)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                No hay negocios registrados.
            </p>
        <?php else: ?>
            <!-- Vista Desktop: Tabla -->
            <div class="gofast-negocios-admin-table-wrapper gofast-negocios-admin-desktop">
                <div class="gofast-table-wrap">
                    <table class="gofast-table gofast-negocios-admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Cliente</th>
                                <th>Dirección</th>
                                <th>Barrio</th>
                                <th>Tipo</th>
                                <th>WhatsApp</th>
                                <th>Estado</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($negocios as $n): ?>
                                <tr class="<?= $n->activo == 0 ? 'gofast-row-inactive' : '' ?>">
                                    <td>#<?= esc_html($n->id) ?></td>
                                    <td><strong><?= esc_html($n->nombre) ?></strong></td>
                                    <td>
                                        <?= esc_html($n->cliente_nombre ?? 'N/A') ?><br>
                                        <small style="color: #666;"><?= esc_html($n->cliente_telefono ?? '') ?></small>
                                    </td>
                                    <td><?= esc_html($n->direccion_full) ?></td>
                                    <td><?= esc_html($n->barrio_nombre ?? 'N/A') ?></td>
                                    <td><?= esc_html($n->tipo) ?></td>
                                    <td><?= gofast_clean_whatsapp($n->whatsapp) ? esc_html(gofast_clean_whatsapp($n->whatsapp)) : '—' ?></td>
                                    <td>
                                        <span class="gofast-badge-estado <?= $n->activo == 1 ? 'gofast-badge-estado-entregado' : 'gofast-badge-estado-cancelado' ?>">
                                            <?= $n->activo == 1 ? '✅ Activo' : '❌ Inactivo' ?>
                                        </span>
                                    </td>
                                    <td><?= gofast_date_format($n->created_at, 'd/m/Y') ?></td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" 
                                                class="gofast-btn-mini gofast-btn-editar-negocio-admin" 
                                                data-negocio-id="<?= esc_attr($n->id) ?>"
                                                data-negocio-nombre="<?= esc_attr($n->nombre) ?>"
                                                data-negocio-tipo="<?= esc_attr($n->tipo) ?>"
                                                data-negocio-barrio-id="<?= esc_attr($n->barrio_id) ?>"
                                                data-negocio-direccion="<?= esc_attr($n->direccion_full) ?>"
                                                data-negocio-whatsapp="<?= esc_attr(gofast_clean_whatsapp($n->whatsapp)) ?>"
                                                data-negocio-activo="<?= esc_attr($n->activo) ?>"
                                                style="background: var(--gofast-yellow); color: #000; margin-right: 4px;">
                                            ✏️ Editar
                                        </button>
                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de eliminar este negocio? Esta acción no se puede deshacer.');">
                                            <?php wp_nonce_field('gofast_eliminar_negocio', 'gofast_eliminar_negocio_nonce'); ?>
                                            <input type="hidden" name="gofast_eliminar_negocio" value="1">
                                            <input type="hidden" name="negocio_id" value="<?= esc_attr($n->id) ?>">
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
            </div>

            <!-- Vista Móvil: Cards -->
            <div class="gofast-negocios-admin-cards gofast-negocios-admin-mobile">
                <?php foreach ($negocios as $n): ?>
                    <div class="gofast-negocio-admin-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 12px; border-left: 4px solid <?= $n->activo == 1 ? '#28a745' : '#dc3545' ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div>
                                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">ID: #<?= esc_html($n->id) ?></div>
                                <div style="font-size: 18px; font-weight: 700; color: #000; margin-bottom: 4px;">
                                    <?= esc_html($n->nombre) ?>
                                </div>
                                <span class="gofast-badge-estado <?= $n->activo == 1 ? 'gofast-badge-estado-entregado' : 'gofast-badge-estado-cancelado' ?>" style="font-size: 12px; padding: 4px 10px;">
                                    <?= $n->activo == 1 ? '✅ Activo' : '❌ Inactivo' ?>
                                </span>
                            </div>
                        </div>

                        <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Cliente:</div>
                            <div style="font-size: 14px; font-weight: 600; color: #000;">
                                <?= esc_html($n->cliente_nombre ?? 'N/A') ?>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                <?= esc_html($n->cliente_telefono ?? '') ?>
                            </div>
                        </div>

                        <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Dirección:</div>
                            <div style="font-size: 14px; color: #000;">
                                <?= esc_html($n->direccion_full) ?>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                <?= esc_html($n->barrio_nombre ?? 'N/A') ?>
                            </div>
                        </div>

                        <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <div style="font-size: 13px; color: #666; margin-bottom: 4px;">Tipo:</div>
                            <div style="font-size: 14px; color: #000;">
                                <?= esc_html($n->tipo) ?>
                            </div>
                            <?php 
                            $whatsapp_limpio = gofast_clean_whatsapp($n->whatsapp);
                            if ($whatsapp_limpio): ?>
                                <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                    📱 <?= esc_html($whatsapp_limpio) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="font-size: 11px; color: #999; margin-bottom: 12px;">
                            Registrado: <?= gofast_date_format($n->created_at, 'd/m/Y') ?>
                        </div>

                        <div style="display: flex; gap: 8px; margin-top: 12px;">
                            <button type="button" 
                                    class="gofast-btn-mini gofast-btn-editar-negocio-admin" 
                                    data-negocio-id="<?= esc_attr($n->id) ?>"
                                    data-negocio-nombre="<?= esc_attr($n->nombre) ?>"
                                    data-negocio-tipo="<?= esc_attr($n->tipo) ?>"
                                    data-negocio-barrio-id="<?= esc_attr($n->barrio_id) ?>"
                                    data-negocio-direccion="<?= esc_attr($n->direccion_full) ?>"
                                    data-negocio-whatsapp="<?= esc_attr($n->whatsapp) ?>"
                                    data-negocio-activo="<?= esc_attr($n->activo) ?>"
                                    style="flex: 1; background: var(--gofast-yellow); color: #000;">
                                ✏️ Editar
                            </button>
                            <form method="post" style="flex: 1;" onsubmit="return confirm('¿Estás seguro de eliminar este negocio? Esta acción no se puede deshacer.');">
                                <?php wp_nonce_field('gofast_eliminar_negocio', 'gofast_eliminar_negocio_nonce'); ?>
                                <input type="hidden" name="gofast_eliminar_negocio" value="1">
                                <input type="hidden" name="negocio_id" value="<?= esc_attr($n->id) ?>">
                                <button type="submit" 
                                        style="width: 100%; background: #dc3545; color: white; border: 0; padding: 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                    🗑️ Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para editar negocio -->
<div id="modal-editar-negocio-admin" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
    <div style="max-width:600px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <h2 style="margin-top:0;margin-bottom:12px;font-size:20px;">✏️ Editar Negocio</h2>
        
        <form method="post" id="form-editar-negocio-admin" action="">
            <?php wp_nonce_field('gofast_editar_negocio', 'gofast_editar_negocio_nonce'); ?>
            <input type="hidden" name="gofast_editar_negocio" value="1">
            <input type="hidden" name="negocio_id" id="editar-negocio-id">
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Nombre del negocio:</label>
                <input type="text" 
                       name="nombre" 
                       id="editar-negocio-nombre"
                       required
                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Tipo de negocio:</label>
                <select name="tipo" id="editar-negocio-tipo" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;" onchange="toggleTipoOtroAdmin()">
                    <?php foreach ($tipos_negocio as $tipo): ?>
                        <option value="<?= esc_attr($tipo) ?>"><?= esc_html($tipo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="tipo_otro_wrapper_admin" style="display:none;margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Especificar tipo:</label>
                <input type="text" 
                       name="tipo_otro" 
                       id="editar-negocio-tipo-otro"
                       placeholder="Ej: Barbería"
                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Barrio:</label>
                <select name="barrio_id" id="editar-negocio-barrio" class="gofast-select" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                    <option value="">— Seleccionar barrio —</option>
                    <?php foreach ($barrios as $b): ?>
                        <option value="<?= esc_attr($b->id) ?>"><?= esc_html($b->nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">Dirección completa:</label>
                <input type="text" 
                       name="direccion_full" 
                       id="editar-negocio-direccion"
                       required
                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#000;">WhatsApp:</label>
                <input type="text" 
                       name="whatsapp" 
                       id="editar-negocio-whatsapp"
                       placeholder="Ej: 3001234567"
                       pattern="[0-9]*"
                       inputmode="numeric"
                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:16px;">
                <small style="display:block;color:#666;font-size:12px;margin-top:4px;">
                    Solo números (sin espacios ni caracteres especiales)
                </small>
            </div>
            
            <div style="margin-bottom:16px;">
                <label class="gofast-switch">
                    <input type="checkbox" name="activo" id="editar-negocio-activo" value="1">
                    <span class="gofast-switch-slider"></span>
                    <span class="gofast-switch-label">Negocio activo</span>
                </label>
            </div>
            
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #ddd;">
                <button type="button" class="gofast-btn-mini gofast-btn-outline" onclick="cerrarModalEditarNegocioAdmin()">Cancelar</button>
                <button type="submit" class="gofast-btn-mini">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleTipoOtroAdmin() {
    const tipoSelect = document.getElementById('editar-negocio-tipo');
    const wrapperOtro = document.getElementById('tipo_otro_wrapper_admin');
    if (tipoSelect && wrapperOtro) {
        wrapperOtro.style.display = tipoSelect.value === 'Otro' ? 'block' : 'none';
    }
}

jQuery(document).ready(function($) {
    // Abrir modal de editar negocio
    $(document).on('click', '.gofast-btn-editar-negocio-admin', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const btn = $(this);
        const negocioId = btn.attr('data-negocio-id');
        const negocioNombre = btn.attr('data-negocio-nombre');
        const negocioTipo = btn.attr('data-negocio-tipo');
        const negocioBarrioId = btn.attr('data-negocio-barrio-id');
        const negocioDireccion = btn.attr('data-negocio-direccion');
        const negocioWhatsapp = btn.attr('data-negocio-whatsapp');
        const negocioActivo = btn.attr('data-negocio-activo');
        
        // Llenar formulario
        $('#editar-negocio-id').val(negocioId || '');
        $('#editar-negocio-nombre').val(negocioNombre || '');
        $('#editar-negocio-direccion').val(negocioDireccion || '');
        
        // Limpiar WhatsApp si es un valor problemático (2147483647 o 0)
        let whatsappValue = negocioWhatsapp || '';
        // Valores problemáticos comunes
        if (whatsappValue == '2147483647' || whatsappValue == '0' || whatsappValue == 2147483647 || whatsappValue == 0 || whatsappValue == '2147483648' || whatsappValue == '-2147483648') {
            whatsappValue = '';
        }
        $('#editar-negocio-whatsapp').val(whatsappValue);
        $('#editar-negocio-activo').prop('checked', negocioActivo == '1');
        
        // Manejar tipo
        const tiposDefault = ['Restaurante', 'Tienda', 'Cafetería', 'Papelería', 'Farmacia', 'Otro'];
        if (tiposDefault.includes(negocioTipo)) {
            $('#editar-negocio-tipo').val(negocioTipo);
            $('#editar-negocio-tipo-otro').val('');
        } else {
            $('#editar-negocio-tipo').val('Otro');
            $('#editar-negocio-tipo-otro').val(negocioTipo);
        }
        toggleTipoOtroAdmin();
        
        // Establecer barrio
        if (negocioBarrioId) {
            $('#editar-negocio-barrio').val(negocioBarrioId);
        }
        
        // Mostrar modal
        $('#modal-editar-negocio-admin').fadeIn(200);
        
        return false;
    });
    
    // Cerrar modal
    window.cerrarModalEditarNegocioAdmin = function() {
        $('#modal-editar-negocio-admin').fadeOut(200);
        $('#form-editar-negocio-admin')[0].reset();
    };
    
    // Cerrar modal al hacer clic fuera
    $(document).on('click', '#modal-editar-negocio-admin', function(e) {
        if (e.target === this) {
            window.cerrarModalEditarNegocioAdmin();
        }
    });
});
</script>

<style>
/* Estilos para tabla de negocios admin */
.gofast-negocios-admin-table-wrapper .gofast-table-wrap {
    width: 100%;
    max-width: 100%;
    overflow-x: auto !important;
    overflow-y: visible !important;
    -webkit-overflow-scrolling: touch;
    display: block;
    margin: 0;
    padding: 0;
}

.gofast-negocios-admin-table-wrapper .gofast-negocios-admin-table {
    min-width: 1200px;
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

/* Vista Desktop: Mostrar tabla, ocultar cards */
.gofast-negocios-admin-desktop {
    display: block;
}

/* Vista Móvil: Cards (oculta en desktop) */
.gofast-negocios-admin-mobile {
    display: none;
}

.gofast-negocios-admin-cards {
    width: 100%;
    max-width: 100%;
}

.gofast-negocio-admin-card {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Responsive para móvil */
@media (max-width: 768px) {
    .gofast-negocios-admin-desktop {
        display: none !important;
    }
    
    .gofast-negocios-admin-mobile {
        display: block !important;
    }
}
</style>

    <?php
    return ob_get_clean();
}
add_shortcode('gofast_admin_negocios', 'gofast_admin_negocios_shortcode');


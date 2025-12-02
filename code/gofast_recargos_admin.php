<?php
/***************************************************
 * GOFAST – ADMIN RECARGOS (shortcode)
 * [gofast_recargos_admin]
 ***************************************************/
function gofast_recargos_admin_shortcode() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    global $wpdb;

    $tabla_recargos = 'recargos';
    $tabla_rangos   = 'recargos_rangos';

    $mensaje = '';

    /* ==========================================================
       0. Validar usuario admin (usuarios_gofast.rol = 'admin')
    ========================================================== */
    $usuario = null;
    if (!empty($_SESSION['gofast_user_id'])) {
        $uid = (int) $_SESSION['gofast_user_id'];
        $usuario = $wpdb->get_row(
            "SELECT id, nombre, rol, activo 
             FROM usuarios_gofast 
             WHERE id = $uid AND activo = 1"
        );
    }

    if (!$usuario || strtolower($usuario->rol) !== 'admin') {
        return "<div class='gofast-box'>
                    ⚠️ Solo los administradores pueden gestionar recargos.
                </div>";
    }

    /* ==========================================================
       1. Procesar POST (crear, editar, eliminar)
    ========================================================== */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (
            empty($_POST['gofast_recargos_nonce']) ||
            !wp_verify_nonce($_POST['gofast_recargos_nonce'], 'gofast_recargos_admin')
        ) {
            return "<div class='gofast-box'>🔒 Error de seguridad al guardar cambios.</div>";
        }

        /* ----------------------------------------------
           A) Crear nuevo recargo FIJO
        ---------------------------------------------- */
        if (!empty($_POST['gofast_crear_fijo'])) {

            $nombre     = sanitize_text_field($_POST['nuevo_fijo_nombre'] ?? '');
            $valor_fijo = (int) ($_POST['nuevo_fijo_valor'] ?? 0);
            $activo     = !empty($_POST['nuevo_fijo_activo']) ? 1 : 0;

            if ($nombre !== '' && $valor_fijo > 0) {
                $ok = $wpdb->insert($tabla_recargos, [
                    'nombre'     => $nombre,
                    'tipo'       => 'fijo',
                    'valor_fijo' => $valor_fijo,
                    'activo'     => $activo,
                ]);

                if ($ok !== false) {
                    $mensaje = "✅ Recargo fijo creado correctamente.";
                } else {
                    $mensaje = "❌ Error al crear recargo fijo: " . esc_html($wpdb->last_error);
                }
            } else {
                $mensaje = "⚠️ Debes indicar nombre y valor fijo mayor a 0.";
            }
        }

        /* ----------------------------------------------
           B) Crear nuevo recargo POR VALOR + rangos
        ---------------------------------------------- */
        if (!empty($_POST['gofast_crear_variable'])) {

            $nombre = sanitize_text_field($_POST['nuevo_var_nombre'] ?? '');
            $activo = !empty($_POST['nuevo_var_activo']) ? 1 : 0;

            $mins   = $_POST['nuevo_var_rango_min']   ?? [];
            $maxs   = $_POST['nuevo_var_rango_max']   ?? [];
            $valors = $_POST['nuevo_var_rango_valor'] ?? [];

            if ($nombre === '') {
                $mensaje = "⚠️ Debes indicar un nombre para el recargo variable.";
            } else {
                $ok = $wpdb->insert($tabla_recargos, [
                    'nombre'     => $nombre,
                    'tipo'       => 'por_valor',
                    'valor_fijo' => 0,
                    'activo'     => $activo,
                ]);

                if ($ok === false) {
                    $mensaje = "❌ Error al crear recargo variable: " . esc_html($wpdb->last_error);
                } else {
                    $recargo_id = (int) $wpdb->insert_id;

                    // Insertar rangos iniciales (si vienen)
                    if (!empty($mins) && is_array($mins)) {
                        foreach ($mins as $idx => $min) {
                            $min   = (int) ($mins[$idx]   ?? 0);
                            $max   = (int) ($maxs[$idx]   ?? 0);
                            $rec   = (int) ($valors[$idx] ?? 0);

                            if ($rec <= 0) {
                                continue;
                            }

                            $wpdb->insert($tabla_rangos, [
                                'recargo_id' => $recargo_id,
                                'monto_min'  => $min,
                                'monto_max'  => $max,
                                'recargo'    => $rec,
                                'activo'     => 1,
                            ]);
                        }
                    }

                    $mensaje = "✅ Recargo por valor creado correctamente.";
                }
            }
        }

        /* ----------------------------------------------
           C) Editar / eliminar recargos + rangos
        ---------------------------------------------- */
        if (!empty($_POST['gofast_guardar_recargos'])) {

            // 1) Eliminar RANGOS seleccionados
            if (!empty($_POST['eliminar_rango']) && is_array($_POST['eliminar_rango'])) {
                foreach ($_POST['eliminar_rango'] as $rid => $flag) {
                    if ($flag != '1') continue;
                    $rid = (int) $rid;
                    if ($rid > 0) {
                        $wpdb->delete($tabla_rangos, ['id' => $rid]);
                    }
                }
            }

            // 2) Eliminar RECARGOS seleccionados (y sus rangos)
            if (!empty($_POST['eliminar_recargo']) && is_array($_POST['eliminar_recargo'])) {
                foreach ($_POST['eliminar_recargo'] as $rid => $flag) {
                    if ($flag != '1') continue;
                    $rid = (int) $rid;
                    if ($rid > 0) {
                        $wpdb->delete($tabla_rangos, ['recargo_id' => $rid]);
                        $wpdb->delete($tabla_recargos, ['id' => $rid]);
                    }
                }
            }

            // 3) Actualizar recargos existentes
            if (!empty($_POST['recargos']) && is_array($_POST['recargos'])) {
                foreach ($_POST['recargos'] as $rid => $data) {
                    $rid = (int) $rid;
                    if ($rid <= 0) continue;

                    $activo     = !empty($data['activo']) ? 1 : 0;
                    $nombre     = sanitize_text_field($data['nombre'] ?? '');
                    $tipo       = $data['tipo'] ?? 'fijo';
                    $valor_fijo = (int) ($data['valor_fijo'] ?? 0);

                    $update_data = [
                        'activo' => $activo,
                        'nombre' => $nombre,
                    ];

                    if ($tipo === 'fijo') {
                        $update_data['valor_fijo'] = $valor_fijo;
                    }

                    $wpdb->update(
                        $tabla_recargos,
                        $update_data,
                        ['id' => $rid]
                    );
                }
            }

            // 4) Actualizar rangos existentes
            if (!empty($_POST['rangos']) && is_array($_POST['rangos'])) {
                foreach ($_POST['rangos'] as $recargo_id => $rangos) {
                    foreach ($rangos as $rango_id => $datos) {
                        $rango_id = (int) $rango_id;
                        if ($rango_id <= 0) continue;

                        $min = (int) ($datos['min']   ?? 0);
                        $max = (int) ($datos['max']   ?? 0);
                        $rec = (int) ($datos['valor'] ?? 0);

                        $wpdb->update(
                            $tabla_rangos,
                            [
                                'monto_min' => $min,
                                'monto_max' => $max,
                                'recargo'   => $rec,
                            ],
                            ['id' => $rango_id]
                        );
                    }
                }
            }

            // 5) Nuevos rangos para recargos existentes
            if (!empty($_POST['nuevos_rangos']) && is_array($_POST['nuevos_rangos'])) {
                foreach ($_POST['nuevos_rangos'] as $recargo_id => $datos) {
                    $recargo_id = (int) $recargo_id;
                    if ($recargo_id <= 0) continue;

                    $mins   = $datos['min']   ?? [];
                    $maxs   = $datos['max']   ?? [];
                    $valors = $datos['valor'] ?? [];

                    if (!is_array($mins)) continue;

                    foreach ($mins as $idx => $min) {
                        $min = (int) ($mins[$idx]   ?? 0);
                        $max = (int) ($maxs[$idx]   ?? 0);
                        $rec = (int) ($valors[$idx] ?? 0);

                        if ($rec <= 0) continue;

                        $wpdb->insert($tabla_rangos, [
                            'recargo_id' => $recargo_id,
                            'monto_min'  => $min,
                            'monto_max'  => $max,
                            'recargo'    => $rec,
                            'activo'     => 1,
                        ]);
                    }
                }
            }

            if ($mensaje === '') {
                $mensaje = "✅ Cambios guardados correctamente.";
            }
        }
    }

    /* ==========================================================
       2. Cargar recargos + rangos actuales
    ========================================================== */
    $recargos = $wpdb->get_results("
        SELECT * FROM {$tabla_recargos}
        ORDER BY tipo ASC, nombre ASC
    ");

    $recargos_fijos    = [];
    $recargos_var      = [];
    $rangos_por_recargo = [];

    if ($recargos) {
        foreach ($recargos as $r) {
            if ($r->tipo === 'por_valor') {
                $recargos_var[] = $r;
            } else {
                $recargos_fijos[] = $r;
            }
        }

        if (!empty($recargos_var)) {
            $ids = implode(',', array_map('intval', wp_list_pluck($recargos_var, 'id')));
            $rangos = $wpdb->get_results("
                SELECT * FROM {$tabla_rangos}
                WHERE recargo_id IN ($ids)
                ORDER BY recargo_id ASC, monto_min ASC
            ");

            foreach ($rangos as $rg) {
                $rangos_por_recargo[$rg->recargo_id][] = $rg;
            }
        }
    }

    /* ==========================================================
       3. HTML
    ========================================================== */
    ob_start();
    ?>

<div class="gofast-home">
    <h1 style="margin-bottom:8px;">⚙️ Administración de recargos</h1>
    <p class="gofast-home-text">
        Define recargos fijos y por valor que se aplican automáticamente a las cotizaciones.
    </p>

    <?php if ($mensaje): ?>
        <div class="gofast-box" style="margin-bottom:15px;">
            <?= esc_html($mensaje); ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('gofast_recargos_admin', 'gofast_recargos_nonce'); ?>

        <!-- =====================================================
             A) CREAR NUEVO RECARGO FIJO
        ====================================================== -->
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">➕ Nuevo recargo fijo</h3>

            <div class="gofast-recargo-nuevo">
                <div>
                    <label>Nombre del recargo</label>
                    <input type="text" name="nuevo_fijo_nombre" placeholder="Ej: Recargo nocturno" />
                </div>
                <div>
                    <label>Valor fijo (COP)</label>
                    <input type="number" name="nuevo_fijo_valor" placeholder="Ej: 2000" min="0" />
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <label class="gofast-switch">
                        <input type="checkbox" name="nuevo_fijo_activo" value="1" checked>
                        <span class="gofast-switch-slider"></span>
                        <span class="gofast-switch-label">Activo</span>
                    </label>
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button type="submit" name="gofast_crear_fijo" class="gofast-small-btn">
                        💾 Crear recargo fijo
                    </button>
                </div>
            </div>
        </div>

        <!-- =====================================================
             B) CREAR NUEVO RECARGO POR VALOR
        ====================================================== -->
        <div class="gofast-box" style="margin-bottom:24px;">
            <h3 style="margin-top:0;">➕ Nuevo recargo por valor</h3>

            <div class="gofast-recargo-nuevo">
                <div>
                    <label>Nombre del recargo</label>
                    <input type="text" name="nuevo_var_nombre" placeholder="Ej: Recargo por lluvia" />
                </div>

                <div style="display:flex;align-items:flex-end;">
                    <label class="gofast-switch">
                        <input type="checkbox" name="nuevo_var_activo" value="1" checked>
                        <span class="gofast-switch-slider"></span>
                        <span class="gofast-switch-label">Activo</span>
                    </label>
                </div>

                <div style="flex:1;min-width:100%;">
                    <table class="gofast-rangos-table">
                        <thead>
                            <tr>
                                <th>Monto mínimo</th>
                                <th>Monto máximo</th>
                                <th>Recargo (COP)</th>
                            </tr>
                        </thead>
                        <tbody id="gofast-nuevo-var-rangos">
                            <tr>
                                <td><input type="number" name="nuevo_var_rango_min[]"   placeholder="0" min="0"></td>
                                <td><input type="number" name="nuevo_var_rango_max[]"   placeholder="0 = sin límite" min="0"></td>
                                <td><input type="number" name="nuevo_var_rango_valor[]" placeholder="Ej: 2000" min="0"></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button"
                            class="gofast-small-btn"
                            id="btn-add-nuevo-var-rango">
                        ➕ Agregar rango
                    </button>
                </div>

                <div style="display:flex;align-items:flex-end;">
                    <button type="submit" name="gofast_crear_variable" class="gofast-small-btn">
                        💾 Crear recargo por valor
                    </button>
                </div>
            </div>
        </div>

        <!-- =====================================================
             C) EDITAR RECARGOS EXISTENTES
        ====================================================== -->

        <!-- 1) Recargos FIJOS -->
        <div class="gofast-box" style="margin-bottom:24px;">
            <h3 style="margin-top:0;">🔧 Recargos fijos</h3>

            <?php if (empty($recargos_fijos)): ?>
                <p style="margin:0;">No hay recargos fijos creados.</p>
            <?php else: ?>
                <table class="gofast-recargos-table">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Nombre</th>
                            <th>Valor fijo (COP)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recargos_fijos as $r): ?>
                        <tr class="<?= $r->activo ? 'gofast-row-active' : 'gofast-row-inactive'; ?>">
                            <td>
                                <label class="gofast-switch">
                                    <input type="checkbox"
                                           name="recargos[<?= esc_attr($r->id); ?>][activo]"
                                           value="1"
                                           <?= $r->activo ? 'checked' : ''; ?>>
                                    <span class="gofast-switch-slider"></span>
                                    <span class="gofast-switch-label">Activo</span>
                                </label>
                                <br>
                                <button type="button"
                                        class="gofast-small-btn gofast-chip-danger gofast-delete-recargo"
                                        data-recargo-id="<?= esc_attr($r->id); ?>">
                                    🗑 Eliminar
                                </button>
                                <input type="hidden"
                                       name="recargos[<?= esc_attr($r->id); ?>][tipo]"
                                       value="fijo">
                            </td>
                            <td>
                                <input type="text"
                                       name="recargos[<?= esc_attr($r->id); ?>][nombre]"
                                       value="<?= esc_attr($r->nombre); ?>">
                            </td>
                            <td>
                                <input type="number"
                                       name="recargos[<?= esc_attr($r->id); ?>][valor_fijo]"
                                       value="<?= esc_attr($r->valor_fijo); ?>"
                                       min="0">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 2) Recargos POR VALOR -->
        <div class="gofast-box">
            <h3 style="margin-top:0;">🔧 Recargos por valor</h3>

            <?php if (empty($recargos_var)): ?>
                <p style="margin:0;">No hay recargos por valor creados.</p>
            <?php else: ?>
                <?php foreach ($recargos_var as $r): ?>
                    <div class="gofast-recargo-bloque <?= $r->activo ? 'gofast-row-active' : 'gofast-row-inactive'; ?>">

                        <div class="gofast-recargo-header">
                            <div>
                                <label class="gofast-switch">
                                    <input type="checkbox"
                                           name="recargos[<?= esc_attr($r->id); ?>][activo]"
                                           value="1"
                                           <?= $r->activo ? 'checked' : ''; ?>>
                                    <span class="gofast-switch-slider"></span>
                                    <span class="gofast-switch-label">Activo</span>
                                </label>

                                <span style="margin-left:10px;font-weight:600;">
                                    <?= esc_html($r->nombre); ?>
                                </span>
                            </div>

                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:11px;background:#000;color:#fff;padding:3px 8px;border-radius:999px;">
                                    Tipo: por valor
                                </span>

                                <button type="button"
                                        class="gofast-small-btn gofast-chip-danger gofast-delete-recargo"
                                        data-recargo-id="<?= esc_attr($r->id); ?>">
                                    🗑 Eliminar
                                </button>
                            </div>

                            <input type="hidden"
                                   name="recargos[<?= esc_attr($r->id); ?>][tipo]"
                                   value="por_valor">
                            <input type="hidden"
                                   name="recargos[<?= esc_attr($r->id); ?>][nombre]"
                                   value="<?= esc_attr($r->nombre); ?>">
                        </div>

                        <table class="gofast-rangos-table">
                            <thead>
                                <tr>
                                    <th>Monto mínimo</th>
                                    <th>Monto máximo</th>
                                    <th>Recargo (COP)</th>
                                    <th style="width:60px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rangos_por_recargo[$r->id] ?? [] as $rg): ?>
                                    <tr>
                                        <td>
                                            <input type="number"
                                                   name="rangos[<?= esc_attr($r->id); ?>][<?= esc_attr($rg->id); ?>][min]"
                                                   value="<?= esc_attr($rg->monto_min); ?>"
                                                   min="0">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="rangos[<?= esc_attr($r->id); ?>][<?= esc_attr($rg->id); ?>][max]"
                                                   value="<?= esc_attr($rg->monto_max); ?>"
                                                   min="0">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="rangos[<?= esc_attr($r->id); ?>][<?= esc_attr($rg->id); ?>][valor]"
                                                   value="<?= esc_attr($rg->recargo); ?>"
                                                   min="0">
                                        </td>
                                        <td style="text-align:center;">
                                            <button type="button"
                                                    class="gofast-icon-btn gofast-chip-danger gofast-delete-rango"
                                                    data-rango-id="<?= esc_attr($rg->id); ?>"
                                                    title="Eliminar rango">
                                                🗑
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Fila para nuevos rangos -->
                                <tr class="gofast-rango-nuevo" data-recargo-id="<?= esc_attr($r->id); ?>">
                                    <td>
                                        <input type="number"
                                               name="nuevos_rangos[<?= esc_attr($r->id); ?>][min][]"
                                               placeholder="0" min="0">
                                    </td>
                                    <td>
                                        <input type="number"
                                               name="nuevos_rangos[<?= esc_attr($r->id); ?>][max][]"
                                               placeholder="0 = sin límite" min="0">
                                    </td>
                                    <td>
                                        <input type="number"
                                               name="nuevos_rangos[<?= esc_attr($r->id); ?>][valor][]"
                                               placeholder="Ej: 2000" min="0">
                                    </td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button"
                                class="gofast-small-btn"
                                onclick="gofastAgregarRangoExistente(<?= esc_attr($r->id); ?>)">
                            ➕ Agregar rango
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="margin-top:18px;text-align:right;">
            <button type="submit"
                    name="gofast_guardar_recargos"
                    value="1"
                    class="gofast-btn-request"
                    style="max-width:260px;margin-left:auto;">
                💾 Guardar cambios
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){

    // Duplicar fila de rangos para "nuevo recargo por valor"
    const nuevoVarBody = document.getElementById('gofast-nuevo-var-rangos');
    const btnAddNuevoVar = document.getElementById('btn-add-nuevo-var-rango');
    if (btnAddNuevoVar && nuevoVarBody) {
        btnAddNuevoVar.addEventListener('click', function(){
            const first = nuevoVarBody.querySelector('tr');
            if (!first) return;
            const clone = first.cloneNode(true);
            clone.querySelectorAll('input').forEach(i => i.value = '');
            nuevoVarBody.appendChild(clone);
        });
    }

    // Función global para agregar rango en recargos existentes
    window.gofastAgregarRangoExistente = function(recargoId){
        const filas = document.querySelectorAll(
            '.gofast-rango-nuevo[data-recargo-id="'+recargoId+'"]'
        );
        if (!filas.length) return;
        const ref = filas[filas.length - 1];
        const clone = ref.cloneNode(true);
        clone.querySelectorAll('input').forEach(i => i.value = '');
        ref.parentNode.appendChild(clone);
    };

    // Eliminar recargo completo
    document.querySelectorAll('.gofast-delete-recargo').forEach(function(btn){
        btn.addEventListener('click', function(){
            const id = btn.getAttribute('data-recargo-id');
            if (!id) return;

            if (!confirm('¿Seguro que quieres eliminar este recargo y todos sus rangos?')) {
                return;
            }

            const form = btn.closest('form');
            if (!form) return;

            let input = form.querySelector('input[name="eliminar_recargo['+id+']"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'eliminar_recargo['+id+']';
                input.value = '1';
                form.appendChild(input);
            }

            let flag = form.querySelector('input[name="gofast_guardar_recargos"]');
            if (!flag) {
                flag = document.createElement('input');
                flag.type = 'hidden';
                flag.name = 'gofast_guardar_recargos';
                flag.value = '1';
                form.appendChild(flag);
            }

            form.submit();
        });
    });

    // Eliminar rango individual
    document.querySelectorAll('.gofast-delete-rango').forEach(function(btn){
        btn.addEventListener('click', function(){
            const id = btn.getAttribute('data-rango-id');
            if (!id) return;

            if (!confirm('¿Eliminar este rango?')) {
                return;
            }

            const form = btn.closest('form');
            if (!form) return;

            let input = form.querySelector('input[name="eliminar_rango['+id+']"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'eliminar_rango['+id+']';
                input.value = '1';
                form.appendChild(input);
            }

            let flag = form.querySelector('input[name="gofast_guardar_recargos"]');
            if (!flag) {
                flag = document.createElement('input');
                flag.type = 'hidden';
                flag.name = 'gofast_guardar_recargos';
                flag.value = '1';
                form.appendChild(flag);
            }

            form.submit();
        });
    });
});
</script>

<?php
    return ob_get_clean();
}
add_shortcode('gofast_recargos_admin', 'gofast_recargos_admin_shortcode');


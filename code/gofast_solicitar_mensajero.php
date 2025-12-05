<?php
/*******************************************************
 * ✅ GOFAST — SOLICITAR MENSAJERO (Resultado de cotización)
 * Shortcode: [gofast_resultado]
 * URL: /solicitar-mensajero
 *******************************************************/
function gofast_resultado_cotizacion() {
    global $wpdb;

    /* ----------------------------------------------------------
       ✅ Validación inicial
    ---------------------------------------------------------- */
    if (!isset($_POST['origen']) || !isset($_POST['destino'])) {
        return "<div class='gofast-box'>No se encontró la cotización.</div>";
    }

    $origen   = intval($_POST['origen']);
    $destinos = array_map('intval', (array) $_POST['destino']);
    
    // Detectar si el origen es un negocio (buscar en todos los negocios si es mensajero/admin)
    $negocio_id_cotizador = 0;
    $negocio_user_id_cotizador = null;
    
    if (!empty($_SESSION['gofast_user_id'])) {
        $user_id_temp = intval($_SESSION['gofast_user_id']);
        $usuario_temp = $wpdb->get_row($wpdb->prepare(
            "SELECT rol FROM usuarios_gofast WHERE id = %d AND activo = 1",
            $user_id_temp
        ));
        
        if ($usuario_temp && (strtolower($usuario_temp->rol) === 'mensajero' || strtolower($usuario_temp->rol) === 'admin')) {
            // Si es mensajero/admin, buscar en todos los negocios
            $todos_negocios_temp = $wpdb->get_results(
                "SELECT n.id, n.barrio_id, n.user_id
                 FROM negocios_gofast n
                 WHERE n.activo = 1"
            );
            
            foreach ($todos_negocios_temp as $neg) {
                if (intval($neg->barrio_id) === $origen) {
                    $negocio_id_cotizador = intval($neg->id);
                    $negocio_user_id_cotizador = intval($neg->user_id);
                    break;
                }
            }
        }
    }

    /* ==========================================================
       ✅ 0) DETECTAR USUARIO GOFAST LOGUEADO
    ========================================================== */
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $usuario             = null;
    $direcciones_previas = [];
    $negocio_usado       = null; // ⭐ negocio detectado como origen (si aplica)

    if (!empty($_SESSION['gofast_user_id'])) {

        $user_id = intval($_SESSION['gofast_user_id']);

        // ✅ Datos del usuario
        $usuario = $wpdb->get_row("
            SELECT id, nombre, telefono, email, rol 
            FROM usuarios_gofast 
            WHERE id = $user_id AND activo = 1
        ");

        // ✅ Cargar negocios del usuario (incluye whatsapp)
        $mis_negocios = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nombre, direccion_full, barrio_id, sector_id, whatsapp
             FROM negocios_gofast
             WHERE user_id = %d AND activo = 1",
            $user_id
        ));

        // 🔍 Detectar si el ORIGEN corresponde al barrio de un negocio
        foreach ((array) $mis_negocios as $n) {
            if (intval($n->barrio_id) === $origen) {
                $negocio_usado = $n;
                break;
            }
        }

        // ✅ Direcciones usadas anteriormente (solo si NO usamos negocio)
        if ($usuario && !$negocio_usado) {

            // Normalizar teléfono del usuario (solo dígitos)
            $tel_norm = preg_replace('/\D+/', '', $usuario->telefono);

            // Direcciones previas usando match de teléfono flexible
            $direcciones_previas = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT direccion_origen
                     FROM servicios_gofast
                     WHERE direccion_origen <> ''
                       AND (
                            user_id = %d
                            OR REPLACE(REPLACE(REPLACE(telefono_cliente, '+', ''), '-', ''), ' ', '') = %s
                       )
                     ORDER BY id DESC
                     LIMIT 10",
                    $usuario->id,
                    $tel_norm
                )
            );
        }
    }

    /* ==========================================================
       ✅ 1) Datos del ORIGEN
    ========================================================== */
    $sector_origen = intval($wpdb->get_var("SELECT sector_id FROM barrios WHERE id = $origen"));
    $nombre_origen = $wpdb->get_var("SELECT nombre FROM barrios WHERE id = $origen");

    /* ==========================================================
       ✅ 2) Recargos activos (FIJOS + POR VALOR)
    ========================================================== */

    // 🔹 Recargos FIJOS (siempre se aplican por cada trayecto)
    $recargos_fijos = $wpdb->get_results("
        SELECT id, nombre, valor_fijo 
        FROM recargos
        WHERE activo = 1 AND tipo = 'fijo'
    ");

    $recargo_fijo_por_envio   = 0;
    $recargos_fijos_aplicados = []; // solo nombres

    foreach ((array) $recargos_fijos as $r) {
        $monto = intval($r->valor_fijo);
        if ($monto > 0) {
            $recargo_fijo_por_envio += $monto;
            $recargos_fijos_aplicados[] = $r->nombre;
        }
    }

    // 🔹 Recargos VARIABLES por rango de valor (ej: lluvia)
    $recargos_variables = $wpdb->get_results("
        SELECT r.id, r.nombre, rr.monto_min, rr.monto_max, rr.recargo
        FROM recargos r
        JOIN recargos_rangos rr ON rr.recargo_id = r.id
        WHERE r.activo = 1 AND r.tipo = 'por_valor'
        ORDER BY rr.monto_min ASC
    ");

    // Helper: dado un valor de tarifa, devuelve:
    //  - total recargo variable
    //  - nombres de recargos aplicados
    $calcular_recargos_variables = function($valor) use ($recargos_variables) {
        $valor = intval($valor);
        $total_variable = 0;
        $nombres = [];

        foreach ((array) $recargos_variables as $r) {
            $min = intval($r->monto_min);
            $max = intval($r->monto_max);
            $rec = intval($r->recargo);

            $cumple_min = ($valor >= $min);
            $cumple_max = ($max <= 0) ? true : ($valor <= $max);

            if ($cumple_min && $cumple_max && $rec > 0) {
                $total_variable += $rec;
                $nombres[] = $r->nombre;
            }
        }

        return [
            'total'   => $total_variable,
            'nombres' => $nombres,
        ];
    };

    /* ==========================================================
       ✅ 3) Procesar destinos + tarifas
    ========================================================== */
    $detalle_envios                = [];
    $total                         = 0;
    $total_recargos_aplicados      = 0; // suma de TODO lo extra vs tarifa base
    $recargos_variables_aplicados  = []; // nombres únicos

    foreach ($destinos as $destino) {

        $sector_destino = intval($wpdb->get_var("SELECT sector_id FROM barrios WHERE id = $destino"));
        $nombre_destino = $wpdb->get_var("SELECT nombre FROM barrios WHERE id = $destino");

        $precio = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT precio FROM tarifas 
                 WHERE origen_sector_id=%d AND destino_sector_id=%d",
                $sector_origen,
                $sector_destino
            )
        );

        $precio = $precio ? intval($precio) : 0;

        // Recargos variables para este trayecto
        $calc_var                  = $calcular_recargos_variables($precio);
        $recargo_variable_monto    = $calc_var['total'];
        $recargo_variable_nombres  = $calc_var['nombres'];

        // Marcar recargos variables aplicados (para mostrar solo nombres luego)
        foreach ($recargo_variable_nombres as $nombre_recargo) {
            $recargos_variables_aplicados[$nombre_recargo] = true;
        }

        // Total de recargos para este trayecto
        $recargo_total_trayecto = $recargo_fijo_por_envio + $recargo_variable_monto;

        // Total final trayecto
        $total_trayecto = $precio + $recargo_total_trayecto;

        $detalle_envios[] = [
            "destino"                  => $nombre_destino,
            "valor"                    => $precio,
            "recargo_fijo"             => $recargo_fijo_por_envio,
            "recargo_variable_monto"   => $recargo_variable_monto,
            "recargo_variable_nombres" => $recargo_variable_nombres,
            "recargo_total"            => $recargo_total_trayecto,
            "total_trayecto"           => $total_trayecto,
        ];

        $total                    += $total_trayecto;
        $total_recargos_aplicados += $recargo_total_trayecto;
    }

    // Lista final de nombres de recargos aplicados (sin duplicados)
    $lista_nombres_recargos = [];

    foreach ($recargos_fijos_aplicados as $nombre) {
        if ($nombre !== '' && !in_array($nombre, $lista_nombres_recargos, true)) {
            $lista_nombres_recargos[] = $nombre;
        }
    }

    foreach (array_keys($recargos_variables_aplicados) as $nombre) {
        if ($nombre !== '' && !in_array($nombre, $lista_nombres_recargos, true)) {
            $lista_nombres_recargos[] = $nombre;
        }
    }

    /* ==========================================================
       ✅ 4) VALORES POR DEFECTO (NEGOCIO / USUARIO)
    ========================================================== */

    // Regla 1.C: whatsapp negocio si existe, si no teléfono usuario
    $nombre_default =
        $negocio_usado ? $negocio_usado->nombre :
        ($usuario ? $usuario->nombre : '');

    $telefono_default =
        ($negocio_usado && !empty($negocio_usado->whatsapp))
            ? $negocio_usado->whatsapp
            : ($usuario ? $usuario->telefono : '');

    $dir_origen_default =
        $negocio_usado ? $negocio_usado->direccion_full : '';

    /* ==========================================================
	   ✅ 5) SI CONFIRMA → GUARDAR SERVICIO Y REDIRIGIR
	========================================================== */
	if (!empty($_POST['confirmar'])) {

		$nombre = sanitize_text_field($_POST['nombre']);
		$tel    = sanitize_text_field($_POST['telefono']);
		
		// Obtener negocio_id del POST (desde cotizador) o detectar por barrio_id
		$negocio_id = $negocio_id_cotizador;
		$negocio_user_id = $negocio_user_id_cotizador;
		$negocio_seleccionado = null;
		$cliente_propietario = null;
		
		// Si hay negocio detectado (ya sea desde POST o desde $negocio_usado), obtener datos
		if ($negocio_id > 0) {
			// Si es mensajero/admin y hay negocio_user_id, buscar por ese user_id
			if ($negocio_user_id && $usuario && (strtolower($usuario->rol) === 'mensajero' || strtolower($usuario->rol) === 'admin')) {
				$negocio_seleccionado = $wpdb->get_row($wpdb->prepare(
					"SELECT n.*, u.nombre as cliente_nombre, u.telefono as cliente_telefono
					 FROM negocios_gofast n
					 INNER JOIN usuarios_gofast u ON n.user_id = u.id
					 WHERE n.id = %d AND n.user_id = %d AND n.activo = 1 AND u.activo = 1",
					$negocio_id,
					$negocio_user_id
				));
			} elseif ($negocio_usado && intval($negocio_usado->id) === $negocio_id) {
				// Si ya tenemos el negocio_usado y coincide, usarlo
				$negocio_seleccionado = $negocio_usado;
			} else {
				// Buscar negocio del usuario logueado
				if ($usuario) {
					$negocio_seleccionado = $wpdb->get_row($wpdb->prepare(
						"SELECT id, nombre, direccion_full, whatsapp, user_id
						 FROM negocios_gofast
						 WHERE id = %d AND user_id = %d AND activo = 1",
						$negocio_id,
						$usuario->id
					));
				}
			}
			
			if ($negocio_seleccionado) {
				$cliente_propietario = $negocio_seleccionado->user_id;
				// Usar datos del NEGOCIO (no del cliente)
				$nombre = $negocio_seleccionado->nombre; // Nombre del negocio
				$tel = $negocio_seleccionado->whatsapp ?: ($negocio_seleccionado->cliente_telefono ?? ($usuario ? $usuario->telefono : '')); // WhatsApp del negocio primero
			}
		} elseif ($negocio_usado) {
			// Si no hay negocio_id del POST pero hay negocio_usado detectado, usarlo
			$negocio_seleccionado = $negocio_usado;
			$cliente_propietario = $negocio_usado->user_id;
			// Usar datos del NEGOCIO (no del cliente)
			$nombre = $negocio_usado->nombre; // Nombre del negocio
			$tel = $negocio_usado->whatsapp ?: ($usuario ? $usuario->telefono : ''); // WhatsApp del negocio primero
		}

		// Dirección origen
		$dir_origen = !empty($_POST['dir_origen_custom'])
			? sanitize_text_field($_POST['dir_origen_custom'])
			: sanitize_text_field($_POST['dir_origen']);
		
		// Si hay negocio seleccionado, usar su dirección
		if ($negocio_seleccionado && $negocio_seleccionado->direccion_full) {
			$dir_origen = $negocio_seleccionado->direccion_full;
		}

		// Direcciones destino opcionales
		$dirs_dest   = isset($_POST['dir_destino'])   ? (array) $_POST['dir_destino']   : [];
		$montos_dest = isset($_POST['monto_destino']) ? (array) $_POST['monto_destino'] : [];

		foreach ($montos_dest as $i => $m) {
			$m = trim($m);
			$montos_dest[$i] = ($m === "" ? 0 : intval(preg_replace('/[^\d]/', '', $m)));
		}

		// JSON de origen
		$origen_completo = [
			"barrio_id"     => $origen,
			"barrio_nombre" => $nombre_origen,
			"sector_id"     => $sector_origen,
			"direccion"     => $dir_origen,
			"negocio_id"    => $negocio_seleccionado ? $negocio_seleccionado->id : null,
			"negocio_user_id" => $negocio_seleccionado ? $negocio_seleccionado->user_id : null,
		];

		// JSON de destinos
		$destinos_completos = [];
		foreach ($destinos as $i => $dest_id) {

			$barrio_nombre = $wpdb->get_var("SELECT nombre FROM barrios WHERE id = $dest_id");
			$sector_id     = intval($wpdb->get_var("SELECT sector_id FROM barrios WHERE id = $dest_id"));

			$destinos_completos[] = [
				"barrio_id"     => $dest_id,
				"barrio_nombre" => $barrio_nombre,
				"sector_id"     => $sector_id,
				"direccion"     => !empty($dirs_dest[$i]) ? sanitize_text_field($dirs_dest[$i]) : "",
				"monto"         => !empty($montos_dest[$i]) ? $montos_dest[$i] : 0,
			];
		}

		$json_final = json_encode(
			[
				"origen"   => $origen_completo,
				"destinos" => $destinos_completos,
			],
			JSON_UNESCAPED_UNICODE
		);

		// Determinar user_id: si hay negocio seleccionado, usar el cliente propietario
		$user_id_servicio = $usuario ? $usuario->id : null;
		if ($negocio_seleccionado && $cliente_propietario) {
			$user_id_servicio = $cliente_propietario; // Asociar al cliente propietario del negocio
		}
		
		// Guardar servicio
		$insertado = $wpdb->insert("servicios_gofast", [
			"nombre_cliente"   => $nombre,
			"telefono_cliente" => $tel,
			"direccion_origen" => $dir_origen,
			"destinos"         => $json_final,
			"total"            => $total,
			"estado"           => "pendiente",
			"tracking_estado"  => "pendiente",
			"mensajero_id"     => null,
			"user_id"          => $user_id_servicio,
		]);

		// ⚠️ Si falla el INSERT, mostramos el error en pantalla
		if ($insertado === false) {
			return "<div class='gofast-box'>
						<b>Error al guardar el servicio:</b><br>" .
						esc_html($wpdb->last_error) .
				   "</div>";
		}

		$service_id = (int) $wpdb->insert_id;

		if (!$service_id) {
			return "<div class='gofast-box'>No se pudo obtener el ID del servicio.</div>";
		}

		$_SESSION["gofast_pending_service"] = $service_id;

		// ✅ IMPORTANTE: redirigir con JavaScript (no con wp_redirect)
		$url = esc_url( home_url('/servicio-registrado?id=' . $service_id) );

		return '<script>window.location.href = "'.$url.'";</script>';
	}


    /* ==========================================================
       ✅ 6) HTML FINAL
    ========================================================== */
    ob_start();
    ?>

    <div class="gofast-checkout-wrapper">

        <!-- ✅ COLUMNA IZQUIERDA -->
        <div class="gofast-box">

            <h3 style="margin-top:0;">📍 Origen: <?= esc_html($nombre_origen) ?></h3>

            <div id="destinos-resumen">
                <?php foreach ($detalle_envios as $idx => $d): ?>
                    <div class="gofast-destino-resumen-item" 
                         data-destino-id="<?= esc_attr($destinos[$idx]) ?>"
                         data-destino-index="<?= esc_attr($idx) ?>"
                         data-precio="<?= esc_attr($d['valor']) ?>"
                         data-recargo="<?= esc_attr($d['recargo_total']) ?>"
                         data-total="<?= esc_attr($d['total_trayecto']) ?>">
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#f8f9fa;border-radius:8px;margin-bottom:10px;border-left:4px solid #F4C524;">
                            <div style="flex:1;">
                                <strong>🎯 <?= esc_html($d["destino"]) ?></strong><br>
                                <small style="color:#666;">
                                    Base: $<?= number_format($d['valor'], 0, ',', '.') ?> | 
                                    Recargo: $<?= number_format($d['recargo_total'], 0, ',', '.') ?>
                                </small><br>
                                <strong style="color:#4CAF50;font-size:18px;">
                                    $<?= number_format($d["total_trayecto"], 0, ',', '.') ?>
                                </strong>
                            </div>
                            <button type="button" class="gofast-btn-delete" onclick="eliminarDestinoResumen(<?= esc_attr($idx) ?>)" style="margin-left:12px;padding:8px 12px;">
                                ❌
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($lista_nombres_recargos)): ?>
                <div class="gofast-recargo-box" style="margin:16px 0;">
                    🟡 <b>Recargos aplicados:</b><br>
                    <ul style="margin:8px 0 0 20px;">
                        <?php foreach ($lista_nombres_recargos as $nombre): ?>
                            <li><?= esc_html($nombre) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="gofast-total-box" style="margin:20px 0;text-align:center;" id="total-box">
                💰 <strong style="font-size:24px;" id="total-amount">Total: $<?= number_format($total, 0, ',', '.') ?></strong>
            </div>

            <!-- Agregar nuevo destino -->
            <?php 
            // Obtener todos los barrios para el select
            $barrios_all = $wpdb->get_results("SELECT id, nombre FROM barrios ORDER BY nombre ASC");
            $destinos_ids = array_map('intval', $destinos);
            ?>
            <div style="background:#f8f9fa;padding:16px;border-radius:8px;margin:20px 0;">
                <label><strong>➕ Agregar nuevo destino</strong></label>
                <select id="nuevo-destino-select" class="gofast-select" style="margin-top:8px;">
                    <option value="">Seleccionar barrio...</option>
                    <?php foreach ($barrios_all as $b): ?>
                        <?php if (!in_array($b->id, $destinos_ids)): ?>
                            <option value="<?= esc_attr($b->id) ?>" data-nombre="<?= esc_attr($b->nombre) ?>">
                                <?= esc_html($b->nombre) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="btn-agregar-destino-resumen" class="gofast-btn-mini" style="margin-top:8px;width:100%;">
                    ➕ Agregar destino
                </button>
            </div>

        </div>

        <!-- ✅ COLUMNA DERECHA -->
        <div class="gofast-box">

            <h3>Datos del servicio</h3>

            <form method="post" id="form-solicitar-servicio">
			<input type="hidden" name="origen" id="input-origen" value="<?= esc_attr($origen) ?>">
			<div id="destinos-hidden-inputs">
				<?php foreach ($destinos as $idx => $d): ?>
					<input type="hidden" name="destino[]" class="destino-input" value="<?= esc_attr($d) ?>" data-index="<?= esc_attr($idx) ?>">
				<?php endforeach; ?>
			</div>
			<?php if ($negocio_id_cotizador > 0): ?>
				<input type="hidden" name="negocio_id" value="<?= esc_attr($negocio_id_cotizador) ?>">
				<input type="hidden" name="cliente_id" value="<?= esc_attr($negocio_user_id_cotizador) ?>">
			<?php elseif ($negocio_usado): ?>
				<input type="hidden" name="negocio_id" value="<?= esc_attr($negocio_usado->id) ?>">
				<input type="hidden" name="cliente_id" value="<?= esc_attr($negocio_usado->user_id) ?>">
			<?php endif; ?>
			<input type="hidden" name="confirmar" value="1">

                <div class="gofast-2col">
                    <div>
                        <label>Tu nombre</label>
                        <input type="text" name="nombre" required
                               value="<?= esc_attr($nombre_default) ?>">
                    </div>

                    <div>
                        <label>WhatsApp</label>
                        <input type="tel" name="telefono" required pattern="[0-9]{10}"
                               value="<?= esc_attr($telefono_default) ?>">
                    </div>
                </div>

                <label>Dirección origen</label>

                <?php if ($negocio_usado): ?>

                    <!-- Origen desde negocio -->
                    <input type="text" name="dir_origen"
                           value="<?= esc_attr($dir_origen_default) ?>" required>

                <?php elseif (!empty($direcciones_previas)): ?>

                    <select name="dir_origen" class="gofast-select" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($direcciones_previas as $dir): ?>
                            <option value="<?= esc_attr($dir) ?>"><?= esc_html($dir) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <small>Si no aparece tu dirección, escribe una nueva:</small>
                    <input type="text" name="dir_origen_custom"
                           placeholder="Calle 10 #5-22" style="margin-top:8px;">

                <?php else: ?>

                    <input type="text" name="dir_origen" required placeholder="Calle 10 #5-22">

                <?php endif; ?>

                <br><br>

                <b>Direcciones destino + monto a pagar</b><br><br>

                <div id="direcciones-destinos-wrapper">
                    <?php foreach ($detalle_envios as $idx => $d): ?>
                        <div class="gofast-dir-item" data-destino-index="<?= esc_attr($idx) ?>">
                            <label><?= esc_html($d["destino"]) ?></label>

                            <!-- Dirección destino OPCIONAL -->
                            <input type="text" name="dir_destino[]" placeholder="Ej: Calle 12 #3-15">

                            <!-- Monto OPCIONAL -->
                            <input type="text" name="monto_destino[]" class="gofast-money" placeholder="$ 0">
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="gofast-btn-group">
                   <button type="submit" class="gofast-btn-request">
						💌 Solicitar servicio
					</button>

                    <a href="<?php echo esc_url( home_url('/cotizar') ); ?>" class="gofast-btn-action gofast-secondary">🔄 Hacer otra cotización</a>
                </div>

            </form>

        </div>

    </div>

    <script>
    // Datos para JavaScript
    const datosResumen = {
        origen: <?= json_encode($origen) ?>,
        sector_origen: <?= json_encode($sector_origen) ?>,
        recargo_fijo: <?= json_encode($recargo_fijo_por_envio) ?>,
        recargos_variables: <?= json_encode(array_map(function($r) {
            return [
                'min' => intval($r->monto_min),
                'max' => intval($r->monto_max),
                'recargo' => intval($r->recargo)
            ];
        }, $recargos_variables)) ?>
    };
    
    // Mapa de barrios con sus sectores
    const barriosMap = <?= json_encode(array_map(function($b) use ($wpdb) {
        $sector = intval($wpdb->get_var($wpdb->prepare("SELECT sector_id FROM barrios WHERE id = %d", $b->id)));
        return ['id' => $b->id, 'nombre' => $b->nombre, 'sector_id' => $sector];
    }, $barrios_all)) ?>;
    
    // Mapa de tarifas
    const tarifasMap = {};
    <?php
    $tarifas_all = $wpdb->get_results("SELECT origen_sector_id, destino_sector_id, precio FROM tarifas");
    foreach ($tarifas_all as $t) {
        if (!isset($tarifasMap[$t->origen_sector_id])) {
            $tarifasMap[$t->origen_sector_id] = [];
        }
        $tarifasMap[$t->origen_sector_id][$t->destino_sector_id] = intval($t->precio);
    }
    ?>
    const tarifasData = <?= json_encode($tarifasMap) ?>;
    
    document.addEventListener('DOMContentLoaded', function () {
      if (window.jQuery && jQuery.fn.select2) {
        try { if (jQuery.fn.select2.amd) { jQuery.fn.select2.amd = undefined; } } catch(e) {}

        jQuery('.gofast-select').each(function(){
          jQuery(this).select2({
            placeholder: "Buscar dirección...",
            width: '100%',
            dropdownParent: jQuery(this).closest('.gofast-box')
          });
        });
        
        // Inicializar Select2 para nuevo destino
        jQuery('#nuevo-destino-select').select2({
            placeholder: '🔍 Buscar barrio...',
            width: '100%',
            dropdownParent: jQuery('body'),
            minimumResultsForSearch: 0,
        });
      }

      // Agregar nuevo destino
      const btnAgregar = document.getElementById('btn-agregar-destino-resumen');
      if (btnAgregar) {
          btnAgregar.addEventListener('click', function(){
              const select = document.getElementById('nuevo-destino-select');
              const destinoId = parseInt(select.value);
              
              if (!destinoId) {
                  alert('Selecciona un destino primero');
                  return;
              }
              
              // Verificar que no esté ya agregado
              if (obtenerDestinosActuales().includes(destinoId.toString())) {
                  alert('Este destino ya está en el resumen');
                  return;
              }
              
              // Agregar destino (recargar para calcular correctamente)
              agregarDestinoResumen(destinoId);
          });
      }
    });

    function eliminarDestinoResumen(index) {
        if (!confirm('¿Eliminar este destino del resumen?')) return;
        
        // Obtener el ID del destino a eliminar
        const item = document.querySelector('[data-destino-index="' + index + '"]');
        if (!item) return;
        
        const destinoId = item.getAttribute('data-destino-id');
        if (!destinoId) return;
        
        // Obtener destinos actuales
        const destinosActuales = obtenerDestinosActuales();
        
        // Eliminar el destino del array
        const destinosFiltrados = destinosActuales.filter(function(id) {
            return id !== destinoId.toString();
        });
        
        // Validar que haya al menos un destino
        if (destinosFiltrados.length === 0) {
            alert('Debes tener al menos un destino');
            return;
        }
        
        // Crear formulario para enviar destinos actualizados
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const inputOrigen = document.createElement('input');
        inputOrigen.type = 'hidden';
        inputOrigen.name = 'origen';
        inputOrigen.value = document.getElementById('input-origen').value;
        form.appendChild(inputOrigen);
        
        // Agregar destinos restantes
        destinosFiltrados.forEach(function(id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'destino[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }

    function agregarDestinoResumen(destinoId) {
        // Recargar página con nuevo destino agregado
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const inputOrigen = document.createElement('input');
        inputOrigen.type = 'hidden';
        inputOrigen.name = 'origen';
        inputOrigen.value = document.getElementById('input-origen').value;
        form.appendChild(inputOrigen);
        
        const destinosActuales = obtenerDestinosActuales();
        destinosActuales.push(destinoId.toString());
        
        destinosActuales.forEach(function(id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'destino[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }

    function obtenerDestinosActuales() {
        // Obtener destinos desde los items visibles en el resumen
        const items = document.querySelectorAll('[data-destino-id]');
        const ids = [];
        items.forEach(function(item) {
            const destinoId = item.getAttribute('data-destino-id');
            if (destinoId) {
                ids.push(destinoId);
            }
        });
        return ids;
    }

    function actualizarDestinosInputs() {
        const destinos = obtenerDestinosActuales();
        const container = document.getElementById('destinos-hidden-inputs');
        if (container) {
            container.innerHTML = '';
            destinos.forEach(function(id, index) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'destino[]';
                input.className = 'destino-input';
                input.value = id;
                input.setAttribute('data-index', index);
                container.appendChild(input);
            });
        }
    }

    function recalcularTotalJS() {
        const items = document.querySelectorAll('[data-destino-index]');
        let total = 0;
        
        items.forEach(function(item) {
            const itemTotal = parseFloat(item.getAttribute('data-total')) || 0;
            total += itemTotal;
        });
        
        // Actualizar total en pantalla
        const totalBox = document.getElementById('total-amount');
        if (totalBox) {
            totalBox.textContent = 'Total: $' + total.toLocaleString('es-CO');
        }
        
        // Validar que haya al menos un destino
        if (items.length === 0) {
            alert('Debes tener al menos un destino');
            window.location.href = '<?= esc_url(home_url('/cotizar')) ?>';
        }
    }
    </script>

    <?php
    return ob_get_clean();
}

add_shortcode("gofast_resultado", "gofast_resultado_cotizacion");


/***************************************************
 * GOFAST – MIS ESTADÍSTICAS (VISTA CONTABLE DEL CLIENTE)
 * Shortcode: [gofast_mis_estadisticas]  (también acepta [gofast_mis_domicilios])
 * URL: /mis-estadisticas
 *
 * Cliente: ve sus servicios (personales y de sus negocios)
 * con resúmenes por tarifa, negocio, destino, mes y estado.
 * Admin: puede ver la vista de cualquier cliente con ?cliente_id=ID
 *
 * Totales: cuentan servicios pendientes, asignados, en ruta y entregados (no los cancelados).
 * Descargas: CSV (detalle y resumen por tarifa) y estado de cuenta imprimible.
 ***************************************************/

if (!function_exists('gofast_md_estados_contables')) {

function gofast_md_estados_contables() {
    return ['pendiente', 'asignado', 'en_ruta', 'entregado'];
}

function gofast_md_estado_labels() {
    return [
        'pendiente' => 'Pendiente',
        'asignado'  => 'Asignado',
        'en_ruta'   => 'En ruta',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
    ];
}

function gofast_md_money($valor) {
    return '$' . number_format((float) $valor, 0, ',', '.');
}

function gofast_md_hoy($formato = 'Y-m-d') {
    return function_exists('gofast_current_time') ? gofast_current_time($formato) : date($formato);
}

function gofast_md_mes_label($ym) {
    $meses = ['01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
              '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'];
    $partes = explode('-', $ym);
    return ($meses[$partes[1] ?? ''] ?? $ym) . ' ' . ($partes[0] ?? '');
}

/**
 * Rango de fechas del periodo elegido, calculado en hora Colombia.
 * El servidor MySQL está en UTC, por eso nunca se usa NOW()/CURDATE().
 */
function gofast_md_rango($periodo, $desde, $hasta) {
    $hoy = new DateTime(gofast_md_hoy('Y-m-d'));

    switch ($periodo) {
        case 'hoy':
            $d = clone $hoy; $h = clone $hoy;
            break;
        case 'semana':
            $d = (clone $hoy)->modify('monday this week'); $h = clone $hoy;
            break;
        case 'mes_anterior':
            $d = (clone $hoy)->modify('first day of last month'); $h = (clone $hoy)->modify('last day of last month');
            break;
        case 'anio':
            $d = new DateTime($hoy->format('Y') . '-01-01'); $h = clone $hoy;
            break;
        case 'rango':
            $valido = '/^\d{4}-\d{2}-\d{2}$/';
            $d = preg_match($valido, $desde) ? new DateTime($desde) : (clone $hoy)->modify('first day of this month');
            $h = preg_match($valido, $hasta) ? new DateTime($hasta) : clone $hoy;
            if ($d > $h) { $tmp = $d; $d = $h; $h = $tmp; }
            $limite = (clone $d)->modify('+366 days');
            if ($h > $limite) { $h = $limite; }
            break;
        case 'mes':
        default:
            $periodo = 'mes';
            $d = (clone $hoy)->modify('first day of this month'); $h = clone $hoy;
            break;
    }

    return [$periodo, $d->format('Y-m-d'), $h->format('Y-m-d')];
}

/**
 * Periodo anterior equivalente: mismo tramo del año anterior si empieza el
 * 1 de enero, del mes anterior si empieza el día 1, o los mismos días justo antes.
 */
function gofast_md_rango_anterior($desde, $hasta) {
    $d = new DateTime($desde);
    $h = new DateTime($hasta);

    if ($d->format('m-d') === '01-01' && $d->format('Y') === $h->format('Y') && $h->format('m') !== '01') {
        $y = (int) $d->format('Y') - 1;
        $ph = new DateTime($y . '-' . $h->format('m') . '-01');
        $ph->setDate($y, (int) $h->format('m'), min((int) $h->format('d'), (int) $ph->format('t')));
        return [$y . '-01-01', $ph->format('Y-m-d')];
    }

    if ($d->format('d') === '01' && $d->format('Y-m') === $h->format('Y-m')) {
        $pd = (clone $d)->modify('first day of last month');
        $dia = min((int) $h->format('d'), (int) $pd->format('t'));
        $ph = (clone $pd)->setDate((int) $pd->format('Y'), (int) $pd->format('m'), $dia);
        return [$pd->format('Y-m-d'), $ph->format('Y-m-d')];
    }

    $dias = (int) $d->diff($h)->days;
    $ph = (clone $d)->modify('-1 day');
    $pd = (clone $ph)->modify('-' . $dias . ' days');
    return [$pd->format('Y-m-d'), $ph->format('Y-m-d')];
}

function gofast_md_delta($actual, $anterior) {
    if ($anterior <= 0) {
        return '<small style="color:#999;">Sin datos del periodo anterior</small>';
    }
    $pct = round(($actual - $anterior) * 100 / $anterior, 1);
    $color = $pct >= 0 ? '#155724' : '#721c24';
    return '<small style="color:' . $color . ';font-weight:600;">' . ($pct >= 0 ? '▲ ' : '▼ ') . abs($pct) . '% vs. periodo anterior</small>';
}

function gofast_md_fecha_corta($ymd) {
    return date('d/m/Y', strtotime($ymd));
}

/**
 * Botones de periodo rápido (enviar el formulario con ese periodo) y rango libre.
 * Deben ir dentro del <form>, después del campo oculto "periodo".
 */
function gofast_md_html_periodos($filtros) {
    $rapidos = ['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'mes_anterior' => 'Mes anterior', 'anio' => 'Este año'];
    ob_start();
    ?>
    <div class="gofast-md-chips">
        <span class="gofast-md-chips-lbl">Periodo</span>
        <?php foreach ($rapidos as $val => $label): ?>
            <button type="submit" name="periodo" value="<?= esc_attr($val) ?>" class="gofast-md-chip<?= $filtros['periodo'] === $val ? ' gofast-md-chip-on' : '' ?>"><?= esc_html($label) ?></button>
        <?php endforeach; ?>
        <span class="gofast-md-rango-libre<?= $filtros['periodo'] === 'rango' ? ' gofast-md-chip-on' : '' ?>">
            <input type="date" name="desde" value="<?= esc_attr($filtros['desde']) ?>" aria-label="Desde">
            <span>a</span>
            <input type="date" name="hasta" value="<?= esc_attr($filtros['hasta']) ?>" aria-label="Hasta">
            <button type="submit" name="periodo" value="rango" class="gofast-md-chip">Ver fechas</button>
        </span>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Determina de qué cliente se muestran los datos.
 * Devuelve ['user_id', 'nombre', 'es_admin'] o ['error' => html].
 */
function gofast_md_contexto() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['gofast_user_id'])) {
        return ['error' => "<div class='gofast-box'>Debes iniciar sesión para ver tus domicilios.</div>"];
    }

    $session_uid = (int) $_SESSION['gofast_user_id'];
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? 'cliente');

    if ($rol === 'mensajero') {
        return ['error' => "<div class='gofast-box'>⚠️ Esta sección es para clientes. Revisa tus servicios en Pedidos.</div>"];
    }

    $es_admin = ($rol === 'admin');
    $user_id = $es_admin ? (int) ($_GET['cliente_id'] ?? 0) : $session_uid;

    $cliente = null;
    if ($user_id > 0) {
        $cliente = $wpdb->get_row($wpdb->prepare(
            "SELECT id, nombre, telefono FROM usuarios_gofast WHERE id = %d",
            $user_id
        ));
    }

    return [
        'user_id'  => $cliente ? (int) $cliente->id : 0,
        'nombre'   => $cliente ? $cliente->nombre : '',
        'telefono' => $cliente ? $cliente->telefono : '',
        'es_admin' => $es_admin,
    ];
}

function gofast_md_filtros() {
    list($periodo, $desde, $hasta) = gofast_md_rango(
        sanitize_text_field($_GET['periodo'] ?? 'mes'),
        sanitize_text_field($_GET['desde'] ?? ''),
        sanitize_text_field($_GET['hasta'] ?? '')
    );
    $negocio = sanitize_text_field($_GET['negocio'] ?? 'todos');
    if ($negocio !== 'todos' && $negocio !== 'personal') {
        $negocio = (string) max(0, (int) $negocio);
    }
    $recargo = sanitize_key($_GET['recargo'] ?? '');
    $tipo = sanitize_key($_GET['tipo'] ?? '');
    // La franja horaria es solo para el admin
    $es_admin = strtolower($_SESSION['gofast_user_rol'] ?? '') === 'admin';
    return [
        'periodo'   => $periodo,
        'desde'     => $desde,
        'hasta'     => $hasta,
        'negocio'   => $negocio,
        'mensajero' => max(-1, (int) ($_GET['mensajero'] ?? 0)),
        'recargo'   => in_array($recargo, ['con', 'sin'], true) ? $recargo : '',
        'tipo'      => in_array($tipo, ['urbano', 'inter'], true) ? $tipo : '',
        'horas'     => $es_admin ? gofast_md_horas_get() : [],
    ];
}

/**
 * Franjas horarias elegidas en el filtro: lista de [desde, hasta) en minutos del día (0-1439),
 * hasta excluido; si hasta es menor o igual que desde, la franja cruza la medianoche.
 * Llega como horas=08:30-10:14,14:00-15:59 (minuto final incluido), como horas sueltas de los
 * enlaces anteriores (8-11, hora final excluida) o como franja=manana|tarde|noche.
 * Sin franjas = todo el día.
 */
function gofast_md_horas_get() {
    $franja = sanitize_key($_GET['franja'] ?? '');
    $texto = (string) ($_GET['horas'] ?? '');
    if ($texto === '' && isset(gofast_md_franjas()[$franja])) {
        list(, $desde, $hasta) = gofast_md_franjas()[$franja];
        $texto = $desde . '-' . $hasta;
    }
    return gofast_md_horas_parse($texto);
}

function gofast_md_horas_parse($texto) {
    $minuto = function ($t) {
        if (!preg_match('/^(\d{1,2})(?::(\d{2}))?$/', trim($t), $m)) return null;
        $h = (int) $m[1];
        $i = isset($m[2]) ? (int) $m[2] : 0;
        if ($h > 24 || $i > 59 || ($h === 24 && $i > 0)) return null;
        return ($h * 60 + $i) % 1440;
    };
    $tramos = [];
    foreach (array_slice(explode(',', $texto), 0, 8) as $parte) {
        $ab = explode('-', $parte);
        if (count($ab) !== 2) continue;
        $a = $minuto($ab[0]);
        $b = $minuto($ab[1]);
        if ($a === null || $b === null) continue;
        if (strpos($ab[1], ':') !== false) $b = ($b + 1) % 1440;
        if ($a === $b) return [];
        $tramos[$a . '-' . $b] = [$a, $b];
    }
    ksort($tramos, SORT_NATURAL);
    return array_values($tramos);
}

/** ¿La fecha (Y-m-d H:i:s) cae en alguna de las franjas? */
function gofast_md_horas_dentro($tramos, $fecha) {
    $m = (int) substr($fecha, 11, 2) * 60 + (int) substr($fecha, 14, 2);
    foreach ($tramos as $t) {
        if ($t[0] < $t[1] ? ($m >= $t[0] && $m < $t[1]) : ($m >= $t[0] || $m < $t[1])) return true;
    }
    return false;
}

function gofast_md_hhmm($minutos) {
    return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
}

/** Minuto final incluido de una franja (hasta - 1). */
function gofast_md_horas_fin($t) {
    return gofast_md_hhmm(($t[1] + 1439) % 1440);
}

/** horas=... para la URL (08:30-10:14,14:00-15:59). */
function gofast_md_horas_arg($tramos) {
    return implode(',', array_map(function ($t) { return gofast_md_hhmm($t[0]) . '-' . gofast_md_horas_fin($t); }, $tramos));
}

/** Texto legible: 08:30 a 10:14, 14:00 a 15:59. */
function gofast_md_horas_texto($tramos) {
    if (!$tramos) return 'Todo el día';
    return implode(', ', array_map(function ($t) { return gofast_md_hhmm($t[0]) . ' a ' . gofast_md_horas_fin($t); }, $tramos));
}

/**
 * Selector de franja horaria (admin): una o varias franjas "desde - hasta" con listas de hora y
 * minuto (24 h), atajos de mañana, tarde y noche. Las filas no se envían: el JS arma horas=...
 * en el campo oculto. Sin franjas se muestra una fila de todo el día (00:00 a 23:59).
 */
function gofast_md_html_horas($tramos) {
    $hm = function ($minutos, $etiqueta) {
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;
        $html = '<span class="gofast-md-horas-hm" aria-label="' . esc_attr($etiqueta) . '"><select data-md-h aria-label="' . esc_attr($etiqueta) . ' (hora)">';
        for ($i = 0; $i < 24; $i++) $html .= sprintf('<option value="%02d"%s>%02d</option>', $i, $i === $h ? ' selected' : '', $i);
        $html .= '</select>:<select data-md-m aria-label="' . esc_attr($etiqueta) . ' (minuto)">';
        for ($i = 0; $i < 60; $i++) $html .= sprintf('<option value="%02d"%s>%02d</option>', $i, $i === $m ? ' selected' : '', $i);
        return $html . '</select></span>';
    };
    $fila = function ($desde = 0, $hasta = 1439) use ($hm) {
        return '<div class="gofast-md-horas-fila" data-md-horas-fila>'
            . $hm($desde, 'Desde') . '<span>a</span>' . $hm($hasta, 'Hasta')
            . '<button type="button" data-md-horas-quitar title="Quitar esta franja" aria-label="Quitar esta franja">✕</button>'
            . '</div>';
    };
    ob_start();
    ?>
    <details class="gofast-md-horas" data-md-horas>
        <summary>🕐 <span data-md-horas-txt><?= esc_html(gofast_md_horas_texto($tramos)) ?></span></summary>
        <div class="gofast-md-horas-panel">
            <input type="hidden" name="horas" value="<?= esc_attr(gofast_md_horas_arg($tramos)) ?>">
            <div class="gofast-md-horas-atajos">
                <span>Atajos:</span>
                <?php foreach (gofast_md_franjas() as $fr): ?>
                    <button type="button" data-md-horas-atajo="<?= esc_attr(gofast_md_horas_arg([[$fr[1] * 60, $fr[2] * 60]])) ?>"><?= esc_html(preg_replace('/ \(.*\)$/', '', $fr[0])) ?></button>
                <?php endforeach; ?>
            </div>
            <div data-md-horas-filas>
                <?php
                if (!$tramos) echo $fila();
                foreach ($tramos as $t) echo $fila($t[0], ($t[1] + 1439) % 1440);
                ?>
            </div>
            <template data-md-horas-molde><?= $fila() ?></template>
            <div class="gofast-md-horas-acciones">
                <button type="button" data-md-horas-agregar>+ Agregar franja</button>
                <button type="button" data-md-horas-limpiar>Todo el día</button>
                <button type="submit" class="gofast-btn-mini">Aplicar</button>
            </div>
            <small>Incluye la hora inicial y la final (08:00 a 09:59). Si la final es menor que la inicial, la franja cruza la medianoche (22:00 a 01:59). 00:00 a 23:59 es todo el día.</small>
        </div>
    </details>
    <?php
    return ob_get_clean();
}

/**
 * Franjas horarias: [etiqueta, hora inicial, hora final exclusiva]. La noche cruza la medianoche.
 */
function gofast_md_franjas() {
    return [
        'manana' => ['🌅 Mañana (6:00 a 11:59)', 6, 12],
        'tarde'  => ['☀️ Tarde (12:00 a 17:59)', 12, 18],
        'noche'  => ['🌙 Noche (18:00 a 5:59)', 18, 6],
    ];
}

/**
 * Textos de los filtros de mensajero y recargo activos (para avisos y PDF).
 */
function gofast_md_filtros_texto($filtros) {
    $textos = [];
    $mensajero = $filtros['mensajero'] ?? 0;
    if ($mensajero === -1) {
        $textos[] = '🏍️ Sin mensajero asignado';
    } elseif ($mensajero > 0) {
        $textos[] = '🏍️ ' . (gofast_md_nombres_usuarios([$mensajero])[$mensajero] ?? 'Mensajero #' . $mensajero);
    }
    if (($filtros['recargo'] ?? '') !== '') {
        $textos[] = $filtros['recargo'] === 'con' ? '➕ Solo con recargo' : '➖ Solo sin recargo';
    }
    if (($filtros['tipo'] ?? '') !== '') {
        $textos[] = $filtros['tipo'] === 'inter' ? '🌐 Solo intermunicipales' : '🏙️ Solo urbanos';
    }
    if (!empty($filtros['horas'])) {
        $textos[] = '🕐 ' . gofast_md_horas_texto($filtros['horas']);
    }
    return $textos;
}

/**
 * ¿El servicio pasa los filtros de mensajero y recargo? (vista cliente y admin)
 */
function gofast_md_pasa_filtros_extra($sv, $filtros) {
    $mensajero = $filtros['mensajero'] ?? 0;
    if ($mensajero === -1 && $sv['mensajero_id'] > 0) return false;
    if ($mensajero > 0 && $sv['mensajero_id'] !== $mensajero) return false;
    $recargo = $filtros['recargo'] ?? '';
    if ($recargo !== '') {
        $con = array_sum(array_column($sv['lineas'], 'recargo')) > 0;
        if (($recargo === 'con') !== $con) return false;
    }
    $tipo = $filtros['tipo'] ?? '';
    if ($tipo !== '' && ($tipo === 'inter') !== $sv['inter']) return false;
    if (!empty($filtros['horas']) && !gofast_md_horas_dentro($filtros['horas'], $sv['fecha'])) return false;
    return true;
}

/**
 * Tarifas actuales sector → sector, usadas solo para repartir el total
 * de servicios con varios destinos. Sin sectores: carga toda la tabla.
 */
function gofast_md_tarifas($sectores_origen = null) {
    global $wpdb;

    if (is_array($sectores_origen)) {
        if (!$sectores_origen) return [];
        $ids = implode(',', array_map('intval', $sectores_origen));
        $filas = $wpdb->get_results("SELECT origen_sector_id, destino_sector_id, precio FROM tarifas WHERE origen_sector_id IN ($ids)");
    } else {
        $filas = $wpdb->get_results("SELECT origen_sector_id, destino_sector_id, precio FROM tarifas");
    }

    $tarifas = [];
    foreach ((array) $filas as $t) {
        $tarifas[(int) $t->origen_sector_id . '-' . (int) $t->destino_sector_id] = (int) $t->precio;
    }
    return $tarifas;
}

/**
 * Sectores de origen de los servicios con varios destinos (para gofast_md_tarifas).
 */
function gofast_md_sectores_multidestino($filas) {
    $sectores = [];
    foreach ((array) $filas as $s) {
        $json = json_decode($s->destinos, true);
        if (!is_array($json) || ($json['tipo_servicio'] ?? '') === 'intermunicipal') continue;
        if (count((array) ($json['destinos'] ?? [])) > 1 && !empty($json['origen']['sector_id'])) {
            $sectores[(int) $json['origen']['sector_id']] = true;
        }
    }
    return array_keys($sectores);
}

/**
 * Convierte una fila de servicios_gofast en un servicio con sus líneas por destino.
 * El total guardado es la fuente de verdad: tarifa = total - recargos.
 * Con varios destinos, si la tarifa cambió desde entonces, se reparte de a $500.
 */
function gofast_md_calcular($s, $tarifas) {
    $json = json_decode($s->destinos, true);
    if (!is_array($json)) {
        $json = ['origen' => [], 'destinos' => []];
    }
    $origen = is_array($json['origen'] ?? null) ? $json['origen'] : [];
    $destinos = is_array($json['destinos'] ?? null) ? array_values(array_filter($json['destinos'], 'is_array')) : [];
    $es_inter = ($json['tipo_servicio'] ?? '') === 'intermunicipal';
    $total = (int) $s->total;
    $estado = $s->tracking_estado ?: 'pendiente';

    if (!$destinos) {
        $destinos = [['barrio_nombre' => 'Sin destino']];
    }
    $n = count($destinos);

    // recargo_total = automáticos (fijos + por valor); el de peso/volumen va aparte
    $rec_auto = [];
    $rec_vol = [];
    foreach ($destinos as $d) {
        $rec_auto[] = (int) ($d['recargo_total'] ?? 0);
        $rec_vol[] = (int) ($d['recargo_seleccionable_valor'] ?? 0);
    }
    $base_total = $total - array_sum($rec_auto) - array_sum($rec_vol);
    if ($es_inter || $base_total < 0) {
        $rec_auto = array_fill(0, $n, 0);
        $rec_vol = array_fill(0, $n, 0);
        $base_total = $total;
    }

    $bases = [];
    $aprox = false;
    if ($n === 1) {
        $bases = [$base_total];
    } else {
        $actuales = [];
        foreach ($destinos as $d) {
            $actuales[] = $tarifas[(int) ($origen['sector_id'] ?? 0) . '-' . (int) ($d['sector_id'] ?? 0)] ?? 0;
        }
        $suma = array_sum($actuales);
        if ($suma === $base_total) {
            $bases = $actuales;
        } elseif ($suma > 0 && $base_total > 0 && ($base_total - $suma) % 500 === 0) {
            $aprox = true;
            $bases = $actuales;
            $paso = $base_total > $suma ? 500 : -500;
            $falta = $base_total - $suma;
            $orden = array_keys($actuales);
            usort($orden, function ($a, $b) use ($actuales) { return $actuales[$b] <=> $actuales[$a]; });
            for ($i = 0; $falta !== 0 && $i < 1000; $i++) {
                $j = $orden[$i % $n];
                if ($bases[$j] + $paso > 0) {
                    $bases[$j] += $paso;
                    $falta -= $paso;
                }
            }
            $bases[$n - 1] += $falta;
        } else {
            $aprox = true;
            $acum = 0;
            foreach ($actuales as $i => $a) {
                if ($i === $n - 1) {
                    $bases[] = $base_total - $acum;
                } else {
                    $parte = $suma > 0 ? (int) (round($base_total * $a / $suma / 500) * 500) : (int) floor($base_total / $n);
                    $bases[] = $parte;
                    $acum += $parte;
                }
            }
        }
    }

    $lineas = [];
    foreach ($destinos as $i => $d) {
        $lineas[] = [
            'destino'        => trim($d['barrio_nombre'] ?? '') ?: 'Sin barrio',
            'direccion'      => trim($d['direccion'] ?? ''),
            'tarifa'         => (int) $bases[$i],
            'recargo_auto'   => $rec_auto[$i],
            'recargo_vol'    => $rec_vol[$i],
            'recargo'        => $rec_auto[$i] + $rec_vol[$i],
            'recargo_nombre' => $rec_vol[$i] > 0 ? (string) ($d['recargo_seleccionable_nombre'] ?? 'Peso / volumen') : '',
            'valor'          => (int) $bases[$i] + $rec_auto[$i] + $rec_vol[$i],
        ];
    }

    return [
        'id'           => (int) $s->id,
        'fecha'        => $s->fecha,
        'user_id'      => (int) ($s->user_id ?? 0),
        'nombre_cliente' => (string) ($s->nombre_cliente ?? ''),
        'mensajero_id' => (int) ($s->mensajero_id ?? 0),
        'negocio_id'   => (int) ($origen['negocio_id'] ?? 0),
        'negocio'      => '',
        'origen'       => trim($origen['barrio_nombre'] ?? '') ?: 'Sin barrio',
        'origen_dir'   => trim($origen['direccion'] ?? '') ?: trim((string) ($s->direccion_origen ?? '')),
        'destinos'     => array_column($lineas, 'destino'),
        'lineas'       => $lineas,
        'total'        => $total,
        'estado'       => $estado,
        'cuenta'       => in_array($estado, gofast_md_estados_contables(), true),
        'inter'        => $es_inter,
        'aprox'        => $aprox,
    ];
}

function gofast_md_resultado_vacio() {
    return [
        'servicios'    => [],
        'envios'       => [],
        'kpi'          => ['servicios' => 0, 'envios' => 0, 'total' => 0, 'tarifas' => 0, 'recargos' => 0, 'recargos_auto' => 0, 'excluidos' => 0, 'aproximados' => 0],
        'recargos_vol' => [],
        'por_recargo'  => [],
        'por_tarifa'   => [],
        'por_negocio'  => [],
        'por_destino'  => [],
        'por_trayecto' => [],
        'por_mes'      => [],
        'por_dia'      => [],
        'por_estado'   => [],
    ];
}

/**
 * Suma un servicio calculado a los resúmenes. $guardar_detalle guarda el
 * servicio y sus envíos (vista cliente); el admin solo acumula para no gastar memoria.
 */
function gofast_md_sumar(&$res, $sv, $guardar_detalle = true) {
    $cuenta = $sv['cuenta'];
    $n = count($sv['lineas']);

    foreach ($sv['lineas'] as $l) {
        if ($guardar_detalle) {
            $res['envios'][] = [
                'servicio_id' => $sv['id'],
                'fecha'       => $sv['fecha'],
                'negocio'     => $sv['negocio'],
                'origen'      => $sv['origen'],
                'destino'     => $l['destino'],
                'tarifa'      => $l['tarifa'],
                'recargo'     => $l['recargo'],
                'valor'       => $l['valor'],
                'estado'      => $sv['estado'],
                'cuenta'      => $cuenta,
                'aprox'       => $sv['aprox'],
            ];
        }
        if (!$cuenta) continue;

        $k = $sv['inter'] ? 'inter-' . $l['tarifa'] : (string) $l['tarifa'];
        if (!isset($res['por_tarifa'][$k])) {
            $res['por_tarifa'][$k] = ['tarifa' => $l['tarifa'], 'inter' => $sv['inter'], 'envios' => 0, 'subtotal' => 0, 'recargos' => 0, 'aprox' => 0];
        }
        $res['por_tarifa'][$k]['envios']++;
        $res['por_tarifa'][$k]['subtotal'] += $l['tarifa'];
        $res['por_tarifa'][$k]['recargos'] += $l['recargo'];
        if ($sv['aprox']) $res['por_tarifa'][$k]['aprox']++;

        $dk = ($sv['inter'] ? '🌐 ' : '') . $l['destino'];
        if (!isset($res['por_destino'][$dk])) $res['por_destino'][$dk] = ['envios' => 0, 'valor' => 0];
        $res['por_destino'][$dk]['envios']++;
        $res['por_destino'][$dk]['valor'] += $l['valor'];

        $tk = $sv['origen'] . ' → ' . $l['destino'];
        if (!isset($res['por_trayecto'][$tk])) $res['por_trayecto'][$tk] = ['envios' => 0, 'valor' => 0, 'tarifas' => []];
        $res['por_trayecto'][$tk]['envios']++;
        $res['por_trayecto'][$tk]['valor'] += $l['valor'];
        $res['por_trayecto'][$tk]['tarifas'][$l['tarifa']] = true;

        $res['kpi']['envios']++;
        $res['kpi']['tarifas'] += $l['tarifa'];
        $res['kpi']['recargos'] += $l['recargo'];
        $res['kpi']['recargos_auto'] += $l['recargo_auto'];
        if ($l['recargo_vol'] > 0) {
            $vk = $l['recargo_nombre'];
            if (!isset($res['recargos_vol'][$vk])) $res['recargos_vol'][$vk] = ['envios' => 0, 'valor' => 0];
            $res['recargos_vol'][$vk]['envios']++;
            $res['recargos_vol'][$vk]['valor'] += $l['recargo_vol'];
        }
        $partes = [['Automático (lluvia / por valor)', $l['recargo_auto']], [$l['recargo_nombre'], $l['recargo_vol']]];
        foreach ($partes as list($nombre, $valor)) {
            if ($valor <= 0) continue;
            $rk = $nombre . '|' . $valor;
            if (!isset($res['por_recargo'][$rk])) $res['por_recargo'][$rk] = ['nombre' => $nombre, 'valor' => $valor, 'envios' => 0, 'total' => 0];
            $res['por_recargo'][$rk]['envios']++;
            $res['por_recargo'][$rk]['total'] += $valor;
        }
    }

    if ($guardar_detalle) {
        $res['servicios'][] = $sv;
    }

    $e = $sv['estado'];
    if (!isset($res['por_estado'][$e])) $res['por_estado'][$e] = ['servicios' => 0, 'envios' => 0, 'valor' => 0];
    $res['por_estado'][$e]['servicios']++;
    $res['por_estado'][$e]['envios'] += $n;
    $res['por_estado'][$e]['valor'] += $sv['total'];

    if (!$cuenta) {
        $res['kpi']['excluidos']++;
        return;
    }

    $res['kpi']['servicios']++;
    $res['kpi']['total'] += $sv['total'];
    if ($sv['aprox']) $res['kpi']['aproximados']++;

    $nk = $sv['negocio'];
    if (!isset($res['por_negocio'][$nk])) $res['por_negocio'][$nk] = ['servicios' => 0, 'envios' => 0, 'valor' => 0];
    $res['por_negocio'][$nk]['servicios']++;
    $res['por_negocio'][$nk]['envios'] += $n;
    $res['por_negocio'][$nk]['valor'] += $sv['total'];

    $mes = substr($sv['fecha'], 0, 7);
    if (!isset($res['por_mes'][$mes])) $res['por_mes'][$mes] = ['servicios' => 0, 'envios' => 0, 'valor' => 0];
    $res['por_mes'][$mes]['servicios']++;
    $res['por_mes'][$mes]['envios'] += $n;
    $res['por_mes'][$mes]['valor'] += $sv['total'];

    $dia = substr($sv['fecha'], 0, 10);
    if (!isset($res['por_dia'][$dia])) $res['por_dia'][$dia] = ['servicios' => 0, 'valor' => 0];
    $res['por_dia'][$dia]['servicios']++;
    $res['por_dia'][$dia]['valor'] += $sv['total'];
}

function gofast_md_ordenar(&$res) {
    uasort($res['por_tarifa'], function ($a, $b) {
        return [$a['inter'], $a['tarifa']] <=> [$b['inter'], $b['tarifa']];
    });
    uasort($res['por_recargo'], function ($a, $b) {
        return [$a['nombre'], $a['valor']] <=> [$b['nombre'], $b['valor']];
    });
    $por_valor = function ($a, $b) { return $b['valor'] <=> $a['valor']; };
    $por_envios = function ($a, $b) { return [$b['envios'], $b['valor']] <=> [$a['envios'], $a['valor']]; };
    uasort($res['por_negocio'], $por_valor);
    uasort($res['por_destino'], $por_envios);
    uasort($res['por_trayecto'], $por_envios);
    ksort($res['por_mes']);
    ksort($res['por_dia']);
}

/**
 * Separa por_tarifa en urbanos e intermunicipales, con sus subtotales.
 */
function gofast_md_tarifas_por_tipo($por_tarifa) {
    $grupos = [];
    foreach (['urbano' => false, 'inter' => true] as $tipo => $es_inter) {
        $filas = array_filter($por_tarifa, function ($t) use ($es_inter) { return (bool) $t['inter'] === $es_inter; });
        $grupos[$tipo] = [
            'filas'    => $filas,
            'envios'   => array_sum(array_column($filas, 'envios')),
            'subtotal' => array_sum(array_column($filas, 'subtotal')),
            'recargos' => array_sum(array_column($filas, 'recargos')),
        ];
    }
    return $grupos;
}

/**
 * Datos de la vista cliente: una consulta a servicios_gofast (índice idx_user_fecha).
 */
function gofast_md_datos($user_id, $filtros) {
    global $wpdb;

    $negocios = $wpdb->get_results($wpdb->prepare(
        "SELECT id, nombre, activo, nit FROM negocios_gofast WHERE user_id = %d ORDER BY nombre ASC",
        $user_id
    ));
    $negocios_map = [];
    $negocios_nit = [];
    foreach ((array) $negocios as $n) {
        $negocios_map[(int) $n->id] = $n->nombre;
        $negocios_nit[(int) $n->id] = (string) $n->nit;
    }

    $filas = $wpdb->get_results($wpdb->prepare(
        "SELECT id, fecha, user_id, nombre_cliente, direccion_origen, destinos, total, tracking_estado, mensajero_id
         FROM servicios_gofast
         WHERE user_id = %d AND fecha >= %s AND fecha <= %s
         ORDER BY fecha DESC, id DESC",
        $user_id,
        $filtros['desde'] . ' 00:00:00',
        $filtros['hasta'] . ' 23:59:59'
    ));

    $tarifas = gofast_md_tarifas(gofast_md_sectores_multidestino($filas));
    $res = gofast_md_resultado_vacio();
    $res['negocios'] = $negocios;
    $res['negocios_map'] = $negocios_map;
    $res['negocios_nit'] = $negocios_nit;
    $res['conteo_negocios'] = ['todos' => 0, 'personal' => 0];
    $res['mensajeros_periodo'] = [];

    foreach ((array) $filas as $s) {
        $sv = gofast_md_calcular($s, $tarifas);

        if ($sv['cuenta']) {
            $ck = $sv['negocio_id'] > 0 ? (string) $sv['negocio_id'] : 'personal';
            $res['conteo_negocios']['todos']++;
            $res['conteo_negocios'][$ck] = ($res['conteo_negocios'][$ck] ?? 0) + 1;
        }

        if ($filtros['negocio'] === 'personal' && $sv['negocio_id'] > 0) continue;
        if ($filtros['negocio'] !== 'todos' && $filtros['negocio'] !== 'personal' && $sv['negocio_id'] !== (int) $filtros['negocio']) continue;

        if ($sv['mensajero_id'] > 0) $res['mensajeros_periodo'][$sv['mensajero_id']] = true;
        if (!gofast_md_pasa_filtros_extra($sv, $filtros)) continue;

        $sv['negocio'] = $sv['negocio_id'] > 0
            ? ($negocios_map[$sv['negocio_id']] ?? ('Negocio #' . $sv['negocio_id']))
            : 'Personal / sin negocio';
        $sv['solicitado'] = $sv['negocio_id'] > 0 ? '🏪 ' . $sv['negocio'] : '👤 Pedido personal';

        gofast_md_sumar($res, $sv, true);
    }

    gofast_md_ordenar($res);
    $res['por_mensajero'] = gofast_md_por_mensajero($res['servicios']);
    return $res;
}

/** Resumen por mensajero de los servicios que cuentan (vista cliente): nombre => servicios, envíos y valor. */
function gofast_md_por_mensajero($servicios) {
    $por_id = [];
    foreach ($servicios as $sv) {
        if (!$sv['cuenta']) continue;
        $mid = $sv['mensajero_id'] > 0 ? $sv['mensajero_id'] : 0;
        if (!isset($por_id[$mid])) $por_id[$mid] = ['servicios' => 0, 'envios' => 0, 'valor' => 0];
        $por_id[$mid]['servicios']++;
        $por_id[$mid]['envios'] += count($sv['lineas']);
        $por_id[$mid]['valor'] += $sv['total'];
    }
    $nombres = gofast_md_nombres_usuarios(array_keys($por_id));
    $res = [];
    foreach ($por_id as $mid => $g) {
        $nombre = $mid ? ($nombres[$mid] ?? 'Mensajero #' . $mid) : 'Sin asignar';
        if (isset($res[$nombre])) $nombre .= ' #' . $mid;
        $res[$nombre] = $g;
    }
    uasort($res, function ($a, $b) { return $b['valor'] <=> $a['valor']; });
    return $res;
}

/**
 * Las páginas de domicilios muestran datos de la sesión GoFast (no de WordPress):
 * para los plugins de caché el visitante es anónimo, así que se marcan sin caché
 * para no servir resultados viejos ni los de otro cliente.
 */
function gofast_md_sin_cache() {
    if (is_admin()) return;
    $post = get_post();
    $contenido = $post ? (string) $post->post_content : '';
    $propios = ['gofast_mis_estadisticas', 'gofast_admin_estadisticas', 'gofast_mis_domicilios', 'gofast_admin_domicilios'];
    $es_propia = false;
    foreach ($propios as $sc) {
        if (has_shortcode($contenido, $sc)) { $es_propia = true; break; }
    }
    if (!$es_propia) return;

    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    do_action('litespeed_control_set_nocache', 'GoFast domicilios: datos privados');
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
}
add_action('template_redirect', 'gofast_md_sin_cache', 1);

/**
 * Descargas (CSV y estado de cuenta). Se atienden en template_redirect
 * para enviar cabeceras antes de que el tema imprima HTML.
 */
function gofast_md_descargas() {
    if (empty($_GET['gofast_md_export'])) return;

    $tipo = sanitize_text_field($_GET['gofast_md_export']);
    if (!in_array($tipo, ['detalle', 'tarifas', 'imprimir'], true)) return;

    $ctx = gofast_md_contexto();
    if (!empty($ctx['error']) || empty($ctx['user_id'])) return;

    $filtros = gofast_md_filtros();
    $datos = gofast_md_datos($ctx['user_id'], $filtros);
    $labels = gofast_md_estado_labels();

    while (ob_get_level()) {
        ob_end_clean();
    }

    if ($tipo === 'imprimir') {
        gofast_md_estado_cuenta($ctx, $filtros, $datos, ($_GET['detalle'] ?? '1') !== '0');
        exit;
    }

    $archivo = 'gofast-estadisticas-' . $tipo . '-' . $filtros['desde'] . '_' . $filtros['hasta'] . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $archivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    if ($tipo === 'tarifas') {
        fputcsv($out, ['Tarifa', 'Tipo', 'Envíos', 'Subtotal tarifas', 'Recargos', 'Total'], ';');
        foreach ($datos['por_tarifa'] as $t) {
            fputcsv($out, [$t['tarifa'], $t['inter'] ? 'Intermunicipal' : 'Urbano', $t['envios'], $t['subtotal'], $t['recargos'], $t['subtotal'] + $t['recargos']], ';');
        }
        fputcsv($out, ['TOTAL', '', $datos['kpi']['envios'], $datos['kpi']['tarifas'], $datos['kpi']['recargos'], $datos['kpi']['tarifas'] + $datos['kpi']['recargos']], ';');
    } else {
        fputcsv($out, ['Servicio', 'Fecha', 'Negocio', 'Origen', 'Destino', 'Tarifa', 'Recargo', 'Valor', 'Estado', 'Suma al total', 'Valor estimado'], ';');
        foreach ($datos['envios'] as $e) {
            fputcsv($out, [
                $e['servicio_id'], $e['fecha'], $e['negocio'], $e['origen'], $e['destino'],
                $e['tarifa'], $e['recargo'], $e['valor'],
                $labels[$e['estado']] ?? $e['estado'],
                $e['cuenta'] ? 'Sí' : 'No',
                $e['aprox'] ? 'Sí' : 'No',
            ], ';');
        }
    }

    fclose($out);
    exit;
}
add_action('template_redirect', 'gofast_md_descargas');

function gofast_md_estado_cuenta($ctx, $filtros, $datos, $con_detalle = true) {
    $kpi = $datos['kpi'];
    $con_nit = function ($id) use ($datos) {
        $nit = $datos['negocios_nit'][$id] ?? '';
        return ($datos['negocios_map'][$id] ?? '') . ($nit !== '' ? ' · NIT ' . $nit : '');
    };
    if ($filtros['negocio'] === 'personal') {
        $lineas_negocio = ['Pedidos personales'];
    } elseif ($filtros['negocio'] !== 'todos') {
        $lineas_negocio = [$con_nit((int) $filtros['negocio'])];
    } elseif ($datos['negocios_map']) {
        $lineas_negocio = array_map($con_nit, array_keys($datos['negocios_map']));
    } else {
        $lineas_negocio = [$ctx['telefono']];
    }
    $num = function ($v) { return number_format((int) $v, 0, ',', '.'); };
    $envios_con_recargo = array_sum(array_column($datos['por_recargo'], 'envios'));
    $con_negocio = count($datos['por_negocio']) > 1;
    $grupos = gofast_md_tarifas_por_tipo($datos['por_tarifa']);
    $hay_inter = !empty($grupos['inter']['filas']);
    $servicios_cobrados = array_filter($datos['servicios'], function ($s) { return $s['cuenta']; });
    $detalle_grupos = [
        'urbano' => array_filter($servicios_cobrados, function ($s) { return !$s['inter']; }),
        'inter'  => array_filter($servicios_cobrados, function ($s) { return $s['inter']; }),
    ];
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estado de cuenta – Go Fast</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; margin: 0; padding: 24px; font-size: 13px; }
        .no-print { background: #f8f9fa; border-bottom: 2px solid #F4C524; padding: 14px; text-align: center; margin: -24px -24px 20px; }
        .no-print button { background: #28a745; color: #fff; border: 0; padding: 10px 24px; border-radius: 6px; font-weight: 600; cursor: pointer; margin: 0 6px; }
        .no-print .gris { background: #6c757d; }
        .muted { color: #666; }
        .hoja { max-width: 860px; margin: 0 auto; }
        .hoja-cab { display: flex; justify-content: space-between; gap: 16px; border-bottom: 3px solid #F4C524; padding-bottom: 10px; margin-bottom: 14px; }
        .hoja-cab .logo { font-weight: 900; font-size: 22px; }
        .hoja-cab .logo span { background: #F4C524; padding: 0 6px; border-radius: 4px; }
        h2 { font-size: 14px; margin: 24px 0 8px; }
        table.t { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.t th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #888; padding: 8px 10px; border-bottom: 2px solid #eee; background: #fafafa; text-align: left; }
        table.t td { padding: 9px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        table.t .n { text-align: right; white-space: nowrap; }
        table.t tr.grupo td { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #555; background: #f6f6f6; padding: 6px 10px; }
        table.t tr.sub td { font-weight: 700; border-top: 1px solid #ddd; }
        table.t tr.total td { font-weight: 800; background: #fff9d6; }
        table.det td { font-size: 12px; padding: 6px 8px; }
        @page { size: A4; margin: 12mm; }
        @media print { .no-print { display: none; } body { padding: 0; } h2 { page-break-after: avoid; } tr { page-break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">📥 Guardar como PDF / Imprimir</button>
        <button class="gris" onclick="window.close()">✕ Cerrar</button>
    </div>

    <div class="hoja">
        <div class="hoja-cab">
            <div>
                <div class="logo">GO <span>FAST</span></div>
                <div class="muted">Estado de cuenta de servicios<?= $con_detalle ? ' · con detalle' : '' ?></div>
            </div>
            <div style="text-align:right;">
                <b><?= esc_html($ctx['nombre']) ?></b><br>
                <?php foreach ($lineas_negocio as $linea): ?>
                    <span class="muted"><?= esc_html($linea) ?></span><br>
                <?php endforeach; ?>
                <span class="muted"><?= esc_html(gofast_md_fecha_corta($filtros['desde'])) ?> – <?= esc_html(gofast_md_fecha_corta($filtros['hasta'])) ?></span>
                <?php foreach (gofast_md_filtros_texto($filtros) as $texto): ?>
                    <br><span class="muted"><?= esc_html($texto) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <table class="t">
            <thead><tr><th>Concepto</th><th class="n">Cantidad</th><th class="n">Valor</th></tr></thead>
            <tbody>
            <?php foreach ($grupos as $tipo => $g): if (!$g['filas']) continue; ?>
                <tr class="grupo"><td colspan="3"><?= $tipo === 'inter' ? 'Envíos intermunicipales' : ($hay_inter ? 'Envíos urbanos por tarifa' : 'Envíos por tarifa') ?></td></tr>
                <?php foreach ($g['filas'] as $t): ?>
                <tr>
                    <td>Envíos <?= $t['inter'] ? 'intermunicipales ' : '' ?>de <?= gofast_md_money($t['tarifa']) ?></td>
                    <td class="n"><?= $num($t['envios']) ?></td>
                    <td class="n"><?= gofast_md_money($t['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="sub"><td><?= $tipo === 'inter' ? 'Subtotal intermunicipales' : ($hay_inter ? 'Subtotal urbanos' : 'Subtotal envíos') ?></td><td class="n"><?= $num($g['envios']) ?></td><td class="n"><?= gofast_md_money($g['subtotal']) ?></td></tr>
            <?php endforeach; ?>

            <?php if ($kpi['recargos'] > 0): ?>
                <tr class="grupo"><td colspan="3">Recargos</td></tr>
                <?php foreach ($datos['por_recargo'] as $r): ?>
                    <tr>
                        <td><?= esc_html($r['nombre']) ?> de <?= gofast_md_money($r['valor']) ?></td>
                        <td class="n"><?= $num($r['envios']) ?></td>
                        <td class="n"><?= gofast_md_money($r['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="sub"><td>Subtotal recargos</td><td class="n"><?= $num($envios_con_recargo) ?></td><td class="n"><?= gofast_md_money($kpi['recargos']) ?></td></tr>
            <?php endif; ?>

            <?php if ($kpi['excluidos'] > 0): ?>
                <tr><td>Cancelados (no se cobran)</td><td class="n"><?= $num($kpi['excluidos']) ?></td><td class="n">$0</td></tr>
            <?php endif; ?>
                <tr class="total"><td>Total del periodo</td><td class="n"><?= $num($kpi['envios']) ?> envíos</td><td class="n"><?= gofast_md_money($kpi['total']) ?></td></tr>
            </tbody>
        </table>
        <p class="muted" style="margin-top:10px;">
            Promedio por envío: <?= gofast_md_money($kpi['envios'] ? round($kpi['total'] / $kpi['envios']) : 0) ?>
            · <?= $num($kpi['servicios']) ?> servicios
            · Generado: <?= esc_html(gofast_md_hoy('d/m/Y H:i')) ?>
        </p>

        <?php if ($con_detalle): ?>
            <?php if ($con_negocio): ?>
                <h2>Por negocio</h2>
                <table class="t">
                    <thead><tr><th>Negocio</th><th class="n">Servicios</th><th class="n">Envíos</th><th class="n">Valor</th></tr></thead>
                    <tbody>
                    <?php foreach ($datos['por_negocio'] as $nombre => $g): ?>
                        <tr><td><?= esc_html($nombre) ?></td><td class="n"><?= $num($g['servicios']) ?></td><td class="n"><?= $num($g['envios']) ?></td><td class="n"><?= gofast_md_money($g['valor']) ?></td></tr>
                    <?php endforeach; ?>
                        <tr class="total"><td>Total</td><td class="n"><?= $num($kpi['servicios']) ?></td><td class="n"><?= $num($kpi['envios']) ?></td><td class="n"><?= gofast_md_money($kpi['total']) ?></td></tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php foreach ($detalle_grupos as $tipo => $lista): if (!$lista) continue;
                $tot = ['envios' => 0, 'tarifas' => 0, 'recargos' => 0, 'total' => 0]; ?>
            <h2><?= $tipo === 'inter' ? 'Detalle de servicios intermunicipales' : ($hay_inter ? 'Detalle de servicios urbanos' : 'Detalle de servicios') ?></h2>
            <table class="t det">
                <thead>
                    <tr>
                        <th>#</th><th>Fecha</th><?php if ($con_negocio): ?><th>Negocio</th><?php endif; ?><th>Origen → Destinos</th>
                        <th class="n">Envíos</th><th class="n">Tarifas</th><th class="n">Recargos</th><th class="n">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lista as $s):
                    $tar_s = array_sum(array_column($s['lineas'], 'tarifa'));
                    $rec_s = array_sum(array_column($s['lineas'], 'recargo'));
                    $tot['envios'] += count($s['lineas']);
                    $tot['tarifas'] += $tar_s;
                    $tot['recargos'] += $rec_s;
                    $tot['total'] += $s['total']; ?>
                    <tr>
                        <td><?= (int) $s['id'] ?></td>
                        <td style="white-space:nowrap;"><?= esc_html(date('d/m/Y H:i', strtotime($s['fecha']))) ?></td>
                        <?php if ($con_negocio): ?><td><?= esc_html($s['negocio']) ?></td><?php endif; ?>
                        <td><?= esc_html($s['origen']) ?> → <?= esc_html(implode(', ', $s['destinos'])) ?></td>
                        <td class="n"><?= count($s['lineas']) ?></td>
                        <td class="n"><?= gofast_md_money($tar_s) ?></td>
                        <td class="n"><?= $rec_s ? gofast_md_money($rec_s) : '—' ?></td>
                        <td class="n"><b><?= gofast_md_money($s['total']) ?></b></td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="total">
                        <td colspan="<?= $con_negocio ? 4 : 3 ?>"><?= $tipo === 'inter' ? 'Total intermunicipales' : ($hay_inter ? 'Total urbanos' : 'Total') ?></td>
                        <td class="n"><?= $num($tot['envios']) ?></td>
                        <td class="n"><?= gofast_md_money($tot['tarifas']) ?></td>
                        <td class="n"><?= gofast_md_money($tot['recargos']) ?></td>
                        <td class="n"><?= gofast_md_money($tot['total']) ?></td>
                    </tr>
                </tbody>
            </table>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html><?php
}

/**
 * Tabla de resumen genérica: filas [label => ['envios', 'valor', ...]]
 */
/**
 * Barra de filtro propia de un tab: buscador y chips. Filtra en el navegador las filas
 * del tab; si el tab tiene secciones (data-md-seccion), los chips muestran u ocultan secciones.
 * Con $lista se muestra una lista desplegable que deja solo la fila con ese nombre (data-md-nombre).
 */
function gofast_md_tabfiltro($chips = [], $placeholder = '🔍 Buscar…', $lista = [], $lista_todos = 'Todos') {
    ob_start();
    ?>
    <div class="gofast-md-tabfiltro" data-md-tabfiltro>
        <?php if ($lista): ?>
            <select data-md-lista class="gofast-md-select2" style="width:260px;" aria-label="<?= esc_attr($lista_todos) ?>">
                <option value=""><?= esc_html($lista_todos) ?></option>
                <?php foreach ($lista as $nombre): ?>
                    <option value="<?= esc_attr($nombre) ?>"><?= esc_html($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php if ($placeholder !== ''): ?>
            <input type="search" data-md-buscar placeholder="<?= esc_attr($placeholder) ?>" aria-label="Buscar en este tab">
        <?php endif; ?>
        <?php if ($chips): ?>
            <span class="gofast-md-tabchips">
                <?php $primero = true;
                foreach ($chips as $valor => $label): ?>
                    <button type="button" data-md-grupo="<?= esc_attr($valor) ?>" class="<?= $primero ? 'on' : '' ?>"><?= esc_html($label) ?></button>
                <?php $primero = false;
                endforeach; ?>
            </span>
        <?php endif; ?>
        <small data-md-conteo></small>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Filtros propios del tab Detalle leídos de GET: estado, mensajero (-1 = sin asignar),
 * barrio de destino y texto (# de servicio, dirección, negocio).
 */
function gofast_md_det_filtros() {
    $estado = sanitize_key($_GET['det_estado'] ?? '');
    if (!isset(gofast_md_estado_labels()[$estado])) $estado = '';
    $mensajero = (int) ($_GET['det_mensajero'] ?? 0);
    return [
        'estado'    => $estado,
        'mensajero' => $mensajero >= -1 ? $mensajero : 0,
        'barrio'    => trim(sanitize_text_field(wp_unslash($_GET['det_barrio'] ?? ''))),
        'q'         => trim(sanitize_text_field(wp_unslash($_GET['det_q'] ?? ''))),
    ];
}

function gofast_md_det_args($det) {
    return array_filter([
        'det_estado'    => $det['estado'],
        'det_mensajero' => $det['mensajero'] ? (string) $det['mensajero'] : '',
        'det_barrio'    => $det['barrio'],
        'det_q'         => $det['q'],
    ], 'strlen');
}

/** Barrios de destino del periodo (sin el prefijo 🌐 de los intermunicipales), en orden alfabético. */
function gofast_md_barrios_destino($datos) {
    $barrios = [];
    foreach (array_keys($datos['por_destino'] ?? []) as $label) {
        $barrios[preg_replace('/^🌐 /u', '', (string) $label)] = true;
    }
    $barrios = array_keys($barrios);
    natcasesort($barrios);
    return array_values($barrios);
}

/** Filtra los servicios del tab Detalle (vista cliente) con gofast_md_det_filtros(). */
function gofast_md_filtrar_detalle($servicios, $det) {
    $estado = $det['estado'];
    $q = $det['q'];
    if ($estado === '' && $q === '' && !$det['mensajero'] && $det['barrio'] === '') return $servicios;
    $q_num = ltrim($q, '#');
    $q_low = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);
    return array_values(array_filter($servicios, function ($s) use ($det, $estado, $q, $q_num, $q_low) {
        if ($estado !== '' && $s['estado'] !== $estado) return false;
        if ($det['mensajero'] === -1 && $s['mensajero_id'] > 0) return false;
        if ($det['mensajero'] > 0 && $s['mensajero_id'] !== $det['mensajero']) return false;
        if ($det['barrio'] !== '' && !in_array($det['barrio'], $s['destinos'], true)) return false;
        if ($q === '') return true;
        if (ctype_digit($q_num) && (string) $s['id'] === $q_num) return true;
        $texto = $s['negocio'] . ' ' . $s['origen'] . ' ' . implode(' ', $s['destinos']);
        foreach ($s['lineas'] as $l) $texto .= ' ' . ($l['direccion'] ?? '');
        $texto = function_exists('mb_strtolower') ? mb_strtolower($texto) : strtolower($texto);
        return strpos($texto, $q_low) !== false;
    }));
}

/**
 * Formulario de filtro del tab Detalle, compartido por la vista cliente y la de admin.
 * $mensajeros: id => nombre; $barrios: lista de barrios de destino.
 */
function gofast_md_html_filtro_detalle($args, $url_base, $det, $mostrados, $total, $mensajeros = [], $barrios = []) {
    $partes = wp_parse_url($url_base);
    $accion = strtok($url_base, '?');
    $ocultos = [];
    if (!empty($partes['query'])) parse_str($partes['query'], $ocultos);
    $ocultos = array_merge($ocultos, $args);
    unset($ocultos['det_estado'], $ocultos['det_mensajero'], $ocultos['det_barrio'], $ocultos['det_q'], $ocultos['pg']);
    if ($det['barrio'] !== '' && !in_array($det['barrio'], $barrios, true)) $barrios[] = $det['barrio'];
    ob_start();
    ?>
    <form method="get" action="<?= esc_url($accion) ?>" class="gofast-md-tabfiltro">
        <?php foreach ($ocultos as $k => $v):
            if (is_array($v) || $v === '' || $v === null) continue; ?>
            <input type="hidden" name="<?= esc_attr($k) ?>" value="<?= esc_attr($v) ?>">
        <?php endforeach; ?>
        <select name="det_estado" aria-label="Estado" data-md-autoenvio>
            <option value="">Todos los estados</option>
            <?php foreach (gofast_md_estado_labels() as $k => $label): ?>
                <option value="<?= esc_attr($k) ?>"<?php selected($det['estado'], $k); ?>><?= esc_html($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="det_mensajero" aria-label="Mensajero" class="gofast-md-select2" style="width:200px;" data-md-autoenvio>
            <option value="">Todos los mensajeros</option>
            <option value="-1"<?php selected($det['mensajero'], -1); ?>>Sin asignar</option>
            <?php foreach ($mensajeros as $mid => $mnombre): ?>
                <option value="<?= (int) $mid ?>"<?php selected($det['mensajero'], (int) $mid); ?>><?= esc_html($mnombre) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="det_barrio" aria-label="Barrio de destino" class="gofast-md-select2" style="width:200px;" data-md-autoenvio>
            <option value="">Todos los barrios</option>
            <?php foreach ($barrios as $b): ?>
                <option value="<?= esc_attr($b) ?>"<?php selected($det['barrio'], $b); ?>><?= esc_html($b) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="search" name="det_q" value="<?= esc_attr($det['q']) ?>" placeholder="🔍 # de servicio o dirección…" aria-label="Buscar servicio">
        <button type="submit" class="gofast-btn-mini">Buscar</button>
        <?php if (gofast_md_det_args($det)): ?>
            <a href="<?= esc_url(add_query_arg($ocultos, $accion)) ?>" class="gofast-btn-mini gofast-btn-outline">Quitar</a>
            <small><?= number_format($mostrados, 0, ',', '.') ?> de <?= number_format($total, 0, ',', '.') ?> servicios</small>
        <?php endif; ?>
    </form>
    <?php
    return ob_get_clean();
}

function gofast_md_tabla_resumen($filas, $titulo_col, $total_valor, $col_servicios = false, $etiqueta_total = 'Total', $totales_extra = [], $filas_extra = []) {
    if (!$filas) {
        return "<p style='text-align:center;color:#666;padding:20px;'>No hay servicios en este periodo.</p>";
    }
    ob_start();
    ?>
    <div class="gofast-table-wrap">
        <table class="gofast-table">
            <thead>
                <tr>
                    <th><?= esc_html($titulo_col) ?></th>
                    <?php if ($col_servicios): ?><th style="text-align:right;">Servicios</th><?php endif; ?>
                    <th style="text-align:right;">Envíos</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:right;">Promedio</th>
                    <th style="min-width:120px;">% del total</th>
                </tr>
            </thead>
            <tbody>
            <?php $sum = ['servicios' => 0, 'envios' => 0, 'valor' => 0];
            foreach ($filas as $label => $g):
                $pct = $total_valor > 0 ? round($g['valor'] * 100 / $total_valor, 1) : 0;
                $base = $col_servicios ? $g['servicios'] : $g['envios'];
                $sum['servicios'] += $g['servicios'] ?? 0;
                $sum['envios'] += $g['envios'];
                $sum['valor'] += $g['valor']; ?>
                <tr data-valor="<?= (int) $g['valor'] ?>" data-md-nombre="<?= esc_attr($label) ?>">
                    <td><?= esc_html($label) ?></td>
                    <?php if ($col_servicios): ?><td style="text-align:right;"><?= number_format($g['servicios'], 0, ',', '.') ?></td><?php endif; ?>
                    <td style="text-align:right;"><?= number_format($g['envios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;font-weight:600;"><?= gofast_md_money($g['valor']) ?></td>
                    <td style="text-align:right;color:#666;"><?= gofast_md_money($base ? round($g['valor'] / $base) : 0) ?></td>
                    <td><div class="gofast-md-barra"><span style="width:<?= min(100, $pct) ?>%;"></span></div><small><?= $pct ?>%</small></td>
                </tr>
            <?php endforeach;
            foreach ($filas_extra as $label => $g):
                $pct = $total_valor > 0 ? round($g['valor'] * 100 / $total_valor, 1) : 0;
                $base_e = $col_servicios ? $g['servicios'] : $g['envios']; ?>
                <tr class="gofast-md-fila-extra" data-valor="<?= (int) $g['valor'] ?>" style="display:none;">
                    <td><?= esc_html($label) ?></td>
                    <?php if ($col_servicios): ?><td style="text-align:right;"><?= number_format($g['servicios'], 0, ',', '.') ?></td><?php endif; ?>
                    <td style="text-align:right;"><?= number_format($g['envios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;font-weight:600;"><?= gofast_md_money($g['valor']) ?></td>
                    <td style="text-align:right;color:#666;"><?= gofast_md_money($base_e ? round($g['valor'] / $base_e) : 0) ?></td>
                    <td><div class="gofast-md-barra"><span style="width:<?= min(100, $pct) ?>%;"></span></div><small><?= $pct ?>%</small></td>
                </tr>
            <?php endforeach;
            $base = $col_servicios ? $sum['servicios'] : $sum['envios']; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;background:#fff9d6;">
                    <td><?= esc_html($etiqueta_total) ?></td>
                    <?php if ($col_servicios): ?><td style="text-align:right;"><?= number_format($sum['servicios'], 0, ',', '.') ?></td><?php endif; ?>
                    <td style="text-align:right;"><?= number_format($sum['envios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;"><?= gofast_md_money($sum['valor']) ?></td>
                    <td style="text-align:right;"><?= gofast_md_money($base ? round($sum['valor'] / $base) : 0) ?></td>
                    <td></td>
                </tr>
                <?php foreach ($totales_extra as $t):
                    $pct_t = $total_valor > 0 ? round($t['valor'] * 100 / $total_valor, 1) : 0; ?>
                <tr style="font-weight:800;background:#ffefa3;">
                    <td><?= esc_html($t['label']) ?></td>
                    <?php if ($col_servicios): ?><td></td><?php endif; ?>
                    <td style="text-align:right;"><?= number_format($t['envios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;"><?= gofast_md_money($t['valor']) ?></td>
                    <td style="text-align:right;"><?= gofast_md_money($t['envios'] ? round($t['valor'] / $t['envios']) : 0) ?></td>
                    <td><small><?= $pct_t ?>%</small></td>
                </tr>
                <?php endforeach; ?>
            </tfoot>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Pestaña "Por destino": intermunicipales primero y los 10 barrios locales con más envíos.
 */
function gofast_md_html_destinos($datos) {
    $total_valor = $datos['kpi']['tarifas'] + $datos['kpi']['recargos'];
    $inter = [];
    $locales = [];
    foreach ($datos['por_destino'] as $label => $g) {
        if (strpos($label, '🌐 ') === 0) $inter[substr($label, strlen('🌐 '))] = $g;
        else $locales[$label] = $g;
    }
    if (!$inter && !$locales) {
        return "<p style='text-align:center;color:#666;padding:20px;'>No hay servicios en este periodo.</p>";
    }
    $top = array_slice($locales, 0, 10, true);
    $resto = array_slice($locales, 10, null, true);
    ob_start();
    echo gofast_md_tabfiltro(
        ($inter && $locales) ? ['' => 'Todos', 'urbano' => '🏙️ Locales', 'inter' => '🌐 Intermunicipales'] : [],
        '🔍 Buscar un barrio o municipio…'
    );
    if ($inter): ?>
        <div data-md-seccion="inter">
        <h4 style="margin:0 0 8px;">🌐 Destinos intermunicipales</h4>
        <?= gofast_md_tabla_resumen($inter, 'Municipio / destino', $total_valor, false, 'Total intermunicipales') ?>
        </div>
    <?php endif;
    if ($locales):
        $suma = function ($filas) {
            return ['envios' => array_sum(array_column($filas, 'envios')), 'valor' => array_sum(array_column($filas, 'valor'))];
        };
        $extra = [];
        if (count($locales) > 10) {
            $extra[] = ['label' => 'Total general · ' . number_format(count($locales), 0, ',', '.') . ' barrios'] + $suma($locales);
        }
        if ($inter) {
            $todo = $suma($locales);
            $i = $suma($inter);
            $extra[] = ['label' => 'Total general con intermunicipales', 'envios' => $todo['envios'] + $i['envios'], 'valor' => $todo['valor'] + $i['valor']];
        } ?>
        <div data-md-seccion="urbano">
        <h4 style="margin:<?= $inter ? '20px' : '0' ?> 0 8px;">🏙️ Los <?= count($top) ?> barrios locales con más envíos</h4>
        <?= gofast_md_tabla_resumen($top, 'Barrio destino', $total_valor, false, count($locales) > 10 ? 'Total de estos 10 barrios' : ($inter ? 'Total locales' : 'Total general'), $extra, $resto) ?>
        <?php if (count($locales) > 10): ?>
            <p class="gofast-md-nota">Se muestran los 10 barrios con más envíos de <?= number_format(count($locales), 0, ',', '.') ?> barrios a los que se envió en el periodo. Con el buscador encuentras cualquiera de ellos; el detalle por envío (Excel) los incluye todos.</p>
        <?php endif; ?>
        </div>
    <?php endif;
    return ob_get_clean();
}

/**
 * Estilos propios de las vistas de domicilios (cliente y admin).
 */
function gofast_md_css() {
    ob_start();
    ?>
<style>
.gofast-md-tabs { display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 2px solid #ddd; margin-bottom: 4px; }
.gofast-md-barra { background: var(--gofast-gray-300); border-radius: 999px; height: 8px; overflow: hidden; margin-bottom: 2px; }
.gofast-md-barra span { display: block; height: 100%; background: var(--gofast-yellow); border-radius: 999px; }
.gofast-md-excluido td { color: #999; }
.gofast-md-nota { font-size: 12px; color: #666; margin-top: 10px; }
.gofast-home .gofast-alert-info { color: #1a1a1a; margin-bottom: 20px; }
.gofast-md-kpi small { display: block; margin-top: 6px; font-size: 11px; }
.gofast-md-kpis { display: grid; gap: 16px; margin: 0 0 20px; }
.gofast-md-kpis .gofast-box { margin: 0; min-width: 0; }
.gofast-md-kpis-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
.gofast-md-kpis-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.gofast-md-kpis-5 .gofast-md-kpi { padding: 16px 10px !important; }
@media (max-width: 768px) {
    .gofast-home .gofast-md-kpis.gofast-md-kpis-5, .gofast-home .gofast-md-kpis.gofast-md-kpis-3 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 10px !important; }
    .gofast-md-kpis-5 > .gofast-box:first-child { grid-column: 1 / -1; }
    .gofast-home .gofast-md-kpis .gofast-box { padding: 12px 8px !important; }
    .gofast-home .gofast-md-kpis .gofast-box > div:first-child { font-size: 22px !important; margin-bottom: 2px !important; }
    .gofast-home .gofast-md-kpis .gofast-box > div:nth-child(2) { font-size: 20px !important; }
    .gofast-home .gofast-md-kpis .gofast-box > div:nth-child(3) { font-size: 12px !important; }
}
.gofast-md-chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
.gofast-md-chips-lbl { font-weight: 600; font-size: 13px; margin-right: 4px; }
.gofast-md-chips button.gofast-md-chip { background: #fff; color: #1a1a1a; border: 1px solid var(--gofast-gray-400); border-radius: 999px; padding: 7px 14px; font-size: 13px; font-weight: 600; line-height: 1.2; width: auto; min-height: 0; margin: 0; box-shadow: none; cursor: pointer; }
.gofast-md-chips button.gofast-md-chip:hover { border-color: #1a1a1a; }
.gofast-md-chips button.gofast-md-chip-on { background: var(--gofast-yellow); border-color: var(--gofast-yellow); }
.gofast-md-tabfiltro { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 12px; padding: 8px 10px; background: #f7f8fa; border: 1px solid #e3e6ea; border-radius: 8px; }
.gofast-md-tabfiltro input[type="search"], .gofast-md-tabfiltro select { flex: 0 1 260px; min-width: 160px; height: 34px; padding: 4px 10px; margin: 0; font-size: 13px; border: 1px solid var(--gofast-gray-400); border-radius: 6px; background: #fff; }
.gofast-md-tabfiltro select { flex-basis: 180px; }
.gofast-md-tabfiltro .select2-container { flex: 0 1 auto; max-width: 100%; }
.gofast-md-tabfiltro .select2-container .select2-selection--single { height: 34px; border-color: var(--gofast-gray-400); border-radius: 6px; }
.gofast-md-tabfiltro .select2-container .select2-selection__rendered { line-height: 32px; font-size: 13px; }
.gofast-md-tabfiltro .select2-container .select2-selection__arrow { height: 32px; }
.gofast-md-tabchips { display: inline-flex; flex-wrap: wrap; gap: 6px; }
.gofast-md-tabfiltro button[data-md-grupo] { background: #fff; color: #1a1a1a; border: 1px solid var(--gofast-gray-400); border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 600; line-height: 1.2; width: auto; min-height: 0; margin: 0; box-shadow: none; cursor: pointer; }
.gofast-md-tabfiltro button[data-md-grupo].on { background: var(--gofast-yellow); border-color: var(--gofast-yellow); }
.gofast-md-tabfiltro small { color: #555; font-size: 12px; }
.gofast-md-tabfiltro .gofast-btn-mini { width: auto; margin: 0; }
.gofast-home .gofast-md-tabfiltro a.gofast-btn-outline, .gofast-home .gofast-md-tabfiltro a.gofast-btn-outline:hover { color: #333; }
.gofast-md-horas { position: relative; }
.gofast-md-horas > summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: 6px; min-height: 42px; padding: 6px 28px 6px 10px; border: 1px solid var(--gofast-gray-400); border-radius: 6px; background: #fff; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; position: relative; }
.gofast-md-horas > summary::-webkit-details-marker { display: none; }
.gofast-md-horas > summary::after { content: '▾'; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #666; }
.gofast-md-horas[open] > summary { border-color: #1a1a1a; }
.gofast-md-horas-panel { position: absolute; z-index: 50; top: calc(100% + 4px); right: 0; width: 330px; max-width: 90vw; padding: 10px; background: #fff; border: 1px solid var(--gofast-gray-400); border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,.12); }
.gofast-md-horas-atajos { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 10px; font-size: 12px; color: #555; }
.gofast-md-horas-panel button[type="button"] { background: #fff; color: #1a1a1a; border: 1px solid var(--gofast-gray-400); border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 600; line-height: 1.2; width: auto; min-height: 0; margin: 0; box-shadow: none; cursor: pointer; }
.gofast-md-horas-panel button[type="button"]:hover { border-color: #1a1a1a; }
.gofast-md-horas-fila { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.gofast-md-horas-hm { display: inline-flex; align-items: center; gap: 2px; font-weight: 700; }
.gofast-md-horas-panel .gofast-md-horas-hm select { width: 58px; min-width: 0; height: 36px; padding: 2px 4px; margin: 0; font-size: 14px; border: 1px solid var(--gofast-gray-400); border-radius: 6px; background: #fff; }
.gofast-md-horas-fila > span:not(.gofast-md-horas-hm) { font-size: 13px; color: #555; }
.gofast-md-horas-panel .gofast-md-horas-fila button[data-md-horas-quitar] { border-radius: 6px; padding: 6px 9px; color: #c0392b; margin-left: auto; }
.gofast-md-horas-acciones { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 4px; }
.gofast-md-horas-acciones .gofast-btn-mini { width: auto; margin: 0 0 0 auto; }
.gofast-md-horas-panel small { display: block; margin-top: 8px; color: #555; font-size: 11px; line-height: 1.35; }
.gofast-md-rango-libre { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; padding: 3px 3px 3px 8px; border: 1px solid var(--gofast-gray-400); border-radius: 999px; background: #fff; font-size: 13px; }
.gofast-md-rango-libre.gofast-md-chip-on { border-color: var(--gofast-yellow); box-shadow: 0 0 0 2px var(--gofast-yellow); }
.gofast-md-rango-libre input[type="date"] { border: 0; padding: 4px; margin: 0; height: auto; font-size: 13px; width: auto; min-height: 0; background: transparent; box-shadow: none; }
.gofast-md-rango-libre button.gofast-md-chip { padding: 5px 12px; }
.gofast-md-negocios { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; margin-bottom: 14px; }
.gofast-md-negocio { display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid var(--gofast-gray-400); border-radius: var(--radius-m); background: #fff; color: #1a1a1a; text-decoration: none; }
.gofast-md-negocio:hover { border-color: #1a1a1a; }
.gofast-md-negocio-on { border: 2px solid var(--gofast-yellow); background: #fff9d6; }
.gofast-md-negocio .ico { font-size: 22px; }
.gofast-md-negocio b { display: block; font-size: 13px; }
.gofast-md-negocio small { color: #666; font-size: 12px; }
.gofast-md-negocio > span:last-child { min-width: 0; }
.gofast-md-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
.gofast-md-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.gofast-md-grid .gofast-box, .gofast-md-grid-2 .gofast-box { margin: 0; }
.gofast-md-chart { display: flex; align-items: flex-end; gap: 3px; height: 200px; border-bottom: 2px solid var(--gofast-gray-300); padding-top: 8px; }
.gofast-md-chart-col { flex: 1; min-width: 3px; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: stretch; cursor: pointer; }
.gofast-md-chart-bar { width: 100%; height: calc((100% - var(--reserva, 20px)) * var(--h, 0)); background: var(--gofast-yellow); border-radius: 4px 4px 0 0; min-height: 2px; transition: background .15s; }
.gofast-md-chart-max { background: #e0a800; }
.gofast-md-chart-val { display: block; text-align: center; font-size: 11px; font-weight: 700; color: #333; line-height: 1.1; margin-bottom: 3px; white-space: nowrap; }
.gofast-md-chart-varias .gofast-md-chart-val { font-size: 10px; }
.gofast-md-chart-muchas { --reserva: 46px; }
.gofast-md-chart-muchas .gofast-md-chart-val { writing-mode: vertical-rl; transform: rotate(180deg); margin: 0 auto 3px; font-size: 10px; font-weight: 600; }
.gofast-md-chart-col:hover .gofast-md-chart-bar, .gofast-md-chart-activa .gofast-md-chart-bar { background: #000; }
.gofast-md-chart-info { margin-top: 10px; padding: 8px 10px; background: #fff9d6; border-radius: 8px; font-size: 13px; }
.gofast-md-chart-info small { color: #666; }
@media (max-width: 600px) {
    .gofast-md-chart-varias { --reserva: 46px; }
    .gofast-md-chart-varias .gofast-md-chart-val { writing-mode: vertical-rl; transform: rotate(180deg); margin: 0 auto 3px; }
    .gofast-md-chart { gap: 2px; }
    .gofast-md-chart-muchas .gofast-md-chart-val { font-size: 9px; }
}
.gofast-md-chart-labels { display: flex; gap: 3px; margin-top: 4px; }
.gofast-md-chart-labels span { flex: 1; min-width: 3px; font-size: 10px; color: #666; text-align: center; white-space: nowrap; }
.gofast-md-stack { display: flex; height: 26px; border-radius: 6px; overflow: hidden; background: var(--gofast-gray-300); margin: 8px 0 14px; }
.gofast-md-stack span { display: block; height: 100%; }
.gofast-md-leyenda > div { display: grid; grid-template-columns: 14px 1fr auto; column-gap: 8px; align-items: center; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
.gofast-md-leyenda i { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }
.gofast-md-leyenda small { grid-column: 2 / 4; color: #666; font-size: 11px; }
.gofast-md-top { display: flex; gap: 10px; align-items: flex-start; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.gofast-md-top-n { width: 24px; height: 24px; border-radius: 50%; background: var(--gofast-yellow); color: #000; font-weight: 700; font-size: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.gofast-md-top-row { display: flex; justify-content: space-between; gap: 8px; font-size: 13px; margin-bottom: 4px; }
.gofast-md-top-nombre { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gofast-md-grid > *, .gofast-md-grid-2 > * { min-width: 0; }
.gofast-md-tarifas { margin-bottom: 8px; align-items: start; }
.gofast-md-tarifas-grafica { position: sticky; top: 20px; background: #fafafa; border: 1px solid var(--gofast-gray-400, #e5e5e5); border-radius: var(--radius-m, 10px); padding: 14px; }
.gofast-md-mini { margin-top: 14px; }
.gofast-md-mini > div { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-bottom: 1px solid #eee; font-size: 13px; }
.gofast-md-mini > div:last-child { border-bottom: 0; }
.gofast-md-mini span { color: #666; }
.gofast-md-mini b { text-align: right; }
.gofast-home .gofast-md-panel .gofast-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
@media (max-width: 900px) {
    .gofast-md-grid, .gofast-md-grid-2 { grid-template-columns: minmax(0, 1fr); }
}
.gofast-md-descargas { display: flex; gap: 8px; flex-wrap: wrap; }
.gofast-md-descargas a { text-decoration: none; }
.gofast-home .gofast-pedidos-filtros-row.gofast-md-filtros-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px 16px; align-items: end; margin-bottom: 14px; }
.gofast-home .gofast-md-filtros-grid > div { min-width: 0 !important; max-width: none !important; width: auto; }
.gofast-home .gofast-md-filtros-grid.gofast-md-filtros-cliente { grid-template-columns: minmax(0, 360px) auto; justify-content: start; }
.gofast-home a.gofast-btn-outline, .gofast-home a.gofast-btn-outline:hover { color: #333; }
.gofast-home .gofast-md-filtros-grid .gofast-pedidos-filtros-actions { display: flex; flex-direction: row !important; justify-content: flex-start !important; gap: 8px; }
.gofast-home .gofast-md-filtros-grid .select2-container { width: 100% !important; max-width: 100%; }
.gofast-home .gofast-md-filtros-grid .select2-selection__rendered { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gofast-md-cargando { position: fixed; inset: 0; z-index: 99999; display: none; align-items: center; justify-content: center; background: rgba(0, 0, 0, .55); }
.gofast-md-cargando.on { display: flex; }
.gofast-md-cargando div { background: #fff; color: #1a1a1a; border-radius: 12px; padding: 18px 26px; font-weight: 600; display: flex; gap: 12px; align-items: center; box-shadow: 0 8px 30px rgba(0,0,0,.3); }
.gofast-md-cargando i { width: 20px; height: 20px; border: 3px solid #eee; border-top-color: var(--gofast-yellow, #F4C524); border-radius: 50%; animation: gofast-md-giro .8s linear infinite; }
@keyframes gofast-md-giro { to { transform: rotate(360deg); } }
.gofast-md-select-cliente + .select2-container .select2-selection--single { height: 42px; display: flex; align-items: center; border: 1px solid var(--gofast-gray-400); border-radius: var(--radius-s); }
.gofast-md-select-cliente + .select2-container .select2-selection__arrow { height: 40px; }
.gofast-md-desktop { display: block; }
.gofast-md-mobile { display: none; }
.gofast-md-card { background: #fff; border: 1px solid var(--gofast-gray-400); border-left: 4px solid var(--gofast-gray-400); border-radius: var(--radius-m); padding: 14px; margin-bottom: 10px; cursor: pointer; }
.gofast-md-card-excluido { opacity: .6; }
.gofast-md-card-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.gofast-md-card-ruta { font-size: 14px; margin: 6px 0 8px; }
.gofast-md-kv { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; background: #f8f9fa; border-left: 4px solid var(--gofast-yellow); border-radius: var(--radius-s); padding: 12px; margin-bottom: 16px; font-size: 13px; }
.gofast-md-kv small { display: block; color: #666; font-weight: 600; }
@media (max-width: 768px) {
    .gofast-md-desktop { display: none; }
    .gofast-md-mobile { display: block; }
    .gofast-md-kv { grid-template-columns: repeat(2, 1fr); }
    .gofast-md-tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .gofast-md-tabs .gofast-config-tab { white-space: nowrap; padding: 10px 14px; font-size: 13px; }
    .gofast-md-descargas a { flex: 1 1 100%; text-align: center; }
    .gofast-home .gofast-md-panel .gofast-table th, .gofast-home .gofast-md-panel .gofast-table td { white-space: nowrap; }
    .gofast-home .gofast-pedidos-filtros-row > div { max-width: none !important; }
    .gofast-home .gofast-pedidos-filtros-row.gofast-md-filtros-grid, .gofast-home .gofast-md-filtros-grid.gofast-md-filtros-cliente { grid-template-columns: minmax(0, 1fr); }
    .gofast-md-actualizado { float: none !important; display: block; margin-top: 4px; }
    .gofast-md-chips { gap: 6px; }
    .gofast-md-chips-lbl { flex-basis: 100%; }
    .gofast-md-chips button.gofast-md-chip { white-space: nowrap; padding: 7px 12px; }
    .gofast-md-rango-libre { flex-basis: 100%; display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); gap: 4px 6px; padding: 6px 8px; border-radius: var(--radius-m); max-width: 420px; }
    .gofast-md-rango-libre input[type="date"] { width: 100%; min-width: 0; }
    .gofast-md-rango-libre button.gofast-md-chip { grid-column: 1 / -1; }
    .gofast-md-negocio small { white-space: nowrap; }
    .gofast-md-negocios { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .gofast-md-negocio { padding: 8px 10px; gap: 8px; min-width: 0; }
    .gofast-md-negocio b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
}
</style>
    <?php
    return ob_get_clean();
}

/**
 * Puntos de la gráfica de valores: por día hasta 62 días, si no por mes.
 * Devuelve ['dia' | 'mes', puntos].
 */
function gofast_md_puntos_grafica($res, $desde, $hasta) {
    $puntos = [];
    $d = new DateTime($desde);
    $h = new DateTime($hasta);

    if ((int) $d->diff($h)->days <= 62) {
        for ($x = clone $d; $x <= $h; $x->modify('+1 day')) {
            $k = $x->format('Y-m-d');
            $puntos[] = [
                'corto'     => $x->format('d'),
                'largo'     => $x->format('d/m/Y'),
                'valor'     => $res['por_dia'][$k]['valor'] ?? 0,
                'servicios' => $res['por_dia'][$k]['servicios'] ?? 0,
            ];
        }
        return ['dia', $puntos];
    }

    foreach ($res['por_mes'] as $ym => $g) {
        $puntos[] = [
            'corto'     => substr(gofast_md_mes_label($ym), 0, 3),
            'largo'     => gofast_md_mes_label($ym),
            'valor'     => $g['valor'],
            'servicios' => $g['servicios'],
        ];
    }
    return ['mes', $puntos];
}

function gofast_md_dinero_corto($v) {
    $v = (int) $v;
    if ($v >= 1000000) return '$' . rtrim(rtrim(number_format($v / 1000000, 1, ',', ''), '0'), ',') . 'M';
    if ($v >= 1000) return '$' . rtrim(rtrim(number_format($v / 1000, $v < 10000 ? 1 : 0, ',', ''), '0'), ',') . 'k';
    return '$' . $v;
}

function gofast_md_grafica($puntos) {
    if (!$puntos || !max(array_column($puntos, 'valor'))) {
        return "<p style='text-align:center;color:#666;padding:40px 0;'>No hay servicios en este periodo.</p>";
    }
    $max = max(array_column($puntos, 'valor'));
    $cada = max(1, (int) ceil(count($puntos) / 16));
    $clase = count($puntos) > 12 ? ' gofast-md-chart-muchas' : (count($puntos) > 6 ? ' gofast-md-chart-varias' : '');
    $info_max = '';
    ob_start();
    ?>
    <div class="gofast-md-chart-wrap">
    <div class="gofast-md-chart<?= $clase ?>">
        <?php foreach ($puntos as $pt):
            $alto = round($pt['valor'] / $max, 3);
            $info = $pt['titulo'] ?? ($pt['largo'] . ': ' . gofast_md_money($pt['valor']) . ' · ' . $pt['servicios'] . ' ' . ($pt['servicios'] === 1 ? 'servicio' : 'servicios'));
            $es_max = $pt['valor'] === $max;
            if ($es_max && $info_max === '') $info_max = $info;
            $etiqueta = $pt['valor'] > 0 ? ($pt['etiqueta'] ?? gofast_md_dinero_corto($pt['valor'])) : ''; ?>
            <div class="gofast-md-chart-col<?= $es_max ? ' gofast-md-chart-activa' : '' ?>" data-info="<?= esc_attr($info) ?>" title="<?= esc_attr($info) ?>">
                <span class="gofast-md-chart-val"><?= esc_html($etiqueta) ?></span>
                <div class="gofast-md-chart-bar<?= $es_max ? ' gofast-md-chart-max' : '' ?>" style="--h:<?= $alto ?>;"></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="gofast-md-chart-labels">
        <?php foreach ($puntos as $i => $pt): ?>
            <span><?= $i % $cada === 0 ? esc_html($pt['corto']) : '' ?></span>
        <?php endforeach; ?>
    </div>
    <div class="gofast-md-chart-info">📌 <span><?= esc_html($info_max) ?></span> <small>(toca una barra para ver su detalle)</small></div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Ranking corto con barra. $campo: 'servicios' o 'envios'.
 */
function gofast_md_top($filas, $total, $max = 5, $campo = 'servicios') {
    if (!$filas) {
        return "<p style='text-align:center;color:#666;padding:20px 0;'>Sin datos.</p>";
    }
    ob_start();
    $i = 0;
    foreach (array_slice($filas, 0, $max, true) as $label => $g):
        $i++;
        $pct = $total > 0 ? round($g['valor'] * 100 / $total, 1) : 0; ?>
        <div class="gofast-md-top">
            <span class="gofast-md-top-n"><?= $i ?></span>
            <div style="flex:1;min-width:0;">
                <div class="gofast-md-top-row">
                    <span class="gofast-md-top-nombre"><?= esc_html($label) ?></span>
                    <strong><?= gofast_md_money($g['valor']) ?></strong>
                </div>
                <div class="gofast-md-barra"><span style="width:<?= min(100, $pct) ?>%;"></span></div>
                <small style="color:#666;"><?= number_format($g[$campo], 0, ',', '.') ?> <?= $campo === 'envios' ? ($g[$campo] === 1 ? 'envío' : 'envíos') : ($g[$campo] === 1 ? 'servicio' : 'servicios') ?> · <?= $pct ?>%</small>
            </div>
        </div>
    <?php endforeach;
    return ob_get_clean();
}

/**
 * Barra apilada con leyenda: las 5 primeras filas y el resto como "Otros".
 */
function gofast_md_apilada($filas, $total) {
    if (!$filas || $total <= 0) {
        return "<p style='text-align:center;color:#666;padding:20px 0;'>Sin datos.</p>";
    }
    $colores = ['#F4C524', '#2196F3', '#FF7043', '#00897B', '#9C27B0', '#9e9e9e'];
    $partes = array_slice($filas, 0, 5, true);
    $resto = array_slice($filas, 5, null, true);
    if ($resto) {
        $partes['Otros'] = [
            'servicios' => array_sum(array_column($resto, 'servicios')),
            'valor'     => array_sum(array_column($resto, 'valor')),
        ];
    }
    ob_start();
    ?>
    <div class="gofast-md-stack">
        <?php $i = 0; foreach ($partes as $label => $g):
            $pct = round($g['valor'] * 100 / $total, 1); ?>
            <span style="width:<?= $pct ?>%;background:<?= $colores[$i++ % 6] ?>;" title="<?= esc_attr($label . ': ' . $pct . '%') ?>"></span>
        <?php endforeach; ?>
    </div>
    <div class="gofast-md-leyenda">
        <?php $i = 0; foreach ($partes as $label => $g):
            $pct = round($g['valor'] * 100 / $total, 1); ?>
            <div>
                <i style="background:<?= $colores[$i++ % 6] ?>;"></i>
                <span><?= esc_html($label) ?></span>
                <strong><?= $pct ?>%</strong>
                <small><?= number_format($g['servicios'], 0, ',', '.') ?> serv. · <?= gofast_md_money($g['valor']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Pestaña "Por tarifa" con el desglose de recargos.
 */
function gofast_md_html_tarifas($datos) {
    $kpi = $datos['kpi'];
    $labels = gofast_md_estado_labels();
    ob_start();
    ?>
<?php if (!$datos['por_tarifa']): ?>
    <p style="text-align:center;color:#666;padding:20px;">No hay servicios en este periodo.</p>
<?php else:
    $puntos_t = [];
    $mas_usada = null;
    foreach ($datos['por_tarifa'] as $t) {
        $puntos_t[] = [
            'corto'     => '$' . rtrim(rtrim(number_format($t['tarifa'] / 1000, 1, '.', ''), '0'), '.') . 'k' . ($t['inter'] ? '*' : ''),
            'valor'     => $t['envios'],
            'servicios' => $t['envios'],
            'etiqueta'  => number_format($t['envios'], 0, ',', '.'),
            'titulo'    => gofast_md_money($t['tarifa']) . ($t['inter'] ? ' (intermunicipal)' : '') . ': ' . $t['envios'] . ' envíos',
        ];
        if ($mas_usada === null || $t['envios'] > $mas_usada['envios']) $mas_usada = $t;
    }
    $grupos = gofast_md_tarifas_por_tipo($datos['por_tarifa']);
    $hay_inter = !empty($grupos['inter']['filas']);
    $hay_urbano = !empty($grupos['urbano']['filas']);
    $titulos = ['urbano' => '🏙️ Envíos urbanos', 'inter' => '🌐 Envíos intermunicipales'];
    if ($hay_inter && $hay_urbano) echo gofast_md_tabfiltro(['' => 'Todos', 'urbano' => '🏙️ Urbanos', 'inter' => '🌐 Intermunicipales'], '');
    ?>
    <div class="gofast-md-grid-2 gofast-md-tarifas">
        <div>
            <?php foreach ($grupos as $tipo => $g):
                if (!$g['filas']) continue;
                $es_ultimo = ($tipo === 'inter' || !$hay_inter);
                $pct_g = $kpi['envios'] ? round($g['envios'] * 100 / $kpi['envios'], 1) : 0; ?>
            <div data-md-seccion="<?= esc_attr($tipo) ?>">
            <?php if ($hay_inter): ?><h4 style="margin:<?= $tipo === 'inter' && $hay_urbano ? '18px' : '0' ?> 0 8px;"><?= $titulos[$tipo] ?></h4><?php endif; ?>
            <div class="gofast-table-wrap">
                <table class="gofast-table">
                    <thead>
                        <tr>
                            <th>Tarifa</th>
                            <th style="text-align:right;">Envíos</th>
                            <th style="text-align:right;">Subtotal</th>
                            <th style="text-align:right;">Recargos</th>
                            <th style="text-align:right;">% envíos</th>
                            <th style="min-width:90px;">Peso</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($g['filas'] as $t):
                        $pct = $kpi['envios'] ? round($t['envios'] * 100 / $kpi['envios'], 1) : 0;
                        $peso = $mas_usada['envios'] ? round($t['envios'] * 100 / $mas_usada['envios']) : 0; ?>
                        <tr>
                            <td>
                                <strong><?= gofast_md_money($t['tarifa']) ?></strong>
                                <?php if ($t['aprox']): ?><small style="color:#856404;" title="Servicios con varios destinos: valor repartido entre destinos"> *</small><?php endif; ?>
                            </td>
                            <td style="text-align:right;"><?= number_format($t['envios'], 0, ',', '.') ?></td>
                            <td style="text-align:right;"><?= gofast_md_money($t['subtotal']) ?></td>
                            <td style="text-align:right;color:#666;"><?= $t['recargos'] ? gofast_md_money($t['recargos']) : '—' ?></td>
                            <td style="text-align:right;color:#666;"><?= $pct ?>%</td>
                            <td><div class="gofast-md-barra"><span style="width:<?= min(100, $peso) ?>%;"></span></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php if ($hay_inter): ?>
                        <tr style="font-weight:700;">
                            <td><?= $tipo === 'inter' ? 'Subtotal intermunicipales' : 'Subtotal urbanos' ?></td>
                            <td style="text-align:right;"><?= number_format($g['envios'], 0, ',', '.') ?></td>
                            <td style="text-align:right;"><?= gofast_md_money($g['subtotal']) ?></td>
                            <td style="text-align:right;"><?= gofast_md_money($g['recargos']) ?></td>
                            <td style="text-align:right;"><?= $pct_g ?>%</td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($es_ultimo): ?>
                        <tr style="font-weight:700;">
                            <td>Total<?= $hay_inter ? ' (urbanos + intermunicipales)' : '' ?></td>
                            <td style="text-align:right;"><?= number_format($kpi['envios'], 0, ',', '.') ?></td>
                            <td style="text-align:right;"><?= gofast_md_money($kpi['tarifas']) ?></td>
                            <td style="text-align:right;"><?= gofast_md_money($kpi['recargos']) ?></td>
                            <td style="text-align:right;">100%</td>
                            <td></td>
                        </tr>
                        <tr style="font-weight:700;background:#fff9d6;">
                            <td colspan="2">Total pagado (tarifas + recargos)</td>
                            <td colspan="2" style="text-align:right;"><?= gofast_md_money($kpi['tarifas'] + $kpi['recargos']) ?></td>
                            <td colspan="2"></td>
                        </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>
            </div>
            </div>
            <?php endforeach; ?>
            <p class="gofast-md-nota">Se cuenta cada destino como un envío: un servicio con 2 destinos suma 2 envíos. Los recargos se muestran aparte.</p>
        </div>
        <div class="gofast-md-tarifas-grafica">
            <?= gofast_md_grafica($puntos_t) ?>
            <div class="gofast-md-mini">
                <div><span>Tarifa más usada</span><b><?= gofast_md_money($mas_usada['tarifa']) ?> · <?= number_format($mas_usada['envios'], 0, ',', '.') ?> <?= $mas_usada['envios'] === 1 ? 'envío' : 'envíos' ?></b></div>
                <div><span>Recargos del periodo</span><b><?= gofast_md_money($kpi['recargos']) ?></b></div>
                <div><span>Tarifa promedio</span><b><?= gofast_md_money($kpi['envios'] ? round($kpi['tarifas'] / $kpi['envios']) : 0) ?></b></div>
            </div>
        </div>
    </div>
    <?php if ($kpi['recargos'] > 0): ?>
        <h4 style="margin:20px 0 8px;">➕ ¿Cuántos recargos de cada valor?</h4>
        <div class="gofast-table-wrap">
            <table class="gofast-table">
                <thead><tr><th>Recargo</th><th style="text-align:right;">Valor</th><th style="text-align:right;">Envíos</th><th style="text-align:right;">Subtotal</th></tr></thead>
                <tbody>
                    <?php foreach ($datos['por_recargo'] ?? [] as $r): ?>
                        <tr>
                            <td><?= esc_html($r['nombre']) ?></td>
                            <td style="text-align:right;"><strong><?= gofast_md_money($r['valor']) ?></strong></td>
                            <td style="text-align:right;"><?= number_format($r['envios'], 0, ',', '.') ?></td>
                            <td style="text-align:right;"><?= gofast_md_money($r['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr style="font-weight:700;background:#fff9d6;"><td colspan="3">Total recargos</td><td style="text-align:right;"><?= gofast_md_money($kpi['recargos']) ?></td></tr></tfoot>
            </table>
        </div>
    <?php endif; ?>
    <?php if ($kpi['aproximados'] > 0): ?>
        <p class="gofast-md-nota">* <?= (int) $kpi['aproximados'] ?> servicio(s) con varios destinos tienen el valor repartido entre sus destinos. El total de cada servicio es exacto.</p>
    <?php endif; ?>
<?php endif; ?>
    <?php
    return ob_get_clean();
}

/**
 * Pestaña "Por trayecto" (100 más frecuentes).
 */
function gofast_md_html_trayectos($datos) {
    $kpi = $datos['kpi'];
    $labels = gofast_md_estado_labels();
    ob_start();
    ?>
<?php if (!$datos['por_trayecto']): ?>
    <p style="text-align:center;color:#666;padding:20px;">No hay servicios en este periodo.</p>
<?php else: ?>
    <div class="gofast-table-wrap">
        <table class="gofast-table">
            <thead>
                <tr>
                    <th>Origen → Destino</th>
                    <th style="text-align:right;">Envíos</th>
                    <th style="text-align:right;">Tarifa</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($datos['por_trayecto'], 0, 100, true) as $label => $g):
                $tarifas_tr = array_keys($g['tarifas']); sort($tarifas_tr); ?>
                <tr>
                    <td><?= esc_html($label) ?></td>
                    <td style="text-align:right;"><?= number_format($g['envios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;"><?= esc_html(implode(' / ', array_map('gofast_md_money', $tarifas_tr))) ?></td>
                    <td style="text-align:right;font-weight:600;"><?= gofast_md_money($g['valor']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($datos['por_trayecto']) > 100): ?>
        <p class="gofast-md-nota">Se muestran los 100 trayectos más frecuentes de <?= count($datos['por_trayecto']) ?>. El CSV de detalle los incluye todos.</p>
    <?php endif; ?>
<?php endif; ?>
    <?php
    return ob_get_clean();
}

/**
 * Pestaña "Por mes" con el cambio frente al mes anterior.
 */
function gofast_md_html_meses($datos) {
    $kpi = $datos['kpi'];
    $labels = gofast_md_estado_labels();
    ob_start();
    ?>
<?php if (!$datos['por_mes']): ?>
    <p style="text-align:center;color:#666;padding:20px;">No hay servicios en este periodo.</p>
<?php else: ?>
    <div class="gofast-table-wrap">
        <table class="gofast-table">
            <thead>
                <tr>
                    <th>Mes</th>
                    <th style="text-align:right;">Servicios</th>
                    <th style="text-align:right;">Envíos</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:right;">Promedio</th>
                    <th style="text-align:right;">Cambio vs. mes anterior</th>
                </tr>
            </thead>
            <tbody>
            <?php $anterior = null;
            foreach ($datos['por_mes'] as $ym => $g):
                $cambio = ($anterior !== null && $anterior > 0) ? round(($g['valor'] - $anterior) * 100 / $anterior, 1) : null; ?>
                <tr>
                    <td><?= esc_html(gofast_md_mes_label($ym)) ?></td>
                    <td style="text-align:right;"><?= number_format($g['servicios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;"><?= number_format($g['envios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;font-weight:600;"><?= gofast_md_money($g['valor']) ?></td>
                    <td style="text-align:right;color:#666;"><?= gofast_md_money($g['servicios'] ? round($g['valor'] / $g['servicios']) : 0) ?></td>
                    <td style="text-align:right;color:<?= $cambio === null ? '#999' : ($cambio >= 0 ? '#155724' : '#721c24') ?>;">
                        <?= $cambio === null ? '—' : (($cambio >= 0 ? '▲ ' : '▼ ') . abs($cambio) . '%') ?>
                    </td>
                </tr>
            <?php $anterior = $g['valor']; endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;background:#fff9d6;">
                    <td>Total</td>
                    <td style="text-align:right;"><?= number_format($kpi['servicios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;"><?= number_format($kpi['envios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;"><?= gofast_md_money($kpi['total']) ?></td>
                    <td style="text-align:right;"><?= gofast_md_money($kpi['servicios'] ? round($kpi['total'] / $kpi['servicios']) : 0) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="gofast-md-nota">El cambio compara el total de cada mes con el mes anterior. Si el periodo empieza o termina a mitad de mes, ese mes aparece incompleto.</p>
<?php endif; ?>
    <?php
    return ob_get_clean();
}

/**
 * Pestaña "Por estado".
 */
function gofast_md_html_estados($datos) {
    $kpi = $datos['kpi'];
    $labels = gofast_md_estado_labels();
    ob_start();
    ?>
<?php if (!$datos['por_estado']): ?>
    <p style="text-align:center;color:#666;padding:20px;">No hay servicios en este periodo.</p>
<?php else: ?>
    <div class="gofast-table-wrap">
        <table class="gofast-table">
            <thead>
                <tr>
                    <th>Estado</th>
                    <th style="text-align:right;">Servicios</th>
                    <th style="text-align:right;">Envíos</th>
                    <th style="text-align:right;">Valor</th>
                    <th>¿Suma al total?</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($labels as $key => $label):
                if (empty($datos['por_estado'][$key])) continue;
                $g = $datos['por_estado'][$key];
                $suma = in_array($key, gofast_md_estados_contables(), true); ?>
                <tr class="<?= $suma ? '' : 'gofast-md-excluido' ?>">
                    <td><span class="gofast-badge-estado gofast-badge-estado-<?= esc_attr($key) ?>"><?= esc_html($label) ?></span></td>
                    <td style="text-align:right;"><?= number_format($g['servicios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;"><?= number_format($g['envios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;"><?= gofast_md_money($g['valor']) ?></td>
                    <td><?= $suma ? '✅ Sí' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
    <?php
    return ob_get_clean();
}

/**
 * Ventana de detalle de un servicio (se llena con gofastMdDetalle).
 */
function gofast_md_html_modal() {
    ob_start();
    ?>
<div id="gofast-md-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;overflow-y:auto;padding:20px;">
    <div style="max-width:640px;margin:20px auto;background:#fff;color:#000;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;">
            <h2 style="margin:0;font-size:20px;" id="gofast-md-modal-titulo"></h2>
            <button type="button" class="gofast-icon-btn" data-md-cerrar aria-label="Cerrar">✕</button>
        </div>
        <div class="gofast-md-kv" id="gofast-md-modal-kv"></div>
        <div class="gofast-table-wrap">
            <table class="gofast-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Destino</th>
                        <th style="text-align:right;">Tarifa</th>
                        <th style="text-align:right;">Recargo</th>
                        <th style="text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="gofast-md-modal-lineas"></tbody>
            </table>
        </div>
        <p class="gofast-md-nota" id="gofast-md-modal-nota"></p>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;flex-wrap:wrap;">
            <a href="#" id="gofast-md-modal-ver" class="gofast-btn-mini gofast-btn-outline" style="text-decoration:none;">Ver servicio completo</a>
            <button type="button" class="gofast-btn-mini" data-md-cerrar>Cerrar</button>
        </div>
    </div>
</div>
    <?php
    return ob_get_clean();
}

/**
 * Pestañas, ventana de detalle, periodo y Select2.
 */
function gofast_md_js() {
    ob_start();
    ?>
<script>
(function () {
    var botones = document.querySelectorAll('[data-md-tab]');
    botones.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-md-tab');
            botones.forEach(function (b) { b.classList.toggle('gofast-config-tab-active', b === btn); });
            document.querySelectorAll('[data-md-panel]').forEach(function (p) {
                p.style.display = p.getAttribute('data-md-panel') === tab ? 'block' : 'none';
            });
            document.querySelectorAll('.gofast-md-tab-input').forEach(function (i) { i.value = tab; });
        });
    });
    var normal = function (t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); };
    // select2 avisa el cambio solo por jQuery (no dispara el change nativo): se guardan los
    // manejadores para conectarlos también por jQuery cuando select2 termine de cargar.
    var alCambiar = function (el, fn) {
        el.addEventListener('change', fn);
        (el._mdCambios = el._mdCambios || []).push(fn);
    };
    var intentosS2 = 0;
    var listasSelect2 = function () {
        if (!(window.jQuery && jQuery.fn.select2)) {
            if (++intentosS2 < 20) setTimeout(listasSelect2, 250);
            return;
        }
        jQuery('select.gofast-md-select2').each(function () {
            var s = this;
            if (jQuery(s).data('select2')) return;
            jQuery(s).select2({ width: 'style', minimumResultsForSearch: 0, language: { noResults: function () { return 'Sin resultados'; } } });
            (s._mdCambios || []).forEach(function (fn) { jQuery(s).on('change', fn); });
        });
    };
    window.addEventListener('load', listasSelect2);
    document.querySelectorAll('select[data-md-autoenvio]').forEach(function (s) {
        alCambiar(s, function () { s.form.submit(); });
    });
    // Franja horaria: cada fila "desde a hasta" (hora y minuto, final incluido) es una franja; se
    // envían juntas como horas=08:30-10:14,14:00-15:59. Una fila de 00:00 a 23:59 es todo el día.
    // El panel solo se cierra con su botón: los clics en las listas no deben cerrarlo.
    document.querySelectorAll('[data-md-horas]').forEach(function (caja) {
        var oculto = caja.querySelector('input[name="horas"]');
        var texto = caja.querySelector('[data-md-horas-txt]');
        var filas = caja.querySelector('[data-md-horas-filas]');
        var molde = caja.querySelector('[data-md-horas-molde]');
        var lado = function (f, i) {
            var hm = f.querySelectorAll('.gofast-md-horas-hm')[i];
            return { h: hm.querySelector('[data-md-h]'), m: hm.querySelector('[data-md-m]') };
        };
        var valor = function (l) { return l.h.value + ':' + l.m.value; };
        var minutos = function (v) { return +v.slice(0, 2) * 60 + +v.slice(3, 5); };
        var todoElDia = function (f) {
            return (minutos(valor(lado(f, 1))) + 1) % 1440 === minutos(valor(lado(f, 0)));
        };
        var poner = function (f, desde, hasta) {
            var a = lado(f, 0), b = lado(f, 1);
            a.h.value = desde.slice(0, 2); a.m.value = desde.slice(3, 5);
            b.h.value = hasta.slice(0, 2); b.m.value = hasta.slice(3, 5);
        };
        var nueva = function () {
            filas.appendChild(molde.content.cloneNode(true));
            return filas.lastElementChild;
        };
        var actualizar = function () {
            var tramos = [], todo = false;
            filas.querySelectorAll('[data-md-horas-fila]').forEach(function (f) {
                if (todoElDia(f)) { todo = true; return; }
                var t = valor(lado(f, 0)) + '-' + valor(lado(f, 1));
                if (tramos.indexOf(t) === -1) tramos.push(t);
            });
            if (todo) tramos = [];
            oculto.value = tramos.join(',');
            texto.textContent = tramos.length ? tramos.map(function (t) { return t.replace('-', ' a '); }).join(', ') : 'Todo el día';
        };
        filas.addEventListener('change', actualizar);
        filas.addEventListener('click', function (e) {
            var q = e.target.closest('[data-md-horas-quitar]');
            if (!q) return;
            q.closest('[data-md-horas-fila]').remove();
            if (!filas.children.length) nueva();
            actualizar();
        });
        caja.querySelector('[data-md-horas-agregar]').addEventListener('click', function () {
            nueva().querySelector('[data-md-h]').focus();
            actualizar();
        });
        caja.querySelector('[data-md-horas-limpiar]').addEventListener('click', function () {
            filas.innerHTML = '';
            nueva();
            actualizar();
        });
        caja.querySelectorAll('[data-md-horas-atajo]').forEach(function (b) {
            b.addEventListener('click', function () {
                var r = b.getAttribute('data-md-horas-atajo').split('-');
                if (oculto.value.split(',').indexOf(r.join('-')) !== -1) return;
                var libre = [].filter.call(filas.children, todoElDia)[0];
                poner(libre || nueva(), r[0], r[1]);
                actualizar();
            });
        });
    });
    document.querySelectorAll('[data-md-tabfiltro]').forEach(function (barra) {
        var panel = barra.closest('[data-md-panel]') || barra.parentElement;
        var buscar = barra.querySelector('[data-md-buscar]');
        var lista = barra.querySelector('[data-md-lista]');
        var chips = barra.querySelectorAll('[data-md-grupo]');
        var conteo = barra.querySelector('[data-md-conteo]');
        var grupo = '';
        var aplicar = function () {
            var q = buscar ? normal(buscar.value.trim()) : '';
            var nombre = lista ? lista.value : '';
            var secciones = panel.querySelectorAll('[data-md-seccion]');
            var vistas = 0, suma = 0;
            secciones.forEach(function (s) {
                s.style.display = (!grupo || s.getAttribute('data-md-seccion') === grupo) ? '' : 'none';
            });
            panel.querySelectorAll('tbody tr[data-valor]').forEach(function (tr) {
                var seccion = tr.closest('[data-md-seccion]');
                if (seccion && seccion.style.display === 'none') return;
                var extra = tr.classList.contains('gofast-md-fila-extra');
                var ok = (secciones.length || !grupo || tr.getAttribute('data-md-grupo') === grupo)
                    && (!q || normal(tr.textContent).indexOf(q) !== -1)
                    && (!nombre || tr.getAttribute('data-md-nombre') === nombre)
                    && (!extra || q);
                tr.style.display = ok ? '' : 'none';
                if (ok) { vistas++; suma += Number(tr.getAttribute('data-valor')) || 0; }
            });
            if (q) secciones.forEach(function (s) {
                if (s.style.display !== 'none' && !s.querySelector('tbody tr[data-valor]:not([style*="none"])')) s.style.display = 'none';
            });
            if (conteo) conteo.textContent = (q || grupo || nombre) ? (vistas ? 'Mostrando ' + vistas + ' · ' + dinero(suma) : 'Sin coincidencias') : '';
        };
        if (buscar) buscar.addEventListener('input', aplicar);
        if (lista) alCambiar(lista, aplicar);
        chips.forEach(function (c) {
            c.addEventListener('click', function () {
                grupo = c.getAttribute('data-md-grupo');
                chips.forEach(function (x) { x.classList.toggle('on', x === c); });
                aplicar();
            });
        });
    });

    var modal = document.getElementById('gofast-md-modal');
    var dinero = function (v) { return '$' + Number(v || 0).toLocaleString('es-CO'); };
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; };

    document.querySelectorAll('[data-md-detalle]').forEach(function (el) {
        el.addEventListener('click', function () {
            var s = (window.gofastMdDetalle || {})[el.getAttribute('data-md-detalle')];
            if (!s || !modal) return;

            document.getElementById('gofast-md-modal-titulo').innerHTML = 'Servicio <strong>#' + s.id + '</strong> · ' + esc(s.negocio);
            document.getElementById('gofast-md-modal-kv').innerHTML =
                '<div><small>Fecha</small>' + esc(s.fecha) + '</div>' +
                '<div><small>Estado</small><span class="gofast-badge-estado gofast-badge-estado-' + esc(s.estado) + '">' + esc(s.estado_l) + '</span></div>' +
                '<div><small>Mensajero</small>' + esc(s.mensajero) + '</div>' +
                '<div><small>Origen</small>' + esc(s.origen) + '</div>' +
                '<div><small>Tipo</small>' + esc(s.tipo) + '</div>' +
                '<div><small>Solicitado por</small>' + esc(s.solicitado) + '</div>';

            var filas = s.lineas.map(function (l, i) {
                return '<tr><td>' + (i + 1) + '</td><td>' + esc(l.destino) + (l.direccion ? '<br><small style="color:#666;">' + esc(l.direccion) + '</small>' : '') + '</td>' +
                    '<td style="text-align:right;">' + dinero(l.tarifa) + '</td>' +
                    '<td style="text-align:right;">' + (l.recargo ? dinero(l.recargo) : '—') + (l.recargo_nombre ? '<br><small style="color:#666;">' + esc(l.recargo_nombre) + '</small>' : '') + '</td>' +
                    '<td style="text-align:right;">' + dinero(l.valor) + '</td></tr>';
            }).join('');
            filas += '<tr style="font-weight:700;background:#fff9d6;"><td colspan="4">Total pagado</td><td style="text-align:right;">' + dinero(s.total) + '</td></tr>';
            document.getElementById('gofast-md-modal-lineas').innerHTML = filas;

            var notas = [];
            if (!s.cuenta) notas.push('Este servicio está cancelado: no se suma al total.');
            if (s.aprox) notas.push('* La tarifa cambió después de este servicio: el valor de cada destino es aproximado; el total pagado es exacto.');
            document.getElementById('gofast-md-modal-nota').textContent = notas.join(' ');
            document.getElementById('gofast-md-modal-ver').href = '<?= esc_js(home_url('/servicio-registrado')) ?>?id=' + s.id;
            modal.style.display = 'block';
        });
    });
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.hasAttribute('data-md-cerrar')) modal.style.display = 'none';
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.style.display = 'none'; });
    }

    document.querySelectorAll('.gofast-md-rango-libre input[type="date"]').forEach(function (input) {
        var form = input.form;
        var oculto = form ? form.querySelector('input[type="hidden"][name="periodo"]') : null;
        input.addEventListener('change', function () {
            if (oculto) oculto.value = 'rango';
        });
        input.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var boton = input.closest('.gofast-md-rango-libre').querySelector('button[value="rango"]');
            if (boton) boton.click();
        });
    });
    document.querySelectorAll('.gofast-md-chart-col').forEach(function (col) {
        col.addEventListener('click', function () {
            var wrap = col.closest('.gofast-md-chart-wrap');
            if (!wrap) return;
            wrap.querySelectorAll('.gofast-md-chart-activa').forEach(function (c) { c.classList.remove('gofast-md-chart-activa'); });
            col.classList.add('gofast-md-chart-activa');
            var info = wrap.querySelector('.gofast-md-chart-info span');
            if (info) info.textContent = col.getAttribute('data-info');
        });
    });

    var cargando = document.createElement('div');
    cargando.className = 'gofast-md-cargando';
    cargando.innerHTML = '<div><i></i>Cargando datos…</div>';
    document.body.appendChild(cargando);
    var mostrarCargando = function () { cargando.classList.add('on'); };
    document.querySelectorAll('.gofast-home form.gofast-pedidos-filtros').forEach(function (f) {
        f.addEventListener('submit', function (e) { if (!e.defaultPrevented) mostrarCargando(); });
    });
    document.querySelectorAll('.gofast-md-negocio, .gofast-pagination a, .gofast-md-actualizado a').forEach(function (a) {
        a.addEventListener('click', function (e) {
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
            mostrarCargando();
        });
    });
    window.addEventListener('pageshow', function () { cargando.classList.remove('on'); });

    var intentos = 0;
    var iniciarSelect2 = function () {
        if (!(window.jQuery && jQuery.fn.select2)) {
            if (++intentos < 20) setTimeout(iniciarSelect2, 250);
            return;
        }
        jQuery('.gofast-md-select-cliente').each(function () {
            if (jQuery(this).data('select2')) return;
            jQuery(this).select2({ width: '100%', placeholder: jQuery(this).data('placeholder'), minimumResultsForSearch: 0 });
        });
    };
    window.addEventListener('load', iniciarSelect2);
})();
</script>
    <?php
    return ob_get_clean();
}

/**
 * Nombres de usuarios por id en una sola consulta.
 */
function gofast_md_nombres_usuarios($ids) {
    global $wpdb;
    $nombres = [];
    $ids_m = array_unique(array_filter(array_map('intval', (array) $ids)));
    if ($ids_m) {
        $filas_m = $wpdb->get_results(
            "SELECT id, nombre FROM usuarios_gofast WHERE id IN (" . implode(',', array_map('intval', $ids_m)) . ")"
        );
        foreach ((array) $filas_m as $m) {
            $nombres[(int) $m->id] = $m->nombre;
        }
    }
    return $nombres;
}

function gofast_md_colores_estado() {
    return ['pendiente' => '#ffc107', 'asignado' => '#17a2b8', 'en_ruta' => '#007bff', 'entregado' => '#28a745', 'cancelado' => '#dc3545'];
}

/**
 * Datos que usa la ventana de detalle para los servicios de la página.
 */
function gofast_md_detalle_js($servicios, $mensajeros) {
    $labels = gofast_md_estado_labels();
    $detalle_js = [];
    foreach ($servicios as $s) {
        $detalle_js[$s['id']] = [
            'id'        => $s['id'],
            'fecha'     => date('d/m/Y H:i', strtotime($s['fecha'])),
            'estado'    => $s['estado'],
            'estado_l'  => $labels[$s['estado']] ?? $s['estado'],
            'mensajero' => $mensajeros[$s['mensajero_id']] ?? 'Sin asignar',
            'negocio'   => $s['negocio'],
            'solicitado' => $s['solicitado'] ?? $s['negocio'],
            'origen'    => $s['origen'] . ($s['origen_dir'] ? ' · ' . $s['origen_dir'] : ''),
            'tipo'      => ($s['inter'] ? 'Intermunicipal' : 'Urbano') . ' · ' . count($s['lineas']) . (count($s['lineas']) === 1 ? ' destino' : ' destinos'),
            'lineas'    => $s['lineas'],
            'total'     => $s['total'],
            'cuenta'    => $s['cuenta'],
            'aprox'     => $s['aprox'],
        ];
    }
    return $detalle_js;
}


function gofast_mis_domicilios_shortcode() {
    global $wpdb;

    $ctx = gofast_md_contexto();
    if (!empty($ctx['error'])) {
        return $ctx['error'];
    }

    $filtros = gofast_md_filtros();
    $labels = gofast_md_estado_labels();
    $tab = sanitize_key($_GET['tab'] ?? 'tarifa');
    $tabs_ok = ['tarifa', 'negocio', 'mensajero', 'destino', 'mes', 'estado', 'detalle'];
    if (!in_array($tab, $tabs_ok, true)) $tab = 'tarifa';

    // Admin sin cliente elegido: selector de cliente
    $clientes = [];
    if ($ctx['es_admin']) {
        $clientes = $wpdb->get_results(
            "SELECT id, nombre, telefono FROM usuarios_gofast WHERE rol = 'cliente' ORDER BY nombre ASC"
        );
    }

    $datos = $ctx['user_id'] ? gofast_md_datos($ctx['user_id'], $filtros) : null;
    $kpi = $datos ? $datos['kpi'] : null;

    list($ant_desde, $ant_hasta) = gofast_md_rango_anterior($filtros['desde'], $filtros['hasta']);
    $ant = $datos
        ? gofast_md_datos($ctx['user_id'], array_merge($filtros, ['desde' => $ant_desde, 'hasta' => $ant_hasta]))['kpi']
        : null;
    $promedio = ($kpi && $kpi['envios']) ? round($kpi['total'] / $kpi['envios']) : 0;
    $promedio_ant = ($ant && $ant['envios']) ? round($ant['total'] / $ant['envios']) : 0;

    $base_args = array_filter([
        'cliente_id' => $ctx['es_admin'] ? $ctx['user_id'] : null,
        'periodo'    => $filtros['periodo'],
        'desde'      => $filtros['periodo'] === 'rango' ? $filtros['desde'] : null,
        'hasta'      => $filtros['periodo'] === 'rango' ? $filtros['hasta'] : null,
        'negocio'    => $filtros['negocio'] !== 'todos' ? $filtros['negocio'] : null,
        'mensajero'  => $filtros['mensajero'] ?: null,
        'recargo'    => $filtros['recargo'] ?: null,
        'tipo'       => $filtros['tipo'] ?: null,
        'horas'      => gofast_md_horas_arg($filtros['horas']) ?: null,
    ]);
    $mensajeros_periodo = $datos ? gofast_md_nombres_usuarios(array_keys($datos['mensajeros_periodo'])) : [];
    if ($filtros['mensajero'] > 0 && !isset($mensajeros_periodo[$filtros['mensajero']])) {
        $mensajeros_periodo += gofast_md_nombres_usuarios([$filtros['mensajero']]);
    }
    asort($mensajeros_periodo);
    $filtros_texto = gofast_md_filtros_texto($filtros);
    $url_base = get_permalink();
    $url_export = function ($tipo) use ($url_base, $base_args) {
        return esc_url(add_query_arg(array_merge($base_args, ['gofast_md_export' => $tipo]), $url_base));
    };
    $url_negocio = function ($negocio) use ($url_base, $base_args, $tab) {
        $args = array_merge($base_args, ['tab' => $tab, 'negocio' => $negocio]);
        if ($negocio === 'todos') unset($args['negocio']);
        return esc_url(add_query_arg($args, $url_base));
    };

    // Paginación del detalle
    $det = gofast_md_det_filtros();
    $det_args = gofast_md_det_args($det);
    $servicios_det = $datos ? gofast_md_filtrar_detalle($datos['servicios'], $det) : [];

    $por_pagina = 25;
    $total_servicios = count($servicios_det);
    $total_paginas = max(1, (int) ceil($total_servicios / $por_pagina));
    $pagina = min($total_paginas, max(1, (int) ($_GET['pg'] ?? 1)));

    ob_start();
    ?>
<?= gofast_md_css() ?>

<div class="gofast-home">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin-bottom:8px;">📊 <?= $ctx['es_admin'] ? 'Estadísticas de clientes' : 'Mis estadísticas' ?></h1>
            <p class="gofast-home-text" style="margin:0;">
                <?php if ($ctx['es_admin'] && $ctx['user_id']): ?>
                    Vista del cliente <strong><?= esc_html($ctx['nombre']) ?></strong>.
                <?php elseif ($ctx['es_admin']): ?>
                    Elige un cliente para ver sus domicilios tal como él los ve.
                <?php else: ?>
                    Resumen de los servicios que has solicitado, listo para tu contabilidad.
                <?php endif; ?>
            </p>
        </div>
        <a href="<?= esc_url(home_url($ctx['es_admin'] ? '/admin-estadisticas' : '/mis-pedidos')) ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;width:auto;">
            <?= $ctx['es_admin'] ? '← Todos los clientes' : '📦 Ver mis pedidos' ?>
        </a>
    </div>

    <!-- Filtros -->
    <div class="gofast-box" style="margin-bottom:20px;">
        <form method="get" class="gofast-pedidos-filtros">
            <input type="hidden" name="tab" value="<?= esc_attr($tab) ?>" class="gofast-md-tab-input">
            <input type="hidden" name="periodo" value="<?= esc_attr($filtros['periodo']) ?>">
            <?php if ($filtros['negocio'] !== 'todos'): ?>
                <input type="hidden" name="negocio" value="<?= esc_attr($filtros['negocio']) ?>">
            <?php endif; ?>

            <?php if ($ctx['es_admin']): ?>
                <div class="gofast-pedidos-filtros-row gofast-md-filtros-grid gofast-md-filtros-cliente">
                    <div>
                        <label>Cliente</label>
                        <select name="cliente_id" class="gofast-md-select-cliente" data-placeholder="🔍 Busca un cliente">
                            <option value="0">Elige un cliente</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= (int) $c->id ?>"<?php selected($ctx['user_id'], $c->id); ?>>
                                    <?= esc_html($c->nombre . ' · ' . $c->telefono) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="gofast-pedidos-filtros-actions">
                        <button type="submit" class="gofast-btn-mini">Ver cliente</button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($datos && $datos['negocios']):
                $conteo = $datos['conteo_negocios'];
                $tarjetas = ['todos' => ['📊', 'Todos']];
                foreach ($datos['negocios'] as $n) {
                    $tarjetas[(string) $n->id] = ['🏪', $n->nombre . ((int) $n->activo ? '' : ' (inactivo)')];
                }
                $tarjetas['personal'] = ['👤', 'Pedidos personales']; ?>
                <div class="gofast-md-negocios">
                    <?php foreach ($tarjetas as $clave => $t):
                        $n_dom = (int) ($conteo[$clave] ?? 0); ?>
                        <a href="<?= $url_negocio($clave) ?>" class="gofast-md-negocio<?= $filtros['negocio'] === (string) $clave ? ' gofast-md-negocio-on' : '' ?>">
                            <span class="ico"><?= $t[0] ?></span>
                            <span><b><?= esc_html($t[1]) ?></b><small><?= number_format($n_dom, 0, ',', '.') ?> <?= $n_dom === 1 ? 'servicio' : 'servicios' ?></small></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($datos): ?>
                <div class="gofast-pedidos-filtros-row gofast-md-filtros-grid gofast-md-filtros-extra">
                    <div>
                        <label>Mensajero</label>
                        <select name="mensajero">
                            <option value="0">Todos</option>
                            <?php foreach ($mensajeros_periodo as $mid => $mnombre): ?>
                                <option value="<?= (int) $mid ?>"<?php selected($filtros['mensajero'], (int) $mid); ?>><?= esc_html($mnombre) ?></option>
                            <?php endforeach; ?>
                            <option value="-1"<?php selected($filtros['mensajero'], -1); ?>>Sin asignar</option>
                        </select>
                    </div>
                    <div>
                        <label>Recargo</label>
                        <select name="recargo">
                            <option value="">Todos</option>
                            <option value="con"<?php selected($filtros['recargo'], 'con'); ?>>Con recargo</option>
                            <option value="sin"<?php selected($filtros['recargo'], 'sin'); ?>>Sin recargo</option>
                        </select>
                    </div>
                    <div>
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="">Todos</option>
                            <option value="urbano"<?php selected($filtros['tipo'], 'urbano'); ?>>Urbano</option>
                            <option value="inter"<?php selected($filtros['tipo'], 'inter'); ?>>Intermunicipal</option>
                        </select>
                    </div>
                    <?php if ($ctx['es_admin']): ?>
                        <div>
                            <label>Franja horaria</label>
                            <?= gofast_md_html_horas($filtros['horas']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="gofast-pedidos-filtros-actions">
                        <button type="submit" class="gofast-btn-mini">Filtrar</button>
                        <?php if ($filtros_texto): ?>
                            <a href="<?= esc_url(add_query_arg(array_diff_key($base_args, ['mensajero' => 1, 'recargo' => 1, 'tipo' => 1, 'horas' => 1]) + ['tab' => $tab], $url_base)) ?>" class="gofast-btn-mini gofast-btn-outline">Quitar</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?= gofast_md_html_periodos($filtros) ?>
        </form>
        <div style="padding:10px;background:#e7f3ff;border-radius:6px;font-size:13px;">
            <strong>Periodo:</strong> <?= esc_html(gofast_md_fecha_corta($filtros['desde'])) ?> a <?= esc_html(gofast_md_fecha_corta($filtros['hasta'])) ?>
            <span style="color:#666;"> · comparado con <?= esc_html(gofast_md_fecha_corta($ant_desde)) ?> a <?= esc_html(gofast_md_fecha_corta($ant_hasta)) ?></span>
            <?php if ($filtros['negocio'] !== 'todos' && $datos): ?>
                · <strong>Negocio:</strong>
                <?= esc_html($filtros['negocio'] === 'personal' ? 'Pedidos personales' : ($datos['negocios_map'][(int) $filtros['negocio']] ?? '')) ?>
            <?php endif; ?>
            <?php if ($filtros_texto && $datos): ?>
                · <strong>Filtros:</strong> <?= esc_html(implode(' · ', $filtros_texto)) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$datos): ?>
        <div class="gofast-box" style="text-align:center;color:#666;">
            Elige un cliente para ver su resumen de domicilios.
        </div>
    <?php else: ?>

    <!-- Indicadores -->
    <div class="gofast-dashboard-stats gofast-md-kpis gofast-md-kpis-5">
        <div class="gofast-box gofast-md-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">💰</div>
            <div style="font-size:23px;font-weight:700;color:#4CAF50;margin-bottom:4px;"><?= gofast_md_money($kpi['total']) ?></div>
            <div style="font-size:13px;color:#666;">Total pagado</div>
            <?= gofast_md_delta($kpi['total'], $ant['total']) ?>
        </div>
        <div class="gofast-box gofast-md-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">📦</div>
            <div style="font-size:23px;font-weight:700;color:#F4C524;margin-bottom:4px;"><?= number_format($kpi['servicios'], 0, ',', '.') ?></div>
            <div style="font-size:13px;color:#666;">Servicios</div>
            <?= gofast_md_delta($kpi['servicios'], $ant['servicios']) ?>
        </div>
        <div class="gofast-box gofast-md-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">📍</div>
            <div style="font-size:23px;font-weight:700;color:#2196F3;margin-bottom:4px;"><?= number_format($kpi['envios'], 0, ',', '.') ?></div>
            <div style="font-size:13px;color:#666;">Envíos (destinos)</div>
            <?= gofast_md_delta($kpi['envios'], $ant['envios']) ?>
        </div>
        <div class="gofast-box gofast-md-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">🧾</div>
            <div style="font-size:23px;font-weight:700;color:#9C27B0;margin-bottom:4px;"><?= gofast_md_money($promedio) ?></div>
            <div style="font-size:13px;color:#666;">Promedio por envío</div>
            <?= gofast_md_delta($promedio, $promedio_ant) ?>
        </div>
        <div class="gofast-box gofast-md-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">➕</div>
            <div style="font-size:23px;font-weight:700;color:#FF5722;margin-bottom:4px;"><?= gofast_md_money($kpi['recargos']) ?></div>
            <div style="font-size:13px;color:#666;">Recargos</div>
            <small style="color:#666;"><?= $kpi['total'] > 0 ? round($kpi['recargos'] * 100 / $kpi['total'], 1) : 0 ?>% del total</small>
        </div>
    </div>

    <?php if ($kpi['excluidos'] > 0): ?>
        <div class="gofast-alert-info">
            ℹ️ <?= (int) $kpi['excluidos'] ?> servicio(s) cancelados no se suman al total. Puedes verlos en la pestaña <strong>Por estado</strong> y en <strong>Detalle</strong>.
        </div>
    <?php endif; ?>

    <!-- Resúmenes -->
    <div class="gofast-box" style="margin-bottom:20px;">
        <div class="gofast-md-tabs">
            <?php
            $tabs = [
                'tarifa'   => '💲 Por tarifa',
                'negocio'  => '🏪 Por negocio',
                'mensajero' => '🏍️ Por mensajero',
                'destino'  => '📍 Por destino',
                'mes'      => '📅 Por mes',
                'estado'   => '🚦 Por estado',
                'detalle'  => '📋 Detalle',
            ];
            if (!$datos['negocios']) unset($tabs['negocio']);
            if ($tab === 'negocio' && !$datos['negocios']) $tab = 'tarifa';
            if (!$datos['por_mensajero']) unset($tabs['mensajero']);
            if ($tab === 'mensajero' && !$datos['por_mensajero']) $tab = 'tarifa';
            foreach ($tabs as $key => $label): ?>
                <button type="button" class="gofast-config-tab <?= $tab === $key ? 'gofast-config-tab-active' : '' ?>" data-md-tab="<?= esc_attr($key) ?>">
                    <?= esc_html($label) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Por tarifa -->
        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="tarifa" style="display:<?= $tab === 'tarifa' ? 'block' : 'none' ?>;">
            <h3>💲 ¿Cuántos envíos de cada valor?</h3>
            <?= gofast_md_html_tarifas($datos) ?>
        </div>

        <!-- Por negocio -->
        <?php if ($datos['negocios']): ?>
        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="negocio" style="display:<?= $tab === 'negocio' ? 'block' : 'none' ?>;">
            <h3>🏪 Servicios por negocio</h3>
            <?php if (count($datos['por_negocio']) > 3) echo gofast_md_tabfiltro([], '🔍 Buscar un negocio…'); ?>
            <?= gofast_md_tabla_resumen($datos['por_negocio'], 'Negocio', $kpi['total'], true) ?>
        </div>
        <?php endif; ?>

        <!-- Por mensajero -->
        <?php if ($datos['por_mensajero']): ?>
        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="mensajero" style="display:<?= $tab === 'mensajero' ? 'block' : 'none' ?>;">
            <h3>🏍️ Mensajeros que atendieron tus envíos</h3>
            <?php if (count($datos['por_mensajero']) > 1) {
                $nombres_m = array_map('strval', array_keys($datos['por_mensajero']));
                natcasesort($nombres_m);
                echo gofast_md_tabfiltro([], '', $nombres_m, 'Todos los mensajeros');
            } ?>
            <?= gofast_md_tabla_resumen($datos['por_mensajero'], 'Mensajero', $kpi['total'], true) ?>
        </div>
        <?php endif; ?>

        <!-- Por destino -->
        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="destino" style="display:<?= $tab === 'destino' ? 'block' : 'none' ?>;">
            <h3>📍 A dónde envías más</h3>
            <?= gofast_md_html_destinos($datos) ?>
        </div>

        <!-- Por mes -->
        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="mes" style="display:<?= $tab === 'mes' ? 'block' : 'none' ?>;">
            <h3>📅 Servicios por mes</h3>
            <?= gofast_md_html_meses($datos) ?>
        </div>

        <!-- Por estado -->
        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="estado" style="display:<?= $tab === 'estado' ? 'block' : 'none' ?>;">
            <h3>🚦 Servicios por estado</h3>
            <?= gofast_md_html_estados($datos) ?>
        </div>

        <!-- Detalle -->
        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="detalle" style="display:<?= $tab === 'detalle' ? 'block' : 'none' ?>;">
            <h3>📋 Detalle de servicios</h3>
            <?php if ($datos['servicios']) echo gofast_md_html_filtro_detalle(array_merge($base_args, ['tab' => 'detalle']), $url_base, $det, $total_servicios, count($datos['servicios']), $mensajeros_periodo, gofast_md_barrios_destino($datos)); ?>
            <?php if (!$datos['servicios']): ?>
                <p style="text-align:center;color:#666;padding:20px;">No hay servicios en este periodo.</p>
            <?php elseif (!$servicios_det): ?>
                <p style="text-align:center;color:#666;padding:20px;">Ningún servicio coincide con el filtro.</p>
            <?php else:
                $pagina_servicios = array_slice($servicios_det, ($pagina - 1) * $por_pagina, $por_pagina);

                $mensajeros = gofast_md_nombres_usuarios(array_column($pagina_servicios, 'mensajero_id'));

                $colores_estado = gofast_md_colores_estado();
                $detalle_js = gofast_md_detalle_js($pagina_servicios, $mensajeros);
            ?>
                <!-- Computador: tabla -->
                <div class="gofast-md-desktop">
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <?php if ($datos['negocios']): ?><th>Negocio</th><?php endif; ?>
                                    <th>Origen → Destinos</th>
                                    <th style="text-align:right;">Recargos</th>
                                    <th style="text-align:right;">Total</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pagina_servicios as $s):
                                $rec_s = array_sum(array_column($s['lineas'], 'recargo')); ?>
                                <tr class="<?= $s['cuenta'] ? '' : 'gofast-md-excluido' ?>">
                                    <td><strong><?= (int) $s['id'] ?></strong></td>
                                    <td style="white-space:nowrap;"><?= esc_html(date('d/m/Y H:i', strtotime($s['fecha']))) ?></td>
                                    <?php if ($datos['negocios']): ?><td><?= esc_html($s['negocio']) ?></td><?php endif; ?>
                                    <td>
                                        <?= esc_html($s['origen']) ?> → <?= esc_html(implode(', ', $s['destinos'])) ?>
                                        <?php if (count($s['destinos']) > 1): ?><small style="color:#666;">(<?= count($s['destinos']) ?>)</small><?php endif; ?>
                                    </td>
                                    <td style="text-align:right;color:#666;"><?= $rec_s ? gofast_md_money($rec_s) : '—' ?></td>
                                    <td style="text-align:right;font-weight:600;"><?= gofast_md_money($s['total']) ?></td>
                                    <td><span class="gofast-badge-estado gofast-badge-estado-<?= esc_attr($s['estado']) ?>"><?= esc_html($labels[$s['estado']] ?? $s['estado']) ?></span></td>
                                    <td><button type="button" class="gofast-btn-mini gofast-btn-outline" data-md-detalle="<?= (int) $s['id'] ?>">Detalle</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Celular: tarjetas -->
                <div class="gofast-md-mobile">
                    <?php foreach ($pagina_servicios as $s):
                        $rec_s = array_sum(array_column($s['lineas'], 'recargo')); ?>
                        <div class="gofast-md-card<?= $s['cuenta'] ? '' : ' gofast-md-card-excluido' ?>" data-md-detalle="<?= (int) $s['id'] ?>" style="border-left-color:<?= esc_attr($colores_estado[$s['estado']] ?? '#ddd') ?>;">
                            <div class="gofast-md-card-row">
                                <strong>#<?= (int) $s['id'] ?> · <?= esc_html(date('d/m H:i', strtotime($s['fecha']))) ?></strong>
                                <span class="gofast-badge-estado gofast-badge-estado-<?= esc_attr($s['estado']) ?>"><?= esc_html($labels[$s['estado']] ?? $s['estado']) ?></span>
                            </div>
                            <?php if ($datos['negocios']): ?>
                                <div style="font-size:12px;color:#666;margin-top:4px;">🏪 <?= esc_html($s['negocio']) ?></div>
                            <?php endif; ?>
                            <div class="gofast-md-card-ruta"><?= esc_html($s['origen']) ?> → <?= esc_html(implode(', ', $s['destinos'])) ?></div>
                            <div class="gofast-md-card-row">
                                <span style="font-size:12px;color:#666;"><?= $rec_s ? 'Recargo ' . gofast_md_money($rec_s) : 'Sin recargo' ?></span>
                                <strong style="font-size:16px;"><?= gofast_md_money($s['total']) ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Ventana de detalle -->
                <?= gofast_md_html_modal() ?>
                <script>window.gofastMdDetalle = <?= wp_json_encode($detalle_js) ?>;</script>

                <?php if ($total_paginas > 1): ?>
                    <div class="gofast-pagination" style="justify-content:center;margin-top:16px;">
                        <?php for ($p = 1; $p <= $total_paginas; $p++):
                            if ($total_paginas > 10 && $p > 2 && $p < $total_paginas - 1 && abs($p - $pagina) > 2) {
                                if ($p === 3 || $p === $total_paginas - 2) echo '<span style="padding:0 4px;">…</span>';
                                continue;
                            }
                            $url = esc_url(add_query_arg(array_merge($base_args, $det_args, ['tab' => 'detalle', 'pg' => $p]), $url_base)); ?>
                            <a href="<?= $url ?>" class="gofast-page-link <?= $p === $pagina ? 'gofast-page-current' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                <p class="gofast-md-nota">Los servicios en gris (cancelados) no se suman al total.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gráficas -->
    <?php if ($kpi['servicios'] > 0):
        list($modo_grafica, $puntos) = gofast_md_puntos_grafica($datos, $filtros['desde'], $filtros['hasta']); ?>
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">📈 Lo que pagaste por <?= $modo_grafica === 'dia' ? 'día' : 'mes' ?></h3>
            <?= gofast_md_grafica($puntos) ?>
        </div>
        <div class="gofast-box" style="margin-bottom:20px;">
            <h3 style="margin-top:0;">📍 Barrios a los que más envías</h3>
            <?= gofast_md_top($datos['por_destino'], $kpi['tarifas'] + $kpi['recargos'], 5, 'envios') ?>
        </div>
    <?php endif; ?>

    <!-- Descargas -->
    <div class="gofast-box">
        <h3>📥 Descargas</h3>
        <p style="font-size:13px;color:#666;margin-top:0;">Con el periodo, el negocio y los filtros elegidos arriba.</p>
        <div class="gofast-md-descargas">
            <a href="<?= $url_export('imprimir') ?>" target="_blank" rel="noopener" class="gofast-btn-mini">🧾 Estado de cuenta con detalle (PDF)</a>
            <a href="<?= esc_url(add_query_arg(array_merge($base_args, ['gofast_md_export' => 'imprimir', 'detalle' => '0']), $url_base)) ?>" target="_blank" rel="noopener" class="gofast-btn-mini">🧾 Estado de cuenta sin detalle · por tarifas (PDF)</a>
            <a href="<?= $url_export('tarifas') ?>" class="gofast-btn-mini gofast-btn-outline">📊 Resumen por tarifa (Excel)</a>
            <a href="<?= $url_export('detalle') ?>" class="gofast-btn-mini gofast-btn-outline">📋 Detalle por envío (Excel)</a>
        </div>
    </div>

    <?php endif; ?>
</div>

<?= gofast_md_js() ?>
    <?php
    return ob_get_clean();
}
add_shortcode('gofast_mis_estadisticas', 'gofast_mis_domicilios_shortcode');
add_shortcode('gofast_mis_domicilios', 'gofast_mis_domicilios_shortcode');

}

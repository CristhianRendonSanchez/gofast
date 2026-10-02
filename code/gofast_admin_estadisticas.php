/***************************************************
 * GOFAST – ESTADÍSTICAS DE CLIENTES (PANEL GENERAL DEL ADMIN)
 * Shortcode: [gofast_admin_estadisticas]  (también acepta [gofast_admin_domicilios])
 * URL: /admin-estadisticas
 *
 * Todos los servicios de todos los clientes: indicadores con
 * comparación frente al periodo anterior, gráficas, resúmenes por
 * tarifa, cliente/negocio, mensajero, destino, trayecto, mes y estado.
 *
 * Requiere el snippet "gofast_mis_estadisticas" activo (usa sus funciones gofast_md_*).
 * Totales: cuentan servicios pendientes, asignados, en ruta y entregados (no los cancelados).
 ***************************************************/

if (!function_exists('gofast_ad_filtros')) {

function gofast_ad_disponible() {
    return function_exists('gofast_md_calcular') && function_exists('gofast_md_html_tarifas');
}

function gofast_ad_es_admin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['gofast_user_id']) && strtolower($_SESSION['gofast_user_rol'] ?? '') === 'admin';
}

/**
 * cliente: 0 = todos, -1 = pedidos sin cuenta registrada.
 * mensajero: 0 = todos, -1 = sin asignar.
 */
function gofast_ad_filtros() {
    list($periodo, $desde, $hasta) = gofast_md_rango(
        sanitize_text_field($_GET['periodo'] ?? 'mes'),
        sanitize_text_field($_GET['desde'] ?? ''),
        sanitize_text_field($_GET['hasta'] ?? '')
    );
    $tipo = sanitize_key($_GET['tipo'] ?? '');
    $estado = sanitize_key($_GET['estado'] ?? '');

    return [
        'periodo'   => $periodo,
        'desde'     => $desde,
        'hasta'     => $hasta,
        'cliente'   => max(-1, (int) ($_GET['cliente'] ?? 0)),
        'negocio'   => max(0, (int) ($_GET['negocio'] ?? 0)),
        'mensajero' => max(-1, (int) ($_GET['mensajero'] ?? 0)),
        'tipo'      => in_array($tipo, ['urbano', 'inter'], true) ? $tipo : '',
        'estado'    => isset(gofast_md_estado_labels()[$estado]) ? $estado : '',
    ];
}

/**
 * Parámetros de URL de los filtros activos (para enlaces, paginación y descargas).
 */
function gofast_ad_args($f) {
    return array_filter([
        'periodo'   => $f['periodo'],
        'desde'     => $f['periodo'] === 'rango' ? $f['desde'] : null,
        'hasta'     => $f['periodo'] === 'rango' ? $f['hasta'] : null,
        'cliente'   => $f['cliente'] ?: null,
        'negocio'   => $f['negocio'] ?: null,
        'mensajero' => $f['mensajero'] ?: null,
        'tipo'      => $f['tipo'] ?: null,
        'estado'    => $f['estado'] ?: null,
    ]);
}

/**
 * WHERE con todos los filtros. El rango de fechas usa idx_fecha_tracking,
 * el cliente idx_user_fecha y el mensajero idx_mensajero_fecha.
 */
function gofast_ad_where($f, $desde = null, $hasta = null) {
    $w = ['fecha >= %s', 'fecha <= %s'];
    $p = [($desde ?: $f['desde']) . ' 00:00:00', ($hasta ?: $f['hasta']) . ' 23:59:59'];

    if ($f['cliente'] === -1) {
        $w[] = '(user_id IS NULL OR user_id = 0)';
    } elseif ($f['cliente'] > 0) {
        $w[] = 'user_id = %d';
        $p[] = $f['cliente'];
    }

    // negocio_id se guarda como texto en el JSON: se compara como texto
    if ($f['negocio'] > 0) {
        $w[] = 'JSON_UNQUOTE(JSON_EXTRACT(destinos, \'$.origen.negocio_id\')) = %s';
        $p[] = (string) $f['negocio'];
    }

    if ($f['mensajero'] === -1) {
        $w[] = '(mensajero_id IS NULL OR mensajero_id = 0)';
    } elseif ($f['mensajero'] > 0) {
        $w[] = 'mensajero_id = %d';
        $p[] = $f['mensajero'];
    }

    if ($f['tipo'] === 'inter') {
        $w[] = 'JSON_UNQUOTE(JSON_EXTRACT(destinos, \'$.tipo_servicio\')) = \'intermunicipal\'';
    } elseif ($f['tipo'] === 'urbano') {
        $w[] = '(JSON_EXTRACT(destinos, \'$.tipo_servicio\') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(destinos, \'$.tipo_servicio\')) <> \'intermunicipal\')';
    }

    if ($f['estado'] === 'pendiente') {
        $w[] = '(tracking_estado = \'pendiente\' OR tracking_estado IS NULL OR tracking_estado = \'\')';
    } elseif ($f['estado'] !== '') {
        $w[] = 'tracking_estado = %s';
        $p[] = $f['estado'];
    }

    return [implode(' AND ', $w), $p];
}

/**
 * Usuarios y negocios en memoria (dos consultas por carga).
 */
function gofast_ad_catalogos() {
    static $cat = null;
    if ($cat !== null) return $cat;

    global $wpdb;
    $cat = ['usuarios' => [], 'negocios' => []];
    foreach ((array) $wpdb->get_results("SELECT id, nombre, telefono, rol, activo FROM usuarios_gofast ORDER BY nombre ASC") as $u) {
        $cat['usuarios'][(int) $u->id] = $u;
    }
    foreach ((array) $wpdb->get_results("SELECT id, user_id, nombre, activo FROM negocios_gofast ORDER BY nombre ASC") as $n) {
        $cat['negocios'][(int) $n->id] = $n;
    }
    return $cat;
}

/**
 * Quién originó el pedido: [tipo, etiqueta]. Los mensajeros registran
 * a su nombre los pedidos que toman en la calle (user_id = mensajero).
 */
function gofast_ad_origen($sv, $cat) {
    if ($sv['inter']) {
        $tipo = 'inter';
    }
    if ($sv['negocio_id'] > 0) {
        $n = $cat['negocios'][$sv['negocio_id']] ?? null;
        return [$tipo ?? 'negocio', '🏪 ' . ($n ? $n->nombre : 'Negocio #' . $sv['negocio_id'])];
    }
    $nombre = $sv['nombre_cliente'] !== '' ? $sv['nombre_cliente'] : 'Sin nombre';
    if ($sv['user_id'] <= 0) {
        return [$tipo ?? 'registro', '📝 ' . $nombre . ' (sin registro)'];
    }
    $u = $cat['usuarios'][$sv['user_id']] ?? null;
    $rol = $u ? $u->rol : 'cliente';
    if ($rol === 'mensajero') {
        return [$tipo ?? 'mensajero', '🏍️ ' . $u->nombre . ' (tomado por mensajero)'];
    }
    if ($rol === 'admin') {
        return [$tipo ?? 'admin', '🛠️ ' . $nombre . ' (creado por admin)'];
    }
    return [$tipo ?? 'cliente', '👤 ' . ($u ? $u->nombre : $nombre)];
}

function gofast_ad_etiqueta($sv, $cat) {
    return gofast_ad_origen($sv, $cat)[1];
}

/**
 * Orden de "Por cliente / negocio": negocios y clientes primero (por valor),
 * después los sin registro, los creados por admin y al final los tomados por mensajeros.
 */
function gofast_ad_priorizar($por_negocio) {
    $nivel = function ($label) {
        foreach (['🏪' => 0, '👤' => 0, '📝' => 1, '🛠' => 2, '🏍' => 3] as $icono => $n) {
            if (strpos($label, $icono) === 0) return $n;
        }
        return 2;
    };
    uksort($por_negocio, function ($a, $b) use ($por_negocio, $nivel) {
        return [$nivel($a), $por_negocio[$b]['valor']] <=> [$nivel($b), $por_negocio[$a]['valor']];
    });
    return $por_negocio;
}

function gofast_ad_texto_plano($etiqueta) {
    return preg_replace('/^[^\p{L}\p{N}]+\s/u', '', $etiqueta);
}

function gofast_ad_es_cliente($user_id, $cat) {
    return $user_id > 0 && isset($cat['usuarios'][$user_id]) && $cat['usuarios'][$user_id]->rol === 'cliente';
}

function gofast_ad_mensajero($id, $cat) {
    if ($id <= 0) return 'Sin asignar';
    return isset($cat['usuarios'][$id]) ? $cat['usuarios'][$id]->nombre : 'Mensajero #' . $id;
}

/**
 * Recorre los servicios del filtro por lotes (keyset sobre fecha, id)
 * para no cargar un año completo en memoria.
 */
function gofast_ad_recorrer($f, $callback, $lote = 2000) {
    global $wpdb;
    list($where, $params) = gofast_ad_where($f);
    $ult_fecha = null;
    $ult_id = 0;

    do {
        $sql = "SELECT id, fecha, user_id, nombre_cliente, direccion_origen, destinos, total, tracking_estado, mensajero_id
                FROM servicios_gofast WHERE $where";
        $p = $params;
        if ($ult_fecha !== null) {
            $sql .= ' AND (fecha > %s OR (fecha = %s AND id > %d))';
            array_push($p, $ult_fecha, $ult_fecha, $ult_id);
        }
        $sql .= ' ORDER BY fecha ASC, id ASC LIMIT %d';
        $p[] = $lote;

        $filas = (array) $wpdb->get_results($wpdb->prepare($sql, $p));
        foreach ($filas as $s) {
            $callback($s);
        }
        $n = count($filas);
        if ($n) {
            $ultima = $filas[$n - 1];
            $ult_fecha = $ultima->fecha;
            $ult_id = (int) $ultima->id;
        }
        unset($filas);
    } while ($n === $lote);
}

/**
 * Resúmenes del periodo. Se guardan 3 minutos para que paginar o cambiar
 * de pestaña no vuelva a recorrer todo el periodo.
 */
function gofast_ad_datos($f, $fresco = false) {
    $clave = 'gofast_ad_' . md5(wp_json_encode($f));
    if (!$fresco) {
        $cache = get_transient($clave);
        if (is_array($cache)) return $cache;
    }

    global $wpdb;
    if (function_exists('set_time_limit')) @set_time_limit(120);
    $cat = gofast_ad_catalogos();
    $tarifas = gofast_md_tarifas();

    $res = gofast_md_resultado_vacio();
    $res['por_mensajero'] = [];
    $res['origen'] = [];
    foreach (array_keys(gofast_ad_tipos_origen()) as $k) {
        $res['origen'][$k] = ['servicios' => 0, 'valor' => 0];
    }
    $res['clientes'] = [];
    $res['enlaces'] = [];

    gofast_ad_recorrer($f, function ($s) use (&$res, $tarifas, $cat) {
        $sv = gofast_md_calcular($s, $tarifas);
        list($origen, $sv['negocio']) = gofast_ad_origen($sv, $cat);
        gofast_md_sumar($res, $sv, false);
        if (!$sv['cuenta']) return;

        $n = count($sv['lineas']);
        if (gofast_ad_es_cliente($sv['user_id'], $cat)) {
            $res['clientes'][$sv['user_id']] = true;
        }
        if ($sv['negocio_id'] > 0 && isset($cat['negocios'][$sv['negocio_id']])) {
            $res['enlaces'][$sv['negocio']] = ['cliente_id' => (int) $cat['negocios'][$sv['negocio_id']]->user_id, 'negocio' => $sv['negocio_id']];
        } elseif ($origen === 'cliente') {
            $res['enlaces'][$sv['negocio']] = ['cliente_id' => $sv['user_id']];
        }

        $mk = gofast_ad_mensajero($sv['mensajero_id'], $cat);
        if (!isset($res['por_mensajero'][$mk])) $res['por_mensajero'][$mk] = ['servicios' => 0, 'envios' => 0, 'valor' => 0];
        $res['por_mensajero'][$mk]['servicios']++;
        $res['por_mensajero'][$mk]['envios'] += $n;
        $res['por_mensajero'][$mk]['valor'] += $sv['total'];

        $res['origen'][$origen]['servicios']++;
        $res['origen'][$origen]['valor'] += $sv['total'];
    });

    gofast_md_ordenar($res);
    $res['por_negocio'] = gofast_ad_priorizar($res['por_negocio']);
    uasort($res['por_mensajero'], function ($a, $b) { return $b['valor'] <=> $a['valor']; });

    // Clientes cuyo primer domicilio cae dentro del periodo
    $res['clientes_nuevos'] = 0;
    $ids = array_keys($res['clientes']);
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $res['clientes_nuevos'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT user_id FROM servicios_gofast WHERE user_id IN ($in) GROUP BY user_id HAVING MIN(fecha) >= %s
             ) t",
            $f['desde'] . ' 00:00:00'
        ));
    }
    $res['clientes'] = count($res['clientes']);
    $res['generado'] = gofast_md_hoy('Y-m-d H:i');

    set_transient($clave, $res, 3 * MINUTE_IN_SECONDS);
    return $res;
}

/**
 * Indicadores del periodo anterior con una sola consulta agregada.
 */
function gofast_ad_kpi_anterior($f, $desde, $hasta) {
    global $wpdb;
    list($where, $params) = gofast_ad_where($f, $desde, $hasta);
    $fila = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS servicios,
                COALESCE(SUM(total), 0) AS total,
                COALESCE(SUM(GREATEST(COALESCE(JSON_LENGTH(destinos, '$.destinos'), 0), 1)), 0) AS envios
         FROM servicios_gofast
         WHERE $where AND (tracking_estado IN ('pendiente', 'asignado', 'en_ruta', 'entregado') OR tracking_estado IS NULL OR tracking_estado = '')",
        $params
    ));
    $usuarios = (array) $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT user_id FROM servicios_gofast
         WHERE $where AND user_id > 0 AND (tracking_estado IN ('pendiente', 'asignado', 'en_ruta', 'entregado') OR tracking_estado IS NULL OR tracking_estado = '')",
        $params
    ));
    $cat = gofast_ad_catalogos();
    $clientes = 0;
    foreach ($usuarios as $uid) {
        if (gofast_ad_es_cliente((int) $uid, $cat)) $clientes++;
    }
    return [
        'servicios' => (int) ($fila->servicios ?? 0),
        'total'     => (int) ($fila->total ?? 0),
        'envios'    => (int) ($fila->envios ?? 0),
        'clientes'  => $clientes,
    ];
}

function gofast_ad_tipos_origen() {
    return [
        'negocio'   => ['🏪 Negocios', '#F4C524'],
        'cliente'   => ['👤 Clientes registrados', '#2196F3'],
        'mensajero' => ['🏍️ Tomados por mensajeros', '#FF7043'],
        'admin'     => ['🛠️ Creados por admin', '#00897B'],
        'registro'  => ['📝 Sin registro', '#9e9e9e'],
        'inter'     => ['🌐 Intermunicipales', '#9C27B0'],
    ];
}

function gofast_ad_origen_html($res) {
    $tipos = gofast_ad_tipos_origen();
    $total = array_sum(array_column($res['origen'], 'valor'));
    if ($total <= 0) {
        return "<p style='text-align:center;color:#666;padding:40px 0;'>Sin datos.</p>";
    }
    ob_start();
    ?>
    <div class="gofast-md-stack">
        <?php foreach ($tipos as $k => $t):
            $pct = round($res['origen'][$k]['valor'] * 100 / $total, 1);
            if ($pct <= 0) continue; ?>
            <span style="width:<?= $pct ?>%;background:<?= $t[1] ?>;" title="<?= esc_attr($t[0] . ': ' . $pct . '%') ?>"></span>
        <?php endforeach; ?>
    </div>
    <div class="gofast-md-leyenda">
        <?php foreach ($tipos as $k => $t):
            $g = $res['origen'][$k];
            if ($g['servicios'] === 0) continue;
            $pct = round($g['valor'] * 100 / $total, 1); ?>
            <div>
                <i style="background:<?= $t[1] ?>;"></i>
                <span><?= esc_html($t[0]) ?></span>
                <strong><?= $pct ?>%</strong>
                <small><?= number_format($g['servicios'], 0, ',', '.') ?> serv. · <?= gofast_md_money($g['valor']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Tabla de cliente/negocio o mensajero con promedio por envío y enlace opcional.
 */
function gofast_ad_tabla_grupos($filas, $titulo_col, $total, $enlaces = null, $args_enlace = [], $limite = 300) {
    if (!$filas) {
        return "<p style='text-align:center;color:#666;padding:20px;'>No hay domicilios en este periodo.</p>";
    }
    ob_start();
    ?>
    <div class="gofast-table-wrap">
        <table class="gofast-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= esc_html($titulo_col) ?></th>
                    <th style="text-align:right;">Servicios</th>
                    <th style="text-align:right;">Envíos</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:right;">Promedio por envío</th>
                    <th style="min-width:120px;">% del total</th>
                    <?php if ($enlaces !== null): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php $i = 0;
            foreach (array_slice($filas, 0, $limite, true) as $label => $g):
                $i++;
                $pct = $total > 0 ? round($g['valor'] * 100 / $total, 1) : 0; ?>
                <tr>
                    <td style="color:#999;"><?= $i ?></td>
                    <td><?= esc_html($label) ?></td>
                    <td style="text-align:right;"><?= number_format($g['servicios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;"><?= number_format($g['envios'], 0, ',', '.') ?></td>
                    <td style="text-align:right;font-weight:600;"><?= gofast_md_money($g['valor']) ?></td>
                    <td style="text-align:right;color:#666;"><?= gofast_md_money($g['envios'] ? round($g['valor'] / $g['envios']) : 0) ?></td>
                    <td><div class="gofast-md-barra"><span style="width:<?= min(100, $pct) ?>%;"></span></div><small><?= $pct ?>%</small></td>
                    <?php if ($enlaces !== null): ?>
                        <td>
                            <?php if (!empty($enlaces[$label])):
                                $args_fila = array_filter(array_merge($args_enlace, $enlaces[$label]));
                                $args_pdf = array_merge($args_fila, ['negocio' => $args_fila['negocio'] ?? 'personal', 'gofast_md_export' => 'imprimir']);
                                $url_md = home_url('/mis-estadisticas'); ?>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                                    <a href="<?= esc_url(add_query_arg($args_fila, $url_md)) ?>" class="gofast-btn-mini gofast-btn-outline" style="text-decoration:none;white-space:nowrap;">Ver cliente</a>
                                    <a href="<?= esc_url(add_query_arg($args_pdf, $url_md)) ?>" target="_blank" rel="noopener" class="gofast-btn-mini" style="text-decoration:none;white-space:nowrap;" title="Estado de cuenta con detalle (PDF)">🧾 Con detalle</a>
                                    <a href="<?= esc_url(add_query_arg(array_merge($args_pdf, ['detalle' => '0']), $url_md)) ?>" target="_blank" rel="noopener" class="gofast-btn-mini" style="text-decoration:none;white-space:nowrap;" title="Estado de cuenta sin detalle, por tarifas (PDF)">🧾 Sin detalle</a>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($filas) > $limite): ?>
        <p class="gofast-md-nota">Se muestran los <?= $limite ?> primeros de <?= count($filas) ?>. El resumen en Excel los incluye todos.</p>
    <?php endif;
    return ob_get_clean();
}

function gofast_ad_paginacion($total_paginas, $pagina, $args, $url_base) {
    if ($total_paginas <= 1) return '';
    ob_start();
    ?>
    <div class="gofast-pagination" style="justify-content:center;margin-top:16px;">
        <?php for ($p = 1; $p <= $total_paginas; $p++):
            if ($total_paginas > 10 && $p > 2 && $p < $total_paginas - 1 && abs($p - $pagina) > 2) {
                if ($p === 3 || $p === $total_paginas - 2) echo '<span style="padding:0 4px;">…</span>';
                continue;
            }
            $url = esc_url(add_query_arg(array_merge($args, ['tab' => 'detalle', 'pg' => $p]), $url_base)); ?>
            <a href="<?= $url ?>" class="gofast-page-link <?= $p === $pagina ? 'gofast-page-current' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Una página del detalle directo desde SQL (no se guarda el periodo en memoria).
 */
function gofast_ad_detalle_pagina($f, $pagina, $por_pagina) {
    global $wpdb;
    list($where, $params) = gofast_ad_where($f);
    $params[] = $por_pagina;
    $params[] = ($pagina - 1) * $por_pagina;

    $filas = (array) $wpdb->get_results($wpdb->prepare(
        "SELECT id, fecha, user_id, nombre_cliente, direccion_origen, destinos, total, tracking_estado, mensajero_id
         FROM servicios_gofast
         WHERE $where
         ORDER BY fecha DESC, id DESC
         LIMIT %d OFFSET %d",
        $params
    ));

    $cat = gofast_ad_catalogos();
    $tarifas = gofast_md_tarifas(gofast_md_sectores_multidestino($filas));
    $servicios = [];
    foreach ($filas as $s) {
        $sv = gofast_md_calcular($s, $tarifas);
        $sv['negocio'] = gofast_ad_etiqueta($sv, $cat);
        $sv['solicitado'] = $sv['negocio'];
        $sv['mensajero'] = gofast_ad_mensajero($sv['mensajero_id'], $cat);
        $servicios[] = $sv;
    }
    return $servicios;
}

function gofast_ad_css() {
    ob_start();
    ?>
<style>
.gofast-ad-kpi small { display: block; margin-top: 6px; font-size: 11px; }
.gofast-ad-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
.gofast-ad-chips span { background: #fff; border: 1px solid #b8daff; border-radius: 999px; padding: 2px 10px; font-size: 12px; }
</style>
    <?php
    return ob_get_clean();
}

/**
 * Descargas del panel: CSV detalle, CSV resumen e informe imprimible.
 */
function gofast_ad_descargas() {
    if (empty($_GET['gofast_ad_export'])) return;
    $tipo = sanitize_text_field($_GET['gofast_ad_export']);
    if (!in_array($tipo, ['detalle', 'resumen', 'imprimir'], true)) return;
    if (!gofast_ad_disponible() || !gofast_ad_es_admin()) return;

    if (function_exists('set_time_limit')) {
        @set_time_limit(180);
    }
    $f = gofast_ad_filtros();
    $labels = gofast_md_estado_labels();

    while (ob_get_level()) {
        ob_end_clean();
    }

    if ($tipo === 'imprimir') {
        gofast_ad_informe($f, gofast_ad_datos($f));
        exit;
    }

    $archivo = 'gofast-estadisticas-clientes-' . $tipo . '-' . $f['desde'] . '_' . $f['hasta'] . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $archivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    if ($tipo === 'detalle') {
        fputcsv($out, ['Servicio', 'Fecha', 'Cliente / negocio', 'Mensajero', 'Tipo', 'Origen', 'Destino', 'Dirección destino',
            'Tarifa', 'Recargo automático', 'Recargo peso/volumen', 'Valor envío', 'Total servicio', 'Estado', 'Suma al total', 'Valor estimado'], ';');

        $cat = gofast_ad_catalogos();
        $tarifas = gofast_md_tarifas();
        gofast_ad_recorrer($f, function ($s) use ($out, $cat, $tarifas, $labels) {
            $sv = gofast_md_calcular($s, $tarifas);
            $cliente = gofast_ad_texto_plano(gofast_ad_etiqueta($sv, $cat));
            $mensajero = gofast_ad_mensajero($sv['mensajero_id'], $cat);
            foreach ($sv['lineas'] as $i => $l) {
                fputcsv($out, [
                    $sv['id'], $sv['fecha'], $cliente, $mensajero,
                    $sv['inter'] ? 'Intermunicipal' : 'Urbano',
                    $sv['origen'], $l['destino'], $l['direccion'],
                    $l['tarifa'], $l['recargo_auto'], $l['recargo_vol'], $l['valor'],
                    $i === 0 ? $sv['total'] : '',
                    $labels[$sv['estado']] ?? $sv['estado'],
                    $sv['cuenta'] ? 'Sí' : 'No',
                    $sv['aprox'] ? 'Sí' : 'No',
                ], ';');
            }
        });
    } else {
        $d = gofast_ad_datos($f);
        $kpi = $d['kpi'];

        fputcsv($out, ['RESUMEN POR TARIFA'], ';');
        fputcsv($out, ['Tarifa', 'Tipo', 'Envíos', 'Subtotal tarifas', 'Recargos', 'Total'], ';');
        foreach ($d['por_tarifa'] as $t) {
            fputcsv($out, [$t['tarifa'], $t['inter'] ? 'Intermunicipal' : 'Urbano', $t['envios'], $t['subtotal'], $t['recargos'], $t['subtotal'] + $t['recargos']], ';');
        }
        fputcsv($out, ['TOTAL', '', $kpi['envios'], $kpi['tarifas'], $kpi['recargos'], $kpi['tarifas'] + $kpi['recargos']], ';');

        $secciones = [
            'RESUMEN POR CLIENTE / NEGOCIO' => ['Cliente / negocio', $d['por_negocio']],
            'RESUMEN POR MENSAJERO'         => ['Mensajero', $d['por_mensajero']],
        ];
        foreach ($secciones as $titulo => $sec) {
            fputcsv($out, [], ';');
            fputcsv($out, [$titulo], ';');
            fputcsv($out, [$sec[0], 'Servicios', 'Envíos', 'Total'], ';');
            foreach ($sec[1] as $label => $g) {
                fputcsv($out, [gofast_ad_texto_plano($label), $g['servicios'], $g['envios'], $g['valor']], ';');
            }
        }

        fputcsv($out, [], ';');
        fputcsv($out, ['RESUMEN POR MES'], ';');
        fputcsv($out, ['Mes', 'Servicios', 'Envíos', 'Total'], ';');
        foreach ($d['por_mes'] as $ym => $g) {
            fputcsv($out, [gofast_md_mes_label($ym), $g['servicios'], $g['envios'], $g['valor']], ';');
        }

        fputcsv($out, [], ';');
        fputcsv($out, ['RESUMEN POR ESTADO'], ';');
        fputcsv($out, ['Estado', 'Servicios', 'Envíos', 'Valor', 'Suma al total'], ';');
        foreach ($labels as $key => $label) {
            if (empty($d['por_estado'][$key])) continue;
            $g = $d['por_estado'][$key];
            fputcsv($out, [$label, $g['servicios'], $g['envios'], $g['valor'], in_array($key, gofast_md_estados_contables(), true) ? 'Sí' : 'No'], ';');
        }
    }

    fclose($out);
    exit;
}
add_action('template_redirect', 'gofast_ad_descargas');

function gofast_ad_informe($f, $d) {
    $kpi = $d['kpi'];
    $labels = gofast_md_estado_labels();
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informe de domicilios – Go Fast</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; margin: 0; padding: 24px; font-size: 13px; }
        .no-print { background: #f8f9fa; border-bottom: 2px solid #F4C524; padding: 14px; text-align: center; margin: -24px -24px 20px; }
        .no-print button { background: #28a745; color: #fff; border: 0; padding: 10px 24px; border-radius: 6px; font-weight: 600; cursor: pointer; margin: 0 6px; }
        .no-print .gris { background: #6c757d; }
        .cab { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #F4C524; padding-bottom: 12px; margin-bottom: 16px; }
        .cab h1 { margin: 0 0 4px; font-size: 22px; }
        .muted { color: #666; }
        .kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 18px; }
        .kpi { border: 1px solid #eee; border-radius: 8px; padding: 10px; text-align: center; }
        .kpi b { display: block; font-size: 17px; margin-bottom: 2px; }
        h2 { font-size: 15px; margin: 18px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #fafafa; }
        td.n, th.n { text-align: right; }
        tfoot td { font-weight: 700; border-top: 2px solid #ddd; }
        .dos { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @page { size: A4; margin: 12mm; }
        @media print { .no-print { display: none; } body { padding: 0; } h2 { page-break-after: avoid; } tr { page-break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">📥 Guardar como PDF / Imprimir</button>
        <button class="gris" onclick="window.close()">✕ Cerrar</button>
    </div>

    <div class="cab">
        <div>
            <h1>Informe general de domicilios</h1>
            <div class="muted">Periodo: <?= esc_html(gofast_md_fecha_corta($f['desde'])) ?> a <?= esc_html(gofast_md_fecha_corta($f['hasta'])) ?></div>
        </div>
        <div class="muted" style="text-align:right;">
            <strong style="color:#000;font-size:16px;">GO FAST</strong><br>
            Generado: <?= esc_html(gofast_md_hoy('Y-m-d H:i')) ?>
        </div>
    </div>

    <div class="kpis">
        <div class="kpi"><b><?= gofast_md_money($kpi['total']) ?></b>Ingresos</div>
        <div class="kpi"><b><?= number_format($kpi['servicios'], 0, ',', '.') ?></b>Servicios</div>
        <div class="kpi"><b><?= number_format($kpi['envios'], 0, ',', '.') ?></b>Envíos</div>
        <div class="kpi"><b><?= gofast_md_money($kpi['recargos']) ?></b>Recargos</div>
        <div class="kpi"><b><?= number_format($d['clientes'], 0, ',', '.') ?></b>Clientes activos</div>
    </div>

    <?php
    $grupos = gofast_md_tarifas_por_tipo($d['por_tarifa']);
    $hay_inter = !empty($grupos['inter']['filas']);
    foreach ($grupos as $tipo => $g): if (!$g['filas']) continue; ?>
    <h2><?= $tipo === 'inter' ? 'Envíos intermunicipales' : ($hay_inter ? 'Resumen por tarifa · urbanos' : 'Resumen por tarifa') ?></h2>
    <table>
        <thead><tr><th>Tarifa</th><th class="n">Envíos</th><th class="n">Subtotal</th><th class="n">Recargos</th><th class="n">Total</th></tr></thead>
        <tbody>
        <?php foreach ($g['filas'] as $t): ?>
            <tr>
                <td><?= gofast_md_money($t['tarifa']) ?></td>
                <td class="n"><?= number_format($t['envios'], 0, ',', '.') ?></td>
                <td class="n"><?= gofast_md_money($t['subtotal']) ?></td>
                <td class="n"><?= gofast_md_money($t['recargos']) ?></td>
                <td class="n"><?= gofast_md_money($t['subtotal'] + $t['recargos']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php if ($hay_inter): ?>
            <tr><td><?= $tipo === 'inter' ? 'Subtotal intermunicipales' : 'Subtotal urbanos' ?></td><td class="n"><?= number_format($g['envios'], 0, ',', '.') ?></td><td class="n"><?= gofast_md_money($g['subtotal']) ?></td><td class="n"><?= gofast_md_money($g['recargos']) ?></td><td class="n"><?= gofast_md_money($g['subtotal'] + $g['recargos']) ?></td></tr>
            <?php endif; ?>
            <?php if ($tipo === 'inter' || !$hay_inter): ?>
            <tr><td>Total<?= $hay_inter ? ' (urbanos + intermunicipales)' : '' ?></td><td class="n"><?= number_format($kpi['envios'], 0, ',', '.') ?></td><td class="n"><?= gofast_md_money($kpi['tarifas']) ?></td><td class="n"><?= gofast_md_money($kpi['recargos']) ?></td><td class="n"><?= gofast_md_money($kpi['tarifas'] + $kpi['recargos']) ?></td></tr>
            <?php endif; ?>
        </tfoot>
    </table>
    <?php endforeach; ?>

    <div class="dos">
        <div>
            <h2>Top 20 clientes / negocios</h2>
            <table>
                <thead><tr><th>Cliente / negocio</th><th class="n">Serv.</th><th class="n">Total</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($d['por_negocio'], 0, 20, true) as $label => $g): ?>
                    <tr><td><?= esc_html($label) ?></td><td class="n"><?= number_format($g['servicios'], 0, ',', '.') ?></td><td class="n"><?= gofast_md_money($g['valor']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div>
            <h2>Mensajeros</h2>
            <table>
                <thead><tr><th>Mensajero</th><th class="n">Serv.</th><th class="n">Total</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($d['por_mensajero'], 0, 20, true) as $label => $g): ?>
                    <tr><td><?= esc_html($label) ?></td><td class="n"><?= number_format($g['servicios'], 0, ',', '.') ?></td><td class="n"><?= gofast_md_money($g['valor']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (count($d['por_mes']) > 1): ?>
        <h2>Por mes</h2>
        <table>
            <thead><tr><th>Mes</th><th class="n">Servicios</th><th class="n">Envíos</th><th class="n">Total</th></tr></thead>
            <tbody>
            <?php foreach ($d['por_mes'] as $ym => $g): ?>
                <tr><td><?= esc_html(gofast_md_mes_label($ym)) ?></td><td class="n"><?= number_format($g['servicios'], 0, ',', '.') ?></td><td class="n"><?= number_format($g['envios'], 0, ',', '.') ?></td><td class="n"><?= gofast_md_money($g['valor']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Por estado</h2>
    <table>
        <thead><tr><th>Estado</th><th class="n">Servicios</th><th class="n">Valor</th><th>¿Suma al total?</th></tr></thead>
        <tbody>
        <?php foreach ($labels as $key => $label):
            if (empty($d['por_estado'][$key])) continue;
            $g = $d['por_estado'][$key]; ?>
            <tr><td><?= esc_html($label) ?></td><td class="n"><?= number_format($g['servicios'], 0, ',', '.') ?></td><td class="n"><?= gofast_md_money($g['valor']) ?></td><td><?= in_array($key, gofast_md_estados_contables(), true) ? 'Sí' : 'No' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="muted">Los totales incluyen los servicios pendientes, asignados, en ruta y entregados. No incluyen los cancelados.</p>
</body>
</html><?php
}


function gofast_admin_domicilios_shortcode() {
    if (!gofast_ad_es_admin()) {
        return "<div class='gofast-box'>⚠️ Esta sección es solo para administradores.</div>";
    }
    if (!gofast_ad_disponible()) {
        return "<div class='gofast-box'>⚠️ Activa el snippet <strong>gofast_mis_estadisticas</strong>: este panel usa sus cálculos.</div>";
    }

    $f = gofast_ad_filtros();
    $cat = gofast_ad_catalogos();
    $labels = gofast_md_estado_labels();
    $tab = sanitize_key($_GET['tab'] ?? 'tarifa');
    $tabs_ok = ['tarifa', 'cliente', 'mensajero', 'destino', 'mes', 'estado', 'detalle'];
    if (!in_array($tab, $tabs_ok, true)) $tab = 'tarifa';

    $datos = gofast_ad_datos($f, !empty($_GET['fresco']));
    $kpi = $datos['kpi'];

    list($ant_desde, $ant_hasta) = gofast_md_rango_anterior($f['desde'], $f['hasta']);
    $ant = gofast_ad_kpi_anterior($f, $ant_desde, $ant_hasta);
    $promedio = $kpi['envios'] ? round($kpi['total'] / $kpi['envios']) : 0;
    $promedio_ant = $ant['envios'] ? round($ant['total'] / $ant['envios']) : 0;

    list($modo_grafica, $puntos) = gofast_md_puntos_grafica($datos, $f['desde'], $f['hasta']);

    $args = gofast_ad_args($f);
    $url_base = get_permalink();
    $url_export = function ($tipo) use ($url_base, $args) {
        return esc_url(add_query_arg(array_merge($args, ['gofast_ad_export' => $tipo]), $url_base));
    };
    $args_cliente = ['periodo' => $f['periodo'], 'desde' => $args['desde'] ?? null, 'hasta' => $args['hasta'] ?? null];

    $por_pagina = 25;
    $total_filas = $kpi['servicios'] + $kpi['excluidos'];
    $total_paginas = max(1, (int) ceil($total_filas / $por_pagina));
    $pagina = min($total_paginas, max(1, (int) ($_GET['pg'] ?? 1)));

    $clientes = array_filter($cat['usuarios'], function ($u) { return $u->rol === 'cliente'; });
    $mensajeros = array_filter($cat['usuarios'], function ($u) { return $u->rol === 'mensajero' || $u->rol === 'admin'; });

    // Filtros activos (chips)
    $chips = [];
    if ($f['cliente'] === -1) $chips[] = '📝 Sin registro';
    elseif ($f['cliente'] > 0) $chips[] = '👤 ' . ($cat['usuarios'][$f['cliente']]->nombre ?? '#' . $f['cliente']);
    if ($f['negocio'] > 0) $chips[] = '🏪 ' . ($cat['negocios'][$f['negocio']]->nombre ?? '#' . $f['negocio']);
    if ($f['mensajero'] === -1) $chips[] = '🏍️ Sin asignar';
    elseif ($f['mensajero'] > 0) $chips[] = '🏍️ ' . gofast_ad_mensajero($f['mensajero'], $cat);
    if ($f['tipo'] !== '') $chips[] = $f['tipo'] === 'inter' ? '🌐 Intermunicipal' : '🏙️ Urbano';
    if ($f['estado'] !== '') $chips[] = '🚦 ' . $labels[$f['estado']];

    ob_start();
    ?>
<?= gofast_md_css() ?>
<?= gofast_ad_css() ?>

<div class="gofast-home">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="margin-bottom:8px;">📊 Estadísticas de clientes</h1>
            <p class="gofast-home-text" style="margin:0;">Resumen de todos los domicilios: ingresos, clientes, mensajeros y tarifas.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?= esc_url(home_url('/mis-estadisticas')) ?>" class="gofast-btn-mini gofast-btn-outline" style="text-decoration:none;white-space:nowrap;">👤 Vista de un cliente</a>
            <a href="<?= esc_url(home_url('/dashboard-admin')) ?>" class="gofast-btn-request" style="text-decoration:none;white-space:nowrap;width:auto;">← Volver al Dashboard</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="gofast-box" style="margin-bottom:20px;">
        <form method="get" class="gofast-pedidos-filtros">
            <input type="hidden" name="tab" value="<?= esc_attr($tab) ?>" class="gofast-md-tab-input">
            <input type="hidden" name="periodo" value="<?= esc_attr($f['periodo']) ?>">
            <div class="gofast-pedidos-filtros-row gofast-md-filtros-grid">
                <div>
                    <label>Cliente</label>
                    <select name="cliente" class="gofast-md-select-cliente" data-placeholder="🔍 Todos">
                        <option value="0">Todos</option>
                        <option value="-1"<?php selected($f['cliente'], -1); ?>>📝 Sin registro</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= (int) $c->id ?>"<?php selected($f['cliente'], (int) $c->id); ?>><?= esc_html($c->nombre . ' · ' . $c->telefono) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Negocio</label>
                    <select name="negocio" class="gofast-md-select-cliente" data-placeholder="🔍 Todos">
                        <option value="0">Todos</option>
                        <?php foreach ($cat['negocios'] as $n): ?>
                            <option value="<?= (int) $n->id ?>"<?php selected($f['negocio'], (int) $n->id); ?>><?= esc_html($n->nombre . ((int) $n->activo ? '' : ' (inactivo)')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Mensajero</label>
                    <select name="mensajero" class="gofast-md-select-cliente" data-placeholder="🔍 Todos">
                        <option value="0">Todos</option>
                        <option value="-1"<?php selected($f['mensajero'], -1); ?>>Sin asignar</option>
                        <?php foreach ($mensajeros as $m): ?>
                            <option value="<?= (int) $m->id ?>"<?php selected($f['mensajero'], (int) $m->id); ?>><?= esc_html($m->nombre . ((int) $m->activo ? '' : ' (inactivo)')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="">Todos</option>
                        <option value="urbano"<?php selected($f['tipo'], 'urbano'); ?>>Urbano</option>
                        <option value="inter"<?php selected($f['tipo'], 'inter'); ?>>Intermunicipal</option>
                    </select>
                </div>

                <div>
                    <label>Estado</label>
                    <select name="estado">
                        <option value="">Todos</option>
                        <?php foreach (array_intersect_key($labels, array_flip(['pendiente', 'asignado'])) as $key => $label): ?>
                            <option value="<?= esc_attr($key) ?>"<?php selected($f['estado'], $key); ?>><?= esc_html($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gofast-pedidos-filtros-actions">
                    <button type="submit" class="gofast-btn-mini">Filtrar</button>
                    <a href="<?= esc_url($url_base) ?>" class="gofast-btn-mini gofast-btn-outline">Limpiar</a>
                </div>
            </div>
            <?= gofast_md_html_periodos($f) ?>
        </form>
        <div style="padding:10px;background:#e7f3ff;border-radius:6px;font-size:13px;">
            <strong>Periodo:</strong> <?= esc_html(gofast_md_fecha_corta($f['desde'])) ?> a <?= esc_html(gofast_md_fecha_corta($f['hasta'])) ?>
            <span style="color:#666;"> · comparado con <?= esc_html(gofast_md_fecha_corta($ant_desde)) ?> a <?= esc_html(gofast_md_fecha_corta($ant_hasta)) ?></span>
            <span class="gofast-md-actualizado" style="float:right;color:#666;font-size:12px;">
                Datos de las <?= esc_html(substr($datos['generado'], 11, 5)) ?> ·
                <a href="<?= esc_url(add_query_arg(array_merge($args, ['tab' => $tab, 'fresco' => 1]), $url_base)) ?>">🔄 Actualizar</a>
            </span>
            <?php if ($chips): ?>
                <div class="gofast-ad-chips">
                    <?php foreach ($chips as $chip): ?><span><?= esc_html($chip) ?></span><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Indicadores -->
    <div class="gofast-dashboard-stats gofast-md-kpis gofast-md-kpis-3">
        <div class="gofast-box gofast-ad-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">💰</div>
            <div style="font-size:24px;font-weight:700;color:#4CAF50;margin-bottom:4px;"><?= gofast_md_money($kpi['total']) ?></div>
            <div style="font-size:13px;color:#666;">Ingresos</div>
            <?= gofast_md_delta($kpi['total'], $ant['total']) ?>
        </div>
        <div class="gofast-box gofast-ad-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">📦</div>
            <div style="font-size:24px;font-weight:700;color:#F4C524;margin-bottom:4px;"><?= number_format($kpi['servicios'], 0, ',', '.') ?></div>
            <div style="font-size:13px;color:#666;">Servicios</div>
            <?= gofast_md_delta($kpi['servicios'], $ant['servicios']) ?>
        </div>
        <div class="gofast-box gofast-ad-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">📍</div>
            <div style="font-size:24px;font-weight:700;color:#2196F3;margin-bottom:4px;"><?= number_format($kpi['envios'], 0, ',', '.') ?></div>
            <div style="font-size:13px;color:#666;">Envíos (destinos)</div>
            <?= gofast_md_delta($kpi['envios'], $ant['envios']) ?>
        </div>
        <div class="gofast-box gofast-ad-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">🧾</div>
            <div style="font-size:24px;font-weight:700;color:#9C27B0;margin-bottom:4px;"><?= gofast_md_money($promedio) ?></div>
            <div style="font-size:13px;color:#666;">Promedio por envío</div>
            <?= gofast_md_delta($promedio, $promedio_ant) ?>
        </div>
        <div class="gofast-box gofast-ad-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">➕</div>
            <div style="font-size:24px;font-weight:700;color:#FF5722;margin-bottom:4px;"><?= gofast_md_money($kpi['recargos']) ?></div>
            <div style="font-size:13px;color:#666;">Recargos</div>
            <small style="color:#666;"><?= $kpi['total'] > 0 ? round($kpi['recargos'] * 100 / $kpi['total'], 1) : 0 ?>% de los ingresos</small>
        </div>
        <div class="gofast-box gofast-ad-kpi" style="text-align:center;padding:18px;">
            <div style="font-size:30px;margin-bottom:6px;">👥</div>
            <div style="font-size:24px;font-weight:700;color:#00897B;margin-bottom:4px;"><?= number_format($datos['clientes'], 0, ',', '.') ?></div>
            <div style="font-size:13px;color:#666;">Clientes activos</div>
            <?= gofast_md_delta($datos['clientes'], $ant['clientes']) ?>
            <?php if ($datos['clientes_nuevos'] > 0): ?>
                <small style="color:#00897B;">🆕 <?= (int) $datos['clientes_nuevos'] ?> nuevo(s)</small>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($kpi['excluidos'] > 0): ?>
        <div class="gofast-alert-info">
            ℹ️ <?= number_format($kpi['excluidos'], 0, ',', '.') ?> servicio(s) cancelados no se suman a los totales. Puedes verlos en <strong>Por estado</strong> y en <strong>Detalle</strong>.
        </div>
    <?php endif; ?>

    <!-- Resúmenes -->
    <div class="gofast-box" style="margin-bottom:20px;">
        <div class="gofast-md-tabs">
            <?php
            $tabs = [
                'tarifa'    => '💲 Por tarifa',
                'cliente'   => '🏪 Por cliente / negocio',
                'mensajero' => '🏍️ Por mensajero',
                'destino'   => '📍 Por destino',
                'mes'       => '📅 Por mes',
                'estado'    => '🚦 Por estado',
                'detalle'   => '📋 Detalle',
            ];
            foreach ($tabs as $key => $label): ?>
                <button type="button" class="gofast-config-tab <?= $tab === $key ? 'gofast-config-tab-active' : '' ?>" data-md-tab="<?= esc_attr($key) ?>">
                    <?= esc_html($label) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="tarifa" style="display:<?= $tab === 'tarifa' ? 'block' : 'none' ?>;">
            <h3>💲 ¿Cuántos envíos de cada valor?</h3>
            <?= gofast_md_html_tarifas($datos) ?>
        </div>

        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="cliente" style="display:<?= $tab === 'cliente' ? 'block' : 'none' ?>;">
            <h3>🏪 Domicilios por cliente o negocio</h3>
            <p class="gofast-md-nota" style="margin-top:0;">🏪 negocio · 👤 cliente registrado · 🏍️ pedido que el mensajero tomó y registró a su nombre · 🛠️ creado por un admin · 📝 sin cuenta. Primero negocios y clientes, después los tomados por mensajeros. "Ver cliente" abre la vista que ve ese cliente; "Con detalle" y "Sin detalle" descargan su estado de cuenta del periodo.</p>
            <?= gofast_ad_tabla_grupos($datos['por_negocio'], 'Cliente / negocio', $kpi['total'], $datos['enlaces'], $args_cliente) ?>
        </div>

        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="mensajero" style="display:<?= $tab === 'mensajero' ? 'block' : 'none' ?>;">
            <h3>🏍️ Domicilios por mensajero</h3>
            <?= gofast_ad_tabla_grupos($datos['por_mensajero'], 'Mensajero', $kpi['total']) ?>
        </div>

        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="destino" style="display:<?= $tab === 'destino' ? 'block' : 'none' ?>;">
            <h3>📍 Destinos con más envíos</h3>
            <?= gofast_md_html_destinos($datos) ?>
        </div>

        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="mes" style="display:<?= $tab === 'mes' ? 'block' : 'none' ?>;">
            <h3>📅 Domicilios por mes</h3>
            <?= gofast_md_html_meses($datos) ?>
        </div>

        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="estado" style="display:<?= $tab === 'estado' ? 'block' : 'none' ?>;">
            <h3>🚦 Servicios por estado</h3>
            <?= gofast_md_html_estados($datos) ?>
        </div>

        <div class="gofast-config-tab-content gofast-md-panel" data-md-panel="detalle" style="display:<?= $tab === 'detalle' ? 'block' : 'none' ?>;">
            <h3>📋 Detalle de servicios <small style="color:#666;font-weight:400;">(<?= number_format($total_filas, 0, ',', '.') ?>)</small></h3>
            <?php if (!$total_filas): ?>
                <p style="text-align:center;color:#666;padding:20px;">No hay domicilios con estos filtros.</p>
            <?php else:
                $pagina_servicios = gofast_ad_detalle_pagina($f, $pagina, $por_pagina);
                $nombres = [];
                foreach ($pagina_servicios as $s) {
                    $nombres[$s['mensajero_id']] = $s['mensajero'];
                }
                $detalle_js = gofast_md_detalle_js($pagina_servicios, $nombres);
                $colores_estado = gofast_md_colores_estado();
            ?>
                <div class="gofast-md-desktop">
                    <div class="gofast-table-wrap">
                        <table class="gofast-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Cliente / negocio</th>
                                    <th>Origen → Destinos</th>
                                    <th>Mensajero</th>
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
                                    <td><?= esc_html($s['negocio']) ?></td>
                                    <td>
                                        <?= $s['inter'] ? '🌐 ' : '' ?><?= esc_html($s['origen']) ?> → <?= esc_html(implode(', ', $s['destinos'])) ?>
                                        <?php if (count($s['destinos']) > 1): ?><small style="color:#666;">(<?= count($s['destinos']) ?>)</small><?php endif; ?>
                                    </td>
                                    <td><?= esc_html($s['mensajero']) ?></td>
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

                <div class="gofast-md-mobile">
                    <?php foreach ($pagina_servicios as $s): ?>
                        <div class="gofast-md-card<?= $s['cuenta'] ? '' : ' gofast-md-card-excluido' ?>" data-md-detalle="<?= (int) $s['id'] ?>" style="border-left-color:<?= esc_attr($colores_estado[$s['estado']] ?? '#ddd') ?>;">
                            <div class="gofast-md-card-row">
                                <strong>#<?= (int) $s['id'] ?> · <?= esc_html(date('d/m H:i', strtotime($s['fecha']))) ?></strong>
                                <span class="gofast-badge-estado gofast-badge-estado-<?= esc_attr($s['estado']) ?>"><?= esc_html($labels[$s['estado']] ?? $s['estado']) ?></span>
                            </div>
                            <div style="font-size:12px;color:#666;margin-top:4px;"><?= esc_html($s['negocio']) ?></div>
                            <div class="gofast-md-card-ruta"><?= $s['inter'] ? '🌐 ' : '' ?><?= esc_html($s['origen']) ?> → <?= esc_html(implode(', ', $s['destinos'])) ?></div>
                            <div class="gofast-md-card-row">
                                <span style="font-size:12px;color:#666;">🏍️ <?= esc_html($s['mensajero']) ?></span>
                                <strong style="font-size:16px;"><?= gofast_md_money($s['total']) ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?= gofast_md_html_modal() ?>
                <script>window.gofastMdDetalle = <?= wp_json_encode($detalle_js) ?>;</script>

                <?= gofast_ad_paginacion($total_paginas, $pagina, $args, $url_base) ?>
                <p class="gofast-md-nota">Los servicios en gris (cancelados) no se suman a los totales.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gráficas -->
    <div class="gofast-box" style="margin-bottom:20px;">
        <h3 style="margin-top:0;">📈 Ingresos por <?= $modo_grafica === 'dia' ? 'día' : 'mes' ?></h3>
        <?= gofast_md_grafica($puntos) ?>
    </div>
    <div class="gofast-box" style="margin-bottom:20px;">
        <h3 style="margin-top:0;">📍 Barrios con más envíos</h3>
        <?= gofast_md_top($datos['por_destino'], $kpi['tarifas'] + $kpi['recargos'], 5, 'envios') ?>
    </div>

    <!-- Información adicional (solo admin) -->
    <h3 style="margin:28px 0 12px;">ℹ️ Información adicional</h3>
    <div class="gofast-box" style="margin-bottom:20px;">
        <h3 style="margin-top:0;">🧭 Origen de los pedidos</h3>
        <?= gofast_ad_origen_html($datos) ?>
    </div>

    <div class="gofast-md-grid-2">
        <div class="gofast-box">
            <h3 style="margin-top:0;">🏆 Clientes y negocios que más piden</h3>
            <?= gofast_md_top(array_intersect_key($datos['por_negocio'], $datos['enlaces']), $kpi['total']) ?>
        </div>
        <div class="gofast-box">
            <h3 style="margin-top:0;">🏍️ Mensajeros con más servicios</h3>
            <?= gofast_md_top($datos['por_mensajero'], $kpi['total']) ?>
        </div>
    </div>

    <!-- Descargas -->
    <div class="gofast-box">
        <h3>📥 Descargas</h3>
        <p style="font-size:13px;color:#666;margin-top:0;">Con el periodo y los filtros elegidos arriba.</p>
        <div class="gofast-md-descargas">
            <a href="<?= $url_export('imprimir') ?>" target="_blank" rel="noopener" class="gofast-btn-mini">🧾 Informe general (PDF)</a>
            <a href="<?= $url_export('resumen') ?>" class="gofast-btn-mini gofast-btn-outline">📊 Resúmenes (Excel)</a>
            <a href="<?= $url_export('detalle') ?>" class="gofast-btn-mini gofast-btn-outline">📋 Detalle por envío (Excel)</a>
        </div>
        <?php if ($f['cliente'] > 0):
            $args_md = array_filter([
                'cliente_id' => $f['cliente'],
                'periodo'    => $f['periodo'],
                'desde'      => $args['desde'] ?? null,
                'hasta'      => $args['hasta'] ?? null,
                'negocio'    => $f['negocio'] > 0 ? $f['negocio'] : null,
            ]);
            $url_md = function ($extra) use ($args_md, $url_base) {
                return esc_url(add_query_arg(array_merge($args_md, $extra), $url_base));
            };
        ?>
            <h4 style="margin:18px 0 6px;">👤 Lo que descarga el cliente · <?= esc_html($cat['usuarios'][$f['cliente']]->nombre ?? '#' . $f['cliente']) ?></h4>
            <div class="gofast-md-descargas">
                <a href="<?= $url_md(['gofast_md_export' => 'imprimir']) ?>" target="_blank" rel="noopener" class="gofast-btn-mini">🧾 Estado de cuenta con detalle (PDF)</a>
                <a href="<?= $url_md(['gofast_md_export' => 'imprimir', 'detalle' => '0']) ?>" target="_blank" rel="noopener" class="gofast-btn-mini">🧾 Estado de cuenta sin detalle · por tarifas (PDF)</a>
                <a href="<?= $url_md(['gofast_md_export' => 'tarifas']) ?>" class="gofast-btn-mini gofast-btn-outline">📊 Resumen por tarifa (Excel)</a>
                <a href="<?= $url_md(['gofast_md_export' => 'detalle']) ?>" class="gofast-btn-mini gofast-btn-outline">📋 Detalle por envío (Excel)</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= gofast_md_js() ?>
    <?php
    return ob_get_clean();
}
add_shortcode('gofast_admin_estadisticas', 'gofast_admin_domicilios_shortcode');
add_shortcode('gofast_admin_domicilios', 'gofast_admin_domicilios_shortcode');

}

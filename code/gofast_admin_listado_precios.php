<?php
/***************************************************
 * GOFAST – LISTADO DE PRECIOS PDF (SOLO ADMIN)
 * Shortcode: [gofast_admin_listado_precios]
 * URL: /admin-listado-precios
 *
 * Genera listado imprimible/PDF:
 * - Por zona: todos los barrios de un sector → resto de barrios
 * - Por barrio: un barrio origen → todos los demás
 * Formato: Barrio A → Barrio B = $X
 ***************************************************/

function gofast_admin_listado_precios_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión para acceder a esta sección.</div>";
    }

    $rol = strtolower($_SESSION['gofast_user_rol'] ?? 'cliente');
    if ($rol !== 'admin') {
        return "<div class='gofast-box'>⚠️ Solo los administradores pueden acceder a esta sección.</div>";
    }

    $modo = isset($_REQUEST['modo']) ? sanitize_text_field($_REQUEST['modo']) : 'zona';
    if (!in_array($modo, ['zona', 'barrio'], true)) {
        $modo = 'zona';
    }

    $sector_id = isset($_REQUEST['sector_id']) ? (int) $_REQUEST['sector_id'] : 0;
    $barrio_id = isset($_REQUEST['barrio_id']) ? (int) $_REQUEST['barrio_id'] : 0;
    $accion = isset($_REQUEST['accion']) ? sanitize_text_field($_REQUEST['accion']) : '';
    $mensaje_error = '';
    $vista_previa = '';

    $sectores = $wpdb->get_results("SELECT id, nombre FROM sectores ORDER BY nombre ASC");
    $barrios = $wpdb->get_results("SELECT id, nombre, sector_id FROM barrios ORDER BY nombre ASC");

    $barrios_por_sector = [];
    $barrios_map = [];
    foreach ($barrios as $b) {
        $sid = (int) $b->sector_id;
        if (!isset($barrios_por_sector[$sid])) {
            $barrios_por_sector[$sid] = [];
        }
        $barrios_por_sector[$sid][] = $b;
        $barrios_map[(int) $b->id] = $b;
    }

    // Generar listado si hay acción válida
    if (in_array($accion, ['ver', 'descargar'], true)) {
        $resultado = gofast_listado_precios_armar($wpdb, $modo, $sector_id, $barrio_id, $barrios, $barrios_map, $barrios_por_sector, $sectores);

        if (!empty($resultado['error'])) {
            $mensaje_error = $resultado['error'];
        } else {
            $html_listado = gofast_listado_precios_html($resultado);

            if ($accion === 'descargar') {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                ?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Listado de Precios – Go Fast</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10pt; }
            .gofast-lp-section h2 { page-break-after: avoid; }
        }
        .print-header {
            background: #f8f9fa;
            padding: 16px 20px;
            text-align: center;
            border-bottom: 2px solid #F4C524;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .print-btn {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            margin: 0 8px;
            font-weight: 600;
        }
        .print-btn:hover { background: #218838; }
        .close-btn {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
        }
        <?= gofast_listado_precios_css() ?>
    </style>
</head>
<body>
    <div class="no-print print-header">
        <button class="print-btn" onclick="window.print()">📥 Guardar como PDF / Imprimir</button>
        <button class="close-btn" onclick="window.close()">✕ Cerrar</button>
        <p style="margin: 10px 0 0; font-size: 12px; color: #666;">
            En el diálogo de impresión, selecciona "Guardar como PDF" como destino.
        </p>
    </div>
    <?= $html_listado ?>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 300); };</script>
</body>
</html><?php
                die();
            }

            // Vista previa en la misma página
            $vista_previa = $html_listado;
        }
    }

    ob_start();
    ?>
    <div class="gofast-admin-listado-precios" style="max-width: 900px; margin: 0 auto;">
        <h2 style="margin: 0 0 8px 0;">📄 Listado de Precios</h2>
        <p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">
            Genera un PDF con precios en formato <strong>Barrio A → Barrio B = $X</strong>.
            Útil para mensajeros o cotizaciones rápidas.
        </p>

        <?php if (!empty($mensaje_error)): ?>
            <div class="gofast-box" style="background: #f8d7da; border-left: 4px solid #dc3545; margin-bottom: 20px;">
                <p style="margin: 0; color: #721c24;"><?= esc_html($mensaje_error) ?></p>
            </div>
        <?php endif; ?>

        <form method="get" id="form-listado-precios" class="gofast-box" style="padding: 24px; margin-bottom: 24px;">
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 10px;">Modo de generación</label>
                <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 12px 16px; border: 2px solid <?= $modo === 'zona' ? '#F4C524' : '#ddd' ?>; border-radius: 8px; background: <?= $modo === 'zona' ? '#fffbeb' : '#fff' ?>;">
                        <input type="radio" name="modo" value="zona" <?= $modo === 'zona' ? 'checked' : '' ?> onchange="gofastLpToggleModo()">
                        <span><strong>Por zona</strong><br><small style="color:#666;">Todos los barrios de una zona → resto de la ciudad</small></span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 12px 16px; border: 2px solid <?= $modo === 'barrio' ? '#F4C524' : '#ddd' ?>; border-radius: 8px; background: <?= $modo === 'barrio' ? '#fffbeb' : '#fff' ?>;">
                        <input type="radio" name="modo" value="barrio" <?= $modo === 'barrio' ? 'checked' : '' ?> onchange="gofastLpToggleModo()">
                        <span><strong>Desde un barrio</strong><br><small style="color:#666;">Un barrio origen → todos los demás</small></span>
                    </label>
                </div>
            </div>

            <!-- Modo zona -->
            <div id="gofast-lp-modo-zona" style="display: <?= $modo === 'zona' ? 'block' : 'none' ?>;">
                <div style="margin-bottom: 16px;">
                    <label for="gofast-lp-sector" style="display: block; font-weight: 600; margin-bottom: 6px;">Zona / Sector origen</label>
                    <select id="gofast-lp-sector" name="sector_id" class="gofast-select-search" style="width: 100%;">
                        <option value="">Selecciona una zona...</option>
                        <?php foreach ($sectores as $s): ?>
                            <option value="<?= (int) $s->id ?>" <?= $sector_id === (int) $s->id ? 'selected' : '' ?>>
                                <?= esc_html($s->nombre) ?>
                                <?php
                                $n = isset($barrios_por_sector[(int) $s->id]) ? count($barrios_por_sector[(int) $s->id]) : 0;
                                echo ' (' . $n . ' barrio' . ($n === 1 ? '' : 's') . ')';
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 16px;">
                    <label for="gofast-lp-barrio-zona" style="display: block; font-weight: 600; margin-bottom: 6px;">
                        ¿No sabes la zona? Busca por barrio
                    </label>
                    <select id="gofast-lp-barrio-zona" class="gofast-select-search" style="width: 100%;">
                        <option value="">Escribe un barrio para detectar su zona...</option>
                        <?php foreach ($barrios as $b): ?>
                            <option value="<?= (int) $b->id ?>" data-sector="<?= (int) $b->sector_id ?>">
                                <?= esc_html(trim($b->nombre)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="gofast-lp-zona-hint" style="margin: 8px 0 0; font-size: 13px; color: #666;"></p>
                </div>
            </div>

            <!-- Modo barrio -->
            <div id="gofast-lp-modo-barrio" style="display: <?= $modo === 'barrio' ? 'block' : 'none' ?>;">
                <div style="margin-bottom: 16px;">
                    <label for="gofast-lp-barrio" style="display: block; font-weight: 600; margin-bottom: 6px;">Barrio origen</label>
                    <select id="gofast-lp-barrio" name="barrio_id" class="gofast-select-search" style="width: 100%;">
                        <option value="">Selecciona un barrio...</option>
                        <?php foreach ($barrios as $b): ?>
                            <option value="<?= (int) $b->id ?>" <?= $barrio_id === (int) $b->id ? 'selected' : '' ?>>
                                <?= esc_html(trim($b->nombre)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px;">
                <button type="submit" name="accion" value="ver" class="gofast-btn-mini"
                        style="background: #1a1a1a; color: #fff; padding: 12px 24px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;">
                    👁 Vista previa
                </button>
                <button type="submit" name="accion" value="descargar" class="gofast-btn-mini"
                        style="background: #28a745; color: #fff; padding: 12px 24px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;"
                        formtarget="_blank">
                    📥 Descargar PDF
                </button>
            </div>
        </form>

        <?php if (!empty($vista_previa)): ?>
            <div class="gofast-box" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
                <div style="background: #f8f9fa; padding: 12px 16px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <strong>Vista previa</strong>
                    <a href="<?= esc_url(add_query_arg([
                        'modo' => $modo,
                        'sector_id' => $sector_id,
                        'barrio_id' => $barrio_id,
                        'accion' => 'descargar',
                    ])) ?>" target="_blank"
                       style="background: #28a745; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px;">
                        📥 Abrir PDF / Imprimir
                    </a>
                </div>
                <div style="padding: 16px; max-height: 70vh; overflow: auto; background: #fff;">
                    <style><?= gofast_listado_precios_css() ?></style>
                    <?= $vista_previa ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        const sectoresNombres = <?= wp_json_encode(array_reduce($sectores, function ($acc, $s) {
            $acc[(int) $s->id] = $s->nombre;
            return $acc;
        }, [])) ?>;

        function initSelect2Lp() {
            if (!(window.jQuery && jQuery.fn.select2)) return;
            jQuery('.gofast-admin-listado-precios .gofast-select-search').each(function() {
                const $el = jQuery(this);
                if ($el.data('select2')) {
                    $el.select2('destroy');
                }
                $el.select2({
                    placeholder: '🔍 Escribe para buscar...',
                    width: '100%',
                    allowClear: true,
                    minimumResultsForSearch: 0
                });
            });
        }

        window.gofastLpToggleModo = function() {
            const modo = document.querySelector('input[name="modo"]:checked').value;
            document.getElementById('gofast-lp-modo-zona').style.display = modo === 'zona' ? 'block' : 'none';
            document.getElementById('gofast-lp-modo-barrio').style.display = modo === 'barrio' ? 'block' : 'none';

            // Actualizar bordes de radios
            document.querySelectorAll('input[name="modo"]').forEach(function(radio) {
                const label = radio.closest('label');
                if (!label) return;
                if (radio.checked) {
                    label.style.borderColor = '#F4C524';
                    label.style.background = '#fffbeb';
                } else {
                    label.style.borderColor = '#ddd';
                    label.style.background = '#fff';
                }
            });

            setTimeout(initSelect2Lp, 50);
        };

        function bindBarrioZona() {
            if (!(window.jQuery && jQuery.fn.select2)) return;
            jQuery('#gofast-lp-barrio-zona').off('change.gofastLp').on('change.gofastLp', function() {
                const $opt = jQuery(this).find('option:selected');
                const sectorId = $opt.data('sector');
                const barrioNombre = $opt.text().trim();
                const hint = document.getElementById('gofast-lp-zona-hint');
                if (sectorId) {
                    jQuery('#gofast-lp-sector').val(String(sectorId)).trigger('change');
                    const zonaNombre = sectoresNombres[sectorId] || ('Sector ' + sectorId);
                    hint.innerHTML = '✅ <strong>' + barrioNombre + '</strong> pertenece a <strong>' + zonaNombre + '</strong>. Se generará el listado de toda esa zona.';
                    hint.style.color = '#155724';
                } else {
                    hint.textContent = '';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initSelect2Lp();
            bindBarrioZona();
        });

        // Por si el DOM ya cargó
        if (document.readyState !== 'loading') {
            initSelect2Lp();
            bindBarrioZona();
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Arma filas de precios barrio → barrio usando tarifas sector → sector.
 */
if (!function_exists('gofast_listado_precios_armar')) {
function gofast_listado_precios_armar($wpdb, $modo, $sector_id, $barrio_id, $barrios, $barrios_map, $barrios_por_sector, $sectores) {
    $origenes = [];
    $titulo = '';
    $subtitulo = '';

    if ($modo === 'zona') {
        if ($sector_id <= 0) {
            return ['error' => 'Selecciona una zona/sector para generar el listado.'];
        }
        $origenes = isset($barrios_por_sector[$sector_id]) ? $barrios_por_sector[$sector_id] : [];
        if (empty($origenes)) {
            return ['error' => 'Esa zona no tiene barrios registrados.'];
        }
        $sector_nombre = '';
        foreach ($sectores as $s) {
            if ((int) $s->id === $sector_id) {
                $sector_nombre = $s->nombre;
                break;
            }
        }
        $titulo = 'Listado de precios — ' . $sector_nombre;
        $subtitulo = count($origenes) . ' barrio(s) de origen → resto de la ciudad';
    } else {
        if ($barrio_id <= 0 || empty($barrios_map[$barrio_id])) {
            return ['error' => 'Selecciona un barrio origen para generar el listado.'];
        }
        $origenes = [$barrios_map[$barrio_id]];
        $titulo = 'Listado de precios — Desde ' . trim($origenes[0]->nombre);
        $subtitulo = 'Hacia todos los demás barrios';
    }

    // Mapa de tarifas: origen_sector → destino_sector → precio
    $tarifas_rows = $wpdb->get_results("SELECT origen_sector_id, destino_sector_id, precio FROM tarifas");
    $tarifas_map = [];
    foreach ($tarifas_rows as $t) {
        $o = (int) $t->origen_sector_id;
        $d = (int) $t->destino_sector_id;
        if (!isset($tarifas_map[$o])) {
            $tarifas_map[$o] = [];
        }
        $tarifas_map[$o][$d] = (int) $t->precio;
    }

    // Ordenar orígenes por nombre
    usort($origenes, function ($a, $b) {
        return strcasecmp(trim($a->nombre), trim($b->nombre));
    });

    $grupos = [];
    $total_filas = 0;
    $omitidas = 0;

    foreach ($origenes as $origen) {
        $oid = (int) $origen->id;
        $osector = (int) $origen->sector_id;
        $filas = [];

        foreach ($barrios as $destino) {
            $did = (int) $destino->id;
            if ($did === $oid) {
                continue;
            }
            $dsector = (int) $destino->sector_id;
            if (!isset($tarifas_map[$osector][$dsector])) {
                $omitidas++;
                continue;
            }
            $filas[] = [
                'origen' => trim($origen->nombre),
                'destino' => trim($destino->nombre),
                'precio' => $tarifas_map[$osector][$dsector],
            ];
        }

        usort($filas, function ($a, $b) {
            return strcasecmp($a['destino'], $b['destino']);
        });

        if (!empty($filas)) {
            $grupos[] = [
                'origen' => trim($origen->nombre),
                'sector_id' => $osector,
                'filas' => $filas,
            ];
            $total_filas += count($filas);
        }
    }

    if ($total_filas === 0) {
        return ['error' => 'No se encontraron tarifas para generar el listado con los filtros seleccionados.'];
    }

    return [
        'titulo' => $titulo,
        'subtitulo' => $subtitulo,
        'grupos' => $grupos,
        'total_filas' => $total_filas,
        'omitidas' => $omitidas,
        'fecha' => function_exists('gofast_current_time')
            ? gofast_current_time('d/m/Y H:i')
            : date('d/m/Y H:i'),
    ];
}

/**
 * CSS compartido para vista previa e impresión.
 */
function gofast_listado_precios_css() {
    return '
        .gofast-lp-doc { padding: 24px; max-width: 800px; margin: 0 auto; }
        .gofast-lp-header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #F4C524; padding-bottom: 16px; }
        .gofast-lp-header .brand { font-size: 22px; font-weight: 800; letter-spacing: 0.5px; }
        .gofast-lp-header h1 { font-size: 16px; margin: 8px 0 4px; font-weight: 700; }
        .gofast-lp-header .meta { font-size: 12px; color: #555; }
        .gofast-lp-section { margin-bottom: 18px; }
        .gofast-lp-section h2 {
            font-size: 13px;
            background: #1a1a1a;
            color: #F4C524;
            padding: 6px 10px;
            margin: 0 0 6px;
            border-radius: 4px;
        }
        .gofast-lp-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .gofast-lp-table td { padding: 3px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        .gofast-lp-table td.arrow { width: 18px; text-align: center; color: #999; }
        .gofast-lp-table td.destino { width: auto; }
        .gofast-lp-table td.precio { width: 90px; text-align: right; font-weight: 700; white-space: nowrap; }
        .gofast-lp-footer { margin-top: 24px; font-size: 11px; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 12px; }
    ';
}

/**
 * HTML del listado (vista previa / PDF).
 */
function gofast_listado_precios_html($resultado) {
    $html = '<div class="gofast-lp-doc">';
    $html .= '<div class="gofast-lp-header">';
    $html .= '<div class="brand">GO FAST</div>';
    $html .= '<h1>' . esc_html($resultado['titulo']) . '</h1>';
    $html .= '<div class="meta">' . esc_html($resultado['subtitulo']) . ' · Generado: ' . esc_html($resultado['fecha']) . '</div>';
    $html .= '<div class="meta">' . (int) $resultado['total_filas'] . ' precios';
    if (!empty($resultado['omitidas'])) {
        $html .= ' · ' . (int) $resultado['omitidas'] . ' sin tarifa (omitidos)';
    }
    $html .= '</div></div>';

    foreach ($resultado['grupos'] as $grupo) {
        $html .= '<div class="gofast-lp-section">';
        $html .= '<h2>Desde: ' . esc_html($grupo['origen']) . '</h2>';
        $html .= '<table class="gofast-lp-table"><tbody>';
        foreach ($grupo['filas'] as $fila) {
            $precio = '$' . number_format($fila['precio'], 0, ',', '.');
            $html .= '<tr>';
            $html .= '<td class="destino">' . esc_html($fila['origen']) . ' → ' . esc_html($fila['destino']) . '</td>';
            $html .= '<td class="precio">' . esc_html($precio) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
    }

    $html .= '<div class="gofast-lp-footer">Listado generado automáticamente por Go Fast · Precios según tarifas por zona</div>';
    $html .= '</div>';

    return $html;
}

add_shortcode('gofast_admin_listado_precios', 'gofast_admin_listado_precios_shortcode');

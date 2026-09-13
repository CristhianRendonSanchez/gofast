<?php
/***************************************************
 * GOFAST – LISTADO DE PRECIOS PDF (SOLO ADMIN)
 * Shortcode: [gofast_admin_listado_precios]
 * URL: /admin-listado-precios
 *
 * Modos:
 * 1) sector_ciudad   → Barrios de zona A → toda la ciudad
 * 2) sector_sector   → Barrios zona A → barrios zona B
 * 3) barrio_sector   → Barrio → barrios de una zona
 * 4) barrio_ciudad   → Barrio → toda la ciudad
 * 5) ciudad_completa → Todos los barrios → todos los barrios
 *
 * El admin elige por sector/barrio; el PDF solo muestra
 * Barrio A → Barrio B = $X (incluye mismo barrio).
 * El precio se calcula con tarifas sector → sector.
 ***************************************************/

if (!function_exists('gofast_admin_listado_precios_shortcode')) {
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

    $modos_ok = ['sector_ciudad', 'sector_sector', 'barrio_sector', 'barrio_ciudad', 'ciudad_completa'];
    $modo = isset($_REQUEST['modo']) ? sanitize_text_field($_REQUEST['modo']) : 'sector_ciudad';
    if (!in_array($modo, $modos_ok, true)) {
        $modo = 'sector_ciudad';
    }

    $sector_origen_id = isset($_REQUEST['sector_origen_id']) ? (int) $_REQUEST['sector_origen_id'] : 0;
    if ($sector_origen_id <= 0 && !empty($_REQUEST['sector_id'])) {
        $sector_origen_id = (int) $_REQUEST['sector_id'];
    }
    $sector_destino_id = isset($_REQUEST['sector_destino_id']) ? (int) $_REQUEST['sector_destino_id'] : 0;
    $barrio_origen_id = isset($_REQUEST['barrio_origen_id']) ? (int) $_REQUEST['barrio_origen_id'] : 0;
    $accion = isset($_REQUEST['accion']) ? sanitize_text_field($_REQUEST['accion']) : '';
    $mensaje_error = '';
    $vista_previa = '';
    $vista_previa_truncada = false;
    $vista_previa_total = 0;

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

    if (in_array($accion, ['ver', 'descargar', 'csv', 'buscar'], true)) {
        $resultado = gofast_listado_precios_armar(
            $wpdb,
            $modo,
            $sector_origen_id,
            $sector_destino_id,
            $barrio_origen_id,
            $sectores,
            $barrios_por_sector,
            $barrios_map
        );

        if (!empty($resultado['error'])) {
            $mensaje_error = $resultado['error'];
        } else {
            // CSV: descarga directa (ideal para listas grandes)
            if ($accion === 'csv') {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                $filename = 'gofast-precios-' . date('Y-m-d-His') . '.csv';
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');
                echo gofast_listado_precios_csv($resultado);
                die();
            }

            $html_listado = gofast_listado_precios_html($resultado);

            // PDF / página limpia para imprimir o buscar (Ctrl+F)
            if ($accion === 'descargar' || $accion === 'buscar') {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                $auto_print = ($accion === 'descargar');
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
        .csv-btn {
            background: #0d6efd;
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            margin: 0 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
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
        .search-hint {
            margin: 10px 0 0;
            font-size: 13px;
            color: #155724;
            background: #d4edda;
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
        }
        <?= gofast_listado_precios_css() ?>
    </style>
</head>
<body>
    <div class="no-print print-header">
        <button class="print-btn" onclick="window.print()">📥 Guardar como PDF / Imprimir</button>
        <a class="csv-btn" href="<?= esc_url(add_query_arg([
            'modo' => $modo,
            'sector_origen_id' => $sector_origen_id,
            'sector_destino_id' => $sector_destino_id,
            'barrio_origen_id' => $barrio_origen_id,
            'accion' => 'csv',
        ])) ?>">📊 Descargar Excel (CSV)</a>
        <button class="close-btn" onclick="window.close()">✕ Cerrar</button>
        <?php if ($accion === 'buscar'): ?>
            <div class="search-hint">Usa <strong>Ctrl + F</strong> (o Cmd + F) para buscar un barrio en esta página.</div>
        <?php else: ?>
            <p style="margin: 10px 0 0; font-size: 12px; color: #666;">
                En el diálogo de impresión, selecciona "Guardar como PDF". Para listas grandes usa CSV.
            </p>
        <?php endif; ?>
    </div>
    <?= $html_listado ?>
    <?php if ($auto_print): ?>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 300); };</script>
    <?php endif; ?>
</body>
</html><?php
                die();
            }

            // Vista previa embebida: si es muy grande, no renderizar todo (rompe el navegador)
            $limite_previa = 400;
            if ((int) $resultado['total_filas'] > $limite_previa) {
                $vista_previa = gofast_listado_precios_html($resultado, $limite_previa);
                $vista_previa_truncada = true;
                $vista_previa_total = (int) $resultado['total_filas'];
            } else {
                $vista_previa = $html_listado;
                $vista_previa_truncada = false;
                $vista_previa_total = (int) $resultado['total_filas'];
            }
        }
    }

    $modo_activo = function ($m) use ($modo) {
        return $modo === $m;
    };

    ob_start();
    ?>
    <div class="gofast-admin-listado-precios" style="max-width: 960px; margin: 0 auto;">
        <h2 style="margin: 0 0 8px 0;">📄 Listado de Precios</h2>
        <p style="margin: 0 0 20px 0; color: #666; font-size: 14px;">
            Tú eliges por zona o barrio. El <strong>PDF solo muestra nombres de barrios</strong>
            (<code>Barrio A → Barrio B = $X</code>), para quien no conoce las zonas.
        </p>

        <?php if (!empty($mensaje_error)): ?>
            <div class="gofast-box" style="background: #f8d7da; border-left: 4px solid #dc3545; margin-bottom: 20px;">
                <p style="margin: 0; color: #721c24;"><?= esc_html($mensaje_error) ?></p>
            </div>
        <?php endif; ?>

        <form method="get" id="form-listado-precios" class="gofast-box gofast-lp-form" style="padding: 24px; margin-bottom: 24px;">
            <div class="gofast-lp-modo-title">¿Qué quieres generar?</div>
            <div class="gofast-lp-modos" role="radiogroup" aria-label="Tipo de listado">
                <?php
                $opciones = [
                    'sector_ciudad' => [
                        'icon' => '🗺️',
                        'title' => 'Zona → ciudad',
                        'desc' => 'Barrios de una zona hacia todos',
                    ],
                    'sector_sector' => [
                        'icon' => '🔀',
                        'title' => 'Zona → zona',
                        'desc' => 'Barrios de zona A hacia zona B',
                    ],
                    'barrio_sector' => [
                        'icon' => '📍',
                        'title' => 'Barrio → zona',
                        'desc' => 'Un barrio hacia una zona',
                    ],
                    'barrio_ciudad' => [
                        'icon' => '🏙️',
                        'title' => 'Barrio → ciudad',
                        'desc' => 'Un barrio hacia todos',
                    ],
                    'ciudad_completa' => [
                        'icon' => '📋',
                        'title' => 'Lista completa',
                        'desc' => 'Todos ↔ todos · usa CSV',
                    ],
                ];
                foreach ($opciones as $key => $txt):
                    $on = $modo_activo($key);
                ?>
                <label class="gofast-lp-modo<?= $on ? ' is-active' : '' ?>">
                    <input type="radio" name="modo" value="<?= esc_attr($key) ?>" <?= $on ? 'checked' : '' ?> onchange="gofastLpToggleModo()">
                    <span class="gofast-lp-modo-icon" aria-hidden="true"><?= $txt['icon'] ?></span>
                    <span class="gofast-lp-modo-text">
                        <span class="gofast-lp-modo-name"><?= esc_html($txt['title']) ?></span>
                        <span class="gofast-lp-modo-desc"><?= esc_html($txt['desc']) ?></span>
                    </span>
                    <span class="gofast-lp-modo-check" aria-hidden="true"></span>
                </label>
                <?php endforeach; ?>
            </div>

            <style>
                .gofast-lp-form .gofast-lp-modo-title {
                    font-size: 13px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                    color: #666;
                    margin: 0 0 12px;
                }
                .gofast-lp-modos {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                    gap: 10px;
                    margin-bottom: 22px;
                }
                .gofast-lp-modo {
                    position: relative;
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 8px;
                    margin: 0;
                    padding: 14px 14px 14px 14px;
                    border: 1.5px solid #e5e5e5;
                    border-radius: 12px;
                    background: #fff;
                    cursor: pointer;
                    transition: border-color .15s ease, background .15s ease, box-shadow .15s ease, transform .12s ease;
                    min-height: 0;
                }
                .gofast-lp-modo:hover {
                    border-color: #cfcfcf;
                    background: #fafafa;
                }
                .gofast-lp-modo.is-active {
                    border-color: #F4C524;
                    background: linear-gradient(180deg, #fffdf3 0%, #fff8db 100%);
                    box-shadow: 0 0 0 3px rgba(244, 197, 36, 0.22);
                }
                .gofast-lp-modo input[type="radio"] {
                    position: absolute;
                    opacity: 0;
                    pointer-events: none;
                }
                .gofast-lp-modo-icon {
                    font-size: 20px;
                    line-height: 1;
                    width: 36px;
                    height: 36px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 10px;
                    background: #f3f3f3;
                }
                .gofast-lp-modo.is-active .gofast-lp-modo-icon {
                    background: #1a1a1a;
                }
                .gofast-lp-modo-text {
                    display: flex;
                    flex-direction: column;
                    gap: 3px;
                    padding-right: 18px;
                }
                .gofast-lp-modo-name {
                    font-size: 14px;
                    font-weight: 700;
                    color: #1a1a1a;
                    line-height: 1.25;
                }
                .gofast-lp-modo-desc {
                    font-size: 12px;
                    color: #777;
                    line-height: 1.35;
                }
                .gofast-lp-modo-check {
                    position: absolute;
                    top: 12px;
                    right: 12px;
                    width: 18px;
                    height: 18px;
                    border-radius: 50%;
                    border: 1.5px solid #ccc;
                    background: #fff;
                    box-sizing: border-box;
                }
                .gofast-lp-modo.is-active .gofast-lp-modo-check {
                    border-color: #1a1a1a;
                    background: #1a1a1a;
                    box-shadow: inset 0 0 0 3px #F4C524;
                }
                @media (max-width: 560px) {
                    .gofast-lp-modos {
                        grid-template-columns: 1fr;
                    }
                }
            </style>

            <!-- Campos sector origen (modos sector_*) -->
            <div id="gofast-lp-field-sector-origen" class="gofast-lp-field" style="margin-bottom:14px;display:none;">
                <label for="gofast-lp-sector-origen" style="display:block;font-weight:600;margin-bottom:6px;">Sector origen *</label>
                <select id="gofast-lp-sector-origen" name="sector_origen_id" class="gofast-select-search" style="width:100%;">
                    <option value="">Selecciona sector origen...</option>
                    <?php foreach ($sectores as $s): ?>
                        <option value="<?= (int) $s->id ?>" <?= $sector_origen_id === (int) $s->id ? 'selected' : '' ?>>
                            <?= esc_html($s->nombre) ?>
                            (<?= isset($barrios_por_sector[(int)$s->id]) ? count($barrios_por_sector[(int)$s->id]) : 0 ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top:10px;">
                    <label for="gofast-lp-barrio-ayuda-origen" style="display:block;font-size:13px;color:#666;margin-bottom:4px;">¿No sabes el sector? Busca un barrio de esa zona</label>
                    <select id="gofast-lp-barrio-ayuda-origen" class="gofast-select-search" style="width:100%;" data-target-sector="#gofast-lp-sector-origen" data-hint="#gofast-lp-hint-ayuda-origen">
                        <option value="">Buscar barrio...</option>
                        <?php foreach ($barrios as $b): ?>
                            <option value="<?= (int) $b->id ?>" data-sector="<?= (int) $b->sector_id ?>"><?= esc_html(trim($b->nombre)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="gofast-lp-hint-ayuda-origen" style="margin:6px 0 0;font-size:12px;color:#666;"></p>
                </div>
            </div>

            <!-- Campos barrio origen (modos barrio_*) -->
            <div id="gofast-lp-field-barrio-origen" class="gofast-lp-field" style="margin-bottom:14px;display:none;">
                <label for="gofast-lp-barrio-origen" style="display:block;font-weight:600;margin-bottom:6px;">Barrio origen *</label>
                <select id="gofast-lp-barrio-origen" name="barrio_origen_id" class="gofast-select-search" style="width:100%;">
                    <option value="">Selecciona barrio origen...</option>
                    <?php foreach ($barrios as $b): ?>
                        <option value="<?= (int) $b->id ?>" <?= $barrio_origen_id === (int) $b->id ? 'selected' : '' ?> data-sector="<?= (int) $b->sector_id ?>">
                            <?= esc_html(trim($b->nombre)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p id="gofast-lp-hint-barrio-origen" style="margin:6px 0 0;font-size:12px;color:#666;"></p>
            </div>

            <!-- Campos sector destino (modos *_sector) -->
            <div id="gofast-lp-field-sector-destino" class="gofast-lp-field" style="margin-bottom:14px;display:none;">
                <label for="gofast-lp-sector-destino" style="display:block;font-weight:600;margin-bottom:6px;">Sector destino *</label>
                <select id="gofast-lp-sector-destino" name="sector_destino_id" class="gofast-select-search" style="width:100%;">
                    <option value="">Selecciona sector destino...</option>
                    <?php foreach ($sectores as $s): ?>
                        <option value="<?= (int) $s->id ?>" <?= $sector_destino_id === (int) $s->id ? 'selected' : '' ?>>
                            <?= esc_html($s->nombre) ?>
                            (<?= isset($barrios_por_sector[(int)$s->id]) ? count($barrios_por_sector[(int)$s->id]) : 0 ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top:10px;">
                    <label for="gofast-lp-barrio-ayuda-destino" style="display:block;font-size:13px;color:#666;margin-bottom:4px;">¿No sabes el sector? Busca un barrio de esa zona</label>
                    <select id="gofast-lp-barrio-ayuda-destino" class="gofast-select-search" style="width:100%;" data-target-sector="#gofast-lp-sector-destino" data-hint="#gofast-lp-hint-ayuda-destino">
                        <option value="">Buscar barrio...</option>
                        <?php foreach ($barrios as $b): ?>
                            <option value="<?= (int) $b->id ?>" data-sector="<?= (int) $b->sector_id ?>"><?= esc_html(trim($b->nombre)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="gofast-lp-hint-ayuda-destino" style="margin:6px 0 0;font-size:12px;color:#666;"></p>
                </div>
            </div>

            <p id="gofast-lp-aviso-completo" style="display:none;margin:12px 0 0;padding:10px 12px;background:#fff3cd;border-left:4px solid #ffc107;font-size:13px;color:#856404;">
                La lista completa tiene decenas de miles de filas. <strong>Usa Descargar Excel (CSV)</strong> — el PDF se traba.
            </p>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
                <button type="submit" name="accion" value="ver"
                        style="background:#1a1a1a;color:#fff;padding:12px 20px;font-weight:600;border:none;border-radius:6px;cursor:pointer;">
                    👁 Vista previa
                </button>
                <button type="submit" name="accion" value="csv"
                        style="background:#0d6efd;color:#fff;padding:12px 20px;font-weight:600;border:none;border-radius:6px;cursor:pointer;">
                    📊 Descargar Excel (CSV)
                </button>
                <button type="submit" name="accion" value="descargar" formtarget="_blank"
                        style="background:#28a745;color:#fff;padding:12px 20px;font-weight:600;border:none;border-radius:6px;cursor:pointer;">
                    📥 PDF / Imprimir
                </button>
            </div>
        </form>

        <?php if (!empty($vista_previa)): ?>
            <div class="gofast-box" style="padding:0;overflow:hidden;margin-bottom:24px;">
                <div style="background:#f8f9fa;padding:12px 16px;border-bottom:1px solid #ddd;">
                    <strong>Vista previa</strong>
                </div>
                <?php if (!empty($vista_previa_truncada)): ?>
                    <div style="padding:12px 16px;background:#e7f1ff;border-bottom:1px solid #b6d4fe;font-size:13px;color:#084298;">
                        Mostrando las primeras 400 filas de <?= number_format($vista_previa_total, 0, ',', '.') ?>.
                        Para ver todo: <strong>Descargar Excel (CSV)</strong> (recomendado).
                    </div>
                <?php endif; ?>
                <div style="padding:16px;max-height:70vh;overflow:auto;background:#fff;">
                    <style><?= gofast_listado_precios_css() ?></style>
                    <?= $vista_previa ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
        @media (max-width:700px) {
            .gofast-admin-listado-precios .gofast-lp-fields-grid { grid-template-columns: 1fr !important; }
        }
    </style>

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
                if ($el.data('select2')) $el.select2('destroy');
                $el.select2({
                    placeholder: '🔍 Escribe para buscar...',
                    width: '100%',
                    allowClear: true,
                    minimumResultsForSearch: 0
                });
            });
        }

        function bindBarrioAyuda(selector) {
            if (!(window.jQuery && jQuery.fn.select2)) return;
            jQuery(selector).off('change.gofastLp').on('change.gofastLp', function() {
                const $el = jQuery(this);
                const $opt = $el.find('option:selected');
                const sectorId = $opt.data('sector');
                const barrioNombre = $opt.text().trim();
                const target = $el.data('target-sector');
                const hint = document.querySelector($el.data('hint'));
                if (sectorId && target) {
                    jQuery(target).val(String(sectorId)).trigger('change');
                    if (hint) {
                        hint.innerHTML = '✅ <strong>' + barrioNombre + '</strong> → <strong>' + (sectoresNombres[sectorId] || ('Sector ' + sectorId)) + '</strong>';
                        hint.style.color = '#155724';
                    }
                } else if (hint) {
                    hint.textContent = '';
                }
            });
        }

        function bindBarrioOrigenHint() {
            if (!(window.jQuery && jQuery.fn.select2)) return;
            jQuery('#gofast-lp-barrio-origen').off('change.gofastLpHint').on('change.gofastLpHint', function() {
                const $opt = jQuery(this).find('option:selected');
                const sectorId = $opt.data('sector');
                const hint = document.getElementById('gofast-lp-hint-barrio-origen');
                if (sectorId && hint) {
                    hint.innerHTML = 'Zona de este barrio: <strong>' + (sectoresNombres[sectorId] || ('Sector ' + sectorId)) + '</strong> (el precio usa esa zona)';
                    hint.style.color = '#555';
                } else if (hint) {
                    hint.textContent = '';
                }
            });
        }

        window.gofastLpToggleModo = function() {
            const modo = (document.querySelector('input[name="modo"]:checked') || {}).value || 'sector_ciudad';
            const showSectorOrigen = (modo === 'sector_ciudad' || modo === 'sector_sector');
            const showBarrioOrigen = (modo === 'barrio_sector' || modo === 'barrio_ciudad');
            const showSectorDestino = (modo === 'sector_sector' || modo === 'barrio_sector');
            const esCompleto = (modo === 'ciudad_completa');

            document.getElementById('gofast-lp-field-sector-origen').style.display = showSectorOrigen ? 'block' : 'none';
            document.getElementById('gofast-lp-field-barrio-origen').style.display = showBarrioOrigen ? 'block' : 'none';
            document.getElementById('gofast-lp-field-sector-destino').style.display = showSectorDestino ? 'block' : 'none';
            const aviso = document.getElementById('gofast-lp-aviso-completo');
            if (aviso) aviso.style.display = esCompleto ? 'block' : 'none';

            // Deshabilitar campos ocultos para que no contaminen el GET
            const so = document.getElementById('gofast-lp-sector-origen');
            const bo = document.getElementById('gofast-lp-barrio-origen');
            const sd = document.getElementById('gofast-lp-sector-destino');
            if (so) so.disabled = !showSectorOrigen;
            if (bo) bo.disabled = !showBarrioOrigen;
            if (sd) sd.disabled = !showSectorDestino;

            document.querySelectorAll('.gofast-lp-modo').forEach(function(label) {
                const radio = label.querySelector('input[name="modo"]');
                if (!radio) return;
                label.classList.toggle('is-active', radio.checked);
            });

            setTimeout(function() {
                initSelect2Lp();
                bindBarrioAyuda('#gofast-lp-barrio-ayuda-origen');
                bindBarrioAyuda('#gofast-lp-barrio-ayuda-destino');
                bindBarrioOrigenHint();
            }, 40);
        };

        function boot() {
            gofastLpToggleModo();
        }

        document.addEventListener('DOMContentLoaded', boot);
        if (document.readyState !== 'loading') boot();
    })();
    </script>
    <?php
    return ob_get_clean();
}
} // function_exists gofast_admin_listado_precios_shortcode

/**
 * Arma el listado expandido a Barrio → Barrio (PDF legible).
 * El precio se obtiene de tarifas sector → sector.
 */
if (!function_exists('gofast_listado_precios_armar')) {
function gofast_listado_precios_armar($wpdb, $modo, $sector_origen_id, $sector_destino_id, $barrio_origen_id, $sectores, $barrios_por_sector, $barrios_map) {
    $sectores_map = [];
    foreach ($sectores as $s) {
        $sectores_map[(int) $s->id] = $s;
    }

    $ordenar_barrios = function ($lista) {
        usort($lista, function ($a, $b) {
            return strcasecmp(trim($a->nombre), trim($b->nombre));
        });
        return $lista;
    };

    $todos_barrios = $ordenar_barrios(array_values($barrios_map));

    // --- Resolver orígenes (objetos barrio) y destinos ---
    $origenes = [];
    $destinos = [];
    $titulo = '';
    $subtitulo = '';

    if ($modo === 'ciudad_completa') {
        if (empty($todos_barrios)) {
            return ['error' => 'No hay barrios registrados.'];
        }
        @set_time_limit(180);
        $origenes = $todos_barrios;
        $destinos = $todos_barrios;
        $titulo = 'Listado completo de precios — Toda la ciudad';
        $subtitulo = 'Todos los barrios → todos los barrios (incluye mismo barrio)';
    } elseif ($modo === 'sector_ciudad' || $modo === 'sector_sector') {
        if ($sector_origen_id <= 0 || empty($sectores_map[$sector_origen_id])) {
            return ['error' => 'Selecciona el sector de origen.'];
        }
        if (empty($barrios_por_sector[$sector_origen_id])) {
            return ['error' => 'Esa zona de origen no tiene barrios registrados.'];
        }
        $origenes = $ordenar_barrios($barrios_por_sector[$sector_origen_id]);
        $nombres_o = array_map(function ($b) { return trim($b->nombre); }, $origenes);

        if ($modo === 'sector_sector') {
            if ($sector_destino_id <= 0 || empty($sectores_map[$sector_destino_id])) {
                return ['error' => 'Selecciona el sector de destino.'];
            }
            if (empty($barrios_por_sector[$sector_destino_id])) {
                return ['error' => 'Esa zona de destino no tiene barrios registrados.'];
            }
            $destinos = $ordenar_barrios($barrios_por_sector[$sector_destino_id]);
            $nombres_d = array_map(function ($b) { return trim($b->nombre); }, $destinos);
            $titulo = 'Listado de precios';
            $subtitulo = 'Desde: ' . implode(', ', $nombres_o) . ' → Hacia: ' . implode(', ', $nombres_d);
        } else {
            $destinos = $todos_barrios;
            $titulo = 'Listado de precios — A toda la ciudad';
            $subtitulo = 'Desde: ' . implode(', ', $nombres_o);
        }
    } elseif ($modo === 'barrio_ciudad' || $modo === 'barrio_sector') {
        if ($barrio_origen_id <= 0 || empty($barrios_map[$barrio_origen_id])) {
            return ['error' => 'Selecciona el barrio de origen.'];
        }
        $origenes = [$barrios_map[$barrio_origen_id]];
        $nombre_o = trim($origenes[0]->nombre);

        if ($modo === 'barrio_sector') {
            if ($sector_destino_id <= 0 || empty($sectores_map[$sector_destino_id])) {
                return ['error' => 'Selecciona el sector de destino.'];
            }
            if (empty($barrios_por_sector[$sector_destino_id])) {
                return ['error' => 'Esa zona de destino no tiene barrios registrados.'];
            }
            $destinos = $ordenar_barrios($barrios_por_sector[$sector_destino_id]);
            $nombres_d = array_map(function ($b) { return trim($b->nombre); }, $destinos);
            $titulo = 'Listado de precios — Desde ' . $nombre_o;
            $subtitulo = 'Hacia: ' . implode(', ', $nombres_d);
        } else {
            $destinos = $todos_barrios;
            $titulo = 'Listado de precios — Desde ' . $nombre_o . ' a toda la ciudad';
            $subtitulo = 'Hacia todos los barrios';
        }
    } else {
        return ['error' => 'Modo no válido.'];
    }

    // Mapa completo de tarifas
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

    $grupos = [];
    $total_filas = 0;
    $omitidas = 0;

    foreach ($origenes as $origen) {
        $oid = (int) $origen->id;
        $osector = (int) $origen->sector_id;
        $onombre = trim($origen->nombre);
        $filas = [];

        foreach ($destinos as $destino) {
            $did = (int) $destino->id;
            $dsector = (int) $destino->sector_id;
            $dnombre = trim($destino->nombre);
            $es_mismo_barrio = ($did === $oid);

            // Incluye mismo barrio: usa tarifa interna zona→zona
            if (!isset($tarifas_map[$osector][$dsector])) {
                $omitidas++;
                continue;
            }

            $filas[] = [
                'origen' => $onombre,
                'destino' => $dnombre,
                'precio' => $tarifas_map[$osector][$dsector],
                'mismo_sector' => ($osector === $dsector),
                'mismo_barrio' => $es_mismo_barrio,
            ];
        }

        usort($filas, function ($a, $b) {
            if (!empty($a['mismo_barrio']) && empty($b['mismo_barrio'])) return -1;
            if (empty($a['mismo_barrio']) && !empty($b['mismo_barrio'])) return 1;
            return strcasecmp($a['destino'], $b['destino']);
        });

        if (!empty($filas)) {
            $grupos[] = [
                'origen' => $onombre,
                'filas' => $filas,
            ];
            $total_filas += count($filas);
        }
    }

    if ($total_filas === 0) {
        return ['error' => 'No se encontraron tarifas para generar el listado.'];
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
}

if (!function_exists('gofast_listado_precios_css')) {
function gofast_listado_precios_css() {
    return '
        .gofast-lp-doc { padding: 24px; max-width: 800px; margin: 0 auto; }
        .gofast-lp-header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #F4C524; padding-bottom: 16px; }
        .gofast-lp-header .brand { font-size: 22px; font-weight: 800; letter-spacing: 0.5px; }
        .gofast-lp-header h1 { font-size: 16px; margin: 8px 0 4px; font-weight: 700; }
        .gofast-lp-header .meta { font-size: 12px; color: #555; }
        .gofast-lp-barrios { font-size: 11px; color: #666; margin: 8px 0 0; text-align: left; }
        .gofast-lp-section { margin-bottom: 18px; }
        .gofast-lp-section h2 {
            font-size: 13px;
            background: #1a1a1a;
            color: #F4C524;
            padding: 6px 10px;
            margin: 0 0 6px;
            border-radius: 4px;
        }
        .gofast-lp-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .gofast-lp-table td { padding: 4px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        .gofast-lp-table td.destino { width: auto; }
        .gofast-lp-table td.precio { width: 100px; text-align: right; font-weight: 700; white-space: nowrap; }
        .gofast-lp-table tr.mismo td { background: #fffbeb; }
        .gofast-lp-table tr.mismo-barrio td { background: #e8f5e9; font-weight: 700; }
        .gofast-lp-footer { margin-top: 24px; font-size: 11px; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 12px; }
    ';
}
}

if (!function_exists('gofast_listado_precios_html')) {
function gofast_listado_precios_html($resultado, $limite = 0) {
    $html = '<div class="gofast-lp-doc">';
    $html .= '<div class="gofast-lp-header">';
    $html .= '<div class="brand">GO FAST</div>';
    $html .= '<h1>' . esc_html($resultado['titulo']) . '</h1>';
    $html .= '<div class="meta">' . esc_html($resultado['subtitulo']) . '</div>';
    $html .= '<div class="meta">Generado: ' . esc_html($resultado['fecha']) . ' · '
        . (int) $resultado['total_filas'] . ' precio' . ((int) $resultado['total_filas'] === 1 ? '' : 's');
    if (!empty($resultado['omitidas'])) {
        $html .= ' · ' . (int) $resultado['omitidas'] . ' sin tarifa (omitidos)';
    }
    if ($limite > 0 && (int) $resultado['total_filas'] > $limite) {
        $html .= ' · vista parcial (primeras ' . (int) $limite . ')';
    }
    $html .= '</div></div>';

    $mostradas = 0;
    foreach ($resultado['grupos'] as $grupo) {
        if ($limite > 0 && $mostradas >= $limite) {
            break;
        }
        $html .= '<div class="gofast-lp-section">';
        $html .= '<h2>Desde: ' . esc_html($grupo['origen']) . '</h2>';
        $html .= '<table class="gofast-lp-table"><tbody>';
        foreach ($grupo['filas'] as $fila) {
            if ($limite > 0 && $mostradas >= $limite) {
                break;
            }
            $precio = '$' . number_format($fila['precio'], 0, ',', '.');
            $cls = '';
            if (!empty($fila['mismo_barrio'])) {
                $cls = ' class="mismo-barrio"';
            } elseif (!empty($fila['mismo_sector'])) {
                $cls = ' class="mismo"';
            }
            $etiqueta_destino = $fila['destino'];
            if (!empty($fila['mismo_barrio'])) {
                $etiqueta_destino .= ' (mismo barrio)';
            }
            $html .= '<tr' . $cls . '>';
            $html .= '<td class="destino">' . esc_html($fila['origen']) . ' → ' . esc_html($etiqueta_destino) . '</td>';
            $html .= '<td class="precio">' . esc_html($precio) . '</td>';
            $html .= '</tr>';
            $mostradas++;
        }
        $html .= '</tbody></table></div>';
    }

    $html .= '<div class="gofast-lp-footer">Go Fast · Listado de precios por barrio</div>';
    $html .= '</div>';

    return $html;
}
}

/**
 * Exporta CSV compatible con Excel (UTF-8 + BOM, separador ;).
 */
if (!function_exists('gofast_listado_precios_csv')) {
function gofast_listado_precios_csv($resultado) {
    $out = fopen('php://temp', 'r+');
    // BOM UTF-8 para que Excel abra tildes bien
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Origen', 'Destino', 'Precio'], ';');

    foreach ($resultado['grupos'] as $grupo) {
        foreach ($grupo['filas'] as $fila) {
            fputcsv($out, [
                $fila['origen'],
                $fila['destino'],
                (int) $fila['precio'],
            ], ';');
        }
    }

    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    return $csv;
}
}

if (!shortcode_exists('gofast_admin_listado_precios')) {
    add_shortcode('gofast_admin_listado_precios', 'gofast_admin_listado_precios_shortcode');
}

<?php
/***************************************************
 * GOFAST – SHORTCODE REGISTRO MULTIPLE DE NEGOCIOS
 ***************************************************/
function gofast_registro_negocio_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['gofast_user_id'])) {
        return "<div class='gofast-box'>Debes iniciar sesión para registrar tu negocio.</div>";
    }

    $user_id = intval($_SESSION['gofast_user_id']);

    /* Modo */
    $editing_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $add_new    = isset($_GET['add']);

    $negocio = null;

    /* Si edita, obtener datos */
    if ($editing_id) {
        $negocio = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM negocios_gofast WHERE id=%d AND user_id=%d",
            $editing_id, $user_id
        ));
    }

    /* Listado */
    $negocios = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM negocios_gofast WHERE user_id=%d ORDER BY id DESC",
        $user_id
    ));

    /* Para mostrar errores */
    $errores = [];
    
    // Mostrar errores si vienen de la redirección (si hay parámetros de error)
    if (isset($_GET['error'])) {
        $error_msg = sanitize_text_field($_GET['error']);
        if ($error_msg) {
            $errores[] = $error_msg;
        }
    }

    /* ======================
       Valores del FORM
    ====================== */
    $tipos_default = ["Restaurante", "Tienda", "Cafetería", "Papelería", "Farmacia", "Otro"];

    if ($negocio) {
        if (!in_array($negocio->tipo, $tipos_default)) {
            $tipo_val      = "Otro";
            $tipo_otro_val = esc_attr($negocio->tipo);
        } else {
            $tipo_val      = esc_attr($negocio->tipo);
            $tipo_otro_val = "";
        }
    } else {
        $tipo_val      = "";
        $tipo_otro_val = "";
    }

    $whatsapp_val = $negocio ? esc_attr($negocio->whatsapp) : '';

    $nombre_val = $negocio ? esc_attr($negocio->nombre) : '';
    $dir_val    = $negocio ? esc_attr($negocio->direccion_full) : '';
    $barrio_val = $negocio ? intval($negocio->barrio_id) : 0;

    $barrios = $wpdb->get_results("SELECT id, nombre FROM barrios ORDER BY nombre ASC");

    ob_start();
?>

<div class="gofast-checkout-wrapper">

    <?php if (!empty($errores)): ?>
        <div class="gofast-recargo-box" style="background:#ffe0e0;border-left-color:#ff3333;">
            <?php foreach ($errores as $e): ?>
                • <?= esc_html($e) ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($add_new || $editing_id): ?>
    <div class="gofast-box">
        <h3><?= $editing_id ? "Editar negocio" : "Registrar nuevo negocio" ?></h3>

        <form method="post">

            <label>Nombre del negocio</label>
            <input type="text" name="nombre_negocio" value="<?= $nombre_val ?>" required>

            <label>Tipo de negocio</label>
            <select name="tipo_negocio" id="tipo_negocio" class="gofast-select" required>
                <option value="">— Selecciona —</option>
                <?php foreach ($tipos_default as $t): ?>
                    <option value="<?= esc_attr($t) ?>" <?= $tipo_val === $t ? "selected" : "" ?>>
                        <?= esc_html($t) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div id="tipo_otro_wrapper" style="display:none;">
                <label>Especifica el tipo</label>
                <input type="text" name="tipo_otro" id="tipo_otro" 
                       value="<?= $tipo_otro_val ?>" placeholder="Ej: Barbería">
            </div>

            <label>Barrio</label>
            <select name="barrio_id" class="gofast-select" required>
                <option value="">— Seleccionar barrio —</option>
                <?php foreach ($barrios as $b): ?>
                    <option value="<?= $b->id ?>" <?= ($barrio_val == $b->id ? "selected" : "") ?>>
                        <?= esc_html($b->nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Dirección completa</label>
            <input type="text" name="direccion_full" value="<?= $dir_val ?>" required>

            <label>WhatsApp del negocio</label>
            <input type="number" name="whatsapp" value="<?= $whatsapp_val ?>" placeholder="Ej: 3001234567">

            <div class="gofast-btn-group" style="margin-top:20px;">
                <button name="gofast_guardar_negocio" class="gofast-btn-request">💾 Guardar</button>
                <a href="/mi-negocio" class="gofast-btn-action gofast-secondary">⬅ Cancelar</a>
            </div>

        </form>
    </div>

    <?php else: ?>

    <div class="gofast-box gofast-box-negocios">
        <a href="?add=1" class="gofast-btn-request gofast-btn-registrar-negocio" style="margin-bottom:16px;">
            <span class="gofast-btn-icon">➕</span>
            <span class="gofast-btn-text">Registrar nuevo negocio</span>
        </a>

        <div class="gofast-negocios-table-wrapper">
            <table class="gofast-negocios-table">

                <tr>
                    <th>Nombre</th>
                    <th>Dirección</th>
                    <th>Barrio</th>
                    <th>Acciones</th>
                </tr>

                <?php foreach ($negocios as $n): ?>
                <tr>
                    <td><?= esc_html($n->nombre) ?></td>
                    <td><?= esc_html($n->direccion_full) ?></td>
                    <td><?= esc_html($wpdb->get_var("SELECT nombre FROM barrios WHERE id={$n->barrio_id}")) ?></td>
                    <td class="gofast-actions">
                        <a href="?edit=<?= $n->id ?>" class="gofast-btn-edit">Editar</a>
                        <a href="?delete=<?= $n->id ?>" class="gofast-btn-delete"
                           onclick="return confirm('¿Eliminar este negocio?');">Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>

            </table>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php return ob_get_clean(); }

add_shortcode('gofast_registro_negocio', 'gofast_registro_negocio_shortcode');


/***************************************************
 * JS + SELECT2 + LÓGICA DEL CAMPO OTRO + Z-INDEX CORREGIDO
 ***************************************************/
add_action('wp_footer', function(){ ?>
<script>
(function(){

const normalize = s => s.toLowerCase().normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim();

/***************************************************
 *  Función para obtener z-index correcto
 ***************************************************/
function getSelect2ZIndex() {
    // Si el menú móvil está abierto, usar z-index más bajo
    if (document.body.classList.contains('gofast-menu-open')) {
        return 9998;
    }
    // Si no, usar z-index moderado para no quedar por encima de todo
    return 1000;
}

function initSelect2(container){
    if (!window.jQuery || !jQuery.fn.select2) return;

    const zIndex = getSelect2ZIndex();

    jQuery(container).find('.gofast-select').select2({
        placeholder: "🔍 Escribe para buscar...",
        width: '100%',
        dropdownParent: jQuery('body'),
        allowClear: true,
        minimumResultsForSearch: 0,
        selectOnClose: true,
        dropdownAutoWidth: true,
        dropdownCssClass: "gofast-select-down",
        language: {
            noResults: function() {
                return "No se encontraron resultados";
            },
            searching: function() {
                return "Buscando...";
            }
        },

        matcher: function(params, data){
            // Si no hay data, retornar null
            if (!data) return null;
            
            // Si es un optgroup (tiene children), dejarlo pasar para que Select2 lo maneje
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

            const term = normalize(params.term);
            if (!term) {
                data.matchScore = 0;
                return data;
            }

            const text = normalize(data.text);
            
            // PRIMERO: Verificar coincidencia exacta (ignorando mayúsculas y tildes)
            if (text === term) {
                data.matchScore = 10000;
                return data;
            }
            
            // SEGUNDO: Verificar si el texto comienza exactamente con el término
            if (text.indexOf(term) === 0) {
                data.matchScore = 9500;
                return data;
            }
            
            // Palabras comunes a ignorar en la búsqueda
            const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
            
            const searchWords = term.split(/\s+/).filter(Boolean).filter(word => {
                return word.length > 2 && !stopWords.includes(word);
            });
            
            // Detectar si el término de búsqueda parece ser completo (múltiples palabras o palabra larga)
            const isCompleteSearch = term.split(/\s+/).length >= 2 || term.length >= 10;
            
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
            
            // Si la búsqueda parece completa y no hay coincidencia exacta, ser más estricto
            if (isCompleteSearch && text !== term && text.indexOf(term) !== 0) {
                // Solo mostrar si el término completo está presente (aunque no al inicio)
                if (text.indexOf(term) === -1) {
                    // Verificar si al menos todas las palabras significativas están presentes
                    const allWordsPresent = searchWords.every(word => text.indexOf(word) !== -1);
                    if (!allWordsPresent) {
                        return null; // Filtrar si no están todas las palabras
                    }
                }
            }

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
        },

        sorter: results => results.sort((a,b)=> (b.matchScore||0)-(a.matchScore||0)),

        templateResult: function(data, container){
            // Manejar optgroups (no tienen id pero tienen children)
            if (!data || !data.text) {
                if (data && data.children) return data.text; // Retornar label del optgroup
                return data ? data.text : '';
            }
            
            // Si no tiene id, es un optgroup label, retornar sin modificar
            if (!data.id) return data.text;

            let originalText = data.text;
            
            // Obtener el término de búsqueda del campo activo
            let searchTerm = "";
            const $activeField = jQuery('.select2-container--open .select2-search__field');
            if ($activeField.length) {
                searchTerm = $activeField.val() || "";
            }
            
            if (!searchTerm || !searchTerm.trim()) {
                const $result = jQuery('<span>' + originalText + '</span>');
                if (data.matchScore !== undefined) {
                    $result.attr('data-match-score', data.matchScore);
                }
                return $result;
            }

            // Normalizar para búsqueda sin tildes
            const normalizedSearch = normalize(searchTerm);
            const normalizedText = normalize(originalText);
            
            // Dividir término en palabras significativas
            const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
            const searchWords = normalizedSearch.split(/\s+/).filter(Boolean).filter(word => {
                return word.length > 2 && !stopWords.includes(word);
            });
            
            // Si no hay palabras significativas, buscar el término completo
            const wordsToHighlight = searchWords.length > 0 ? searchWords : [normalizedSearch];
            
            // Encontrar coincidencias en el texto normalizado y mapear a texto original
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
                        const charNorm = normalize(originalText[i]);
                        if (normPos === searchPos) {
                            origStart = i;
                        }
                        normPos += charNorm.length;
                    }
                    
                    // Encontrar fin
                    if (origStart >= 0) {
                        normPos = searchPos;
                        for (let i = origStart; i < originalText.length; i++) {
                            const charNorm = normalize(originalText[i]);
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
                    // Agregar texto antes del rango
                    if (range.start > lastIndex) {
                        parts.push(originalText.substring(lastIndex, range.start));
                    }
                    
                    // Agregar texto resaltado
                    const matchText = originalText.substring(range.start, range.end);
                    parts.push('<span style="background-color:#F4C524;color:#000;font-weight:bold;padding:1px 2px;">' + 
                               matchText + '</span>');
                    
                    lastIndex = range.end;
                });
                
                // Agregar texto restante después del último rango
                if (lastIndex < originalText.length) {
                    parts.push(originalText.substring(lastIndex));
                }
                
                const result = parts.join('');
                
                // Crear elemento jQuery con el HTML renderizado correctamente
                const $result = jQuery('<span>').html(result);
                // Agregar atributo data con el score para poder filtrar después
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
        // Aplicar z-index dinámico cuando se abre
        setTimeout(function() {
            const currentZIndex = getSelect2ZIndex();
            const $container = jQuery(e.target).closest('.select2-container');
            const $dropdown = jQuery('.select2-dropdown');
            
            // Aplicar z-index moderado para todos los dropdowns
            const zIndex = 1000;
            
            if ($container.length) {
                $container.css('z-index', zIndex);
            }
            if ($dropdown.length) {
                $dropdown.css('z-index', zIndex);
            }
            
            // Asegurar que todos los dropdowns abiertos tengan z-index moderado
            jQuery('.select2-container--open').css('z-index', zIndex);
            jQuery('.select2-dropdown').css('z-index', zIndex);
            
            // Agregar listener para búsqueda dinámica y mejorar UX (especialmente móvil)
            const $searchField = $container.find('.select2-search__field');
            if ($searchField.length) {
                // Detectar si es móvil
                const isMobile = window.innerWidth <= 768;
                
                // Hacer el campo más visible y claro
                $searchField.attr('placeholder', isMobile ? '🔍 Escribe para buscar entre 5000+ barrios...' : '🔍 Escribe aquí para buscar...');
                $searchField.attr('autocomplete', 'off');
                $searchField.attr('inputmode', 'text'); // Mejor teclado en móvil
                $searchField.attr('spellcheck', 'false');
                
                // Estilos base
                const baseStyles = {
                    'outline': 'none',
                    'border': '2px solid #F4C524',
                    'border-radius': '8px'
                };
                
                // Estilos específicos para móvil
                if (isMobile) {
                    Object.assign(baseStyles, {
                        'font-size': '18px',
                        'padding': '16px 20px',
                        'min-height': '56px',
                        'border': '3px solid #F4C524',
                        'border-radius': '12px',
                        '-webkit-appearance': 'none',
                        '-moz-appearance': 'none',
                        'appearance': 'none'
                    });
                } else {
                    Object.assign(baseStyles, {
                        'font-size': '16px',
                        'padding': '12px 16px'
                    });
                }
                
                $searchField.css(baseStyles);
                
                // Remover listeners anteriores para evitar duplicados
                $searchField.off('input.select2-dynamic-search keyup.select2-dynamic-search focus.select2-dynamic-search');
                
                // Función para forzar actualización del dropdown
                const forceUpdate = function() {
                    const $select = jQuery(e.target);
                    if ($select.length && $select.data('select2')) {
                        const select2Instance = $select.data('select2');
                        // Forzar actualización del dropdown
                        if (select2Instance && select2Instance.dropdown) {
                            // Obtener el término de búsqueda actual
                            const searchTerm = $searchField.val() || '';
                            // Forzar búsqueda y actualización
                            select2Instance.dataAdapter.query({ term: searchTerm }, function(data) {
                                select2Instance.updateResults(data);
                                
                                // Después de actualizar resultados, filtrar si hay coincidencias exactas
                                setTimeout(function() {
                                    const $dropdown = jQuery('.select2-dropdown');
                                    const $results = $dropdown.find('.select2-results__options');
                                    const $items = $results.find('.select2-results__option[role="option"]:not(.select2-results__option--loading)');
                                    
                                    if ($items.length > 0) {
                                        // Verificar si hay coincidencias exactas (score 10000)
                                        let hasExactMatch = false;
                                        $items.each(function() {
                                            const $item = jQuery(this);
                                            const $span = $item.find('span[data-match-score]');
                                            if ($span.length) {
                                                const score = parseInt($span.attr('data-match-score') || '0', 10);
                                                if (score >= 10000) {
                                                    hasExactMatch = true;
                                                    return false; // break
                                                }
                                            }
                                        });
                                        
                                        if (hasExactMatch) {
                                            // Ocultar elementos con score < 9500
                                            $items.each(function() {
                                                const $item = jQuery(this);
                                                const $span = $item.find('span[data-match-score]');
                                                if ($span.length) {
                                                    const score = parseInt($span.attr('data-match-score') || '0', 10);
                                                    if (score < 9500) {
                                                        $item.hide();
                                                    } else {
                                                        $item.show();
                                                    }
                                                } else {
                                                    $item.show(); // Mostrar si no tiene score
                                                }
                                            });
                                        } else {
                                            // Si no hay exactas, verificar si hay muy cercanas (>= 9000)
                                            let hasVeryClose = false;
                                            $items.each(function() {
                                                const $item = jQuery(this);
                                                const $span = $item.find('span[data-match-score]');
                                                if ($span.length) {
                                                    const score = parseInt($span.attr('data-match-score') || '0', 10);
                                                    if (score >= 9000) {
                                                        hasVeryClose = true;
                                                        return false; // break
                                                    }
                                                }
                                            });
                                            
                                            if (hasVeryClose) {
                                                // Mostrar solo las muy cercanas (hasta 5)
                                                let count = 0;
                                                $items.each(function() {
                                                    const $item = jQuery(this);
                                                    const $span = $item.find('span[data-match-score]');
                                                    if ($span.length) {
                                                        const score = parseInt($span.attr('data-match-score') || '0', 10);
                                                        if (score >= 9000 && count < 5) {
                                                            $item.show();
                                                            count++;
                                                        } else {
                                                            $item.hide();
                                                        }
                                                    } else {
                                                        $item.hide(); // Ocultar si no tiene score
                                                    }
                                                });
                                            } else {
                                                // Mostrar todos (hasta 10)
                                                let count = 0;
                                                $items.each(function() {
                                                    if (count < 10) {
                                                        jQuery(this).show();
                                                        count++;
                                                    } else {
                                                        jQuery(this).hide();
                                                    }
                                                });
                                            }
                                        }
                                    }
                                }, 150);
                            });
                        }
                    }
                };
                
                // Agregar listeners para búsqueda dinámica
                $searchField.on('input.select2-dynamic-search', function() {
                    forceUpdate();
                });
                
                $searchField.on('keyup.select2-dynamic-search', function(e) {
                    // Actualizar en cada tecla presionada
                    setTimeout(forceUpdate, 10);
                });
                
                // Enfocar automáticamente el campo cuando se abre (más tiempo en móvil)
                setTimeout(function() {
                    $searchField.focus();
                    // En móvil, hacer scroll al campo si es necesario
                    if (isMobile) {
                        setTimeout(function() {
                            $searchField[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }, 200);
                    }
                }, isMobile ? 200 : 100);
            }
        }, 10);
    });
}

/******** CAMPO OTRO =============*/
document.addEventListener('DOMContentLoaded', function(){

    initSelect2('.gofast-checkout-wrapper');

    const tipoSelect  = document.getElementById("tipo_negocio");
    const wrapperOtro = document.getElementById("tipo_otro_wrapper");
    const inputOtro   = document.getElementById("tipo_otro");

    const tiposDefault = ["Restaurante","Tienda","Cafetería","Papelería","Farmacia","Otro"];

    function toggleOtro() {
        const valor = tipoSelect ? tipoSelect.value.trim() : "";

        if (valor === "") {
            wrapperOtro.style.display = "none";
            inputOtro.required = false;
            return;
        }

        if (valor === "Otro") {
            wrapperOtro.style.display = "block";
            inputOtro.required = true;
            return;
        }

        if (!tiposDefault.includes(valor)) {
            wrapperOtro.style.display = "block";
            inputOtro.required = true;
            return;
        }

        wrapperOtro.style.display = "none";
        inputOtro.required = false;
    }

    jQuery('#tipo_negocio').on('select2:select', toggleOtro);

    setTimeout(toggleOtro, 200);

    // Observar cambios en el menú móvil para ajustar z-index
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                const currentZIndex = getSelect2ZIndex();
                // Aplicar z-index moderado
                const zIndex = document.body.classList.contains('gofast-menu-open') ? 9998 : 1000;
                jQuery('.select2-container--open').each(function() {
                    const $select = jQuery(this).prev('select');
                    const isBarrioField = $select.length && $select.attr('name') === 'barrio_id';
                    jQuery(this).css('z-index', isBarrioField ? 1000 : zIndex);
                });
                jQuery('.select2-dropdown').each(function() {
                    jQuery(this).css('z-index', zIndex);
                });
            }
        });
    });
    
    // Asegurar z-index correcto cuando se hace scroll
    let scrollTimeout;
    window.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(function() {
            const zIndex = document.body.classList.contains('gofast-menu-open') ? 9998 : 1000;
            jQuery('.select2-container--open').each(function() {
                const $select = jQuery(this).prev('select');
                const isBarrioField = $select.length && $select.attr('name') === 'barrio_id';
                jQuery(this).css('z-index', isBarrioField ? 1000 : zIndex);
            });
            jQuery('.select2-dropdown').css('z-index', zIndex);
        }, 10);
    }, { passive: true });

    observer.observe(document.body, {
        attributes: true,
        attributeFilter: ['class']
    });

    // También cerrar Select2 cuando se abre el menú móvil
    const menuToggle = document.querySelector('.gofast-hamburger');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            // Si el menú se está abriendo, cerrar todos los Select2
            setTimeout(function() {
                if (document.body.classList.contains('gofast-menu-open')) {
                    jQuery('.select2-container--open').select2('close');
                }
            }, 50);
        });
    }
});

})();
</script>
<?php });


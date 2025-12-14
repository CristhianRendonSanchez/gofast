<?php
/*******************************************************
 * ✅ GOFAST — COTIZAR V2 (Selector inteligente con negocios)
 * Shortcode: [gofast_cotizar]
 * URL: /cotizar
 *******************************************************/
function gofast_cotizar_shortcode() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Recuperar cotización anterior
    $old_data = $_SESSION['gofast_last_quote'] ?? [
        'origen'   => '',
        'destinos' => []
    ];

    // Guardar si envían formulario
    if (isset($_POST['gofast_cotizar'])) {
        $_SESSION['gofast_last_quote'] = [
            'origen'   => sanitize_text_field($_POST['origen']),
            'destinos' => array_map('sanitize_text_field', $_POST['destino'] ?? []),
            'negocio_id' => isset($_POST['negocio_id']) ? intval($_POST['negocio_id']) : 0,
            'cliente_id' => isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0,
        ];
    }

    // Limpiar
    if (isset($_POST['gofast_reset'])) {
        unset($_SESSION['gofast_last_quote']);
        $old_data = ['origen' => '', 'destinos' => []];
    }

    /*******************************************************
     * 🔥 1. Negocios del usuario logueado Y todos los negocios si es mensajero/admin
     *******************************************************/
    $user_id = $_SESSION['gofast_user_id'] ?? 0;
    $rol = strtolower($_SESSION['gofast_user_rol'] ?? '');
    $es_mensajero_o_admin = ($rol === 'mensajero' || $rol === 'admin');

    $mis_negocios = [];
    if ($user_id) {
        $mis_negocios = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nombre, barrio_id 
             FROM negocios_gofast 
             WHERE user_id=%d 
             ORDER BY id DESC",
            $user_id
        ));
    }
    
    // Si es mensajero o admin, obtener TODOS los negocios
    $todos_negocios = [];
    if ($es_mensajero_o_admin) {
        $todos_negocios = $wpdb->get_results(
            "SELECT n.id, n.nombre, n.direccion_full, n.barrio_id, n.whatsapp, n.user_id,
                    u.nombre as cliente_nombre, u.telefono as cliente_telefono
             FROM negocios_gofast n
             INNER JOIN usuarios_gofast u ON n.user_id = u.id
             WHERE n.activo = 1 AND u.activo = 1
             ORDER BY n.nombre ASC"
        );
    }

    // Barrios prioritarios
    $barrios_prioritarios = [];
    foreach ($mis_negocios as $n) {
        if ($n->barrio_id && !in_array($n->barrio_id, $barrios_prioritarios, true)) {
            $barrios_prioritarios[] = $n->barrio_id;
        }
    }

    /*******************************************************
     * 🔥 2. Obtener TODOS los barrios y reordenarlos
     *******************************************************/
    $barrios_all = $wpdb->get_results("SELECT id, nombre FROM barrios ORDER BY nombre ASC");
    $barrios     = [];

    // Asegurar que siempre haya barrios (incluso si la consulta falla, usar array vacío)
    if (!$barrios_all) {
        $barrios_all = [];
    }

    // Primero los barrios de negocios
    foreach ($barrios_all as $b) {
        if (in_array($b->id, $barrios_prioritarios, true)) {
            $barrios[] = $b;
        }
    }
    // Luego los demás
    foreach ($barrios_all as $b) {
        if (!in_array($b->id, $barrios_prioritarios, true)) {
            $barrios[] = $b;
        }
    }
    
    // Si no hay barrios prioritarios, usar todos los barrios directamente
    if (empty($barrios_prioritarios) && !empty($barrios_all)) {
        $barrios = $barrios_all;
    }

    /*******************************************************
     * 🔥 3. AUTOPRELLENAR ORIGEN si no existe
     *******************************************************/
    if (empty($old_data['origen']) && !empty($barrios_prioritarios)) {
        $old_data['origen'] = $barrios_prioritarios[0];
    }

    // URL para el formulario
    $url_solicitar = esc_url( home_url('/solicitar-mensajero') );

    ob_start();
    ?>
    <div class="gofast-form">

        <?php if (!empty($old_data['origen'])): ?>
            <div class="gofast-box" style="background:#fff3cd;padding:10px;border-radius:6px;margin-bottom:12px;">
                ⚡ Se ha cargado tu última cotización o tu negocio registrado.
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo $url_solicitar; ?>" id="gofast-form">

            <!-- ORIGEN -->
            <div class="gofast-row">
                <div style="flex:1;">
                    <label><strong>Origen</strong></label>
                    <select name="origen" class="gofast-select" id="origen" required>
                        <option value="">Buscar dirección...</option>
                        <?php 
                        // Agregar opciones de negocios al select (todos los negocios si es mensajero/admin, solo del usuario si es cliente)
                        $negocios_a_mostrar = $es_mensajero_o_admin && !empty($todos_negocios) ? $todos_negocios : $mis_negocios;
                        
                        if (!empty($negocios_a_mostrar)): 
                            foreach ($negocios_a_mostrar as $n):
                                // Para todos los negocios, usar estructura completa
                                if ($es_mensajero_o_admin && isset($n->cliente_nombre)) {
                                    $barrio_nombre = $wpdb->get_var($wpdb->prepare(
                                        "SELECT nombre FROM barrios WHERE id = %d",
                                        $n->barrio_id
                                    ));
                                    $isSelected = ((string)$old_data['origen'] === (string)$n->barrio_id);
                        ?>
                            <option value="<?= esc_attr($n->barrio_id) ?>" 
                                    data-is-negocio="true"
                                    data-negocio-id="<?= esc_attr($n->id) ?>"
                                    data-cliente-id="<?= esc_attr($n->user_id) ?>"
                                    <?= $isSelected ? 'selected' : '' ?>>
                                🏪 <?= esc_html($n->nombre) ?> — <?= esc_html($barrio_nombre ?: 'Sin barrio') ?>
                            </option>
                        <?php 
                                } else {
                                    // Para negocios del usuario (estructura simple)
                                    $barrio_nombre = $wpdb->get_var(
                                        $wpdb->prepare("SELECT nombre FROM barrios WHERE id=%d", $n->barrio_id)
                                    );
                                    $isSelected = ((string)$old_data['origen'] === (string)$n->barrio_id);
                        ?>
                            <option value="<?= esc_attr($n->barrio_id) ?>" 
                                    data-is-negocio="true"
                                    data-negocio-id="<?= esc_attr($n->id) ?>"
                                    data-cliente-id="<?= esc_attr($n->user_id) ?>"
                                    <?= $isSelected ? 'selected' : '' ?>>
                                🏪 <?= esc_html($n->nombre) ?> — <?= esc_html($barrio_nombre) ?>
                            </option>
                        <?php 
                                }
                            endforeach;
                        endif; 
                        ?>
                        <?php if (!empty($barrios)): ?>
                            <?php foreach ($barrios as $b): ?>
                                <option value="<?= esc_attr($b->id) ?>"
                                    <?= ((string)$old_data['origen'] === (string)$b->id ? 'selected' : '') ?>>
                                    <?= esc_html($b->nombre) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Fallback: mostrar todos los barrios directamente si $barrios está vacío -->
                            <?php 
                            $barrios_fallback = $wpdb->get_results("SELECT id, nombre FROM barrios ORDER BY nombre ASC");
                            if (!empty($barrios_fallback)): 
                            ?>
                                <?php foreach ($barrios_fallback as $b): ?>
                                    <option value="<?= esc_attr($b->id) ?>"
                                        <?= ((string)$old_data['origen'] === (string)$b->id ? 'selected' : '') ?>>
                                        <?= esc_html($b->nombre) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- PRIMER DESTINO -->
                <div style="flex:1;">
                    <label><strong>Primer destino</strong></label>
                    <select name="destino[]" class="gofast-select" id="destino-principal" required>
                        <option value="">Buscar dirección...</option>
                        <?php foreach ($barrios as $b): ?>
                            <option value="<?= esc_attr($b->id) ?>"
                                <?= in_array($b->id, $old_data['destinos'], true) ? 'selected' : '' ?>>
                                <?= esc_html($b->nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- DESTINOS ADICIONALES -->
            <div class="gofast-destinos-grid">
                <div class="gofast-destinos-label">
                    <label><strong>Destinos adicionales</strong></label>
                </div>

                <div id="destinos-wrapper">
                    <div id="destinos-extra"></div>
                    <button type="button" id="btn-add-destino"
                            class="gofast-add-button" onclick="addDestino()">
                        ➕ Agregar destino adicional
                    </button>
                </div>
            </div>

            <!-- TEMPLATE -->
            <template id="tpl-destino">
                <div class="gofast-destino-item">
                    <select name="destino[]" class="gofast-select" required>
                        <option value="">Buscar dirección...</option>
                        <?php foreach ($barrios as $b): ?>
                            <option value="<?= esc_attr($b->id) ?>">
                                <?= esc_html($b->nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="gofast-remove" onclick="removeDestino(this)">❌</button>
                </div>
            </template>

            <div class="gofast-btn-group">
                <button type="submit" name="gofast_cotizar"
                        class="gofast-submit" id="btn-submit">
                    Cotizar 🚀
                </button>
            </div>

            <div id="gofast-loading"
                 style="display:none;text-align:center;margin-top:10px;">
                <div class="loader"></div>
                <p>Procesando tu solicitud...</p>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gofast_cotizar', 'gofast_cotizar_shortcode');

/*******************************************************
 * ✅ JS: Select2 + UX Mejorado
 *******************************************************/
add_action('wp_footer', function () { ?>
<script>
(function(){

  /***************************************************
   *  Normalizador (quita tildes)
   ***************************************************/
  const normalize = s => (s || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .trim();

  /***************************************************
   *  MATCHER EXCLUSIVO PARA ORIGEN (con optgroups)
   ***************************************************/
  function matcherOrigen(params, data){
    // Si no hay data, retornar null
    if (!data) return null;
    
    // Si es un optgroup (tiene children), verificar si tiene hijos que coincidan
    if (data.children && Array.isArray(data.children)) {
      // Si no hay búsqueda, mostrar el optgroup completo
      if (!params.term || !params.term.trim()) {
        return data;
      }
      
      // Si hay búsqueda, verificar si algún hijo coincide
      const term = normalize(params.term);
      const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
      const searchWords = term.split(/\s+/).filter(Boolean).filter(word => {
        return word.length > 2 && !stopWords.includes(word);
      });
      
      const hasMatchingChild = data.children.some(child => {
        if (!child || !child.text || !child.id) return false;
        const childText = normalize(child.text);
        
        // Si no hay palabras significativas, buscar el término completo
        if (searchWords.length === 0) {
          return childText.indexOf(term) !== -1;
        }
        
        // Verificar que al menos una palabra significativa coincida
        return searchWords.some(word => {
          if (word.length <= 2) {
            return childText.split(/\s+/).some(textWord => textWord.indexOf(word) === 0);
          }
          return childText.indexOf(word) !== -1;
        });
      });
      
      // Solo mostrar el optgroup si tiene hijos que coincidan
      return hasMatchingChild ? data : null;
    }
    
    // Si no tiene id, es un optgroup label o separador
    if (!data.id) {
      // Sin búsqueda, mostrar labels
      if (!params.term || !params.term.trim()) {
        return data;
      }
      // Con búsqueda, ocultar labels
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
    
    // Palabras comunes a ignorar en la búsqueda
    const stopWords = ['las', 'los', 'la', 'el', 'de', 'del', 'en', 'un', 'una', 'y', 'o'];
    
    const searchWords = term.split(/\s+/).filter(Boolean).filter(word => {
      return word.length > 2 && !stopWords.includes(word);
    });
    
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

    // Sistema de puntuación
    let score = 0;
    
    const textWithoutStopWords = text.split(/\s+/).filter(w => !stopWords.includes(w)).join(' ');
    const termWithoutStopWords = searchWords.join(' ');
    
    if (textWithoutStopWords === termWithoutStopWords) {
      score = 10000;
    } else if (textWithoutStopWords.indexOf(termWithoutStopWords) === 0) {
      score = 9000;
    } else if (textWithoutStopWords.indexOf(termWithoutStopWords) !== -1) {
      score = 8000;
    } else if (searchWords.some(word => text.indexOf(word) === 0)) {
      score = 7000;
    } else if (text.indexOf(term) !== -1) {
      score = 6000;
    } else {
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
  }

  /***************************************************
   *  MATCHER UNIFICADO (para origen y destinos)
   *  Maneja optgroups pero con lógica de búsqueda simple
   ***************************************************/
  function matcherDestinos(params, data){
    // Si no hay data, retornar null
    if (!data) return null;
    
    // Si es un optgroup (tiene children), dejarlo pasar para que Select2 lo maneje
    // Select2 procesará los hijos individualmente con este mismo matcher
    if (data.children && Array.isArray(data.children)) {
      return data;
    }
    
    // Si no tiene id, es un optgroup label o separador
    // Sin búsqueda, mostrarlo; con búsqueda, ocultarlo
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
  }

  /***************************************************
   *  INIT SELECT2 (UNIFICADO + ALWAYS DOWN)
   ***************************************************/
  function initSelect2(container){
    if (window.jQuery && jQuery.fn.select2) {

      jQuery(container).find('.gofast-select').each(function() {
        // Evitar inicializar dos veces
        if (jQuery(this).data('select2')) {
          return;
        }
        
        // Usar el mismo matcher para todos (origen y destinos)
        // La presentación de optgroups se mantiene en el HTML
        jQuery(this).select2({
          placeholder: "🔍 Escribe para buscar dirección...",
          width: '100%',
          dropdownParent: jQuery('body'),       // 👉 dropdown en <body>
          allowClear: true,
          minimumResultsForSearch: 0,  // SIEMPRE mostrar campo de búsqueda
          selectOnClose: true,
          dropdownAutoWidth: true,
          dropdownCssClass: "gofast-select-down",
          // Forzar que Select2 procese optgroups correctamente
          templateSelection: function(data) {
            return data.text || data.id;
          },

        // Usar el mismo matcher para todos (simplificado, igual que destino)
        matcher: matcherDestinos,

        sorter: function(results){
          return results.sort(function(a,b){
            return (b.matchScore || 0) - (a.matchScore || 0);
          });
        },
        

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
              // Construir texto normalizado carácter por carácter para mapear correctamente
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
          const $select = jQuery(this);
          const $container = $select.next('.select2-container');
          
          // FORZAR que el dropdown siempre abra hacia abajo
          // Usar requestAnimationFrame para asegurar que se ejecute después de que Select2 posicione el dropdown
          requestAnimationFrame(function() {
            // Remover clase --above si existe y agregar --below
            $container.removeClass('select2-container--above').addClass('select2-container--below');
            
            // Obtener el dropdown y forzar posición hacia abajo
            const $dropdown = jQuery('.select2-dropdown');
            if ($dropdown.length) {
              // Forzar posición hacia abajo - el CSS ya maneja esto, pero lo reforzamos aquí
              $dropdown.css({
                'top': '',
                'bottom': 'auto',
                'margin-top': '4px',
                'margin-bottom': '0',
                'position': 'absolute',
                'transform': 'none'
              });
              
              // Asegurar que el contenedor tenga la clase correcta
              $container.removeClass('select2-container--above').addClass('select2-container--below');
            }
          });
          
          // Asegurar que el campo de búsqueda sea visible
          setTimeout(function() {
            const $dropdown = jQuery('.select2-dropdown');
            const $searchContainer = $dropdown.find('.select2-search--dropdown');
            const $searchField = $searchContainer.find('.select2-search__field');
            
            if ($searchContainer.length) {
              $searchContainer.css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
              });
            }
            
            if ($searchField.length) {
              $searchField.css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
              });
              
              // Enfocar el campo
              setTimeout(function() {
                $searchField.focus();
              }, 100);
              
              // Forzar actualización dinámica al escribir
              $searchField.on('input.select2-dynamic', function() {
                const select2Instance = jQuery(e.target).data('select2');
                if (select2Instance) {
                  const term = jQuery(this).val() || '';
                  select2Instance.dataAdapter.query({ term: term }, function(data) {
                    select2Instance.updateResults(data);
                    
                    // Después de actualizar resultados, filtrar si hay coincidencias exactas
                    setTimeout(function() {
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
              });
            }
          }, 50);
        });
      }); // Cerrar .each()
    }
  }

  /***************************************************
   *  Añadir y remover destino
   ***************************************************/
  function addDestino(){
    const tpl = document.getElementById('tpl-destino');
    const cont = document.getElementById('destinos-extra');
    const btn  = document.getElementById('btn-add-destino');

    cont.appendChild(tpl.content.cloneNode(true));
    cont.parentNode.appendChild(btn);

    initSelect2('#destinos-extra');
  }
  function removeDestino(btn){
    btn.parentElement.remove();
  }

  window.addDestino    = addDestino;
  window.removeDestino = removeDestino;

  /***************************************************
   *  Validación UX
   ***************************************************/
  document.addEventListener('DOMContentLoaded', function(){

    initSelect2('.gofast-form');

    const form      = document.getElementById('gofast-form');
    const submitBtn = document.getElementById('btn-submit');
    const loader    = document.getElementById('gofast-loading');

    if (!form) return;

    // Manejar cambio del select de origen para agregar campos hidden de negocio
    const origenSelect = document.getElementById('origen');
    if (origenSelect) {
      origenSelect.addEventListener('change', function() {
        // Eliminar campos hidden anteriores
        const existingNegocioId = document.getElementById('hidden-negocio-id');
        const existingClienteId = document.getElementById('hidden-cliente-id');
        if (existingNegocioId) existingNegocioId.remove();
        if (existingClienteId) existingClienteId.remove();
        
        // Obtener la opción seleccionada
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption) {
          const isNegocio = selectedOption.getAttribute('data-is-negocio') === 'true';
          const negocioId = selectedOption.getAttribute('data-negocio-id');
          const clienteId = selectedOption.getAttribute('data-cliente-id');
          
          if (isNegocio && negocioId) {
            // Agregar campos hidden para negocio_id y cliente_id
            const negocioInput = document.createElement('input');
            negocioInput.type = 'hidden';
            negocioInput.name = 'negocio_id';
            negocioInput.id = 'hidden-negocio-id';
            negocioInput.value = negocioId;
            form.appendChild(negocioInput);
            
            if (clienteId) {
              const clienteInput = document.createElement('input');
              clienteInput.type = 'hidden';
              clienteInput.name = 'cliente_id';
              clienteInput.id = 'hidden-cliente-id';
              clienteInput.value = clienteId;
              form.appendChild(clienteInput);
            }
          }
        }
      });
      
      // Ejecutar al cargar la página si ya hay una opción seleccionada
      if (origenSelect.value) {
        origenSelect.dispatchEvent(new Event('change'));
      }
    }

    form.addEventListener('submit', function(e){
      const origen = document.getElementById('origen').value;
      const destino = document.getElementById('destino-principal').value;

      if (!origen || !destino){
        e.preventDefault();
        alert("Debes seleccionar origen y al menos un destino ⚠️");
        return false;
      }

      submitBtn.disabled   = true;
      loader.style.display = 'block';
    });
  });

})();
</script>
<?php });


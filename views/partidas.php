<?php
// views/partidas.php
$role = $_SESSION['user_role'];
$isSuperAdmin = ($role == 1);
$isAcademy = ($role == 2);
$isStudent = ($role == 6); // Definir si es alumno
?>

<div id="toast"></div>

<?php if (!$isStudent): ?>
<div id="createGameModal" class="modal">
    <div class="modal-content modal-xl">
        <h3 class="mb-4"><?php echo __('game_modal_title'); ?></h3>
        <form id="formCreateGame" style="display:flex; flex-direction:column; height:100%;">
            <input type="hidden" name="action" value="crear">
            
            <div class="modal-form-grid" style="margin-bottom: 1rem;">
                <div>
                    <label class="block text-muted mb-2"><?php echo __('game_form_name'); ?> <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" placeholder="Ej: Repaso T1" required>
                </div>
                <div>
                    <label class="block text-muted mb-2"><?php echo __('game_form_mode'); ?></label>
                    <select name="id_modo" class="form-control">
                        <option value="1"><?php echo __('game_mode_quiz'); ?></option>
                    </select>
                </div>
            </div>

            <?php if ($isSuperAdmin || $isAcademy): ?>
            <div class="mb-4" style="background: var(--bg-body); padding: 10px; border: 1px solid var(--border-color); border-radius: var(--radius);">
                <small class="text-muted"><strong><?php echo __('game_assign_owner'); ?>:</strong></small>
                <select name="target_user_id" id="targetUserSelect" class="form-control" style="margin-top:5px;">
                    <option value=""><?php echo __('game_loading_users'); ?></option>
                </select>
            </div>
            <?php endif; ?>

            <div class="builder-container">
                <div class="builder-col">
                    <div class="builder-header"><?php echo __('game_builder_library'); ?></div>
                    <div style="padding:10px; background:#f8fafc; border-bottom:1px solid var(--border-color); display:flex; gap:5px; flex-wrap:wrap;">
                        <input type="text" id="filterSearch" class="form-control" placeholder="<?php echo __('game_filter_text'); ?>" onkeyup="debounceQFilter()" style="font-size:0.85rem;">
                        <input type="text" id="filterSection" class="form-control" placeholder="<?php echo __('game_filter_section'); ?>" onkeyup="debounceQFilter()" style="font-size:0.85rem;">
                    </div>
                    <div id="sourceList" class="builder-list" ondragover="allowDrop(event)" ondrop="drop(event, 'source')">
                        <p class="text-center text-muted mt-3"><?php echo __('game_loading_users'); ?></p>
                    </div>
                </div>

                <div class="builder-col" style="border-color: var(--primary);">
                    <div class="builder-header" style="color:var(--primary);">
                        <?php echo __('game_builder_selected'); ?>
                        <span class="text-white" style="float:right; font-size:0.8rem; background:var(--primary); padding:2px 8px; border-radius:10px;" id="countSelected">0</span>
                    </div>
                    <div id="targetList" class="builder-list" ondragover="allowDrop(event)" ondrop="drop(event, 'target')">
                        <div class="text-center text-muted mt-5" id="emptyMsg"><?php echo __('game_builder_drag'); ?></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer-actions">
                <button type="button" class="btn-icon btn-modal-cancel" onclick="closeModals()"><?php echo __('key_btn_cancel'); ?></button>
                <button type="submit" class="btn-primary"><?php echo __('game_btn_save_game'); ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div id="playersModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3 class="mb-4"><?php echo __('game_players_title'); ?></h3>
        <div id="playersListContent" style="max-height: 400px; overflow-y: auto; margin-bottom: 1rem;"></div>
        <div class="text-right">
            <button class="btn-primary" onclick="closeModals()">Cerrar</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="dashboard-header" style="margin-bottom: 1rem;">
        <h2 class="welcome-title">
            <i class="fa-solid fa-gamepad"></i> 
            <?php echo ($role == 6) ? 'Mi Historial' : __('game_management'); ?>
        </h2>
        
        <div class="header-actions">
            <?php if($role == 6): ?>
                <a href="play/index.php" target="_blank" class="btn-primary" style="text-decoration: none;">
                    <i class="fa-solid fa-gamepad"></i> Unirse a Partida
                </a>
            <?php else: ?>
                <button class="btn-icon" title="<?php echo __('btn_export_pdf'); ?>" onclick="exportPartidasPDF()">
                    <i class="fa-solid fa-file-pdf text-danger" style="font-size: 1.2rem;"></i>
                </button>
                <button class="btn-primary" onclick="openCreateGameModal()">
                    <i class="fa-solid fa-plus"></i> 
                    <span><?php echo __('game_modal_title'); ?></span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if($isAcademy || $isSuperAdmin || in_array($role, [3,4])): ?>
    <div class="quick-filters-bar">
        <span class="quick-filter-label">
            <i class="fa-solid fa-bolt"></i> <?php echo __('quick_filters_title'); ?>:
        </span>
        
        <?php if($isAcademy || $isSuperAdmin): ?>
            <button class="btn-quick-filter" onclick="applyGameQuick('live', this)">
                <i class="fa-solid fa-satellite-dish"></i> <?php echo __('qf_games_live'); ?>
            </button>
            <button class="btn-quick-filter" onclick="applyGameQuick('quality', this)">
                <i class="fa-solid fa-users-viewfinder"></i> <?php echo __('qf_games_quality'); ?>
            </button>
        <?php endif; ?>

        <?php if(in_array($role, [3,4])): ?>
            <button class="btn-quick-filter" onclick="applyGameQuick('review', this)">
                <i class="fa-solid fa-calendar-check"></i> <?php echo __('qf_quarterly_review'); ?>
            </button>
        <?php endif; ?>
        
        <button class="btn-quick-filter" onclick="applyGameQuick('reset', this)" title="<?php echo __('qf_reset'); ?>">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <?php endif; ?>

    <div class="search-panel">
        <div class="search-bar-wrapper">
            <input type="text" id="pSearch" class="form-control" placeholder="<?php echo __('key_search_placeholder'); ?>" onkeyup="debounceGameLoad()">
            <button class="btn-icon" style="border:1px solid var(--border-color);" onclick="toggleAdvancedSearch(this)" title="<?php echo __('key_filter_advanced_title'); ?>">
                <i class="fa-solid fa-filter"></i>
            </button>
        </div>
        
        <div class="advanced-search"> 
            <div>
                <label class="block text-muted mb-2"><?php echo __('key_users_filter_status'); ?></label>
                <select id="pStatus" class="form-control" onchange="loadGames()">
                    <option value="">(<?php echo __('key_filter_all'); ?>)</option>
                    <option value="active">Activas (Sala/Jugando)</option>
                    <option value="finalizada"><?php echo __('game_js_finished'); ?></option>
                </select>
            </div>
            <div>
                <label class="block text-muted mb-2"><?php echo __('key_filter_date_from'); ?></label>
                <input type="date" id="pDateFrom" class="form-control" onchange="loadGames()">
            </div>
            <div>
                <label class="block text-muted mb-2"><?php echo __('key_filter_date_to'); ?></label>
                <input type="date" id="pDateTo" class="form-control" onchange="loadGames()">
            </div>
        </div>
    </div>

    <div id="gamesGrid" class="dashboard-grid">
        <p class="text-center text-muted col-span-full"><?php echo __('key_js_loading'); ?></p>
    </div>
</div>

<script>
// Configuración Global
const isSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
const isAcademy = <?php echo $isAcademy ? 'true' : 'false'; ?>;
const isStudent = <?php echo $isStudent ? 'true' : 'false'; ?>;
const currentUserId = <?php echo $_SESSION['user_id']; ?>;

// Variables de Estado
let qFilterTimeout;
let gameLoadTimeout;
let allQuestions = []; 
let selectedIds = new Set();
let gameState = { minPlayers: 0 }; 

// Traducciones
const LANG_FINISHED = <?php echo json_encode(__('game_js_finished')); ?>;
const LANG_PROJECTOR = <?php echo json_encode(__('game_js_projector')); ?>;
const LANG_LAUNCH = <?php echo json_encode(__('game_js_launch')); ?>;
const LANG_NO_RESULTS = <?php echo json_encode(__('key_js_no_results')); ?>;
const LANG_PLAYERS_TITLE = <?php echo json_encode(__('game_players_title')); ?>;
const LANG_LOADING = <?php echo json_encode(__('game_loading_users')); ?>;
const LANG_EMPTY = <?php echo json_encode(__('game_players_empty')); ?>;
const LANG_CONFIRM_LAUNCH = <?php echo json_encode(__('game_js_confirm_launch')); ?>;
const LANG_CONFIRM_DELETE = <?php echo json_encode(__('game_js_confirm_delete')); ?>;
const LANG_ERROR_NO_Q = <?php echo json_encode(__('game_js_error_no_q')); ?>;
const LANG_DRAG_MSG = <?php echo json_encode(__('game_builder_drag')); ?>;

document.addEventListener('DOMContentLoaded', () => {
    loadGames();
    if(!isStudent) {
        loadAllQuestionsForBuilder(); 
        if(isSuperAdmin || isAcademy) loadUsersForSelects();
    }
    
    // Auto-refresco
    setInterval(() => {
        if(!document.getElementById('pSearch').value) loadGames(true);
    }, 10000); 
});

// UI Helpers
function toggleAdvancedSearch(button) {
    const searchPanel = button.closest('.search-panel');
    if (searchPanel) {
        const advancedSearch = searchPanel.querySelector('.advanced-search');
        advancedSearch.classList.toggle('open');
    }
}

function debounceGameLoad() { clearTimeout(gameLoadTimeout); gameLoadTimeout = setTimeout(loadGames, 300); }

// Filtros Rápidos
function applyGameQuick(type, btn) {
    document.querySelectorAll('.btn-quick-filter').forEach(b => b.classList.remove('active'));
    if(btn) btn.classList.add('active');

    document.getElementById('pSearch').value = '';
    document.getElementById('pStatus').value = '';
    document.getElementById('pDateFrom').value = '';
    document.getElementById('pDateTo').value = '';
    gameState.minPlayers = 0;

    if (type === 'live') document.getElementById('pStatus').value = 'active';
    else if (type === 'review') {
        const d = new Date(); d.setDate(d.getDate() - 90);
        document.getElementById('pDateFrom').value = d.toISOString().split('T')[0];
        document.getElementById('pDateTo').value = new Date().toISOString().split('T')[0];
    }
    else if (type === 'quality') {
        const num = prompt("<?php echo __('prompt_min_players'); ?>", "20");
        if(num) { gameState.minPlayers = parseInt(num); document.getElementById('pStatus').value = 'finalizada'; }
        else { document.querySelectorAll('.btn-quick-filter').forEach(b => b.classList.remove('active')); return; }
    }
    loadGames();
}

// Carga de Partidas (Renderizado de Tarjetas)
async function loadGames(isAutoRefresh = false) {
    const grid = document.getElementById('gamesGrid');
    const search = document.getElementById('pSearch').value;
    const status = document.getElementById('pStatus').value;
    const dateFrom = document.getElementById('pDateFrom').value;
    const dateTo = document.getElementById('pDateTo').value;

    const params = new URLSearchParams({ 
        action: 'listar', search, status, date_from: dateFrom, date_to: dateTo, min_players: gameState.minPlayers
    });

    try {
        const res = await fetch(`api/partidas.php?${params}`);
        const json = await res.json();
        
        if(!json.success || json.data.length === 0) {
            grid.innerHTML = `<div class="text-center text-muted" style="grid-column:1/-1; padding:2rem;">${LANG_NO_RESULTS}</div>`; 
            return;
        }
        
        grid.innerHTML = '';
        json.data.forEach(p => {
            let btnAction = '';
            let displayState = p.estado;
            let statusClass = 'st-creada';
            
            if(p.estado === 'sala_espera') { displayState = 'En Sala'; statusClass = 'st-sala_espera'; }
            else if(p.estado === 'jugando') { displayState = 'Jugando'; statusClass = 'st-jugando'; }
            else if(p.estado === 'finalizada') { displayState = LANG_FINISHED; statusClass = 'st-finalizada'; }

            // Lógica de botones según rol
            if (!isStudent) {
                if(p.estado === 'finalizada') {
                    btnAction = `<button class="btn-icon" disabled style="opacity:0.5; cursor:default;"><i class="fa-solid fa-flag-checkered"></i></button>`;
                } else if (p.estado === 'jugando' || p.estado === 'sala_espera') {
                    btnAction = `<button class="btn-primary" style="background:#10b981;" onclick="openProjector('${p.codigo_pin}')" title="${LANG_PROJECTOR}"><i class="fa-solid fa-tv"></i></button>`;
                } else {
                    btnAction = `<button class="btn-primary" onclick="launchGame(${p.id_partida}, '${p.codigo_pin}')" title="${LANG_LAUNCH}"><i class="fa-solid fa-rocket"></i></button>`;
                }
            } else {
                // Para alumnos, quizás solo ver info o nada en btnAction
                btnAction = `<span style="font-size:0.8rem; color:var(--text-muted);">${displayState}</span>`;
            }

            const fecha = new Date(p.fecha_inicio).toLocaleDateString();
            
            // Botones secundarios (Eliminar solo si no es alumno)
            let secondaryBtns = `<button class="btn-icon" onclick="viewPlayers(${p.id_partida})" title="${LANG_PLAYERS_TITLE}"><i class="fa-solid fa-users"></i></button>`;
            if (!isStudent) {
                secondaryBtns += `<button class="btn-icon text-danger" onclick="deleteGame(${p.id_partida})"><i class="fa-solid fa-trash"></i></button>`;
            }

            grid.innerHTML += `
            <div class="card game-card">
                <div class="card-actions">
                    ${secondaryBtns}
                </div>
                <span class="status-badge ${statusClass}">${displayState}</span>
                <h3 style="margin:10px 0 5px; color:var(--primary); font-size:1.1rem;">${p.nombre_partida}</h3>
                <div class="pin-display">PIN: ${p.codigo_pin}</div>
                <div style="margin-top:10px; font-size:0.85rem; color:var(--text-muted);">
                    <div><i class="fa-solid fa-calendar"></i> ${fecha}</div>
                    <div><i class="fa-solid fa-list-ol"></i> ${p.total_preguntas} Pregs | <i class="fa-solid fa-user"></i> ${p.total_jugadores} Jugs</div>
                    <div style="font-size:0.8rem; margin-top:2px;">Creado por: ${p.nombre_creador}</div>
                </div>
                <div class="card-footer-action">${btnAction}</div>
            </div>`;
        });
    } catch(e) { console.error(e); }
}

// Lógica Builder (Drag & Drop) - Solo se activa si NO es estudiante
async function loadAllQuestionsForBuilder() {
    try {
        const res = await fetch('api/preguntas.php?action=list&limit=1000'); 
        const json = await res.json();
        if(json.data) { allQuestions = json.data; renderSourceList(); }
    } catch(e) {}
}
function debounceQFilter() { clearTimeout(qFilterTimeout); qFilterTimeout = setTimeout(renderSourceList, 300); }
function renderSourceList() {
    const list = document.getElementById('sourceList');
    if(!list) return; // Si es alumno no existe el elemento
    const txt = document.getElementById('filterSearch').value.toLowerCase();
    const sec = document.getElementById('filterSection').value.toLowerCase();
    list.innerHTML = '';
    allQuestions.forEach(q => {
        if(selectedIds.has(String(q.id_pregunta))) return;
        if(txt && !q.texto.toLowerCase().includes(txt)) return;
        if(sec && (!q.seccion || !q.seccion.toLowerCase().includes(sec))) return;
        const card = createCardElement(q, 'source');
        list.appendChild(card);
    });
}
function createCardElement(q, type) {
    const div = document.createElement('div');
    div.className = 'q-card'; div.draggable = true; div.dataset.id = q.id_pregunta; div.id = `q-${q.id_pregunta}`;
    div.innerHTML = `<div class="q-info" style="font-size:0.9rem;"><strong>${q.texto}</strong><div style="font-size:0.75rem; color:#666; margin-top:4px;"><span style="background:#eee; padding:2px 5px; border-radius:3px;">${q.seccion||'General'}</span> ${q.tipo} (${q.tiempo_limite}s)</div></div><div class="q-action" style="cursor:pointer; color:var(--primary); padding:5px;" onclick="moveCard(${q.id_pregunta}, '${type}')"><i class="fa-solid fa-${type==='source'?'plus':'xmark'}"></i></div>`;
    
    div.addEventListener('dragstart', (e) => { 
        e.dataTransfer.setData("text/plain", q.id_pregunta); 
        div.classList.add('dragging'); 
        div.style.opacity = '0.5'; 
    });
    
    div.addEventListener('dragend', () => { 
        div.classList.remove('dragging'); 
        div.style.opacity = '1'; 
    });
    
    return div;
}
function moveCard(id, fromType) {
    const q = allQuestions.find(x => x.id_pregunta == id);
    if(!q) return;
    if(fromType === 'source') { selectedIds.add(String(id)); document.getElementById('targetList').appendChild(createCardElement(q, 'target')); } 
    else { selectedIds.delete(String(id)); const item = document.querySelector(`#targetList [data-id="${id}"]`); if(item) item.remove(); }
    renderSourceList(); updateCount();
}
function allowDrop(ev) { 
    ev.preventDefault(); 
    const container = ev.target.closest('.builder-list');
    // Solo permitimos reordenar visualmente en la lista de destino
    if (!container || container.id !== 'targetList') return;
    
    const draggable = document.querySelector('.dragging');
    if (!draggable) return;

    const afterElement = getBuilderAfterElement(container, ev.clientY);
    if (afterElement == null) {
        container.appendChild(draggable);
    } else {
        container.insertBefore(draggable, afterElement);
    }
}

function drop(ev, zone) {
    ev.preventDefault();
    const id = ev.dataTransfer.getData("text/plain");
    if(!id) return;
    
    if (zone === 'target') {
        if (!selectedIds.has(String(id))) {
            moveCard(id, 'source');
        }
        // El reordenamiento visual ya se gestionó en allowDrop
    } else if (zone === 'source' && selectedIds.has(String(id))) {
        moveCard(id, 'target');
    }
}

function getBuilderAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.q-card:not(.dragging)')];
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}
function updateCount() {
    const count = selectedIds.size;
    document.getElementById('countSelected').innerText = count;
    document.getElementById('emptyMsg').style.display = count > 0 ? 'none' : 'block';
}

// Event Listeners condicionales
const formCreate = document.getElementById('formCreateGame');
if(formCreate) {
    formCreate.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        const finalOrderIds = [];
        document.querySelectorAll('#targetList .q-card').forEach(el => finalOrderIds.push(el.dataset.id));
        if(finalOrderIds.length === 0) { alert(LANG_ERROR_NO_Q); return; }
        const data = { action: 'crear', nombre: e.target.nombre.value, id_modo: e.target.id_modo.value, preguntas_ids: finalOrderIds, target_user_id: document.getElementById('targetUserSelect') ? document.getElementById('targetUserSelect').value : null };
        btn.disabled = true;
        try {
            const res = await fetch('api/partidas.php', { method: 'POST', body: JSON.stringify(data) });
            const json = await res.json();
            if(json.success) { closeModals(); e.target.reset(); selectedIds.clear(); document.getElementById('targetList').innerHTML = `<div class="text-center text-muted mt-5" id="emptyMsg">${LANG_DRAG_MSG}</div>`; renderSourceList(); loadGames(); } 
            else { alert("Error: " + json.error); }
        } catch(err) { console.error(err); }
        btn.disabled = false;
    });
}

function openCreateGameModal() { 
    if(isStudent) return;
    document.getElementById('createGameModal').classList.add('active'); 
    renderSourceList(); 
}
function closeModals() { document.querySelectorAll('.modal').forEach(m => m.classList.remove('active')); }
async function loadUsersForSelects() {
    try {
        const res = await fetch('api/usuarios.php?limit=-1'); const json = await res.json();
        const selTarget = document.getElementById('targetUserSelect');
        let opts = `<option value="${currentUserId}">-- A mi nombre --</option>`;
        if(json.data) { json.data.forEach(u => { if([2,3,4,5].includes(parseInt(u.id_rol)) && u.id_usuario != currentUserId) { opts += `<option value="${u.id_usuario}">${u.nombre} (${u.nombre_rol})</option>`; } }); }
        if(selTarget) selTarget.innerHTML = opts;
    } catch(e) {}
}
async function launchGame(idPartida, pin) {
    if(!confirm(LANG_CONFIRM_LAUNCH)) return;
    await fetch('api/partidas.php', { method: 'POST', body: JSON.stringify({ action: 'abrir_sala', id_partida: idPartida }) });
    loadGames(); openProjector(pin);
}
function openProjector(pin) { window.open(`index.php?view=proyector&pin=${pin}`, '_blank'); }
async function deleteGame(id) { if(confirm(LANG_CONFIRM_DELETE)) { try { await fetch('api/partidas.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'borrar', id_partida: id }) }); loadGames(); } catch(e) {} } }
async function viewPlayers(id) {
    const list = document.getElementById('playersListContent');
    list.innerHTML = LANG_LOADING;
    document.getElementById('playersModal').classList.add('active');
    const res = await fetch(`api/partidas.php?action=ver_jugadores&id_partida=${id}`);
    const json = await res.json();
    if(!json.data.length) list.innerHTML = LANG_EMPTY;
    else list.innerHTML = json.data.map(p => `<div><b>${p.nombre_nick}</b>: ${p.puntuacion} pts</div>`).join('');
}
function exportPartidasPDF() { const search = document.getElementById('pSearch').value; window.open(`api/export_pdf.php?type=partidas&search=${search}`, '_blank'); }
</script>
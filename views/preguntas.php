<?php
// views/preguntas.php
if ($_SESSION['user_role'] == 6) { echo "<script>window.location.href='index.php';</script>"; exit; }
$role = $_SESSION['user_role'];
$isSuperAdmin = ($role == 1);
$isAcademy = ($role == 2);
$isTeacher = ($role == 3 || $role == 4); 
?>

<div id="toast"></div>

<div id="reassignModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3 class="mb-4"><?php echo __('key_modal_reassign_title'); ?></h3>
        <p class="text-muted mb-4"><?php echo __('key_modal_reassign_desc'); ?> <strong id="modalReassignCount">0</strong> <?php echo __('key_modal_reassign_desc_plural'); ?></p>
        <form id="frmReassign">
            <div class="mb-4">
                <label for="reassign_target_user" class="block text-muted mb-2"><?php echo __('key_modal_reassign_select'); ?></label>
                <select id="reassign_target_user" class="form-control" required>
                    <option value=""><?php echo __('key_modal_reassign_option_default'); ?></option>
                </select>
            </div>
            <div class="modal-footer-actions">
                <button type="button" class="btn-icon btn-modal-cancel" onclick="document.getElementById('reassignModal').classList.remove('active')"><?php echo __('key_btn_cancel'); ?></button>
                <button type="submit" class="btn-primary"><?php echo __('key_btn_reassign_confirm'); ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="dashboard-header" style="margin-bottom: 1.5rem;">
        <h2 class="welcome-title"><i class="fa-solid fa-circle-question"></i> <?php echo __('key_questions_title'); ?></h2>
        
        <div class="header-actions">
            <button class="btn-icon" onclick="window.open('api/export_pdf.php?type=preguntas', '_blank')" title="<?php echo __('btn_export_pdf'); ?>">
                <i class="fa-solid fa-file-pdf text-danger"></i>
            </button>
            
            <button class="btn-primary" onclick="showSection('list')" title="<?php echo __('key_btn_list_title'); ?>">
                <i class="fa-solid fa-list"></i> <span class="mobile-hidden"><?php echo __('key_btn_list'); ?></span>
            </button>
            
            <button class="btn-icon" style="border:1px solid var(--border-color); border-radius:var(--radius); width:auto; padding:0.6rem 1.2rem;" onclick="prepareCreate()" title="<?php echo __('key_btn_new_import_title'); ?>">
                <i class="fa-solid fa-plus"></i> <span class="mobile-hidden"><?php echo __('key_btn_new_import'); ?></span>
            </button>
        </div>
    </div>

    <div id="sec-list">
        
        <?php if($isAcademy || $isSuperAdmin || $isTeacher): ?>
        <div class="quick-filters-bar">
            <span class="quick-filter-label">
                <i class="fa-solid fa-bolt"></i> <?php echo __('quick_filters_title'); ?>:
            </span>
            
            <?php if($isAcademy || $isSuperAdmin): ?>
                <button class="btn-quick-filter" onclick="applyQFilter('most_used', this)">
                    <i class="fa-solid fa-fire"></i> <?php echo __('qf_most_used_questions'); ?>
                </button>
            <?php endif; ?>

            <button class="btn-quick-filter" onclick="applyQFilter('orphan', this)">
                <i class="fa-solid fa-folder-open"></i> <?php echo __('qf_orphan_questions'); ?>
            </button>
            
            <?php if($role == 3): ?>
            <button class="btn-quick-filter" onclick="applyQFilter('shared', this)">
                <i class="fa-solid fa-share-nodes"></i> <?php echo __('qf_shared_bank'); ?>
            </button>
            <?php endif; ?>

            <button class="btn-quick-filter" onclick="resetQFilters(this)" title="<?php echo __('qf_reset'); ?>">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <?php endif; ?>

        <div class="search-panel">
            <div class="search-bar-wrapper">
                <input type="text" id="qSearch" class="form-control" placeholder="<?php echo __('key_search_placeholder'); ?>" onkeyup="debounceQLoad()">
                
                <?php if (!$isSuperAdmin && $role != 4): ?>
                <select id="qFilterScope" class="form-control" style="max-width:200px;" onchange="loadQuestions()">
                    <option value="mine"><?php echo __('key_filter_my_questions'); ?></option>
                    <option value="shared_bank"><?php echo __('key_filter_shared_questions'); ?></option>
                </select>
                <?php endif; ?>
                
                <button class="btn-icon" style="border:1px solid var(--border-color);" onclick="toggleAdvancedSearch(this)" title="<?php echo __('key_filter_advanced_title'); ?>">
                    <i class="fa-solid fa-filter"></i>
                </button>
            </div>
            
            <div class="advanced-search"> 
                <div>
                    <label class="block text-muted mb-2"><?php echo __('key_filter_lang'); ?></label>
                    <select id="f_idioma" class="form-control" onchange="loadQuestions()">
                        <option value=""><?php echo __('key_filter_all'); ?></option>
                        <option value="es"><?php echo __('key_filter_lang_es'); ?></option>
                        <option value="gl"><?php echo __('key_filter_lang_gl'); ?></option>
                        <option value="en"><?php echo __('key_filter_lang_en'); ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-muted mb-2"><?php echo __('key_filter_type'); ?></label>
                    <select id="f_tipo" class="form-control" onchange="loadQuestions()">
                        <option value=""><?php echo __('key_filter_all'); ?></option>
                        <option value="quiz"><?php echo __('key_filter_type_quiz'); ?></option>
                        <option value="verdadero_falso"><?php echo __('key_filter_type_tf'); ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-muted mb-2"><?php echo __('key_filter_section'); ?></label>
                    <input type="text" id="f_seccion" class="form-control" onkeyup="debounceQLoad()">
                </div>
                <?php if($isSuperAdmin || $isAcademy): ?>
                <div>
                    <label class="block text-muted mb-2"><?php echo __('key_filter_user'); ?></label>
                    <select id="f_usuario" class="form-control" onchange="loadQuestions()"><option value=""><?php echo __('key_filter_all'); ?></option></select>
                </div>
                <?php endif; ?>
                
                <div>
                    <label class="block text-muted mb-2"><?php echo __('key_filter_date_from'); ?></label>
                    <input type="date" id="f_date_from" class="form-control" onchange="loadQuestions()">
                </div>
                <div>
                    <label class="block text-muted mb-2"><?php echo __('key_filter_date_to'); ?></label>
                    <input type="date" id="f_date_to" class="form-control" onchange="loadQuestions()">
                </div>
            </div>
        </div>

        <div class="pagination-bar">
            <div style="display:flex; align-items:center; gap:10px;">
                <label class="text-muted"><?php echo __('key_pagination_show'); ?> </label>
                <select id="limitSelectTop" onchange="changeQLimit(this.value)" class="form-control" style="width:auto; padding:5px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div class="pagination-controls">
                <button class="btn-icon" style="border:1px solid var(--border-color)" onclick="prevQPage()" id="btnPrevTop"><i class="fa-solid fa-chevron-left"></i></button>
                <span id="pageInfoTop" class="text-muted" style="margin:0 10px;"></span>
                <button class="btn-icon" style="border:1px solid var(--border-color)" onclick="nextQPage()" id="btnNextTop"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </div>
        
        <div class="bulk-actions-panel" id="bulkActionsPanel">
            <span style="font-weight:bold;" id="selectionCount">0 <?php echo __('key_bulk_selected'); ?></span>
            <button class="btn-icon" onclick="openReassignModal()" title="<?php echo __('key_bulk_reassign_title'); ?>" style="margin-left: 1rem;">
                <i class="fa-solid fa-user-pen"></i> <?php echo __('key_bulk_reassign'); ?>
            </button>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width:20px;"><input type="checkbox" id="selectAllCheck" class="header-checkbox" onclick="toggleSelectAll(this)"></th>
                        <th class="sortable" onclick="changeQSort('id_pregunta')" style="width:50px;"><?php echo __('key_header_id'); ?> <i id="icon-id_pregunta" class="fa-solid fa-sort"></i></th>
                        <th class="sortable" onclick="changeQSort('texto')"><?php echo __('key_header_question'); ?> <i id="icon-texto" class="fa-solid fa-sort-down"></i></th>
                        <th class="sortable" onclick="changeQSort('seccion')"><?php echo __('key_header_section'); ?> <i id="icon-seccion" class="fa-solid fa-sort"></i></th>
                        <?php if($isSuperAdmin || $isAcademy): ?><th><?php echo __('key_header_owner'); ?></th><?php endif; ?>
                        <th class="sortable" onclick="changeQSort('idioma')" style="width:60px;"><?php echo __('key_header_lang'); ?> <i id="icon-idioma" class="fa-solid fa-sort"></i></th>
                        <th><?php echo __('key_header_type'); ?></th>
                        <th class="sortable text-center" onclick="changeQSort('doble_valor')" style="width:60px;"><?php echo __('key_header_2x'); ?> <i id="icon-doble_valor" class="fa-solid fa-sort"></i></th>
                        <th class="text-right"><?php echo __('key_header_actions'); ?></th>
                    </tr>
                </thead>
                <tbody id="questionsTableBody"></tbody>
            </table>
        </div>

        <div class="pagination-bar">
            <div id="totalRecordsInfo" class="text-muted"></div>
            <div class="pagination-controls">
                <button class="btn-icon" style="border:1px solid var(--border-color)" onclick="prevQPage()" id="btnPrevBottom"><i class="fa-solid fa-chevron-left"></i></button>
                <span id="pageInfoBottom" class="text-muted" style="margin:0 10px;"></span>
                <button class="btn-icon" style="border:1px solid var(--border-color)" onclick="nextQPage()" id="btnNextBottom"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div id="sec-create" class="hidden">
        <div class="tabs-header" style="display:flex; gap:10px; margin-bottom:1rem; border-bottom:1px solid var(--border-color);">
            <button class="tab-btn active" id="btn-tab-manual" onclick="openSubTab('manual', this)" style="padding:10px; background:none; border:none; border-bottom:2px solid var(--primary); font-weight:bold; color:var(--primary); cursor:pointer;">
                <?php echo __('key_tab_manual'); ?>
            </button>
            <button class="tab-btn" id="btn-tab-import" onclick="openSubTab('import', this)" style="padding:10px; background:none; border:none; border-bottom:2px solid transparent; color:var(--text-muted); cursor:pointer;">
                <?php echo __('key_tab_import'); ?>
            </button>
        </div>

        <div id="tab-manual" class="tab-content active" style="margin-top:20px;">
            <form id="frmManual">
                <input type="hidden" name="action" id="formAction" value="create_manual">
                <input type="hidden" name="id_pregunta" id="editId" value="">
                
                <?php if($isAcademy || $isSuperAdmin): ?>
                <div class="mb-4 p-3" style="background:var(--bg-body); border-radius:var(--radius);">
                    <label class="block text-muted mb-2"><?php echo __('key_form_assign_to'); ?></label>
                    <select name="target_user_id" id="manual_target_user" class="form-control"><option value="<?php echo $_SESSION['user_id']; ?>"><?php echo __('key_form_assign_to_self'); ?></option></select>
                </div>
                <?php endif; ?>

                <div class="modal-form-grid">
                    <div>
                        <div class="mb-4">
                            <label class="block text-muted mb-2"><?php echo __('key_form_text'); ?> <span class="text-danger">*</span></label>
                            <textarea name="texto" id="inp_texto" class="form-control" required rows="2"></textarea>
                        </div>
                        <div class="modal-form-grid" style="gap:10px;">
                            <div class="mb-4">
                                <label class="block text-muted mb-2"><?php echo __('key_form_section'); ?></label>
                                <input type="text" name="seccion" id="inp_seccion" class="form-control">
                            </div>
                            <div class="mb-4">
                                <label class="block text-muted mb-2"><?php echo __('key_form_lang'); ?></label>
                                <select name="idioma" id="inp_idioma" class="form-control">
                                    <option value="es"><?php echo __('key_filter_lang_es'); ?></option>
                                    <option value="gl"><?php echo __('key_filter_lang_gl'); ?></option>
                                    <option value="en"><?php echo __('key_filter_lang_en'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="mb-4">
                            <label class="block text-muted mb-2"><?php echo __('key_form_type'); ?></label>
                            <select name="tipo" class="form-control" id="manual_tipo" onchange="renderManualOptions()">
                                <option value="quiz"><?php echo __('key_filter_type_quiz'); ?></option>
                                <option value="verdadero_falso"><?php echo __('key_filter_type_tf'); ?></option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-muted mb-2"><?php echo __('key_form_time'); ?></label>
                            <input type="number" name="tiempo_limite" id="inp_tiempo" class="form-control" value="20" min="5">
                        </div>
                        <div class="mb-4 p-2" style="border:1px solid var(--border-color); border-radius:4px;">
                            <label class="switch-label">
                                <input type="checkbox" name="doble_valor" id="chk_doble" value="1">
                                <span><strong><?php echo __('key_form_double_points'); ?></strong></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div style="background:var(--bg-surface); border:1px solid var(--border-color); padding:15px; border-radius:var(--radius); margin-top:10px;">
                    <h4 style="margin:0 0 10px 0; border-bottom:1px solid var(--border-color); color:var(--primary); padding-bottom:5px;">
                        <?php echo __('key_form_answers'); ?> <small class="text-muted" style="font-weight:normal;"><?php echo __('key_form_answers_desc'); ?></small>
                    </h4>
                    <div id="manual-options-container"></div>
                </div>

                <div class="modal-footer-actions">
                    <button type="button" class="btn-icon btn-modal-cancel" onclick="cancelEdit()"><?php echo __('key_btn_cancel'); ?></button>
                    <button type="submit" class="btn-primary" id="btnSaveManual"><?php echo __('key_btn_save'); ?></button>
                </div>
            </form>
        </div>

        <div id="tab-import" class="tab-content hidden" style="margin-top:20px;">
             <div class="step-wizard">
                <span class="step-item active" id="st-1"><?php echo __('key_import_step1'); ?></span>
                <span class="step-item" id="st-2"><?php echo __('key_import_step2'); ?></span>
                <span class="step-item" id="st-3"><?php echo __('key_import_step3'); ?></span>
            </div>
            
            <div id="import-step-1">
                <?php if($isAcademy || $isSuperAdmin): ?>
                <div class="mb-4" style="max-width:400px;">
                    <label class="block text-muted mb-2"><?php echo __('key_import_destination'); ?></label>
                    <select id="import_target_user" class="form-control"><option value="<?php echo $_SESSION['user_id']; ?>"><?php echo __('key_import_my_library'); ?></option></select>
                </div>
                <?php endif; ?>
                
                <div class="import-area" id="dropZone">
                    <i class="fa-solid fa-file-excel" style="font-size:3rem; color:var(--primary); margin-bottom:1rem;"></i>
                    <p class="text-muted">Arrastra CSV, Excel (.xlsx, .xls) o ODS aquí</p>
                    <input type="file" id="fileInput" accept=".csv, .xlsx, .xls, .ods" hidden>
                </div>
                
                <div class="mt-4 text-right">
                    <a href="api/preguntas.php?action=download_template" target="_blank" class="btn-icon" style="border:1px solid var(--border-color); border-radius:4px; padding:0.5rem 1rem; text-decoration:none;">
                        <i class="fa-solid fa-download"></i> <?php echo __('key_import_download_template'); ?>
                    </a>
                </div>
            </div>

            <div id="import-mapping-step" class="hidden">
                <h4 style="color:var(--primary); margin-bottom:10px;"><?php echo __('key_import_mapping_title'); ?></h4>
                <p class="text-muted mb-3"><?php echo __('key_import_mapping_desc'); ?></p>
                <div style="max-height:400px; overflow-y:auto; border:1px solid var(--border-color); border-radius:var(--radius); padding:1rem;">
                    <table class="table-mini" style="width:100%;">
                        <thead>
                            <tr><th width="40%"><?php echo __('key_col_system'); ?></th><th><?php echo __('key_col_file'); ?></th></tr>
                        </thead>
                        <tbody id="mappingTableBody"></tbody>
                    </table>
                </div>
                <div class="text-right mt-3 modal-footer-actions">
                    <button class="btn-icon btn-modal-cancel" onclick="resetImport()"><?php echo __('key_btn_cancel'); ?></button>
                    <button class="btn-primary" onclick="executeQImport()"><?php echo __('key_import_btn_import'); ?></button>
                </div>
            </div>

            <div id="import-step-2" class="hidden">
                <div id="validation-log" class="mb-3" style="background:#1e293b; color:#00ff9d; padding:1rem; border-radius:5px;"></div>
                <div class="text-right">
                    <button class="btn-icon btn-modal-cancel" onclick="resetImport()"><?php echo __('key_btn_cancel'); ?></button>
                    <button class="btn-primary" id="btn-confirm-import" onclick="executeQImport()" disabled><?php echo __('key_import_btn_import'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// --- Variables de Estado ---
let qState = { page: 1, limit: 10, sort: 'texto', order: 'ASC', totalPages: 1 };
let searchTimeout;
const isSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
const isAcademy = <?php echo $isAcademy ? 'true' : 'false'; ?>;
let selectedQuestions = new Set();
let currentQFilter = '';
let currentScope = 'mine'; 

// Traducciones seguras
const LANG_LOADING = <?php echo json_encode(__('key_js_loading')); ?>;
const LANG_NO_RESULTS = <?php echo json_encode(__('key_js_no_results')); ?>;
const LANG_TRUE = <?php echo json_encode(__('key_form_answer_true')); ?>;
const LANG_FALSE = <?php echo json_encode(__('key_form_answer_false')); ?>;
const LANG_CONFIRM_DELETE = <?php echo json_encode(__('game_js_confirm_delete')); ?>;

document.addEventListener('DOMContentLoaded', () => {
    loadQuestions();
    renderManualOptions();
    if(isAcademy || isSuperAdmin) loadUsersForSelect();
    document.getElementById('frmReassign').addEventListener('submit', handleReassignSubmit);
});

// UI Helpers
function showToast(msg, type='success') {
    const x = document.getElementById("toast");
    x.innerText = msg;
    x.style.backgroundColor = type === 'error' ? '#ef4444' : '#22c55e';
    x.className = "show";
    setTimeout(() => { x.className = x.className.replace("show", ""); }, 5000);
}

function showSection(sec) {
    document.getElementById('sec-list').classList.add('hidden');
    document.getElementById('sec-create').classList.add('hidden');
    document.getElementById('sec-'+sec).classList.remove('hidden');
}
function openSubTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.getElementById('tab-'+name).classList.remove('hidden');
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active'); b.style.color = 'var(--text-muted)'; b.style.borderBottomColor = 'transparent';
    });
    btn.classList.add('active'); btn.style.color = 'var(--primary)'; btn.style.borderBottomColor = 'var(--primary)';
}

function toggleAdvancedSearch(button) {
    const searchPanel = button.closest('.search-panel');
    if (searchPanel) {
        const advancedSearch = searchPanel.querySelector('.advanced-search');
        advancedSearch.classList.toggle('open');
    }
}

// Listado
function debounceQLoad() { clearTimeout(searchTimeout); searchTimeout = setTimeout(() => { qState.page = 1; loadQuestions(); }, 300); }
function changeQSort(col) {
    if (qState.sort === col) qState.order = (qState.order === 'ASC') ? 'DESC' : 'ASC';
    else { qState.sort = col; qState.order = 'ASC'; }
    document.querySelectorAll('#sec-list th i').forEach(i => { if(i.id.startsWith('icon-')) i.className = 'fa-solid fa-sort'; });
    const icon = document.getElementById(`icon-${col}`);
    if(icon) icon.className = `fa-solid fa-sort-${qState.order === 'ASC' ? 'up' : 'down'}`;
    loadQuestions();
}
function changeQLimit(val) { qState.limit = parseInt(val); qState.page = 1; loadQuestions(); }
function prevQPage() { if(qState.page > 1) { qState.page--; loadQuestions(); } }
function nextQPage() { if(qState.page < qState.totalPages) { qState.page++; loadQuestions(); } }

async function loadQuestions() {
    const search = document.getElementById('qSearch').value;
    const scopeElem = document.getElementById('qFilterScope');
    if(scopeElem && currentQFilter !== 'orphan') currentScope = scopeElem.value;
    else if (!scopeElem) currentScope = 'mine';
    
    const params = new URLSearchParams({
        action: 'list', search, scope: currentScope, 
        f_idioma: document.getElementById('f_idioma').value,
        f_tipo: document.getElementById('f_tipo').value,
        f_seccion: document.getElementById('f_seccion').value,
        f_usuario: document.getElementById('f_usuario') ? document.getElementById('f_usuario').value : '',
        date_from: document.getElementById('f_date_from').value,
        date_to: document.getElementById('f_date_to').value,
        sort: qState.sort, order: qState.order, page: qState.page, limit: qState.limit,
        special: currentQFilter 
    });

    const colspan = (isSuperAdmin || isAcademy) ? 9 : 8; 
    const tbody = document.getElementById('questionsTableBody');
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center">${LANG_LOADING}</td></tr>`;
    
    if(document.getElementById('selectAllCheck')) document.getElementById('selectAllCheck').checked = false;
    selectedQuestions.clear();
    updateBulkActionsPanel();

    try {
        const res = await fetch(`api/preguntas.php?${params.toString()}`);
        const json = await res.json();
        tbody.innerHTML = '';
        
        if (!json.data || json.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-muted">${LANG_NO_RESULTS}</td></tr>`;
            updatePaginationInfo(0);
            return;
        }
        
        json.data.forEach(q => {
            let ownerInfo = '';
            if (isSuperAdmin || isAcademy) {
                ownerInfo = `<td><span class="badge-owner">${q.nombre_propietario}</span>`;
                if(q.nombre_academia) ownerInfo += `<br><span class="badge-academy">${q.nombre_academia}</span>`;
                ownerInfo += `</td>`;
            }
            
            const tipoIcon = q.tipo === 'quiz' ? `<i class="fa-solid fa-list"></i>` : `<i class="fa-solid fa-check"></i>`;
            const dobleValorCell = q.doble_valor == 1 ? `<i class="fa-solid fa-bolt" style="color:orange;"></i>` : `<span class="text-muted">-</span>`;
            const sharedIcon = q.compartida == 1 ? `<span style="color:var(--primary); margin-left:8px;"><i class="fa-solid fa-share-nodes"></i></span>` : '';
            const isChecked = selectedQuestions.has(q.id_pregunta) ? 'checked' : '';
            
            let actions = '';
            if (currentScope === 'shared_bank' && q.id_propietario != <?php echo $_SESSION['user_id']; ?>) {
                actions = `<button class="btn-icon" onclick="duplicateQuestion(${q.id_pregunta})" title="Duplicar" style="color:var(--primary);"><i class="fa-solid fa-copy"></i></button>`;
            } else {
                actions = `
                    <button class="btn-icon" onclick="reassignOne(${q.id_pregunta})"><i class="fa-solid fa-user-pen"></i></button>
                    <button class="btn-icon" onclick="editQuestion(${q.id_pregunta})"><i class="fa-solid fa-pen"></i></button>
                    <button class="btn-icon" onclick="duplicateQuestion(${q.id_pregunta})"><i class="fa-solid fa-copy"></i></button>
                    <button class="btn-icon" style="color:var(--danger-color);" onclick="deleteQuestion(${q.id_pregunta})"><i class="fa-solid fa-trash"></i></button>
                `;
            }

            tbody.innerHTML += `
                <tr>
                    <td class="text-center"><input type="checkbox" class="row-select" onchange="updateSelection(this, ${q.id_pregunta})" ${isChecked}></td>
                    <td><small class="text-muted">${q.id_pregunta}</small></td>
                    <td><strong>${q.texto}</strong> ${sharedIcon}</td>
                    <td>${q.seccion || '-'}</td>
                    ${ownerInfo}
                    <td><span style="text-transform:uppercase; font-size:0.8rem;">${q.idioma}</span></td>
                    <td>${tipoIcon}</td>
                    <td class="text-center">${dobleValorCell}</td>
                    <td class="text-right">${actions}</td>
                </tr>`;
        });
        updatePaginationInfo(json.total);
    } catch(e) { 
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger">Error</td></tr>`;
    }
}

function updatePaginationInfo(total) {
    qState.totalPages = Math.ceil(total / qState.limit) || 1;
    const txt = `<?php echo __('key_pagination_page'); ?> ${qState.page} <?php echo __('key_pagination_of'); ?> ${qState.totalPages}`;
    document.getElementById('pageInfoTop').innerText = txt;
    document.getElementById('pageInfoBottom').innerText = txt;
    document.getElementById('totalRecordsInfo').innerText = `<?php echo __('key_pagination_total'); ?> ${total}`;
    ['btnPrevTop','btnPrevBottom'].forEach(id => document.getElementById(id).disabled = (qState.page === 1));
    ['btnNextTop','btnNextBottom'].forEach(id => document.getElementById(id).disabled = (qState.page >= qState.totalPages));
}

// --- FILTROS ---
function applyQFilter(type, btn) {
    document.querySelectorAll('.btn-quick-filter').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    document.getElementById('qSearch').value = '';
    document.getElementById('f_seccion').value = '';
    currentQFilter = '';
    
    if(type === 'most_used') { qState.sort = 'veces_usada'; qState.order = 'DESC'; currentScope = 'mine'; } 
    else if (type === 'orphan') { currentQFilter = 'orphan'; currentScope = 'mine'; } 
    else if (type === 'shared') { currentScope = 'shared_bank'; }

    if(document.getElementById('qFilterScope')) document.getElementById('qFilterScope').value = (currentScope === 'shared_bank' ? 'shared_bank' : 'mine');
    qState.page = 1;
    loadQuestions();
}

function resetQFilters(btn) {
    qState.sort = 'texto'; qState.order = 'ASC'; currentQFilter = ''; currentScope = 'mine';
    if(document.getElementById('qFilterScope')) document.getElementById('qFilterScope').value = 'mine';
    loadQuestions();
    document.querySelectorAll('.btn-quick-filter').forEach(b => b.classList.remove('active'));
}

// --- CRUD ---
async function editQuestion(id) {
    try {
        const res = await fetch(`api/preguntas.php?action=get_one&id=${id}`);
        const json = await res.json();
        if(json.error) { showToast(json.error, 'error'); return; }
        const q = json.data;
        document.getElementById('formAction').value = 'update_manual';
        document.getElementById('editId').value = q.id_pregunta;
        document.getElementById('inp_texto').value = q.texto;
        document.getElementById('inp_seccion').value = q.seccion;
        document.getElementById('inp_idioma').value = q.idioma;
        document.getElementById('manual_tipo').value = q.tipo;
        document.getElementById('inp_tiempo').value = q.tiempo_limite;
        document.getElementById('chk_doble').checked = (q.doble_valor == 1);
        if(document.getElementById('manual_target_user')) document.getElementById('manual_target_user').value = q.id_propietario;
        renderManualOptions(JSON.parse(q.json_opciones));
        document.getElementById('btnSaveManual').innerText = "<?php echo __('key_js_btn_update'); ?>";
        document.getElementById('btn-tab-import').style.display = 'none';
        document.getElementById('btn-tab-manual').click();
        showSection('create');
    } catch(e) { showToast("Error", 'error'); }
}
function cancelEdit() { prepareCreate(); showSection('list'); }
function prepareCreate() {
    document.getElementById('frmManual').reset();
    document.getElementById('formAction').value = 'create_manual';
    document.getElementById('btnSaveManual').innerText = "<?php echo __('key_btn_save'); ?>";
    document.getElementById('btn-tab-import').style.display = 'block';
    renderManualOptions();
    showSection('create');
}

function renderManualOptions(existing = null) {
    const tipo = document.getElementById('manual_tipo').value;
    const container = document.getElementById('manual-options-container');
    container.innerHTML = '';
    if(tipo === 'verdadero_falso') {
        const isTrue = existing ? existing[0].es_correcta : true;
        container.innerHTML = `
            <div class="option-row"><input type="radio" name="correcta" value="0" class="radio-correct" ${isTrue?'checked':''}><input type="text" class="form-control" value="${LANG_TRUE}" readonly style="color:green; font-weight:bold;"></div>
            <div class="option-row"><input type="radio" name="correcta" value="1" class="radio-correct" ${!isTrue?'checked':''}><input type="text" class="form-control" value="${LANG_FALSE}" readonly style="color:red; font-weight:bold;"></div>`;
    } else {
        for(let i=0; i<4; i++) {
            const val = existing && existing[i] ? existing[i].texto : '';
            const chk = existing && existing[i] ? existing[i].es_correcta : (i===0);
            container.innerHTML += `<div class="option-row"><input type="radio" name="correcta" value="${i}" class="radio-correct" ${chk?'checked':''}><input type="text" name="opcion_${i}" class="form-control" placeholder="Opción ${i+1}" value="${val}" required></div>`;
        }
    }
}

document.getElementById('frmManual').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = Object.fromEntries(fd.entries());
    data.doble_valor = document.getElementById('chk_doble').checked ? 1 : 0;
    let opciones = [];
    if(data.tipo === 'verdadero_falso') {
        opciones = [{texto:LANG_TRUE, es_correcta:(data.correcta==='0')}, {texto:LANG_FALSE, es_correcta:(data.correcta==='1')}];
    } else {
        for(let i=0; i<4; i++) if(fd.get(`opcion_${i}`)) opciones.push({texto:fd.get(`opcion_${i}`), es_correcta:(data.correcta==i)});
    }
    const res = await fetch('api/preguntas.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({...data, opciones}) });
    const json = await res.json();
    if(json.success) {
        showToast(data.action==='create_manual' ? "<?php echo __('key_js_saved'); ?>" : "<?php echo __('key_js_updated'); ?>");
        if(data.action==='create_manual') prepareCreate(); else showSection('list');
        loadQuestions();
    } else showToast(json.error, 'error');
});

// Importacion (Resumido para brevedad, lógica igual a usuarios/partidas)
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
let fileToImport = null;
if(dropZone && fileInput) {
    dropZone.onclick = () => fileInput.click();
    fileInput.onchange = (e) => { if(e.target.files.length) { fileToImport=e.target.files[0]; validateQFile(); } };
    dropZone.ondragover = (e) => { e.preventDefault(); dropZone.classList.add('dragover'); };
    dropZone.ondragleave = () => dropZone.classList.remove('dragover');
    dropZone.ondrop = (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); if(e.dataTransfer.files.length) { fileToImport=e.dataTransfer.files[0]; validateQFile(); } };
}

async function validateQFile() {
    document.getElementById('import-step-1').classList.add('hidden');
    document.getElementById('import-mapping-step').classList.add('hidden');
    document.getElementById('import-step-2').classList.remove('hidden');
    document.getElementById('validation-log').innerHTML = "Validando...";
    const fd = new FormData(); fd.append('action', 'validate_import'); fd.append('archivo', fileToImport);
    try {
        const res = await fetch('api/preguntas.php', {method:'POST', body:fd});
        const json = await res.json();
        if(json.status === 'ok') { document.getElementById('validation-log').innerHTML = "OK"; document.getElementById('btn-confirm-import').disabled = false; }
        else if (json.status === 'need_mapping') { document.getElementById('import-step-2').classList.add('hidden'); renderMappingInterface(json.headers); }
    } catch(e) {}
}
function renderMappingInterface(headers) {
    const container = document.getElementById('import-mapping-step');
    const tbody = document.getElementById('mappingTableBody');
    tbody.innerHTML = '';
    const fields = [{key:'texto',label:'Pregunta'},{key:'correcta',label:'Correcta'},{key:'a',label:'Resp 1'},{key:'b',label:'Resp 2'}];
    fields.forEach(f => {
        let opts = `<option value="">-- Ignorar --</option>`;
        headers.forEach((h,i) => opts += `<option value="${i}">${h}</option>`);
        tbody.innerHTML += `<tr><td>${f.label}</td><td><select class="form-control mapping-select" data-key="${f.key}">${opts}</select></td></tr>`;
    });
    container.classList.remove('hidden');
}
async function executeQImport() {
    const fd = new FormData(); fd.append('action', 'execute_import'); fd.append('archivo', fileToImport);
    await fetch('api/preguntas.php', {method:'POST', body:fd});
    location.reload();
}
function resetImport() { location.reload(); }

// Acciones en Lote
function updateSelection(checkbox, id) { if(checkbox.checked) selectedQuestions.add(id); else selectedQuestions.delete(id); updateBulkActionsPanel(); }
function toggleSelectAll(master) { document.querySelectorAll('#questionsTableBody .row-select').forEach(cb => { cb.checked = master.checked; updateSelection(cb, parseInt(cb.getAttribute('onchange').match(/\d+/)[0])); }); }
function updateBulkActionsPanel() {
    const count = selectedQuestions.size;
    document.getElementById('bulkActionsPanel').style.display = count > 0 ? 'block' : 'none';
    document.getElementById('selectionCount').innerText = count;
}
function openReassignModal() { document.getElementById('reassignModal').classList.add('active'); document.getElementById('modalReassignCount').innerText = selectedQuestions.size; }
async function handleReassignSubmit(e) {
    e.preventDefault();
    await fetch('api/preguntas.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'bulk_reassign', ids:Array.from(selectedQuestions), target:document.getElementById('reassign_target_user').value}) });
    location.reload();
}
async function deleteQuestion(id) { if(confirm(LANG_CONFIRM_DELETE)) { await fetch('api/preguntas.php', {method:'POST', body:JSON.stringify({action:'delete', id_pregunta:id})}); loadQuestions(); } }
async function duplicateQuestion(id) { await fetch('api/preguntas.php', {method:'POST', body:JSON.stringify({action:'duplicate', id_pregunta:id})}); loadQuestions(); }
async function loadUsersForSelect() {
    const res = await fetch('api/usuarios.php?limit=-1');
    const json = await res.json();
    const sel = document.getElementById('f_usuario');
    if(sel) json.data.forEach(u => sel.innerHTML += `<option value="${u.id_usuario}">${u.nombre}</option>`);
    const sel2 = document.getElementById('reassign_target_user');
    if(sel2) json.data.forEach(u => sel2.innerHTML += `<option value="${u.id_usuario}">${u.nombre}</option>`);
    const sel3 = document.getElementById('manual_target_user');
    if(sel3) json.data.forEach(u => sel3.innerHTML += `<option value="${u.id_usuario}">${u.nombre}</option>`);
}
function reassignOne(id) { selectedQuestions.clear(); selectedQuestions.add(id); openReassignModal(); }
</script>
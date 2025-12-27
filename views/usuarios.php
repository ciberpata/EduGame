<?php
// views/usuarios.php
if ($_SESSION['user_role'] == 6) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$role = $_SESSION['user_role'];
$isSuperAdmin = ($role == 1);
$isAcademy = ($role == 2);
?>

<div id="toast"></div>

<div id="userModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle" class="mb-4"><?php echo __('key_users_modal_user'); ?></h3>
        <form id="userForm" enctype="multipart/form-data">
            <input type="hidden" name="id_usuario" id="userId">
            <div class="modal-form-grid">
                <div>
                    <h4 class="modal-section-title"><?php echo __('key_users_modal_account'); ?></h4>
                    
                    <div class="mb-4" style="text-align:center;">
                        <img id="modalPhotoPreview" src="assets/img/default-avatar.png" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid var(--border-color); margin-bottom:10px;">
                        <input type="file" name="foto_perfil" id="in_foto" class="form-control" accept="image/*" onchange="previewUserPhoto(event)">
                    </div>

                    <div class="mb-4">
                        <label class="block text-muted mb-2"><?php echo __('name_label'); ?> <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="in_nombre" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-muted mb-2"><?php echo __('email_label'); ?> <span class="text-danger">*</span></label>
                        <input type="email" name="correo" id="in_correo" class="form-control" required>
                    </div>
                    <div class="mb-4" id="divPassCreate">
                        <label class="block text-muted mb-2"><?php echo __('password_label'); ?> <span class="text-danger">*</span></label>
                        <input type="password" name="contrasena" id="in_pass" class="form-control">
                    </div>
                    <div class="mb-4">
                        <label class="block text-muted mb-2"><?php echo __('key_users_header_role'); ?> <span class="text-danger">*</span></label>
                        <select name="rol" id="in_rol" class="form-control" required onchange="toggleAcademySelect()"></select>
                    </div>
                    <div class="mb-4 hidden" id="divSelectAcademia">
                        <label class="block text-muted mb-2"><?php echo __('key_users_modal_assign_academy'); ?> <span class="text-danger">*</span></label>
                        <select name="id_padre" id="in_id_padre" class="form-control">
                            <option value=""><?php echo __('key_users_modal_select'); ?></option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-muted mb-2"><?php echo __('language_label'); ?> <span class="text-danger">*</span></label>
                        <select name="idioma_pref" id="in_idioma" class="form-control" required>
                            <option value="es"><?php echo __('lang_es'); ?></option>
                            <option value="gl"><?php echo __('lang_gl'); ?></option>
                            <option value="en"><?php echo __('lang_en'); ?></option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-muted mb-2"><?php echo __('key_users_header_status'); ?> <span class="text-danger">*</span></label>
                        <select name="activo" id="in_activo" class="form-control" required>
                            <option value="1"><?php echo __('key_users_status_active'); ?></option>
                            <option value="0"><?php echo __('key_users_status_blocked'); ?></option>
                        </select>
                    </div>
                </div>
                <div>
                    <h4 class="modal-section-title"><?php echo __('fiscal_data'); ?></h4>
                    
                    <div id="fiscalSpecificFields">
                        <div class="modal-fiscal-row">
                            <div>
                                <label class="block text-muted mb-2"><?php echo __('business_name_label'); ?></label>
                                <input type="text" name="razon_social" id="in_razon" class="form-control">
                            </div>
                            <div>
                                <label class="block text-muted mb-2"><?php echo __('trade_name_label'); ?></label>
                                <input type="text" name="nombre_negocio" id="in_negocio" class="form-control">
                            </div>
                        </div>
                        <div class="modal-fiscal-row">
                            <div>
                                <label class="block text-muted mb-2"><?php echo __('nif_label'); ?> <span class="text-danger">*</span></label>
                                <input type="text" name="nif" id="in_nif" class="form-control" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()" onblur="validateNifVisual(this)" required>
                                <small id="nifError" class="text-danger hidden" style="font-size:0.8rem; margin-top:2px;"><?php echo __('nif_error'); ?></small>
                            </div>
                            <div>
                                <label class="block text-muted mb-2"><?php echo __('roi_label'); ?></label>
                                <input type="text" name="roi" id="in_roi" class="form-control" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-muted mb-2"><?php echo __('phone_label'); ?></label>
                        <input type="text" name="telefono" id="in_tel" class="form-control">
                    </div>
                    <div class="modal-fiscal-row">
                        <div>
                            <label class="block text-muted mb-2"><?php echo __('address_label'); ?></label>
                            <input type="text" name="direccion" id="in_dir" class="form-control">
                        </div>
                        <div>
                            <label class="block text-muted mb-2"><?php echo __('key_users_modal_address_num'); ?></label>
                            <input type="text" name="direccion_numero" id="in_dir_num" class="form-control">
                        </div>
                    </div>
                    <div class="modal-fiscal-row">
                        <div>
                            <label class="block text-muted mb-2"><?php echo __('country_label'); ?></label>
                            <select name="id_pais" id="in_pais" class="form-control" onchange="toggleAdminGeo(); validateNifVisual(document.getElementById('in_nif'));"></select>
                        </div>
                        <div>
                            <label class="block text-muted mb-2"><?php echo __('zip_code_label'); ?></label>
                            <input type="text" name="cp" id="in_cp" class="form-control">
                        </div>
                    </div>
                    <div id="adminSpainFields" class="modal-fiscal-row hidden">
                        <div>
                            <label class="block text-muted mb-2"><?php echo __('province_label'); ?></label>
                            <select name="id_provincia" id="in_prov" class="form-control" onchange="loadAdminCiudades(this.value)">
                                <option value="">Sel...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-muted mb-2"><?php echo __('city_label'); ?></label>
                            <select name="id_ciudad" id="in_ciud" class="form-control"></select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer-actions">
                <button type="button" onclick="closeModals()" class="btn-icon btn-modal-cancel"><?php echo __('key_btn_cancel'); ?></button>
                <button type="submit" class="btn-primary"><?php echo __('key_btn_save'); ?></button>
            </div>
        </form>
    </div>
</div>

<div id="passModal" class="modal">
    <div class="modal-content" style="max-width:400px;">
        <h3 class="mb-4"><?php echo __('key_users_modal_pass_title'); ?></h3>
        <form id="passFormAdmin">
            <input type="hidden" name="id_usuario" id="passUserId">
            <input type="hidden" name="action" value="change_password">
            <input type="password" name="new_password" class="form-control mb-4" required minlength="6" placeholder="Mínimo 6 caracteres">
            <div class="modal-footer-actions">
                <button type="button" onclick="closeModals()" class="btn-icon btn-modal-cancel"><?php echo __('key_btn_cancel'); ?></button>
                <button type="submit" class="btn-primary w-100"><?php echo __('key_users_modal_pass_btn'); ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="dashboard-header" style="margin-bottom: 1rem;">
        <h2 class="welcome-title"><i class="fa-solid fa-users"></i> <?php echo __('key_users_title'); ?></h2>
        <div class="header-actions">
            <button class="btn-icon" title="<?php echo __('btn_export_pdf'); ?>" onclick="exportUsersPDF()">
                <i class="fa-solid fa-file-pdf text-danger" style="font-size: 1.2rem;"></i>
            </button>
            <button class="btn-primary" onclick="showSection('list')" title="<?php echo __('key_btn_list_title'); ?>">
                <i class="fa-solid fa-list"></i> <span><?php echo __('key_btn_list'); ?></span>
            </button>
            <button class="btn-icon" style="border:1px solid var(--border-color); border-radius: var(--radius); width: auto; padding: 0.6rem 1.2rem;" onclick="prepareCreateUser()" title="<?php echo __('key_btn_new_import_title'); ?>">
                <i class="fa-solid fa-plus"></i> <span><?php echo __('key_btn_new_import'); ?></span>
            </button>
        </div>
    </div>

    <div id="sec-list">
        <?php if ($isSuperAdmin || $isAcademy): ?>
            <div class="quick-filters-bar">
                <span class="quick-filter-label"><i class="fa-solid fa-bolt"></i> <?php echo __('quick_filters_title'); ?>:</span>
                <button class="btn-quick-filter" title="<?php echo __('qf_title_active_teachers'); ?>" onclick="applyUserFilter('active_teachers', this)"><?php echo __('qf_active_teachers'); ?></button>
                <button class="btn-quick-filter" title="<?php echo __('qf_title_inactive_teachers'); ?>" onclick="applyUserFilter('inactive_teachers', this)"><?php echo __('qf_inactive_teachers'); ?></button>
                <button class="btn-quick-filter" title="<?php echo __('qf_title_risk_students'); ?>" onclick="applyUserFilter('risk_students', this)"><?php echo __('qf_risk_students'); ?></button>
                <button class="btn-quick-filter" title="<?php echo __('qf_title_top_creators'); ?>" onclick="applyUserFilter('top_creators', this)"><?php echo __('qf_top_creators'); ?></button>
                <?php if ($isSuperAdmin): ?>
                    <button class="btn-quick-filter" title="<?php echo __('qf_title_blocked_users'); ?>" onclick="applyUserFilter('blocked_users', this)"><?php echo __('qf_blocked_users'); ?></button>
                    <button class="btn-quick-filter" title="<?php echo __('qf_title_new_academies'); ?>" onclick="applyUserFilter('new_academies', this)"><?php echo __('qf_new_academies'); ?></button>
                    <button class="btn-quick-filter" title="<?php echo __('qf_title_ghost_users'); ?>" onclick="applyUserFilter('ghost_users', this)"><?php echo __('qf_ghost_users'); ?></button>
                <?php endif; ?>
                <button class="btn-quick-filter" onclick="resetUserFilters(this)" title="<?php echo __('qf_title_reset'); ?>"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>

        <div class="search-panel">
            <div class="search-bar-wrapper">
                <input type="text" id="globalSearch" class="form-control" placeholder="<?php echo __('key_search_placeholder'); ?>" onkeyup="debounceUserLoad()">
                <button class="btn-icon" style="border:1px solid var(--border-color);" onclick="toggleAdvancedSearch(this)" title="<?php echo __('key_filter_advanced_title'); ?>">
                    <i class="fa-solid fa-filter"></i>
                </button>
            </div>
            <div class="advanced-search">
                <div class="grid-3">
                    <div>
                        <label class="block text-muted mb-2"><?php echo __('key_users_filter_role'); ?></label>
                        <select id="f_rol" class="form-control" onchange="loadAdminUsers()">
                            <option value=""><?php echo __('key_filter_all'); ?></option>
                            <option value="2"><?php echo __('key_users_role_2'); ?></option>
                            <option value="3"><?php echo __('key_users_role_3'); ?></option>
                            <option value="4"><?php echo __('key_users_role_4'); ?></option>
                            <option value="5"><?php echo __('key_users_role_5'); ?></option>
                            <option value="6"><?php echo __('key_users_role_6'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-muted mb-2"><?php echo __('key_users_filter_status'); ?></label>
                        <select id="f_estado" class="form-control" onchange="loadAdminUsers()">
                            <option value=""><?php echo __('key_filter_all'); ?></option>
                            <option value="1"><?php echo __('key_users_status_active'); ?></option>
                            <option value="0"><?php echo __('key_users_status_blocked'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-muted mb-2"><?php echo __('key_filter_date_from'); ?></label>
                        <input type="date" id="f_date_from" class="form-control" onchange="loadAdminUsers()">
                    </div>
                    <div>
                        <label class="block text-muted mb-2"><?php echo __('key_filter_date_to'); ?></label>
                        <input type="date" id="f_date_to" class="form-control" onchange="loadAdminUsers()">
                    </div>
                </div>
            </div>
        </div>

        <div class="pagination-bar">
            <div style="display:flex; align-items:center; gap:10px;">
                <label class="text-muted"><?php echo __('key_pagination_show'); ?> </label>
                <select id="limitSelectTop" onchange="changeUserLimit(this.value)" class="form-control" style="width:auto; padding:5px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="pagination-controls" id="pagControlsTop">
                <button class="btn-icon" style="border:1px solid var(--border-color)" onclick="prevUserPage()"><i class="fa-solid fa-chevron-left"></i></button>
                <span id="pageInfoTop" class="text-muted" style="margin:0 10px; font-size:0.9rem;"></span>
                <button class="btn-icon" style="border:1px solid var(--border-color)" onclick="nextUserPage()"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="sortable" onclick="changeUserSort('id_usuario')" style="width:60px; cursor:pointer;">
                            <?php echo __('key_header_id'); ?> <i id="icon-id_usuario" class="fa-solid fa-sort-down"></i>
                        </th>
                        <th class="sortable" onclick="changeUserSort('nombre')" style="cursor:pointer;">
                            <?php echo __('key_users_header_user'); ?> <i id="icon-nombre" class="fa-solid fa-sort"></i>
                        </th>
                        <th class="sortable" onclick="changeUserSort('id_rol')" style="cursor:pointer;">
                            <?php echo __('key_users_header_role'); ?> <i id="icon-id_rol" class="fa-solid fa-sort"></i>
                        </th>
                        <th class="sortable" onclick="changeUserSort('fiscal')" style="cursor:pointer;">
                            <?php echo __('key_users_header_fiscal'); ?> <i id="icon-fiscal" class="fa-solid fa-sort"></i>
                        </th>
                        <th class="sortable" onclick="changeUserSort('activo')" style="cursor:pointer;">
                            <?php echo __('key_users_header_status'); ?> <i id="icon-activo" class="fa-solid fa-sort"></i>
                        </th>
                        <th class="text-right"><?php echo __('key_header_actions'); ?></th>
                    </tr>
                </thead>
                <tbody id="usersTableBody"></tbody>
            </table>
        </div>

        <div class="pagination-bar">
            <div id="totalRecordsInfo" class="text-muted" style="font-size:0.9rem;"></div>
            <div class="pagination-controls" id="pagControlsBottom">
                <button class="btn-icon" style="border:1px solid var(--border-color)" onclick="prevUserPage()"><i class="fa-solid fa-chevron-left"></i></button>
                <span id="pageInfoBottom" class="text-muted" style="margin:0 10px; font-size:0.9rem;"></span>
                <button class="btn-icon" style="border:1px solid var(--border-color)" onclick="nextUserPage()"><i class="fa-solid fa-chevron-right"></i></button>
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

        <div id="tab-manual" class="tab-content active" style="margin-top:20px; text-align:center; padding: 40px;">
            <p class="text-muted mb-4"><?php echo __('key_users_single'); ?></p>
            <button class="btn-primary" onclick="openCreateModal()">
                <i class="fa-solid fa-user-plus"></i> <?php echo __('key_users_btn_new'); ?> (Manual)
            </button>
        </div>

        <div id="tab-import" class="tab-content hidden" style="margin-top:20px;">
            <div class="step-wizard">
                <span class="step-item active" id="st-1"><?php echo __('key_import_step1'); ?></span>
                <span class="step-item" id="st-2"><?php echo __('key_import_step2'); ?></span>
                <span class="step-item" id="st-3"><?php echo __('key_import_step3'); ?></span>
            </div>

            <div id="import-step-1">
                <p class="mb-3"><?php echo __('key_users_import_desc'); ?></p>
                <div class="import-area" id="dropZone">
                    <i class="fa-solid fa-file-csv" style="font-size:3rem; color:var(--primary); margin-bottom:1rem;"></i>
                    <p class="text-muted"><?php echo __('key_import_step2'); ?></p>
                    <input type="file" id="fileInput" accept=".csv, .xlsx, .xls, .ods" hidden>
                </div>
                <div class="mt-4 text-right">
                    <a href="api/usuarios.php?action=download_template" target="_blank" class="btn-icon" style="border:1px solid var(--border-color); width:auto; padding:0.5rem 1rem; border-radius:4px; text-decoration:none;">
                        <i class="fa-solid fa-download"></i> <?php echo __('key_import_download_template'); ?>
                    </a>
                </div>
            </div>

            <div id="import-mapping-step" class="hidden">
                <h4 style="color:var(--primary); margin-bottom:10px;"><?php echo __('key_import_mapping_title'); ?></h4>
                <p class="text-muted mb-3"><?php echo __('key_import_mapping_desc'); ?></p>
                <div class="mapping-container" style="max-height:400px; overflow-y:auto; border:1px solid var(--border-color); border-radius:var(--radius); padding:1rem;">
                    <table class="table-mini" style="width:100%;">
                        <thead>
                            <tr>
                                <th width="40%"><?php echo __('key_col_system'); ?></th>
                                <th><?php echo __('key_col_file'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="mappingTableBody"></tbody>
                    </table>
                </div>
                <div class="text-right mt-4 modal-footer-actions">
                    <button class="btn-icon btn-modal-cancel" onclick="resetImport()"><?php echo __('key_btn_cancel'); ?></button>
                    <button class="btn-primary" onclick="executeUserImport()"><?php echo __('key_import_btn_import'); ?></button>
                </div>
            </div>

            <div id="import-step-2" class="hidden">
                <div id="validation-log" class="mb-3" style="background:#1e293b; color:#00ff9d; padding:1rem; border-radius:5px; max-height:200px; overflow-y:auto; font-family:monospace;"></div>
                <div class="text-right">
                    <button class="btn-icon btn-modal-cancel" onclick="resetImport()"><?php echo __('key_btn_cancel'); ?></button>
                    <button class="btn-primary" id="btn-confirm-import" onclick="executeUserImport()" disabled><?php echo __('key_import_btn_import'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let userState = {
        page: 1,
        limit: 10,
        sortCol: 'id_usuario',
        sortOrder: 'ASC',
        totalPages: 1
    };
    let userSearchTimeout;
    const myRole = <?php echo $_SESSION['user_role']; ?>;
    let fileToImport = null;
    window.currentUserFilter = '';

    document.addEventListener('DOMContentLoaded', () => {
        loadAdminUsers();
        loadAdminPaises();
        if (myRole === 1) loadAcademiasList();
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        if (dropZone && fileInput) {
            dropZone.onclick = () => fileInput.click();
            fileInput.onchange = (e) => {
                if (e.target.files.length) {
                    fileToImport = e.target.files[0];
                    validateUserFile();
                }
            };
            dropZone.ondragover = (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            };
            dropZone.ondragleave = () => dropZone.classList.remove('dragover');
            dropZone.ondrop = (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                if (e.dataTransfer.files.length) {
                    fileToImport = e.dataTransfer.files[0];
                    validateUserFile();
                }
            };
        }
    });

    function previewUserPhoto(e) { 
        const r = new FileReader(); 
        r.onload = () => document.getElementById('modalPhotoPreview').src = r.result; 
        r.readAsDataURL(e.target.files[0]); 
    }

    function formatDateInput(date) {
        return date.toISOString().split('T')[0];
    }

    function resetUserFilters(btn) {
        document.querySelectorAll('.btn-quick-filter').forEach(b => b.classList.remove('active'));
        document.getElementById('f_rol').value = '';
        document.getElementById('f_estado').value = '';
        document.getElementById('f_date_from').value = '';
        document.getElementById('f_date_to').value = '';
        window.currentUserFilter = '';
        userState.sortCol = 'id_usuario';
        userState.sortOrder = 'ASC';
        loadAdminUsers();
    }

    function applyUserFilter(type, btn) {
        document.querySelectorAll('.btn-quick-filter').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active'); 

        document.getElementById('f_rol').value = '';
        document.getElementById('f_estado').value = '';
        document.getElementById('f_date_from').value = '';
        document.getElementById('f_date_to').value = '';
        userState.sortCol = 'id_usuario';
        userState.sortOrder = 'ASC';
        window.currentUserFilter = '';

        if (type === 'top_creators') {
            window.currentUserFilter = 'top_creators';
            userState.sortCol = 'total_preguntas';
            userState.sortOrder = 'DESC';
        } else if (type === 'inactive_teachers') {
            window.currentUserFilter = 'inactive_teachers';
            userState.sortCol = 'id_usuario';
        } else if (type === 'risk_students') {
            window.currentUserFilter = 'risk_students';
            userState.sortCol = 'promedio_puntos';
            userState.sortOrder = 'ASC';
        } else if (type === 'active_teachers') {
            window.currentUserFilter = 'active_teachers';
            document.getElementById('f_estado').value = '1';
        } else if (type === 'blocked_users') {
            document.getElementById('f_estado').value = '0';
        } else if (type === 'new_academies') {
            document.getElementById('f_rol').value = '2';
            const today = new Date();
            const past = new Date();
            past.setDate(today.getDate() - 30);
            document.getElementById('f_date_from').value = formatDateInput(past);
            document.getElementById('f_date_to').value = formatDateInput(today);
            userState.sortCol = 'id_usuario';
            userState.sortOrder = 'DESC';
        } else if (type === 'ghost_users') {
            document.getElementById('f_estado').value = '1';
            const limitDate = new Date();
            limitDate.setMonth(limitDate.getMonth() - 6);
            document.getElementById('f_date_to').value = formatDateInput(limitDate);
        }
        userState.page = 1;
        loadAdminUsers();
    }

    function changeUserSort(col) {
        if (userState.sortCol === col) userState.sortOrder = (userState.sortOrder === 'ASC') ? 'DESC' : 'ASC';
        else {
            userState.sortCol = col;
            userState.sortOrder = 'ASC';
        }
        document.querySelectorAll('th i').forEach(i => i.className = 'fa-solid fa-sort');
        const icon = document.getElementById(`icon-${col}`);
        if (icon) icon.className = `fa-solid fa-sort-${userState.sortOrder === 'ASC' ? 'up' : 'down'}`;
        loadAdminUsers();
    }

    function changeUserLimit(val) {
        userState.limit = parseInt(val);
        userState.page = 1;
        loadAdminUsers();
    }

    function prevUserPage() {
        if (userState.page > 1) {
            userState.page--;
            loadAdminUsers();
        }
    }

    function nextUserPage() {
        if (userState.page < userState.totalPages) {
            userState.page++;
            loadAdminUsers();
        }
    }

    function debounceUserLoad() {
        clearTimeout(userSearchTimeout);
        userSearchTimeout = setTimeout(() => {
            userState.page = 1;
            loadAdminUsers();
        }, 300);
    }

    async function loadAdminUsers() {
    const global = document.getElementById('globalSearch').value;
    const f_rol = document.getElementById('f_rol').value;
    const f_estado = document.getElementById('f_estado').value;
    const date_from = document.getElementById('f_date_from').value;
    const date_to = document.getElementById('f_date_to').value;
    const tbody = document.getElementById('usersTableBody');
    const params = new URLSearchParams({
        global, f_rol, f_estado, date_from, date_to,
        sort: userState.sortCol, order: userState.sortOrder,
        page: userState.page, limit: userState.limit,
        special_filter: window.currentUserFilter
    });

    try {
        const res = await fetch(`api/usuarios.php?${params.toString()}`);
        const data = await res.json();
        tbody.innerHTML = '';
        if (!data.data || data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted"><?php echo __("key_js_no_results"); ?></td></tr>';
            renderUserPagination(0);
            return;
        }
        
        data.data.forEach(u => {
            const statusIcon = u.activo == 1 ?
                `<i class="fa-solid fa-toggle-on text-success" style="font-size:1.2rem;"></i>` :
                `<i class="fa-solid fa-toggle-off text-muted" style="font-size:1.2rem;"></i>`;
            const toggleTitle = u.activo == 1 ? "<?php echo __('user_js_deactivate'); ?>" : "<?php echo __('user_js_activate'); ?>";
            
            let extraInfo = '';
            if (window.currentUserFilter === 'top_creators') {
                extraInfo = `<br><small class="text-muted"><i class="fa-solid fa-file-circle-question"></i> ${u.total_preguntas}</small>`;
            } else if (window.currentUserFilter === 'risk_students') {
                const colorClass = u.promedio_puntos < 50 ? 'text-danger' : 'text-warning';
                extraInfo = `<br><small class="${colorClass}"><i class="fa-solid fa-star"></i> Avg: ${u.promedio_puntos}</small>`;
            }

            // --- LÓGICA DE RENDERIZADO DUAL (WEB P + FALLBACK) ---
            const webpPath = u.foto_perfil || 'assets/img/default-avatar.png';
            // Fallback: Si el archivo es webp, intentamos cargar el nombre base con extensión común
            const fallbackPath = webpPath.includes('.webp') ? webpPath.replace('.webp', '.jpg') : webpPath;

            const pictureHtml = `
                <picture>
                    <source srcset="${webpPath}" type="image/webp">
                    <source srcset="${fallbackPath}" type="image/jpeg">
                    <img src="${webpPath}" 
                         style="width:30px; height:30px; border-radius:50%; object-fit:cover; border:1px solid var(--border-color);"
                         onerror="this.src='assets/img/default-avatar.png'">
                </picture>`;

            tbody.innerHTML += `
            <tr>
                <td><strong>${u.id_usuario}</strong></td>
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        ${pictureHtml}
                        <div><strong>${u.nombre}</strong><br><small class="text-muted">${u.correo}</small>${extraInfo}</div>
                    </div>
                </td>
                <td>${u.nombre_rol}</td>
                <td>${u.nif || '-'}<br><small class="text-muted">${u.razon_social || ''}</small></td>
                <td>${u.activo==1?'<span class="text-success font-bold"><?php echo __("key_users_status_active"); ?></span>':'<span class="text-danger font-bold"><?php echo __("key_users_status_blocked"); ?></span>'}</td>
                <td class="text-right">
                    <button onclick="toggleUserStatus(${u.id_usuario}, ${u.activo})" class="btn-icon" title="${toggleTitle}">${statusIcon}</button>
                    <button onclick="editUser(${u.id_usuario})" class="btn-icon" title="<?php echo __("key_js_edit_title"); ?>"><i class="fa-solid fa-pen"></i></button>
                    <button onclick="changePass(${u.id_usuario})" class="btn-icon text-warning" title="<?php echo __("key_users_pass_title"); ?>"><i class="fa-solid fa-key"></i></button>
                    <button onclick="deleteUser(${u.id_usuario})" class="btn-icon text-danger" title="<?php echo __("key_js_delete_title"); ?>"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>`;
        });
        renderUserPagination(data.total);
    } catch (e) {
        console.error(e);
    }
}

    function renderUserPagination(total) {
        userState.totalPages = Math.ceil(total / (userState.limit === -1 ? total : userState.limit)) || 1;
        const pageText = `<?php echo __('key_pagination_page'); ?> ${userState.page} <?php echo __('key_pagination_of'); ?> ${userState.totalPages}`;
        const infoTop = document.getElementById('pageInfoTop');
        const infoBottom = document.getElementById('pageInfoBottom');
        if (infoTop) infoTop.innerText = pageText;
        if (infoBottom) infoBottom.innerText = pageText;
        const totalInfo = document.getElementById('totalRecordsInfo');
        if (totalInfo) totalInfo.innerText = `<?php echo __('key_pagination_total'); ?> ${total}`;
        document.querySelectorAll('button[onclick="prevUserPage()"]').forEach(b => b.disabled = (userState.page === 1));
        document.querySelectorAll('button[onclick="nextUserPage()"]').forEach(b => b.disabled = (userState.page >= userState.totalPages));
    }

    function isValidNifFormat(nif) {
        if (!nif) return false;
        const validChars = 'TRWAGMYFPDXBNJZSQVHLCKE';
        const nifRexp = /^[0-9]{8}[TRWAGMYFPDXBNJZSQVHLCKE]$/i;
        const nieRexp = /^[XYZ][0-9]{7}[TRWAGMYFPDXBNJZSQVHLCKE]$/i;
        const str = nif.toString().toUpperCase();
        if (!nifRexp.test(str) && !nieRexp.test(str)) return false;
        const nie = str.replace(/^[X]/, '0').replace(/^[Y]/, '1').replace(/^[Z]/, '2');
        const letter = str.substr(-1);
        const charIndex = parseInt(nie.substr(0, 8)) % 23;
        return validChars.charAt(charIndex) === letter;
    }

    function validateNifVisual(input) {
        const errorMsg = document.getElementById('nifError');
        const pais = document.getElementById('in_pais').value;
        if (!input.value || (pais && pais !== 'ES')) {
            if (errorMsg) errorMsg.classList.add('hidden');
            input.style.borderColor = '';
            return;
        }
        const valid = isValidNifFormat(input.value);
        input.style.borderColor = valid ? 'var(--success-color)' : 'var(--danger-color)';
        if (errorMsg) {
            if (valid) errorMsg.classList.add('hidden');
            else errorMsg.classList.remove('hidden');
        }
    }

    function toggleAdvancedSearch(button) {
        const searchPanel = button.closest('.search-panel');
        if (searchPanel) {
            const adv = searchPanel.querySelector('.advanced-search');
            adv.classList.toggle('open');
        }
    }

    function showSection(sec) {
        document.getElementById('sec-list').classList.add('hidden');
        document.getElementById('sec-create').classList.add('hidden');
        document.getElementById('sec-' + sec).classList.remove('hidden');
        if (sec === 'list') loadAdminUsers();
    }

    function prepareCreateUser() {
        showSection('create');
        document.getElementById('btn-tab-manual').click();
    }

    function openSubTab(name, btn) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
        document.getElementById('tab-' + name).classList.remove('hidden');
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active');
            b.style.color = 'var(--text-muted)';
            b.style.borderBottomColor = 'transparent';
        });
        btn.classList.add('active');
        btn.style.color = 'var(--primary)';
        btn.style.borderBottomColor = 'var(--primary)';
    }

    function closeModals() {
        document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
    }

    function openCreateModal() {
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.getElementById('modalTitle').innerText = '<?php echo __('key_users_modal_create_title'); ?>';
        document.getElementById('divPassCreate').style.display = 'block';
        document.getElementById('in_pass').required = true;
        document.getElementById('modalPhotoPreview').src = 'assets/img/default-avatar.png';
        configureRoleSelect();
        document.getElementById('in_nif').style.borderColor = '';
        const err = document.getElementById('nifError');
        if (err) err.classList.add('hidden');
        document.getElementById('userModal').classList.add('active');
    }

    async function editUser(id) {
        const res = await fetch(`api/usuarios.php?id=${id}`);
        const u = await res.json();
        document.getElementById('userId').value = u.id_usuario;
        document.getElementById('modalTitle').innerText = '<?php echo __('key_users_modal_edit_title'); ?>' + u.nombre;
        document.getElementById('in_nombre').value = u.nombre;
        document.getElementById('in_correo').value = u.correo;
        document.getElementById('in_idioma').value = u.idioma_pref || 'es';
        configureRoleSelect();
        document.getElementById('in_rol').value = u.id_rol;
        if (myRole === 1 && u.id_padre) document.getElementById('in_id_padre').value = u.id_padre;
        toggleAcademySelect();
        document.getElementById('in_activo').value = u.activo;
        document.getElementById('in_razon').value = u.razon_social || '';
        document.getElementById('in_negocio').value = u.nombre_negocio || '';
        document.getElementById('in_nif').value = u.nif || '';
        document.getElementById('in_roi').value = u.roi || '';
        document.getElementById('in_tel').value = u.telefono || '';
        document.getElementById('in_dir').value = u.direccion || '';
        document.getElementById('in_dir_num').value = u.direccion_numero || '';
        document.getElementById('in_cp').value = u.cp || '';
        
        document.getElementById('modalPhotoPreview').src = u.foto_perfil ? u.foto_perfil : 'assets/img/default-avatar.png';

        const paisSelect = document.getElementById('in_pais');
        paisSelect.value = u.id_pais || 'ES';
        toggleAdminGeo();
        if (u.id_pais === 'ES') {
            await loadAdminProvincias();
            document.getElementById('in_prov').value = u.id_provincia || '';
            if (u.id_provincia) {
                await loadAdminCiudades(u.id_provincia);
                document.getElementById('in_ciud').value = u.id_ciudad || '';
            }
        }
        validateNifVisual(document.getElementById('in_nif'));
        document.getElementById('divPassCreate').style.display = 'none';
        document.getElementById('in_pass').required = false;
        document.getElementById('userModal').classList.add('active');
    }

    async function toggleUserStatus(id, currentStatus) {
        const newStatus = currentStatus == 1 ? 0 : 1;
        try {
            const res = await fetch('api/usuarios.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_status', id_usuario: id, nuevo_estado: newStatus })
            });
            const json = await res.json();
            if (json.success) loadAdminUsers();
            else alert('Error: ' + json.error);
        } catch (e) { alert("Error de conexión"); }
    }

    function changePass(id) {
        document.getElementById('passFormAdmin').reset();
        document.getElementById('passUserId').value = id;
        document.getElementById('passModal').classList.add('active');
    }

    async function deleteUser(id) {
        if(!confirm('<?php echo __('key_js_delete_confirm'); ?>')) return;
        try {
            const res = await fetch('api/usuarios.php', { 
                method: 'DELETE', 
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({id}) 
            });
            const json = await res.json();
            if(json.success) loadAdminUsers();
            else alert(json.error);
        } catch(e) { alert("Error de conexión al intentar borrar el usuario."); }
    }

    async function loadAcademiasList() {
        const res = await fetch('api/usuarios.php?type=academias');
        const json = await res.json();
        const sel = document.getElementById('in_id_padre');
        sel.innerHTML = '<option value=""><?php echo __('key_users_modal_select_academy'); ?></option>';
        json.data.forEach(a => sel.innerHTML += `<option value="${a.id_usuario}">${a.nombre}</option>`);
    }

    function configureRoleSelect() {
        const sel = document.getElementById('in_rol');
        sel.innerHTML = '';
        if (myRole === 1) {
            sel.innerHTML = '<option value="1">SuperAdmin</option><option value="2">Academia</option><option value="4">Profe Indep</option><option value="3">Profe</option><option value="5">Gestor Contenido</option><option value="6">Alumno</option>';
        } else if (myRole === 2) {
            sel.innerHTML = '<option value="3">Profe</option><option value="5">Gestor Contenido</option><option value="6">Alumno</option>';
        } else if (myRole === 4) {
            sel.innerHTML = '<option value="6">Alumno</option>'; 
        }
        toggleAcademySelect();
    }

    function toggleAcademySelect() {
        const rol = document.getElementById('in_rol').value;
        const divAcad = document.getElementById('divSelectAcademia');
        const divFiscal = document.getElementById('fiscalSpecificFields');
        
        // CORRECCIÓN CLAVE: Mostrar u ocultar campos fiscales
        const isStudent = (rol === '6');
        divFiscal.style.display = isStudent ? 'none' : 'block';
        
        // IMPORTANTE: Si está oculto, quitar required para que el formulario se envíe
        const nifInput = document.getElementById('in_nif');
        nifInput.required = !isStudent;

        if (myRole === 1 && (rol === '3' || rol === '5')) {
            divAcad.classList.remove('hidden');
            document.getElementById('in_id_padre').required = true;
        } else {
            divAcad.classList.add('hidden');
            document.getElementById('in_id_padre').required = false;
            document.getElementById('in_id_padre').value = '';
        }
    }

    async function loadAdminPaises() {
        const res = await fetch('api/geo.php?type=paises');
        const data = await res.json();
        const sel = document.getElementById('in_pais');
        sel.innerHTML = '<option value="">Sel...</option>';
        data.forEach(p => sel.innerHTML += `<option value="${p.id}">${p.nombre}</option>`);
    }

    function toggleAdminGeo() {
        const div = document.getElementById('adminSpainFields');
        const p = document.getElementById('in_pais').value;
        const isES = (p === 'ES');
        if (isES) {
            div.classList.remove('hidden');
            loadAdminProvincias();
        } else {
            div.classList.add('hidden');
        }
        document.getElementById('in_prov').required = isES;
        document.getElementById('in_ciud').required = isES;
    }

    async function loadAdminProvincias() {
        const res = await fetch('api/geo.php?type=provincias&id=ES');
        const data = await res.json();
        const sel = document.getElementById('in_prov');
        sel.innerHTML = '<option value="">Provincia...</option>';
        data.forEach(p => sel.innerHTML += `<option value="${p.id}">${p.nombre}</option>`);
    }

    async function loadAdminCiudades(pid) {
        if (!pid) return;
        const res = await fetch(`api/geo.php?type=ciudades&id=${pid}`);
        const data = await res.json();
        const sel = document.getElementById('in_ciud');
        sel.innerHTML = '<option value="">Ciudad...</option>';
        data.forEach(c => sel.innerHTML += `<option value="${c.id}">${c.nombre}</option>`);
    }

    document.getElementById('userForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        if(fd.get('id_usuario')) fd.append('action', 'update_user_crud');
        const res = await fetch('api/usuarios.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            closeModals();
            loadAdminUsers();
        } else alert(json.error);
    });

    document.getElementById('passFormAdmin').addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target));
        const res = await fetch('api/usuarios.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            alert('<?php echo __('key_js_pass_success'); ?>');
            closeModals();
        } else alert(json.error);
    });

    function exportUsersPDF() {
        const search = document.getElementById('globalSearch').value;
        window.open(`api/export_pdf.php?type=usuarios&search=${search}`, '_blank');
    }

    // --- IMPORTACIÓN ---
    async function validateUserFile() {
        document.getElementById('import-step-1').classList.add('hidden');
        document.getElementById('import-mapping-step').classList.add('hidden');
        document.getElementById('import-step-2').classList.add('hidden');
        const fd = new FormData();
        fd.append('action', 'validate_import');
        fd.append('archivo', fileToImport);
        document.getElementById('import-step-2').classList.remove('hidden');
        document.getElementById('validation-log').innerHTML = "<?php echo __('key_js_import_validating'); ?>";
        document.getElementById('btn-confirm-import').disabled = true;
        try {
            const res = await fetch('api/usuarios.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.status === 'ok') {
                document.getElementById('validation-log').innerHTML = `<span class="text-success">OK. ${json.filas_validas} registros detectados.</span>`;
                document.getElementById('btn-confirm-import').disabled = false;
            } else if (json.status === 'need_mapping') {
                document.getElementById('import-step-2').classList.add('hidden');
                renderUserMappingInterface(json.headers);
            } else {
                document.getElementById('validation-log').innerHTML = `<span class="text-danger">${json.mensaje}</span>`;
            }
        } catch (e) {
            document.getElementById('validation-log').innerHTML = `<span class="text-danger">Error server</span>`;
        }
    }

    function resetImport() {
        fileToImport = null;
        document.getElementById('import-step-1').classList.remove('hidden');
        document.getElementById('import-step-2').classList.add('hidden');
        document.getElementById('import-mapping-step').classList.add('hidden');
        document.getElementById('fileInput').value = '';
        document.querySelectorAll('.step-item').forEach(s => s.classList.remove('active'));
        document.getElementById('st-1').classList.add('active');
    }

    async function executeUserImport() {
        let mappingData = null;
        const mappingDiv = document.getElementById('import-mapping-step');
        if (!mappingDiv.classList.contains('hidden')) {
            mappingData = {};
            document.querySelectorAll('.mapping-select').forEach(sel => {
                if (sel.value !== "") mappingData[sel.dataset.key] = parseInt(sel.value);
            });
        }
        const btn = document.getElementById('btn-confirm-import');
        if (btn) {
            btn.disabled = true;
            btn.innerText = "<?php echo __('key_js_import_importing'); ?>";
        }
        const fd = new FormData();
        fd.append('action', 'execute_import');
        fd.append('archivo', fileToImport);
        if (mappingData) fd.append('mapping', JSON.stringify(mappingData));
        try {
            const res = await fetch('api/usuarios.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.status === 'ok') {
                alert(json.mensaje);
                location.reload();
            } else {
                alert(json.mensaje);
                if (btn) {
                    btn.disabled = false;
                    btn.innerText = "<?php echo __('key_import_btn_import'); ?>";
                }
            }
        } catch (e) { console.error(e); }
    }

    function renderUserMappingInterface(headers) {
        const container = document.getElementById('import-mapping-step');
        const tbody = document.getElementById('mappingTableBody');
        tbody.innerHTML = '';
        const fields = [
            { key: 'nombre', label: "<?php echo __('key_field_name'); ?> *" }, 
            { key: 'correo', label: "<?php echo __('key_field_email'); ?> *" }, 
            { key: 'contrasena', label: "<?php echo __('key_field_password'); ?> *" }, 
            { key: 'rol', label: "<?php echo __('key_field_role'); ?>" }, 
            { key: 'telefono', label: "<?php echo __('key_field_phone'); ?>" }, 
            { key: 'nif', label: "<?php echo __('key_field_nif'); ?>" }, 
            { key: 'razon_social', label: "<?php echo __('business_name_label'); ?>" }, 
            { key: 'direccion', label: "<?php echo __('address_label'); ?>" }, 
            { key: 'cp', label: "<?php echo __('zip_code_label'); ?>" }, 
            { key: 'pais', label: "<?php echo __('country_label'); ?>" }
        ];
        fields.forEach(field => {
            let options = `<option value="">-- Ignorar --</option>`;
            let matchClass = 'unmatched';
            headers.forEach((h, index) => {
                const hLow = h.toLowerCase().trim();
                const fLow = field.key.toLowerCase();
                let selected = '';
                if ((fLow === 'nombre' && hLow.includes('nomb')) || 
                    (fLow === 'correo' && (hLow.includes('corr') || hLow.includes('email'))) || 
                    (fLow === 'contrasena' && (hLow.includes('pass') || hLow.includes('contra'))) || 
                    (fLow === 'rol' && (hLow.includes('rol') || hLow.includes('type'))) || 
                    (fLow === 'telefono' && (hLow.includes('tel') || hLow.includes('phon'))) || 
                    (fLow === 'nif' && (hLow.includes('nif') || hLow.includes('dni'))) || 
                    (fLow === 'razon_social' && (hLow.includes('razon') || hLow.includes('social'))) || 
                    (fLow === 'direccion' && (hLow.includes('direcc') || hLow.includes('address'))) || 
                    (fLow === 'cp' && (hLow.includes('postal') || hLow.includes('cp'))) || 
                    (fLow === 'pais' && (hLow.includes('pais') || hLow.includes('country')))) {
                    selected = 'selected';
                    matchClass = 'matched';
                }
                options += `<option value="${index}" ${selected}>${h}</option>`;
            });
            tbody.innerHTML += `<tr><td><strong>${field.label}</strong></td><td><select class="mapping-select ${matchClass}" data-key="${field.key}">${options}</select></td></tr>`;
        });
        container.classList.remove('hidden');
        document.querySelectorAll('.step-item').forEach(s => s.classList.remove('active'));
        document.getElementById('st-2').classList.add('active');
    }
</script>
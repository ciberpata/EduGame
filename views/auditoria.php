<?php
// views/auditoria.php
if (!in_array($_SESSION['user_role'], [1, 2, 4])) { echo "<script>window.location='dashboard';</script>"; exit; }
$isSuperAdmin = ($_SESSION['user_role'] == 1);
$role = $_SESSION['user_role']; // Variable role necesaria para la lógica
?>
<style>
    /* --- ESTILOS BASE (Mantenidos) --- */
    .search-panel { background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius); margin-bottom: 2rem; border: 1px solid var(--border-color); }
    .log-details { font-size: 0.85rem; color: var(--text-muted); max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .log-details span { margin-right: 10px; display: inline-block; }
    .log-details b { color: var(--text-main); }
    th.sortable { cursor: pointer; user-select: none; }
    th.sortable:hover { background-color: rgba(0,0,0,0.05); }
    
    /* Filtros Rápidos */
    .quick-filters-bar {
        display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;
        padding: 10px; background: var(--bg-body); border-radius: var(--radius); border: 1px solid var(--border-color);
        align-items: center;
    }
    .btn-quick-filter {
        border: 1px solid var(--border-color); background: var(--bg-surface);
        padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; cursor: pointer;
        display: flex; align-items: center; gap: 5px; transition: all 0.2s;
        color: var(--text-muted); white-space: nowrap;
    }
    .btn-quick-filter:hover { border-color: var(--primary); color: var(--primary); }
    .btn-quick-filter.active {
        background: var(--primary-light); border-color: var(--primary); color: var(--primary); font-weight: bold;
        box-shadow: 0 0 0 1px var(--primary);
    }

    /* Contenedor Tabla */
    .table-container {
        width: 100%;
        overflow-x: auto; /* Scroll horizontal automático */
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1rem;
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
    }
    
    /* Paginación */
    .pagination-bar {
        display: flex; justify-content: space-between; align-items: center; padding: 10px 0;
    }

    /* Grid de búsqueda por defecto (Desktop) */
    .search-grid {
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
        gap: 10px;
    }

    /* --- MEDIA QUERIES (RESPONSIVE MÓVIL) --- */
    @media (max-width: 768px) {
        /* Título y cabecera apilados */
        .card > div:first-child {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        /* Filtros rápidos: Botones más grandes y ocupan ancho disponible */
        .quick-filters-bar {
            gap: 8px;
        }
        .btn-quick-filter {
            flex: 1 1 auto; /* Crecen para llenar huecos */
            justify-content: center;
            padding: 8px 10px; /* Más fáciles de tocar */
        }
        
        /* Panel de búsqueda: Inputs uno debajo de otro */
        .search-panel {
            padding: 1rem;
        }
        .search-grid {
            grid-template-columns: 1fr !important; /* Forzar 1 columna */
        }

        /* Paginación apilada */
        .pagination-bar {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }
        .pagination-controls {
            width: 100%;
            display: flex;
            justify-content: space-between;
        }

        /* Tabla: Ajuste de fuente y padding */
        table th, table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.85rem;
            white-space: nowrap; /* Evita que el texto se rompa feo, fuerza el scroll */
        }
    }
</style>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <h2 style="margin:0;"><i class="fa-solid fa-clipboard-list"></i> <?php echo __('audit_title'); ?></h2>
    </div>

    <?php if($isSuperAdmin || $role == 2): ?>
    <div class="quick-filters-bar">
        <span style="font-size:0.85rem; font-weight:bold; color:var(--primary); align-self:center; margin-right:5px; white-space: nowrap;">
            <i class="fa-solid fa-bolt"></i> <?php echo __('quick_filters_title'); ?>:
        </span>
        <?php if($role == 2): ?>
        <button class="btn-quick-filter" onclick="applyAuditFilter('staff_only', this)">
            <?php echo __('qf_staff_audit'); ?>
        </button>
        <?php endif; ?>
        <button class="btn-quick-filter" onclick="applyAuditFilter('errors', this)">
            <?php echo __('qf_error_audit'); ?>
        </button>
        <button class="btn-quick-filter" onclick="applyAuditFilter('security_alert', this)">
            <?php echo __('qf_security_alert'); ?>
        </button>
        <button class="btn-quick-filter" onclick="applyAuditFilter('failed_login', this)">
            <?php echo __('qf_failed_login'); ?>
        </button>
        <button class="btn-quick-filter" onclick="resetAuditFilters(this)" title="<?php echo __('qf_reset'); ?>">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <?php endif; ?>

    <div class="search-panel">
        <div style="margin-bottom: 10px;">
            <input type="text" id="aSearch" class="form-control" placeholder="<?php echo __('audit_search_placeholder'); ?>" onkeyup="debounceAudit()">
        </div>
        <div class="search-grid">
            <select id="aAction" class="form-control" onchange="loadAudit()">
                <option value=""><?php echo __('audit_filter_action'); ?> (<?php echo __('key_filter_all'); ?>)</option>
                <option value="LOGIN"><?php echo __('audit_action_LOGIN'); ?></option>
                <option value="INSERT"><?php echo __('audit_action_INSERT'); ?></option>
                <option value="UPDATE"><?php echo __('audit_action_UPDATE'); ?></option>
                <option value="DELETE"><?php echo __('audit_action_DELETE'); ?></option>
                <option value="JUEGO_JOIN"><?php echo __('audit_action_JUEGO_JOIN'); ?></option>
                <option value="JUEGO_RESPUESTA"><?php echo __('audit_action_JUEGO_RESPUESTA'); ?></option>
                <option value="EXPORT_PDF"><?php echo __('audit_action_EXPORT_PDF'); ?></option>
            </select>
            
            <select id="aEntity" class="form-control" onchange="loadAudit()">
                <option value=""><?php echo __('audit_filter_entity'); ?> (<?php echo __('key_filter_all'); ?>)</option>
                
                <?php if($role != 4): ?>
                <option value="usuarios">Usuarios</option>
                <?php endif; ?>
                
                <option value="partidas">Partidas</option>
                <option value="preguntas">Preguntas</option>
                <option value="sesion">Sesión</option>
            </select>
            
            <div>
                <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:2px;"><?php echo __('key_filter_date_from'); ?></label>
                <input type="date" id="aDateFrom" class="form-control" onchange="loadAudit()">
            </div>
            <div>
                <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:2px;"><?php echo __('key_filter_date_to'); ?></label>
                <input type="date" id="aDateTo" class="form-control" onchange="loadAudit()">
            </div>
        </div>
    </div>

    <div class="pagination-bar">
        <div>
            <label><?php echo __('key_pagination_show'); ?> </label>
            <select id="limitSelectTop" onchange="changeLimitAudit(this.value)" style="padding:5px; border-radius:4px; border:1px solid var(--border-color);">
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
        <div class="pagination-controls">
            <button class="btn-prev" onclick="prevAPage()"><i class="fa-solid fa-chevron-left"></i></button>
            <span class="pageInfo" style="margin:0 10px;"></span>
            <button class="btn-next" onclick="nextAPage()"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th class="sortable" onclick="changeAuditSort('fecha')"><?php echo __('audit_col_date'); ?> <i id="icon-fecha" class="fa-solid fa-sort-down"></i></th>
                    <th class="sortable" onclick="changeAuditSort('usuario')"><?php echo __('audit_col_user'); ?> <i id="icon-usuario" class="fa-solid fa-sort"></i></th>
                    <th class="sortable" onclick="changeAuditSort('accion')"><?php echo __('audit_col_action'); ?> <i id="icon-accion" class="fa-solid fa-sort"></i></th>
                    <th class="sortable" onclick="changeAuditSort('entidad')"><?php echo __('audit_col_entity'); ?> <i id="icon-entidad" class="fa-solid fa-sort"></i></th>
                    <th><?php echo __('audit_col_details'); ?></th>
                    <th><?php echo __('audit_col_ip'); ?></th>
                </tr>
            </thead>
            <tbody id="auditTableBody"></tbody>
        </table>
    </div>
    
    <div class="pagination-bar">
        <div id="totalRecordsAudit" class="text-muted"><?php echo __('key_pagination_total'); ?> 0</div>
        <div class="pagination-controls">
            <button class="btn-prev" onclick="prevAPage()"><i class="fa-solid fa-chevron-left"></i></button>
            <span class="pageInfo" style="margin:0 10px;"></span>
            <button class="btn-next" onclick="nextAPage()"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
</div>

<script>
// --- Variables Globales de Estado ---
let aState = { page: 1, limit: 15, sort: 'fecha', order: 'DESC', totalPages: 1 };
let aTimer;
const currentLang = "<?php echo $_SESSION['lang'] ?? 'es'; ?>";

// --- Variables para Filtros Especiales ---
let customActionFilter = null; // Para acciones múltiples (ej: UPDATE,DELETE)
let excludeSelf = false;       // Para filtrar plantilla (Rol Academia)

// Mapas de Traducción
const actionMap = {
    'LOGIN': "<?php echo __('audit_action_LOGIN'); ?>",
    'INSERT': "<?php echo __('audit_action_INSERT'); ?>",
    'UPDATE': "<?php echo __('audit_action_UPDATE'); ?>",
    'DELETE': "<?php echo __('audit_action_DELETE'); ?>",
    'JUEGO_JOIN': "<?php echo __('audit_action_JUEGO_JOIN'); ?>",
    'JUEGO_RESPUESTA': "<?php echo __('audit_action_JUEGO_RESPUESTA'); ?>",
    'EXPORT_PDF': "<?php echo __('audit_action_EXPORT_PDF'); ?>"
};

const detailsMap = {
    'status': "Estado", 'action': "Acción", 'inserted': "Insertados", 'skipped': "Omitidos",
    'new_owner': "Nuevo Propietario", 'count': "Cantidad", 'ids_implicados': "IDs",
    'owner_was': "Propietario anterior", 'nombre': "Nombre", 'correo': "Correo",
    'success': "Éxito", 'error': "Error", 'manual_update': "Edición Manual",
    'manual_create': "Creación Manual", 'reassign': "Reasignación"
};

const nullMsg = "<?php echo __('audit_null_msg'); ?>";

document.addEventListener('DOMContentLoaded', loadAudit);

// --- Funciones Auxiliares ---
function formatDateInput(date) { return date.toISOString().split('T')[0]; }

// --- LÓGICA DE RESETEO DE FILTROS ---
function resetAuditFilters(btn) {
    document.querySelectorAll('.btn-quick-filter').forEach(b => b.classList.remove('active'));
    document.getElementById('aSearch').value = '';
    document.getElementById('aAction').value = '';
    document.getElementById('aEntity').value = '';
    document.getElementById('aDateFrom').value = '';
    document.getElementById('aDateTo').value = '';
    customActionFilter = null;
    excludeSelf = false; 
    loadAudit();
}

// --- LÓGICA PRINCIPAL DE FILTROS RÁPIDOS ---
function applyAuditFilter(type, btn) {
    document.querySelectorAll('.btn-quick-filter').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const today = new Date();
    const todayStr = formatDateInput(today);

    document.getElementById('aSearch').value = '';
    document.getElementById('aAction').value = ''; 
    document.getElementById('aEntity').value = '';
    document.getElementById('aDateFrom').value = '';
    document.getElementById('aDateTo').value = '';
    
    customActionFilter = null;
    excludeSelf = false;

    if (type === 'errors') {
        document.getElementById('aSearch').value = 'error'; 
    }
    else if (type === 'security_alert') {
        document.getElementById('aEntity').value = 'usuarios';
        document.getElementById('aDateFrom').value = todayStr;
        document.getElementById('aDateTo').value = todayStr;
        customActionFilter = 'UPDATE,DELETE';
    }
    else if (type === 'failed_login') {
        document.getElementById('aAction').value = 'LOGIN';
        document.getElementById('aSearch').value = 'FALLIDO';
    }
    else if (type === 'staff_only') {
        excludeSelf = true;
    }
    
    aState.page = 1; 
    loadAudit();
}

// --- Paginación y Ordenación ---
function debounceAudit() { clearTimeout(aTimer); aTimer = setTimeout(() => { aState.page=1; loadAudit(); }, 300); }
function prevAPage() { if(aState.page > 1) { aState.page--; loadAudit(); } }
function nextAPage() { if(aState.page < aState.totalPages) { aState.page++; loadAudit(); } }
function changeLimitAudit(val) { aState.limit = parseInt(val); aState.page = 1; loadAudit(); }

function changeAuditSort(col) {
    if (aState.sort === col) {
        aState.order = (aState.order === 'ASC') ? 'DESC' : 'ASC';
    } else {
        aState.sort = col;
        aState.order = 'ASC';
    }
    document.querySelectorAll('th i').forEach(i => i.className = 'fa-solid fa-sort');
    document.getElementById(`icon-${col}`).className = `fa-solid fa-sort-${aState.order === 'ASC' ? 'up' : 'down'}`;
    loadAudit();
}

// --- Renderizado de Fecha y Detalles ---
function formatDate(isoDate) {
    const d = new Date(isoDate);
    if (currentLang === 'en') return d.toLocaleString('en-US');
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

function formatDetails(rawDetails) {
    if (!rawDetails || rawDetails === 'null' || rawDetails.trim() === '') {
        return `<span style="color:#9ca3af; font-style:italic;">${nullMsg}</span>`;
    }
    try {
        const obj = JSON.parse(rawDetails);
        let outputHTML = '';
        for (const [key, value] of Object.entries(obj)) {
            const label = detailsMap[key] || key;
            const displayValue = (typeof value === 'object') ? JSON.stringify(value) : (detailsMap[value] || value);
            outputHTML += `<span><b>${label}:</b> ${displayValue}</span> `;
        }
        return outputHTML;
    } catch (e) {
        return rawDetails;
    }
}

// --- CARGA DE DATOS (AJAX) ---
async function loadAudit() {
    const search = document.getElementById('aSearch').value;
    const action = customActionFilter ? customActionFilter : document.getElementById('aAction').value;
    const entity = document.getElementById('aEntity').value;
    const dateFrom = document.getElementById('aDateFrom').value;
    const dateTo = document.getElementById('aDateTo').value;
    
    const params = new URLSearchParams({ 
        page: aState.page, 
        limit: aState.limit, 
        search, 
        action, 
        entity,
        date_from: dateFrom, 
        date_to: dateTo,
        sort: aState.sort, 
        order: aState.order,
        exclude_self: excludeSelf 
    });
    
    const tbody = document.getElementById('auditTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center"><?php echo __("key_js_loading"); ?></td></tr>';

    try {
        const res = await fetch(`api/auditoria.php?${params}`);
        const json = await res.json();
        
        tbody.innerHTML = '';
        if(!json.data || json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted"><?php echo __("key_js_no_results"); ?></td></tr>';
            updateControls(0, 0);
            return;
        }

        json.data.forEach(Row => {
            const detailsDisplay = formatDetails(Row.detalles);
            let actionText = actionMap[Row.accion] || Row.accion;
            
            let color = 'var(--text-muted)';
            if(Row.accion === 'DELETE') color = 'var(--danger-color)';
            else if(Row.accion === 'INSERT') color = 'var(--success-color)';
            else if(Row.accion === 'LOGIN') color = 'var(--primary)';
            else if(Row.accion === 'UPDATE') color = '#f59e0b';

            tbody.innerHTML += `
                <tr>
                    <td><small>${formatDate(Row.fecha)}</small></td>
                    <td><strong>${Row.nombre_usuario || 'Sistema/Eliminado'}</strong></td>
                    <td><span style="color:${color}; font-weight:bold;">${actionText}</span></td>
                    <td>${Row.entidad} <small class="text-muted">#${Row.id_afectado || '?'}</small></td>
                    <td class="log-details" title='${Row.detalles || ""}'>${detailsDisplay}</td>
                    <td><small>${Row.ip || '-'}</small></td>
                </tr>`;
        });
        
        aState.totalPages = json.pages;
        updateControls(json.total);
    } catch(e) { 
        console.error(e); 
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error de conexión</td></tr>';
    }
}

function updateControls(total) {
    const totalPages = Math.ceil(total / aState.limit) || 1;
    const txt = `<?php echo __("key_pagination_page"); ?> ${aState.page} / ${totalPages}`;
    document.querySelectorAll('.pageInfo').forEach(el => el.innerText = txt);
    document.getElementById('totalRecordsAudit').innerText = `<?php echo __('key_pagination_total'); ?> ${total}`;
    
    document.querySelectorAll('.btn-prev').forEach(b => b.disabled = (aState.page <= 1));
    document.querySelectorAll('.btn-next').forEach(b => b.disabled = (aState.page >= totalPages));
}
</script>
<?php
// views/diagnostico.php
if (!in_array($_SESSION['user_role'], [1, 2, 4])) { echo "<div class='card'><h2>Acceso Denegado</h2></div>"; exit; }
?>
<div class="fade-in">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:10px;">
        <h2 style="margin:0; font-size:1.5rem;"><i class="fa-solid fa-heart-pulse"></i> <?php echo __('diag_title'); ?></h2>
        <button class="btn-primary" onclick="loadDiagnostics()">
            <i class="fa-solid fa-rotate"></i> <span class="mobile-hidden"><?php echo __('diag_btn_refresh'); ?></span>
        </button>
    </div>

    <div class="dashboard-grid" id="diag-container">
        
        <div class="card diag-card" draggable="true">
            <h3 style="color:var(--primary); margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                <span><i class="fa-solid fa-database"></i> <?php echo __('diag_db_status'); ?></span>
                <i class="fa-solid fa-grip-lines text-muted" style="cursor:grab"></i>
            </h3>
            <div id="tableStatusContent">
                <p class="text-center text-muted"><i class="fa-solid fa-circle-notch fa-spin"></i> <?php echo __('diag_loading'); ?></p>
            </div>
        </div>

        <div class="card diag-card" draggable="true">
            <h3 style="color:var(--primary); margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                <span><i class="fa-solid fa-check-double"></i> <?php echo __('diag_data_quality'); ?></span>
                <i class="fa-solid fa-grip-lines text-muted"></i>
            </h3>
            <div id="qualityCheckContent">
                <p class="text-center text-muted"><i class="fa-solid fa-circle-notch fa-spin"></i> <?php echo __('diag_analyzing'); ?></p>
            </div>
        </div>

        <div class="card diag-card" draggable="true" style="grid-column: span 2;">
            <h3 style="color:var(--primary); margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                <span><i class="fa-solid fa-terminal"></i> <?php echo __('diag_sql_runner'); ?></span>
                <i class="fa-solid fa-grip-lines text-muted"></i>
            </h3>
            
            <div class="diag-actions">
                <input type="text" id="sqlInput" class="form-control" placeholder="SELECT * FROM usuarios LIMIT 5" style="font-family:monospace; flex:1; min-width:200px;">
                <button class="btn-primary" onclick="runSQL()"><?php echo __('diag_btn_run'); ?></button>
            </div>
            
            <div id="sqlOutput" class="diag-console hidden"></div>
        </div>

        <div class="card diag-card" draggable="true" style="border-left: 4px solid var(--danger-color);">
            <h3 style="color:var(--danger-color); margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                <span><i class="fa-solid fa-trash-can"></i> <?php echo __('diag_wipe_games'); ?></span>
                <i class="fa-solid fa-grip-lines text-muted"></i>
            </h3>
            <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1rem;">
                <?php echo __('diag_wipe_desc'); ?>
            </p>
            
            <div style="background: #fff1f2; padding: 10px; border-radius: 5px; border: 1px solid #fda4af;">
                <label style="font-size:0.8rem; color:#881337; display:block; margin-bottom:5px;"><?php echo __('diag_confirm_wipe'); ?></label>
                
                <div class="diag-actions">
                    <input type="text" id="wipeConfirm" class="form-control" placeholder="CONFIRMAR" style="border-color:#fda4af; flex:1; min-width:150px;">
                    <button class="btn-primary" style="background:var(--danger-color); border-color:var(--danger-color);" onclick="wipeGames()"><?php echo __('diag_btn_wipe'); ?></button>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadDiagnostics();
    setupDiagDragAndDrop();
});

async function loadDiagnostics() {
    try {
        const res = await fetch('api/herramientas.php', { method: 'POST', body: JSON.stringify({action: 'get_diagnostics'}) });
        const json = await res.json();
        
        if(json.success) {
            // --- TABLA 1: STATS BD ---
            // Usamos .diag-table que tiene min-width: 0 !important en CSS
            let htmlTables = '<table class="diag-table"><thead><tr><th class="diag-col-name">Tabla</th><th class="diag-col-val">Reg.</th></tr></thead><tbody>';
            
            const statsData = json.stats || (json.data && json.data.tables) || {};
            
            for (const [table, count] of Object.entries(statsData)) {
                htmlTables += `<tr>
                    <td><strong>${table}</strong></td>
                    <td class="diag-col-val">${count}</td>
                </tr>`;
            }
            htmlTables += '</tbody></table>';
            document.getElementById('tableStatusContent').innerHTML = htmlTables;

            // --- TABLA 2: CHECKS ---
            let htmlQuality = '<table class="diag-table"><thead><tr><th class="diag-col-name">Check</th><th class="diag-col-val">Estado</th></tr></thead><tbody>';
            
            const checksData = json.checks || (json.data && json.data.checks) || [];
            
            checksData.forEach(c => {
                let badge = c.status === 'ok' 
                    ? '<span style="color:var(--success-color)"><i class="fa-solid fa-check"></i> OK</span>' 
                    : '<span style="color:var(--danger-color)"><i class="fa-solid fa-triangle-exclamation"></i> Err</span>';
                
                htmlQuality += `<tr>
                    <td>${c.desc}</td>
                    <td class="diag-col-val">${badge} <small>(${c.count})</small></td>
                </tr>`;
            });
            htmlQuality += '</tbody></table>';
            document.getElementById('qualityCheckContent').innerHTML = htmlQuality;
        }
    } catch(e) { console.error(e); }
}

async function runSQL() {
    const sql = document.getElementById('sqlInput').value;
    if(!sql) return;
    
    const out = document.getElementById('sqlOutput');
    out.classList.remove('hidden');
    out.innerHTML = '<?php echo __('diag_executing'); ?>';

    try {
        const res = await fetch('api/herramientas.php', { method: 'POST', body: JSON.stringify({action: 'run_sql', sql: sql}) });
        const json = await res.json();
        
        if(json.success) {
            if(json.type === 'read') {
                out.innerText = JSON.stringify(json.data, null, 2);
            } else {
                out.innerText = `<?php echo __('diag_sql_success'); ?> ${json.affected}`;
            }
        } else {
            out.innerText = "<?php echo __('diag_sql_error'); ?> " + json.error;
            out.style.color = '#ef4444';
        }
    } catch(e) { out.innerText = "<?php echo __('diag_net_error'); ?>"; }
}

async function wipeGames() {
    const confirmTxt = document.getElementById('wipeConfirm').value;
    if(confirmTxt !== 'CONFIRMAR') {
        alert("<?php echo __('diag_wipe_alert'); ?>");
        return;
    }

    if(!confirm("<?php echo __('diag_wipe_confirm'); ?>")) return;

    try {
        const res = await fetch('api/herramientas.php', { method: 'POST', body: JSON.stringify({action: 'wipe_games', confirm: confirmTxt}) });
        const json = await res.json();
        if(json.success) {
            alert(json.message); 
            document.getElementById('wipeConfirm').value = '';
            loadDiagnostics();
        } else {
            alert("Error: " + json.error);
        }
    } catch(e) { alert("<?php echo __('diag_conn_error'); ?>"); }
}

function setupDiagDragAndDrop() {
    const container = document.getElementById('diag-container');
    const cards = container.querySelectorAll('.diag-card');

    cards.forEach(card => {
        card.addEventListener('dragstart', () => card.classList.add('dragging'));
        card.addEventListener('dragend', () => card.classList.remove('dragging'));
    });

    container.addEventListener('dragover', e => {
        e.preventDefault();
        const afterElement = getDragAfterElement(container, e.clientX, e.clientY);
        const draggable = document.querySelector('.dragging');
        if (afterElement == null) container.appendChild(draggable);
        else container.insertBefore(draggable, afterElement);
    });
}

function getDragAfterElement(container, x, y) {
    const draggableElements = [...container.querySelectorAll('.diag-card:not(.dragging)')];
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offsetX = x - (box.left + box.width / 2);
        const offsetY = y - (box.top + box.height / 2);
        const dist = Math.hypot(offsetX, offsetY);
        if (offsetY < 0 && dist < closest.dist) return { dist: dist, element: child };
        return closest;
    }, { dist: Number.POSITIVE_INFINITY }).element;
}
</script>
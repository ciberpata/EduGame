<?php
// views/limpieza.php
// Seguridad: Solo Superadmin
if ($_SESSION['user_role'] != 1) { echo "<script>window.location='dashboard';</script>"; exit; }
?>

<div class="card">
    <div class="clean-header">
        <h2 style="margin:0; color:var(--primary);"><i class="fa-solid fa-broom"></i> <?php echo __('clean_title'); ?></h2>
        <p class="text-muted" style="margin-top:5px;"><?php echo __('clean_desc'); ?></p>
    </div>

    <div class="clean-grid-layout">
        
        <div>
            <form id="frmLimpieza">
                <div class="mb-3">
                    <label style="font-weight:bold;"><?php echo __('clean_scope_label'); ?></label>
                    <p class="text-muted" style="font-size:0.85rem;"><?php echo __('clean_scope_help'); ?></p>
                    <select id="scopeSelect" class="form-control">
                        <option value="global"><?php echo __('clean_scope_global'); ?></option>
                    </select>
                </div>

                <div class="mb-3">
                    <label style="font-weight:bold;"><?php echo __('clean_age_label'); ?></label>
                    <p class="text-muted" style="font-size:0.85rem;"><?php echo __('clean_age_help'); ?></p>
                    <div class="input-group-flex">
                        <input type="number" id="timeVal" class="form-control" value="30" min="1" style="width:100px;">
                        <select id="timeUnit" class="form-control" style="flex:1;">
                            <option value="DAY"><?php echo __('clean_unit_days'); ?></option>
                            <option value="WEEK"><?php echo __('clean_unit_weeks'); ?></option>
                            <option value="MONTH"><?php echo __('clean_unit_months'); ?></option>
                            <option value="YEAR"><?php echo __('clean_unit_years'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="clean-warning-box">
                    <h4 class="clean-warning-title"><?php echo __('clean_warning_title'); ?></h4>
                    <ul class="clean-warning-list">
                        <li><?php echo __('clean_warn_1'); ?></li>
                        <li><?php echo __('clean_warn_2'); ?></li>
                        <li><?php echo __('clean_warn_3'); ?></li>
                        <li><em><?php echo __('clean_warn_4'); ?></em></li>
                    </ul>
                </div>

                <div class="text-right">
                    <button type="submit" class="btn-danger-custom">
                        <i class="fa-solid fa-trash-can"></i> <?php echo __('clean_btn_run'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="clean-console-box">
            <h3 class="clean-console-header"><?php echo __('clean_console_title'); ?></h3>
            <div id="consoleOutput" class="clean-console-output">
                <span style="color:#94a3b8;"><?php echo __('clean_console_waiting'); ?></span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadUsers);

async function loadUsers() {
    try {
        const res = await fetch('api/usuarios.php?limit=-1');
        const json = await res.json();
        const sel = document.getElementById('scopeSelect');
        if(json.data) {
            json.data.forEach(u => {
                if([2,3,4].includes(parseInt(u.id_rol))) { 
                    sel.innerHTML += `<option value="${u.id_usuario}">👤 ${u.nombre} (${u.correo})</option>`;
                }
            });
        }
    } catch(e) { 
        log(<?php echo json_encode(__('clean_js_err_users')); ?> + e.message, 'error'); 
    }
}

document.getElementById('frmLimpieza').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const scope = document.getElementById('scopeSelect').value;
    const val = document.getElementById('timeVal').value;
    const unit = document.getElementById('timeUnit').value;
    
    const confirmMsg = <?php echo json_encode(__('clean_js_confirm')); ?>;
    
    if(!confirm(confirmMsg)) return;

    log(`<?php echo __('clean_js_starting'); ?> [Scope: ${scope}, > ${val} ${unit}]`, 'info');

    try {
        const formData = {
            action: 'clean_data',
            scope: scope,
            val: val,
            unit: unit
        };

        const res = await fetch('api/herramientas.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        });

        const json = await res.json();

        if(json.success) {
            log(<?php echo json_encode(__('clean_js_success')); ?>, 'success');
            log(`- <?php echo __('clean_stat_closed'); ?> ${json.closed}`);
            log(`- <?php echo __('clean_stat_players'); ?> ${json.players_deleted}`);
            log(`- <?php echo __('clean_stat_logs'); ?> ${json.logs_deleted}`);
            log(`- <?php echo __('clean_stat_temp'); ?> ${json.temp_files}`);
        } else {
            const errorLabel = <?php echo json_encode(__('diag_error')); ?>; 
            log(errorLabel + " " + json.error, 'error');
        }

    } catch(err) {
        log("<?php echo __('diag_net_error'); ?>: " + err.message, 'error');
    }
});

function log(msg, type='normal') {
    const box = document.getElementById('consoleOutput');
    const color = type === 'error' ? '#ef4444' : (type === 'success' ? '#22c55e' : (type === 'info' ? '#60a5fa' : '#f8fafc'));
    const time = new Date().toLocaleTimeString();
    
    // Si es el primer mensaje y tiene el texto de espera, limpiar
    if(box.innerHTML.includes('<?php echo __('clean_console_waiting'); ?>')) {
        box.innerHTML = '';
    }
    
    box.innerHTML += `<div style="color:${color}; margin-bottom:5px;">[${time}] ${msg}</div>`;
    box.scrollTop = box.scrollHeight;
}
</script>
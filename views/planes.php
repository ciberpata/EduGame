<?php
// views/planes.php
if ($_SESSION['user_role'] != 1) { echo "<script>window.location='dashboard';</script>"; exit; }
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <h2 style="margin:0;"><i class="fa-solid fa-tags"></i> <?php echo __('plans_title'); ?></h2>
        <button class="btn-primary" onclick="openPlanModal()"><i class="fa-solid fa-plus"></i> <?php echo __('plans_btn_new'); ?></button>
    </div>

    <div id="planesGrid" class="dashboard-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
        </div>
</div>

<div id="planModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3 id="modalPlanTitle"><?php echo __('plans_btn_new'); ?></h3>
        <form id="planForm">
            <input type="hidden" name="id_plan" id="planId">
            
            <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label><?php echo __('plan_name'); ?> <span class="req">*</span></label>
                    <input type="text" name="nombre" id="pNombre" class="form-control" required placeholder="Ej: Enterprise">
                </div>
                <div>
                    <label><?php echo __('plan_price'); ?></label>
                    <input type="number" name="precio_mensual" id="pPrecio" class="form-control" step="0.01" value="0">
                </div>
            </div>

            <h4 style="border-bottom:1px solid #eee; margin-bottom:10px; color:var(--primary);">Límites (<small><?php echo __('plan_unlimited'); ?></small>)</h4>
            <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label><?php echo __('plan_limit_players'); ?></label>
                    <input type="number" name="limite_jugadores" id="pJugadores" class="form-control" value="10">
                </div>
                <div>
                    <label><?php echo __('plan_limit_games'); ?></label>
                    <input type="number" name="limite_partidas" id="pPartidas" class="form-control" value="1">
                </div>
            </div>

            <h4 style="border-bottom:1px solid #eee; margin-bottom:10px; color:var(--primary);">Características</h4>
            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
                <label class="switch-label">
                    <input type="checkbox" name="feature_marca_blanca" id="fMarca">
                    <span><?php echo __('plan_feature_whitelabel'); ?></span>
                </label>
                <label class="switch-label">
                    <input type="checkbox" name="feature_exportar" id="fExport">
                    <span><?php echo __('plan_feature_export'); ?></span>
                </label>
                <label class="switch-label">
                    <input type="checkbox" name="feature_modos_extra" id="fModos">
                    <span><?php echo __('plan_feature_modes'); ?></span>
                </label>
            </div>
            <h4 style="border-bottom:1px solid #eee; margin-bottom:10px; margin-top:20px; color:var(--primary);">Control & Herramientas</h4>
            
            <div class="mb-3">
                <label>Historial Auditoría (Días) <small class="text-muted">(0 = Ilimitado)</small></label>
                <input type="number" name="limite_auditoria_dias" id="pAudit" class="form-control" value="30">
            </div>

            <div style="display:flex; flex-direction:column; gap:10px;">
                <label class="switch-label">
                    <input type="checkbox" name="feature_filtros_avanzados" id="fFiltros">
                    <span>Permitir Filtros Avanzados (Icono)</span>
                </label>
                <label class="switch-label" style="color:orange;">
                    <input type="checkbox" name="tool_sql" id="tSql">
                    <span>Herramienta: Ejecutar SQL</span>
                </label>
                <label class="switch-label" style="color:red;">
                    <input type="checkbox" name="tool_wipe" id="tWipe">
                    <span>Herramienta: Borrado Total</span>
                </label>
            </div>

            <div class="text-right">
                <button type="button" class="btn-icon" onclick="document.getElementById('planModal').classList.remove('active')"><?php echo __('key_btn_cancel'); ?></button>
                <button type="submit" class="btn-primary"><?php echo __('key_btn_save'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadPlanes);

async function loadPlanes() {
    const grid = document.getElementById('planesGrid');
    grid.innerHTML = 'Cargando...';
    
    try {
        const res = await fetch('api/planes.php');
        const json = await res.json();
        
        if(json.success) {
            grid.innerHTML = '';
            json.data.forEach(p => {
                const isFree = parseFloat(p.precio_mensual) === 0;
                const priceBadge = isFree 
                    ? '<span style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:10px; font-size:0.8rem; font-weight:bold;">GRATIS</span>'
                    : `<span style="background:#dbeafe; color:#1e40af; padding:2px 8px; border-radius:10px; font-size:0.8rem; font-weight:bold;">${p.precio_mensual}€ / mes</span>`;

                const featuresList = `
                    <ul style="font-size:0.85rem; color:#666; padding-left:20px; margin:10px 0;">
                        <li>${p.limite_jugadores == 0 ? 'Jugadores Ilimitados' : p.limite_jugadores + ' Jugadores máx'}</li>
                        <li>${p.limite_partidas == 0 ? 'Partidas Ilimitadas' : p.limite_partidas + ' Partidas simultáneas'}</li>
                        <li style="color:${p.feature_marca_blanca==1?'green':'#ccc'}">Marca Blanca</li>
                        <li style="color:${p.feature_exportar==1?'green':'#ccc'}">Exportar Datos</li>
                    </ul>`;

                grid.innerHTML += `
                    <div class="card" style="position:relative; border-top: 4px solid var(--primary);">
                        <div style="display:flex; justify-content:space-between; align-items:start;">
                            <h3 style="margin:0;">${p.nombre}</h3>
                            ${priceBadge}
                        </div>
                        ${featuresList}
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:15px; padding-top:10px; border-top:1px solid #eee;">
                            <small class="text-muted"><i class="fa-solid fa-users"></i> ${p.usuarios_count} usuarios</small>
                            <div>
                                <button class="btn-icon" onclick='editPlan(${JSON.stringify(p)})'><i class="fa-solid fa-pen"></i></button>
                                ${p.id_plan != 1 ? `<button class="btn-icon" style="color:var(--danger-color)" onclick="deletePlan(${p.id_plan})"><i class="fa-solid fa-trash"></i></button>` : ''}
                            </div>
                        </div>
                    </div>`;
            });
        }
    } catch(e) { console.error(e); }
}

function openPlanModal() {
    document.getElementById('planForm').reset();
    document.getElementById('planId').value = '';
    document.getElementById('modalPlanTitle').innerText = "<?php echo __('plans_btn_new'); ?>";
    document.getElementById('planModal').classList.add('active');
}

function editPlan(p) {
    document.getElementById('planId').value = p.id_plan;
    document.getElementById('pNombre').value = p.nombre;
    document.getElementById('pPrecio').value = p.precio_mensual;
    document.getElementById('pJugadores').value = p.limite_jugadores;
    document.getElementById('pPartidas').value = p.limite_partidas;
    document.getElementById('fMarca').checked = (p.feature_marca_blanca == 1);
    document.getElementById('fExport').checked = (p.feature_exportar == 1);
    document.getElementById('fModos').checked = (p.feature_modos_extra == 1);

    // NUEVOS CAMPOS
    document.getElementById('pAudit').value = p.limite_auditoria_dias;
    document.getElementById('fFiltros').checked = (p.feature_filtros_avanzados == 1);
    document.getElementById('tSql').checked = (p.tool_sql == 1);
    document.getElementById('tWipe').checked = (p.tool_wipe == 1);
    
    document.getElementById('modalPlanTitle').innerText = "Editar: " + p.nombre;
    document.getElementById('planModal').classList.add('active');
}

document.getElementById('planForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    // Checkboxes no se envían si no están marcados, hay que manejarlos en PHP o forzarlos
    // En api/planes.php usamos isset(), así que funciona con FormData estándar.
    
    try {
        const res = await fetch('api/planes.php', {
            method: 'POST', 
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if(json.success) {
            document.getElementById('planModal').classList.remove('active');
            loadPlanes();
        } else {
            alert("Error: " + json.error);
        }
    } catch(e) { alert("Error de conexión"); }
});

async function deletePlan(id) {
    if(!confirm("<?php echo __('plan_delete_confirm'); ?>")) return;
    try {
        const res = await fetch('api/planes.php', {
            method: 'DELETE', 
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id_plan: id})
        });
        const json = await res.json();
        if(json.success) loadPlanes(); else alert(json.error);
    } catch(e) {}
}
</script>
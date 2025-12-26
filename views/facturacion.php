<!-- views/facturacion.php -->
<style>
    /* Estilos específicos para estados de factura */
    .badge-warning { background-color: #fef9c3; color: #854d0e; } /* Amarillo */
    .badge-danger { background-color: #fee2e2; color: #991b1b; } /* Rojo */
    .font-mono { font-family: 'Courier New', Courier, monospace; letter-spacing: -0.5px; }
</style>

<section id="view-facturacion" class="hidden fade-in">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.5rem; font-weight: 700;">Facturación y Pagos</h2>
    </div>

    <!-- Widgets de Resumen Financiero -->
    <div class="dashboard-grid" style="margin-bottom: 2rem;">
        <!-- Widget Pendiente -->
        <div class="card widget-stat" style="border-left: 4px solid #eab308;">
            <div>
                <p>Pendiente de Pago</p>
                <h3 id="totalPendiente" style="color: #ca8a04;">0.00 €</h3>
            </div>
            <div class="widget-icon" style="background-color: #fef9c3; color: #ca8a04;">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
        </div>
        <!-- Widget Última Factura -->
        <div class="card widget-stat" style="border-left: 4px solid #22c55e;">
            <div>
                <p>Última Factura</p>
                <h3 id="lastInvoiceDate">-</h3>
            </div>
            <div class="widget-icon" style="background-color: #dcfce7; color: #16a34a;">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
        </div>
        <!-- Widget Configuración -->
        <div class="card widget-stat" style="border-left: 4px solid #6b7280; cursor: pointer;" onclick="openUserModal()">
            <div>
                <p>Datos Fiscales</p>
                <h3 style="font-size: 1rem; margin-top: 0.5rem;">Ver / Editar</h3>
            </div>
            <div class="widget-icon" style="background-color: #f3f4f6; color: #374151;">
                <i class="fa-solid fa-building"></i>
            </div>
        </div>
    </div>

    <!-- Tabla de Facturas -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha Emisión</th>
                    <th>Concepto</th>
                    <th class="text-right">Importe</th>
                    <th class="text-center">Estado</th>
                    <th class="text-right">Descarga</th>
                </tr>
            </thead>
            <tbody id="invoicesListBody">
                <!-- Se llena vía JS -->
            </tbody>
        </table>
    </div>
</section>

<script>
function loadInvoices() {
    const tbody = document.getElementById('invoicesListBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center">Cargando facturas...</td></tr>';

    fetch('api/facturas.php')
    .then(r => r.json())
    .then(res => {
        tbody.innerHTML = '';
        
        if(!res.data || res.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 2rem; color: var(--text-muted);">No tienes facturas disponibles todavía.</td></tr>';
            document.getElementById('totalPendiente').innerText = '0.00 €';
            return;
        }

        let totalPendiente = 0;
        let lastDate = null;

        res.data.forEach(f => {
            // Cálculos para widgets
            if(f.estado === 'pendiente') totalPendiente += parseFloat(f.monto);
            if(!lastDate) lastDate = f.fecha_emision; // Asumiendo orden DESC por SQL

            // Definir estilos según estado
            let badgeClass = 'badge-offline'; 
            let icon = '';
            let statusText = f.estado.charAt(0).toUpperCase() + f.estado.slice(1);

            if(f.estado === 'pagada') { 
                badgeClass = 'badge-online'; 
                icon = '<i class="fa-solid fa-check"></i> '; 
            } else if(f.estado === 'pendiente') { 
                badgeClass = 'badge-warning'; 
                icon = '<i class="fa-solid fa-clock"></i> '; 
            } else if(f.estado === 'cancelada') { 
                badgeClass = 'badge-danger'; 
                icon = '<i class="fa-solid fa-ban"></i> '; 
            }

            tbody.innerHTML += `
                <tr>
                    <td class="font-mono" style="font-weight:bold; color: var(--text-main);">${f.numero_factura}</td>
                    <td>${new Date(f.fecha_emision).toLocaleDateString()}</td>
                    <td>${f.concepto}</td>
                    <td class="text-right font-mono">${parseFloat(f.monto).toFixed(2)} €</td>
                    <td class="text-center"><span class="badge ${badgeClass}">${icon}${statusText}</span></td>
                    <td class="text-right">
                        <a href="${f.url_archivo}" target="_blank" class="btn-icon" style="color: var(--primary-color);" title="Descargar PDF">
                            <i class="fa-solid fa-file-pdf fa-lg"></i>
                        </a>
                    </td>
                </tr>
            `;
        });

        // Actualizar widgets
        document.getElementById('totalPendiente').innerText = totalPendiente.toFixed(2) + ' €';
        document.getElementById('lastInvoiceDate').innerText = lastDate ? new Date(lastDate).toLocaleDateString() : '-';
    })
    .catch(err => {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="color: var(--danger-color);">Error al cargar datos de facturación.</td></tr>';
        console.error(err);
    });
}

// Auto-cargar si se navega directamente aquí
if(typeof CURRENT_VIEW !== 'undefined' && CURRENT_VIEW === 'facturacion') {
    loadInvoices();
}
</script>
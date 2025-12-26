// assets/js/app.js

// --- VARIABLES DE ESTADO ---
let currentPage = 1;
let currentLimit = 10;
let currentSort = 'nombre_jugador';
let currentOrder = 'ASC';

document.addEventListener('DOMContentLoaded', () => {
    // 1. Iniciar Vista (URL o Default)
    const urlParams = new URLSearchParams(window.location.search);
    const view = urlParams.get('view') || 'dashboard';
    
    // Si estamos en la vista de usuarios, cargar datos
    if(view === 'usuarios') {
        loadUsers();
    }
    
    // 2. Configurar Drag & Drop
    setupDragAndDrop();

    // 3. Inicializar Lógica Responsive (Overlay)
    initResponsiveSidebar();

    // 4. Cargar tema guardado
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark');
    }
});

// --- FUNCIONES UI / RESPONSIVE ---

/**
 * Inicializa el comportamiento del menú en móviles.
 * Crea el overlay dinámicamente si no existe para no tocar el HTML.
 */
function initResponsiveSidebar() {
    // Verificar si ya existe el overlay, si no, crearlo
    if (!document.querySelector('.sidebar-overlay')) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        // Al hacer clic en el fondo oscuro, cerramos el menú
        overlay.onclick = toggleSidebar; 
        document.body.appendChild(overlay);
    }
}

/**
 * Abre o cierra el sidebar.
 * Se llama desde el botón hamburguesa del Header.
 */
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar'); // Usamos la clase definida en CSS
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('active');
        
        // Opcional: Bloquear scroll del body cuando el menú está abierto en móvil
        if (window.innerWidth <= 768) {
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }
    }
    
    if (overlay) {
        // Forzamos la visibilidad del overlay basada en el estado del sidebar
        // Aunque el CSS lo maneja, esto asegura compatibilidad
        overlay.style.display = sidebar.classList.contains('active') ? 'block' : '';
    }
}

function toggleTheme() {
    document.body.classList.toggle('dark');
    localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
}

function changeAccent(color) {
    document.documentElement.style.setProperty('--primary', color); // Corregido a --primary según tu CSS
}

// --- NAVEGACIÓN Y AJAX (SPA Simulado) ---

function navigate(viewId) {
    // Nota: Si tu backend usa PHP para renderizar vistas completas, 
    // esta función podría no ser necesaria si usas enlaces <a href="?view=x">.
    // La mantengo por compatibilidad con tu código original.
    
    window.history.pushState({view: viewId}, viewId, `?view=${viewId}`);
    
    document.querySelectorAll('section').forEach(el => el.classList.add('hidden'));
    
    const target = document.getElementById(`view-${viewId}`);
    if(target) target.classList.remove('hidden');

    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    const navBtn = document.getElementById(`nav-${viewId}`);
    if(navBtn) navBtn.classList.add('active');

    if(viewId === 'usuarios') loadUsers();
}

// --- GESTIÓN DE USUARIOS (AJAX) ---

async function loadUsers() {
    const searchInput = document.getElementById('searchInput');
    const limitSelect = document.getElementById('pageSize');
    
    const search = searchInput ? searchInput.value : '';
    currentLimit = limitSelect ? limitSelect.value : 10;

    const tbody = document.getElementById('playersTableBody');
    if (!tbody) return; // Evitar errores si no estamos en la vista usuarios

    tbody.innerHTML = '<tr><td colspan="5" class="text-center">Cargando datos...</td></tr>';

    try {
        const response = await fetch(`api/usuarios.php?page=${currentPage}&limit=${currentLimit}&search=${search}&sort=${currentSort}&order=${currentOrder}`);
        
        // Manejo robusto de errores JSON
        let result;
        try {
            result = await response.json();
        } catch (e) {
            throw new Error("Respuesta inválida del servidor");
        }
        
        if(result.error) throw new Error(result.error);

        renderTable(result.data);
        updatePagination(result.pagination);

    } catch (error) {
        console.error('Error:', error);
        tbody.innerHTML = `<tr><td colspan="5" class="text-center" style="color:var(--danger-color)">Error al cargar: ${error.message}</td></tr>`;
    }
}

function renderTable(data) {
    const tbody = document.getElementById('playersTableBody');
    tbody.innerHTML = '';

    if(!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No se encontraron jugadores.</td></tr>';
        return;
    }

    data.forEach(player => {
        const tr = document.createElement('tr');
        const isOnline = player.conectado == 1;
        const statusClass = isOnline ? 'badge-online' : 'badge-offline'; // Asegúrate de tener estas clases en CSS o usar st-ok/st-error
        const statusText = isOnline ? 'Online' : 'Offline';
        // Mapeo de estilos CSS existentes
        const cssStatus = isOnline ? 'color: var(--success-color)' : 'color: var(--text-muted)';

        tr.innerHTML = `
            <td>
                <div style="display: flex; align-items: center;">
                    <div style="width: 30px; height: 30px; background: var(--bg-body); border-radius: 50%; display: flex; justify-content: center; align-items: center; margin-right: 10px; color: var(--text-muted);">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <b>${player.nombre_jugador}</b>
                </div>
            </td>
            <td class="text-center">${player.partidas_jugadas || 0}</td>
            <td class="text-center" style="font-family: monospace;">${player.puntuacion || 0}</td>
            <td class="text-center"><span style="${cssStatus}; font-weight:bold;">${statusText}</span></td>
            <td class="text-right">
                <button onclick="editPlayer(${player.id_jugador})" class="btn-icon" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>
                <button onclick="deletePlayer(${player.id_jugador})" class="btn-icon" style="color: var(--danger-color);" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function updatePagination(pagination) {
    const indicator = document.getElementById('pageIndicator');
    if(indicator) indicator.innerText = `Página ${pagination.current_page} de ${pagination.total_pages}`;
}

function sortTable(column) {
    if (currentSort === column) {
        currentOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
    } else {
        currentSort = column;
        currentOrder = 'ASC';
    }
    loadUsers();
}

function changePageSize() {
    currentPage = 1;
    loadUsers();
}

function prevPage() {
    if(currentPage > 1) {
        currentPage--;
        loadUsers();
    }
}

function nextPage() {
    currentPage++;
    loadUsers(); 
}

// --- DRAG & DROP (Dashboard) ---

function setupDragAndDrop() {
    const draggables = document.querySelectorAll('[draggable="true"]');
    const container = document.getElementById('widget-container');

    if(!container) return;

    draggables.forEach(d => {
        d.addEventListener('dragstart', () => d.classList.add('draggable-source'));
        d.addEventListener('dragend', () => d.classList.remove('draggable-source'));
    });

    container.addEventListener('dragover', e => {
        e.preventDefault();
        const afterElement = getDragAfterElement(container, e.clientX); // Nota: para grid responsive, quizá necesites clientY también
        const draggable = document.querySelector('.draggable-source');
        if (afterElement == null) container.appendChild(draggable);
        else container.insertBefore(draggable, afterElement);
    });
}

function getDragAfterElement(container, x) {
    const draggableElements = [...container.querySelectorAll('[draggable="true"]:not(.draggable-source)')];
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        // Simplificación horizontal, idealmente debería ser 2D (X e Y) para un Dashboard Grid
        const offset = x - box.left - box.width / 2;
        if (offset < 0 && offset > closest.offset) return { offset: offset, element: child };
        else return closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}
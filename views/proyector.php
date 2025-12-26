<?php
// views/proyector.php
$pin = $_GET['pin'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proyector - PIN: <?php echo $pin; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('assets/img/bg-proyector.jpg'); 
            background-size: cover; 
            background-position: center;
            color: white; margin: 0; font-family: 'Roboto', sans-serif; overflow: hidden; height: 100vh; 
        }
        
        /* Contenedores */
        .screen-layer { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; flex-direction: column; z-index: 1; }
        .screen-layer.active { display: flex; z-index: 100; }
        
        /* Lobby */
        .lobby-header { text-align: center; padding-top: 40px; flex: 0 0 auto; }
        .pin-box { font-size: 8rem; font-weight: 900; letter-spacing: 15px; margin: 10px 0; color:white; text-shadow: 0 0 30px rgba(99,102,241,1); }
        
        .players-grid { flex: 1; display: flex; flex-wrap: wrap; gap: 15px; padding: 20px 40px; justify-content: center; align-content: flex-start; overflow-y: auto; }
        
        .player-chip { 
            background: rgba(255, 255, 255, 0.9); color: #333; padding: 10px 45px 10px 20px; border-radius: 50px; 
            display: flex; align-items: center; gap: 10px; font-size: 1.3rem; font-weight: bold; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: all 0.3s ease; 
            position: relative; animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .player-chip:hover { transform: scale(1.05); z-index: 10; background: white; }
        .kick-btn {
            position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
            width: 30px; height: 30px; background: #fee2e2; color: #ef4444; 
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; opacity: 0; transition: 0.2s; font-size: 1rem;
        }
        .player-chip:hover .kick-btn { opacity: 1; }
        .kick-btn:hover { background: #fecaca; transform: translateY(-50%) scale(1.1); }

        @keyframes popIn { from { transform: scale(0); } to { transform: scale(1); } }
        
        .lobby-footer { flex: 0 0 100px; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); display: flex; justify-content: space-between; align-items: center; padding: 0 50px; }
        .nav-btn { padding:15px 40px; background:white; color:#333; border:none; border-radius:50px; font-weight:bold; cursor:pointer; font-size:1.5rem; box-shadow:0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s; white-space: nowrap; }
        .nav-btn:hover { transform: scale(1.05); }
        .nav-btn:disabled { opacity: 0.5; cursor: not-allowed; background: #ccc; }

        /* Estilos específicos para los botones de control en la CABECERA DEL JUEGO */
        .control-btn-group { display: flex; gap: 15px; flex-shrink: 0; align-items: center; }
        .game-top .nav-btn {
            padding: 10px 25px; /* Más pequeños para la cabecera */
            font-size: 1.2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }
        .btn-next-q { background: #22c55e; color: white; border: 2px solid white; } /* Verde */
        .btn-end-game { background: #ef4444; color: white; border: 2px solid white; } /* Rojo */

        /* Game Header */
        .game-top { flex: 0 0 100px; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; background: rgba(0,0,0,0.85); border-bottom: 2px solid #444; }
        .top-left { display: flex; align-items: center; gap: 20px; }
        .top-right { display: flex; align-items: center; gap: 20px; }
        .timer-circle { width: 80px; height: 80px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 900; border: 4px solid white; }
        .q-badge { background: white; color: #333; padding: 10px 25px; border-radius: 30px; font-weight: 900; font-size: 1.8rem; box-shadow: 0 0 15px rgba(255,255,255,0.2); }
        .app-logo-proj { height: 80px; width: auto; border-radius: 10px; border: 2px solid white; background:white; }

        .question-area { flex: 1; display: flex; align-items: center; justify-content: center; padding: 30px; background: rgba(255,255,255,0.95); color: #333; margin: 30px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
        .question-text { font-size: clamp(2.5rem, 4vw, 4rem); text-align: center; font-weight: 800; line-height: 1.2; }
        .answers-grid { flex: 0 0 45vh; display: grid; grid-template-columns: 1fr 1fr; gap: 25px; padding: 0 50px 50px 50px; }
        .answer-card { display: flex; align-items: center; padding: 30px 40px; border-radius: 20px; font-size: clamp(1.8rem, 2.5vw, 2.5rem); color: white; font-weight: bold; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .ans-0 { background: #ef4444; } .ans-1 { background: #3b82f6; } .ans-2 { background: #eab308; color:black; } .ans-3 { background: #22c55e; }
        .shape-icon { margin-right: 30px; font-size: 3rem; }
        
        .intro-overlay { position: absolute; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.95); z-index: 200; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        
        .ranking-container { flex:1; padding: 40px; display:flex; flex-direction:column; align-items:center; justify-content:center; width: 100%; overflow-y:auto; }
        .rank-row { 
            background: white; color: #333; width: 80%; padding: 20px 40px; margin: 10px 0; 
            border-radius: 15px; font-size: 2.5rem; font-weight: bold; display: flex; justify-content: space-between; align-items: center;
            transition: transform 0.5s ease; box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .rank-row.up { border-left: 15px solid #22c55e; animation: slideIn 0.5s; }
        @keyframes slideIn { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
    </style>
</head>
<body>

    <div id="screen-lobby" class="screen-layer active">
        <div class="lobby-header">
            <img id="userLogoLobby" src="assets/uploads/6925eda979617.png" class="app-logo-proj" style="height:100px; margin-bottom:10px;">
            <h2 id="academyName" style="margin:0; font-size: 2rem;">EduGame</h2>
            <div class="join-url" style="font-size: 1.5rem; margin-top: 10px;">edugame.com/play</div>
            <div class="pin-box"><?php echo $pin; ?></div>
        </div>
        
        <div class="players-grid" id="lobbyPlayers"></div>
        
        <div class="lobby-footer">
            <div style="font-size: 2rem; color:white;"><i class="fa-solid fa-users"></i> <span id="playerCount">0</span></div>
            <button id="btnStart" class="nav-btn" style="background:#22c55e; color:white;" disabled>Comenzar</button>
        </div>
    </div>

    <div id="screen-game" class="screen-layer">
        <div class="game-top">
            <div class="top-left">
                <img id="userLogoGame" src="assets/uploads/6925eda979617.png" class="app-logo-proj">
                <div class="timer-circle" id="timer">0</div>
                <div id="answersCounter" style="font-size:2rem; font-weight:bold; color:white; margin-left: 20px;">0 Resp.</div>
            </div>
            
            <div class="control-btn-group">
                <button class="nav-btn btn-end-game" onclick="endGame(this)">
                    Terminar <i class="fa-solid fa-stop"></i>
                </button>
                <button class="nav-btn btn-next-q" onclick="forceRanking(this)">
                    Siguiente <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>

            <div class="top-right">
                <div class="q-badge" id="qCounterDisplay">Pregunta 1</div>
                <div style="font-weight:bold; font-size:1.5rem;">PIN: <?php echo $pin; ?></div>
            </div>
        </div>

        <div class="question-area"><div class="question-text" id="qText">...</div></div>
        <div class="answers-grid" id="answersGrid"></div>
        <div id="introOverlay" class="intro-overlay" style="display:none;">
            <div id="introText" style="font-size:4rem; color:#a5b4fc; text-align:center; padding: 0 50px;">Pregunta</div>
            <div id="introCount" style="font-size:15rem; font-weight:900; color:#facc15;">5</div>
        </div>
    </div>

    <div id="screen-ranking" class="screen-layer">
        <div class="game-top">
            <div style="font-size:3rem; font-weight:bold; flex: 1;">Clasificación</div>
            
            <div class="control-btn-group">
                <button class="nav-btn btn-end-game" onclick="endGame(this)">
                    Terminar <i class="fa-solid fa-stop"></i>
                </button>
                <button class="nav-btn btn-next-q" onclick="nextQuestion(this)">
                    Siguiente <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>

        </div>
        <div class="ranking-container" id="rankingList"></div>
    </div>

<script>
    const PIN = "<?php echo $pin; ?>";
    const shapes = ['▲', '◆', '●', '■']; 
    let gameId = null;
    let currentPhase = ''; 
    let lastQIndex = -1;
    let playerCount = 0;
    let prevScores = {};
    let isStarting = false;

    const avatars = { 1:'🛡️', 2:'⚔️', 3:'🗡️', 4:'🏛️', 5:'🏹', 6:'🔮', 7:'🥷', 8:'🏴‍☠️', 9:'🌿', 10:'⚜️', 11:'🤖', 12:'👽', 13:'🦊', 14:'🦁', 15:'🦄' };
    function getAvatar(id) { return avatars[id] || '👤'; }

    // --- ACCIONES DE BOTONES ---

    async function startGame() {
        if(!gameId || isStarting) return;
        if(playerCount === 0) { alert("¡Esperando jugadores!"); return; }
        isStarting = true;
        document.getElementById('btnStart').disabled = true; 
        document.getElementById('btnStart').innerText = "Iniciando...";
        await fetch('api/partidas.php', { method: 'POST', body: JSON.stringify({ action: 'iniciar_juego', id_partida: gameId }) });
    }

    // Botón Siguiente (Desde Ranking -> Nueva Pregunta)
    async function nextQuestion(btn) {
        if(!gameId) return;
        btn.disabled = true;
        await fetch('api/partidas.php', { method: 'POST', body: JSON.stringify({ action: 'siguiente_fase', id_partida: gameId }) });
        setTimeout(() => { btn.disabled = false; }, 3000);
    }

    // Botón Siguiente (Desde Pregunta -> Ranking)
    async function forceRanking(btn) {
        if(!gameId) return;
        btn.disabled = true;
        await fetch('api/partidas.php', { method: 'POST', body: JSON.stringify({ action: 'siguiente_fase', id_partida: gameId }) });
        setTimeout(() => { btn.disabled = false; }, 2000);
    }

    // Botón Terminar Partida (Cierra todo)
    async function endGame(btn) {
        if(!gameId) return;
        if(!confirm("¿Seguro que quieres terminar la partida ahora?")) return;
        btn.disabled = true;
        await fetch('api/partidas.php', { method: 'POST', body: JSON.stringify({ action: 'finalizar', id_partida: gameId }) });
    }

    window.deletePlayer = async function(idSesion) {
        if(!confirm("¿Expulsar a este jugador?")) return;
        try {
            await fetch('api/partidas.php', { method: 'POST', body: JSON.stringify({ action: 'expulsar_jugador', id_sesion: idSesion }) });
            loadLobbyPlayers(gameId);
        } catch(e) { console.error(e); }
    };

    document.getElementById('btnStart').onclick = startGame;

    // --- INICIALIZACIÓN Y BUCLE ---

    async function init() {
        try {
            const res = await fetch(`api/partidas.php?action=info_proyector&codigo_pin=${PIN}`);
            const json = await res.json();
            if(!json.success) { document.body.innerHTML = "<h1 style='color:white;text-align:center;'>Partida no encontrada</h1>"; return; }
            gameId = json.data.id_partida;
            document.getElementById('academyName').innerText = json.data.nombre_visual;
            
            if(json.data.logo_visual) {
                document.getElementById('userLogoLobby').src = json.data.logo_visual;
                document.getElementById('userLogoGame').src = json.data.logo_visual;
            }

            setInterval(syncLoop, 1000); 
        } catch(e){}
    }

    async function syncLoop() {
        if(!gameId) return;
        try {
            let state = null;
            // IMPORTANTE: Consultar API directa para sincronización instantánea
            const res = await fetch(`api/partidas.php?action=estado_juego&codigo_pin=${PIN}`);
            if(res.ok) {
                const json = await res.json();
                state = json.data;
            }

            if(!state) return;

            // --- ESTADOS ---

            if(state.estado === 'sala_espera') {
                switchScreen('screen-lobby');
                loadLobbyPlayers(gameId);
            }
            else if (state.estado === 'jugando') {
                
                // FASE: INTRO
                if(state.estado_pregunta === 'intro') {
                    switchScreen('screen-game');
                    document.getElementById('introOverlay').style.display = 'flex';
                    document.getElementById('introText').innerText = state.texto_pregunta;
                    document.getElementById('qCounterDisplay').innerText = "Pregunta " + state.pregunta_actual_index;
                    
                    if(currentPhase !== 'intro' || lastQIndex !== state.pregunta_actual_index) {
                        currentPhase = 'intro';
                        lastQIndex = state.pregunta_actual_index;
                        startIntroTimer(state.id_partida);
                    }
                }
                // FASE: RESPONDIENDO
                else if(state.estado_pregunta === 'respondiendo') {
                    switchScreen('screen-game');
                    document.getElementById('introOverlay').style.display = 'none';
                    document.getElementById('qText').innerText = state.texto_pregunta;
                    document.getElementById('qCounterDisplay').innerText = "Pregunta " + state.pregunta_actual_index;
                    
                    if(document.getElementById('answersGrid').innerHTML === '' || currentPhase !== 'respondiendo') {
                        renderAnswers(state.json_opciones);
                    }
                    currentPhase = 'respondiendo';

                    // Tiempo
                    let left = 0;
                    if (state.tiempo_inicio_pregunta) {
                        const startTime = new Date(state.tiempo_inicio_pregunta.replace(" ", "T")).getTime();
                        const elapsed = Math.floor((Date.now() - startTime) / 1000);
                        const limit = parseInt(state.tiempo_limite);
                        left = Math.max(0, limit - elapsed);
                    }
                    document.getElementById('timer').innerText = left;
                    
                    if(state.respuestas_recibidas !== undefined) {
                        document.getElementById('answersCounter').innerText = `${state.respuestas_recibidas} / ${state.total_jugadores} Resp.`;
                    }

                    // AUTO-AVANCE SOLO POR TIEMPO:
                    // Si el tiempo acaba, avanzamos.
                    // Si todos responden, el servidor cambia el estado y lo captamos en el próximo fetch.
                    if(left === 0 && !window.processingNext) {
                        window.processingNext = true;
                        setTimeout(() => fetch('api/partidas.php', { method: 'POST', body: JSON.stringify({ action: 'siguiente_fase', id_partida: gameId }) }), 1000);
                    }
                }
                // FASE: RESULTADOS (RANKING)
                else if(state.estado_pregunta === 'resultados') {
                    if(currentPhase !== 'resultados') {
                        switchScreen('screen-ranking');
                        loadRanking(state.id_partida);
                        currentPhase = 'resultados';
                        window.processingNext = false;
                        // AQUÍ SE PARA EL FLUJO. Espera a los botones.
                    }
                }
            }
            else if (state.estado === 'finalizada') {
                document.body.innerHTML = "<div style='display:flex; justify-content:center; align-items:center; height:100%; flex-direction:column; background:#111; color:white; background: linear-gradient(rgba(0,0,0,0.8), rgba(0,0,0,0.8)), url(\"assets/img/bg-proyector.jpg\"); background-size:cover;'><h1 style='font-size:6rem; color:#facc15; margin-bottom:20px; text-shadow:0 0 20px black;'>¡Fin de la Partida!</h1><p style='font-size:2.5rem;'>Gracias por jugar</p><a href='index.php' style='color:white; font-size:1.5rem; margin-top:40px; border:2px solid white; padding:15px 40px; border-radius:50px; text-decoration:none; transition:0.3s;'>Salir</a></div>";
            }
        } catch(e) {}
    }

    async function loadLobbyPlayers(id) {
        const res = await fetch(`api/partidas.php?action=ver_jugadores&id_partida=${id}`);
        const json = await res.json();
        if(json.success) {
            const list = json.data;
            playerCount = list.length;
            document.getElementById('playerCount').innerText = playerCount;
            
            if (!isStarting) {
                document.getElementById('btnStart').disabled = (playerCount === 0);
            }

            const grid = document.getElementById('lobbyPlayers');
            const currentApiIds = new Set(list.map(p => parseInt(p.id_sesion)));
            
            [...grid.children].forEach(child => {
                const id = parseInt(child.dataset.id);
                if (!currentApiIds.has(id)) {
                    child.style.transform = 'scale(0)';
                    setTimeout(() => { if(child.parentNode) child.remove(); }, 400); 
                }
            });

            list.forEach(p => {
                if (!document.querySelector(`.player-chip[data-id="${p.id_sesion}"]`)) {
                    const div = document.createElement('div');
                    div.className = 'player-chip';
                    div.dataset.id = p.id_sesion;
                    div.innerHTML = `${getAvatar(p.avatar_id)} ${p.nombre_nick} <span class="kick-btn" onclick="deletePlayer(${p.id_sesion})"><i class="fa-solid fa-xmark"></i></span>`;
                    grid.appendChild(div);
                }
            });
        }
    }

    function switchScreen(id) {
        document.querySelectorAll('.screen-layer').forEach(d => d.classList.remove('active'));
        document.getElementById(id).classList.add('active');
    }

    function startIntroTimer(idPartida) {
        let count = 5;
        const el = document.getElementById('introCount');
        if(window.introInterval) clearInterval(window.introInterval);
        
        el.innerText = count;
        window.introInterval = setInterval(() => {
            count--;
            el.innerText = count;
            if(count <= 0) {
                clearInterval(window.introInterval);
                fetch('api/partidas.php', { method: 'POST', body: JSON.stringify({ action: 'siguiente_fase', id_partida: idPartida }) });
            }
        }, 1000);
    }

    async function loadRanking(idPartida) {
        const res = await fetch(`api/partidas.php?action=ranking_parcial&id_partida=${idPartida}`);
        const json = await res.json();
        const div = document.getElementById('rankingList');
        div.innerHTML = '';
        if(json.success) {
            json.ranking.forEach((p, i) => {
                const pid = p.id_sesion || p.nombre_nick;
                const score = parseInt(p.puntuacion);
                
                let isUp = false;
                if(prevScores[pid] !== undefined && score > prevScores[pid]) {
                    isUp = true;
                }
                prevScores[pid] = score;

                const rowClass = isUp ? 'rank-row up' : 'rank-row';
                
                div.innerHTML += `
                    <div class="${rowClass}">
                        <div style="display:flex; align-items:center; gap:20px;">
                            <span style="background:#333; color:white; width:50px; height:50px; border-radius:50%; display:flex; justify-content:center; align-items:center;">${i+1}</span>
                            <span>${getAvatar(p.avatar_id)} ${p.nombre_nick}</span>
                        </div>
                        <span>${p.puntuacion} pts</span>
                    </div>`;
            });
        }
    }

    function renderAnswers(jsonStr) {
        const grid = document.getElementById('answersGrid');
        grid.innerHTML = '';
        try {
            const opts = (typeof jsonStr === 'string') ? JSON.parse(jsonStr) : jsonStr;
            opts.forEach((o, i) => {
                grid.innerHTML += `<div class="answer-card ans-${i}"><div class="shape-icon">${shapes[i]}</div><div>${o.texto}</div></div>`;
            });
        } catch(e) {}
    }

    init();
</script>
</body>
</html>
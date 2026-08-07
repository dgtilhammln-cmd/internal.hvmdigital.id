<?php
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
if(!isset($_SESSION['admin'])){ header("Location: /"); exit; }
$user_logged = $_SESSION['admin'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkSpace HVM | Core Intel V6</title>
    <link rel="stylesheet" href="/dashboard/workspace/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="cyber-overlay"></div>
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="dashboard-wrapper">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/dashboard/sidebar.php'; ?>

        <main class="main-content custom-scrollbar-page">
            <header class="workspace-nav animate-reveal">
                <div class="nav-left">
                    <a href="/dashboard/" class="nav-back-btn hover-neon"><i class="fas fa-chevron-left"></i></a>
                    <div class="brand-logotype">WorkSpace <span class="text-neon-gradient">HVM</span></div>
                </div>
                <!-- User ID Pill dihapus sesuai capture 1 -->
            </header>

            <div class="workspace-main-layout">
                <div class="ai-terminal-column animate-slide-up">
                    <div class="glass-container ai-cockpit">
                        <div class="cockpit-header">
                            <div class="ai-status-group">
                                <div class="ai-orb-pulse"></div>
                                <div class="ai-label">
                                    <h4>CORE INTELLIGENCE</h4>
                                    <small>SYSTEM STABLE • ANALYTICS READY</small>
                                </div>
                            </div>
                            <div class="cockpit-controls">
                                <div class="deepthink-panel">
                                    <span>DEEPTHINK</span>
                                    <label class="cyber-switch">
                                        <input type="checkbox" id="deepThinkToggle">
                                        <span class="cyber-slider"></span>
                                    </label>
                                </div>
                                <button class="reset-btn-neon" onclick="clearChat()">PURGE</button>
                            </div>
                        </div>

                        <div id="chatInterface" class="terminal-body custom-scrollbar-chat">
                            <div class="msg-group ai animate-pop">
                                <div class="ai-sys-icon"><i class="fas fa-shield-halved"></i></div>
                                <div class="bubble-cyber ai-bubble">
                                    Akses Database Berhasil. Selamat bekerja, <strong><?= $user_logged ?></strong>. Saya sudah memuat data Payment, Client, dan Team ke dalam sistem memori. Apa yang ingin Anda analisis hari ini?
                                </div>
                            </div>
                        </div>

                        <div class="terminal-input-zone">
                            <div id="fileStagingArea"></div>
                            <div class="command-bar-glass focus-glow">
                                <label for="fileInput" class="icon-btn-utility staging-trigger">
                                    <i class="fas fa-camera-retro"></i>
                                    <input type="file" id="fileInput" hidden onchange="handleStaging()">
                                </label>
                                <input type="text" id="aiCommanderInput" placeholder="Command Core System..." onkeyup="if(event.key==='Enter') executeCommand()">
                                <button class="btn-fire-neon" onclick="executeCommand()"><i class="fas fa-bolt"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="data-intel-column">
                    <div class="glass-container intel-card animate-slide-up" style="animation-delay: 0.1s;">
                        <div class="card-intel-header">
                            <i class="fas fa-brain text-neon"></i>
                            <span class="bold-header">INTEL PROJECT NOTES</span>
                        </div>
                        <div class="intel-form">
                            <input type="text" id="noteTitle" placeholder="Milestone Title" class="cyber-input bold-font">
                            <div class="form-grid-2">
                                <input type="date" id="noteDate" value="<?= date('Y-m-d') ?>" class="cyber-input">
                                <input type="time" id="noteTime" value="<?= date('H:i') ?>" class="cyber-input">
                            </div>
                            <textarea id="noteBody" placeholder="Log aktivitas detail..." class="cyber-input" onkeyup="autoScanProject(this.value)"></textarea>
                            <div id="scanResult"></div>
                            <button class="btn-submit-cyber" onclick="commitNoteToDatabase()">PUSH TO CORE</button>
                        </div>
                    </div>

                    <div class="glass-container intel-card animate-slide-up" style="animation-delay: 0.2s;">
                        <div class="calendar-header">
                            <button class="cal-nav-btn animate-hover-left" onclick="navMonth(-1)"><i class="fas fa-arrow-left"></i></button>
                            <h3 id="calMonthDisplay">...</h3>
                            <button class="cal-nav-btn animate-hover-right" onclick="navMonth(1)"><i class="fas fa-arrow-right"></i></button>
                        </div>
                        <div id="calendarGrid" class="cyber-calendar-grid"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const clientList = <?= json_encode($clients) ?>;
        let stagedFile = null, stagedMime = null;
        let cMonth = new Date().getMonth(), cYear = new Date().getFullYear();

        // 1. CALENDAR POP-UP FIX
        function showAgendaDetail(jsonStr) {
            try {
                const data = JSON.parse(decodeURIComponent(jsonStr));
                if(data.length === 0) return;

                let html = `<div class="agenda-scroll-area">`;
                data.forEach(item => {
                    html += `
                        <div class="milestone-card animate-pop">
                            <div class="ms-header">
                                <span class="ms-title">${item.title}</span>
                                <span class="ms-time">${item.time}</span>
                            </div>
                            <div class="ms-project">PROJECT ID: ${item.project || 'INTERNAL'}</div>
                            <div class="ms-body">${item.note}</div>
                        </div>`;
                });
                html += `</div>`;

                Swal.fire({
                    title: '<span style="color:#a1ff5a; font-weight:900;">MILTONE ARCHIVE</span>',
                    html: html,
                    background: 'rgba(5,7,5,0.98)',
                    width: '550px',
                    confirmButtonText: 'ACKNOWLEDGE',
                    confirmButtonColor: '#a1ff5a',
                    customClass: { popup: 'cyber-popup-border' }
                });
            } catch(e) { console.error("Data Parse Error", e); }
        }

        function syncCalendar(m, y) {
            const months = ["JANUARI","FEBRUARI","MARET","APRIL","MEI","JUNI","JULI","AGUSTUS","SEPTEMBER","OKTOBER","NOVEMBER","DESEMBER"];
            document.getElementById('calMonthDisplay').innerText = months[m] + " " + y;
            fetch(`/dashboard/workspace/calendar_logic.php?m=${m+1}&y=${y}`)
                .then(r => r.text())
                .then(h => document.getElementById('calendarGrid').innerHTML = h);
        }

        function navMonth(dir) {
            cMonth += dir;
            if(cMonth < 0) { cMonth = 11; cYear--; }
            if(cMonth > 11) { cMonth = 0; cYear++; }
            syncCalendar(cMonth, cYear);
        }

        async function executeCommand() {
            const cmdInput = document.getElementById('aiCommanderInput'), terminal = document.getElementById('chatInterface');
            const isDT = document.getElementById('deepThinkToggle').checked;
            if(!cmdInput.value && !stagedFile) return;

            let userHtml = `<div class="msg-group user animate-slide-in-right"><div class="bubble-cyber user-bubble">`;
            if(stagedFile && stagedMime.startsWith('image/')) userHtml += `<img src="data:${stagedMime};base64,${stagedFile}" class="scan-img-inline">`;
            userHtml += `${cmdInput.value}</div></div>`;
            terminal.innerHTML += userHtml;
            const prompt = cmdInput.value; cmdInput.value = "";
            terminal.scrollTop = terminal.scrollHeight;

            const tid = 'ai_' + Date.now();
            terminal.innerHTML += `
                <div class="msg-group ai animate-pop" id="${tid}">
                    <div class="ai-sys-icon"><i class="fas fa-spinner fa-spin"></i></div>
                    <div class="bubble-cyber thinking-bubble">
                        <div class="thinking-hologram"></div>
                        <span>QUANTUM ANALYSIS...</span>
                    </div>
                </div>`;
            terminal.scrollTop = terminal.scrollHeight;

            try {
                const response = await fetch('/dashboard/workspace/ai-handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ message: prompt, file: stagedFile, mimeType: stagedMime, deepThink: isDT })
                });
                const resData = await response.json();
                document.getElementById(tid).remove();
                
                terminal.innerHTML += `
                    <div class="msg-group ai animate-pop">
                        <div class="ai-sys-icon"><i class="fas fa-microchip"></i></div>
                        <div class="bubble-cyber ai-bubble">${resData.choices[0].message.content.replace(/\n/g, '<br>')}</div>
                    </div>`;
                terminal.scrollTop = terminal.scrollHeight;
                resetStaging();
            } catch (e) { document.getElementById(tid).innerHTML = "Neural failure."; }
        }

        function commitNoteToDatabase() {
            const payload = {
                title: document.getElementById('noteTitle').value,
                date: document.getElementById('noteDate').value,
                time: document.getElementById('noteTime').value,
                note: document.getElementById('noteBody').value,
                project: document.getElementById('scanResult').innerText.replace('ID: ', '').trim()
            };
            if(!payload.title || !payload.note) return Swal.fire({ title:'INVALID_DATA', background:'#050705', color:'#fff', icon:'error' });
            fetch('/dashboard/workspace/save_note.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) })
                .then(() => {
                    Swal.fire({ title:'SYNC_SUCCESS', background:'#050705', color:'#fff', icon:'success', confirmButtonColor:'#a1ff5a' });
                    syncCalendar(cMonth, cYear);
                    document.getElementById('noteTitle').value = ""; document.getElementById('noteBody').value = "";
                });
        }

        function handleStaging() {
            const f = document.getElementById('fileInput').files[0], r = new FileReader();
            r.onload = e => { stagedFile = e.target.result.split(',')[1]; stagedMime = f.type;
            document.getElementById('fileStagingArea').innerHTML = `<div class="staging-pill animate-pop"><img src="${f.type.startsWith('image/') ? e.target.result : ''}"><span>${f.name}</span><i class="fas fa-times-circle" onclick="resetStaging()"></i></div>`; };
            r.readAsDataURL(f);
        }
        function resetStaging() { stagedFile = null; document.getElementById('fileStagingArea').innerHTML = ""; }
        function clearChat() { document.getElementById('chatInterface').innerHTML = ""; }
        function autoScanProject(v) {
            const found = clientList.find(c => v.toLowerCase().includes(c.toLowerCase()));
            document.getElementById('scanResult').innerHTML = found ? `<div class="tag-detected animate-pop"><i class="fas fa-project-diagram"></i> ID: ${found}</div>` : "";
        }
        syncCalendar(cMonth, cYear);
    </script>
</body>
</html>
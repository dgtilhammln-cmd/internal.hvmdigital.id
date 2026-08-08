<?php
$current_page = $_SERVER['REQUEST_URI'];
$is_super_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');

// Fetch AI icon from DB (only if super_admin to avoid unnecessary query)
$ai_icon_src = '';
$ai_name_display = 'AI';
if($is_super_admin && isset($conn)) {
    $q_icon = mysqli_query($conn, "SELECT setting_key, setting_value FROM ai_settings WHERE setting_key IN ('ai_icon','ai_name')");
    if($q_icon) {
        while($ri = mysqli_fetch_assoc($q_icon)) {
            if($ri['setting_key'] === 'ai_icon' && $ri['setting_value'])
                $ai_icon_src = '/uploads/ai/' . htmlspecialchars($ri['setting_value']);
            if($ri['setting_key'] === 'ai_name' && $ri['setting_value'])
                $ai_name_display = htmlspecialchars($ri['setting_value']);
        }
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/dashboard/sidebar.style.css">

<nav class="sidebar">
    <!-- Logo hanya muncul di Desktop/Minimize Sidebar -->
    <div class="logo-container">
        <img src="/uploads/icon.png" class="logo-min" alt="Icon">
        <img src="/uploads/logohvm.png" class="logo-full" alt="Logo">
    </div>

    <!-- Nav List tetap satu kontainer untuk memudahkan transisi -->
    <div class="nav-list">
        <a href="/dashboard/" class="nav-item <?= (strpos($current_page, 'overview.php')) ? 'active' : '' ?>" title="Overview">
            <i class="fas fa-th-large"></i>
            <span class="nav-text">Overview</span>
        </a>

        <a href="/dashboard/performance/" class="nav-item <?= (strpos($current_page, 'performance')) ? 'active' : '' ?>" title="Performance">
            <i class="fas fa-chart-line"></i>
            <span class="nav-text">Performance</span>
        </a>

        <a href="/dashboard/clients/" class="nav-item <?= (strpos($current_page, 'clients')) ? 'active' : '' ?>" title="Clients">
            <i class="fas fa-users"></i>
            <span class="nav-text">Clients</span>
        </a>

        <a href="/dashboard/prospects/" class="nav-item <?= (strpos($current_page, 'prospects')) ? 'active' : '' ?>" title="Prospects">
            <i class="fas fa-binoculars"></i>
            <span class="nav-text">Prospects</span>
        </a>

        <a href="/dashboard/payment/" class="nav-item <?= (strpos($current_page, 'payment')) ? 'active' : '' ?>" title="Payment">
            <i class="fas fa-wallet"></i>
            <span class="nav-text">Payment</span>
        </a>

        <a href="/dashboard/invoice/" class="nav-item <?= (strpos($current_page, 'invoice')) ? 'active' : '' ?>" title="Invoice">
            <i class="fas fa-file-invoice"></i>
            <span class="nav-text">Invoice</span>
        </a>

        <a href="/dashboard/teams/" class="nav-item <?= (strpos($current_page, 'teams')) ? 'active' : '' ?>" title="Teams">
            <i class="fas fa-user-friends"></i>
            <span class="nav-text">Teams</span>
        </a>

        <?php if($is_super_admin): ?>
        <a href="/dashboard/settings/" class="nav-item <?= (strpos($current_page, 'settings')) ? 'active' : '' ?>" title="Settings AI">
            <i class="fas fa-cog"></i>
            <span class="nav-text">Settings AI</span>
        </a>
        <?php endif; ?>

        <a href="/dashboard/logout.php" class="nav-item logout-item" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</nav>

<?php if($is_super_admin): ?>
<!-- ============================================================
     AI FLOATING CHAT BUTTON + MODAL (Super Admin Only)
     ============================================================ -->

<style>
/* ─── FLOATING BUTTON ─── */
#ai-fab {
    position: fixed;
    bottom: 28px;
    right: 28px;
    width: 62px;
    height: 62px;
    border-radius: 50%;
    background: linear-gradient(135deg, #a1ff5a, #4efdc4);
    border: none;
    cursor: pointer;
    z-index: 99998;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 32px rgba(161,255,90,0.35), 0 0 0 0 rgba(161,255,90,0.4);
    transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.275), box-shadow 0.3s;
    animation: fabPulse 3s infinite;
    overflow: hidden;
}
#ai-fab:hover {
    transform: scale(1.08) translateY(-3px);
    box-shadow: 0 14px 40px rgba(161,255,90,0.5), 0 0 0 8px rgba(161,255,90,0.1);
    animation: none;
}
#ai-fab img { width:100%; height:100%; object-fit:cover; border-radius:50%; position:absolute; inset:0; transition:opacity 0.2s; }
#ai-fab .fab-inner { display:flex; align-items:center; justify-content:center; width:100%; height:100%; border-radius:50%; transition:opacity 0.2s; }
#ai-fab .fab-icon-wrap { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; transition:opacity 0.25s, transform 0.25s; }
#ai-fab .fab-icon-wrap.icon-layer { opacity:1; transform:scale(1); }
#ai-fab .fab-icon-wrap.close-layer { opacity:0; transform:scale(0.5); }
#ai-fab.open .fab-icon-wrap.icon-layer { opacity:0; transform:scale(0.5); }
#ai-fab.open .fab-icon-wrap.close-layer { opacity:1; transform:scale(1); }
#ai-fab .fab-icon { font-size:1.6rem; color:#000; }
#ai-fab .fab-icon-wrap.close-layer i { font-size:1.4rem; color:#000; }
#ai-fab.open { background: linear-gradient(135deg, #ff6b6b, #ff9f43); animation:none; }
#ai-fab.open img { opacity:0; pointer-events:none; }
@keyframes fabPulse {
    0%,100% { box-shadow: 0 8px 32px rgba(161,255,90,0.35), 0 0 0 0 rgba(161,255,90,0.4); }
    50% { box-shadow: 0 8px 32px rgba(161,255,90,0.35), 0 0 0 12px rgba(161,255,90,0); }
}

/* Mobile safe area */
@media(max-width:768px) {
    #ai-fab { bottom: 95px; right: 20px; width:54px; height:54px; }
}

/* ─── CHAT MODAL ─── */
#ai-chat-modal {
    position: fixed;
    bottom: 105px;
    right: 28px;
    width: 390px;
    height: 560px;
    background: rgba(10,10,10,0.97);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 24px;
    z-index: 99997;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(0,0,0,0.8), 0 0 0 1px rgba(161,255,90,0.06);
    transform: scale(0.85) translateY(30px);
    opacity: 0;
    pointer-events: none;
    transition: all 0.35s cubic-bezier(0.175,0.885,0.32,1.275);
    backdrop-filter: blur(30px);
}
#ai-chat-modal.open {
    transform: scale(1) translateY(0);
    opacity: 1;
    pointer-events: all;
}
@media(max-width:768px) {
    #ai-chat-modal {
        bottom: 0; right: 0; left: 0; width: 100%; height: 80vh;
        border-radius: 24px 24px 0 0;
        transform: translateY(100%);
    }
    #ai-chat-modal.open { transform: translateY(0); }
}

/* ─── MODAL HEADER ─── */
.ai-chat-header {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255,255,255,0.02);
    flex-shrink: 0;
}
.ai-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg,#a1ff5a,#4efdc4);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: #000; font-weight: 800; flex-shrink: 0;
    overflow: hidden;
}
.ai-avatar img { width:100%; height:100%; object-fit:cover; }
.ai-header-info { flex: 1; }
.ai-header-name { font-weight: 700; font-size: 0.9rem; color: #fff; }
.ai-header-status { font-size: 0.72rem; color: #a1ff5a; display: flex; align-items: center; gap: 5px; }
.ai-header-status::before { content:''; width:7px; height:7px; border-radius:50%; background:#a1ff5a; box-shadow:0 0 6px #a1ff5a; display:inline-block; }
.ai-header-actions { display: flex; gap: 6px; }
.ai-hbtn { width:30px; height:30px; border-radius:8px; border:none; background:rgba(255,255,255,0.05); color:#666; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.85rem; transition:0.2s; }
.ai-hbtn:hover { background:rgba(255,255,255,0.1); color:#fff; }

/* ─── MESSAGES ─── */
.ai-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    scroll-behavior: smooth;
}
.ai-messages::-webkit-scrollbar { width: 4px; }
.ai-messages::-webkit-scrollbar-thumb { background:#222; border-radius:10px; }
.msg-row { display: flex; gap: 8px; animation: msgIn 0.25s ease; }
.msg-row.user { flex-direction: row-reverse; }
@keyframes msgIn { from{opacity:0;transform:translateY(8px);} to{opacity:1;transform:translateY(0);} }
.msg-avatar-sm {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700;
    overflow: hidden;
}
.msg-avatar-sm.ai-av { background: linear-gradient(135deg,#a1ff5a,#4efdc4); color:#000; }
.msg-avatar-sm.ai-av img { width:100%; height:100%; object-fit:cover; }
.msg-avatar-sm.user-av { background: rgba(255,255,255,0.1); color:#aaa; }
.msg-bubble {
    max-width: 78%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 0.83rem;
    line-height: 1.55;
    word-break: break-word;
}
.msg-bubble.ai {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.07);
    color: #ddd;
    border-bottom-left-radius: 4px;
}
.msg-bubble.user {
    background: linear-gradient(135deg, rgba(161,255,90,0.15), rgba(78,253,196,0.1));
    border: 1px solid rgba(161,255,90,0.2);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.msg-bubble.error { background: rgba(255,80,80,0.08); border-color:rgba(255,80,80,0.2); color:#ff8888; }
.msg-time { font-size:0.62rem; color:#444; margin-top:4px; }

/* Typing indicator */
.typing-dot { display:inline-block; width:7px; height:7px; border-radius:50%; background:#a1ff5a; margin:0 2px; animation:typingBounce 1.2s infinite; }
.typing-dot:nth-child(2) { animation-delay:0.2s; }
.typing-dot:nth-child(3) { animation-delay:0.4s; }
@keyframes typingBounce { 0%,80%,100%{transform:translateY(0);opacity:0.4;} 40%{transform:translateY(-5px);opacity:1;} }

/* ─── QUICK PROMPTS ─── */
.quick-prompts {
    padding: 8px 14px;
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    border-top: 1px solid rgba(255,255,255,0.04);
    flex-shrink: 0;
}
.quick-btn {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 20px;
    padding: 5px 12px;
    color: #777;
    font-size: 0.72rem;
    cursor: pointer;
    transition: 0.2s;
    font-family: inherit;
    white-space: nowrap;
}
.quick-btn:hover { background:rgba(161,255,90,0.08); border-color:rgba(161,255,90,0.3); color:#a1ff5a; }

/* ─── INPUT AREA ─── */
.ai-input-area {
    padding: 12px 14px;
    border-top: 1px solid rgba(255,255,255,0.06);
    display: flex;
    gap: 8px;
    align-items: flex-end;
    flex-shrink: 0;
}
#aiMessageInput {
    flex: 1;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 10px 14px;
    color: #fff;
    font-family: inherit;
    font-size: 0.85rem;
    outline: none;
    resize: none;
    overflow: hidden;
    max-height: 100px;
    min-height: 40px;
    line-height: 1.4;
    transition: border-color 0.2s;
}
#aiMessageInput:focus { border-color: rgba(161,255,90,0.4); }
#aiMessageInput::placeholder { color: #333; }
.ai-send-btn {
    width: 40px; height: 40px; border-radius: 12px;
    background: linear-gradient(135deg, #a1ff5a, #4efdc4);
    border: none; color: #000; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; font-weight: 700;
    transition: 0.2s; flex-shrink: 0;
}
.ai-send-btn:hover { transform:scale(1.05); box-shadow:0 4px 15px rgba(161,255,90,0.3); }
.ai-send-btn:disabled { opacity:0.4; cursor:not-allowed; transform:none; }
</style>

<!-- Floating Button -->
<button id="ai-fab" onclick="toggleAIChat()" title="AI Asisten HVM">
    <?php if($ai_icon_src): ?>
        <img src="<?= $ai_icon_src ?>" alt="AI">
    <?php endif; ?>
    <!-- Icon layer (shown when closed) -->
    <span class="fab-icon-wrap icon-layer">
        <?php if(!$ai_icon_src): ?>
            <i class="fas fa-robot fab-icon"></i>
        <?php endif; ?>
    </span>
    <!-- Close layer (shown when open) -->
    <span class="fab-icon-wrap close-layer">
        <i class="fas fa-times"></i>
    </span>
</button>

<!-- Chat Modal -->
<div id="ai-chat-modal">
    <div class="ai-chat-header">
        <div class="ai-avatar" id="aiAvatarHeader">
            <?php if($ai_icon_src): ?>
                <img src="<?= $ai_icon_src ?>" alt="AI">
            <?php else: ?>
                <i class="fas fa-robot"></i>
            <?php endif; ?>
        </div>
        <div class="ai-header-info">
            <div class="ai-header-name" id="aiNameHeader"><?= $ai_name_display ?></div>
            <div class="ai-header-status">Online &bull; Siap membantu</div>
        </div>
        <div class="ai-header-actions">
            <button class="ai-hbtn" onclick="clearChat()" title="Clear chat"><i class="fas fa-trash-alt"></i></button>
            <button class="ai-hbtn" onclick="toggleAIChat()" title="Tutup"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <div class="ai-messages" id="aiMessages">
        <!-- Welcome message inserted by JS -->
    </div>

    <div class="quick-prompts" id="quickPrompts">
        <button class="quick-btn" onclick="sendQuick('Ringkasan meeting minggu ini')">📅 Meeting minggu ini</button>
        <button class="quick-btn" onclick="sendQuick('Berapa jumlah klien aktif?')">👥 Klien aktif</button>
        <button class="quick-btn" onclick="sendQuick('Status prospek terbaru')">🎯 Prospek terbaru</button>
        <button class="quick-btn" onclick="sendQuick('Invoice yang belum lunas')">💰 Invoice pending</button>
    </div>

    <div class="ai-input-area">
        <textarea id="aiMessageInput" placeholder="Tanya apa saja tentang data perusahaan..." rows="1"
            onkeydown="handleChatKey(event)" oninput="autoResize(this)"></textarea>
        <button class="ai-send-btn" id="aiSendBtn" onclick="sendAIMessage()">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<script>
let aiChatOpen = false;
let aiHistory  = [];
let aiTyping   = false;
const aiName   = <?= json_encode($ai_name_display) ?>;

function toggleAIChat() {
    aiChatOpen = !aiChatOpen;
    const modal = document.getElementById('ai-chat-modal');
    const fab   = document.getElementById('ai-fab');
    modal.classList.toggle('open', aiChatOpen);
    fab.classList.toggle('open', aiChatOpen);
    if(aiChatOpen && document.getElementById('aiMessages').children.length === 0) {
        addMessage('ai', `Halo! Saya <strong>${aiName}</strong> 👋<br>Saya punya akses ke semua data internal HVM — meeting, klien, prospek, tim, dan invoice.<br><br>Ada yang bisa saya bantu?`);
    }
    if(aiChatOpen) setTimeout(() => document.getElementById('aiMessageInput').focus(), 350);
}

function addMessage(role, html, isError=false) {
    const msgs  = document.getElementById('aiMessages');
    const now   = new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
    const isAI  = role === 'ai';
    const row   = document.createElement('div');
    row.className = 'msg-row' + (isAI ? '' : ' user');

    const avClass = isAI ? 'ai-av' : 'user-av';
    const avContent = isAI
        ? (<?= $ai_icon_src ? "true" : "false" ?> ? `<img src="<?= $ai_icon_src ?>">` : '<i class="fas fa-robot"></i>')
        : '<i class="fas fa-user"></i>';

    const bubClass = isAI ? (isError ? 'error' : 'ai') : 'user';
    row.innerHTML = `
        <div class="msg-avatar-sm ${avClass}">${avContent}</div>
        <div>
            <div class="msg-bubble ${bubClass}">${html}</div>
            <div class="msg-time">${now}</div>
        </div>`;
    msgs.appendChild(row);
    msgs.scrollTop = msgs.scrollHeight;
    return row;
}

function showTyping() {
    const msgs = document.getElementById('aiMessages');
    const row  = document.createElement('div');
    row.className = 'msg-row';
    row.id = 'typingRow';
    const avContent = <?= $ai_icon_src ? "true" : "false" ?> ? `<img src="<?= $ai_icon_src ?>">` : '<i class="fas fa-robot"></i>';
    row.innerHTML = `
        <div class="msg-avatar-sm ai-av">${avContent}</div>
        <div><div class="msg-bubble ai" style="padding:12px 16px;">
            <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
        </div></div>`;
    msgs.appendChild(row);
    msgs.scrollTop = msgs.scrollHeight;
}

function hideTyping() {
    const tr = document.getElementById('typingRow');
    if(tr) tr.remove();
}

async function sendAIMessage(msg) {
    if(aiTyping) return;
    const input = document.getElementById('aiMessageInput');
    const text  = (msg || input.value).trim();
    if(!text) return;

    // Hide quick prompts after first message
    document.getElementById('quickPrompts').style.display = 'none';

    input.value = '';
    input.style.height = 'auto';
    addMessage('user', escHtml(text));
    aiHistory.push({ role:'user', content: text });

    aiTyping = true;
    document.getElementById('aiSendBtn').disabled = true;
    showTyping();

    try {
        const fd = new FormData();
        fd.append('action', 'chat');
        fd.append('message', text);
        fd.append('history', JSON.stringify(aiHistory.slice(-10)));

        const r = await fetch('/dashboard/ai/handler.php', { method:'POST', body:fd });
        const d = await r.json();
        hideTyping();

        if(d.error) {
            addMessage('ai', '<i class="fas fa-exclamation-triangle"></i> ' + escHtml(d.error), true);
        } else {
            const reply = d.reply || '';
            const formatted = reply
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code style="background:rgba(255,255,255,0.08);padding:1px 5px;border-radius:4px;">$1</code>')
                .replace(/\n/g, '<br>');
            addMessage('ai', formatted);
            aiHistory.push({ role:'assistant', content: reply });
        }
    } catch(e) {
        hideTyping();
        addMessage('ai', 'Gagal terhubung ke server. Coba lagi.', true);
    }

    aiTyping = false;
    document.getElementById('aiSendBtn').disabled = false;
}

function sendQuick(msg) { sendAIMessage(msg); }

function clearChat() {
    aiHistory = [];
    document.getElementById('aiMessages').innerHTML = '';
    document.getElementById('quickPrompts').style.display = 'flex';
    addMessage('ai', `Chat dibersihkan. Ada lagi yang bisa saya bantu, <strong><?= addslashes(htmlspecialchars($_SESSION['admin'] ?? '')) ?></strong>?`);
}

function handleChatKey(e) {
    if(e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendAIMessage(); }
}
function autoResize(el) {
    el.style.height = '40px';
    el.style.height = Math.max(40, Math.min(el.scrollHeight, 100)) + 'px';
}
function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}
</script>
<?php endif; ?>
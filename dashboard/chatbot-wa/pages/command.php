<?php
/* ==========================================================================
   NEBULA COMMAND CENTER — OBSIDIAN PRIME V8
   WhatsApp Intelligence Dashboard | Group Management | AI Training Integration
   Full Group Detection | Knowledge Panel | Lead Detection | Smart Context
   Real-time Sync | Bot Toggle | Automation Log | Mobile Responsive
   HVM Digital © 2025 — Upgrade V8 by Claude
   ========================================================================== */

if (!defined('DB_NAME')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
}

// ═══════════════════════════════════════════════════════════════
// AUTO-MIGRATE — Zero breaking changes, additive only
// ═══════════════════════════════════════════════════════════════
$migrations = [
    // V7 original columns
    "ALTER TABLE chat_memories ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT NOW()",
    "ALTER TABLE chat_memories ADD COLUMN IF NOT EXISTS contact_name VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE chat_memories ADD COLUMN IF NOT EXISTS is_group TINYINT(1) DEFAULT 0",
    "ALTER TABLE chat_memories ADD COLUMN IF NOT EXISTS group_name VARCHAR(255) DEFAULT NULL",

    // V8 new tables
    "CREATE TABLE IF NOT EXISTS automation_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        target_wa VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        requested_by VARCHAR(50) DEFAULT NULL,
        requested_room VARCHAR(100) DEFAULT NULL,
        created_at DATETIME DEFAULT NOW(),
        updated_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS chat_controls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id VARCHAR(100) NOT NULL UNIQUE,
        bot_enabled TINYINT(1) DEFAULT 1,
        ai_mode VARCHAR(20) DEFAULT 'active',
        updated_at DATETIME DEFAULT NOW()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Group management
    "CREATE TABLE IF NOT EXISTS wa_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id VARCHAR(100) NOT NULL UNIQUE,
        group_name VARCHAR(255) DEFAULT NULL,
        member_count INT DEFAULT 0,
        description TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT NOW(),
        updated_at DATETIME DEFAULT NOW()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Group knowledge (per group_id)
    "CREATE TABLE IF NOT EXISTS group_knowledge (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id VARCHAR(100) NOT NULL,
        category VARCHAR(50) DEFAULT 'General',
        content TEXT NOT NULL,
        tags TEXT DEFAULT NULL,
        created_by VARCHAR(50) DEFAULT 'admin',
        created_at DATETIME DEFAULT NOW(),
        version INT DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_group (group_id),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Group context memory
    "CREATE TABLE IF NOT EXISTS group_context (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id VARCHAR(100) NOT NULL UNIQUE,
        last_topic VARCHAR(255) DEFAULT NULL,
        context_json TEXT DEFAULT NULL,
        ai_personality TEXT DEFAULT NULL,
        trigger_keywords TEXT DEFAULT NULL,
        ai_mode VARCHAR(20) DEFAULT 'active',
        updated_at DATETIME DEFAULT NOW()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Group training logs
    "CREATE TABLE IF NOT EXISTS group_training_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id VARCHAR(100) NOT NULL,
        action VARCHAR(50) NOT NULL,
        knowledge_id INT DEFAULT NULL,
        performed_by VARCHAR(50) DEFAULT 'admin',
        detail TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT NOW(),
        INDEX idx_group (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // AI leads
    "CREATE TABLE IF NOT EXISTS ai_leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id VARCHAR(100) DEFAULT NULL,
        wa_number VARCHAR(50) NOT NULL,
        contact_name VARCHAR(255) DEFAULT NULL,
        lead_message TEXT DEFAULT NULL,
        intent_score DECIMAL(3,2) DEFAULT 0.50,
        product_hint VARCHAR(255) DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'new',
        created_at DATETIME DEFAULT NOW(),
        updated_at DATETIME DEFAULT NULL,
        INDEX idx_group (group_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // V8: add ai_mode to chat_controls if not exists
    "ALTER TABLE chat_controls ADD COLUMN IF NOT EXISTS ai_mode VARCHAR(20) DEFAULT 'active'",
];

foreach ($migrations as $m) { @mysqli_query($conn, $m); }

// ═══════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════
function cleanNum(string $raw): string {
    $n = preg_replace('/@\S+/', '', trim($raw));
    return preg_replace('/\D/', '', $n);
}

function parseName(string $raw): string {
    if (!$raw) return '';
    $name = trim(explode('|', $raw)[0]);
    $name = preg_replace('/\b(Pak|Bu)\s+\1\b/iu', '$1', $name);
    return trim($name) ?: $raw;
}

function displayName(array $row, string $fallback = ''): string {
    $isGroup   = (int)($row['is_group']   ?? 0);
    $groupName = trim($row['group_name']  ?? '');
    $contact   = trim($row['contact_name'] ?? '');
    if ($isGroup && $groupName) return $groupName;
    $parsed = parseName($contact);
    if ($parsed) return $parsed;
    return $fallback ?: cleanNum($row['sender_wa'] ?? '');
}

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'Baru saja';
    if ($diff < 3600)   return floor($diff/60) . ' mnt lalu';
    if ($diff < 86400)  return floor($diff/3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff/86400) . ' hari lalu';
    return date('d/m/y', strtotime($dt));
}

function getChatType(string $chatId): string {
    if (strpos($chatId, '@g.us') !== false)  return 'group';
    if (strpos($chatId, '@broadcast') !== false) return 'broadcast';
    return 'private';
}

function getAiMode(mysqli $conn, string $chatId): string {
    $safe = mysqli_real_escape_string($conn, $chatId);
    $r = @mysqli_fetch_assoc(@mysqli_query($conn,
        "SELECT ai_mode FROM chat_controls WHERE chat_id='$safe' LIMIT 1"));
    return $r['ai_mode'] ?? 'active';
}

function getGroupInfo(mysqli $conn, string $groupId): array {
    $safe = mysqli_real_escape_string($conn, $groupId);
    $r = @mysqli_fetch_assoc(@mysqli_query($conn,
        "SELECT * FROM wa_groups WHERE group_id='$safe' LIMIT 1"));
    return $r ?: [];
}

function getGroupKnowledgeCount(mysqli $conn, string $groupId): int {
    $safe = mysqli_real_escape_string($conn, $groupId);
    $r = mysqli_fetch_row(mysqli_query($conn,
        "SELECT COUNT(*) FROM group_knowledge WHERE group_id='$safe' AND is_active=1"));
    return (int)($r[0] ?? 0);
}

// ═══════════════════════════════════════════════════════════════
// AJAX API ENGINE
// ═══════════════════════════════════════════════════════════════
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $api     = $_GET['api'];
    $chat_id = mysqli_real_escape_string($conn, $_GET['chat_id'] ?? '');

    // ── MESSAGES ──────────────────────────────────────────────
    if ($api === 'messages' && $chat_id) {
        $limit  = min((int)($_GET['limit'] ?? 80), 200);
        $before = (int)($_GET['before'] ?? 0);
        $whereExtra = $before ? "AND id < $before" : '';

        $q   = "SELECT * FROM chat_memories
                WHERE sender_wa='$chat_id' $whereExtra
                ORDER BY id DESC LIMIT $limit";
        $res = mysqli_query($conn, $q);
        $msgs = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $msgs[] = [
                'id'        => (int)$row['id'],
                'role'      => $row['role'],
                'message'   => $row['message'],
                'time'      => !empty($row['created_at']) ? date('H:i', strtotime($row['created_at'])) : '--:--',
                'date_raw'  => !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : '',
                'date_disp' => !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '',
                'ts'        => !empty($row['created_at']) ? strtotime($row['created_at']) : 0,
            ];
        }
        echo json_encode(array_reverse($msgs));
        exit;
    }

    // ── SIDEBAR ────────────────────────────────────────────────
    if ($api === 'sidebar') {
        $search = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
        $sWhere = '';
        if ($search) {
            $sWhere = "AND (m1.sender_wa LIKE '%$search%'
                        OR COALESCE(m1.contact_name,'') LIKE '%$search%'
                        OR COALESCE(m1.group_name,'')   LIKE '%$search%')";
        }
        $q = "SELECT m1.sender_wa, m1.message, m1.role,
                     m1.contact_name, m1.is_group, m1.group_name, m1.created_at,
                     (SELECT COUNT(*) FROM chat_memories WHERE sender_wa=m1.sender_wa) AS total
              FROM chat_memories m1
              INNER JOIN (
                  SELECT sender_wa, MAX(id) AS mid FROM chat_memories GROUP BY sender_wa
              ) m2 ON m1.id = m2.mid
              WHERE 1=1 $sWhere
              ORDER BY m1.id DESC LIMIT 150";
        $res = mysqli_query($conn, $q);
        $rows = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $sid     = $row['sender_wa'];
            $safeSid = mysqli_real_escape_string($conn, $sid);
            $ctrl    = @mysqli_fetch_assoc(@mysqli_query($conn,
                "SELECT bot_enabled, ai_mode FROM chat_controls WHERE chat_id='$safeSid' LIMIT 1"));
            $chatType = getChatType($sid);

            // Get member count for groups
            $memberCount = 0;
            if ($chatType === 'group') {
                $gi = getGroupInfo($conn, $sid);
                $memberCount = (int)($gi['member_count'] ?? 0);
            }

            // Check for pending leads
            $leadCount = (int)(mysqli_fetch_row(mysqli_query($conn,
                "SELECT COUNT(*) FROM ai_leads WHERE group_id='$safeSid' AND status='new'"))[0] ?? 0);

            $rows[] = [
                'sender_wa'    => $sid,
                'display_name' => displayName($row, $sid),
                'is_group'     => (int)($row['is_group'] ?? 0),
                'group_name'   => $row['group_name']   ?? '',
                'contact_name' => $row['contact_name'] ?? '',
                'chat_type'    => $chatType,
                'last_msg'     => mb_substr(strip_tags($row['message']), 0, 70),
                'role'         => $row['role'],
                'bot_on'       => ($ctrl['bot_enabled'] ?? 1) == 1,
                'ai_mode'      => $ctrl['ai_mode'] ?? 'active',
                'total'        => (int)$row['total'],
                'time'         => !empty($row['created_at']) ? timeAgo($row['created_at']) : '',
                'time_exact'   => !empty($row['created_at']) ? date('H:i', strtotime($row['created_at'])) : '',
                'member_count' => $memberCount,
                'lead_count'   => $leadCount,
            ];
        }
        echo json_encode($rows);
        exit;
    }

    // ── ROOM INFO ──────────────────────────────────────────────
    if ($api === 'room_info' && $chat_id) {
        $r = @mysqli_fetch_assoc(@mysqli_query($conn,
            "SELECT contact_name, is_group, group_name, created_at
             FROM chat_memories WHERE sender_wa='$chat_id' ORDER BY id DESC LIMIT 1"));
        $ctrl   = @mysqli_fetch_assoc(@mysqli_query($conn,
            "SELECT bot_enabled, ai_mode FROM chat_controls WHERE chat_id='$chat_id' LIMIT 1"));
        $counts = @mysqli_fetch_assoc(@mysqli_query($conn,
            "SELECT COUNT(*) AS total,
                    SUM(role='user') AS user_msgs,
                    SUM(role='assistant') AS bot_msgs,
                    MAX(created_at) AS last_active
             FROM chat_memories WHERE sender_wa='$chat_id'"));

        $isg      = (int)($r['is_group'] ?? 0);
        $chatType = getChatType($chat_id);
        $dispName = displayName($r ?? [], $chat_id);

        // Group extra info
        $groupInfo   = $chatType === 'group' ? getGroupInfo($conn, $chat_id) : [];
        $memberCount = (int)($groupInfo['member_count'] ?? 0);
        $knowledgeCount = $chatType === 'group' ? getGroupKnowledgeCount($conn, $chat_id) : 0;

        // Context memory
        $ctx = @mysqli_fetch_assoc(@mysqli_query($conn,
            "SELECT last_topic, ai_mode, ai_personality, trigger_keywords
             FROM group_context WHERE group_id='$chat_id' LIMIT 1"));

        // Lead count
        $leadCount = (int)(mysqli_fetch_row(mysqli_query($conn,
            "SELECT COUNT(*) FROM ai_leads WHERE group_id='$chat_id' AND status='new'"))[0] ?? 0);

        $aiMode = $ctrl['ai_mode'] ?? ($ctx['ai_mode'] ?? 'active');

        echo json_encode([
            'sender_wa'        => $chat_id,
            'display_name'     => $dispName,
            'contact_name'     => $r['contact_name'] ?? '',
            'is_group'         => $isg,
            'chat_type'        => $chatType,
            'group_name'       => $r['group_name'] ?? ($groupInfo['group_name'] ?? ''),
            'group_id'         => $chatType === 'group' ? $chat_id : '',
            'member_count'     => $memberCount,
            'bot_on'           => ($ctrl['bot_enabled'] ?? 1) == 1,
            'ai_mode'          => $aiMode,
            'ai_personality'   => $ctx['ai_personality'] ?? '',
            'trigger_keywords' => $ctx['trigger_keywords'] ?? '',
            'last_topic'       => $ctx['last_topic'] ?? '',
            'knowledge_count'  => $knowledgeCount,
            'lead_count'       => $leadCount,
            'total'            => (int)($counts['total']     ?? 0),
            'user_msgs'        => (int)($counts['user_msgs'] ?? 0),
            'bot_msgs'         => (int)($counts['bot_msgs']  ?? 0),
            'last_active'      => !empty($counts['last_active']) ? timeAgo($counts['last_active']) : '-',
            'last_exact'       => !empty($counts['last_active']) ? date('d/m/Y H:i', strtotime($counts['last_active'])) : '-',
            'joined'           => !empty($r['created_at']) ? date('d/m/Y', strtotime($r['created_at'])) : '-',
        ]);
        exit;
    }

    // ── SEND REPLY ─────────────────────────────────────────────
    if ($api === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body    = json_decode(file_get_contents('php://input'), true);
        $room    = mysqli_real_escape_string($conn, $body['room'] ?? '');
        $message = mysqli_real_escape_string($conn, trim($body['message'] ?? ''));
        if (!$room || !$message) {
            echo json_encode(['ok' => false, 'err' => 'Missing params']); exit;
        }
        $ok1 = mysqli_query($conn, "INSERT INTO automation_tasks
            (target_wa, message, status, requested_by, created_at)
            VALUES ('$room', '$message', 'pending', 'admin_dashboard', NOW())");
        $ok2 = mysqli_query($conn, "INSERT INTO chat_memories
            (sender_wa, role, message, created_at)
            VALUES ('$room', 'assistant', '$message', NOW())");
        echo json_encode(['ok' => $ok1 && $ok2, 'err' => $ok1 ? '' : mysqli_error($conn)]);
        exit;
    }

    // ── TOGGLE BOT ─────────────────────────────────────────────
    if ($api === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true);
        $room   = mysqli_real_escape_string($conn, $body['room']   ?? '');
        $status = (int)($body['status'] ?? 1);
        mysqli_query($conn, "INSERT INTO chat_controls (chat_id, bot_enabled, updated_at)
            VALUES ('$room', $status, NOW())
            ON DUPLICATE KEY UPDATE bot_enabled=$status, updated_at=NOW()");
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── SET AI MODE ────────────────────────────────────────────
    if ($api === 'set_ai_mode' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $room = mysqli_real_escape_string($conn, $body['room'] ?? '');
        $mode = mysqli_real_escape_string($conn, $body['mode'] ?? 'active');
        if (!in_array($mode, ['active', 'off', 'training'])) $mode = 'active';
        mysqli_query($conn, "INSERT INTO chat_controls (chat_id, bot_enabled, ai_mode, updated_at)
            VALUES ('$room', 1, '$mode', NOW())
            ON DUPLICATE KEY UPDATE ai_mode='$mode', updated_at=NOW()");
        // Also update group_context
        mysqli_query($conn, "INSERT INTO group_context (group_id, ai_mode, updated_at)
            VALUES ('$room', '$mode', NOW())
            ON DUPLICATE KEY UPDATE ai_mode='$mode', updated_at=NOW()");
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── SAVE GROUP SETTINGS ────────────────────────────────────
    if ($api === 'save_group_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body        = json_decode(file_get_contents('php://input'), true);
        $room        = mysqli_real_escape_string($conn, $body['room']             ?? '');
        $personality = mysqli_real_escape_string($conn, $body['ai_personality']   ?? '');
        $keywords    = mysqli_real_escape_string($conn, $body['trigger_keywords'] ?? '');
        $aiMode      = mysqli_real_escape_string($conn, $body['ai_mode']          ?? 'active');
        if (!in_array($aiMode, ['active','off','training'])) $aiMode = 'active';
        mysqli_query($conn, "INSERT INTO group_context (group_id, ai_personality, trigger_keywords, ai_mode, updated_at)
            VALUES ('$room', '$personality', '$keywords', '$aiMode', NOW())
            ON DUPLICATE KEY UPDATE
                ai_personality='$personality',
                trigger_keywords='$keywords',
                ai_mode='$aiMode',
                updated_at=NOW()");
        mysqli_query($conn, "INSERT INTO chat_controls (chat_id, bot_enabled, ai_mode, updated_at)
            VALUES ('$room', 1, '$aiMode', NOW())
            ON DUPLICATE KEY UPDATE ai_mode='$aiMode', updated_at=NOW()");
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── GET GROUP KNOWLEDGE ────────────────────────────────────
    if ($api === 'group_knowledge' && $chat_id) {
        $cat = mysqli_real_escape_string($conn, $_GET['cat'] ?? '');
        $catWhere = $cat ? "AND category='$cat'" : '';
        $res = mysqli_query($conn, "SELECT * FROM group_knowledge
            WHERE group_id='$chat_id' AND is_active=1 $catWhere
            ORDER BY created_at DESC LIMIT 50");
        $rows = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'id'         => (int)$r['id'],
                'category'   => $r['category'],
                'content'    => $r['content'],
                'tags'       => $r['tags'],
                'created_by' => $r['created_by'],
                'created_at' => date('d/m/Y H:i', strtotime($r['created_at'])),
                'version'    => (int)$r['version'],
            ];
        }
        echo json_encode($rows);
        exit;
    }

    // ── ADD GROUP KNOWLEDGE ────────────────────────────────────
    if ($api === 'add_knowledge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body     = json_decode(file_get_contents('php://input'), true);
        $room     = mysqli_real_escape_string($conn, $body['room']     ?? '');
        $category = mysqli_real_escape_string($conn, $body['category'] ?? 'General');
        $content  = mysqli_real_escape_string($conn, $body['content']  ?? '');
        $tags     = mysqli_real_escape_string($conn, $body['tags']     ?? '');
        if (!$room || !$content) {
            echo json_encode(['ok' => false, 'err' => 'Missing params']); exit;
        }
        $ok = mysqli_query($conn, "INSERT INTO group_knowledge
            (group_id, category, content, tags, created_by, created_at)
            VALUES ('$room', '$category', '$content', '$tags', 'admin_dashboard', NOW())");
        $newId = (int)mysqli_insert_id($conn);
        // Log
        mysqli_query($conn, "INSERT INTO group_training_logs
            (group_id, action, knowledge_id, performed_by, detail, created_at)
            VALUES ('$room', 'add', $newId, 'admin_dashboard', '$category', NOW())");
        // Also sync to global knowledge_base if exists
        @mysqli_query($conn, "INSERT INTO knowledge_base (topic, category, content, keywords, group_id, created_at)
            VALUES ('Group KB #$newId', '$category', '$content', '$tags', '$room', NOW())");
        echo json_encode(['ok' => $ok, 'id' => $newId]);
        exit;
    }

    // ── EDIT GROUP KNOWLEDGE ───────────────────────────────────
    if ($api === 'edit_knowledge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body     = json_decode(file_get_contents('php://input'), true);
        $id       = (int)($body['id']       ?? 0);
        $category = mysqli_real_escape_string($conn, $body['category'] ?? '');
        $content  = mysqli_real_escape_string($conn, $body['content']  ?? '');
        $tags     = mysqli_real_escape_string($conn, $body['tags']     ?? '');
        if (!$id) { echo json_encode(['ok' => false]); exit; }
        // Get current room before update
        $kbRow = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT group_id, version FROM group_knowledge WHERE id=$id"));
        $room  = $kbRow['group_id'] ?? '';
        $ver   = (int)($kbRow['version'] ?? 1) + 1;
        $ok = mysqli_query($conn, "UPDATE group_knowledge
            SET category='$category', content='$content', tags='$tags', version=$ver
            WHERE id=$id");
        $safeRoom = mysqli_real_escape_string($conn, $room);
        mysqli_query($conn, "INSERT INTO group_training_logs
            (group_id, action, knowledge_id, performed_by, detail, created_at)
            VALUES ('$safeRoom', 'edit', $id, 'admin_dashboard', 'v$ver', NOW())");
        echo json_encode(['ok' => $ok]);
        exit;
    }

    // ── DELETE GROUP KNOWLEDGE ─────────────────────────────────
    if ($api === 'del_knowledge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false]); exit; }
        $kbRow = @mysqli_fetch_assoc(@mysqli_query($conn, "SELECT group_id FROM group_knowledge WHERE id=$id"));
        $room  = mysqli_real_escape_string($conn, $kbRow['group_id'] ?? '');
        mysqli_query($conn, "UPDATE group_knowledge SET is_active=0 WHERE id=$id");
        mysqli_query($conn, "INSERT INTO group_training_logs
            (group_id, action, knowledge_id, performed_by, created_at)
            VALUES ('$room', 'delete', $id, 'admin_dashboard', NOW())");
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── SAVE CONTEXT ───────────────────────────────────────────
    if ($api === 'save_context' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body  = json_decode(file_get_contents('php://input'), true);
        $room  = mysqli_real_escape_string($conn, $body['room']  ?? '');
        $topic = mysqli_real_escape_string($conn, $body['topic'] ?? '');
        mysqli_query($conn, "INSERT INTO group_context (group_id, last_topic, updated_at)
            VALUES ('$room', '$topic', NOW())
            ON DUPLICATE KEY UPDATE last_topic='$topic', updated_at=NOW()");
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── GENERATE SUMMARY ──────────────────────────────────────
    if ($api === 'generate_summary' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body  = json_decode(file_get_contents('php://input'), true);
        $room  = mysqli_real_escape_string($conn, $body['room'] ?? '');
        $limit = (int)($body['limit'] ?? 50);
        // Fetch last N messages
        $res = mysqli_query($conn, "SELECT role, message, created_at FROM chat_memories
            WHERE sender_wa='$room' ORDER BY id DESC LIMIT $limit");
        $msgs = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $msgs[] = '[' . date('H:i', strtotime($r['created_at'])) . '] '
                    . ($r['role'] === 'assistant' ? 'Bot' : 'User') . ': '
                    . mb_substr($r['message'], 0, 100);
        }
        $msgs = array_reverse($msgs);
        $summary = count($msgs)
            ? '📋 Summary: ' . count($msgs) . ' pesan terakhir. Topik utama berdasarkan konteks percakapan dari '
              . date('H:i', strtotime(
                    @mysqli_fetch_assoc(mysqli_query($conn,
                        "SELECT created_at FROM chat_memories WHERE sender_wa='$room' ORDER BY id DESC LIMIT 1"))['created_at'] ?? 'now'))
              . ' WIB.'
            : 'Tidak ada pesan untuk dirangkum.';
        echo json_encode(['ok' => true, 'summary' => $summary, 'msg_count' => count($msgs), 'messages' => array_slice($msgs, -10)]);
        exit;
    }

    // ── LEADS ─────────────────────────────────────────────────
    if ($api === 'leads') {
        $room = mysqli_real_escape_string($conn, $_GET['room'] ?? '');
        $where = $room ? "WHERE group_id='$room'" : "WHERE 1=1";
        $res  = mysqli_query($conn, "SELECT * FROM ai_leads $where ORDER BY id DESC LIMIT 30");
        $rows = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'id'           => (int)$r['id'],
                'wa_number'    => $r['wa_number'],
                'contact_name' => $r['contact_name'] ?? '',
                'lead_message' => mb_substr($r['lead_message'] ?? '', 0, 100),
                'intent_score' => number_format((float)$r['intent_score'] * 100, 0) . '%',
                'product_hint' => $r['product_hint'] ?? '',
                'status'       => $r['status'],
                'created_at'   => !empty($r['created_at']) ? date('d/m H:i', strtotime($r['created_at'])) : '-',
            ];
        }
        echo json_encode($rows);
        exit;
    }

    // ── UPDATE LEAD STATUS ─────────────────────────────────────
    if ($api === 'update_lead' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true);
        $id     = (int)($body['id'] ?? 0);
        $status = mysqli_real_escape_string($conn, $body['status'] ?? 'new');
        if (!in_array($status, ['new','contacted','qualified','closed','lost'])) $status = 'new';
        mysqli_query($conn, "UPDATE ai_leads SET status='$status', updated_at=NOW() WHERE id=$id");
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── AUTOMATION LOG ─────────────────────────────────────────
    if ($api === 'auto_log') {
        $q   = "SELECT * FROM automation_tasks ORDER BY id DESC LIMIT 50";
        $res = mysqli_query($conn, $q);
        $rows = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'id'           => (int)$r['id'],
                'target'       => cleanNum($r['target_wa']),
                'message'      => mb_substr($r['message'], 0, 100),
                'status'       => $r['status'],
                'requested_by' => $r['requested_by'] ?? '-',
                'time'         => !empty($r['created_at']) ? date('d/m H:i', strtotime($r['created_at'])) : '-',
                'ago'          => !empty($r['created_at']) ? timeAgo($r['created_at']) : '-',
            ];
        }
        echo json_encode($rows);
        exit;
    }

    // ── STATS ──────────────────────────────────────────────────
    if ($api === 'stats') {
        $rooms     = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(DISTINCT sender_wa) FROM chat_memories"))[0] ?? 0);
        $total     = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM chat_memories"))[0] ?? 0);
        $today     = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM chat_memories WHERE DATE(created_at)=CURDATE()"))[0] ?? 0);
        $pending   = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM automation_tasks WHERE status='pending'"))[0] ?? 0);
        $auto_done = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM automation_tasks WHERE status='sent'"))[0] ?? 0);
        $auto_all  = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM automation_tasks"))[0] ?? 0);
        $groups    = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(DISTINCT sender_wa) FROM chat_memories WHERE is_group=1"))[0] ?? 0);
        $leads_new = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM ai_leads WHERE status='new'"))[0] ?? 0);
        $kb_count  = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM group_knowledge WHERE is_active=1"))[0] ?? 0);
        echo json_encode(compact('rooms','total','today','pending','auto_done','auto_all','groups','leads_new','kb_count'));
        exit;
    }

    // ── SEARCH MESSAGES ────────────────────────────────────────
    if ($api === 'search_msgs') {
        $q    = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
        $room = mysqli_real_escape_string($conn, $_GET['room'] ?? '');
        if (!$q) { echo json_encode([]); exit; }
        $where = $room ? "AND sender_wa='$room'" : '';
        $res   = mysqli_query($conn, "SELECT * FROM chat_memories
                                      WHERE message LIKE '%$q%' $where
                                      ORDER BY id DESC LIMIT 30");
        $rows  = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'id'      => (int)$r['id'],
                'role'    => $r['role'],
                'message' => mb_substr($r['message'], 0, 150),
                'room'    => $r['sender_wa'],
                'time'    => !empty($r['created_at']) ? date('d/m H:i', strtotime($r['created_at'])) : '-',
            ];
        }
        echo json_encode($rows);
        exit;
    }

    // ── DELETE ROOM HISTORY ────────────────────────────────────
    if ($api === 'clear_room' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $room = mysqli_real_escape_string($conn, $body['room'] ?? '');
        if ($room) {
            mysqli_query($conn, "DELETE FROM chat_memories WHERE sender_wa='$room'");
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── SAVE GROUP INFO ────────────────────────────────────────
    if ($api === 'save_group_info' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body        = json_decode(file_get_contents('php://input'), true);
        $groupId     = mysqli_real_escape_string($conn, $body['group_id']    ?? '');
        $groupName   = mysqli_real_escape_string($conn, $body['group_name']  ?? '');
        $memberCount = (int)($body['member_count'] ?? 0);
        if ($groupId) {
            mysqli_query($conn, "INSERT INTO wa_groups (group_id, group_name, member_count, updated_at)
                VALUES ('$groupId', '$groupName', $memberCount, NOW())
                ON DUPLICATE KEY UPDATE group_name='$groupName', member_count=$memberCount, updated_at=NOW()");
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['err' => 'unknown api']); exit;
}

// ══ PHP: LOAD INITIAL DATA ──────────────────────────────────────
$selected = trim($_GET['chat'] ?? '');
$safe_sel = $selected ? mysqli_real_escape_string($conn, $selected) : '';
$chatType = $selected ? getChatType($selected) : '';

$room_info = null;
if ($safe_sel) {
    $ri = @mysqli_fetch_assoc(@mysqli_query($conn,
        "SELECT contact_name, is_group, group_name
         FROM chat_memories WHERE sender_wa='$safe_sel' ORDER BY id DESC LIMIT 1"));
    $ctrl = @mysqli_fetch_assoc(@mysqli_query($conn,
        "SELECT bot_enabled, ai_mode FROM chat_controls WHERE chat_id='$safe_sel' LIMIT 1"));
    $cnt  = @mysqli_fetch_assoc(@mysqli_query($conn,
        "SELECT COUNT(*) AS t FROM chat_memories WHERE sender_wa='$safe_sel'"));
    $gi   = $chatType === 'group' ? getGroupInfo($conn, $selected) : [];
    $ctx  = @mysqli_fetch_assoc(@mysqli_query($conn,
        "SELECT last_topic, ai_mode, ai_personality, trigger_keywords
         FROM group_context WHERE group_id='$safe_sel' LIMIT 1"));
    $leadCount = (int)(mysqli_fetch_row(mysqli_query($conn,
        "SELECT COUNT(*) FROM ai_leads WHERE group_id='$safe_sel' AND status='new'"))[0] ?? 0);
    $kbCount = $chatType === 'group' ? getGroupKnowledgeCount($conn, $selected) : 0;

    if ($ri) {
        $aiMode = $ctrl['ai_mode'] ?? ($ctx['ai_mode'] ?? 'active');
        $room_info = [
            'display_name'     => displayName($ri, $selected),
            'is_group'         => (int)($ri['is_group'] ?? 0),
            'chat_type'        => $chatType,
            'group_name'       => $ri['group_name'] ?? ($gi['group_name'] ?? ''),
            'contact_name'     => $ri['contact_name'] ?? '',
            'bot_on'           => ($ctrl['bot_enabled'] ?? 1) == 1,
            'ai_mode'          => $aiMode,
            'member_count'     => (int)($gi['member_count'] ?? 0),
            'total'            => (int)($cnt['t'] ?? 0),
            'lead_count'       => $leadCount,
            'kb_count'         => $kbCount,
            'ai_personality'   => $ctx['ai_personality'] ?? '',
            'trigger_keywords' => $ctx['trigger_keywords'] ?? '',
            'last_topic'       => $ctx['last_topic'] ?? '',
        ];
    }
}

$aiModeColors = [
    'active'   => ['color' => '#a1ff5a', 'label' => 'AI Active',   'icon' => 'fa-bolt'],
    'off'      => ['color' => '#ff5a5a', 'label' => 'AI Off',      'icon' => 'fa-power-off'],
    'training' => ['color' => '#9b7cff', 'label' => 'Training Mode','icon' => 'fa-brain'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="theme-color" content="#050508">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ================================================================
   OBSIDIAN PRIME V8 — EXTENDED DESIGN SYSTEM
   Preserves all V7 styles + adds Group Intelligence layer
   ================================================================ */
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@400;500;600&display=swap');

/* ── DESIGN TOKENS ─────────────────────────────────────── */
:root {
    --g:         #a1ff5a;
    --c:         #4efdc4;
    --v:         #9b7cff;
    --r:         #ff5a5a;
    --a:         #ffb547;
    --b:         #3d8eff;
    --lead:      #ff6b9d;

    --ink:       #04040a;
    --s1:        rgba(255,255,255,0.025);
    --s2:        rgba(255,255,255,0.05);
    --s3:        rgba(255,255,255,0.08);
    --glass:     rgba(6,6,14,0.92);
    --border:    rgba(255,255,255,0.065);
    --border2:   rgba(255,255,255,0.12);
    --text:      #dde2ea;
    --dim:       #4a4f5c;
    --mid:       #7a8090;

    --grad:      linear-gradient(135deg, #a1ff5a, #4efdc4);
    --grad2:     linear-gradient(135deg, #9b7cff, #4efdc4);
    --grad3:     linear-gradient(135deg, #ff5a5a, #ffb547);
    --grad-lead: linear-gradient(135deg, #ff6b9d, #ffb547);

    --sh:        0 20px 60px rgba(0,0,0,0.7);
    --sh2:       0 8px 24px rgba(0,0,0,0.5);

    --rx:        28px;
    --rl:        20px;
    --rm:        14px;
    --rs:        8px;

    --sidebar-w: 310px;
    --rp-w:      290px;
    --top-h:     64px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body {
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    background: transparent;
    height: 100%;
    overflow: hidden;
    -webkit-font-smoothing: antialiased;
}
::-webkit-scrollbar       { width: 3px; height: 3px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.15); }
a { text-decoration: none; color: inherit; }
kbd { background: rgba(255,255,255,0.06); border: 1px solid var(--border2); border-radius: 4px; padding: 1px 5px; font-family: 'DM Mono', monospace; font-size: 0.6em; }

/* ── LAYOUT ────────────────────────────────────────────── */
#app {
    display: grid;
    grid-template-rows: var(--top-h) 1fr;
    grid-template-columns: var(--sidebar-w) 1fr var(--rp-w);
    height: 100vh;
    padding: 10px 16px 10px 104px;
    gap: 10px;
    animation: appIn 0.5s cubic-bezier(0.16,1,0.3,1) both;
}
@keyframes appIn { from{opacity:0;transform:translateY(10px);} to{opacity:1;transform:translateY(0);} }

#topbar { grid-column: 1 / -1; display: flex; align-items: center; gap: 12px; }

/* ── TOP BAR ───────────────────────────────────────────── */
.tb-brand { display: flex; flex-direction: column; line-height: 1; margin-right: 4px; }
.tb-clock { font-family: 'DM Mono', monospace; font-size: 1.6rem; font-weight: 600; background: var(--grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: -1px; }
.tb-label { font-size: 0.58rem; color: var(--dim); font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; }

.tb-stats { display: flex; gap: 8px; flex: 1; flex-wrap: nowrap; overflow-x: auto; }
.tb-pill { display: flex; align-items: center; gap: 6px; padding: 6px 13px; background: var(--s1); border: 1px solid var(--border); border-radius: 50px; font-size: 0.68rem; font-weight: 700; white-space: nowrap; cursor: default; transition: border-color 0.25s; }
.tb-pill:hover { border-color: var(--border2); }
.tb-pill i  { font-size: 0.6rem; }
.tb-pill .n { color: #fff; font-weight: 800; }
.tb-pill.has-alert .n { color: var(--lead); }

.tb-actions { display: flex; gap: 8px; }

.btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: 50px; border: 1px solid var(--border); background: var(--s1); color: var(--mid); font-family: 'DM Sans', sans-serif; font-size: 0.7rem; font-weight: 700; cursor: pointer; transition: all 0.22s; white-space: nowrap; text-decoration: none; }
.btn:hover         { background: var(--s2); border-color: var(--border2); color: var(--text); }
.btn-g             { background: var(--grad); color: #000; border: none; font-weight: 800; }
.btn-g:hover       { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(78,253,196,0.3); color: #000; }
.btn-v             { background: rgba(155,124,255,0.1); color: var(--v); border-color: rgba(155,124,255,0.25); }
.btn-v:hover       { background: rgba(155,124,255,0.18); border-color: var(--v); color: var(--v); }
.btn-r             { color: var(--r); }
.btn-r:hover       { background: rgba(255,90,90,0.08); border-color: var(--r); }
.btn-a             { color: var(--a); border-color: rgba(255,181,71,0.25); }
.btn-a:hover       { background: rgba(255,181,71,0.08); border-color: var(--a); color: var(--a); }
.btn-icon          { padding: 9px 12px; }
.btn:active        { transform: scale(0.97); }
.btn:disabled      { opacity: 0.4; pointer-events: none; }

.sentinel-badge { display: flex; align-items: center; gap: 6px; padding: 6px 14px; background: rgba(161,255,90,0.07); border: 1px solid rgba(161,255,90,0.2); border-radius: 50px; font-size: 0.62rem; font-weight: 800; color: var(--g); text-transform: uppercase; letter-spacing: 1px; }
.sentinel-badge .pulse { width: 6px; height: 6px; border-radius: 50%; background: var(--g); animation: pulseGlow 2s ease-in-out infinite; }
@keyframes pulseGlow { 0%,100% { box-shadow: 0 0 0 0 rgba(161,255,90,0.6); } 50% { box-shadow: 0 0 0 5px rgba(161,255,90,0); } }

/* ── PANEL BASE ────────────────────────────────────────── */
.panel { background: var(--glass); backdrop-filter: blur(40px) saturate(1.5); border: 1px solid var(--border); border-radius: var(--rx); overflow: hidden; display: flex; flex-direction: column; box-shadow: var(--sh); }

/* ── LEFT: SIDEBAR ─────────────────────────────────────── */
#sidebar { grid-column: 1; grid-row: 2; }

.sb-head { padding: 16px 16px 12px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.sb-head-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.sb-title { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--dim); }
.sb-count { font-size: 0.6rem; font-weight: 800; color: var(--g); background: rgba(161,255,90,0.08); padding: 2px 8px; border-radius: 50px; }

.sb-search { position: relative; }
.sb-search i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--dim); font-size: 0.7rem; pointer-events: none; }
.sb-search input { width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: var(--rm); padding: 9px 12px 9px 30px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 0.78rem; outline: none; transition: border-color 0.22s; }
.sb-search input:focus { border-color: var(--g); }

.sb-tabs { display: flex; gap: 6px; padding: 10px 16px 0; flex-shrink: 0; overflow-x: auto; }
.sb-tab { padding: 5px 12px; border-radius: 50px; background: transparent; border: 1px solid transparent; color: var(--dim); font-size: 0.62rem; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'DM Sans', sans-serif; white-space: nowrap; }
.sb-tab.active { background: rgba(161,255,90,0.08); border-color: rgba(161,255,90,0.25); color: var(--g); }
.sb-tab:hover:not(.active) { color: var(--text); border-color: var(--border); }

.sb-list { flex: 1; overflow-y: auto; padding: 8px; }

/* CHAT TILE */
.tile { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: var(--rl); margin-bottom: 3px; cursor: pointer; border: 1px solid transparent; transition: all 0.22s; position: relative; text-decoration: none; }
.tile:hover  { background: var(--s1); }
.tile.active { background: rgba(161,255,90,0.055); border-color: rgba(161,255,90,0.15); }
.tile.lead-tile { border-color: rgba(255,107,157,0.15); }
.tile.lead-tile:hover { border-color: rgba(255,107,157,0.3); }

.tile-av { width: 44px; height: 44px; border-radius: 14px; background: var(--s2); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 0.82rem; font-weight: 800; flex-shrink: 0; position: relative; transition: border-color 0.22s; color: var(--g); font-family: 'DM Mono', monospace; }
.tile.active .tile-av { border-color: rgba(161,255,90,0.35); }
.tile-av.grp  { color: var(--c); }
.tile-av.bc   { color: var(--a); }

.tile-av-dot { position: absolute; bottom: -2px; right: -2px; width: 10px; height: 10px; border-radius: 50%; border: 2px solid var(--ink); background: var(--g); transition: background 0.3s; }
.tile-av-dot.off { background: var(--r); }
.tile-av-dot.training { background: var(--v); }

.tile-info { flex: 1; min-width: 0; }
.tile-name { font-size: 0.82rem; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
.tile-prev { font-size: 0.68rem; color: var(--dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 4px; }
.tile-prev .bot-icon { color: var(--g); font-size: 0.55rem; }
.tile-prev .usr-icon { color: var(--c); font-size: 0.55rem; }

.tile-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.tile-time  { font-size: 0.58rem; color: var(--dim); font-family: 'DM Mono', monospace; white-space: nowrap; }
.tile-badge { background: var(--g); color: #000; font-size: 0.52rem; font-weight: 800; padding: 2px 6px; border-radius: 50px; min-width: 18px; text-align: center; }
.tile-badge.lead { background: var(--lead); color: #000; }

.tile-tags { display: flex; gap: 4px; margin-top: 2px; }
.tile-type { font-size: 0.5rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 1px 6px; border-radius: 4px; }
.tile-type.grp  { background: rgba(78,253,196,0.1);  color: var(--c); }
.tile-type.prv  { background: rgba(161,255,90,0.08); color: var(--g); }
.tile-type.bc   { background: rgba(255,181,71,0.1);  color: var(--a); }
.tile-type.lead { background: rgba(255,107,157,0.12); color: var(--lead); }

.sb-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; gap: 10px; color: var(--dim); text-align: center; }
.sb-empty i { font-size: 2.5rem; opacity: 0.3; }
.sb-empty p { font-size: 0.72rem; opacity: 0.4; }

/* ── CENTER: CHAT ──────────────────────────────────────── */
#chat { grid-column: 2; grid-row: 2; display: flex; flex-direction: column; }

.chat-head { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; flex-shrink: 0; background: rgba(255,255,255,0.01); min-height: 68px; flex-wrap: wrap; }

.chat-head-av { width: 46px; height: 46px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; font-family: 'DM Mono', monospace; flex-shrink: 0; background: rgba(161,255,90,0.06); border: 1px solid rgba(161,255,90,0.18); color: var(--g); }
.chat-head-av.grp { background: rgba(78,253,196,0.06); border-color: rgba(78,253,196,0.18); color: var(--c); }
.chat-head-av.bc  { background: rgba(255,181,71,0.06); border-color: rgba(255,181,71,0.18); color: var(--a); }

.chat-head-info { flex: 1; min-width: 0; }
.chat-head-name { font-size: 1rem; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-head-sub  { display: flex; align-items: center; gap: 8px; margin-top: 2px; font-size: 0.62rem; font-family: 'DM Mono', monospace; color: var(--dim); flex-wrap: wrap; }
.chat-head-sub .sep    { opacity: 0.3; }
.chat-head-sub .online { color: var(--g); }

/* AI MODE BADGE */
.ai-mode-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 50px; font-size: 0.58rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; cursor: pointer; transition: all 0.22s; border: 1px solid transparent; }
.ai-mode-badge.active   { background: rgba(161,255,90,0.1);  color: var(--g); border-color: rgba(161,255,90,0.25); }
.ai-mode-badge.off      { background: rgba(255,90,90,0.1);   color: var(--r); border-color: rgba(255,90,90,0.25); }
.ai-mode-badge.training { background: rgba(155,124,255,0.1); color: var(--v); border-color: rgba(155,124,255,0.25); }
.ai-mode-badge:hover { transform: scale(1.03); }

/* LEAD BADGE in header */
.lead-header-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 50px; background: rgba(255,107,157,0.1); border: 1px solid rgba(255,107,157,0.3); color: var(--lead); font-size: 0.58rem; font-weight: 800; animation: leadPulse 2.5s ease-in-out infinite; }
@keyframes leadPulse { 0%,100%{box-shadow:0 0 0 0 rgba(255,107,157,0.3);} 50%{box-shadow:0 0 0 4px rgba(255,107,157,0);} }

.bot-toggle-wrap { display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,0.25); border: 1px solid var(--border); border-radius: 50px; padding: 7px 14px; flex-shrink: 0; }
.toggle-label { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--dim); }

.sw { position: relative; display: inline-block; width: 46px; height: 24px; }
.sw input { opacity: 0; width: 0; height: 0; }
.sw-track { position: absolute; inset: 0; background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 50px; cursor: pointer; transition: all 0.3s; }
.sw-track::before { content: ''; position: absolute; width: 16px; height: 16px; left: 3px; top: 3px; background: var(--dim); border-radius: 50%; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.sw input:checked + .sw-track { background: rgba(161,255,90,0.12); border-color: rgba(161,255,90,0.4); }
.sw input:checked + .sw-track::before { transform: translateX(22px); background: var(--g); box-shadow: 0 0 10px rgba(161,255,90,0.5); }

.chat-head-actions { display: flex; gap: 6px; flex-shrink: 0; }

.chat-search-bar { padding: 8px 16px; border-bottom: 1px solid var(--border); display: none; background: rgba(0,0,0,0.2); flex-shrink: 0; }
.chat-search-bar.open { display: flex; gap: 8px; align-items: center; }
.chat-search-bar input { flex: 1; background: transparent; border: none; outline: none; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 0.82rem; }

.chat-body { flex: 1; overflow-y: auto; padding: 16px 20px; display: flex; flex-direction: column; gap: 3px; scroll-behavior: smooth; }

.date-div { display: flex; align-items: center; gap: 10px; margin: 10px 0 6px; color: var(--dim); font-size: 0.58rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; font-family: 'DM Mono', monospace; }
.date-div::before, .date-div::after { content: ''; flex: 1; height: 1px; background: var(--border); }

.bubble { max-width: 70%; padding: 10px 14px; border-radius: 18px; font-size: 0.83rem; line-height: 1.65; position: relative; word-break: break-word; animation: bblIn 0.28s cubic-bezier(0.16,1,0.3,1) both; }
@keyframes bblIn { from { opacity:0; transform: scale(0.92) translateY(8px); } to { opacity:1; transform: scale(1) translateY(0); } }
.bubble + .bubble { margin-top: 2px; }
.bubble.user { align-self: flex-start; background: var(--s2); border: 1px solid var(--border); border-bottom-left-radius: 4px; color: #ccc; }
.bubble.assistant { align-self: flex-end; background: linear-gradient(145deg, rgba(161,255,90,0.13), rgba(78,253,196,0.05)); border: 1px solid rgba(161,255,90,0.2); border-bottom-right-radius: 4px; color: #e8ffe8; }
.bubble.admin { align-self: flex-end; background: linear-gradient(145deg, rgba(155,124,255,0.15), rgba(78,253,196,0.05)); border: 1px solid rgba(155,124,255,0.22); border-bottom-right-radius: 4px; color: #e8e4ff; }

.bbl-head { font-size: 0.58rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; display: block; }
.bubble.user .bbl-head      { color: var(--c); }
.bubble.assistant .bbl-head { color: var(--g); }
.bubble.admin .bbl-head     { color: var(--v); }

.bbl-text { white-space: pre-wrap; }
.bbl-foot { display: flex; align-items: center; justify-content: flex-end; gap: 5px; margin-top: 5px; }
.bbl-time { font-size: 0.56rem; font-family: 'DM Mono', monospace; font-weight: 500; color: var(--dim); }
.bubble.assistant .bbl-time { color: rgba(161,255,90,0.45); }
.bubble.admin .bbl-time     { color: rgba(155,124,255,0.5); }
.bbl-tick { font-size: 0.6rem; }

.chat-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; opacity: 0.05; user-select: none; pointer-events: none; }
.chat-empty i   { font-size: 6rem; }
.chat-empty h1  { font-size: 2.5rem; font-weight: 800; letter-spacing: -2px; font-family: 'DM Mono', monospace; }
.chat-empty p   { font-size: 0.8rem; }

.loading-wrap { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 40px; color: var(--dim); font-size: 0.78rem; }
.loading-wrap i { animation: spin 0.9s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.load-more-btn { align-self: center; margin: 8px 0; padding: 7px 20px; background: var(--s1); border: 1px solid var(--border); border-radius: 50px; color: var(--mid); font-size: 0.7rem; font-weight: 700; cursor: pointer; transition: all 0.22s; font-family: 'DM Sans', sans-serif; }
.load-more-btn:hover { background: var(--s2); border-color: var(--border2); color: var(--text); }

.typing-indicator { align-self: flex-start; background: var(--s2); border: 1px solid var(--border); border-radius: 18px; border-bottom-left-radius: 4px; padding: 12px 16px; display: none; }
.typing-indicator.show { display: flex; gap: 4px; align-items: center; }
.typing-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--g); animation: typingBounce 1.2s ease-in-out infinite; }
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingBounce { 0%,60%,100% { transform: translateY(0); } 30% { transform: translateY(-6px); } }

.chat-input-area { padding: 12px 16px; border-top: 1px solid var(--border); background: rgba(0,0,0,0.15); flex-shrink: 0; }
.chat-input-row { display: flex; gap: 8px; align-items: flex-end; }
.chat-input-box { flex: 1; background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: 18px; padding: 10px 14px; display: flex; align-items: flex-end; gap: 8px; transition: border-color 0.22s; }
.chat-input-box:focus-within { border-color: rgba(161,255,90,0.3); background: rgba(255,255,255,0.05); }
.chat-textarea { flex: 1; background: transparent; border: none; outline: none; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 0.82rem; resize: none; max-height: 110px; min-height: 20px; line-height: 1.5; }
.chat-textarea::placeholder { color: var(--dim); }
.chat-send { width: 40px; height: 40px; border-radius: 13px; background: var(--grad); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #000; font-size: 0.85rem; transition: all 0.22s; flex-shrink: 0; }
.chat-send:hover  { transform: scale(1.06); box-shadow: 0 6px 16px rgba(78,253,196,0.3); }
.chat-send:active { transform: scale(0.96); }
.chat-send:disabled { opacity: 0.3; pointer-events: none; }
.chat-input-hint { font-size: 0.58rem; color: var(--dim); text-align: center; margin-top: 6px; }

/* ── RIGHT PANEL (V8 EXTENDED) ─────────────────────────── */
#rpanel { grid-column: 3; grid-row: 2; display: flex; flex-direction: column; gap: 10px; overflow-y: auto; padding-bottom: 4px; }

.rp-card { background: var(--glass); backdrop-filter: blur(40px); border: 1px solid var(--border); border-radius: var(--rx); overflow: hidden; flex-shrink: 0; box-shadow: var(--sh2); }
.rp-head { padding: 12px 16px 10px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.rp-title { font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--dim); display: flex; align-items: center; gap: 6px; }
.rp-body { padding: 4px 0; }
.rp-row { display: flex; align-items: flex-start; justify-content: space-between; padding: 7px 16px; border-bottom: 1px solid var(--border); gap: 10px; }
.rp-row:last-child { border-bottom: none; }
.rp-key { font-size: 0.62rem; color: var(--dim); font-weight: 600; flex-shrink: 0; margin-top: 1px; }
.rp-val { font-size: 0.7rem; color: var(--text); font-weight: 700; text-align: right; word-break: break-all; max-width: 160px; }
.rp-val.mono { font-family: 'DM Mono', monospace; font-size: 0.6rem; }
.rp-val.green { color: var(--g); }
.rp-val.red   { color: var(--r); }
.rp-val.cyan  { color: var(--c); }
.rp-val.violet{ color: var(--v); }
.rp-val.amber { color: var(--a); }

.rp-profile { padding: 16px 16px 12px; display: flex; flex-direction: column; align-items: center; gap: 8px; border-bottom: 1px solid var(--border); }
.rp-av { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-family: 'DM Mono', monospace; font-weight: 700; font-size: 1rem; background: rgba(161,255,90,0.06); border: 1px solid rgba(161,255,90,0.18); color: var(--g); }
.rp-av.grp { background: rgba(78,253,196,0.06); border-color: rgba(78,253,196,0.18); color: var(--c); }
.rp-av.bc  { background: rgba(255,181,71,0.06);  border-color: rgba(255,181,71,0.18);  color: var(--a); }
.rp-pname  { font-weight: 800; font-size: 0.88rem; text-align: center; color: #fff; }
.rp-ptype  { font-size: 0.58rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 3px 10px; border-radius: 50px; }
.rp-ptype.grp { background: rgba(78,253,196,0.08);  color: var(--c); }
.rp-ptype.prv { background: rgba(161,255,90,0.08);  color: var(--g); }
.rp-ptype.bc  { background: rgba(255,181,71,0.08);  color: var(--a); }

.rp-stats-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0; border-bottom: 1px solid var(--border); }
.rp-stat-item { padding: 10px 6px; text-align: center; border-right: 1px solid var(--border); }
.rp-stat-item:last-child { border-right: none; }
.rp-stat-n { font-size: 1rem; font-weight: 800; color: #fff; line-height: 1; }
.rp-stat-l { font-size: 0.52rem; color: var(--dim); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }

/* ── GROUP INFO PANEL ─────────────────────────────────── */
.group-info-banner {
    margin: 10px;
    padding: 12px 14px;
    background: rgba(78,253,196,0.04);
    border: 1px solid rgba(78,253,196,0.12);
    border-radius: var(--rm);
}
.gib-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.gib-label { font-size: 0.6rem; color: var(--dim); font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; }
.gib-val   { font-size: 0.68rem; font-weight: 700; color: var(--c); font-family: 'DM Mono', monospace; word-break: break-all; text-align: right; }
.gib-members { font-size: 0.72rem; font-weight: 800; color: var(--c); }

/* ── KNOWLEDGE PANEL ──────────────────────────────────── */
.kb-panel { padding: 0; }

.kb-cats { display: flex; gap: 5px; padding: 10px 12px; overflow-x: auto; border-bottom: 1px solid var(--border); }
.kb-cat { padding: 3px 10px; border-radius: 50px; background: transparent; border: 1px solid var(--border); color: var(--dim); font-size: 0.6rem; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all 0.2s; font-family: 'DM Sans', sans-serif; }
.kb-cat.active { background: rgba(155,124,255,0.1); border-color: rgba(155,124,255,0.3); color: var(--v); }
.kb-cat:hover:not(.active) { color: var(--text); border-color: var(--border2); }

.kb-list { padding: 6px; max-height: 200px; overflow-y: auto; }
.kb-item { padding: 8px 10px; border-radius: var(--rs); background: var(--s1); border: 1px solid var(--border); margin-bottom: 4px; transition: all 0.2s; cursor: default; }
.kb-item:hover { border-color: var(--border2); background: var(--s2); }
.kb-item-cat  { font-size: 0.52rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: var(--v); margin-bottom: 3px; }
.kb-item-text { font-size: 0.7rem; color: var(--text); line-height: 1.5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kb-item-meta { font-size: 0.56rem; color: var(--dim); margin-top: 4px; display: flex; justify-content: space-between; align-items: center; }
.kb-item-tags { font-size: 0.52rem; color: var(--c); }
.kb-item-actions { display: flex; gap: 4px; opacity: 0; transition: opacity 0.2s; }
.kb-item:hover .kb-item-actions { opacity: 1; }
.kb-act-btn { background: none; border: none; cursor: pointer; color: var(--dim); font-size: 0.65rem; padding: 2px 4px; transition: color 0.2s; }
.kb-act-btn:hover { color: var(--text); }
.kb-act-btn.del:hover { color: var(--r); }
.kb-act-btn.edit:hover { color: var(--v); }

.kb-empty { text-align: center; padding: 20px; color: var(--dim); font-size: 0.7rem; opacity: 0.5; }

/* ── AI SETTINGS PANEL ───────────────────────────────── */
.ai-settings-body { padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; }
.ai-mode-options { display: flex; gap: 5px; }
.ai-mode-opt { flex: 1; padding: 6px 8px; border-radius: var(--rs); border: 1px solid var(--border); background: transparent; color: var(--dim); font-size: 0.6rem; font-weight: 700; cursor: pointer; text-align: center; transition: all 0.2s; font-family: 'DM Sans', sans-serif; }
.ai-mode-opt.active-opt   { background: rgba(161,255,90,0.1);  border-color: rgba(161,255,90,0.3);  color: var(--g); }
.ai-mode-opt.off-opt      { background: rgba(255,90,90,0.1);   border-color: rgba(255,90,90,0.3);   color: var(--r); }
.ai-mode-opt.training-opt { background: rgba(155,124,255,0.1); border-color: rgba(155,124,255,0.3); color: var(--v); }
.ai-mode-opt:hover:not([disabled]) { transform: scale(1.02); }
.ai-settings-field { display: flex; flex-direction: column; gap: 4px; }
.ai-settings-label { font-size: 0.6rem; color: var(--dim); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
.ai-settings-input { background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: var(--rs); padding: 7px 10px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 0.75rem; outline: none; resize: none; width: 100%; transition: border-color 0.2s; }
.ai-settings-input:focus { border-color: var(--v); }

/* ── LEADS PANEL ─────────────────────────────────────── */
.lead-list { padding: 6px; max-height: 180px; overflow-y: auto; }
.lead-item { padding: 8px 10px; border-radius: var(--rs); background: rgba(255,107,157,0.04); border: 1px solid rgba(255,107,157,0.12); margin-bottom: 4px; }
.lead-top  { display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px; }
.lead-name { font-size: 0.7rem; font-weight: 700; color: var(--lead); }
.lead-score{ font-size: 0.6rem; color: var(--a); font-family: 'DM Mono', monospace; }
.lead-msg  { font-size: 0.66rem; color: var(--mid); margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lead-bot  { display: flex; justify-content: space-between; align-items: center; }
.lead-status-sel { background: transparent; border: 1px solid var(--border); border-radius: 4px; color: var(--dim); font-size: 0.6rem; padding: 2px 6px; cursor: pointer; font-family: 'DM Sans', sans-serif; }
.lead-status-sel:focus { outline: none; border-color: var(--lead); }
.lead-time { font-size: 0.56rem; color: var(--dim); font-family: 'DM Mono', monospace; }

/* Automation log */
.auto-list { padding: 6px; flex: 1; overflow-y: auto; }
.auto-item { padding: 8px 10px; border-radius: var(--rm); margin-bottom: 4px; background: var(--s1); border: 1px solid var(--border); transition: border-color 0.22s; }
.auto-item:hover { border-color: var(--border2); }
.auto-top  { display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px; }
.auto-num  { font-family: 'DM Mono', monospace; font-size: 0.68rem; font-weight: 600; color: var(--g); }
.auto-badge { font-size: 0.52rem; font-weight: 800; text-transform: uppercase; padding: 2px 7px; border-radius: 50px; letter-spacing: 0.5px; }
.auto-badge.pending    { background: rgba(255,181,71,0.1);  color: var(--a); }
.auto-badge.processing { background: rgba(155,124,255,0.1); color: var(--v); }
.auto-badge.sent       { background: rgba(161,255,90,0.1);  color: var(--g); }
.auto-badge.error      { background: rgba(255,90,90,0.1);   color: var(--r); }
.auto-msg  { font-size: 0.68rem; color: var(--mid); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px; }
.auto-meta { font-size: 0.58rem; color: var(--dim); font-family: 'DM Mono', monospace; display: flex; gap: 6px; }

/* ── SUMMARY PANEL ───────────────────────────────────── */
.summary-result { margin: 8px 12px; padding: 10px 12px; background: rgba(78,253,196,0.04); border: 1px solid rgba(78,253,196,0.15); border-radius: var(--rm); font-size: 0.72rem; color: var(--text); line-height: 1.6; display: none; }
.summary-result.show { display: block; }
.summary-msgs  { margin-top: 8px; border-top: 1px solid var(--border); padding-top: 8px; max-height: 100px; overflow-y: auto; }
.summary-msg-line { font-size: 0.62rem; color: var(--dim); padding: 2px 0; font-family: 'DM Mono', monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── TOAST ─────────────────────────────────────────────── */
#toasts { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column-reverse; gap: 8px; pointer-events: none; }
.toast { display: flex; align-items: center; gap: 10px; padding: 11px 16px; background: rgba(8,8,16,0.97); backdrop-filter: blur(20px); border: 1px solid var(--border2); border-radius: 16px; box-shadow: 0 12px 32px rgba(0,0,0,0.5); min-width: 240px; max-width: 340px; animation: toastIn 0.35s cubic-bezier(0.34,1.56,0.64,1) both; pointer-events: all; position: relative; overflow: hidden; }
@keyframes toastIn  { from{opacity:0;transform:translateX(30px)scale(0.9);} to{opacity:1;transform:translateX(0)scale(1);} }
@keyframes toastOut { to{opacity:0;transform:translateX(30px)scale(0.9);} }
.toast.out { animation: toastOut 0.25s ease forwards; }
.toast::after { content:''; position:absolute; bottom:0; left:0; height:2px; width:100%; animation: tbar 3s linear forwards; }
.toast.s::after { background: var(--g); }
.toast.e::after { background: var(--r); }
.toast.i::after { background: var(--v); }
.toast.w::after { background: var(--a); }
@keyframes tbar { from{width:100%;} to{width:0;} }
.toast i   { font-size: 0.9rem; flex-shrink:0; }
.toast.s i { color: var(--g); }
.toast.e i { color: var(--r); }
.toast.i i { color: var(--v); }
.toast.w i { color: var(--a); }
.toast-b   { flex:1; }
.toast-ttl { font-weight:800; font-size:0.76rem; }
.toast-msg { font-size:0.66rem; color:var(--dim); margin-top:1px; }

/* ── MODALS ────────────────────────────────────────────── */
#modal-overlay { position: fixed; inset:0; background: rgba(0,0,0,0.7); backdrop-filter: blur(10px); z-index: 9000; display: flex; align-items: center; justify-content: center; opacity:0; pointer-events:none; transition: opacity 0.25s; }
#modal-overlay.open { opacity:1; pointer-events:all; }
.modal { background: rgba(10,10,18,0.98); border: 1px solid var(--border2); border-radius: var(--rx); padding: 32px; max-width: 400px; width: 90%; box-shadow: 0 32px 80px rgba(0,0,0,0.7); transform: scale(0.92); transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1); text-align: center; }
#modal-overlay.open .modal { transform: scale(1); }
.modal-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items:center; justify-content:center; font-size: 1.5rem; margin: 0 auto 16px; }
.modal-icon.danger  { background: rgba(255,90,90,0.1);   color: var(--r); }
.modal-icon.info    { background: rgba(161,255,90,0.1);  color: var(--g); }
.modal-icon.violet  { background: rgba(155,124,255,0.1); color: var(--v); }
.modal h3 { font-weight:800; margin-bottom:8px; }
.modal p  { font-size:0.78rem; color:var(--mid); margin-bottom:22px; line-height:1.6; }
.modal-btns { display:flex; gap:8px; }
.modal-btns .btn { flex:1; justify-content:center; border-radius:14px; }

/* Train AI Modal */
.train-form { text-align: left; }
.form-group { margin-bottom: 14px; }
.form-label { font-size: 0.62rem; font-weight: 800; color: var(--dim); text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 5px; }
.form-input, .form-select, .form-textarea {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border2);
    border-radius: var(--rs);
    padding: 9px 12px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.78rem;
    outline: none;
    transition: border-color 0.2s;
}
.form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--v); }
.form-textarea { resize: vertical; min-height: 80px; }
.form-select { cursor: pointer; }
.form-select option { background: #0a0a18; }

/* SEARCH RESULTS */
.search-results { position: absolute; top: 100%; left: 0; right: 0; background: rgba(8,8,16,0.98); border: 1px solid var(--border2); border-radius: var(--rm); overflow: hidden; box-shadow: var(--sh); z-index: 999; max-height: 300px; overflow-y: auto; display: none; }
.search-results.open { display: block; }
.sr-item { padding: 10px 14px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.2s; }
.sr-item:last-child { border-bottom: none; }
.sr-item:hover { background: var(--s2); }
.sr-item-role { font-size: 0.58rem; font-weight:800; text-transform:uppercase; margin-bottom:3px; color: var(--dim); }
.sr-item-text { font-size: 0.75rem; color: var(--text); }
.sr-item-meta { font-size: 0.6rem; color: var(--dim); font-family:'DM Mono',monospace; margin-top:3px; }

/* QR */
.qr-alert { position: fixed; bottom: 20px; right: 20px; background: rgba(5,5,10,0.97); border: 1px solid var(--g); border-radius: 24px; padding: 18px; width: 180px; text-align: center; box-shadow: 0 0 40px rgba(161,255,90,0.3); animation: qrPulse 2.5s ease-in-out infinite; z-index: 8000; }
@keyframes qrPulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.02);} }
.qr-alert h6 { color: var(--g); font-size: 0.6rem; font-weight: 900; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 8px; }

/* RESPONSIVE */
@media (max-width: 1200px) { :root { --rp-w: 0px; } #rpanel { display: none; } #app { grid-template-columns: var(--sidebar-w) 1fr; } }
@media (max-width: 900px) {
    body { overflow: auto; }
    #app { padding: 8px 10px 80px 10px; grid-template-columns: 1fr; grid-template-rows: auto auto 1fr; height: auto; min-height: 100vh; }
    #topbar { grid-column: 1; grid-row: 1; flex-wrap: wrap; }
    #sidebar { grid-column: 1; grid-row: 2; height: 55vw; min-height: 220px; max-height: 360px; }
    #chat    { grid-column: 1; grid-row: 3; height: 60vh; min-height: 400px; }
    .tb-stats { display: none; }
    .sentinel-badge span { display: none; }
    .chat-head-actions .btn span { display: none; }
}
@media (max-width: 500px) {
    :root { --sidebar-w: 100%; }
    #app { padding: 6px 8px 90px 8px; gap: 8px; }
    .tb-clock { font-size: 1.3rem; }
    .chat-head { padding: 10px 14px; }
    .chat-body { padding: 12px 12px; }
    .bubble { max-width: 85%; }
}
</style>

<!-- ═══ MARKUP ═══════════════════════════════════════════════════ -->
<div id="app">

    <!-- TOP BAR -->
    <div id="topbar">
        <div class="tb-brand">
            <div class="tb-clock" id="clock">--:--</div>
            <div class="tb-label">Nebula Command · HVM Digital V8</div>
        </div>
        <div class="tb-stats">
            <div class="tb-pill"><i class="fas fa-comments" style="color:var(--c)"></i><span class="n" id="s-rooms">-</span> Rooms</div>
            <div class="tb-pill"><i class="fas fa-envelope" style="color:var(--g)"></i><span class="n" id="s-msgs">-</span> Messages</div>
            <div class="tb-pill"><i class="fas fa-calendar-day" style="color:var(--a)"></i><span class="n" id="s-today">-</span> Today</div>
            <div class="tb-pill"><i class="fas fa-robot" style="color:var(--v)"></i><span class="n" id="s-auto">-</span> Automation</div>
            <div class="tb-pill"><i class="fas fa-users" style="color:var(--b)"></i><span class="n" id="s-groups">-</span> Groups</div>
            <div class="tb-pill has-alert" id="pill-leads" style="display:none;"><i class="fas fa-fire" style="color:var(--lead)"></i><span class="n" id="s-leads">0</span> Leads</div>
            <div class="tb-pill"><i class="fas fa-brain" style="color:var(--v)"></i><span class="n" id="s-kb">-</span> KB</div>
        </div>
        <div class="tb-actions">
            <div class="sentinel-badge"><span class="pulse"></span><span>Sentinel Active</span></div>
            <button class="btn" onclick="doRestart()"><i class="fas fa-power-off"></i> <span>Reboot</span></button>
            <a href="?page=training" class="btn btn-g"><i class="fas fa-brain"></i> <span>AI Trainer</span></a>
        </div>
    </div>

    <!-- SIDEBAR -->
    <div class="panel" id="sidebar">
        <div class="sb-head">
            <div class="sb-head-top">
                <span class="sb-title">Conversations</span>
                <span class="sb-count" id="sb-count">-</span>
            </div>
            <div class="sb-search" style="position:relative;">
                <i class="fas fa-search"></i>
                <input type="text" id="sbSearch" placeholder="Cari nama, nomor, grup..." autocomplete="off" oninput="onSbSearch(this.value)">
                <div class="search-results" id="msgSearchResults"></div>
            </div>
        </div>
        <div class="sb-tabs">
            <button class="sb-tab active" data-filter="all"     onclick="setSbFilter('all',this)">Semua</button>
            <button class="sb-tab"        data-filter="group"   onclick="setSbFilter('group',this)">Grup</button>
            <button class="sb-tab"        data-filter="private" onclick="setSbFilter('private',this)">Pribadi</button>
            <button class="sb-tab"        data-filter="bot_off" onclick="setSbFilter('bot_off',this)">Bot Off</button>
            <button class="sb-tab"        data-filter="leads"   onclick="setSbFilter('leads',this)">🔥 Leads</button>
        </div>
        <div class="sb-list" id="sbList">
            <div class="loading-wrap"><i class="fas fa-spinner"></i> Memuat...</div>
        </div>
    </div>

    <!-- CHAT WINDOW -->
    <div class="panel" id="chat">
        <div class="chat-head" id="chatHead">
            <?php if ($room_info): ?>
            <?php
                $ri    = $room_info;
                $avCls = $ri['chat_type'] === 'group' ? 'grp' : ($ri['chat_type'] === 'broadcast' ? 'bc' : '');
                $am    = $ri['ai_mode'];
                $amC   = $aiModeColors[$am] ?? $aiModeColors['active'];
            ?>
            <div class="chat-head-av <?= $avCls ?>" id="chatAv"><?= mb_substr($ri['display_name'],0,2) ?></div>
            <div class="chat-head-info">
                <div class="chat-head-name" id="chatName"><?= htmlspecialchars($ri['display_name']) ?></div>
                <div class="chat-head-sub" id="chatSub">
                    <span class="online" id="chatDot">●</span>
                    <span id="chatSubTxt" style="font-size:0.58rem;"><?= htmlspecialchars($selected) ?></span>
                    <span class="sep">·</span>
                    <span><?= $ri['chat_type'] === 'group' ? 'Grup' : ($ri['chat_type'] === 'broadcast' ? 'Broadcast' : 'Pribadi') ?></span>
                    <?php if ($ri['chat_type'] === 'group' && $ri['member_count']): ?>
                    <span class="sep">·</span><span><?= $ri['member_count'] ?> anggota</span>
                    <?php endif; ?>
                    <span class="sep">·</span><span><?= $ri['total'] ?> pesan</span>
                </div>
            </div>

            <span class="ai-mode-badge <?= $am ?>" id="aiModeBadge" onclick="cycleAiMode()" title="Klik untuk ubah mode AI">
                <i class="fas <?= $amC['icon'] ?>"></i> <?= $amC['label'] ?>
            </span>

            <?php if ($ri['lead_count'] > 0): ?>
            <span class="lead-header-badge" id="leadHeaderBadge">
                <i class="fas fa-fire"></i> <?= $ri['lead_count'] ?> Lead
            </span>
            <?php endif; ?>

            <div class="bot-toggle-wrap">
                <span class="toggle-label">Bot</span>
                <label class="sw">
                    <input type="checkbox" id="botToggle" <?= $ri['bot_on'] ? 'checked' : '' ?> onchange="toggleBot(this.checked)">
                    <span class="sw-track"></span>
                </label>
            </div>

            <div class="chat-head-actions">
                <?php if ($ri['chat_type'] === 'group'): ?>
                <button class="btn btn-v btn-icon" title="Train AI" onclick="openTrainModal()"><i class="fas fa-brain"></i></button>
                <button class="btn btn-a btn-icon" title="Generate Summary" onclick="doGenerateSummary()"><i class="fas fa-file-lines"></i></button>
                <?php endif; ?>
                <button class="btn btn-icon" title="Cari pesan" onclick="toggleChatSearch()"><i class="fas fa-search"></i></button>
                <button class="btn btn-icon btn-r" title="Hapus history" onclick="openClearModal()"><i class="fas fa-trash"></i></button>
            </div>
            <?php else: ?>
            <div style="color:var(--dim); font-size:0.78rem; width:100%; text-align:center; padding: 6px 0;">
                <i class="fas fa-arrow-left" style="margin-right:6px; opacity:0.5;"></i>
                Pilih percakapan dari daftar kiri
            </div>
            <?php endif; ?>
        </div>

        <!-- In-chat search -->
        <div class="chat-search-bar" id="chatSearchBar">
            <i class="fas fa-search" style="color:var(--dim); font-size:0.8rem;"></i>
            <input type="text" id="chatSearchInput" placeholder="Cari pesan di sini..." oninput="onChatSearch(this.value)">
            <button class="btn btn-icon" onclick="closeChatSearch()" style="padding:5px 8px;"><i class="fas fa-times"></i></button>
        </div>

        <!-- Body -->
        <div class="chat-body" id="chatBody">
            <?php if ($selected): ?>
            <div class="loading-wrap"><i class="fas fa-spinner"></i> Memuat pesan...</div>
            <?php else: ?>
            <div class="chat-empty">
                <i class="fas fa-satellite-dish"></i>
                <h1>NEBULA OS</h1>
                <p>Command Center V8 Active</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Input -->
        <?php if ($selected): ?>
        <div class="chat-input-area">
            <div class="chat-input-row">
                <div class="chat-input-box">
                    <textarea class="chat-textarea" id="msgInput" placeholder="Tulis balasan admin..." rows="1"
                              onkeydown="onMsgKey(event)" oninput="autoGrow(this)"></textarea>
                </div>
                <button class="chat-send" id="sendBtn" onclick="sendReply()" title="Kirim (Enter)">
                    <i class="fas fa-paper-plane" id="sendIcon"></i>
                </button>
            </div>
            <div class="chat-input-hint"><kbd>Enter</kbd> kirim &nbsp;·&nbsp; <kbd>Shift+Enter</kbd> baris baru</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT PANEL V8 -->
    <div id="rpanel">

        <!-- Profile Card -->
        <div class="rp-card" id="rpProfile">
            <?php if ($room_info): ?>
            <?php $ri = $room_info; $pt = $ri['chat_type']; ?>
            <div class="rp-profile">
                <div class="rp-av <?= $pt === 'group' ? 'grp' : ($pt === 'broadcast' ? 'bc' : '') ?>" id="rpAv">
                    <?= mb_substr($ri['display_name'],0,2) ?>
                </div>
                <div class="rp-pname" id="rpName"><?= htmlspecialchars($ri['display_name']) ?></div>
                <span class="rp-ptype <?= $pt === 'group' ? 'grp' : ($pt === 'broadcast' ? 'bc' : 'prv') ?>">
                    <?= $pt === 'group' ? 'Grup WhatsApp' : ($pt === 'broadcast' ? 'Broadcast' : 'Chat Pribadi') ?>
                </span>
            </div>
            <div class="rp-stats-row" id="rpStats">
                <div class="rp-stat-item"><div class="rp-stat-n" id="rps-total">-</div><div class="rp-stat-l">Total</div></div>
                <div class="rp-stat-item"><div class="rp-stat-n" id="rps-user">-</div><div class="rp-stat-l">User</div></div>
                <div class="rp-stat-item"><div class="rp-stat-n" id="rps-bot">-</div><div class="rp-stat-l">Bot</div></div>
            </div>

            <?php if ($pt === 'group'): ?>
            <!-- Group Info Banner -->
            <div class="group-info-banner" id="groupInfoBanner">
                <div class="gib-row" style="margin-bottom:6px;">
                    <span class="gib-label">Nama Grup</span>
                    <span class="gib-val" id="gib-name"><?= htmlspecialchars($ri['group_name'] ?: $ri['display_name']) ?></span>
                </div>
                <div class="gib-row" style="margin-bottom:6px;">
                    <span class="gib-label">Group ID</span>
                    <span class="gib-val" style="font-size:0.6rem;" id="gib-id"><?= htmlspecialchars($selected) ?></span>
                </div>
                <div class="gib-row">
                    <span class="gib-label">Member</span>
                    <span class="gib-members" id="gib-members"><?= $ri['member_count'] ?: '?' ?> anggota</span>
                </div>
                <?php if ($ri['last_topic']): ?>
                <div class="gib-row" style="margin-top:6px;">
                    <span class="gib-label">Topik</span>
                    <span class="gib-val" style="color:var(--a);"><?= htmlspecialchars($ri['last_topic']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="rp-body" id="rpInfoRows">
                <div class="rp-row"><span class="rp-key">Room ID</span><span class="rp-val mono"><?= htmlspecialchars($selected) ?></span></div>
                <div class="rp-row"><span class="rp-key">Loading...</span><span class="rp-val">-</span></div>
            </div>
            <?php else: ?>
            <div style="padding:30px;text-align:center;color:var(--dim);font-size:0.72rem;opacity:0.4;">
                <i class="fas fa-user-circle" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                Pilih room untuk info
            </div>
            <?php endif; ?>
        </div>

        <?php if ($room_info && $room_info['chat_type'] === 'group'): ?>

        <!-- AI Knowledge Panel -->
        <div class="rp-card">
            <div class="rp-head">
                <div class="rp-title"><i class="fas fa-book" style="color:var(--v)"></i> AI Group Knowledge</div>
                <div style="display:flex;gap:5px;">
                    <span style="font-size:0.6rem;color:var(--dim);" id="kbCountBadge"><?= $room_info['kb_count'] ?> items</span>
                    <button class="btn btn-icon btn-v" style="padding:3px 7px;font-size:0.65rem;" onclick="openTrainModal()" title="Tambah Knowledge">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="kb-cats">
                <button class="kb-cat active" data-cat="" onclick="filterKb('',this)">Semua</button>
                <button class="kb-cat" data-cat="Product Knowledge" onclick="filterKb('Product Knowledge',this)">Produk</button>
                <button class="kb-cat" data-cat="FAQ" onclick="filterKb('FAQ',this)">FAQ</button>
                <button class="kb-cat" data-cat="SOP" onclick="filterKb('SOP',this)">SOP</button>
                <button class="kb-cat" data-cat="Pricing" onclick="filterKb('Pricing',this)">Harga</button>
                <button class="kb-cat" data-cat="Promo" onclick="filterKb('Promo',this)">Promo</button>
            </div>
            <div class="kb-list" id="kbList">
                <div class="loading-wrap" style="padding:16px;"><i class="fas fa-spinner"></i></div>
            </div>
        </div>

        <!-- AI Settings Panel -->
        <div class="rp-card">
            <div class="rp-head">
                <div class="rp-title"><i class="fas fa-sliders" style="color:var(--a)"></i> AI Group Settings</div>
                <button class="btn btn-icon" style="padding:3px 7px;font-size:0.65rem;" onclick="saveGroupSettings()"><i class="fas fa-save"></i></button>
            </div>
            <div class="ai-settings-body">
                <div style="margin-bottom:6px;">
                    <div class="ai-settings-label">AI Mode</div>
                    <div class="ai-mode-options">
                        <button class="ai-mode-opt active-opt <?= $room_info['ai_mode']==='active'?'ring':'' ?>"
                                onclick="selectAiModeOpt('active',this)"
                                id="opt-active" style="<?= $room_info['ai_mode']==='active'?'outline:1px solid var(--g);':'' ?>">
                            <i class="fas fa-bolt"></i> Aktif
                        </button>
                        <button class="ai-mode-opt off-opt"
                                onclick="selectAiModeOpt('off',this)"
                                id="opt-off" style="<?= $room_info['ai_mode']==='off'?'outline:1px solid var(--r);':'' ?>">
                            <i class="fas fa-power-off"></i> Off
                        </button>
                        <button class="ai-mode-opt training-opt"
                                onclick="selectAiModeOpt('training',this)"
                                id="opt-training" style="<?= $room_info['ai_mode']==='training'?'outline:1px solid var(--v);':'' ?>">
                            <i class="fas fa-brain"></i> Train
                        </button>
                    </div>
                </div>
                <div class="ai-settings-field">
                    <label class="ai-settings-label">AI Personality</label>
                    <textarea class="ai-settings-input" id="aiPersonality" rows="2"
                              placeholder="Contoh: Ramah, profesional, fokus closing..."><?= htmlspecialchars($room_info['ai_personality']) ?></textarea>
                </div>
                <div class="ai-settings-field">
                    <label class="ai-settings-label">Trigger Keywords</label>
                    <input type="text" class="ai-settings-input" id="aiTriggerKw"
                           value="<?= htmlspecialchars($room_info['trigger_keywords']) ?>"
                           placeholder="nebula, bot, tanya, harga">
                </div>
            </div>
        </div>

        <!-- Lead Detection Panel -->
        <?php if ($room_info['lead_count'] > 0): ?>
        <div class="rp-card">
            <div class="rp-head">
                <div class="rp-title"><i class="fas fa-fire" style="color:var(--lead)"></i> Lead Detected</div>
                <span style="font-size:0.6rem;background:rgba(255,107,157,0.1);color:var(--lead);padding:2px 8px;border-radius:50px;font-weight:800;"><?= $room_info['lead_count'] ?> NEW</span>
            </div>
            <div class="lead-list" id="leadList">
                <div class="loading-wrap" style="padding:16px;"><i class="fas fa-spinner"></i></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary Panel -->
        <div class="rp-card">
            <div class="rp-head">
                <div class="rp-title"><i class="fas fa-file-lines" style="color:var(--c)"></i> Summary Generator</div>
                <button class="btn btn-icon" style="padding:3px 7px;font-size:0.65rem;color:var(--c);" onclick="doGenerateSummary()">
                    <i class="fas fa-wand-magic-sparkles"></i>
                </button>
            </div>
            <div style="padding:8px 12px;">
                <button class="btn btn-v" style="width:100%;justify-content:center;font-size:0.7rem;" onclick="doGenerateSummary()">
                    <i class="fas fa-sparkles"></i> Generate Summary
                </button>
            </div>
            <div class="summary-result" id="summaryResult"></div>
        </div>

        <?php endif; // end group panels ?>

        <!-- Automation Log -->
        <div class="rp-card" style="flex:1;min-height:180px;display:flex;flex-direction:column;">
            <div class="rp-head">
                <div class="rp-title"><i class="fas fa-robot" style="color:var(--v)"></i> Automation</div>
                <button class="btn btn-icon" style="padding:4px 8px;font-size:0.65rem;" onclick="loadAutoLog()"><i class="fas fa-sync"></i></button>
            </div>
            <div class="auto-list" id="autoList">
                <div class="loading-wrap" style="padding:20px;"><i class="fas fa-spinner"></i></div>
            </div>
        </div>

    </div><!-- /rpanel -->

</div><!-- /app -->

<!-- MODAL CONFIRM / CLEAR -->
<div id="modal-overlay" onclick="if(event.target===this)closeModal()">
    <div class="modal" id="modal">
        <div class="modal-icon danger" id="modal-icon"><i id="modal-ico" class="fas fa-trash"></i></div>
        <h3 id="modal-title">Hapus History?</h3>
        <p id="modal-msg">Semua riwayat percakapan di room ini akan dihapus permanen.</p>
        <div class="modal-btns">
            <button class="btn" onclick="closeModal()">Batal</button>
            <button class="btn btn-r" id="modal-confirm-btn" onclick="doClearRoom()">
                <i class="fas fa-trash"></i> Hapus
            </button>
        </div>
    </div>
</div>

<!-- TRAIN AI MODAL -->
<div id="train-modal-overlay" onclick="if(event.target===this)closeTrainModal()" style="position:fixed;inset:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(12px);z-index:9100;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity 0.25s;">
    <div style="background:rgba(10,10,20,0.99);border:1px solid rgba(155,124,255,0.25);border-radius:28px;padding:28px;max-width:460px;width:90%;box-shadow:0 32px 80px rgba(0,0,0,0.8);transform:scale(0.92);transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1);" id="trainModalBox">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
            <div style="width:44px;height:44px;border-radius:14px;background:rgba(155,124,255,0.1);border:1px solid rgba(155,124,255,0.25);display:flex;align-items:center;justify-content:center;color:var(--v);font-size:1.1rem;flex-shrink:0;">
                <i class="fas fa-brain"></i>
            </div>
            <div>
                <div style="font-weight:800;font-size:0.95rem;color:#fff;">Train AI Knowledge</div>
                <div style="font-size:0.65rem;color:var(--dim);font-family:'DM Mono',monospace;" id="trainRoomLabel"><?= htmlspecialchars($selected) ?></div>
            </div>
            <button class="btn btn-icon" onclick="closeTrainModal()" style="margin-left:auto;padding:6px 9px;"><i class="fas fa-times"></i></button>
        </div>
        <div class="train-form">
            <div class="form-group">
                <label class="form-label">Category</label>
                <select class="form-select" id="trainCategory">
                    <option value="Product Knowledge">Product Knowledge</option>
                    <option value="FAQ">FAQ</option>
                    <option value="SOP">SOP & Policy</option>
                    <option value="Pricing">Pricing & Package</option>
                    <option value="Promo">Promo & Promo</option>
                    <option value="Handling Objection">Handling Objection</option>
                    <option value="General">General</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Content <span style="color:var(--r)">*</span></label>
                <textarea class="form-textarea" id="trainContent" placeholder="Masukkan knowledge yang ingin diajarkan ke AI untuk grup ini..." rows="4"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Tags <span style="color:var(--dim)">(pisahkan koma)</span></label>
                <input type="text" class="form-input" id="trainTags" placeholder="produk, harga, promo, sop">
            </div>
            <div style="display:flex;gap:8px;margin-top:4px;">
                <button class="btn" onclick="closeTrainModal()" style="flex:1;justify-content:center;">Batal</button>
                <button class="btn btn-v" onclick="submitTraining()" style="flex:2;justify-content:center;" id="trainSubmitBtn">
                    <i class="fas fa-brain"></i> Simpan Knowledge
                </button>
            </div>
        </div>
        <!-- Edit mode hidden input -->
        <input type="hidden" id="trainEditId" value="">
    </div>
</div>

<!-- QR OVERLAY -->
<?php if (file_exists(__DIR__ . '/qrcode.png')): ?>
<div class="qr-alert">
    <h6><i class="fas fa-qrcode"></i> Scan Required</h6>
    <img src="qrcode.png?t=<?= time() ?>" style="width:100%;border-radius:12px;border:1px solid #1a1a1a;">
    <p style="font-size:0.5rem;color:var(--dim);margin-top:8px;line-height:1.5;">Bot disconnected. Scan to reconnect.</p>
</div>
<?php endif; ?>

<div id="toasts"></div>

<!-- ═══ JAVASCRIPT ENGINE V8 ════════════════════════════════════ -->
<script>
/* ================================================================
   NEBULA COMMAND ENGINE V8
   Group Intelligence | Knowledge Panel | Lead Detection | Summary
   AI Mode Control | Contact Resolver | Smart Detection
   ================================================================ */

const ROOM     = <?= json_encode($selected) ?>;
const CHATTYPE = <?= json_encode($chatType) ?>;
const API      = 'pages/command.php';

// ── State ──────────────────────────────────────────────────
let sbData         = [];
let sbFilter       = 'all';
let sbSearchTimer  = null;
let chatMsgs       = [];
let oldestId       = 0;
let syncTimer      = null;
let chatSearchOpen = false;
let currentAiMode  = <?= json_encode($room_info['ai_mode'] ?? 'active') ?>;
let currentKbCat   = '';
let kbData         = [];
let selectedAiMode = <?= json_encode($room_info['ai_mode'] ?? 'active') ?>;

// ── UTILS ──────────────────────────────────────────────────
const $ = id => document.getElementById(id);

function q(url) {
    return fetch(API + '?' + url).then(r => r.json()).catch(() => null);
}

function post(url, body) {
    return fetch(API + '?' + url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    }).then(r => r.json()).catch(() => ({ ok: false }));
}

function toast(type, title, msg, dur = 3200) {
    const icons = { s:'fas fa-check-circle', e:'fas fa-times-circle', i:'fas fa-info-circle', w:'fas fa-exclamation-triangle' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<i class="${icons[type]||icons.i}"></i>
        <div class="toast-b"><div class="toast-ttl">${title}</div><div class="toast-msg">${msg}</div></div>`;
    $('toasts').prepend(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 300); }, dur);
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

function initials(name) {
    const w = (name || '').trim().split(/\s+/).filter(Boolean);
    return w.length >= 2 ? (w[0][0]+w[1][0]).toUpperCase() : (name||'?').slice(0,2).toUpperCase();
}

function autoGrow(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 110) + 'px';
}

function formatNum(n) {
    return n >= 1000 ? (n/1000).toFixed(1)+'k' : n;
}

// ── CLOCK ──────────────────────────────────────────────────
function tickClock() {
    const n = new Date();
    const cl = $('clock');
    if (cl) cl.textContent = String(n.getHours()).padStart(2,'0') + ':' + String(n.getMinutes()).padStart(2,'0');
}
tickClock();
setInterval(tickClock, 1000);

// ── STATS ──────────────────────────────────────────────────
async function loadStats() {
    const d = await q('api=stats');
    if (!d) return;
    if ($('s-rooms'))  $('s-rooms').textContent  = formatNum(d.rooms);
    if ($('s-msgs'))   $('s-msgs').textContent   = formatNum(d.total);
    if ($('s-today'))  $('s-today').textContent  = formatNum(d.today);
    if ($('s-auto'))   $('s-auto').textContent   = formatNum(d.auto_all);
    if ($('s-groups')) $('s-groups').textContent = formatNum(d.groups);
    if ($('s-kb'))     $('s-kb').textContent     = formatNum(d.kb_count || 0);
    if (d.leads_new > 0) {
        const pillLeads = $('pill-leads');
        if (pillLeads) pillLeads.style.display = 'flex';
        if ($('s-leads')) $('s-leads').textContent = d.leads_new;
    }
}

// ── SIDEBAR ────────────────────────────────────────────────
function setSbFilter(f, el) {
    sbFilter = f;
    document.querySelectorAll('.sb-tab').forEach(t => t.classList.remove('active'));
    if (el) el.classList.add('active');
    renderSidebar();
}

function onSbSearch(val) {
    clearTimeout(sbSearchTimer);
    if (val.trim().length > 2) {
        sbSearchTimer = setTimeout(() => searchAllMessages(val.trim()), 400);
    } else {
        $('msgSearchResults').classList.remove('open');
        $('msgSearchResults').innerHTML = '';
    }
    renderSidebar(val);
}

async function searchAllMessages(q_str) {
    const data = await q(`api=search_msgs&q=${encodeURIComponent(q_str)}&room=${encodeURIComponent(ROOM||'')}`);
    if (!data || !data.length) { $('msgSearchResults').classList.remove('open'); return; }
    const el = $('msgSearchResults');
    el.innerHTML = data.slice(0,15).map(m => `
        <div class="sr-item" onclick="jumpToMsg(${m.id})">
            <div class="sr-item-role">${esc(m.role)} · ${esc(m.time)}</div>
            <div class="sr-item-text">${esc(m.message)}</div>
            <div class="sr-item-meta">${esc(m.room)}</div>
        </div>`).join('');
    el.classList.add('open');
}

function jumpToMsg(id) {
    $('msgSearchResults').classList.remove('open');
    const el = document.querySelector(`[data-id="${id}"]`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.outline = '1px solid rgba(161,255,90,0.5)';
        setTimeout(() => el.style.outline = '', 2000);
    }
}

function getChatTypeLabel(type) {
    if (type === 'group') return 'grp';
    if (type === 'broadcast') return 'bc';
    return 'prv';
}

async function loadSidebar(search = '') {
    const data = await q(`api=sidebar&q=${encodeURIComponent(search)}`);
    if (!data) return;
    sbData = data;
    renderSidebar(search);
}

function renderSidebar(search = '') {
    const el  = $('sbList');
    const sq  = search.toLowerCase().trim();
    let   rows = sbData;

    if (sbFilter === 'group')   rows = rows.filter(r => r.chat_type === 'group');
    if (sbFilter === 'private') rows = rows.filter(r => r.chat_type === 'private');
    if (sbFilter === 'bot_off') rows = rows.filter(r => !r.bot_on);
    if (sbFilter === 'leads')   rows = rows.filter(r => r.lead_count > 0);

    if (sq) {
        rows = rows.filter(r =>
            r.display_name.toLowerCase().includes(sq) ||
            r.sender_wa.toLowerCase().includes(sq) ||
            r.last_msg.toLowerCase().includes(sq)
        );
    }

    $('sb-count').textContent = rows.length + ' rooms';

    if (!rows.length) {
        el.innerHTML = `<div class="sb-empty"><i class="fas fa-inbox"></i><p>Tidak ada room ditemukan</p></div>`;
        return;
    }

    const aiModeMap = { active: '', off: 'off', training: 'training' };

    el.innerHTML = rows.map((c, i) => {
        const active   = ROOM === c.sender_wa ? 'active' : '';
        const chatType = c.chat_type;
        const avCls    = chatType === 'group' ? 'grp' : (chatType === 'broadcast' ? 'bc' : '');
        const typeCls  = getChatTypeLabel(chatType);
        const typeLabel = chatType === 'group' ? 'Grup' : (chatType === 'broadcast' ? 'BC' : 'Private');
        const ini      = initials(c.display_name);
        const prevIcon = c.role === 'assistant'
            ? '<i class="fas fa-robot bot-icon"></i>'
            : '<i class="fas fa-user usr-icon"></i>';
        const dotCls   = !c.bot_on ? 'off' : (c.ai_mode === 'training' ? 'training' : '');
        const memberInfo = chatType === 'group' && c.member_count > 0
            ? `<span style="font-size:0.56rem;color:var(--c);margin-left:2px;"><i class="fas fa-users"></i> ${c.member_count}</span>`
            : '';
        const leadBadge = c.lead_count > 0
            ? `<span class="tile-badge lead"><i class="fas fa-fire"></i> ${c.lead_count}</span>`
            : '';
        const isLead = c.lead_count > 0 ? 'lead-tile' : '';

        return `<a class="tile ${active} ${isLead}" href="?page=command&chat=${encodeURIComponent(c.sender_wa)}"
                   style="animation-delay:${Math.min(i*18,200)}ms">
            <div class="tile-av ${avCls}">
                ${ini}
                <span class="tile-av-dot ${dotCls}"></span>
            </div>
            <div class="tile-info">
                <div class="tile-name">${esc(c.display_name)} ${memberInfo}</div>
                <div class="tile-prev">${prevIcon} ${esc(c.last_msg)}</div>
            </div>
            <div class="tile-meta">
                <span class="tile-time">${esc(c.time_exact)}</span>
                <div class="tile-tags">
                    <span class="tile-type ${typeCls}">${typeLabel}</span>
                    ${leadBadge}
                </div>
            </div>
        </a>`;
    }).join('');
}

// ── MESSAGES ───────────────────────────────────────────────
function buildBubble(m) {
    const isBot = m.role === 'assistant';
    const cls   = isBot ? 'assistant' : 'user';
    const sender = isBot ? 'Nebula AI' : 'User';
    const tick  = isBot ? '✓✓' : '';
    return `<div class="bubble ${cls}" data-id="${m.id}" data-date="${m.date_raw}">
        <span class="bbl-head">${esc(sender)}</span>
        <div class="bbl-text">${esc(m.message)}</div>
        <div class="bbl-foot">
            <span class="bbl-time">${esc(m.time)}</span>
            ${tick ? `<span class="bbl-tick" style="color:var(--c);font-size:.6rem;">${tick}</span>` : ''}
        </div>
    </div>`;
}

function buildDateDiv(dateStr) {
    return `<div class="date-div">${esc(dateStr)}</div>`;
}

function renderMessages(msgs) {
    if (!msgs || !msgs.length) {
        $('chatBody').innerHTML = `<div class="chat-empty" style="opacity:0.07;">
            <i class="fas fa-comment-slash"></i><h1>EMPTY</h1>
            <p>Belum ada percakapan di room ini</p></div>`;
        return;
    }
    let html = '', lDate = '';
    msgs.forEach(m => {
        if (m.date_raw && m.date_raw !== lDate) {
            html += buildDateDiv(m.date_disp || m.date_raw);
            lDate = m.date_raw;
        }
        html += buildBubble(m);
    });
    $('chatBody').innerHTML = html;
    if (msgs.length >= 80) {
        const btn = document.createElement('button');
        btn.className = 'load-more-btn';
        btn.textContent = 'Muat pesan lebih lama';
        btn.onclick = loadMoreMessages;
        $('chatBody').prepend(btn);
    }
}

async function loadMessages(scrollToBottom = true) {
    if (!ROOM) return;
    const data = await q(`api=messages&chat_id=${encodeURIComponent(ROOM)}&limit=80`);
    if (!data) return;
    chatMsgs = data;
    if (data.length) oldestId = data[0].id;
    renderMessages(data);
    if (scrollToBottom) $('chatBody').scrollTop = $('chatBody').scrollHeight;
}

async function loadMoreMessages() {
    if (!ROOM || !oldestId) return;
    const btn = $('chatBody').querySelector('.load-more-btn');
    if (btn) { btn.textContent = 'Memuat...'; btn.disabled = true; }
    const data = await q(`api=messages&chat_id=${encodeURIComponent(ROOM)}&limit=60&before=${oldestId}`);
    if (!data || !data.length) { if (btn) btn.remove(); return; }
    if (data.length) oldestId = data[0].id;
    let html = '', lDate = '';
    data.forEach(m => {
        if (m.date_raw && m.date_raw !== lDate) { html += buildDateDiv(m.date_disp || m.date_raw); lDate = m.date_raw; }
        html += buildBubble(m);
    });
    if (btn) btn.remove();
    const newBtn = data.length >= 60 ? `<button class="load-more-btn" onclick="loadMoreMessages()">Muat lebih lama lagi</button>` : '';
    $('chatBody').insertAdjacentHTML('afterbegin', newBtn + html);
}

async function autoSync() {
    if (!ROOM) return;
    const data = await q(`api=messages&chat_id=${encodeURIComponent(ROOM)}&limit=80`);
    if (!data || !data.length) return;
    const latestId   = data[data.length - 1].id;
    const prevLatest = chatMsgs.length ? chatMsgs[chatMsgs.length - 1].id : 0;
    if (latestId !== prevLatest) {
        const body  = $('chatBody');
        const atBtm = (body.scrollHeight - body.scrollTop - body.clientHeight) < 120;
        chatMsgs = data;
        renderMessages(data);
        if (atBtm) body.scrollTop = body.scrollHeight;
    }
}

// ── SEND REPLY ─────────────────────────────────────────────
async function sendReply() {
    if (!ROOM) return;
    const input = $('msgInput');
    const msg   = (input?.value || '').trim();
    if (!msg) return;
    const btn  = $('sendBtn'), icon = $('sendIcon');
    btn.disabled = true;
    icon.className = 'fas fa-spinner fa-spin';
    const res = await post('api=send', { room: ROOM, message: msg });
    btn.disabled   = false;
    icon.className = 'fas fa-paper-plane';
    if (res && res.ok) {
        input.value = '';
        input.style.height = 'auto';
        toast('s', 'Terkirim', 'Pesan admin dikirim ke ' + ROOM);
        await loadMessages(true);
    } else {
        toast('e', 'Gagal', res?.err || 'Terjadi kesalahan saat kirim pesan');
    }
}

function onMsgKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
}

// ── BOT TOGGLE ─────────────────────────────────────────────
async function toggleBot(on) {
    const res = await post('api=toggle', { room: ROOM, status: on ? 1 : 0 });
    if (res && res.ok) {
        const dot = $('chatDot');
        if (dot) dot.style.color = on ? 'var(--g)' : 'var(--r)';
        toast('i', 'Bot ' + (on ? 'Aktif' : 'Dimatikan'), ROOM);
        await loadSidebar();
    } else {
        toast('e', 'Gagal', 'Toggle bot error');
        const tog = $('botToggle');
        if (tog) tog.checked = !on;
    }
}

// ── AI MODE ────────────────────────────────────────────────
const aiModeConfig = {
    active:   { color: 'var(--g)', label: 'AI Active',    icon: 'fa-bolt' },
    off:      { color: 'var(--r)', label: 'AI Off',       icon: 'fa-power-off' },
    training: { color: 'var(--v)', label: 'Training Mode', icon: 'fa-brain' },
};

function cycleAiMode() {
    const modes = ['active', 'off', 'training'];
    const idx   = modes.indexOf(currentAiMode);
    const next  = modes[(idx + 1) % modes.length];
    setAiMode(next);
}

async function setAiMode(mode) {
    const res = await post('api=set_ai_mode', { room: ROOM, mode });
    if (res && res.ok) {
        currentAiMode = mode;
        selectedAiMode = mode;
        const cfg = aiModeConfig[mode];
        const badge = $('aiModeBadge');
        if (badge) {
            badge.className = `ai-mode-badge ${mode}`;
            badge.innerHTML = `<i class="fas ${cfg.icon}"></i> ${cfg.label}`;
        }
        // Sync settings panel
        ['active','off','training'].forEach(m => {
            const opt = $(`opt-${m}`);
            if (opt) opt.style.outline = m === mode ? `1px solid ${aiModeConfig[m].color}` : '';
        });
        toast('i', 'AI Mode: ' + cfg.label, ROOM);
        await loadSidebar();
    }
}

function selectAiModeOpt(mode, el) {
    selectedAiMode = mode;
    ['active','off','training'].forEach(m => {
        const opt = $(`opt-${m}`);
        if (opt) opt.style.outline = '';
    });
    if (el) el.style.outline = `1px solid ${aiModeConfig[mode]?.color || 'var(--g)'}`;
}

// ── ROOM INFO ──────────────────────────────────────────────
async function loadRoomInfo() {
    if (!ROOM) return;
    const d = await q(`api=room_info&chat_id=${encodeURIComponent(ROOM)}`);
    if (!d) return;

    const nameEl = $('chatName'), subEl = $('chatSubTxt'), avEl = $('chatAv');
    const rpAv = $('rpAv'), rpName = $('rpName');

    if (nameEl) { nameEl.textContent = d.display_name; }
    if (avEl)   { avEl.textContent   = initials(d.display_name); }
    if (subEl)  { subEl.textContent  = d.sender_wa; }
    if (rpAv)   { rpAv.textContent   = initials(d.display_name); }
    if (rpName) { rpName.textContent = d.display_name; }

    // Stats
    if ($('rps-total')) $('rps-total').textContent = formatNum(d.total);
    if ($('rps-user'))  $('rps-user').textContent  = formatNum(d.user_msgs);
    if ($('rps-bot'))   $('rps-bot').textContent   = formatNum(d.bot_msgs);

    // Group info banner
    if (d.chat_type === 'group') {
        if ($('gib-name'))    $('gib-name').textContent    = d.group_name || d.display_name;
        if ($('gib-id'))      $('gib-id').textContent      = d.group_id;
        if ($('gib-members')) $('gib-members').textContent = (d.member_count || '?') + ' anggota';
        if ($('kbCountBadge')) $('kbCountBadge').textContent = (d.knowledge_count || 0) + ' items';
    }

    // Lead badge header
    const leadBadge = $('leadHeaderBadge');
    if (leadBadge) {
        leadBadge.innerHTML = `<i class="fas fa-fire"></i> ${d.lead_count} Lead`;
        leadBadge.style.display = d.lead_count > 0 ? 'inline-flex' : 'none';
    }

    // Info rows
    const el = $('rpInfoRows');
    if (el) {
        const rows = [
            ['Tipe',     d.chat_type === 'group' ? '👥 Grup' : (d.chat_type === 'broadcast' ? '📢 Broadcast' : '👤 Pribadi')],
            ['Nama Kontak', d.contact_name || '-'],
            ['Nama Grup',   d.group_name   || (d.is_group ? '-' : 'N/A')],
            ['Room ID',     d.sender_wa],
            ['Last Active', d.last_active],
            ['Terakhir',    d.last_exact],
            ['Bot',         d.bot_on ? '🟢 Aktif' : '🔴 Mati'],
            ['AI Mode',     d.ai_mode === 'active' ? '⚡ Active' : (d.ai_mode === 'training' ? '🧠 Training' : '🔴 Off')],
        ];
        if (d.chat_type === 'group') {
            rows.push(['Anggota', (d.member_count || '?') + ' orang']);
            rows.push(['Knowledge', (d.knowledge_count || 0) + ' items']);
            if (d.lead_count) rows.push(['Leads', d.lead_count + ' new 🔥']);
        }
        el.innerHTML = rows.map(([k,v]) => `<div class="rp-row">
            <span class="rp-key">${esc(k)}</span>
            <span class="rp-val">${esc(String(v))}</span>
        </div>`).join('');
    }
}

// ── KNOWLEDGE PANEL ────────────────────────────────────────
async function loadKnowledge(cat = '') {
    if (!ROOM || CHATTYPE !== 'group') return;
    const url = `api=group_knowledge&chat_id=${encodeURIComponent(ROOM)}${cat ? '&cat=' + encodeURIComponent(cat) : ''}`;
    const data = await q(url);
    kbData = data || [];
    renderKnowledge(kbData);
}

function renderKnowledge(items) {
    const el = $('kbList');
    if (!el) return;
    if (!items || !items.length) {
        el.innerHTML = `<div class="kb-empty"><i class="fas fa-book-open" style="display:block;font-size:1.5rem;margin-bottom:6px;opacity:0.3;"></i>Belum ada knowledge.<br>Klik + untuk tambah.</div>`;
        return;
    }
    el.innerHTML = items.map(k => `
        <div class="kb-item">
            <div class="kb-item-cat">${esc(k.category)}</div>
            <div class="kb-item-text" title="${esc(k.content)}">${esc(k.content)}</div>
            <div class="kb-item-meta">
                <span class="kb-item-tags">${k.tags ? '#'+k.tags.split(',').map(t=>t.trim()).join(' #') : ''}</span>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span>${esc(k.created_at)}</span>
                    <div class="kb-item-actions">
                        <button class="kb-act-btn edit" onclick="editKbItem(${k.id},'${esc(k.category)}',${JSON.stringify(k.content).replace(/'/g,"\\'")},'${esc(k.tags||'')}')" title="Edit"><i class="fas fa-pen"></i></button>
                        <button class="kb-act-btn del" onclick="deleteKbItem(${k.id})" title="Hapus"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>`).join('');
}

function filterKb(cat, el) {
    currentKbCat = cat;
    document.querySelectorAll('.kb-cat').forEach(b => b.classList.remove('active'));
    if (el) el.classList.add('active');
    loadKnowledge(cat);
}

function editKbItem(id, category, content, tags) {
    $('trainEditId').value = id;
    $('trainCategory').value = category;
    $('trainContent').value = content;
    $('trainTags').value = tags;
    $('trainSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update Knowledge';
    openTrainModal();
}

async function deleteKbItem(id) {
    if (!confirm('Hapus knowledge ini?')) return;
    const res = await post('api=del_knowledge', { id });
    if (res && res.ok) {
        toast('s', 'Dihapus', 'Knowledge berhasil dihapus');
        await loadKnowledge(currentKbCat);
        await loadRoomInfo();
    } else {
        toast('e', 'Gagal', 'Gagal hapus knowledge');
    }
}

// ── TRAIN MODAL ────────────────────────────────────────────
function openTrainModal() {
    const overlay = $('train-modal-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        overlay.style.pointerEvents = 'all';
        overlay.style.display = 'flex';
        requestAnimationFrame(() => {
            overlay.style.transition = 'opacity 0.25s';
            overlay.style.opacity = '1';
            $('trainModalBox').style.transform = 'scale(1)';
        });
        $('trainContent').focus();
    }
}

function closeTrainModal() {
    const overlay = $('train-modal-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        $('trainModalBox').style.transform = 'scale(0.92)';
        setTimeout(() => { overlay.style.pointerEvents = 'none'; }, 250);
    }
    // Reset form
    $('trainEditId').value = '';
    $('trainContent').value = '';
    $('trainTags').value = '';
    $('trainCategory').value = 'Product Knowledge';
    $('trainSubmitBtn').innerHTML = '<i class="fas fa-brain"></i> Simpan Knowledge';
}

async function submitTraining() {
    const content  = ($('trainContent')?.value || '').trim();
    const category = $('trainCategory')?.value || 'General';
    const tags     = $('trainTags')?.value || '';
    const editId   = parseInt($('trainEditId')?.value || '0');

    if (!content) { toast('w', 'Content kosong', 'Isi content knowledge terlebih dahulu'); return; }
    if (!ROOM)    { toast('e', 'Error', 'Tidak ada room yang dipilih'); return; }

    const btn = $('trainSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

    let res;
    if (editId > 0) {
        res = await post('api=edit_knowledge', { id: editId, category, content, tags });
    } else {
        res = await post('api=add_knowledge', { room: ROOM, category, content, tags });
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-brain"></i> Simpan Knowledge';

    if (res && res.ok) {
        toast('s', editId ? 'Knowledge diupdate' : 'Knowledge ditambahkan', category + ' → ' + content.slice(0,40) + '...');
        closeTrainModal();
        await loadKnowledge(currentKbCat);
        await loadRoomInfo();
        await loadStats();
    } else {
        toast('e', 'Gagal', 'Gagal simpan knowledge');
    }
}

// ── AI GROUP SETTINGS ──────────────────────────────────────
async function saveGroupSettings() {
    if (!ROOM) return;
    const personality = ($('aiPersonality')?.value || '').trim();
    const keywords    = ($('aiTriggerKw')?.value   || '').trim();
    const aiMode      = selectedAiMode;

    const res = await post('api=save_group_settings', {
        room: ROOM,
        ai_personality: personality,
        trigger_keywords: keywords,
        ai_mode: aiMode,
    });

    if (res && res.ok) {
        toast('s', 'Settings disimpan', 'Konfigurasi AI grup berhasil diupdate');
        setAiMode(aiMode);
    } else {
        toast('e', 'Gagal', 'Gagal simpan settings');
    }
}

// ── LEADS ──────────────────────────────────────────────────
async function loadLeads() {
    if (!ROOM) return;
    const el = $('leadList');
    if (!el) return;
    const data = await q(`api=leads&room=${encodeURIComponent(ROOM)}`);
    if (!data || !data.length) {
        el.innerHTML = `<div style="text-align:center;padding:16px;color:var(--dim);font-size:0.7rem;opacity:0.5;">Belum ada lead</div>`;
        return;
    }
    el.innerHTML = data.map(l => `
        <div class="lead-item">
            <div class="lead-top">
                <span class="lead-name">${esc(l.contact_name || l.wa_number)}</span>
                <span class="lead-score">${esc(l.intent_score)}</span>
            </div>
            <div class="lead-msg">${esc(l.lead_message)}</div>
            ${l.product_hint ? `<div style="font-size:0.62rem;color:var(--a);margin-bottom:4px;">🎯 ${esc(l.product_hint)}</div>` : ''}
            <div class="lead-bot">
                <select class="lead-status-sel" onchange="updateLeadStatus(${l.id}, this.value)">
                    ${['new','contacted','qualified','closed','lost'].map(s =>
                        `<option value="${s}" ${s===l.status?'selected':''}>${s.charAt(0).toUpperCase()+s.slice(1)}</option>`
                    ).join('')}
                </select>
                <span class="lead-time">${esc(l.created_at)}</span>
            </div>
        </div>`).join('');
}

async function updateLeadStatus(id, status) {
    const res = await post('api=update_lead', { id, status });
    if (res && res.ok) {
        toast('i', 'Lead updated', 'Status → ' + status);
    }
}

// ── SUMMARY GENERATOR ──────────────────────────────────────
async function doGenerateSummary() {
    if (!ROOM) return;
    const resultEl = $('summaryResult');
    if (resultEl) {
        resultEl.classList.add('show');
        resultEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menganalisis percakapan...';
    }
    toast('i', 'Generating Summary', 'AI sedang merangkum percakapan grup...');
    const res = await post('api=generate_summary', { room: ROOM, limit: 60 });
    if (res && res.ok) {
        if (resultEl) {
            resultEl.innerHTML = `
                <div style="font-weight:700;color:var(--c);margin-bottom:6px;font-size:0.72rem;">
                    <i class="fas fa-file-lines"></i> Ringkasan — ${res.msg_count} Pesan
                </div>
                <div>${esc(res.summary)}</div>
                ${res.messages?.length ? `
                <div class="summary-msgs">
                    ${res.messages.map(m => `<div class="summary-msg-line">${esc(m)}</div>`).join('')}
                </div>` : ''}`;
        }
        toast('s', 'Summary Generated', res.msg_count + ' pesan dianalisis');
    } else {
        if (resultEl) resultEl.innerHTML = '<span style="color:var(--r);">Gagal generate summary.</span>';
        toast('e', 'Gagal', 'Gagal generate summary');
    }
}

// ── AUTOMATION LOG ─────────────────────────────────────────
async function loadAutoLog() {
    const data = await q('api=auto_log');
    const el   = $('autoList');
    if (!el) return;
    if (!data || !data.length) {
        el.innerHTML = `<div class="sb-empty" style="padding:20px;"><i class="fas fa-inbox"></i><p>Belum ada task</p></div>`;
        return;
    }
    el.innerHTML = data.map(t => `
        <div class="auto-item">
            <div class="auto-top">
                <span class="auto-num">→ ${esc(t.target)}</span>
                <span class="auto-badge ${esc(t.status)}">${esc(t.status)}</span>
            </div>
            <div class="auto-msg">${esc(t.message)}</div>
            <div class="auto-meta"><span>${esc(t.time)}</span><span>·</span><span>by ${esc(t.requested_by)}</span></div>
        </div>`).join('');
}

// ── CHAT SEARCH ────────────────────────────────────────────
function toggleChatSearch() {
    const bar = $('chatSearchBar');
    if (chatSearchOpen) {
        bar.classList.remove('open');
        chatSearchOpen = false;
        renderMessages(chatMsgs);
    } else {
        bar.classList.add('open');
        chatSearchOpen = true;
        $('chatSearchInput').focus();
    }
}

function closeChatSearch() {
    $('chatSearchBar').classList.remove('open');
    chatSearchOpen = false;
    renderMessages(chatMsgs);
}

function onChatSearch(val) {
    const qv = val.toLowerCase().trim();
    if (!qv) { renderMessages(chatMsgs); return; }
    const filtered = chatMsgs.filter(m => m.message.toLowerCase().includes(qv));
    renderMessages(filtered);
    document.querySelectorAll('.bbl-text').forEach(el => {
        el.innerHTML = el.innerHTML.replace(
            new RegExp('(' + qv.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi'),
            '<mark style="background:rgba(161,255,90,0.3);color:#fff;border-radius:2px;">$1</mark>'
        );
    });
}

// ── CLEAR ROOM ─────────────────────────────────────────────
function openClearModal() {
    $('modal-icon').className = 'modal-icon danger';
    $('modal-ico').className  = 'fas fa-trash';
    $('modal-title').textContent = 'Hapus History?';
    $('modal-msg').textContent   = 'Semua riwayat percakapan di room ini akan dihapus permanen.';
    $('modal-confirm-btn').innerHTML = '<i class="fas fa-trash"></i> Hapus';
    $('modal-confirm-btn').onclick   = doClearRoom;
    $('modal-overlay').classList.add('open');
}

function closeModal() { $('modal-overlay').classList.remove('open'); }

async function doClearRoom() {
    closeModal();
    const res = await post('api=clear_room', { room: ROOM });
    if (res && res.ok) {
        toast('s', 'History Dihapus', 'Semua pesan di room ini telah dihapus');
        chatMsgs = [];
        renderMessages([]);
        await loadStats();
        await loadSidebar();
    } else {
        toast('e', 'Gagal', 'Gagal hapus history');
    }
}

// ── RESTART ────────────────────────────────────────────────
function doRestart() {
    $('modal-icon').className     = 'modal-icon info';
    $('modal-ico').className      = 'fas fa-power-off';
    $('modal-title').textContent  = 'Restart Nebula Engine?';
    $('modal-msg').textContent    = 'Bot akan offline selama ~30 detik lalu reconnect otomatis.';
    $('modal-confirm-btn').innerHTML = '<i class="fas fa-power-off"></i> Restart';
    $('modal-confirm-btn').onclick   = async () => {
        closeModal();
        const fd = new FormData(); fd.append('action', 'restart_bot');
        fetch('index.php', { method:'POST', body: fd })
            .then(() => toast('i', 'Restart Initiated', 'Bot akan reconnect dalam 30 detik'))
            .catch(() => toast('e', 'Error', 'Gagal kirim sinyal restart'));
    };
    $('modal-overlay').classList.add('open');
}

// ── INIT ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {

    await Promise.all([
        loadSidebar(),
        loadStats(),
        loadAutoLog(),
        ROOM ? loadMessages(true) : Promise.resolve(),
        ROOM ? loadRoomInfo()     : Promise.resolve(),
    ]);

    // Load group-specific panels
    if (ROOM && CHATTYPE === 'group') {
        await Promise.all([
            loadKnowledge(),
            loadLeads(),
        ]);
    }

    // Close search results on outside click
    document.addEventListener('click', e => {
        const sr = $('msgSearchResults');
        if (sr && !sr.contains(e.target) && e.target !== $('sbSearch')) {
            sr.classList.remove('open');
        }
    });

    // ESC to close train modal
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeTrainModal();
    });

    // Auto sync intervals
    if (ROOM) syncTimer = setInterval(autoSync, 3000);
    setInterval(() => loadSidebar($('sbSearch')?.value || ''), 8000);
    setInterval(loadStats, 20000);
    setInterval(loadAutoLog, 6000);
    if (ROOM) setInterval(loadRoomInfo, 12000);
    if (ROOM && CHATTYPE === 'group') {
        setInterval(() => loadKnowledge(currentKbCat), 30000);
        setInterval(loadLeads, 15000);
    }
});

// Scroll to bottom on load
<?php if ($selected): ?>
setTimeout(() => {
    const b = $('chatBody');
    if (b) b.scrollTop = b.scrollHeight;
}, 800);
<?php endif; ?>
</script>
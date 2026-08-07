<?php
/* ==========================================================================
   NEBULA NEURAL REPOSITORY — TITAN TRAINING CENTER V15 ULTRA
   ─────────────────────────────────────────────────────────────────────────
   Tab 1 : Knowledge Base        — Full CRUD, export, tagging, versioning
   Tab 2 : Spreadsheet Reader    — Google Sheets & CSV via link
   Tab 3 : Contact Profiles      — Training karakter per nomor WA
   Tab 4 : Group Intelligence    — Group context, personality, roles
   Tab 5 : AI Analytics          — FAQ, leads, summaries, intent logs

   V15 FEATURES:
   ✅ 1.  Group Context Memory      — per-group scoped knowledge
   ✅ 2.  Smart Trigger System      — mention/keyword/command trigger
   ✅ 3.  Contact Identity Resolver — name-based member recognition
   ✅ 4.  Conversation Context Engine — session-based chat memory
   ✅ 5.  Knowledge Training per Group
   ✅ 6.  Auto Knowledge Update + Versioning
   ✅ 7.  Role Detection System     — owner/admin/member
   ✅ 8.  Correction Training       — admin-corrected answers
   ✅ 9.  Anti Hallucination Guard  — KB-only answers
   ✅ 10. Knowledge Tagging System  — multi-category tags
   ✅ 11. AI Summary Generator      — group chat summaries
   ✅ 12. Auto FAQ Generator        — frequency-detected FAQ
   ✅ 13. Intent Detection System   — price/meeting/product intent
   ✅ 14. Group Personality Config  — per-group AI personality
   ✅ 15. Smart Notification Filter — silent mode + trigger rules
   ✅ 16. Knowledge Confidence Score
   ✅ 17. RAG Knowledge Retrieval   — semantic keyword scoring
   ✅ 18. Auto Lead Detection       — prospect intent capture
   ========================================================================== */

if (!defined('DB_NAME')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
}

$status_msg  = '';
$status_text = '';
$edit_data   = null;

// ═══════════════════════════════════════════════════════════════
// AUTO-MIGRATE — SAFE, ADDITIVE ONLY, NEVER DROPS COLUMNS
// ═══════════════════════════════════════════════════════════════
function safe_migrate(mysqli $conn, string $sql): void {
    @mysqli_query($conn, $sql);
}

// --- Knowledge Base ---
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'Product Knowledge'");
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS added_at DATETIME DEFAULT NOW()");
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS scope VARCHAR(20) DEFAULT 'global'");
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS added_by VARCHAR(30) DEFAULT NULL");
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS group_id VARCHAR(50) DEFAULT NULL");
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS version INT DEFAULT 1");
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS parent_id INT DEFAULT NULL");
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS confidence_score DECIMAL(3,2) DEFAULT 1.00");
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS tags TEXT DEFAULT NULL");
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");
safe_migrate($conn, "ALTER TABLE knowledge_base ADD COLUMN IF NOT EXISTS use_count INT DEFAULT 0");

// --- Contact Profiles ---
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS contact_profiles (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    wa_number     VARCHAR(30)  NOT NULL UNIQUE,
    display_name  VARCHAR(255) DEFAULT NULL,
    `character`   TEXT         DEFAULT NULL,
    topic         TEXT         DEFAULT NULL,
    instructions  TEXT         DEFAULT NULL,
    industry      VARCHAR(100) DEFAULT NULL,
    language      VARCHAR(50)  DEFAULT NULL,
    notes         TEXT         DEFAULT NULL,
    safe_mode     TINYINT(1)   DEFAULT 1,
    added_by      VARCHAR(30)  DEFAULT NULL,
    created_at    DATETIME     DEFAULT NOW(),
    updated_at    DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
safe_migrate($conn, "ALTER TABLE contact_profiles ADD COLUMN IF NOT EXISTS safe_mode TINYINT(1) DEFAULT 1");
safe_migrate($conn, "ALTER TABLE contact_profiles ADD COLUMN IF NOT EXISTS group_context TEXT DEFAULT NULL");
safe_migrate($conn, "ALTER TABLE contact_profiles ADD COLUMN IF NOT EXISTS ai_prompt TEXT DEFAULT NULL");

// --- Spreadsheet Cache ---
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS spreadsheet_cache (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    url_hash        VARCHAR(64)  NOT NULL UNIQUE,
    source_url      TEXT         NOT NULL,
    sheet_label     VARCHAR(255) DEFAULT NULL,
    sheet_data      LONGTEXT     DEFAULT NULL,
    raw_json        LONGTEXT     DEFAULT NULL,
    row_count       INT          DEFAULT 0,
    col_count       INT          DEFAULT 0,
    is_sensitive    TINYINT(1)   DEFAULT 0,
    sensitive_note  TEXT         DEFAULT NULL,
    added_by        VARCHAR(30)  DEFAULT NULL,
    fetched_at      DATETIME     DEFAULT NOW(),
    expires_at      DATETIME     DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
safe_migrate($conn, "ALTER TABLE spreadsheet_cache ADD COLUMN IF NOT EXISTS is_sensitive TINYINT(1) DEFAULT 0");
safe_migrate($conn, "ALTER TABLE spreadsheet_cache ADD COLUMN IF NOT EXISTS sensitive_note TEXT DEFAULT NULL");

// ─── V15: Group Intelligence ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS group_profiles (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    group_id          VARCHAR(50)  NOT NULL UNIQUE,
    group_name        VARCHAR(255) DEFAULT NULL,
    group_type        ENUM('client','internal','support','sales','general') DEFAULT 'general',
    personality       TEXT         DEFAULT NULL,
    language_style    VARCHAR(100) DEFAULT 'Informal Bahasa Indonesia',
    trigger_keywords  TEXT         DEFAULT NULL,
    silent_mode       TINYINT(1)   DEFAULT 0,
    mention_only      TINYINT(1)   DEFAULT 0,
    ai_enabled        TINYINT(1)   DEFAULT 1,
    system_prompt     TEXT         DEFAULT NULL,
    anti_hallucinate  TINYINT(1)   DEFAULT 1,
    confidence_thresh DECIMAL(3,2) DEFAULT 0.50,
    notes             TEXT         DEFAULT NULL,
    created_at        DATETIME     DEFAULT NOW(),
    updated_at        DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── V15: Group Members / Role Detection ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS group_members (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    group_id     VARCHAR(50)  NOT NULL,
    wa_number    VARCHAR(30)  NOT NULL,
    display_name VARCHAR(255) DEFAULT NULL,
    role         ENUM('owner','admin','member','bot') DEFAULT 'member',
    can_train    TINYINT(1)   DEFAULT 0,
    can_correct  TINYINT(1)   DEFAULT 0,
    joined_at    DATETIME     DEFAULT NOW(),
    updated_at   DATETIME     DEFAULT NOW(),
    UNIQUE KEY uq_gm (group_id, wa_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── V15: Conversation Context Engine ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS conversation_sessions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    session_key  VARCHAR(100) NOT NULL UNIQUE,
    group_id     VARCHAR(50)  DEFAULT NULL,
    wa_number    VARCHAR(30)  DEFAULT NULL,
    context_json LONGTEXT     DEFAULT NULL,
    intent_last  VARCHAR(100) DEFAULT NULL,
    topic_last   VARCHAR(255) DEFAULT NULL,
    msg_count    INT          DEFAULT 0,
    started_at   DATETIME     DEFAULT NOW(),
    last_active  DATETIME     DEFAULT NOW(),
    expires_at   DATETIME     DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── V15: Intent Detection Logs ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS intent_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    group_id     VARCHAR(50)  DEFAULT NULL,
    wa_number    VARCHAR(30)  DEFAULT NULL,
    display_name VARCHAR(255) DEFAULT NULL,
    message_text TEXT         DEFAULT NULL,
    intent_type  VARCHAR(100) DEFAULT NULL,
    confidence   DECIMAL(3,2) DEFAULT 0.00,
    entities     TEXT         DEFAULT NULL,
    logged_at    DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── V15: Auto Lead Detection ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS ai_leads (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    group_id      VARCHAR(50)  DEFAULT NULL,
    wa_number     VARCHAR(30)  DEFAULT NULL,
    display_name  VARCHAR(255) DEFAULT NULL,
    lead_message  TEXT         DEFAULT NULL,
    product_hint  VARCHAR(255) DEFAULT NULL,
    intent_score  DECIMAL(3,2) DEFAULT 0.00,
    status        ENUM('new','contacted','qualified','closed','lost') DEFAULT 'new',
    notes         TEXT         DEFAULT NULL,
    detected_at   DATETIME     DEFAULT NOW(),
    updated_at    DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── V15: FAQ Generator ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS ai_faq (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    group_id     VARCHAR(50)  DEFAULT NULL,
    question     TEXT         NOT NULL,
    answer       TEXT         DEFAULT NULL,
    frequency    INT          DEFAULT 1,
    source       ENUM('auto','manual','correction') DEFAULT 'auto',
    is_approved  TINYINT(1)   DEFAULT 0,
    category     VARCHAR(100) DEFAULT NULL,
    created_at   DATETIME     DEFAULT NOW(),
    updated_at   DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── V15: AI Summaries ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS ai_summaries (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    group_id     VARCHAR(50)  DEFAULT NULL,
    period_label VARCHAR(100) DEFAULT NULL,
    summary_text LONGTEXT     DEFAULT NULL,
    msg_count    INT          DEFAULT 0,
    from_date    DATETIME     DEFAULT NULL,
    to_date      DATETIME     DEFAULT NULL,
    generated_at DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── V15: Correction Training ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS ai_corrections (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    group_id         VARCHAR(50)  DEFAULT NULL,
    original_question TEXT        NOT NULL,
    wrong_answer     TEXT         DEFAULT NULL,
    correct_answer   TEXT         NOT NULL,
    corrected_by     VARCHAR(30)  DEFAULT NULL,
    kb_id_updated    INT          DEFAULT NULL,
    applied          TINYINT(1)   DEFAULT 0,
    created_at       DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── V15: Knowledge Versioning ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS knowledge_versions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    kb_id        INT          NOT NULL,
    version      INT          NOT NULL,
    topic        VARCHAR(255) DEFAULT NULL,
    content      LONGTEXT     DEFAULT NULL,
    keywords     TEXT         DEFAULT NULL,
    tags         TEXT         DEFAULT NULL,
    changed_by   VARCHAR(30)  DEFAULT NULL,
    changed_at   DATETIME     DEFAULT NOW(),
    change_note  VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── V15: Smart Trigger Rules ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS trigger_rules (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    group_id     VARCHAR(50)  DEFAULT 'global',
    rule_type    ENUM('keyword','mention','command','regex') DEFAULT 'keyword',
    trigger_val  VARCHAR(500) NOT NULL,
    action       ENUM('respond','silent','forward','log','lead') DEFAULT 'respond',
    priority     INT          DEFAULT 5,
    is_active    TINYINT(1)   DEFAULT 1,
    notes        VARCHAR(255) DEFAULT NULL,
    created_at   DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── V15: System Event Log ───
safe_migrate($conn, "CREATE TABLE IF NOT EXISTS ai_event_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) DEFAULT NULL,
    group_id   VARCHAR(50)  DEFAULT NULL,
    wa_number  VARCHAR(30)  DEFAULT NULL,
    payload    TEXT         DEFAULT NULL,
    result     TEXT         DEFAULT NULL,
    logged_at  DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ═══════════════════════════════════════════════════════════════
// ACTIVE TAB
// ═══════════════════════════════════════════════════════════════
$active_tab = $_GET['tab'] ?? 'knowledge';

// ═══════════════════════════════════════════════════════════════
// TAB 1: KNOWLEDGE BASE ACTIONS
// ═══════════════════════════════════════════════════════════════
if (isset($_GET['delete_id']) && $active_tab === 'knowledge') {
    $del_id = (int)$_GET['delete_id'];
    if ($del_id > 0) {
        safe_migrate($conn, "UPDATE knowledge_base SET is_active=0 WHERE id=$del_id");
        $ok = mysqli_query($conn, "DELETE FROM knowledge_base WHERE id=$del_id");
        $status_msg  = $ok ? 'deleted' : 'error';
        $status_text = $ok ? 'Aset berhasil dihapus dari Neural Core.' : 'Gagal hapus: '.mysqli_error($conn);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_knowledge'])) {
    $topic    = mysqli_real_escape_string($conn, trim($_POST['topic']    ?? ''));
    $category = mysqli_real_escape_string($conn, trim($_POST['category'] ?? 'Product Knowledge'));
    $content  = mysqli_real_escape_string($conn, trim($_POST['content']  ?? ''));
    $keywords = mysqli_real_escape_string($conn, trim($_POST['keywords'] ?? ''));
    $tags     = mysqli_real_escape_string($conn, trim($_POST['kb_tags']  ?? ''));
    $group_id = mysqli_real_escape_string($conn, trim($_POST['kb_group_id'] ?? ''));
    $scope    = $group_id ? 'group' : 'global';
    $conf     = floatval($_POST['confidence_score'] ?? 1.00);
    $edit_id  = (int)($_POST['edit_id'] ?? 0);

    if (empty($topic) || empty($content) || empty($keywords)) {
        $status_msg  = 'error';
        $status_text = 'Semua field wajib diisi sebelum commit.';
    } else {
        if ($edit_id > 0) {
            // Save version before updating
            $prev = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM knowledge_base WHERE id=$edit_id LIMIT 1"));
            if ($prev) {
                $pv = (int)($prev['version'] ?? 1);
                $pvT = mysqli_real_escape_string($conn, $prev['topic']); $pvC = mysqli_real_escape_string($conn, $prev['content']);
                $pvK = mysqli_real_escape_string($conn, $prev['keywords']); $pvTg = mysqli_real_escape_string($conn, $prev['tags'] ?? '');
                mysqli_query($conn, "INSERT INTO knowledge_versions (kb_id, version, topic, content, keywords, tags, changed_at, change_note)
                    VALUES ($edit_id, $pv, '$pvT', '$pvC', '$pvK', '$pvTg', NOW(), 'auto-version-before-edit')");
            }
            $new_version = (int)($prev['version'] ?? 1) + 1;
            $sql = "UPDATE knowledge_base SET topic='$topic', category='$category', content='$content', keywords='$keywords',
                    tags='$tags', scope='$scope', group_id=" . ($group_id ? "'$group_id'" : "NULL") .
                    ", confidence_score=$conf, version=$new_version WHERE id=$edit_id";
            $ok  = mysqli_query($conn, $sql);
            $status_msg  = $ok ? 'updated' : 'error';
            $status_text = $ok ? 'Konfigurasi Neural berhasil disinkronkan (v'.$new_version.').' : 'Gagal update: '.mysqli_error($conn);
        } else {
            $sql = "INSERT INTO knowledge_base (topic, category, content, keywords, tags, scope, group_id, confidence_score, version, is_active, added_at)
                    VALUES ('$topic','$category','$content','$keywords','$tags','$scope'," . ($group_id ? "'$group_id'" : "NULL") . ",$conf,1,1,NOW())";
            $ok  = mysqli_query($conn, $sql);
            $status_msg  = $ok ? 'added' : 'error';
            $status_text = $ok ? 'Knowledge Asset berhasil diinjeksikan ke Neural Core.' : 'Gagal insert: '.mysqli_error($conn);
        }
    }
}

if (isset($_GET['edit_id'])) {
    $eid      = (int)$_GET['edit_id'];
    $edit_res = mysqli_query($conn, "SELECT * FROM knowledge_base WHERE id=$eid LIMIT 1");
    $edit_data = $edit_res ? mysqli_fetch_assoc($edit_res) : null;
}

// Export handlers
if (isset($_GET['export_json'])) {
    $all = mysqli_query($conn, "SELECT topic, category, content, keywords, tags, scope, group_id, confidence_score, version, added_at FROM knowledge_base WHERE is_active=1 ORDER BY id ASC");
    $rows = []; while ($r = mysqli_fetch_assoc($all)) $rows[] = $r;
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="nebula_knowledge_'.date('Ymd_His').'.json"');
    header('Cache-Control: no-cache, must-revalidate'); header('Pragma: no-cache');
    echo json_encode(['exported_at' => date('Y-m-d H:i:s'), 'total' => count($rows), 'data' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
if (isset($_GET['export_csv'])) {
    $all = mysqli_query($conn, "SELECT id, topic, category, content, keywords, tags, scope, confidence_score, version, added_at FROM knowledge_base WHERE is_active=1 ORDER BY id ASC");
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="nebula_knowledge_'.date('Ymd_His').'.csv"');
    header('Cache-Control: no-cache, must-revalidate'); header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; $out = fopen('php://output','w');
    fputcsv($out, ['ID','Topic','Category','Content','Keywords','Tags','Scope','Confidence','Version','Added At']);
    while ($r = mysqli_fetch_assoc($all)) fputcsv($out, $r); fclose($out); exit;
}
if (isset($_GET['export_guide'])) {
    $all  = mysqli_query($conn, "SELECT topic, category, content, keywords, tags FROM knowledge_base WHERE is_active=1 ORDER BY category, topic ASC");
    $text = "=============================================================\n  PANDUAN TRAINING NEBULA AI V15 — HVM DIGITAL\n  Diekspor: ".date('d F Y, H:i')." WIB\n=============================================================\n\n";
    $cur_cat = '';
    while ($r = mysqli_fetch_assoc($all)) {
        if ($r['category'] !== $cur_cat) {
            $cur_cat = $r['category'];
            $text .= "\n══════════════════════════════════\n  KATEGORI: ".strtoupper($cur_cat)."\n══════════════════════════════════\n\n";
        }
        $text .= "▸ TOPIK   : {$r['topic']}\n  KONTEN  : {$r['content']}\n  KEYWORDS: {$r['keywords']}\n  TAGS    : {$r['tags']}\n\n";
    }
    header('Content-Type: text/plain; charset=UTF-8'); header('Content-Disposition: attachment; filename="panduan_nebula_v15_'.date('Ymd').'.txt"');
    header('Cache-Control: no-cache, must-revalidate'); header('Pragma: no-cache'); echo $text; exit;
}

// ═══════════════════════════════════════════════════════════════
// TAB 3: CONTACT PROFILES ACTIONS
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_contact'])) {
    $cp_id       = (int)($_POST['cp_edit_id'] ?? 0);
    $cp_num      = mysqli_real_escape_string($conn, preg_replace('/\D/', '', trim($_POST['wa_number'] ?? '')));
    if (strlen($cp_num) > 0 && $cp_num[0] === '0') $cp_num = '62'.substr($cp_num, 1);
    $cp_name     = mysqli_real_escape_string($conn, trim($_POST['cp_name']         ?? ''));
    $cp_char     = mysqli_real_escape_string($conn, trim($_POST['cp_character']    ?? ''));
    $cp_top      = mysqli_real_escape_string($conn, trim($_POST['cp_topic']        ?? ''));
    $cp_ins      = mysqli_real_escape_string($conn, trim($_POST['cp_instructions'] ?? ''));
    $cp_ind      = mysqli_real_escape_string($conn, trim($_POST['cp_industry']     ?? ''));
    $cp_lang     = mysqli_real_escape_string($conn, trim($_POST['cp_language']     ?? ''));
    $cp_note     = mysqli_real_escape_string($conn, trim($_POST['cp_notes']        ?? ''));
    $cp_safe     = isset($_POST['cp_safe_mode']) ? 1 : 0;
    $cp_grpctx   = mysqli_real_escape_string($conn, trim($_POST['cp_group_context'] ?? ''));
    $cp_prompt   = mysqli_real_escape_string($conn, trim($_POST['cp_ai_prompt']    ?? ''));
    if (empty($cp_num)) {
        $status_msg = 'error'; $status_text = 'Nomor WA wajib diisi.';
    } else {
        $sql = $cp_id > 0
            ? "UPDATE contact_profiles SET wa_number='$cp_num', display_name='$cp_name', `character`='$cp_char',
               topic='$cp_top', instructions='$cp_ins', industry='$cp_ind', language='$cp_lang',
               notes='$cp_note', safe_mode=$cp_safe, group_context='$cp_grpctx', ai_prompt='$cp_prompt',
               updated_at=NOW() WHERE id=$cp_id"
            : "INSERT INTO contact_profiles (wa_number, display_name, `character`, topic, instructions, industry, language, notes, safe_mode, group_context, ai_prompt, created_at, updated_at)
               VALUES ('$cp_num','$cp_name','$cp_char','$cp_top','$cp_ins','$cp_ind','$cp_lang','$cp_note',$cp_safe,'$cp_grpctx','$cp_prompt',NOW(),NOW())
               ON DUPLICATE KEY UPDATE display_name='$cp_name', `character`='$cp_char', topic='$cp_top',
               instructions='$cp_ins', industry='$cp_ind', language='$cp_lang', notes='$cp_note',
               safe_mode=$cp_safe, group_context='$cp_grpctx', ai_prompt='$cp_prompt', updated_at=NOW()";
        $ok  = mysqli_query($conn, $sql);
        $status_msg  = $ok ? 'added' : 'error';
        $status_text = $ok ? "Profil kontak $cp_num berhasil disimpan." : 'Gagal: '.mysqli_error($conn);
    }
}
if (isset($_GET['del_contact'])) {
    $dc_id = (int)$_GET['del_contact'];
    if ($dc_id > 0) { $ok = mysqli_query($conn, "DELETE FROM contact_profiles WHERE id=$dc_id"); $status_msg = $ok?'deleted':'error'; $status_text = $ok?'Profil kontak dihapus.':'Gagal: '.mysqli_error($conn); }
    $active_tab = 'contacts';
}
$cp_edit_data = null;
if (isset($_GET['edit_contact'])) {
    $active_tab = 'contacts';
    $eid = (int)$_GET['edit_contact'];
    $r   = mysqli_query($conn, "SELECT * FROM contact_profiles WHERE id=$eid LIMIT 1");
    $cp_edit_data = $r ? mysqli_fetch_assoc($r) : null;
}
if (isset($_GET['export_contacts'])) {
    $all = mysqli_query($conn, "SELECT * FROM contact_profiles ORDER BY id ASC"); $rows = [];
    while ($r = mysqli_fetch_assoc($all)) $rows[] = $r;
    header('Content-Type: application/json; charset=UTF-8'); header('Content-Disposition: attachment; filename="nebula_contacts_'.date('Ymd_His').'.json"');
    header('Cache-Control: no-cache'); echo json_encode(['exported_at'=>date('Y-m-d H:i:s'),'total'=>count($rows),'data'=>$rows], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); exit;
}
if (isset($_GET['export_contacts_csv'])) {
    $all = mysqli_query($conn, "SELECT id, wa_number, display_name, `character`, topic, instructions, industry, language, notes, created_at FROM contact_profiles ORDER BY id ASC");
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="nebula_contacts_'.date('Ymd_His').'.csv"'); header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF"; $out = fopen('php://output','w');
    fputcsv($out, ['ID','WA Number','Name','Character','Topic','Instructions','Industry','Language','Notes','Created At']);
    while ($r = mysqli_fetch_assoc($all)) fputcsv($out, $r); fclose($out); exit;
}

// ═══════════════════════════════════════════════════════════════
// TAB 4: GROUP INTELLIGENCE ACTIONS
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_group'])) {
    $gid    = mysqli_real_escape_string($conn, trim($_POST['group_id']         ?? ''));
    $gname  = mysqli_real_escape_string($conn, trim($_POST['group_name']       ?? ''));
    $gtype  = mysqli_real_escape_string($conn, trim($_POST['group_type']       ?? 'general'));
    $gpers  = mysqli_real_escape_string($conn, trim($_POST['group_personality'] ?? ''));
    $glang  = mysqli_real_escape_string($conn, trim($_POST['group_language']   ?? 'Informal Bahasa Indonesia'));
    $gtrg   = mysqli_real_escape_string($conn, trim($_POST['group_triggers']   ?? ''));
    $gsilent = isset($_POST['group_silent']) ? 1 : 0;
    $gment  = isset($_POST['group_mention']) ? 1 : 0;
    $gai    = isset($_POST['group_ai_enabled']) ? 1 : 0;
    $ganti  = isset($_POST['group_anti_hallucinate']) ? 1 : 0;
    $gconf  = floatval($_POST['group_conf_thresh'] ?? 0.50);
    $gsys   = mysqli_real_escape_string($conn, trim($_POST['group_system_prompt'] ?? ''));
    $gnotes = mysqli_real_escape_string($conn, trim($_POST['group_notes']      ?? ''));
    $edit_gid = (int)($_POST['edit_group_id'] ?? 0);
    if (empty($gid)) { $status_msg='error'; $status_text='Group ID wajib diisi.'; }
    else {
        $sql = $edit_gid > 0
            ? "UPDATE group_profiles SET group_id='$gid', group_name='$gname', group_type='$gtype',
               personality='$gpers', language_style='$glang', trigger_keywords='$gtrg',
               silent_mode=$gsilent, mention_only=$gment, ai_enabled=$gai,
               anti_hallucinate=$ganti, confidence_thresh=$gconf,
               system_prompt='$gsys', notes='$gnotes', updated_at=NOW() WHERE id=$edit_gid"
            : "INSERT INTO group_profiles (group_id, group_name, group_type, personality, language_style, trigger_keywords, silent_mode, mention_only, ai_enabled, anti_hallucinate, confidence_thresh, system_prompt, notes, created_at, updated_at)
               VALUES ('$gid','$gname','$gtype','$gpers','$glang','$gtrg',$gsilent,$gment,$gai,$ganti,$gconf,'$gsys','$gnotes',NOW(),NOW())
               ON DUPLICATE KEY UPDATE group_name='$gname', group_type='$gtype', personality='$gpers',
               language_style='$glang', trigger_keywords='$gtrg', silent_mode=$gsilent,
               mention_only=$gment, ai_enabled=$gai, anti_hallucinate=$ganti, confidence_thresh=$gconf,
               system_prompt='$gsys', notes='$gnotes', updated_at=NOW()";
        $ok = mysqli_query($conn, $sql);
        $status_msg = $ok ? 'added' : 'error';
        $status_text = $ok ? "Group profile '$gid' berhasil disimpan." : 'Gagal: '.mysqli_error($conn);
    }
}
if (isset($_GET['del_group'])) {
    $dg = (int)$_GET['del_group'];
    if ($dg > 0) { mysqli_query($conn, "DELETE FROM group_profiles WHERE id=$dg"); $status_msg='deleted'; $status_text='Group profile dihapus.'; }
    $active_tab = 'groups';
}

// Group Member actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_member'])) {
    $mgid  = mysqli_real_escape_string($conn, trim($_POST['member_group_id'] ?? ''));
    $mnum  = mysqli_real_escape_string($conn, preg_replace('/\D/', '', trim($_POST['member_number'] ?? '')));
    if (strlen($mnum) > 0 && $mnum[0] === '0') $mnum = '62'.substr($mnum, 1);
    $mname = mysqli_real_escape_string($conn, trim($_POST['member_name']    ?? ''));
    $mrole = mysqli_real_escape_string($conn, trim($_POST['member_role']    ?? 'member'));
    $mtrain= isset($_POST['member_can_train'])  ? 1 : 0;
    $mcorr = isset($_POST['member_can_correct']) ? 1 : 0;
    if (!empty($mgid) && !empty($mnum)) {
        $ok = mysqli_query($conn, "INSERT INTO group_members (group_id, wa_number, display_name, role, can_train, can_correct, joined_at, updated_at)
            VALUES ('$mgid','$mnum','$mname','$mrole',$mtrain,$mcorr,NOW(),NOW())
            ON DUPLICATE KEY UPDATE display_name='$mname', role='$mrole', can_train=$mtrain, can_correct=$mcorr, updated_at=NOW()");
        $status_msg  = $ok ? 'added' : 'error';
        $status_text = $ok ? "Member $mnum berhasil ditambahkan ke group $mgid." : 'Gagal: '.mysqli_error($conn);
    }
}
if (isset($_GET['del_member'])) {
    $dm = (int)$_GET['del_member'];
    if ($dm > 0) { mysqli_query($conn, "DELETE FROM group_members WHERE id=$dm"); $status_msg='deleted'; $status_text='Member dihapus.'; }
    $active_tab = 'groups';
}

// Trigger Rules actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_trigger'])) {
    $tgid  = mysqli_real_escape_string($conn, trim($_POST['tr_group_id']  ?? 'global'));
    $ttype = mysqli_real_escape_string($conn, trim($_POST['tr_type']      ?? 'keyword'));
    $tval  = mysqli_real_escape_string($conn, trim($_POST['tr_value']     ?? ''));
    $tact  = mysqli_real_escape_string($conn, trim($_POST['tr_action']    ?? 'respond'));
    $tpri  = (int)($_POST['tr_priority'] ?? 5);
    $tnote = mysqli_real_escape_string($conn, trim($_POST['tr_notes']     ?? ''));
    if (!empty($tval)) {
        $ok = mysqli_query($conn, "INSERT INTO trigger_rules (group_id, rule_type, trigger_val, action, priority, notes, created_at)
            VALUES ('$tgid','$ttype','$tval','$tact',$tpri,'$tnote',NOW())");
        $status_msg = $ok ? 'added' : 'error'; $status_text = $ok ? 'Trigger rule berhasil ditambahkan.' : 'Gagal: '.mysqli_error($conn);
    }
}
if (isset($_GET['del_trigger'])) {
    $dt = (int)$_GET['del_trigger'];
    if ($dt > 0) { mysqli_query($conn, "DELETE FROM trigger_rules WHERE id=$dt"); $status_msg='deleted'; $status_text='Trigger rule dihapus.'; }
    $active_tab = 'groups';
}

// ═══════════════════════════════════════════════════════════════
// TAB 5: AI ANALYTICS ACTIONS
// ═══════════════════════════════════════════════════════════════
// Correction Training
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_correction'])) {
    $cgrp = mysqli_real_escape_string($conn, trim($_POST['corr_group_id']  ?? ''));
    $cq   = mysqli_real_escape_string($conn, trim($_POST['corr_question']  ?? ''));
    $cw   = mysqli_real_escape_string($conn, trim($_POST['corr_wrong']     ?? ''));
    $ca   = mysqli_real_escape_string($conn, trim($_POST['corr_correct']   ?? ''));
    $cby  = mysqli_real_escape_string($conn, trim($_POST['corr_by']        ?? 'admin'));
    if (!empty($cq) && !empty($ca)) {
        $ok = mysqli_query($conn, "INSERT INTO ai_corrections (group_id, original_question, wrong_answer, correct_answer, corrected_by, created_at)
            VALUES (" . ($cgrp ? "'$cgrp'" : "NULL") . ",'$cq','$cw','$ca','$cby',NOW())");
        if ($ok) {
            // Auto-inject correction into knowledge base
            $ckb = mysqli_query($conn, "INSERT INTO knowledge_base (topic, category, content, keywords, tags, scope, confidence_score, version, is_active, added_at)
                VALUES ('Koreksi: $cq','Correction Training','Q: $cq\nA (Benar): $ca','koreksi,correction,".strtolower(substr($cq,0,30))."','correction,qa','" . ($cgrp?'group':'global') . "',1.00,1,1,NOW())");
            $kb_id = $ckb ? (int)mysqli_insert_id($conn) : null;
            if ($kb_id) mysqli_query($conn, "UPDATE ai_corrections SET applied=1, kb_id_updated=$kb_id WHERE id=LAST_INSERT_ID()");
        }
        $status_msg = $ok ? 'added' : 'error'; $status_text = $ok ? 'Koreksi berhasil disimpan dan diinjeksikan ke Knowledge Base.' : 'Gagal: '.mysqli_error($conn);
    }
}
// FAQ Approve
if (isset($_GET['approve_faq'])) {
    $af = (int)$_GET['approve_faq'];
    if ($af > 0) { mysqli_query($conn, "UPDATE ai_faq SET is_approved=1 WHERE id=$af"); $status_msg='updated'; $status_text='FAQ berhasil disetujui.'; }
    $active_tab = 'analytics';
}
if (isset($_GET['del_faq'])) {
    $df = (int)$_GET['del_faq'];
    if ($df > 0) { mysqli_query($conn, "DELETE FROM ai_faq WHERE id=$df"); $status_msg='deleted'; $status_text='FAQ dihapus.'; }
    $active_tab = 'analytics';
}
// Lead update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lead'])) {
    $lid   = (int)$_POST['lead_id'];
    $lst   = mysqli_real_escape_string($conn, trim($_POST['lead_status'] ?? 'new'));
    $lnote = mysqli_real_escape_string($conn, trim($_POST['lead_notes']  ?? ''));
    if ($lid > 0) { mysqli_query($conn, "UPDATE ai_leads SET status='$lst', notes='$lnote', updated_at=NOW() WHERE id=$lid"); $status_msg='updated'; $status_text='Lead berhasil diperbarui.'; }
    $active_tab = 'analytics';
}
if (isset($_GET['del_lead'])) {
    $dl = (int)$_GET['del_lead'];
    if ($dl > 0) { mysqli_query($conn, "DELETE FROM ai_leads WHERE id=$dl"); $status_msg='deleted'; $status_text='Lead dihapus.'; }
    $active_tab = 'analytics';
}

// ═══════════════════════════════════════════════════════════════
// TAB 2: SPREADSHEET READER
// ═══════════════════════════════════════════════════════════════
$sheet_result  = null;
$sheet_preview = null;

$SENSITIVE_KEYWORDS = [
    'password','passwd','login','username','user name','email login',
    'akun','account','pin','otp','token','secret','api key','apikey',
    'kredensial','credential','kunci','private key','passphrase',
    'kartu kredit','credit card','cvv','cvc','nomor kartu',
    'rekening pribadi','no rekening','nik','ktp','passport'
];

function isSensitiveContent(string $text, array $keywords): bool {
    $lower = mb_strtolower($text);
    foreach ($keywords as $kw) { if (strpos($lower, $kw) !== false) return true; }
    return false;
}
function detectSensitiveColumns(array $header, array $keywords): array {
    $found = [];
    foreach ($header as $col) {
        $col = mb_strtolower(trim($col));
        foreach ($keywords as $kw) { if (strpos($col, $kw) !== false) { $found[] = trim($col); break; } }
    }
    return array_unique($found);
}
function normalizeSheetUrl(string $url): ?string {
    if (preg_match('/docs\.google\.com\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/i', $url, $m)) {
        $gid = 0;
        if (preg_match('/[#&?]gid=(\d+)/i', $url, $gm)) $gid = (int)$gm[1];
        return "https://docs.google.com/spreadsheets/d/{$m[1]}/export?format=csv&gid=$gid";
    }
    if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_sheet'])) {
    $sheet_url   = trim($_POST['sheet_url']   ?? '');
    $sheet_label = trim($_POST['sheet_label'] ?? 'Spreadsheet');
    $save_to_kb  = !empty($_POST['save_to_kb']);
    $force_save  = !empty($_POST['force_save_sensitive']);
    $active_tab  = 'spreadsheet';

    $fetchUrl = normalizeSheetUrl($sheet_url);
    if (!$fetchUrl) {
        $sheet_result = ['success'=>false,'error'=>'URL tidak valid.'];
    } else {
        $ch = curl_init($fetchUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>10,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,CURLOPT_ENCODING=>'',CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml,text/csv,*/*','Accept-Language: en-US,en;q=0.9','Cache-Control: no-cache'],CURLOPT_COOKIEFILE=>'',CURLOPT_COOKIEJAR=>'']);
        $raw = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlErr = curl_error($ch); curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            $spId = null; $gid2 = 0;
            if (preg_match('/spreadsheets\/d\/([a-zA-Z0-9_-]+)/i', $sheet_url, $fx)) $spId = $fx[1];
            if (preg_match('/[#&?]gid=(\d+)/i', $sheet_url, $fx)) $gid2 = (int)$fx[1];
            if ($spId) {
                $ch2 = curl_init("https://docs.google.com/spreadsheets/d/{$spId}/gviz/tq?tqx=out:csv&gid={$gid2}");
                curl_setopt_array($ch2,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>10,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_ENCODING=>'',CURLOPT_USERAGENT=>'Mozilla/5.0',CURLOPT_HTTPHEADER=>['Accept: text/csv,*/*']]);
                $raw2 = curl_exec($ch2); $httpCode2 = curl_getinfo($ch2,CURLINFO_HTTP_CODE); curl_close($ch2);
                if ($httpCode2 === 200 && !empty($raw2)) { $raw = $raw2; $httpCode = 200; $curlErr = ''; }
            }
        }

        if ($curlErr || $httpCode !== 200) {
            $errMsg = $curlErr ?: "HTTP $httpCode";
            if ($httpCode === 400) $errMsg = "HTTP 400 — Pastikan sharing 'Anyone with the link'.";
            elseif ($httpCode === 403) $errMsg = "HTTP 403 — Akses ditolak. Pastikan spreadsheet publik.";
            $sheet_result = ['success'=>false,'error'=>$errMsg];
        } else {
            $lines = explode("\n", str_replace(["\r\n","\r"],"\n",$raw));
            $rows = []; $maxCol = 0;
            foreach ($lines as $line) {
                $line = trim($line); if ($line==='') continue;
                $fields = str_getcsv($line,',','"'); $rows[] = $fields;
                if (count($fields) > $maxCol) $maxCol = count($fields);
            }
            if (empty($rows)) { $sheet_result=['success'=>false,'error'=>'Spreadsheet kosong.']; }
            else {
                $header = $rows[0] ?? [];
                $sensitiveColumns = detectSensitiveColumns($header, $SENSITIVE_KEYWORDS);
                $isSensitive      = !empty($sensitiveColumns) || isSensitiveContent($raw, $SENSITIVE_KEYWORDS);
                $sensitiveNote    = $isSensitive ? implode(', ', $sensitiveColumns) : '';
                $formatted = "=== DATA SPREADSHEET: $sheet_label (".count($rows)." baris, $maxCol kolom) ===\n\nHEADER: ".implode(" | ", array_map('trim',$header))."\n".str_repeat("-",60)."\n";
                for ($i = 1; $i < count($rows); $i++) {
                    $parts = [];
                    foreach ($rows[$i] as $cIdx => $cell) {
                        $cell = trim($cell); $colName = isset($header[$cIdx]) ? trim($header[$cIdx]) : "Kolom".($cIdx+1);
                        if ($isSensitive && !$force_save) { $isColSens = false; foreach ($SENSITIVE_KEYWORDS as $kw) { if (strpos(mb_strtolower($colName),$kw)!==false){$isColSens=true;break;} } if ($isColSens) $cell='[DISEMBUNYIKAN]'; }
                        if ($cell !== '') $parts[] = "$colName: $cell";
                    }
                    if (!empty($parts)) $formatted .= "Baris $i: ".implode(", ",$parts)."\n";
                }
                $formatted .= "\n=== END DATA ===";

                $urlHash = hash('sha256',$sheet_url); $hashE = mysqli_real_escape_string($conn,$urlHash);
                $sourceE = mysqli_real_escape_string($conn,$sheet_url); $labelE = mysqli_real_escape_string($conn,$sheet_label);
                $dataE = mysqli_real_escape_string($conn,$formatted); $jsonE = mysqli_real_escape_string($conn,json_encode($rows,JSON_UNESCAPED_UNICODE));
                $rowCnt = count($rows); $isSensInt = $isSensitive?1:0; $sensNoteE = mysqli_real_escape_string($conn,$sensitiveNote);
                @mysqli_query($conn,"INSERT INTO spreadsheet_cache (url_hash,source_url,sheet_label,sheet_data,raw_json,row_count,col_count,is_sensitive,sensitive_note,fetched_at,expires_at) VALUES ('$hashE','$sourceE','$labelE','$dataE','$jsonE',$rowCnt,$maxCol,$isSensInt,'$sensNoteE',NOW(),DATE_ADD(NOW(),INTERVAL 60 MINUTE)) ON DUPLICATE KEY UPDATE sheet_data='$dataE',raw_json='$jsonE',sheet_label='$labelE',row_count=$rowCnt,col_count=$maxCol,is_sensitive=$isSensInt,sensitive_note='$sensNoteE',fetched_at=NOW(),expires_at=DATE_ADD(NOW(),INTERVAL 60 MINUTE)");

                if ($save_to_kb) {
                    if ($isSensitive && !$force_save) { $status_msg='error'; $status_text="⚠️ Spreadsheet mengandung kolom sensitif (".implode(', ',$sensitiveColumns)."). Data TIDAK disimpan ke KB."; }
                    else { $kbTopic = mysqli_real_escape_string($conn,"Spreadsheet: $sheet_label"); $kbKw = mysqli_real_escape_string($conn,strtolower($sheet_label).",spreadsheet,data"); $kbData = mysqli_real_escape_string($conn,$formatted); mysqli_query($conn,"INSERT INTO knowledge_base (topic,category,content,keywords,tags,scope,is_active,added_at) VALUES ('$kbTopic','General','$kbData','$kbKw','spreadsheet,data','" . ($isSensitive?'restricted':'global') . "',1,NOW()) ON DUPLICATE KEY UPDATE content='$kbData',added_at=NOW()"); $status_msg='added'; $status_text="Data spreadsheet berhasil disimpan ke Knowledge Base."; }
                }
                $sheet_result = ['success'=>true,'rows'=>$rows,'header'=>$header,'formatted'=>$formatted,'rowCount'=>$rowCnt,'colCount'=>$maxCol,'isSensitive'=>$isSensitive,'sensitiveColumns'=>$sensitiveColumns,'sensitiveNote'=>$sensitiveNote];
                $sheet_preview = $rows;
            }
        }
    }
}

$cached_sheets = [];
$csq = mysqli_query($conn, "SELECT id, sheet_label, source_url, row_count, col_count, is_sensitive, sensitive_note, fetched_at FROM spreadsheet_cache ORDER BY fetched_at DESC LIMIT 20");
if ($csq) while ($r = mysqli_fetch_assoc($csq)) $cached_sheets[] = $r;

if (isset($_GET['del_sheet'])) {
    mysqli_query($conn, "DELETE FROM spreadsheet_cache WHERE id=".(int)$_GET['del_sheet']);
    header("Location: ?page=training&tab=spreadsheet"); exit;
}

// ═══════════════════════════════════════════════════════════════
// DATA LISTS
// ═══════════════════════════════════════════════════════════════
$categories = ['Product Knowledge'=>'fas fa-box-open','Handling Objection'=>'fas fa-shield-alt','Database Client'=>'fas fa-database','Marketing Strategy'=>'fas fa-bullhorn','SOP & Policy'=>'fas fa-clipboard-list','Pricing & Package'=>'fas fa-tags','Correction Training'=>'fas fa-redo','General'=>'fas fa-info-circle'];
$filter_cat = $_GET['filter_cat'] ?? ''; $search_q = trim($_GET['search'] ?? '');
$where_parts = ["is_active=1"];
if ($filter_cat) $where_parts[] = "category='".mysqli_real_escape_string($conn,$filter_cat)."'";
if ($search_q)   $where_parts[] = "(topic LIKE '%".mysqli_real_escape_string($conn,$search_q)."%' OR content LIKE '%".mysqli_real_escape_string($conn,$search_q)."%' OR keywords LIKE '%".mysqli_real_escape_string($conn,$search_q)."%')";
$where_sql   = 'WHERE '.implode(' AND ',$where_parts);
$res_list    = mysqli_query($conn, "SELECT * FROM knowledge_base $where_sql ORDER BY id DESC");
$total_all   = (int)mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM knowledge_base WHERE is_active=1"))[0];
$total_shown = mysqli_num_rows($res_list);
$cat_counts  = [];
$cc_res = mysqli_query($conn,"SELECT category, COUNT(*) as cnt FROM knowledge_base WHERE is_active=1 GROUP BY category");
if ($cc_res) while ($ccr = mysqli_fetch_assoc($cc_res)) $cat_counts[$ccr['category']] = (int)$ccr['cnt'];

$cp_list = []; $total_cp = 0;
$cpq = mysqli_query($conn,"SELECT * FROM contact_profiles ORDER BY updated_at DESC");
if ($cpq) { while ($r = mysqli_fetch_assoc($cpq)) $cp_list[] = $r; $total_cp = count($cp_list); }

$group_list = []; $total_groups = 0;
$gpq = mysqli_query($conn,"SELECT * FROM group_profiles ORDER BY updated_at DESC");
if ($gpq) { while ($r = mysqli_fetch_assoc($gpq)) $group_list[] = $r; $total_groups = count($group_list); }

$member_list = [];
$mlq = mysqli_query($conn,"SELECT gm.*, gp.group_name FROM group_members gm LEFT JOIN group_profiles gp ON gm.group_id=gp.group_id ORDER BY gm.group_id, gm.role DESC");
if ($mlq) while ($r = mysqli_fetch_assoc($mlq)) $member_list[] = $r;

$trigger_list = [];
$trq = mysqli_query($conn,"SELECT * FROM trigger_rules ORDER BY priority ASC, id DESC");
if ($trq) while ($r = mysqli_fetch_assoc($trq)) $trigger_list[] = $r;

// Analytics data
$faq_list = []; $fq = mysqli_query($conn,"SELECT * FROM ai_faq ORDER BY frequency DESC, created_at DESC LIMIT 50"); if ($fq) while ($r = mysqli_fetch_assoc($fq)) $faq_list[] = $r;
$lead_list = []; $lq = mysqli_query($conn,"SELECT * FROM ai_leads ORDER BY detected_at DESC LIMIT 100"); if ($lq) while ($r = mysqli_fetch_assoc($lq)) $lead_list[] = $r;
$intent_list = []; $iq = mysqli_query($conn,"SELECT * FROM intent_logs ORDER BY logged_at DESC LIMIT 50"); if ($iq) while ($r = mysqli_fetch_assoc($iq)) $intent_list[] = $r;
$correction_list = []; $corq = mysqli_query($conn,"SELECT * FROM ai_corrections ORDER BY created_at DESC LIMIT 50"); if ($corq) while ($r = mysqli_fetch_assoc($corq)) $correction_list[] = $r;
$summary_list = []; $sumq = mysqli_query($conn,"SELECT * FROM ai_summaries ORDER BY generated_at DESC LIMIT 20"); if ($sumq) while ($r = mysqli_fetch_assoc($sumq)) $summary_list[] = $r;

// Analytics stats
$total_leads_new = (int)(mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM ai_leads WHERE status='new'"))[0] ?? 0);
$total_faq       = (int)(mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM ai_faq WHERE is_approved=1"))[0] ?? 0);
$total_intent    = (int)(mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM intent_logs WHERE logged_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)"))[0] ?? 0);
$total_sessions  = (int)(mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM conversation_sessions WHERE last_active >= DATE_SUB(NOW(),INTERVAL 24 HOUR)"))[0] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nebula Neural Training V15 Ultra</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;700&display=swap');

:root {
    --acid:      #b8ff3c;
    --acid2:     #d4ff7a;
    --electric:  #7c5cff;
    --teal:      #00e5c4;
    --danger:    #ff3d5a;
    --warning:   #ffaa00;
    --obs:       #080810;
    --obs2:      #0d0d1a;
    --obs3:      #111120;
    --panel:     rgba(255,255,255,0.02);
    --border:    rgba(255,255,255,0.06);
    --border2:   rgba(255,255,255,0.11);
    --border3:   rgba(184,255,60,0.18);
    --text:      #dde2ef;
    --text-dim:  #52566a;
    --text-mid:  #8890a8;
    --grad-acid: linear-gradient(135deg, #b8ff3c 0%, #00e5c4 100%);
    --grad-elec: linear-gradient(135deg, #7c5cff 0%, #00e5c4 100%);
    --grad-fire: linear-gradient(135deg, #ff3d5a 0%, #ffaa00 100%);
    --glow-acid: 0 0 40px rgba(184,255,60,0.15);
    --glow-elec: 0 0 40px rgba(124,92,255,0.2);
    --shadow-deep: 0 40px 100px rgba(0,0,0,0.8);
    --r-xl: 32px; --r-lg: 22px; --r-md: 14px; --r-sm: 9px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Syne', sans-serif; color: var(--text); background: transparent; -webkit-font-smoothing: antialiased; }
.nt-wrap { padding: 8px 28px 100px 104px; position: relative; }
.nt-wrap::before { content:''; position:fixed; inset:0; background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,0.015) 2px,rgba(0,0,0,0.015) 4px); pointer-events:none; z-index:0; }

/* MASTHEAD */
.nt-masthead { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:28px; flex-wrap:wrap; gap:16px; }
.nt-brand { display:flex; flex-direction:column; gap:4px; }
.nt-brand-label { font-family:'IBM Plex Mono',monospace; font-size:0.6rem; color:var(--acid); letter-spacing:3px; text-transform:uppercase; opacity:0.7; }
.nt-brand-title { font-size:2.4rem; font-weight:800; letter-spacing:-2px; line-height:1; background:var(--grad-acid); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
.nt-brand-sub { font-family:'IBM Plex Mono',monospace; font-size:0.65rem; color:var(--text-dim); margin-top:4px; }
.nt-head-actions { display:flex; gap:10px; flex-wrap:wrap; }
.btn-export { display:flex; align-items:center; gap:7px; padding:10px 18px; border-radius:50px; background:var(--panel); border:1px solid var(--border2); color:var(--text-mid); font-family:'Syne',sans-serif; font-size:0.72rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all 0.25s; white-space:nowrap; }
.btn-export:hover { border-color:var(--border3); color:var(--acid); background:rgba(184,255,60,0.05); }
.btn-export.guide { border-color:rgba(124,92,255,0.3); color:var(--electric); }
.btn-export.guide:hover { background:rgba(124,92,255,0.08); }

/* STATS STRIP */
.nt-stats-strip { display:flex; gap:2px; margin-bottom:28px; background:var(--panel); border:1px solid var(--border); border-radius:var(--r-lg); padding:4px; overflow:hidden; flex-wrap:wrap; }
.nt-stat-pill { flex:1; display:flex; align-items:center; gap:12px; padding:16px 20px; border-radius:var(--r-md); transition:background 0.3s; min-width:140px; }
.nt-stat-pill:hover { background:rgba(255,255,255,0.03); }
.nt-stat-ico { width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:0.85rem; flex-shrink:0; }
.nt-stat-ico.acid   { background:rgba(184,255,60,0.08);  color:var(--acid); }
.nt-stat-ico.teal   { background:rgba(0,229,196,0.08);   color:var(--teal); }
.nt-stat-ico.elec   { background:rgba(124,92,255,0.08);  color:var(--electric); }
.nt-stat-ico.warn   { background:rgba(255,170,0,0.08);   color:var(--warning); }
.nt-stat-ico.danger { background:rgba(255,61,90,0.08);   color:var(--danger); }
.nt-stat-info { min-width:0; }
.nt-stat-lbl  { font-family:'IBM Plex Mono',monospace; font-size:0.55rem; color:var(--text-dim); letter-spacing:1px; text-transform:uppercase; }
.nt-stat-num  { font-size:1.35rem; font-weight:800; letter-spacing:-1px; margin-top:1px; }
.nt-stat-unit { font-family:'IBM Plex Mono',monospace; font-size:0.6rem; color:var(--text-dim); font-weight:500; margin-left:4px; }

/* TABS */
.nt-tabs { display:flex; gap:4px; margin-bottom:28px; background:var(--panel); border:1px solid var(--border); border-radius:var(--r-lg); padding:5px; overflow-x:auto; }
.nt-tab { padding:11px 22px; border-radius:var(--r-md); border:1px solid transparent; background:transparent; color:var(--text-dim); font-family:'Syne',sans-serif; font-size:0.78rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all 0.25s; display:flex; align-items:center; gap:8px; white-space:nowrap; }
.nt-tab:hover { color:var(--text); background:rgba(255,255,255,0.03); }
.nt-tab.active { background:rgba(184,255,60,0.08); border-color:rgba(184,255,60,0.2); color:var(--acid); }
.nt-tab.active.elec { background:rgba(124,92,255,0.1); border-color:rgba(124,92,255,0.25); color:var(--electric); }
.nt-tab.active.teal { background:rgba(0,229,196,0.08); border-color:rgba(0,229,196,0.2); color:var(--teal); }
.nt-tab.active.warn { background:rgba(255,170,0,0.08); border-color:rgba(255,170,0,0.2); color:var(--warning); }
.nt-tab.active.danger { background:rgba(255,61,90,0.08); border-color:rgba(255,61,90,0.2); color:var(--danger); }
.nt-tab-badge { background:rgba(255,255,255,0.07); padding:2px 7px; border-radius:50px; font-family:'IBM Plex Mono',monospace; font-size:0.55rem; font-weight:700; }
.nt-tab.active .nt-tab-badge { background:rgba(184,255,60,0.12); color:var(--acid); }
.nt-tab-section { display:none; animation:fadeIn 0.3s ease both; }
.nt-tab-section.visible { display:block; }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);} }

/* CARDS */
.nt-card { background:rgba(13,13,26,0.7); backdrop-filter:blur(24px); border:1px solid var(--border); border-radius:var(--r-xl); padding:32px 36px; margin-bottom:24px; position:relative; overflow:hidden; transition:border-color 0.35s; }
.nt-card::before { content:''; position:absolute; top:0; left:32px; right:32px; height:1px; background:var(--grad-acid); opacity:0.25; }
.nt-card.elec-accent::before { background:var(--grad-elec); }
.nt-card.teal-accent::before { background:linear-gradient(90deg,var(--teal),transparent); }
.nt-card.warn-accent::before { background:linear-gradient(90deg,var(--warning),transparent); }
.nt-card.danger-accent::before { background:var(--grad-fire); }
.nt-card.edit-mode { border-color:rgba(124,92,255,0.3); box-shadow:var(--glow-elec); }
.nt-card.edit-mode::before { background:var(--grad-elec); opacity:0.4; }
.card-title { display:flex; align-items:center; gap:10px; margin-bottom:26px; font-size:1.05rem; font-weight:800; letter-spacing:-0.5px; }
.card-title-icon { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:0.82rem; }
.cti-acid  { background:rgba(184,255,60,0.1);  color:var(--acid); }
.cti-elec  { background:rgba(124,92,255,0.12); color:var(--electric); }
.cti-teal  { background:rgba(0,229,196,0.1);   color:var(--teal); }
.cti-fire  { background:rgba(255,61,90,0.1);   color:var(--danger); }
.cti-warn  { background:rgba(255,170,0,0.1);   color:var(--warning); }

/* FORM */
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px; }
.form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:18px; margin-bottom:18px; }
.form-grid-4 { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:18px; margin-bottom:18px; }
.form-group  { display:flex; flex-direction:column; gap:7px; margin-bottom:18px; }
.form-group:last-child { margin-bottom:0; }
.fl { font-family:'IBM Plex Mono',monospace; font-size:0.58rem; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-dim); }
.fl-hint { font-family:'IBM Plex Mono',monospace; font-size:0.56rem; color:var(--electric); margin-left:6px; font-weight:500; opacity:0.8; }
.fi, .fs, .ft { background:rgba(255,255,255,0.025); border:1px solid var(--border); border-radius:var(--r-md); padding:12px 16px; color:var(--text); font-family:'Syne',sans-serif; font-size:0.85rem; outline:none; transition:border-color 0.2s,box-shadow 0.2s,background 0.2s; width:100%; }
.fi:focus, .fs:focus, .ft:focus { border-color:var(--acid); background:rgba(184,255,60,0.025); box-shadow:0 0 0 3px rgba(184,255,60,0.07); }
.ft { resize:vertical; min-height:110px; line-height:1.7; }
.fs { cursor:pointer; } .fs option { background:#111; }
.fi.invalid, .ft.invalid { border-color:var(--danger)!important; box-shadow:0 0 0 3px rgba(255,61,90,0.1)!important; }

/* KEYWORD TAGS */
.kw-shell { background:rgba(255,255,255,0.025); border:1px solid var(--border); border-radius:var(--r-md); padding:9px 13px; display:flex; flex-wrap:wrap; gap:7px; align-items:center; cursor:text; min-height:48px; transition:border-color 0.2s,box-shadow 0.2s; }
.kw-shell:focus-within { border-color:var(--acid); box-shadow:0 0 0 3px rgba(184,255,60,0.07); }
.kw-tag { background:rgba(0,229,196,0.1); color:var(--teal); padding:4px 10px 4px 12px; border-radius:50px; font-family:'IBM Plex Mono',monospace; font-size:0.68rem; font-weight:700; display:flex; align-items:center; gap:6px; animation:tagBirth 0.2s cubic-bezier(0.34,1.56,0.64,1) both; }
@keyframes tagBirth { from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;} }
.kw-rm { cursor:pointer; opacity:0.45; transition:opacity 0.15s; font-size:0.65rem; } .kw-rm:hover { opacity:1; }
.kw-input { border:none; background:transparent; outline:none; color:var(--text); font-family:'Syne',sans-serif; font-size:0.82rem; flex:1; min-width:110px; }
.kw-hint { font-family:'IBM Plex Mono',monospace; font-size:0.58rem; color:var(--text-dim); margin-bottom:18px; }
kbd { background:rgba(255,255,255,0.06); border:1px solid var(--border2); border-radius:4px; padding:1px 5px; font-family:'IBM Plex Mono',monospace; font-size:0.58rem; }
.char-bar { display:flex; justify-content:space-between; font-family:'IBM Plex Mono',monospace; font-size:0.58rem; color:var(--text-dim); margin-top:-13px; margin-bottom:18px; }

/* BUTTONS */
.form-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:8px; }
.btn-commit { display:inline-flex; align-items:center; gap:8px; padding:13px 30px; border-radius:50px; border:none; background:var(--grad-acid); color:#0a0a14; font-family:'Syne',sans-serif; font-size:0.78rem; font-weight:800; letter-spacing:0.5px; cursor:pointer; transition:transform 0.25s,box-shadow 0.25s; box-shadow:0 8px 28px rgba(184,255,60,0.2); }
.btn-commit:hover   { transform:translateY(-2px); box-shadow:0 14px 36px rgba(184,255,60,0.3); }
.btn-commit:active  { transform:scale(0.97); }
.btn-commit.loading { opacity:0.6; pointer-events:none; }
.btn-commit.elec-btn { background:var(--grad-elec); box-shadow:0 8px 28px rgba(124,92,255,0.2); color:#fff; }
.btn-commit.elec-btn:hover { box-shadow:0 14px 36px rgba(124,92,255,0.35); }
.btn-commit.teal-btn { background:linear-gradient(135deg,#00e5c4,#0095c8); box-shadow:0 8px 28px rgba(0,229,196,0.2); color:#05101a; }
.btn-commit.warn-btn { background:linear-gradient(135deg,#ffaa00,#ff6b00); box-shadow:0 8px 28px rgba(255,170,0,0.2); color:#100500; }
.btn-ghost { display:inline-flex; align-items:center; gap:7px; padding:13px 22px; border-radius:50px; border:1px solid var(--border2); background:transparent; color:var(--text-mid); font-family:'Syne',sans-serif; font-size:0.78rem; font-weight:700; cursor:pointer; transition:all 0.25s; text-decoration:none; }
.btn-ghost:hover { border-color:var(--border3); color:var(--acid); }
.btn-ghost.danger:hover { border-color:rgba(255,61,90,0.4); color:var(--danger); }
.btn-sm { padding:7px 14px; font-size:0.7rem; border-radius:9px; border:1px solid var(--border); background:transparent; color:var(--text-dim); cursor:pointer; transition:all 0.2s; font-family:'Syne',sans-serif; font-weight:700; display:inline-flex; align-items:center; gap:5px; text-decoration:none; }
.btn-sm:hover { color:var(--text); border-color:var(--border2); }
.btn-sm.acid:hover { color:var(--acid); border-color:rgba(184,255,60,0.3); }
.btn-sm.elec:hover { color:var(--electric); border-color:rgba(124,92,255,0.3); }
.btn-sm.teal:hover { color:var(--teal); border-color:rgba(0,229,196,0.3); }
.btn-sm.red:hover  { color:var(--danger); border-color:rgba(255,61,90,0.3); }
.btn-sm.warn:hover { color:var(--warning); border-color:rgba(255,170,0,0.3); }

/* SECTION HEADERS */
.section-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:12px; }
.section-title { font-size:1.05rem; font-weight:800; display:flex; align-items:center; gap:9px; letter-spacing:-0.3px; }
.section-badge { font-family:'IBM Plex Mono',monospace; font-size:0.6rem; font-weight:700; background:rgba(184,255,60,0.08); color:var(--acid); padding:3px 9px; border-radius:50px; border:1px solid rgba(184,255,60,0.15); }
.section-badge.elec  { background:rgba(124,92,255,0.08); color:var(--electric); border-color:rgba(124,92,255,0.2); }
.section-badge.teal  { background:rgba(0,229,196,0.07);  color:var(--teal);     border-color:rgba(0,229,196,0.15); }
.section-badge.warn  { background:rgba(255,170,0,0.07);  color:var(--warning);  border-color:rgba(255,170,0,0.2); }
.section-badge.danger{ background:rgba(255,61,90,0.07);  color:var(--danger);   border-color:rgba(255,61,90,0.2); }

/* SEARCH */
.search-wrap { position:relative; }
.search-wrap i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--text-dim); font-size:0.75rem; pointer-events:none; }
.search-inp { background:rgba(255,255,255,0.025); border:1px solid var(--border); border-radius:50px; padding:10px 18px 10px 36px; color:var(--text); font-family:'Syne',sans-serif; font-size:0.8rem; outline:none; width:240px; transition:border-color 0.2s,box-shadow 0.2s; }
.search-inp:focus { border-color:var(--acid); box-shadow:0 0 0 3px rgba(184,255,60,0.07); }

/* CATEGORY PILLS */
.cat-pills { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:20px; }
.cat-pill { display:inline-flex; align-items:center; gap:6px; padding:7px 15px; border-radius:50px; background:var(--panel); border:1px solid var(--border); color:var(--text-dim); text-decoration:none; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.3px; transition:all 0.22s; white-space:nowrap; }
.cat-pill:hover  { border-color:var(--border2); color:var(--text); }
.cat-pill.active { background:rgba(184,255,60,0.07); border-color:rgba(184,255,60,0.25); color:var(--acid); }
.cat-pill-num { background:rgba(255,255,255,0.06); padding:1px 6px; border-radius:50px; font-size:0.55rem; }
.cat-pill.active .cat-pill-num { background:rgba(184,255,60,0.12); }

/* ASSET GRID */
.asset-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(330px,1fr)); gap:16px; min-height:140px; }
.asset-card { background:var(--panel); border:1px solid var(--border); border-radius:var(--r-lg); padding:20px; display:flex; flex-direction:column; gap:0; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); animation:cardIn 0.35s cubic-bezier(0.16,1,0.3,1) both; position:relative; overflow:hidden; }
@keyframes cardIn { from{opacity:0;transform:scale(0.96) translateY(8px);}to{opacity:1;transform:scale(1) translateY(0);} }
.asset-card::after { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:var(--grad-acid); opacity:0; transition:opacity 0.3s; }
.asset-card:hover { border-color:rgba(184,255,60,0.18); background:rgba(255,255,255,0.028); transform:translateY(-3px); box-shadow:0 16px 40px rgba(0,0,0,0.5); }
.asset-card:hover::after { opacity:0.4; }
.asset-card.dying { animation:cardDie 0.3s ease forwards; pointer-events:none; }
@keyframes cardDie { to{opacity:0;transform:scale(0.9);} }
.ac-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
.ac-cat { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:50px; background:rgba(0,229,196,0.07); color:var(--teal); font-family:'IBM Plex Mono',monospace; font-size:0.55rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; }
.ac-date { font-family:'IBM Plex Mono',monospace; font-size:0.55rem; color:var(--text-dim); }
.ac-topic { font-size:0.97rem; font-weight:800; color:#fff; margin-bottom:8px; line-height:1.3; letter-spacing:-0.3px; }
.ac-body { font-size:0.78rem; color:var(--text-mid); line-height:1.7; flex:1; margin-bottom:10px; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
.ac-kws { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:10px; }
.ac-kw { padding:2px 7px; border-radius:4px; background:rgba(255,255,255,0.035); color:var(--text-dim); font-family:'IBM Plex Mono',monospace; font-size:0.55rem; font-weight:700; }
.ac-meta { display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
.ac-conf { font-family:'IBM Plex Mono',monospace; font-size:0.58rem; padding:2px 7px; border-radius:4px; }
.conf-high { background:rgba(184,255,60,0.07); color:var(--acid); }
.conf-mid  { background:rgba(255,170,0,0.07);  color:var(--warning); }
.conf-low  { background:rgba(255,61,90,0.07);  color:var(--danger); }
.ac-ver  { font-family:'IBM Plex Mono',monospace; font-size:0.55rem; color:var(--text-dim); }
.ac-scope-tag { font-family:'IBM Plex Mono',monospace; font-size:0.55rem; padding:2px 7px; border-radius:4px; background:rgba(124,92,255,0.07); color:var(--electric); }
.ac-actions { display:flex; gap:7px; border-top:1px solid var(--border); padding-top:14px; margin-top:auto; }
.ac-btn { flex:1; padding:9px 10px; border-radius:9px; border:1px solid var(--border); background:transparent; color:var(--text-dim); font-family:'Syne',sans-serif; font-size:0.7rem; font-weight:700; cursor:pointer; transition:all 0.22s; display:flex; align-items:center; justify-content:center; gap:5px; }
.ac-btn-edit:hover { background:rgba(184,255,60,0.07); border-color:rgba(184,255,60,0.3); color:var(--acid); }
.ac-btn-del:hover  { background:rgba(255,61,90,0.07);  border-color:rgba(255,61,90,0.3);  color:var(--danger); }
.empty-state { grid-column:1/-1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:80px 20px; gap:14px; text-align:center; }
.empty-state i  { font-size:2.8rem; color:var(--text-dim); opacity:0.25; }
.empty-state h4 { font-size:1rem; font-weight:800; color:var(--text-dim); opacity:0.45; }
.empty-state p  { font-size:0.75rem; color:var(--text-dim); opacity:0.3; max-width:280px; }

/* CONTACT GRID */
.cp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:16px; }
.cp-card { background:var(--panel); border:1px solid var(--border); border-radius:var(--r-lg); padding:20px; display:flex; flex-direction:column; gap:12px; transition:all 0.3s; position:relative; overflow:hidden; animation:cardIn 0.35s cubic-bezier(0.16,1,0.3,1) both; }
.cp-card::before { content:''; position:absolute; top:0; left:0; width:3px; height:100%; background:var(--grad-elec); opacity:0; transition:opacity 0.3s; }
.cp-card:hover { border-color:rgba(124,92,255,0.25); transform:translateY(-3px); box-shadow:0 16px 40px rgba(0,0,0,0.5); }
.cp-card:hover::before { opacity:1; }
.cp-card.dying { animation:cardDie 0.3s ease forwards; }
.cp-header { display:flex; justify-content:space-between; align-items:flex-start; }
.cp-num { font-family:'IBM Plex Mono',monospace; font-size:0.78rem; font-weight:700; color:var(--electric); background:rgba(124,92,255,0.08); border:1px solid rgba(124,92,255,0.2); padding:5px 12px; border-radius:50px; }
.cp-since { font-family:'IBM Plex Mono',monospace; font-size:0.55rem; color:var(--text-dim); }
.cp-name { font-size:1rem; font-weight:800; color:#fff; letter-spacing:-0.3px; }
.cp-industry { font-size:0.7rem; color:var(--text-mid); margin-top:2px; }
.cp-fields { display:flex; flex-direction:column; gap:8px; }
.cp-field { display:flex; flex-direction:column; gap:3px; }
.cp-field-lbl { font-family:'IBM Plex Mono',monospace; font-size:0.55rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; }
.cp-field-val { font-size:0.77rem; color:var(--text); line-height:1.5; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.cp-badge-safe   { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:50px; font-family:'IBM Plex Mono',monospace; font-size:0.52rem; font-weight:700; background:rgba(0,229,196,0.07); color:var(--teal); border:1px solid rgba(0,229,196,0.15); }
.cp-badge-unsafe { background:rgba(255,170,0,0.07); color:var(--warning); border-color:rgba(255,170,0,0.2); }
.cp-actions { display:flex; gap:7px; border-top:1px solid var(--border); padding-top:12px; }
.cp-btn { flex:1; padding:8px 10px; border-radius:9px; border:1px solid var(--border); background:transparent; color:var(--text-dim); font-family:'Syne',sans-serif; font-size:0.69rem; font-weight:700; cursor:pointer; transition:all 0.22s; display:flex; align-items:center; justify-content:center; gap:5px; }
.cp-btn-edit:hover { background:rgba(124,92,255,0.08); border-color:rgba(124,92,255,0.35); color:var(--electric); }
.cp-btn-del:hover  { background:rgba(255,61,90,0.07); border-color:rgba(255,61,90,0.3); color:var(--danger); }

/* GROUP CARDS */
.grp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:16px; }
.grp-card { background:var(--panel); border:1px solid var(--border); border-radius:var(--r-lg); padding:20px; display:flex; flex-direction:column; gap:10px; transition:all 0.3s; animation:cardIn 0.35s cubic-bezier(0.16,1,0.3,1) both; position:relative; overflow:hidden; }
.grp-card::after { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:var(--grad-elec); opacity:0; transition:opacity 0.3s; }
.grp-card:hover { border-color:rgba(124,92,255,0.2); transform:translateY(-2px); box-shadow:0 12px 30px rgba(0,0,0,0.4); }
.grp-card:hover::after { opacity:1; }
.grp-id-tag { font-family:'IBM Plex Mono',monospace; font-size:0.72rem; font-weight:700; color:var(--electric); background:rgba(124,92,255,0.08); border:1px solid rgba(124,92,255,0.2); padding:4px 11px; border-radius:50px; display:inline-block; }
.grp-name { font-size:0.95rem; font-weight:800; color:#fff; }
.grp-badges { display:flex; gap:6px; flex-wrap:wrap; }
.grp-badge { font-family:'IBM Plex Mono',monospace; font-size:0.52rem; font-weight:700; padding:2px 7px; border-radius:4px; }
.gb-type   { background:rgba(0,229,196,0.07); color:var(--teal);     border:1px solid rgba(0,229,196,0.15); }
.gb-ai-on  { background:rgba(184,255,60,0.07); color:var(--acid);    border:1px solid rgba(184,255,60,0.15); }
.gb-ai-off { background:rgba(255,61,90,0.06);  color:var(--danger);  border:1px solid rgba(255,61,90,0.15); }
.gb-silent { background:rgba(255,170,0,0.07);  color:var(--warning); border:1px solid rgba(255,170,0,0.2); }
.gb-anti   { background:rgba(124,92,255,0.07); color:var(--electric);border:1px solid rgba(124,92,255,0.15); }
.grp-fields { display:flex; flex-direction:column; gap:6px; }
.grp-field { display:flex; flex-direction:column; gap:2px; }
.grp-field-lbl { font-family:'IBM Plex Mono',monospace; font-size:0.55rem; color:var(--text-dim); text-transform:uppercase; }
.grp-field-val { font-size:0.75rem; color:var(--text-mid); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.grp-actions { display:flex; gap:6px; border-top:1px solid var(--border); padding-top:10px; flex-wrap:wrap; }

/* ANALYTICS / DATA TABLES */
.data-table-wrap { overflow-x:auto; border-radius:var(--r-md); border:1px solid var(--border); margin-bottom:20px; }
.data-table { width:100%; border-collapse:collapse; font-size:0.75rem; }
.data-table th { background:rgba(255,255,255,0.03); color:var(--text-mid); padding:10px 14px; text-align:left; font-family:'IBM Plex Mono',monospace; font-size:0.58rem; text-transform:uppercase; letter-spacing:0.8px; border-bottom:1px solid var(--border2); white-space:nowrap; }
.data-table td { padding:10px 14px; border-bottom:1px solid var(--border); color:var(--text); vertical-align:top; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover td { background:rgba(255,255,255,0.015); }
.data-table .tdim { color:var(--text-dim); font-family:'IBM Plex Mono',monospace; font-size:0.65rem; }
.data-table .tnum { font-family:'IBM Plex Mono',monospace; }
.pill-status { padding:2px 8px; border-radius:50px; font-family:'IBM Plex Mono',monospace; font-size:0.55rem; font-weight:700; }
.ps-new   { background:rgba(184,255,60,0.08); color:var(--acid); }
.ps-contacted { background:rgba(0,229,196,0.08); color:var(--teal); }
.ps-qualified { background:rgba(124,92,255,0.08); color:var(--electric); }
.ps-closed { background:rgba(255,170,0,0.07); color:var(--warning); }
.ps-lost   { background:rgba(255,61,90,0.06); color:var(--danger); }
.intent-tag { padding:2px 8px; border-radius:4px; font-family:'IBM Plex Mono',monospace; font-size:0.58rem; }
.it-price   { background:rgba(255,170,0,0.08); color:var(--warning); }
.it-meeting { background:rgba(0,229,196,0.08); color:var(--teal); }
.it-product { background:rgba(184,255,60,0.07); color:var(--acid); }
.it-support { background:rgba(124,92,255,0.08); color:var(--electric); }
.it-other   { background:rgba(255,255,255,0.04); color:var(--text-dim); }
.role-tag { padding:2px 8px; border-radius:4px; font-family:'IBM Plex Mono',monospace; font-size:0.6rem; font-weight:700; }
.rt-owner  { background:rgba(255,170,0,0.1);  color:var(--warning); border:1px solid rgba(255,170,0,0.2); }
.rt-admin  { background:rgba(184,255,60,0.08); color:var(--acid);    border:1px solid rgba(184,255,60,0.15); }
.rt-member { background:rgba(255,255,255,0.04); color:var(--text-dim); border:1px solid var(--border); }
.rt-bot    { background:rgba(124,92,255,0.08); color:var(--electric); border:1px solid rgba(124,92,255,0.15); }
.trigger-type { padding:2px 8px; border-radius:4px; font-family:'IBM Plex Mono',monospace; font-size:0.58rem; }
.tt-keyword { background:rgba(0,229,196,0.07); color:var(--teal); }
.tt-mention { background:rgba(184,255,60,0.07); color:var(--acid); }
.tt-command { background:rgba(124,92,255,0.07); color:var(--electric); }
.tt-regex   { background:rgba(255,170,0,0.07); color:var(--warning); }
.conf-bar { height:6px; background:var(--border); border-radius:3px; overflow:hidden; width:80px; display:inline-block; vertical-align:middle; margin-left:6px; }
.conf-fill { height:100%; border-radius:3px; }
.cf-high { background:var(--acid); }
.cf-mid  { background:var(--warning); }
.cf-low  { background:var(--danger); }

/* SPREADSHEET */
.url-input-wrap { display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap; }
.url-input-wrap .fi { flex:1; min-width:200px; }
.sheet-result-panel { background:rgba(0,229,196,0.03); border:1px solid rgba(0,229,196,0.15); border-radius:var(--r-lg); padding:24px; margin-bottom:24px; animation:fadeIn 0.4s ease both; }
.sheet-result-panel.warn-panel { background:rgba(255,170,0,0.03); border-color:rgba(255,170,0,0.2); }
.sheet-success-head { display:flex; align-items:center; gap:12px; margin-bottom:18px; }
.sheet-success-ico { width:40px; height:40px; border-radius:12px; background:rgba(0,229,196,0.1); color:var(--teal); display:flex; align-items:center; justify-content:center; font-size:1rem; }
.sheet-success-ico.warn-ico { background:rgba(255,170,0,0.1); color:var(--warning); }
.sheet-meta { font-size:0.8rem; color:var(--text-mid); margin-top:2px; font-family:'IBM Plex Mono',monospace; }
.sheet-table-wrap { overflow-x:auto; border-radius:var(--r-md); border:1px solid var(--border); margin-bottom:16px; max-height:400px; overflow-y:auto; }
.sheet-table { width:100%; border-collapse:collapse; font-size:0.75rem; font-family:'IBM Plex Mono',monospace; }
.sheet-table th { background:rgba(0,229,196,0.08); color:var(--teal); padding:10px 14px; text-align:left; font-size:0.62rem; text-transform:uppercase; letter-spacing:0.8px; border-bottom:1px solid rgba(0,229,196,0.12); white-space:nowrap; position:sticky; top:0; z-index:1; }
.sheet-table td { padding:8px 14px; border-bottom:1px solid var(--border); color:var(--text); white-space:nowrap; max-width:220px; overflow:hidden; text-overflow:ellipsis; }
.sheet-table tr:hover td { background:rgba(255,255,255,0.02); }
.sheet-table tr:last-child td { border-bottom:none; }
.row-num { color:var(--text-dim)!important; font-size:0.55rem!important; }
.col-sensitive { color:var(--warning)!important; }
.cell-hidden { color:var(--warning)!important; font-style:italic; opacity:0.7; }
.cached-list { display:flex; flex-direction:column; gap:8px; }
.cached-item { display:flex; align-items:center; gap:12px; padding:14px 18px; background:var(--panel); border:1px solid var(--border); border-radius:var(--r-md); transition:all 0.22s; flex-wrap:wrap; }
.cached-item:hover { border-color:var(--border2); }
.cached-item.sensitive-item { border-color:rgba(255,170,0,0.15); }
.cached-ico { width:32px; height:32px; border-radius:9px; background:rgba(0,229,196,0.07); color:var(--teal); display:flex; align-items:center; justify-content:center; font-size:0.8rem; flex-shrink:0; }
.cached-ico.sens-ico { background:rgba(255,170,0,0.07); color:var(--warning); }
.cached-info { flex:1; min-width:0; }
.cached-name { font-size:0.85rem; font-weight:700; color:#fff; }
.cached-meta { font-family:'IBM Plex Mono',monospace; font-size:0.58rem; color:var(--text-dim); margin-top:2px; }
.cached-sens-tag { font-family:'IBM Plex Mono',monospace; font-size:0.52rem; background:rgba(255,170,0,0.08); color:var(--warning); border:1px solid rgba(255,170,0,0.2); padding:1px 7px; border-radius:50px; }

/* MODAL */
.modal-bg { position:fixed; inset:0; background:rgba(0,0,0,0.75); backdrop-filter:blur(10px); z-index:9000; display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.3s; }
.modal-bg.open { opacity:1; pointer-events:all; }
.modal-box { background:rgba(13,13,26,0.98); border:1px solid var(--border2); border-radius:var(--r-xl); padding:36px; max-width:420px; width:90%; box-shadow:0 40px 100px rgba(0,0,0,0.8); transform:scale(0.9) translateY(10px); transition:transform 0.35s cubic-bezier(0.34,1.56,0.64,1); text-align:center; position:relative; overflow:hidden; }
.modal-box::before { content:''; position:absolute; top:0; left:20%; right:20%; height:1px; background:var(--grad-fire); opacity:0.5; }
.modal-bg.open .modal-box { transform:scale(1) translateY(0); }
.modal-ico { width:60px; height:60px; border-radius:18px; background:rgba(255,61,90,0.1); color:var(--danger); font-size:1.6rem; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; }
.modal-box h3 { font-size:1.15rem; font-weight:800; margin-bottom:10px; }
.modal-box p  { font-size:0.8rem; color:var(--text-mid); margin-bottom:24px; line-height:1.65; }
.modal-btns   { display:flex; gap:10px; }
.modal-btn-ok { flex:1; padding:13px; background:var(--danger); color:#fff; border:none; border-radius:13px; cursor:pointer; font-family:'Syne',sans-serif; font-weight:800; font-size:0.8rem; transition:all 0.22s; }
.modal-btn-ok:hover { background:#ff1a38; transform:translateY(-1px); }
.modal-btn-cn { flex:1; padding:13px; background:var(--panel); color:var(--text-mid); border:1px solid var(--border2); border-radius:13px; cursor:pointer; font-family:'Syne',sans-serif; font-weight:700; font-size:0.8rem; transition:all 0.22s; }
.modal-btn-cn:hover { color:var(--text); border-color:var(--border3); }

/* TOAST */
.toast-deck { position:fixed; bottom:26px; right:26px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
.toast { display:flex; align-items:center; gap:12px; padding:13px 18px; background:rgba(10,10,20,0.97); backdrop-filter:blur(20px); border:1px solid var(--border2); border-radius:16px; min-width:290px; max-width:420px; box-shadow:0 20px 50px rgba(0,0,0,0.6); animation:toastSlide 0.4s cubic-bezier(0.34,1.56,0.64,1) both; pointer-events:all; position:relative; overflow:hidden; }
@keyframes toastSlide { from{opacity:0;transform:translateX(40px) scale(0.9);}to{opacity:1;transform:translateX(0) scale(1);} }
.toast.out { animation:toastOut 0.3s ease forwards; }
@keyframes toastOut { to{opacity:0;transform:translateX(40px) scale(0.9);} }
.toast::after { content:''; position:absolute; bottom:0; left:0; height:2px; background:var(--acid); border-radius:0 0 16px 16px; animation:countdown 4s linear forwards; }
.toast.err::after { background:var(--danger); animation-duration:5s; }
.toast.warn::after { background:var(--warning); animation-duration:6s; }
.toast.info::after { background:var(--electric); }
@keyframes countdown { from{width:100%;}to{width:0%;} }
.toast-ico { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:0.82rem; flex-shrink:0; }
.toast.ok   .toast-ico { background:rgba(184,255,60,0.1);  color:var(--acid); }
.toast.err  .toast-ico { background:rgba(255,61,90,0.1);   color:var(--danger); }
.toast.warn .toast-ico { background:rgba(255,170,0,0.1);   color:var(--warning); }
.toast.info .toast-ico { background:rgba(124,92,255,0.12); color:var(--electric); }
.toast-body { flex:1; }
.toast-ttl  { font-weight:800; font-size:0.8rem; margin-bottom:1px; }
.toast-msg  { font-size:0.7rem; color:var(--text-dim); line-height:1.4; }
.toast-x { background:none; border:none; color:var(--text-dim); cursor:pointer; opacity:0.4; transition:opacity 0.15s; padding:3px; font-size:0.85rem; display:flex; }
.toast-x:hover { opacity:1; }

/* ALERTS */
.inline-alert { padding:12px 18px; border-radius:var(--r-md); font-size:0.8rem; font-weight:600; margin-bottom:20px; display:flex; align-items:flex-start; gap:9px; line-height:1.6; }
.inline-alert.ok   { background:rgba(184,255,60,0.07);  border:1px solid rgba(184,255,60,0.2); color:var(--acid); }
.inline-alert.err  { background:rgba(255,61,90,0.07);   border:1px solid rgba(255,61,90,0.2);  color:var(--danger); }
.inline-alert.warn { background:rgba(255,170,0,0.07);   border:1px solid rgba(255,170,0,0.2);  color:var(--warning); }
.inline-alert.info { background:rgba(124,92,255,0.07);  border:1px solid rgba(124,92,255,0.2); color:var(--electric); }

/* TOGGLES */
.toggle-row { display:flex; align-items:center; gap:10px; padding:12px 16px; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:var(--r-md); }
.toggle-row input[type=checkbox] { accent-color:var(--acid); width:16px; height:16px; cursor:pointer; }
.toggle-row label { font-size:0.8rem; color:var(--text-mid); cursor:pointer; font-weight:600; }
.toggle-row.warn-toggle { border-color:rgba(255,170,0,0.2); background:rgba(255,170,0,0.03); }
.toggle-row.warn-toggle label { color:var(--warning); }
.toggle-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

/* HELP BLOCKS */
.help-block { background:rgba(124,92,255,0.05); border:1px solid rgba(124,92,255,0.15); border-radius:var(--r-md); padding:16px 20px; font-size:0.78rem; color:var(--text-mid); line-height:1.8; }
.help-block strong { color:var(--electric); }
.help-block code { background:rgba(124,92,255,0.1); color:var(--electric); padding:2px 7px; border-radius:5px; font-family:'IBM Plex Mono',monospace; font-size:0.73rem; }
.help-block.teal-help { background:rgba(0,229,196,0.04); border-color:rgba(0,229,196,0.13); }
.help-block.teal-help strong { color:var(--teal); } .help-block.teal-help code { background:rgba(0,229,196,0.08); color:var(--teal); }
.help-block.acid-help { background:rgba(184,255,60,0.03); border-color:rgba(184,255,60,0.12); }
.help-block.acid-help strong { color:var(--acid); } .help-block.acid-help code { background:rgba(184,255,60,0.07); color:var(--acid); }
.help-block.warn-help { background:rgba(255,170,0,0.04); border-color:rgba(255,170,0,0.15); }
.help-block.warn-help strong { color:var(--warning); } .help-block.warn-help code { background:rgba(255,170,0,0.08); color:var(--warning); }

/* AI PROMPT PREVIEW */
.ai-prompt-preview { background:rgba(184,255,60,0.025); border:1px solid rgba(184,255,60,0.1); border-radius:var(--r-md); padding:14px 16px; font-family:'IBM Plex Mono',monospace; font-size:0.72rem; color:var(--text-mid); line-height:1.8; margin-top:8px; white-space:pre-wrap; max-height:200px; overflow-y:auto; }
.form-divider { border:none; border-top:1px solid var(--border); margin:24px 0; }

/* ANALYTICS GRID */
.analytics-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; margin-bottom:24px; }
.analytics-card { background:var(--panel); border:1px solid var(--border); border-radius:var(--r-lg); padding:20px; }
.an-icon { width:44px; height:44px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:1rem; margin-bottom:12px; }
.an-num  { font-size:2rem; font-weight:800; letter-spacing:-2px; }
.an-lbl  { font-family:'IBM Plex Mono',monospace; font-size:0.6rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; margin-top:4px; }
.security-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:50px; font-family:'IBM Plex Mono',monospace; font-size:0.6rem; font-weight:700; }
.security-badge.safe    { background:rgba(0,229,196,0.08); color:var(--teal); border:1px solid rgba(0,229,196,0.2); }
.security-badge.warning { background:rgba(255,170,0,0.08); color:var(--warning); border:1px solid rgba(255,170,0,0.2); }

@media (max-width:768px) {
    .nt-wrap     { padding:10px 14px 100px 14px; }
    .form-grid-2 { grid-template-columns:1fr; }
    .form-grid-3 { grid-template-columns:1fr 1fr; }
    .form-grid-4 { grid-template-columns:1fr 1fr; }
    .asset-grid  { grid-template-columns:1fr; }
    .cp-grid     { grid-template-columns:1fr; }
    .grp-grid    { grid-template-columns:1fr; }
    .nt-card     { padding:22px 18px; }
    .nt-stats-strip { flex-wrap:wrap; }
    .search-inp  { width:100%; }
    .nt-brand-title { font-size:1.7rem; }
    .toggle-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<div class="nt-wrap">

<!-- ─── MASTHEAD ───────────────────────────────────────────── -->
<div class="nt-masthead">
    <div class="nt-brand">
        <span class="nt-brand-label">Nebula AI · HVM Digital</span>
        <h1 class="nt-brand-title">Neural Training Center</h1>
        <p class="nt-brand-sub">v15 ULTRA · 18 AI Systems · Group Intelligence · Anti-Hallucination · RAG Engine</p>
    </div>
    <div class="nt-head-actions">
        <a href="?page=training&tab=knowledge&export_guide=1" class="btn-export guide"><i class="fas fa-book-open"></i> Panduan</a>
        <a href="?page=training&tab=knowledge&export_json=1"  class="btn-export"><i class="fas fa-file-code"></i> KB JSON</a>
        <a href="?page=training&tab=knowledge&export_csv=1"   class="btn-export"><i class="fas fa-file-csv"></i> KB CSV</a>
        <a href="?page=training&export_contacts=1"            class="btn-export guide"><i class="fas fa-address-book"></i> Kontak JSON</a>
        <a href="?page=training&export_contacts_csv=1"        class="btn-export"><i class="fas fa-file-csv"></i> Kontak CSV</a>
    </div>
</div>

<!-- ─── STATS STRIP ───────────────────────────────────────── -->
<div class="nt-stats-strip">
    <div class="nt-stat-pill">
        <div class="nt-stat-ico acid"><i class="fas fa-brain"></i></div>
        <div class="nt-stat-info"><div class="nt-stat-lbl">Knowledge</div><div class="nt-stat-num"><?= number_format($total_all) ?><span class="nt-stat-unit">assets</span></div></div>
    </div>
    <div class="nt-stat-pill">
        <div class="nt-stat-ico teal"><i class="fas fa-users"></i></div>
        <div class="nt-stat-info"><div class="nt-stat-lbl">Groups</div><div class="nt-stat-num"><?= $total_groups ?><span class="nt-stat-unit">aktif</span></div></div>
    </div>
    <div class="nt-stat-pill">
        <div class="nt-stat-ico elec"><i class="fas fa-user-circle"></i></div>
        <div class="nt-stat-info"><div class="nt-stat-lbl">Contacts</div><div class="nt-stat-num"><?= $total_cp ?><span class="nt-stat-unit">profil</span></div></div>
    </div>
    <div class="nt-stat-pill">
        <div class="nt-stat-ico warn"><i class="fas fa-fire"></i></div>
        <div class="nt-stat-info"><div class="nt-stat-lbl">New Leads</div><div class="nt-stat-num"><?= $total_leads_new ?><span class="nt-stat-unit">prospek</span></div></div>
    </div>
    <div class="nt-stat-pill">
        <div class="nt-stat-ico danger"><i class="fas fa-comments"></i></div>
        <div class="nt-stat-info"><div class="nt-stat-lbl">Sessions 24h</div><div class="nt-stat-num"><?= $total_sessions ?><span class="nt-stat-unit">aktif</span></div></div>
    </div>
    <div class="nt-stat-pill">
        <div class="nt-stat-ico acid"><i class="fas fa-shield-alt"></i></div>
        <div class="nt-stat-info"><div class="nt-stat-lbl">Anti-Halluc</div><div class="nt-stat-num" style="font-size:0.82rem;color:var(--acid);letter-spacing:0;">ON</div></div>
    </div>
</div>

<!-- ─── TAB NAVIGATION ────────────────────────────────────── -->
<div class="nt-tabs">
    <a href="?page=training&tab=knowledge"   class="nt-tab <?= $active_tab==='knowledge'   ? 'active' : '' ?>"><i class="fas fa-brain"></i> Knowledge Base <span class="nt-tab-badge"><?= $total_all ?></span></a>
    <a href="?page=training&tab=spreadsheet" class="nt-tab teal <?= $active_tab==='spreadsheet' ? 'active teal' : '' ?>"><i class="fas fa-table"></i> Spreadsheet <span class="nt-tab-badge"><?= count($cached_sheets) ?></span></a>
    <a href="?page=training&tab=contacts"    class="nt-tab elec <?= $active_tab==='contacts'    ? 'active elec' : '' ?>"><i class="fas fa-user-cog"></i> Contact Profiles <span class="nt-tab-badge"><?= $total_cp ?></span></a>
    <a href="?page=training&tab=groups"      class="nt-tab warn <?= $active_tab==='groups'      ? 'active warn' : '' ?>"><i class="fas fa-layer-group"></i> Group Intelligence <span class="nt-tab-badge"><?= $total_groups ?></span></a>
    <a href="?page=training&tab=analytics"   class="nt-tab danger <?= $active_tab==='analytics'   ? 'active danger' : '' ?>"><i class="fas fa-chart-line"></i> AI Analytics <span class="nt-tab-badge"><?= $total_leads_new + $total_faq ?></span></a>
</div>


<!-- ══════════════════════════════════════════════════════════
     TAB 1: KNOWLEDGE BASE
     ══════════════════════════════════════════════════════════ -->
<div class="nt-tab-section <?= $active_tab==='knowledge' ? 'visible' : '' ?>">

    <div class="nt-card" id="formCard">
        <div class="card-title" id="formTitle">
            <div class="card-title-icon cti-acid"><i class="fas fa-plus"></i></div>
            <span>Inject New Knowledge Asset</span>
        </div>

        <div class="help-block acid-help" style="margin-bottom:22px;">
            <strong>Knowledge Base V15:</strong> Mendukung scope global dan per-grup, versioning otomatis, tags multi-kategori, dan confidence score untuk Anti-Hallucination Guard.<br>
            <strong>Perintah WA:</strong> <code>Nebula, Pelajari: [isi knowledge]</code> &nbsp;|&nbsp; <code>Nebula, simpan: [informasi]</code>
        </div>

        <form method="POST" id="kwForm" onsubmit="return handleKwSubmit(event)">
            <input type="hidden" name="save_knowledge" value="1">
            <input type="hidden" name="edit_id" id="editIdHidden" value="0">
            <input type="hidden" name="keywords" id="kwHidden" value="<?= htmlspecialchars($edit_data['keywords'] ?? '') ?>">

            <div class="form-grid-3">
                <div class="form-group">
                    <label class="fl">Knowledge Topic</label>
                    <input type="text" name="topic" id="topicIn" class="fi" placeholder="Misal: Harga Paket Premium" value="<?= htmlspecialchars($edit_data['topic'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="fl">Neural Category</label>
                    <select name="category" id="catIn" class="fi fs">
                        <?php foreach ($categories as $cat => $icon): ?>
                            <option value="<?= $cat ?>" <?= ($edit_data['category'] ?? 'Product Knowledge') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="fl">Group ID <span class="fl-hint">— kosong = global</span></label>
                    <input type="text" name="kb_group_id" class="fi" placeholder="Kosong = berlaku global" value="<?= htmlspecialchars($edit_data['group_id'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="fl">Full Knowledge Content</label>
                <textarea name="content" id="contentIn" class="fi ft" placeholder="Masukkan informasi detail yang ingin diajarkan ke Nebula..."><?= htmlspecialchars($edit_data['content'] ?? '') ?></textarea>
                <div class="char-bar"><span>Natural language. Bullet points diperbolehkan.</span><span id="charCnt">0</span> karakter</div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="fl">Core Keywords <span class="fl-hint">— RAG matching</span></label>
                    <div class="kw-shell" id="kwShell" onclick="document.getElementById('kwRaw').focus()">
                        <input type="text" id="kwRaw" class="kw-input" placeholder="Ketik keyword, tekan Enter...">
                    </div>
                    <div class="kw-hint">Tekan <kbd>Enter</kbd> atau <kbd>,</kbd> untuk tambah. <kbd>Backspace</kbd> hapus terakhir.</div>
                </div>
                <div>
                    <div class="form-group">
                        <label class="fl">Tags <span class="fl-hint">— multi-label untuk filter</span></label>
                        <input type="text" name="kb_tags" class="fi" placeholder="produk,harga,promo,sop,meeting" value="<?= htmlspecialchars($edit_data['tags'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="fl">Confidence Score <span class="fl-hint">— 0.0 – 1.0</span></label>
                        <input type="number" name="confidence_score" class="fi" min="0" max="1" step="0.01" value="<?= $edit_data['confidence_score'] ?? '1.00' ?>" placeholder="1.00">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-commit" id="kwSubmitBtn"><i class="fas fa-bolt"></i> <span id="kwSubmitLbl">Commit to Neural Core</span></button>
                <button type="button" class="btn-ghost" id="kwCancelBtn" onclick="resetKwForm()" style="display:none;"><i class="fas fa-times"></i> Batal Edit</button>
            </div>
        </form>
    </div>

    <!-- Repository -->
    <div class="section-head">
        <div class="section-title"><i class="fas fa-layer-group" style="color:var(--teal);"></i> Intelligence Repository <span class="section-badge" id="repoCount"><?= $total_shown ?> assets</span></div>
        <div class="search-wrap"><i class="fas fa-search"></i><input type="text" class="search-inp" id="liveSearchIn" placeholder="Cari knowledge..." value="<?= htmlspecialchars($search_q) ?>" oninput="liveSearch(this.value)"></div>
    </div>

    <div class="cat-pills">
        <a href="?page=training&tab=knowledge" class="cat-pill <?= !$filter_cat ? 'active' : '' ?>"><i class="fas fa-th"></i> SEMUA <span class="cat-pill-num"><?= $total_all ?></span></a>
        <?php foreach ($categories as $cat => $icon): $cnt = $cat_counts[$cat] ?? 0; if (!$cnt) continue; ?>
            <a href="?page=training&tab=knowledge&filter_cat=<?= urlencode($cat) ?>" class="cat-pill <?= $filter_cat===$cat ? 'active' : '' ?>"><i class="<?= $icon ?>"></i> <?= $cat ?> <span class="cat-pill-num"><?= $cnt ?></span></a>
        <?php endforeach; ?>
    </div>

    <div class="asset-grid" id="assetGrid">
        <?php if ($total_shown > 0): ?>
            <?php mysqli_data_seek($res_list, 0); $cd = 0; while ($row = mysqli_fetch_assoc($res_list)):
                $kws = array_filter(array_map('trim', explode(',', $row['keywords'] ?? '')));
                $tags_arr = array_filter(array_map('trim', explode(',', $row['tags'] ?? '')));
                $cat_icon = $categories[$row['category']] ?? 'fas fa-info-circle';
                $date_str = isset($row['added_at']) ? date('d/m/y', strtotime($row['added_at'])) : '-';
                $conf     = floatval($row['confidence_score'] ?? 1);
                $conf_cls = $conf >= 0.8 ? 'conf-high' : ($conf >= 0.5 ? 'conf-mid' : 'conf-low');
                $cfill_cls = $conf >= 0.8 ? 'cf-high' : ($conf >= 0.5 ? 'cf-mid' : 'cf-low');
                $cd += 40; ?>
            <div class="asset-card" id="card-<?= $row['id'] ?>" style="animation-delay:<?= $cd ?>ms"
                 data-topic="<?= strtolower(htmlspecialchars($row['topic'])) ?>"
                 data-content="<?= strtolower(htmlspecialchars(substr($row['content'],0,300))) ?>"
                 data-kws="<?= strtolower(htmlspecialchars($row['keywords'])) ?>">
                <div class="ac-top">
                    <span class="ac-cat"><i class="<?= $cat_icon ?>"></i> <?= htmlspecialchars($row['category']) ?></span>
                    <span class="ac-date"><?= $date_str ?></span>
                </div>
                <h3 class="ac-topic"><?= htmlspecialchars($row['topic']) ?></h3>
                <p class="ac-body"><?= nl2br(htmlspecialchars($row['content'])) ?></p>
                <?php if ($kws): ?>
                <div class="ac-kws"><?php foreach (array_slice($kws,0,5) as $kw): ?><span class="ac-kw"><?= htmlspecialchars($kw) ?></span><?php endforeach; ?><?php if (count($kws)>5): ?><span class="ac-kw" style="color:var(--text-dim);">+<?= count($kws)-5 ?></span><?php endif; ?></div>
                <?php endif; ?>
                <div class="ac-meta">
                    <span class="ac-conf <?= $conf_cls ?>"><i class="fas fa-medal"></i> <?= number_format($conf*100) ?>% conf<span class="conf-bar"><span class="conf-fill <?= $cfill_cls ?>" style="width:<?= ($conf*100) ?>%"></span></span></span>
                    <span class="ac-ver">v<?= $row['version'] ?? 1 ?></span>
                    <?php if ($row['scope'] === 'group'): ?><span class="ac-scope-tag"><i class="fas fa-users"></i> <?= htmlspecialchars($row['group_id']??'group') ?></span><?php endif; ?>
                    <?php foreach (array_slice($tags_arr,0,2) as $tag): ?><span class="ac-kw" style="background:rgba(184,255,60,0.05);color:var(--acid);"><?= htmlspecialchars($tag) ?></span><?php endforeach; ?>
                </div>
                <div class="ac-actions">
                    <button class="ac-btn ac-btn-edit" onclick='loadKwEdit(<?= json_encode(["id"=>$row['id'],"topic"=>$row['topic'],"category"=>$row['category'],"content"=>$row['content'],"keywords"=>$row['keywords'],"tags"=>$row['tags']??"","group_id"=>$row['group_id']??"","confidence_score"=>$row['confidence_score']??1]) ?>)'><i class="fas fa-edit"></i> Edit</button>
                    <button class="ac-btn ac-btn-del" onclick="openDel(<?= $row['id'] ?>,'<?= addslashes(htmlspecialchars($row['topic'])) ?>')"><i class="fas fa-trash-alt"></i> Hapus</button>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-folder-open"></i><h4>Tidak ada knowledge</h4><p><?= $search_q ? "Tidak ada hasil untuk \"$search_q\"" : ($filter_cat ? "Kategori ini kosong." : "Belum ada knowledge yang diinjeksikan.") ?></p></div>
        <?php endif; ?>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     TAB 2: SPREADSHEET READER
     ══════════════════════════════════════════════════════════ -->
<div class="nt-tab-section <?= $active_tab==='spreadsheet' ? 'visible' : '' ?>">
    <div class="nt-card teal-accent">
        <div class="card-title"><div class="card-title-icon cti-teal"><i class="fas fa-table"></i></div><span>Baca Spreadsheet — Google Sheets / CSV</span></div>
        <div class="help-block teal-help" style="margin-bottom:20px;">
            <strong>Syarat Google Sheets:</strong> Share → <code>Anyone with the link</code> → Viewer → Copy Link, atau File → Share → <strong>Publish to web</strong> → CSV → Publish.<br>
            <strong>🔒 Security:</strong> Kolom sensitif (password, login, token, PIN) otomatis disembunyikan. <strong>Perintah WA:</strong> <code>Nebula, refresh spreadsheet [URL]</code>
        </div>
        <form method="POST" id="sheetForm">
            <input type="hidden" name="fetch_sheet" value="1">
            <div class="form-grid-2">
                <div class="form-group"><label class="fl">URL Google Sheets / CSV</label><input type="text" name="sheet_url" class="fi" placeholder="https://docs.google.com/spreadsheets/d/..." value="<?= htmlspecialchars($_POST['sheet_url'] ?? '') ?>"></div>
                <div class="form-group"><label class="fl">Label / Nama Spreadsheet</label><input type="text" name="sheet_label" class="fi" placeholder="Misal: Data Klien Q1 2025" value="<?= htmlspecialchars($_POST['sheet_label'] ?? '') ?>"></div>
            </div>
            <div class="toggle-row" style="margin-bottom:10px;"><input type="checkbox" name="save_to_kb" id="saveKbChk" value="1" <?= !empty($_POST['save_to_kb']) ? 'checked' : '' ?>><label for="saveKbChk">Simpan data ke <strong>Knowledge Base</strong> Nebula secara permanen (data non-sensitif saja)</label></div>
            <div class="toggle-row warn-toggle" style="margin-bottom:18px;"><input type="checkbox" name="force_save_sensitive" id="forceSaveChk" value="1"><label for="forceSaveChk"><i class="fas fa-exclamation-triangle"></i> <strong>Paksa Simpan</strong> — Saya yakin data ini aman (termasuk kolom sensitif)</label></div>
            <div class="form-actions"><button type="submit" class="btn-commit teal-btn" id="sheetBtn"><i class="fas fa-cloud-download-alt"></i> <span id="sheetBtnLbl">Fetch & Baca Spreadsheet</span></button></div>
        </form>
    </div>

    <?php if ($sheet_result): ?>
        <?php if ($sheet_result['success']): ?>
            <?php if (!empty($sheet_result['sensitiveColumns'])): ?>
            <div class="inline-alert warn"><i class="fas fa-shield-alt" style="flex-shrink:0;margin-top:2px;"></i><div><strong>⚠️ KOLOM SENSITIF TERDETEKSI:</strong> <?= htmlspecialchars(implode(', ', $sheet_result['sensitiveColumns'])) ?> — data dari kolom ini disembunyikan secara default.</div></div>
            <?php endif; ?>
            <div class="sheet-result-panel <?= !empty($sheet_result['sensitiveColumns']) ? 'warn-panel' : '' ?>">
                <div class="sheet-success-head">
                    <div class="sheet-success-ico <?= !empty($sheet_result['sensitiveColumns']) ? 'warn-ico' : '' ?>"><i class="fas <?= !empty($sheet_result['sensitiveColumns']) ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i></div>
                    <div>
                        <div style="font-size:1rem;font-weight:800;color:#fff;">Spreadsheet berhasil dibaca <?= empty($sheet_result['sensitiveColumns']) ? '<span class="security-badge safe" style="margin-left:8px;"><i class="fas fa-check"></i> Aman</span>' : '<span class="security-badge warning" style="margin-left:8px;"><i class="fas fa-lock"></i> Kolom sensitif tersembunyi</span>' ?></div>
                        <div class="sheet-meta"><?= $sheet_result['rowCount'] ?> baris · <?= $sheet_result['colCount'] ?> kolom · <?= strlen($sheet_result['formatted']) ?> karakter</div>
                    </div>
                </div>
                <div class="sheet-table-wrap">
                    <table class="sheet-table">
                        <thead><tr><th>#</th><?php foreach ($sheet_result['header'] as $h): $isSensCol=false; foreach ($SENSITIVE_KEYWORDS as $sk) { if(strpos(mb_strtolower($h),$sk)!==false){$isSensCol=true;break;} } ?><th class="<?= $isSensCol?'col-sensitive':'' ?>"><?= htmlspecialchars($h) ?><?= $isSensCol?' <i class="fas fa-lock" style="font-size:0.6rem;"></i>':'' ?></th><?php endforeach; ?></tr></thead>
                        <tbody><?php for ($ri=1;$ri<count($sheet_result['rows']);$ri++): ?><tr><td class="row-num"><?= $ri ?></td><?php foreach ($sheet_result['rows'][$ri] as $cIdx=>$cell): $colName=$sheet_result['header'][$cIdx]??''; $isHid=false; foreach ($SENSITIVE_KEYWORDS as $sk){if(strpos(mb_strtolower($colName),$sk)!==false){$isHid=true;break;}} ?><td title="<?= $isHid?'[DISEMBUNYIKAN]':htmlspecialchars($cell) ?>" class="<?= $isHid?'cell-hidden':'' ?>"><?= $isHid?'••••••':htmlspecialchars($cell) ?></td><?php endforeach; ?></tr><?php endfor; ?></tbody>
                    </table>
                </div>
                <details style="margin-bottom:16px;"><summary style="cursor:pointer;font-family:'IBM Plex Mono',monospace;font-size:0.72rem;color:var(--teal);padding:10px 0;user-select:none;"><i class="fas fa-code"></i> Lihat format teks Nebula (<?= $sheet_result['rowCount'] ?> baris)</summary><pre style="background:rgba(0,0,0,0.3);border:1px solid var(--border);border-radius:var(--r-md);padding:16px;font-family:'IBM Plex Mono',monospace;font-size:0.68rem;color:var(--teal);overflow-x:auto;max-height:300px;overflow-y:auto;margin-top:10px;white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars($sheet_result['formatted']) ?></pre></details>
                <div style="display:flex;gap:9px;flex-wrap:wrap;"><button class="btn-commit teal-btn" onclick="downloadSheetTxt()"><i class="fas fa-download"></i> Download .txt</button><button class="btn-ghost" onclick="copySheetData()"><i class="fas fa-copy"></i> Copy Data</button></div>
                <textarea id="sheetRawTxt" style="display:none;"><?= htmlspecialchars($sheet_result['formatted']) ?></textarea>
            </div>
        <?php else: ?>
            <div class="inline-alert err" style="margin-bottom:20px;"><i class="fas fa-exclamation-triangle" style="flex-shrink:0;"></i><?= htmlspecialchars($sheet_result['error']) ?></div>
            <?php if (str_contains($sheet_result['error']??'','400')||str_contains($sheet_result['error']??'','403')): ?>
            <div class="inline-alert info" style="margin-bottom:20px;"><i class="fas fa-lightbulb" style="flex-shrink:0;margin-top:2px;"></i><div><strong>Solusi — Publish to web:</strong> File → Share → Publish to web → <strong>CSV</strong> → Publish → Copy link</div></div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($cached_sheets)): ?>
    <div class="nt-card teal-accent">
        <div class="card-title"><div class="card-title-icon cti-teal"><i class="fas fa-history"></i></div><span>Cache Aktif</span></div>
        <div class="cached-list">
            <?php foreach ($cached_sheets as $cs): ?>
            <div class="cached-item <?= $cs['is_sensitive']?'sensitive-item':'' ?>">
                <div class="cached-ico <?= $cs['is_sensitive']?'sens-ico':'' ?>"><i class="fas <?= $cs['is_sensitive']?'fa-lock':'fa-table' ?>"></i></div>
                <div class="cached-info">
                    <div class="cached-name"><?= htmlspecialchars($cs['sheet_label']?:'Spreadsheet') ?> <?= $cs['is_sensitive']?'<span class="cached-sens-tag"><i class="fas fa-shield-alt"></i> SENSITIF</span>':'' ?></div>
                    <div class="cached-meta"><?= $cs['row_count'] ?> baris · <?= $cs['col_count'] ?> kolom · <?= date('d/m/Y H:i',strtotime($cs['fetched_at'])) ?><?= $cs['sensitive_note']?' · Sensitif: '.htmlspecialchars($cs['sensitive_note']):'' ?></div>
                </div>
                <a href="?page=training&tab=spreadsheet&del_sheet=<?= $cs['id'] ?>" class="btn-sm red" onclick="return confirm('Hapus cache ini?')"><i class="fas fa-trash-alt"></i></a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>


<!-- ══════════════════════════════════════════════════════════
     TAB 3: CONTACT PROFILES
     ══════════════════════════════════════════════════════════ -->
<div class="nt-tab-section <?= $active_tab==='contacts' ? 'visible' : '' ?>">
    <div class="nt-card elec-accent" id="cpFormCard">
        <div class="card-title" id="cpFormTitle"><div class="card-title-icon cti-elec"><i class="fas fa-user-plus"></i></div><span>Training Profil Kontak Baru</span></div>
        <div class="help-block" style="margin-bottom:22px;">
            <strong>Contact Identity Resolver V15:</strong> Nebula mengenali anggota berdasarkan nama, bukan hanya nomor. Profil berlaku global di semua room.<br>
            <strong>Perintah WA:</strong> <code>Nebula, set kontak 628xxxx karakter: [deskripsi]</code> &nbsp;|&nbsp; <code>Nebula, lihat profil kontak 628xxxx</code>
        </div>
        <form method="POST" id="cpForm">
            <input type="hidden" name="save_contact" value="1">
            <input type="hidden" name="cp_edit_id" id="cpEditId" value="0">
            <div class="form-grid-3">
                <div class="form-group"><label class="fl">Nomor WhatsApp <span style="color:var(--danger);">*</span></label><input type="text" name="wa_number" id="cpNum" class="fi" placeholder="628xxxxxxxxxx" value="<?= htmlspecialchars($cp_edit_data['wa_number']??'') ?>"></div>
                <div class="form-group"><label class="fl">Nama Panggilan</label><input type="text" name="cp_name" id="cpName" class="fi" placeholder="Pak Budi / Bu Sari" value="<?= htmlspecialchars($cp_edit_data['display_name']??'') ?>"></div>
                <div class="form-group"><label class="fl">Industri / Bidang</label><input type="text" name="cp_industry" id="cpIndustry" class="fi" placeholder="Retail, IT, Kuliner" value="<?= htmlspecialchars($cp_edit_data['industry']??'') ?>"></div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="fl">Karakter Nebula</label><textarea name="cp_character" id="cpChar" class="fi ft" style="min-height:90px;" placeholder="Sales ramah, proaktif, fokus closing..."><?= htmlspecialchars($cp_edit_data['character']??'') ?></textarea></div>
                <div class="form-group"><label class="fl">Konteks / Topik Utama</label><textarea name="cp_topic" id="cpTopic" class="fi ft" style="min-height:90px;" placeholder="Proyek website toko online, budget 15jt..."><?= htmlspecialchars($cp_edit_data['topic']??'') ?></textarea></div>
            </div>
            <div class="form-group"><label class="fl">Instruksi Khusus</label><textarea name="cp_instructions" id="cpInst" class="fi ft" style="min-height:80px;" placeholder="Selalu tawarkan paket premium dulu. Follow up tiap 3 hari..."><?= htmlspecialchars($cp_edit_data['instructions']??'') ?></textarea></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="fl">Gaya Bahasa</label><input type="text" name="cp_language" id="cpLang" class="fi" placeholder="Formal / Informal / Inggris" value="<?= htmlspecialchars($cp_edit_data['language']??'') ?>"></div>
                <div class="form-group"><label class="fl">Catatan Internal</label><input type="text" name="cp_notes" id="cpNotes" class="fi" placeholder="Catatan untuk tim (tidak dikirim ke AI)" value="<?= htmlspecialchars($cp_edit_data['notes']??'') ?>"></div>
            </div>
            <hr class="form-divider">
            <div style="margin-bottom:18px;">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:0.65rem;color:var(--teal);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;display:flex;align-items:center;gap:8px;"><i class="fas fa-users"></i> Konteks Grup (Opsional)</div>
                <div class="form-group"><label class="fl">Instruksi Khusus Saat di Grup</label><textarea name="cp_group_context" id="cpGrpCtx" class="fi ft" style="min-height:70px;" placeholder="Di grup proyek XYZ, fokus update progress. Jangan sebut harga di grup klien..."><?= htmlspecialchars($cp_edit_data['group_context']??'') ?></textarea></div>
            </div>
            <div style="margin-bottom:18px;">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:0.65rem;color:var(--acid);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;display:flex;align-items:center;gap:8px;"><i class="fas fa-robot"></i> Custom AI Prompt (Advanced) <span style="font-size:0.55rem;color:var(--text-dim);font-weight:400;text-transform:none;letter-spacing:0;">— langsung masuk ke system prompt</span></div>
                <div class="form-group"><label class="fl">Prompt AI Tambahan</label><textarea name="cp_ai_prompt" id="cpAiPrompt" class="fi ft" style="min-height:100px;font-family:'IBM Plex Mono',monospace;font-size:0.78rem;" placeholder="Kontak VIP enterprise. Gunakan bahasa formal dan profesional..."><?= htmlspecialchars($cp_edit_data['ai_prompt']??'') ?></textarea></div>
                <?php if (!empty($cp_edit_data['ai_prompt'])): ?><div style="font-family:'IBM Plex Mono',monospace;font-size:0.58rem;color:var(--acid);margin-bottom:6px;"><i class="fas fa-eye"></i> Preview Prompt Aktif</div><div class="ai-prompt-preview"><?= htmlspecialchars($cp_edit_data['ai_prompt']) ?></div><?php endif; ?>
            </div>
            <div class="toggle-row warn-toggle" style="margin-bottom:18px;"><input type="checkbox" name="cp_safe_mode" id="cpSafeMode" value="1" <?= ($cp_edit_data['safe_mode']??1) ? 'checked' : '' ?>><label for="cpSafeMode"><i class="fas fa-shield-alt"></i> <strong>Safe Mode</strong> — Tidak membagikan data sensitif (password, login, token) ke kontak ini</label></div>
            <div class="form-actions">
                <button type="submit" class="btn-commit elec-btn" id="cpSubmitBtn"><i class="fas fa-save"></i> <span id="cpSubmitLbl">Simpan Profil Kontak</span></button>
                <button type="button" class="btn-ghost" id="cpCancelBtn" onclick="resetCpForm()" style="display:none;"><i class="fas fa-times"></i> Batal Edit</button>
            </div>
        </form>
    </div>

    <div class="section-head">
        <div class="section-title"><i class="fas fa-address-book" style="color:var(--electric);"></i> Profil Kontak Tertraining <span class="section-badge elec"><?= $total_cp ?> kontak</span></div>
        <div class="search-wrap"><i class="fas fa-search"></i><input type="text" class="search-inp" id="cpSearchIn" placeholder="Cari kontak..." oninput="cpSearch(this.value)"></div>
    </div>

    <?php if (!empty($cp_list)): ?>
    <div class="cp-grid" id="cpGrid">
        <?php $cpd = 0; foreach ($cp_list as $cp): $cpd += 40; ?>
        <div class="cp-card" id="cpcard-<?= $cp['id'] ?>" style="animation-delay:<?= $cpd ?>ms" data-num="<?= strtolower($cp['wa_number']) ?>" data-name="<?= strtolower(htmlspecialchars($cp['display_name']??'')) ?>">
            <div class="cp-header"><span class="cp-num"><?= htmlspecialchars($cp['wa_number']) ?></span><span class="cp-since"><?= date('d/m/Y',strtotime($cp['created_at'])) ?></span></div>
            <div>
                <div class="cp-name"><?= htmlspecialchars($cp['display_name']?:'—') ?></div>
                <?php if ($cp['industry']): ?><div class="cp-industry"><i class="fas fa-industry" style="margin-right:4px;font-size:0.65rem;color:var(--text-dim);"></i><?= htmlspecialchars($cp['industry']) ?></div><?php endif; ?>
                <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;">
                    <?php if ($cp['safe_mode']??1): ?><span class="cp-badge-safe"><i class="fas fa-shield-alt"></i> Safe Mode</span><?php else: ?><span class="cp-badge-safe cp-badge-unsafe"><i class="fas fa-unlock"></i> Unsafe</span><?php endif; ?>
                    <?php if (!empty($cp['ai_prompt'])): ?><span class="cp-badge-safe" style="background:rgba(184,255,60,0.07);color:var(--acid);border-color:rgba(184,255,60,0.15);"><i class="fas fa-robot"></i> Custom Prompt</span><?php endif; ?>
                    <?php if (!empty($cp['group_context'])): ?><span class="cp-badge-safe" style="background:rgba(124,92,255,0.07);color:var(--electric);border-color:rgba(124,92,255,0.15);"><i class="fas fa-users"></i> Group Rule</span><?php endif; ?>
                </div>
            </div>
            <div class="cp-fields">
                <?php if ($cp['character']): ?><div class="cp-field"><span class="cp-field-lbl"><i class="fas fa-theater-masks"></i> Karakter</span><span class="cp-field-val"><?= htmlspecialchars($cp['character']) ?></span></div><?php endif; ?>
                <?php if ($cp['topic']): ?><div class="cp-field"><span class="cp-field-lbl"><i class="fas fa-bullseye"></i> Topik</span><span class="cp-field-val"><?= htmlspecialchars($cp['topic']) ?></span></div><?php endif; ?>
                <?php if ($cp['instructions']): ?><div class="cp-field"><span class="cp-field-lbl"><i class="fas fa-tasks"></i> Instruksi</span><span class="cp-field-val"><?= htmlspecialchars($cp['instructions']) ?></span></div><?php endif; ?>
                <?php if (!empty($cp['ai_prompt'])): ?><div class="cp-field"><span class="cp-field-lbl"><i class="fas fa-robot"></i> AI Prompt</span><span class="cp-field-val" style="font-family:'IBM Plex Mono',monospace;font-size:0.68rem;color:var(--acid);"><?= htmlspecialchars(substr($cp['ai_prompt'],0,100)) ?><?= strlen($cp['ai_prompt'])>100?'...':'' ?></span></div><?php endif; ?>
            </div>
            <div class="cp-actions">
                <button class="cp-btn cp-btn-edit" onclick='loadCpEdit(<?= json_encode(["id"=>$cp['id'],"wa_number"=>$cp['wa_number'],"display_name"=>$cp['display_name'],"character"=>$cp['character'],"topic"=>$cp['topic'],"instructions"=>$cp['instructions'],"industry"=>$cp['industry'],"language"=>$cp['language'],"notes"=>$cp['notes'],"safe_mode"=>$cp['safe_mode'],"group_context"=>$cp['group_context'],"ai_prompt"=>$cp['ai_prompt']]) ?>)'><i class="fas fa-edit"></i> Edit</button>
                <button class="cp-btn cp-btn-del" onclick="openDelCp(<?= $cp['id'] ?>,'<?= addslashes(htmlspecialchars($cp['wa_number'])) ?>')"><i class="fas fa-trash-alt"></i> Hapus</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><i class="fas fa-user-slash"></i><h4>Belum ada profil kontak</h4><p>Tambahkan profil pertama di form di atas.</p></div>
    <?php endif; ?>
</div>


<!-- ══════════════════════════════════════════════════════════
     TAB 4: GROUP INTELLIGENCE
     ══════════════════════════════════════════════════════════ -->
<div class="nt-tab-section <?= $active_tab==='groups' ? 'visible' : '' ?>">

    <!-- Group Profile Form -->
    <div class="nt-card warn-accent" id="gpFormCard">
        <div class="card-title"><div class="card-title-icon cti-warn"><i class="fas fa-users-cog"></i></div><span>Konfigurasi Group Intelligence</span></div>
        <div class="help-block warn-help" style="margin-bottom:22px;">
            <strong>Group Context Memory:</strong> Setiap grup punya memori, kepribadian, dan aturan trigger tersendiri — tidak bercampur dengan grup lain.<br>
            <strong>Group ID:</strong> nomor grup WA, contoh <code>628xxxxx-1234567890@g.us</code><br>
            <strong>Perintah WA:</strong> <code>Nebula, konfigurasi grup ini: [instruksi]</code> &nbsp;|&nbsp; <code>Nebula, mode silent</code> &nbsp;|&nbsp; <code>Nebula, mode aktif</code>
        </div>
        <form method="POST">
            <input type="hidden" name="save_group" value="1">
            <input type="hidden" name="edit_group_id" id="editGroupId" value="0">
            <div class="form-grid-3">
                <div class="form-group"><label class="fl">Group ID / WA Group Number <span style="color:var(--danger);">*</span></label><input type="text" name="group_id" id="gpId" class="fi" placeholder="628xxx-12345@g.us"></div>
                <div class="form-group"><label class="fl">Nama Grup</label><input type="text" name="group_name" id="gpName" class="fi" placeholder="Grup Klien A / Tim Sales"></div>
                <div class="form-group"><label class="fl">Tipe Grup</label>
                    <select name="group_type" id="gpType" class="fi fs">
                        <option value="general">General</option><option value="client">Client</option><option value="internal">Internal Team</option><option value="sales">Sales</option><option value="support">Support</option>
                    </select>
                </div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="fl">Kepribadian AI di Grup ini <span class="fl-hint">— Group Personality Config</span></label><textarea name="group_personality" id="gpPersonality" class="fi ft" style="min-height:90px;" placeholder="Formal dan profesional untuk klien enterprise. Hindari emoji berlebihan. Selalu gunakan data dari KB..."></textarea></div>
                <div class="form-group"><label class="fl">System Prompt Tambahan</label><textarea name="group_system_prompt" id="gpSysPrompt" class="fi ft" style="min-height:90px;" placeholder="Instruksi tingkat lanjut yang langsung masuk ke system prompt AI untuk grup ini..."></textarea></div>
            </div>
            <div class="form-grid-3">
                <div class="form-group"><label class="fl">Gaya Bahasa</label><input type="text" name="group_language" id="gpLang" class="fi" placeholder="Formal / Informal / Inggris" value="Informal Bahasa Indonesia"></div>
                <div class="form-group"><label class="fl">Keyword Trigger <span class="fl-hint">— Smart Trigger System</span></label><input type="text" name="group_triggers" id="gpTriggers" class="fi" placeholder="nebula,bot,ai,halo nebula"></div>
                <div class="form-group"><label class="fl">Confidence Threshold <span class="fl-hint">— 0.0–1.0</span></label><input type="number" name="group_conf_thresh" id="gpConf" class="fi" min="0" max="1" step="0.01" value="0.50" placeholder="0.50"></div>
            </div>
            <div class="toggle-grid" style="margin-bottom:18px;">
                <div class="toggle-row"><input type="checkbox" name="group_ai_enabled" id="gpAiEnabled" value="1" checked><label for="gpAiEnabled"><i class="fas fa-robot"></i> AI Aktif di grup ini</label></div>
                <div class="toggle-row warn-toggle"><input type="checkbox" name="group_silent" id="gpSilent" value="1"><label for="gpSilent"><i class="fas fa-bell-slash"></i> Silent Mode — hanya respon saat trigger</label></div>
                <div class="toggle-row"><input type="checkbox" name="group_mention" id="gpMention" value="1"><label for="gpMention"><i class="fas fa-at"></i> Hanya respon jika di-mention</label></div>
                <div class="toggle-row"><input type="checkbox" name="group_anti_hallucinate" id="gpAnti" value="1" checked><label for="gpAnti"><i class="fas fa-shield-alt"></i> Anti-Hallucination Guard aktif</label></div>
            </div>
            <div class="form-group"><label class="fl">Catatan Internal</label><input type="text" name="group_notes" class="fi" placeholder="Catatan tentang grup ini untuk tim admin"></div>
            <div class="form-actions">
                <button type="submit" class="btn-commit warn-btn"><i class="fas fa-save"></i> Simpan Group Config</button>
                <button type="button" class="btn-ghost" onclick="resetGpForm()"><i class="fas fa-times"></i> Reset</button>
            </div>
        </form>
    </div>

    <!-- Group List -->
    <?php if (!empty($group_list)): ?>
    <div class="section-head"><div class="section-title"><i class="fas fa-layer-group" style="color:var(--warning);"></i> Group Profiles <span class="section-badge warn"><?= $total_groups ?> groups</span></div></div>
    <div class="grp-grid" style="margin-bottom:24px;">
        <?php foreach ($group_list as $g): ?>
        <div class="grp-card">
            <div><span class="grp-id-tag"><?= htmlspecialchars($g['group_id']) ?></span></div>
            <div class="grp-name"><?= htmlspecialchars($g['group_name']?:'(no name)') ?></div>
            <div class="grp-badges">
                <span class="grp-badge gb-type"><?= htmlspecialchars($g['group_type']) ?></span>
                <?php if ($g['ai_enabled']): ?><span class="grp-badge gb-ai-on"><i class="fas fa-robot"></i> AI ON</span><?php else: ?><span class="grp-badge gb-ai-off"><i class="fas fa-robot"></i> AI OFF</span><?php endif; ?>
                <?php if ($g['silent_mode']): ?><span class="grp-badge gb-silent"><i class="fas fa-bell-slash"></i> SILENT</span><?php endif; ?>
                <?php if ($g['anti_hallucinate']): ?><span class="grp-badge gb-anti"><i class="fas fa-shield-alt"></i> ANTI-HALLUC</span><?php endif; ?>
                <?php if ($g['mention_only']): ?><span class="grp-badge gb-anti"><i class="fas fa-at"></i> MENTION ONLY</span><?php endif; ?>
            </div>
            <?php if ($g['personality']): ?><div class="grp-fields"><div class="grp-field"><span class="grp-field-lbl">Personality</span><span class="grp-field-val"><?= htmlspecialchars($g['personality']) ?></span></div></div><?php endif; ?>
            <?php if ($g['trigger_keywords']): ?><div class="grp-fields"><div class="grp-field"><span class="grp-field-lbl"><i class="fas fa-bolt"></i> Triggers</span><span class="grp-field-val" style="font-family:'IBM Plex Mono',monospace;font-size:0.68rem;color:var(--acid);"><?= htmlspecialchars($g['trigger_keywords']) ?></span></div></div><?php endif; ?>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:0.58rem;color:var(--text-dim);">Conf. thresh: <?= number_format($g['confidence_thresh']*100) ?>% · Lang: <?= htmlspecialchars($g['language_style']) ?></div>
            <div class="grp-actions">
                <button class="btn-sm elec" onclick='loadGpEdit(<?= json_encode($g) ?>)'><i class="fas fa-edit"></i> Edit</button>
                <a href="?page=training&tab=groups&del_group=<?= $g['id'] ?>" class="btn-sm red" onclick="return confirm('Hapus group profile ini?')"><i class="fas fa-trash-alt"></i> Hapus</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Members -->
    <div class="nt-card warn-accent">
        <div class="card-title"><div class="card-title-icon cti-warn"><i class="fas fa-user-tag"></i></div><span>Group Member Roles — Role Detection System</span></div>
        <div class="help-block warn-help" style="margin-bottom:20px;">
            <strong>Role Detection:</strong> Tentukan siapa yang boleh melatih AI (can_train) atau mengoreksi jawaban (can_correct). Hanya owner/admin yang bisa memberi perintah sistem.<br>
            <strong>Perintah WA:</strong> <code>Nebula, set role 628xxxx admin</code>
        </div>
        <form method="POST">
            <input type="hidden" name="save_member" value="1">
            <div class="form-grid-4">
                <div class="form-group"><label class="fl">Group ID</label><input type="text" name="member_group_id" class="fi" placeholder="ID grup"></div>
                <div class="form-group"><label class="fl">Nomor WA</label><input type="text" name="member_number" class="fi" placeholder="628xxxxxxxxxx"></div>
                <div class="form-group"><label class="fl">Nama</label><input type="text" name="member_name" class="fi" placeholder="Nama anggota"></div>
                <div class="form-group"><label class="fl">Role</label><select name="member_role" class="fi fs"><option value="member">Member</option><option value="admin">Admin</option><option value="owner">Owner</option><option value="bot">Bot</option></select></div>
            </div>
            <div class="toggle-grid" style="margin-bottom:14px;">
                <div class="toggle-row"><input type="checkbox" name="member_can_train" id="mTrain" value="1"><label for="mTrain"><i class="fas fa-graduation-cap"></i> Bisa melatih AI</label></div>
                <div class="toggle-row"><input type="checkbox" name="member_can_correct" id="mCorr" value="1"><label for="mCorr"><i class="fas fa-redo"></i> Bisa mengoreksi jawaban</label></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn-commit warn-btn"><i class="fas fa-user-plus"></i> Tambah Member</button></div>
        </form>

        <?php if (!empty($member_list)): ?>
        <div style="margin-top:24px;">
            <div class="section-title" style="margin-bottom:14px;font-size:0.9rem;"><i class="fas fa-list" style="color:var(--warning);"></i> Daftar Member <span class="section-badge warn"><?= count($member_list) ?></span></div>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Nomor</th><th>Nama</th><th>Grup</th><th>Role</th><th>Bisa Train</th><th>Bisa Koreksi</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($member_list as $m): ?>
                        <tr>
                            <td class="tnum"><?= htmlspecialchars($m['wa_number']) ?></td>
                            <td><?= htmlspecialchars($m['display_name']?:'-') ?></td>
                            <td class="tdim"><?= htmlspecialchars($m['group_name']??$m['group_id']) ?></td>
                            <td><span class="role-tag rt-<?= $m['role'] ?>"><?= strtoupper($m['role']) ?></span></td>
                            <td><?= $m['can_train'] ? '<span style="color:var(--acid)"><i class="fas fa-check"></i></span>' : '<span style="color:var(--text-dim)">—</span>' ?></td>
                            <td><?= $m['can_correct'] ? '<span style="color:var(--teal)"><i class="fas fa-check"></i></span>' : '<span style="color:var(--text-dim)">—</span>' ?></td>
                            <td><a href="?page=training&tab=groups&del_member=<?= $m['id'] ?>" class="btn-sm red" onclick="return confirm('Hapus member?')"><i class="fas fa-trash-alt"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Smart Trigger Rules -->
    <div class="nt-card warn-accent">
        <div class="card-title"><div class="card-title-icon cti-warn"><i class="fas fa-bolt"></i></div><span>Smart Trigger Rules — Notification Filter</span></div>
        <div class="help-block warn-help" style="margin-bottom:20px;">
            <strong>Smart Trigger System:</strong> Tentukan kapan AI boleh merespons. Tambahkan rule agar Nebula hanya bereaksi pada kondisi tertentu dan tetap silent di lainnya.<br>
            <strong>Types:</strong> <code>keyword</code> = trigger jika kata ditemukan &nbsp;|&nbsp; <code>mention</code> = trigger jika di-mention &nbsp;|&nbsp; <code>command</code> = trigger jika perintah eksplisit &nbsp;|&nbsp; <code>regex</code> = pola regex
        </div>
        <form method="POST">
            <input type="hidden" name="save_trigger" value="1">
            <div class="form-grid-4">
                <div class="form-group"><label class="fl">Group ID</label><input type="text" name="tr_group_id" class="fi" placeholder="global atau ID grup"></div>
                <div class="form-group"><label class="fl">Tipe Trigger</label><select name="tr_type" class="fi fs"><option value="keyword">Keyword</option><option value="mention">Mention</option><option value="command">Command</option><option value="regex">Regex</option></select></div>
                <div class="form-group"><label class="fl">Nilai Trigger</label><input type="text" name="tr_value" class="fi" placeholder="halo nebula / @nebula / !ask / ^nebula.*"></div>
                <div class="form-group"><label class="fl">Action</label><select name="tr_action" class="fi fs"><option value="respond">Respond</option><option value="silent">Silent</option><option value="log">Log Only</option><option value="lead">Detect Lead</option><option value="forward">Forward</option></select></div>
            </div>
            <div class="form-grid-2">
                <div class="form-group"><label class="fl">Priority (1=tinggi)</label><input type="number" name="tr_priority" class="fi" value="5" min="1" max="10"></div>
                <div class="form-group"><label class="fl">Catatan</label><input type="text" name="tr_notes" class="fi" placeholder="Keterangan rule ini"></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn-commit warn-btn"><i class="fas fa-plus"></i> Tambah Trigger Rule</button></div>
        </form>

        <?php if (!empty($trigger_list)): ?>
        <div style="margin-top:24px;">
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Grup</th><th>Type</th><th>Trigger</th><th>Action</th><th>Priority</th><th>Catatan</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($trigger_list as $tr): ?>
                        <tr>
                            <td class="tdim"><?= htmlspecialchars($tr['group_id']) ?></td>
                            <td><span class="trigger-type tt-<?= $tr['rule_type'] ?>"><?= $tr['rule_type'] ?></span></td>
                            <td style="font-family:'IBM Plex Mono',monospace;font-size:0.72rem;color:var(--acid);"><?= htmlspecialchars($tr['trigger_val']) ?></td>
                            <td><span class="ac-kw" style="color:var(--teal);"><?= $tr['action'] ?></span></td>
                            <td class="tnum"><?= $tr['priority'] ?></td>
                            <td class="tdim"><?= htmlspecialchars($tr['notes']?:'-') ?></td>
                            <td><a href="?page=training&tab=groups&del_trigger=<?= $tr['id'] ?>" class="btn-sm red" onclick="return confirm('Hapus rule?')"><i class="fas fa-trash-alt"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     TAB 5: AI ANALYTICS
     ══════════════════════════════════════════════════════════ -->
<div class="nt-tab-section <?= $active_tab==='analytics' ? 'visible' : '' ?>">

    <!-- Analytics Summary Cards -->
    <div class="analytics-grid">
        <div class="analytics-card">
            <div class="an-icon" style="background:rgba(184,255,60,0.08);"><i class="fas fa-fire" style="color:var(--acid);"></i></div>
            <div class="an-num" style="color:var(--acid);"><?= $total_leads_new ?></div>
            <div class="an-lbl">New Leads (Auto-Detected)</div>
        </div>
        <div class="analytics-card">
            <div class="an-icon" style="background:rgba(0,229,196,0.08);"><i class="fas fa-question-circle" style="color:var(--teal);"></i></div>
            <div class="an-num" style="color:var(--teal);"><?= $total_faq ?></div>
            <div class="an-lbl">FAQ Disetujui</div>
        </div>
        <div class="analytics-card">
            <div class="an-icon" style="background:rgba(124,92,255,0.08);"><i class="fas fa-brain" style="color:var(--electric);"></i></div>
            <div class="an-num" style="color:var(--electric);"><?= $total_intent ?></div>
            <div class="an-lbl">Intent Terdeteksi (7 hari)</div>
        </div>
        <div class="analytics-card">
            <div class="an-icon" style="background:rgba(255,170,0,0.08);"><i class="fas fa-comments" style="color:var(--warning);"></i></div>
            <div class="an-num" style="color:var(--warning);"><?= $total_sessions ?></div>
            <div class="an-lbl">Session Aktif (24 jam)</div>
        </div>
    </div>

    <!-- Correction Training -->
    <div class="nt-card danger-accent">
        <div class="card-title"><div class="card-title-icon cti-fire"><i class="fas fa-redo"></i></div><span>Correction Training — Perbaiki Jawaban AI</span></div>
        <div class="help-block warn-help" style="margin-bottom:20px;">
            <strong>Correction Training:</strong> Setiap koreksi otomatis diinjeksikan ke Knowledge Base sebagai entry baru, sehingga AI tidak mengulangi kesalahan yang sama.<br>
            <strong>Perintah WA:</strong> <code>Nebula, koreksi: jawaban kamu salah. Yang benar adalah [jawaban yang benar]</code>
        </div>
        <form method="POST">
            <input type="hidden" name="save_correction" value="1">
            <div class="form-grid-2">
                <div class="form-group"><label class="fl">Group ID <span class="fl-hint">— kosong = global</span></label><input type="text" name="corr_group_id" class="fi" placeholder="Kosong = global correction"></div>
                <div class="form-group"><label class="fl">Dikoreksi Oleh</label><input type="text" name="corr_by" class="fi" placeholder="admin / nama admin" value="admin"></div>
            </div>
            <div class="form-group"><label class="fl">Pertanyaan / Konteks Original</label><input type="text" name="corr_question" class="fi" placeholder="Apa harga paket premium?"></div>
            <div class="form-grid-2">
                <div class="form-group"><label class="fl">Jawaban Salah (Opsional)</label><textarea name="corr_wrong" class="fi ft" style="min-height:80px;" placeholder="Jawaban AI yang salah sebelumnya..."></textarea></div>
                <div class="form-group"><label class="fl">Jawaban Benar <span style="color:var(--danger);">*</span></label><textarea name="corr_correct" class="fi ft" style="min-height:80px;" placeholder="Jawaban yang benar dan harus dipelajari Nebula..."></textarea></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn-commit" style="background:var(--grad-fire);color:#fff;"><i class="fas fa-save"></i> Simpan Koreksi & Injek ke KB</button></div>
        </form>

        <?php if (!empty($correction_list)): ?>
        <div style="margin-top:24px;"><div class="section-title" style="margin-bottom:14px;font-size:0.9rem;"><i class="fas fa-history" style="color:var(--danger);"></i> Riwayat Koreksi</div>
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Pertanyaan</th><th>Jawaban Benar</th><th>Oleh</th><th>Applied</th><th>Tanggal</th></tr></thead>
            <tbody><?php foreach ($correction_list as $corr): ?><tr>
                <td><?= htmlspecialchars(substr($corr['original_question'],0,80)) ?><?= strlen($corr['original_question'])>80?'...':'' ?></td>
                <td style="color:var(--acid);"><?= htmlspecialchars(substr($corr['correct_answer'],0,100)) ?><?= strlen($corr['correct_answer'])>100?'...':'' ?></td>
                <td class="tdim"><?= htmlspecialchars($corr['corrected_by']) ?></td>
                <td><?= $corr['applied'] ? '<span style="color:var(--acid)"><i class="fas fa-check"></i> Injected</span>' : '<span style="color:var(--text-dim)">Pending</span>' ?></td>
                <td class="tdim"><?= date('d/m/Y H:i',strtotime($corr['created_at'])) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div></div>
        <?php endif; ?>
    </div>

    <!-- Auto FAQ Generator -->
    <div class="nt-card teal-accent">
        <div class="card-title"><div class="card-title-icon cti-teal"><i class="fas fa-question-circle"></i></div><span>Auto FAQ Generator — Pertanyaan Sering Muncul</span></div>
        <div class="help-block teal-help" style="margin-bottom:20px;">
            <strong>Auto FAQ:</strong> AI secara otomatis mendeteksi pertanyaan yang sering muncul dan menyimpannya di sini. Admin bisa menyetujui atau menghapus FAQ yang terdeteksi.<br>
            <strong>Perintah WA:</strong> <code>Nebula, tampilkan FAQ grup ini</code> &nbsp;|&nbsp; <code>Nebula, generate FAQ dari percakapan minggu ini</code>
        </div>
        <?php if (!empty($faq_list)): ?>
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>#</th><th>Pertanyaan</th><th>Jawaban</th><th>Frekuensi</th><th>Sumber</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($faq_list as $fi => $faq): ?>
                <tr>
                    <td class="tdim"><?= $fi+1 ?></td>
                    <td style="font-weight:700;max-width:220px;"><?= htmlspecialchars(substr($faq['question'],0,100)) ?><?= strlen($faq['question'])>100?'...':'' ?></td>
                    <td style="color:var(--text-mid);max-width:200px;"><?= htmlspecialchars(substr($faq['answer']??'',0,80)) ?><?= strlen($faq['answer']??'')>80?'...':'' ?></td>
                    <td class="tnum" style="color:var(--acid);"><?= $faq['frequency'] ?>×</td>
                    <td><span class="trigger-type <?= $faq['source']==='auto'?'tt-keyword':($faq['source']==='correction'?'tt-command':'tt-mention') ?>"><?= $faq['source'] ?></span></td>
                    <td><?= $faq['is_approved'] ? '<span style="color:var(--acid)"><i class="fas fa-check-circle"></i> Approved</span>' : '<span style="color:var(--text-dim)"><i class="fas fa-clock"></i> Pending</span>' ?></td>
                    <td style="display:flex;gap:5px;">
                        <?php if (!$faq['is_approved']): ?><a href="?page=training&tab=analytics&approve_faq=<?= $faq['id'] ?>" class="btn-sm teal"><i class="fas fa-check"></i></a><?php endif; ?>
                        <a href="?page=training&tab=analytics&del_faq=<?= $faq['id'] ?>" class="btn-sm red" onclick="return confirm('Hapus FAQ?')"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php else: ?>
        <div class="empty-state" style="padding:40px;"><i class="fas fa-question-circle"></i><h4>Belum ada FAQ terdeteksi</h4><p>FAQ akan muncul otomatis setelah Nebula aktif menjawab di grup.</p></div>
        <?php endif; ?>
    </div>

    <!-- Auto Lead Detection -->
    <div class="nt-card elec-accent">
        <div class="card-title"><div class="card-title-icon cti-elec"><i class="fas fa-fire"></i></div><span>Auto Lead Detection — Prospek Terdeteksi AI</span></div>
        <div class="help-block" style="margin-bottom:20px;">
            <strong>Auto Lead Detection:</strong> AI otomatis mendeteksi pesan yang menunjukkan minat membeli dan menyimpannya sebagai prospek. Kelola status prospek di sini untuk tim sales.<br>
            <strong>Intent yang dideteksi:</strong> pertanyaan harga, permintaan penawaran, tanya ketersediaan, dll.
        </div>
        <?php if (!empty($lead_list)): ?>
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Kontak</th><th>Pesan / Intent</th><th>Produk Hint</th><th>Score</th><th>Status</th><th>Detected</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($lead_list as $lead): ?>
                <tr>
                    <td>
                        <div style="font-family:'IBM Plex Mono',monospace;font-size:0.7rem;color:var(--electric);"><?= htmlspecialchars($lead['wa_number']??'-') ?></div>
                        <?php if ($lead['display_name']): ?><div style="font-size:0.72rem;color:var(--text-mid);"><?= htmlspecialchars($lead['display_name']) ?></div><?php endif; ?>
                        <?php if ($lead['group_id']): ?><div class="tdim"><?= htmlspecialchars($lead['group_id']) ?></div><?php endif; ?>
                    </td>
                    <td style="max-width:200px;font-size:0.75rem;"><?= htmlspecialchars(substr($lead['lead_message']??'',0,100)) ?><?= strlen($lead['lead_message']??'')>100?'...':'' ?></td>
                    <td style="color:var(--acid);font-size:0.73rem;"><?= htmlspecialchars($lead['product_hint']??'-') ?></td>
                    <td>
                        <?php $sc = floatval($lead['intent_score']); ?>
                        <span style="font-family:'IBM Plex Mono',monospace;font-size:0.7rem;color:<?= $sc>=0.8?'var(--acid)':($sc>=0.5?'var(--warning)':'var(--danger)') ?>;"><?= number_format($sc*100) ?>%</span>
                        <span class="conf-bar"><span class="conf-fill <?= $sc>=0.8?'cf-high':($sc>=0.5?'cf-mid':'cf-low') ?>" style="width:<?= ($sc*100) ?>%"></span></span>
                    </td>
                    <td><span class="pill-status ps-<?= $lead['status'] ?>"><?= strtoupper($lead['status']) ?></span></td>
                    <td class="tdim"><?= date('d/m H:i',strtotime($lead['detected_at'])) ?></td>
                    <td>
                        <form method="POST" style="display:inline;"><input type="hidden" name="update_lead" value="1"><input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                            <select name="lead_status" class="fi" style="padding:5px 8px;font-size:0.68rem;min-width:0;width:auto;display:inline;" onchange="this.form.submit()">
                                <?php foreach (['new','contacted','qualified','closed','lost'] as $ls): ?><option value="<?= $ls ?>" <?= $lead['status']===$ls?'selected':'' ?>><?= ucfirst($ls) ?></option><?php endforeach; ?>
                            </select>
                        </form>
                        <a href="?page=training&tab=analytics&del_lead=<?= $lead['id'] ?>" class="btn-sm red" onclick="return confirm('Hapus lead?')" style="margin-left:4px;"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php else: ?>
        <div class="empty-state" style="padding:40px;"><i class="fas fa-fire"></i><h4>Belum ada lead terdeteksi</h4><p>Lead akan muncul otomatis saat Nebula mendeteksi intent pembelian di percakapan grup.</p></div>
        <?php endif; ?>
    </div>

    <!-- Intent Detection Logs -->
    <?php if (!empty($intent_list)): ?>
    <div class="nt-card elec-accent">
        <div class="card-title"><div class="card-title-icon cti-elec"><i class="fas fa-brain"></i></div><span>Intent Detection Log — 50 Terakhir</span></div>
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Kontak</th><th>Pesan</th><th>Intent</th><th>Confidence</th><th>Waktu</th></tr></thead>
            <tbody>
                <?php foreach ($intent_list as $il): ?>
                <tr>
                    <td class="tdim"><?= htmlspecialchars($il['display_name']??$il['wa_number']??'-') ?></td>
                    <td style="max-width:200px;font-size:0.75rem;"><?= htmlspecialchars(substr($il['message_text']??'',0,80)) ?><?= strlen($il['message_text']??'')>80?'...':'' ?></td>
                    <td><span class="intent-tag it-<?= $il['intent_type']??'other' ?>"><?= htmlspecialchars($il['intent_type']??'-') ?></span></td>
                    <td class="tnum"><?= number_format(floatval($il['confidence'])*100) ?>%</td>
                    <td class="tdim"><?= date('d/m H:i',strtotime($il['logged_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <!-- AI Summary Generator -->
    <div class="nt-card teal-accent">
        <div class="card-title"><div class="card-title-icon cti-teal"><i class="fas fa-file-alt"></i></div><span>AI Summary Generator — Ringkasan Percakapan Grup</span></div>
        <div class="help-block teal-help" style="margin-bottom:20px;">
            <strong>AI Summary:</strong> Nebula dapat merangkum percakapan grup secara otomatis. Gunakan perintah di grup untuk generate ringkasan.<br>
            <strong>Perintah WA:</strong> <code>Nebula, ringkas percakapan hari ini</code> &nbsp;|&nbsp; <code>Nebula, summary minggu ini</code> &nbsp;|&nbsp; <code>Nebula, rekap meeting tadi</code>
        </div>
        <?php if (!empty($summary_list)): ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($summary_list as $sum): ?>
            <div style="background:rgba(0,229,196,0.03);border:1px solid rgba(0,229,196,0.1);border-radius:var(--r-md);padding:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <div style="font-weight:700;font-size:0.88rem;"><?= htmlspecialchars($sum['period_label']?:'Summary') ?> <span class="tdim" style="font-size:0.65rem;"><?= htmlspecialchars($sum['group_id']??'') ?></span></div>
                    <div class="tdim"><?= date('d/m/Y H:i',strtotime($sum['generated_at'])) ?> · <?= $sum['msg_count'] ?> pesan</div>
                </div>
                <div style="font-size:0.78rem;color:var(--text-mid);line-height:1.7;white-space:pre-wrap;"><?= htmlspecialchars($sum['summary_text']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:40px;"><i class="fas fa-file-alt"></i><h4>Belum ada ringkasan</h4><p>Gunakan perintah WA untuk generate summary percakapan grup.</p></div>
        <?php endif; ?>
    </div>

</div><!-- end analytics -->

</div><!-- end nt-wrap -->

<!-- DELETE MODAL -->
<div class="modal-bg" id="delModal">
    <div class="modal-box">
        <div class="modal-ico"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Konfirmasi Hapus</h3>
        <p id="delModalMsg">Menghapus asset ini akan membuat Nebula melupakan informasi tersebut.</p>
        <div class="modal-btns"><button class="modal-btn-cn" onclick="closeDel()">Batal</button><button class="modal-btn-ok" onclick="confirmDel()"><i class="fas fa-trash-alt"></i> Hapus</button></div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-deck" id="toastDeck"></div>

<script>
/* ── STATE ── */
let kwTags = [];
let delId = null, delType = 'knowledge';

/* ── INIT ── */
document.addEventListener('DOMContentLoaded', () => {
    const ta = document.getElementById('contentIn');
    const cc = document.getElementById('charCnt');
    if (ta && cc) { const upd = () => cc.textContent = ta.value.length.toLocaleString(); ta.addEventListener('input', upd); upd(); }

    const hidKw = document.getElementById('kwHidden')?.value;
    if (hidKw) hidKw.split(',').map(s=>s.trim()).filter(Boolean).forEach(addKwTag);

    const ki = document.getElementById('kwRaw');
    if (ki) {
        ki.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); commitKwInput(); }
            if (e.key === 'Backspace' && ki.value === '' && kwTags.length > 0) removeKwTag(kwTags.length-1);
        });
        ki.addEventListener('input', e => {
            if (e.target.value.includes(',')) {
                e.target.value.split(',').forEach(s => { if(s.trim()) addKwTag(s.trim()); });
                e.target.value = ''; syncKw();
            }
        });
        ki.addEventListener('blur', commitKwInput);
    }

    const sf = document.getElementById('sheetForm');
    if (sf) sf.addEventListener('submit', () => {
        const btn = document.getElementById('sheetBtn');
        if (btn) { btn.classList.add('loading'); btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Membaca data...</span>'; }
    });

    <?php if ($status_msg === 'added'):   ?> showToast('ok',   'Berhasil!',   '<?= addslashes($status_text) ?>'); <?php endif; ?>
    <?php if ($status_msg === 'updated'): ?> showToast('info', 'Diperbarui!', '<?= addslashes($status_text) ?>'); <?php endif; ?>
    <?php if ($status_msg === 'deleted'): ?> showToast('ok',   'Dihapus.',    '<?= addslashes($status_text) ?>'); <?php endif; ?>
    <?php if ($status_msg === 'error'):   ?> showToast('err',  'Error!',      '<?= addslashes($status_text) ?>'); <?php endif; ?>

    <?php if ($edit_data):    ?> setKwEditMode(true); <?php endif; ?>
    <?php if ($cp_edit_data): ?> setCpEditMode(true); <?php endif; ?>
});

/* ── KNOWLEDGE ── */
function handleKwSubmit(e) {
    commitKwInput(); syncKw(); let ok = true;
    ['topicIn','contentIn'].forEach(id => {
        const el = document.getElementById(id);
        if (!el || !el.value.trim()) { el?.classList.add('invalid'); shakeEl(id); ok = false; }
        else el.classList.remove('invalid');
    });
    if (!document.getElementById('kwHidden').value.trim()) {
        shakeEl('kwShell'); document.getElementById('kwShell').style.borderColor='var(--danger)';
        setTimeout(()=>document.getElementById('kwShell').style.borderColor='',2000); ok=false;
    }
    if (!ok) { showToast('err','Validasi Gagal','Lengkapi semua field.'); return false; }
    const btn = document.getElementById('kwSubmitBtn');
    btn.classList.add('loading'); btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Committing...</span>';
    return true;
}
function addKwTag(t) { t=t.trim().toLowerCase(); if(!t||kwTags.includes(t))return; kwTags.push(t); renderKwTags(); syncKw(); }
function removeKwTag(i) { kwTags.splice(i,1); renderKwTags(); syncKw(); }
function renderKwTags() {
    const shell=document.getElementById('kwShell'), ki=document.getElementById('kwRaw');
    if (!shell) return;
    shell.querySelectorAll('.kw-tag').forEach(t=>t.remove());
    kwTags.forEach((t,i) => {
        const el=document.createElement('span'); el.className='kw-tag';
        el.innerHTML=`${t} <span class="kw-rm" onclick="removeKwTag(${i})"><i class="fas fa-times"></i></span>`;
        shell.insertBefore(el,ki);
    });
}
function syncKw() { const h=document.getElementById('kwHidden'); if(h) h.value=kwTags.join(', '); }
function commitKwInput() { const ki=document.getElementById('kwRaw'); if(!ki)return; const v=ki.value.trim(); if(v){addKwTag(v);ki.value='';} }

function loadKwEdit(d) {
    document.getElementById('editIdHidden').value=d.id;
    document.getElementById('topicIn').value=d.topic;
    document.getElementById('catIn').value=d.category;
    document.getElementById('contentIn').value=d.content;
    document.getElementById('charCnt').textContent=d.content.length.toLocaleString();
    const tagsEl = document.querySelector('input[name="kb_tags"]');
    if (tagsEl) tagsEl.value = d.tags||'';
    const gidEl = document.querySelector('input[name="kb_group_id"]');
    if (gidEl) gidEl.value = d.group_id||'';
    const confEl = document.querySelector('input[name="confidence_score"]');
    if (confEl) confEl.value = d.confidence_score||1;
    kwTags=[]; renderKwTags();
    if(d.keywords) d.keywords.split(',').map(s=>s.trim()).filter(Boolean).forEach(addKwTag);
    syncKw(); setKwEditMode(true);
    document.getElementById('formCard').scrollIntoView({behavior:'smooth',block:'start'});
}
function setKwEditMode(on) {
    const card=document.getElementById('formCard'), title=document.getElementById('formTitle');
    const btn=document.getElementById('kwSubmitBtn'), cancel=document.getElementById('kwCancelBtn');
    if (!card) return;
    if(on) {
        card.classList.add('edit-mode');
        title.innerHTML='<div class="card-title-icon cti-elec"><i class="fas fa-sync-alt"></i></div><span>Edit Knowledge Asset</span>';
        btn.className='btn-commit elec-btn'; document.getElementById('kwSubmitLbl').textContent='Sync Configuration'; cancel.style.display='flex';
    } else {
        card.classList.remove('edit-mode');
        title.innerHTML='<div class="card-title-icon cti-acid"><i class="fas fa-plus"></i></div><span>Inject New Knowledge Asset</span>';
        btn.className='btn-commit'; document.getElementById('kwSubmitLbl').textContent='Commit to Neural Core'; cancel.style.display='none';
    }
}
function resetKwForm() {
    document.getElementById('editIdHidden').value='0';
    ['topicIn','contentIn'].forEach(id=>{const e=document.getElementById(id);if(e){e.value='';e.classList.remove('invalid');}});
    document.getElementById('catIn').value='Product Knowledge';
    document.getElementById('charCnt').textContent='0';
    const tagsEl=document.querySelector('input[name="kb_tags"]'); if(tagsEl) tagsEl.value='';
    const gidEl=document.querySelector('input[name="kb_group_id"]'); if(gidEl) gidEl.value='';
    const confEl=document.querySelector('input[name="confidence_score"]'); if(confEl) confEl.value='1.00';
    kwTags=[]; renderKwTags(); syncKw(); setKwEditMode(false);
}

/* ── CONTACT ── */
function loadCpEdit(d) {
    document.getElementById('cpEditId').value=d.id;
    document.getElementById('cpNum').value=d.wa_number;
    document.getElementById('cpName').value=d.display_name||'';
    document.getElementById('cpChar').value=d.character||'';
    document.getElementById('cpTopic').value=d.topic||'';
    document.getElementById('cpInst').value=d.instructions||'';
    document.getElementById('cpIndustry').value=d.industry||'';
    document.getElementById('cpLang').value=d.language||'';
    document.getElementById('cpNotes').value=d.notes||'';
    document.getElementById('cpSafeMode').checked=!!d.safe_mode;
    document.getElementById('cpGrpCtx').value=d.group_context||'';
    document.getElementById('cpAiPrompt').value=d.ai_prompt||'';
    setCpEditMode(true);
    document.getElementById('cpFormCard').scrollIntoView({behavior:'smooth',block:'start'});
}
function setCpEditMode(on) {
    const title=document.getElementById('cpFormTitle'), btn=document.getElementById('cpSubmitBtn'), cancel=document.getElementById('cpCancelBtn');
    if (!title) return;
    if(on) { title.innerHTML='<div class="card-title-icon cti-elec"><i class="fas fa-edit"></i></div><span>Edit Profil Kontak</span>'; document.getElementById('cpSubmitLbl').textContent='Update Profil'; cancel.style.display='flex'; }
    else { title.innerHTML='<div class="card-title-icon cti-elec"><i class="fas fa-user-plus"></i></div><span>Training Profil Kontak Baru</span>'; document.getElementById('cpSubmitLbl').textContent='Simpan Profil Kontak'; cancel.style.display='none'; }
}
function resetCpForm() {
    document.getElementById('cpEditId').value='0';
    ['cpNum','cpName','cpChar','cpTopic','cpInst','cpIndustry','cpLang','cpNotes','cpGrpCtx','cpAiPrompt'].forEach(id=>{const e=document.getElementById(id);if(e)e.value='';});
    document.getElementById('cpSafeMode').checked=true;
    setCpEditMode(false);
}

/* ── GROUP ── */
function loadGpEdit(d) {
    document.getElementById('editGroupId').value=d.id;
    document.getElementById('gpId').value=d.group_id||'';
    document.getElementById('gpName').value=d.group_name||'';
    document.getElementById('gpType').value=d.group_type||'general';
    document.getElementById('gpPersonality').value=d.personality||'';
    document.getElementById('gpSysPrompt').value=d.system_prompt||'';
    document.getElementById('gpLang').value=d.language_style||'';
    document.getElementById('gpTriggers').value=d.trigger_keywords||'';
    document.getElementById('gpConf').value=d.confidence_thresh||0.5;
    document.getElementById('gpAiEnabled').checked=!!d.ai_enabled;
    document.getElementById('gpSilent').checked=!!d.silent_mode;
    document.getElementById('gpMention').checked=!!d.mention_only;
    document.getElementById('gpAnti').checked=!!d.anti_hallucinate;
    document.querySelector('input[name="group_notes"]').value=d.notes||'';
    document.getElementById('gpFormCard').scrollIntoView({behavior:'smooth',block:'start'});
}
function resetGpForm() {
    document.getElementById('editGroupId').value='0';
    ['gpId','gpName','gpPersonality','gpSysPrompt','gpLang','gpTriggers'].forEach(id=>{const e=document.getElementById(id);if(e)e.value='';});
    document.getElementById('gpType').value='general';
    document.getElementById('gpConf').value='0.50';
    document.getElementById('gpAiEnabled').checked=true;
    document.getElementById('gpSilent').checked=false;
    document.getElementById('gpMention').checked=false;
    document.getElementById('gpAnti').checked=true;
    document.querySelector('input[name="group_notes"]').value='';
}

/* ── SEARCH ── */
function liveSearch(q) {
    q=q.toLowerCase().trim();
    const cards=document.querySelectorAll('.asset-card'); let shown=0;
    cards.forEach(c => { const match=!q||(c.dataset.topic+' '+c.dataset.content+' '+c.dataset.kws).includes(q); c.style.display=match?'':'none'; if(match) shown++; });
    const rc=document.getElementById('repoCount'); if(rc) rc.textContent=shown+' assets';
    let emp=document.getElementById('lsEmpty');
    if(shown===0&&!emp) { emp=document.createElement('div'); emp.id='lsEmpty'; emp.className='empty-state'; emp.style.gridColumn='1/-1'; emp.innerHTML=`<i class="fas fa-search"></i><h4>Tidak ditemukan</h4><p>Tidak ada hasil untuk "${q}"</p>`; document.getElementById('assetGrid')?.appendChild(emp); }
    else if(emp&&shown>0) emp.remove();
}
function cpSearch(q) {
    q=q.toLowerCase().trim();
    document.querySelectorAll('.cp-card').forEach(c=>{ const match=!q||(c.dataset.num+' '+c.dataset.name).includes(q); c.style.display=match?'':'none'; });
}

/* ── SPREADSHEET ── */
function downloadSheetTxt() {
    const txt=document.getElementById('sheetRawTxt')?.value||'';
    const a=document.createElement('a'), blob=new Blob([txt],{type:'text/plain;charset=utf-8'});
    a.href=URL.createObjectURL(blob); a.download='nebula_spreadsheet_'+Date.now()+'.txt'; a.click();
    showToast('ok','Download dimulai','File .txt sedang diunduh...');
}
function copySheetData() {
    const txt=document.getElementById('sheetRawTxt')?.value||'';
    navigator.clipboard.writeText(txt).then(()=>showToast('ok','Disalin!','Data berhasil disalin ke clipboard.'));
}

/* ── DELETE MODAL ── */
function openDel(id, name) { delId=id; delType='knowledge'; document.getElementById('delModalMsg').innerHTML=`Hapus asset "<strong>${name}</strong>"? Nebula akan melupakan informasi ini secara global.`; document.getElementById('delModal').classList.add('open'); }
function openDelCp(id, num) { delId=id; delType='contact'; document.getElementById('delModalMsg').innerHTML=`Hapus profil kontak <strong>${num}</strong>? Nebula akan kembali ke mode standar.`; document.getElementById('delModal').classList.add('open'); }
function closeDel() { document.getElementById('delModal').classList.remove('open'); delId=null; }
function confirmDel() {
    if(!delId) return;
    if(delType==='knowledge') { const card=document.getElementById('card-'+delId); if(card) card.classList.add('dying'); closeDel(); setTimeout(()=>window.location.href=`?page=training&tab=knowledge&delete_id=${delId}`,320); }
    else { const card=document.getElementById('cpcard-'+delId); if(card) card.classList.add('dying'); closeDel(); setTimeout(()=>window.location.href=`?page=training&tab=contacts&del_contact=${delId}`,320); }
}
document.getElementById('delModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closeDel();});

/* ── TOAST ── */
function showToast(type, title, msg) {
    const icons={ok:'fas fa-check',err:'fas fa-times',warn:'fas fa-exclamation-triangle',info:'fas fa-info'};
    const deck=document.getElementById('toastDeck');
    const t=document.createElement('div'); t.className=`toast ${type}`;
    t.innerHTML=`<div class="toast-ico"><i class="${icons[type]||icons.info}"></i></div><div class="toast-body"><div class="toast-ttl">${title}</div><div class="toast-msg">${msg}</div></div><button class="toast-x" onclick="killToast(this.parentElement)"><i class="fas fa-times"></i></button>`;
    deck.appendChild(t);
    setTimeout(()=>killToast(t), type==='err'?5600:type==='warn'?6600:4100);
}
function killToast(el) { if(!el||!el.parentElement) return; el.classList.add('out'); setTimeout(()=>el?.parentElement?.removeChild(el),310); }

/* ── SHAKE ── */
const _s=document.createElement('style'); _s.textContent=`@keyframes shX{0%,100%{transform:translateX(0)}20%{transform:translateX(-7px)}40%{transform:translateX(7px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}`; document.head.appendChild(_s);
function shakeEl(id) { const el=document.getElementById(id); if(!el) return; el.style.animation='none'; el.offsetHeight; el.style.animation='shX 0.4s ease'; }
</script>
</body>
</html>
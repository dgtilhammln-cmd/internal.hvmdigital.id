<?php
/* ==========================================================================
   TITAN QUEUE MANAGER V7.0 - SMART COOLDOWN ENGINE
   - Auto-Detect Server Limits (Hostinger Ban Protection)
   - Intelligent Cooldown (Sleeps for 1 Hour if Server Blocks)
   - Atomic Processing & Zero-Collision
   ========================================================================== */

ignore_user_abort(true);
set_time_limit(180);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mailer_engine.php';

date_default_timezone_set('Asia/Jakarta');
mysqli_query($conn, "SET time_zone = '+07:00'");

// 1. FETCH CONFIGURATION
$res_cfg = mysqli_query($conn, "SELECT * FROM email_settings");
$cfg =[];
while($r = mysqli_fetch_assoc($res_cfg)) { $cfg[$r['key_name']] = $r['key_value']; }

$sys_status     = $cfg['system_status']   ?? 'ON';
$work_start     = $cfg['work_hour_start'] ?? '00:00';
$work_end       = $cfg['work_hour_end']   ?? '23:59';
$weekend        = $cfg['send_on_weekend'] ?? 'yes';
$cooldown_until = $cfg['cooldown_until']  ?? '2020-01-01 00:00:00';

// 2. COOLDOWN PROTECTION (Fitur Penyelamat Domain)
$now_full = date('Y-m-d H:i:s');
if ($now_full < $cooldown_until) {
    die("🛡️ [COOLDOWN ACTIVE] Server Hostinger sedang memblokir pengiriman. Mesin diistirahatkan hingga: $cooldown_until WIB.");
}

// 3. GLOBAL GUARDS
if ($sys_status !== 'ON') die("💤 [SYSTEM_OFF] Engine paused via Dashboard.");

$now_time = date('H:i');
$is_within_hours = false;

if ($work_start <= $work_end) {
    if ($now_time >= $work_start && $now_time <= $work_end) $is_within_hours = true;
} else {
    if ($now_time >= $work_start || $now_time <= $work_end) $is_within_hours = true;
}

if (!$is_within_hours) die("💤 [OUT_OF_HOURS] Time: $now_time. Resumes at $work_start.");
if ($weekend === 'no' && date('N') > 5) die("💤[WEEKEND_REST] System follows strictly weekdays.");

// 4. ATOMIC DATA FETCHING
mysqli_begin_transaction($conn);

$sql = "SELECT q.id, q.campaign_id, q.contact_id, c.subject, c.body_content, con.email, con.name 
        FROM email_queue q
        JOIN email_campaigns c ON q.campaign_id = c.id
        JOIN email_contacts con ON q.contact_id = con.id
        WHERE q.status = 'pending' 
        AND q.scheduled_at <= NOW() 
        ORDER BY q.scheduled_at ASC 
        LIMIT 1 FOR UPDATE"; 

$res = mysqli_query($conn, $sql);
$job = mysqli_fetch_assoc($res);

if (!$job) {
    mysqli_commit($conn);
    die("💤 [EMPTY] No pending tasks for " . date('H:i:s'));
}

$qid = $job['id'];
mysqli_query($conn, "UPDATE email_queue SET status = 'processing' WHERE id = $qid");
mysqli_commit($conn); 

// 5. EXECUTION & AUTO-COOLDOWN TRIGGER
$send = sendTitanEmail($job['email'], $job['name'], $job['subject'], $job['body_content'], $qid);

if ($send['status']) {
    mysqli_query($conn, "UPDATE email_queue SET sent_at = NOW(), status = 'sent' WHERE id = $qid");
    echo "🚀 [SUCCESS] Delivered via " . ($send['method'] ?? 'SYS') . " to: " . $job['email'] . " [" . date('H:i:s') . "]";
} else {
    $error_msg = mysqli_real_escape_string($conn, $send['msg']);
    
    // JIKA HOSTINGER MEMBLOKIR (Instantiate / Authenticate error)
    if (stripos($error_msg, 'instantiate') !== false || stripos($error_msg, 'authenticate') !== false || stripos($error_msg, 'Firewall') !== false) {
        
        // Kembalikan status email menjadi pending agar bisa dikirim lagi nanti
        mysqli_query($conn, "UPDATE email_queue SET status = 'pending', attempts = attempts + 1 WHERE id = $qid");
        
        // Aktifkan mode Cooldown selama 1 Jam ke depan
        $next_hour = date('Y-m-d H:i:s', strtotime('+1 hour'));
        mysqli_query($conn, "UPDATE email_settings SET key_value = '$next_hour' WHERE key_name = 'cooldown_until'");
        
        echo "⛔ [SERVER BLOCKED] Hostinger limit reached. Email returned to queue. Engine cooling down until $next_hour.";
    } else {
        // Jika error biasa (email tidak valid, dsb)
        mysqli_query($conn, "UPDATE email_queue SET status = 'failed', error_msg = '$error_msg', attempts = attempts + 1 WHERE id = $qid");
        echo "❌ [FAILED] Target: " . $job['email'] . " | Error: " . $send['msg'];
    }
}
?>
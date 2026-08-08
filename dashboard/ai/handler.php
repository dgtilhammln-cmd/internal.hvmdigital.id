<?php
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
if(!isset($_SESSION['admin']) || ($_SESSION['role'] ?? '') !== 'super_admin'){
    http_response_code(403); echo json_encode(['error'=>'Akses ditolak. Hanya Super Admin.']); exit;
}

header('Content-Type: application/json');
set_time_limit(120); // Prevent PHP from timing out before cURL
error_reporting(E_ALL);
ini_set('display_errors', 1);

$action = $_POST['action'] ?? '';

// Load AI settings from DB
function getAISetting($conn, $key, $default='') {
    $k = mysqli_real_escape_string($conn, $key);
    $q = mysqli_query($conn, "SELECT setting_value FROM ai_settings WHERE setting_key='$k'");
    if($q && mysqli_num_rows($q) > 0) return mysqli_fetch_assoc($q)['setting_value'];
    return $default;
}

// ─── TEST CONNECTION ────────────────────────────────────────
if($action === 'test') {
    $key      = trim($_POST['key'] ?? '');
    $provider = trim($_POST['provider'] ?? 'openai');
    $model    = trim($_POST['model'] ?? 'gpt-4o');
    $result   = callAI($provider, $key, $model, [['role'=>'user','content'=>'Reply with exactly: OK']], 'You are a test bot.');
    if(isset($result['error'])) echo json_encode(['ok'=>false,'error'=>$result['error']]);
    else echo json_encode(['ok'=>true,'model'=>$model]);
    exit;
}

// ─── CHAT ───────────────────────────────────────────────────
if($action === 'chat') {
    $message  = trim($_POST['message'] ?? '');
    if(!$message) { echo json_encode(['error'=>'Pesan kosong']); exit; }

    $provider = getAISetting($conn, 'ai_provider', 'openai');
    $api_key  = getAISetting($conn, 'ai_api_key', '');
    $model    = getAISetting($conn, 'ai_model', 'gpt-4o');
    $ai_name  = getAISetting($conn, 'ai_name', 'Asisten HVM');
    $persona  = getAISetting($conn, 'ai_persona', '');

    if(!$api_key) { echo json_encode(['error'=>'API Key belum dikonfigurasi. Buka /dashboard/settings untuk mengatur.']); exit; }

    // ─── FETCH ALL COMPANY DATA ────────────────────────────
    $context_parts = [];
    $today = date('Y-m-d');
    $user  = $_SESSION['admin'];

    // Helper for compressed CSV context
    function getTableAsCSV($conn, $table, $allowed_cols, $title, $orderByLimit) {
        $q_cols = mysqli_query($conn, "SHOW COLUMNS FROM $table");
        if(!$q_cols) return "";
        $avail = [];
        while($r = mysqli_fetch_assoc($q_cols)) $avail[] = $r['Field'];
        
        $select_cols = array_intersect($allowed_cols, $avail);
        if(empty($select_cols)) return "";
        $select_str = implode(",", $select_cols);
        
        $q = mysqli_query($conn, "SELECT $select_str FROM $table $orderByLimit");
        if(!$q || mysqli_num_rows($q) == 0) return "";
        
        $csv = "=== $title ===\n";
        $first = true;
        while($r = mysqli_fetch_assoc($q)) {
            if($first) {
                $csv .= implode("|", array_keys($r)) . "\n";
                $first = false;
            }
            $csv .= implode("|", array_map(function($val) {
                $s = str_replace(["\n","\r","|"], [" "," ","/"], (string)$val);
                // Truncate only very long texts to protect tokens, 300 is enough for JSON arrays
                return mb_strlen($s) > 300 ? mb_substr($s, 0, 297) . '...' : $s;
            }, array_values($r))) . "\n";
        }
        return $csv;
    }

    $context_parts[] = getTableAsCSV($conn, "events", ['event_date', 'title', 'time_start', 'meeting_mode', 'location', 'target_name', 'teams_involved', 'status', 'log_hasil', 'detail'], "DATA MEETING / EVENTS", "ORDER BY event_date DESC LIMIT 30");
    $context_parts[] = getTableAsCSV($conn, "teams", ['name', 'nama', 'position', 'posisi', 'role', 'whatsapp', 'email', 'domicile'], "DATA TEAM", "LIMIT 30");
    $context_parts[] = getTableAsCSV($conn, "clients", ['company_name', 'name', 'pic', 'whatsapp', 'status', 'city', 'domain', 'services_data', 'service'], "DATA CLIENTS", "LIMIT 50");
    $context_parts[] = getTableAsCSV($conn, "prospects", ['company_name', 'name', 'pic', 'status', 'city', 'deal_status', 'notes'], "DATA PROSPECTS", "ORDER BY id DESC LIMIT 50");
    $context_parts[] = getTableAsCSV($conn, "invoices", ['inv_no', 'client_name', 'service_label', 'inv_date', 'total', 'status'], "DATA INVOICE", "ORDER BY inv_date DESC LIMIT 30");
    $context_parts[] = getTableAsCSV($conn, "payments", ['payment_date', 'company_name', 'amount', 'service_type', 'payment_type', 'invoice_no'], "PEMASUKAN / PAYMENTS", "ORDER BY payment_date DESC LIMIT 30");
    $context_parts[] = getTableAsCSV($conn, "spendings", ['type', 'vendor', 'detail', 'amount', 'payment_date', 'date'], "PENGELUARAN / SPENDINGS", "ORDER BY id DESC LIMIT 30");

    $context_parts = array_filter($context_parts);

    $db_context = implode("\n\n", $context_parts);
    $persona_extra = $persona ? "\n\nInstruksi tambahan: $persona" : '';

    $system_prompt = "Kamu adalah {$ai_name}, asisten cerdas internal perusahaan HVM Digital. Hari ini tanggal {$today}. User yang sedang login: {$user}.

Tugasmu adalah membantu tim HVM Digital dengan pertanyaan seputar data operasional perusahaan (termasuk jadwal meeting, data klien, prospek, tim, keuangan masuk/keluar, dan invoice).

Sifatmu: ramah, profesional, ringkas, pakai Bahasa Indonesia. Jika diminta summary, buat yang mudah dicerna.{$persona_extra}

Berikut adalah data aktual dari database sistem internal HVM Digital (per hari ini):

{$db_context}

Gunakan data di atas untuk menjawab pertanyaan user dengan akurat. Jika data tidak ada, jawab jujur.";

    // Get history from POST if any
    $history = json_decode($_POST['history'] ?? '[]', true) ?: [];
    $messages = [];
    foreach($history as $h) {
        if(isset($h['role']) && isset($h['content'])) {
            $messages[] = ['role'=>$h['role'], 'content'=>$h['content']];
        }
    }
    $messages[] = ['role'=>'user', 'content'=>$message];

    $result = callAI($provider, $api_key, $model, $messages, $system_prompt);

    if(isset($result['error'])) {
        echo json_encode(['error'=>$result['error']], JSON_INVALID_UTF8_SUBSTITUTE);
    } else {
        echo json_encode(['reply'=>$result['content'], 'model'=>$model, 'name'=>$ai_name], JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

echo json_encode(['error'=>'Invalid action']);
exit;

// ─── UNIVERSAL AI CALLER ─────────────────────────────────────
function callAI($provider, $api_key, $model, $messages, $system_prompt) {
    // OpenAI-compatible providers (OpenAI + Groq)
    if($provider === 'openai' || $provider === 'groq') {
        $base_url = ($provider === 'groq')
            ? 'https://api.groq.com/openai/v1/chat/completions'
            : 'https://api.openai.com/v1/chat/completions';
        $payload = [
            'model'    => $model,
            'messages' => array_merge([['role'=>'system','content'=>$system_prompt]], $messages),
            'max_tokens' => 1200,
            'temperature' => 0.7
        ];
        $resp = httpPost($base_url, json_encode($payload), [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ]);
        $d = json_decode($resp, true);
        if(!$d) return ['error' => 'Invalid API response dari Groq/OpenAI: ' . substr($resp, 0, 150)];
        if(isset($d['error'])) return ['error' => $d['error']['message'] ?? ($provider === 'groq' ? 'Groq error' : 'OpenAI error')];
        return ['content' => $d['choices'][0]['message']['content'] ?? ''];

    } elseif($provider === 'gemini') {
        $parts = [['text' => $system_prompt . "\n\n---\n\nUser: " . end($messages)['content']]];
        // Build conversation for Gemini
        $gemini_contents = [];
        foreach($messages as $m) {
            $role = ($m['role']==='assistant') ? 'model' : 'user';
            $gemini_contents[] = ['role'=>$role, 'parts'=>[['text'=>$m['content']]]];
        }
        $payload = [
            'system_instruction' => ['parts'=>[['text'=>$system_prompt]]],
            'contents'           => $gemini_contents,
            'generationConfig'   => ['maxOutputTokens'=>1200, 'temperature'=>0.7]
        ];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
        $resp = httpPost($url, json_encode($payload), ['Content-Type: application/json']);
        $d = json_decode($resp, true);
        if(isset($d['error'])) return ['error' => $d['error']['message'] ?? 'Gemini error'];
        return ['content' => $d['candidates'][0]['content']['parts'][0]['text'] ?? ''];

    } elseif($provider === 'anthropic') {
        $payload = [
            'model'      => $model,
            'max_tokens' => 1200,
            'system'     => $system_prompt,
            'messages'   => $messages
        ];
        $resp = httpPost('https://api.anthropic.com/v1/messages', json_encode($payload), [
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json'
        ]);
        $d = json_decode($resp, true);
        if(isset($d['error'])) return ['error' => $d['error']['message'] ?? 'Anthropic error'];
        return ['content' => $d['content'][0]['text'] ?? ''];
    }
    return ['error' => 'Provider tidak dikenal'];
}

function httpPost($url, $body, $headers) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($ch);
    if(curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return json_encode(['error' => ['message' => 'Koneksi API Gagal/Timeout: ' . $err]]);
    }
    curl_close($ch);
    return $resp;
}

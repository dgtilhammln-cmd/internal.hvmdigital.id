<?php
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
if(!isset($_SESSION['admin']) || ($_SESSION['role'] ?? '') !== 'super_admin'){
    http_response_code(403); echo json_encode(['error'=>'Akses ditolak. Hanya Super Admin.']); exit;
}

header('Content-Type: application/json');

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

    // Meetings / Events
    $chk = mysqli_query($conn, "SHOW TABLES LIKE 'events'");
    if($chk && mysqli_num_rows($chk) > 0) {
        $q_ev = mysqli_query($conn, "SELECT title, event_date, time_start, meeting_type, meeting_mode, location, target_name, target_type, teams_involved, log_hasil, status FROM events ORDER BY event_date DESC LIMIT 50");
        if($q_ev && mysqli_num_rows($q_ev) > 0) {
            $evList = [];
            while($r = mysqli_fetch_assoc($q_ev)) {
                $evList[] = sprintf("- [%s] %s | %s %s | Mode: %s | Lokasi: %s | Target: %s (%s) | Tim: %s | Status: %s | Log: %s",
                    $r['event_date'], $r['title'], $r['time_start']??'', $r['meeting_type']??'',
                    $r['meeting_mode']??'-', $r['location']??'-',
                    $r['target_name']??'-', $r['target_type']??'-',
                    $r['teams_involved']??'-', $r['status']??'-',
                    $r['log_hasil']??'Belum ada log'
                );
            }
            $context_parts[] = "=== DATA MEETING / EVENTS (50 terbaru) ===\n" . implode("\n", $evList);
        }
    }

    // Teams
    $chk2 = mysqli_query($conn, "SHOW TABLES LIKE 'teams'");
    if($chk2 && mysqli_num_rows($chk2) > 0) {
        $q_tm = mysqli_query($conn, "SELECT name, position, role, whatsapp, email, domicile FROM teams ORDER BY name ASC");
        if($q_tm && mysqli_num_rows($q_tm) > 0) {
            $tmList = [];
            while($r = mysqli_fetch_assoc($q_tm)) {
                $tmList[] = sprintf("- %s | Posisi: %s | Role: %s | WA: %s | Email: %s | Domisili: %s",
                    $r['name'], $r['position'], $r['role'], $r['whatsapp'], $r['email'], $r['domicile']??'-'
                );
            }
            $context_parts[] = "=== DATA TEAM ===\n" . implode("\n", $tmList);
        }
    }

    // Clients
    $chk3 = mysqli_query($conn, "SHOW TABLES LIKE 'clients'");
    if($chk3 && mysqli_num_rows($chk3) > 0) {
        $q_cl = mysqli_query($conn, "SELECT company_name, pic, pic_position, whatsapp, status, city, domain FROM clients ORDER BY company_name ASC LIMIT 100");
        if($q_cl && mysqli_num_rows($q_cl) > 0) {
            $clList = [];
            while($r = mysqli_fetch_assoc($q_cl)) {
                $clList[] = sprintf("- %s | PIC: %s (%s) | WA: %s | Status: %s | Kota: %s | Domain: %s",
                    $r['company_name'], $r['pic']??'-', $r['pic_position']??'-',
                    $r['whatsapp']??'-', $r['status']??'-', $r['city']??'-', $r['domain']??'-'
                );
            }
            $context_parts[] = "=== DATA CLIENTS (100 terbaru) ===\n" . implode("\n", $clList);
        }
    }

    // Prospects
    $chk4 = mysqli_query($conn, "SHOW TABLES LIKE 'prospects'");
    if($chk4 && mysqli_num_rows($chk4) > 0) {
        $cols = mysqli_query($conn, "SHOW COLUMNS FROM prospects LIKE 'deal_status'");
        $deal_col = ($cols && mysqli_num_rows($cols) > 0) ? ', deal_status' : '';
        $q_pr = mysqli_query($conn, "SELECT company_name, pic, status, city$deal_col FROM prospects ORDER BY id DESC LIMIT 100");
        if($q_pr && mysqli_num_rows($q_pr) > 0) {
            $prList = [];
            while($r = mysqli_fetch_assoc($q_pr)) {
                $deal = isset($r['deal_status']) ? ' | Deal Status: '.($r['deal_status']?:'Belum Ditentukan') : '';
                $prList[] = sprintf("- %s | PIC: %s | Pipeline: %s | Kota: %s%s",
                    $r['company_name'], $r['pic']??'-', $r['status']??'-', $r['city']??'-', $deal
                );
            }
            $context_parts[] = "=== DATA PROSPECTS (100 terbaru) ===\n" . implode("\n", $prList);
        }
    }

    // Invoices
    $chk5 = mysqli_query($conn, "SHOW TABLES LIKE 'invoices'");
    if($chk5 && mysqli_num_rows($chk5) > 0) {
        $q_inv = mysqli_query($conn, "SELECT inv_no, client_name, service_label, inv_date, total, status FROM invoices ORDER BY inv_date DESC LIMIT 50");
        if($q_inv && mysqli_num_rows($q_inv) > 0) {
            $invList = [];
            while($r = mysqli_fetch_assoc($q_inv)) {
                $invList[] = sprintf("- INV#%s | Klien: %s | Layanan: %s | Tanggal: %s | Total: Rp%s | Status: %s",
                    $r['inv_no'], $r['client_name'], $r['service_label'], $r['inv_date'],
                    number_format((float)$r['total'],0,',','.'), $r['status']
                );
            }
            $context_parts[] = "=== DATA INVOICE (50 terbaru) ===\n" . implode("\n", $invList);
        }
    }

    // Payments (Keuangan Masuk)
    $chk6 = mysqli_query($conn, "SHOW TABLES LIKE 'payments'");
    if($chk6 && mysqli_num_rows($chk6) > 0) {
        $q_pay = mysqli_query($conn, "SELECT company_name, amount, payment_date, service_type, payment_type, invoice_no FROM payments ORDER BY payment_date DESC LIMIT 50");
        if($q_pay && mysqli_num_rows($q_pay) > 0) {
            $payList = [];
            while($r = mysqli_fetch_assoc($q_pay)) {
                $payList[] = sprintf("- %s | Klien: %s | Jumlah: Rp%s | Layanan: %s | Tipe: %s | INV: %s",
                    $r['payment_date'], $r['company_name']??'-', number_format((float)$r['amount'],0,',','.'),
                    $r['service_type']??'-', $r['payment_type']??'-', $r['invoice_no']??'-'
                );
            }
            $context_parts[] = "=== PEMASUKAN / PAYMENTS (50 terbaru) ===\n" . implode("\n", $payList);
        }
    }

    // Spendings (Pengeluaran)
    $chk7 = mysqli_query($conn, "SHOW TABLES LIKE 'spendings'");
    if($chk7 && mysqli_num_rows($chk7) > 0) {
        $q_sp = mysqli_query($conn, "SELECT type, detail, amount, vendor FROM spendings ORDER BY id DESC LIMIT 50");
        if($q_sp && mysqli_num_rows($q_sp) > 0) {
            $spList = [];
            while($r = mysqli_fetch_assoc($q_sp)) {
                $spList[] = sprintf("- Tipe: %s | Vendor: %s | Detail: %s | Jumlah: Rp%s",
                    $r['type']??'-', $r['vendor']??'-', $r['detail']??'-',
                    number_format((float)$r['amount'],0,',','.')
                );
            }
            $context_parts[] = "=== PENGELUARAN / SPENDINGS (50 terbaru) ===\n" . implode("\n", $spList);
        }
    }

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
        echo json_encode(['error'=>$result['error']]);
    } else {
        echo json_encode(['reply'=>$result['content'], 'model'=>$model, 'name'=>$ai_name]);
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

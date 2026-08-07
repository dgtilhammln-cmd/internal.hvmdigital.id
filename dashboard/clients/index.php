<?php
// 1. INIT
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if(!isset($_SESSION['admin'])){ echo "<script>window.location='/index.php';</script>"; exit; }

$allowed  = (isset($_SESSION['role']) && ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admin'));
$is_super = (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');

// 2. HELPERS
function formatWA($no){ $no = preg_replace('/[^0-9]/','',$no); if(substr($no,0,1)=='0') $no='62'.substr($no,1); return $no; }
function checkUrl($url){ if($url && !preg_match("~^(?:f|ht)tps?://~i", $url)) return "https://".$url; return $url; }

// 3. AUTO-FIX DATABASE
function fixCol($conn, $t, $c, $d){
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    if(mysqli_num_rows($q) == 0) mysqli_query($conn, "ALTER TABLE `$t` ADD COLUMN `$c` $d");
}
fixCol($conn, 'clients', 'link_planner',       'TEXT');
fixCol($conn, 'clients', 'link_design',        'TEXT');
fixCol($conn, 'clients', 'cred_instagram',     'TEXT');
fixCol($conn, 'clients', 'cred_tiktok',        'TEXT');
fixCol($conn, 'clients', 'cred_youtube',       'TEXT');
fixCol($conn, 'clients', 'logo_path',          "VARCHAR(255) DEFAULT NULL");
fixCol($conn, 'clients', 'notes',              'TEXT');
fixCol($conn, 'clients', 'link_artikel',       'TEXT');
fixCol($conn, 'clients', 'link_thumbnail',     'TEXT');
fixCol($conn, 'clients', 'link_other',         'TEXT');
fixCol($conn, 'clients', 'legal_docs',         'LONGTEXT');
fixCol($conn, 'clients', 'services_data',      'LONGTEXT');
fixCol($conn, 'clients', 'credentials_data',   'LONGTEXT');

// Auto-migrate old contract data to services_data JSON if empty
$q_mig = mysqli_query($conn, "SELECT client_id, contract_type, contract_start, contract_end FROM clients WHERE contract_type != '' AND (services_data IS NULL OR services_data = '' OR services_data = '[]')");
if($q_mig && mysqli_num_rows($q_mig) > 0){
    while($row = mysqli_fetch_assoc($q_mig)){
        $types = explode(',', $row['contract_type']);
        $svc_arr = [];
        foreach($types as $t){
            $t = trim($t);
            if(empty($t)) continue;
            $svc_arr[] = [
                'id'       => uniqid('svc_'),
                'type'     => $t,
                'start'    => $row['contract_start'] ?? '',
                'end'      => $row['contract_end'] ?? '',
                'status'   => 'Active',
                'price'    => '',
                'keywords' => '',
                'notes'    => ''
            ];
        }
        if(!empty($svc_arr)){
            $json = mysqli_real_escape_string($conn, json_encode($svc_arr, JSON_UNESCAPED_UNICODE));
            $cid = $row['client_id'];
            mysqli_query($conn, "UPDATE clients SET services_data='$json' WHERE client_id='$cid'");
        }
    }
}

// Upload dirs
$upload_dir  = $_SERVER['DOCUMENT_ROOT'] . '/uploads/client_logos/';
$docs_dir    = $_SERVER['DOCUMENT_ROOT'] . '/uploads/client_docs/';
if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
if(!is_dir($docs_dir))   mkdir($docs_dir,   0755, true);

// 4. AJAX: GET CLIENT DATA
if(isset($_POST['action']) && $_POST['action'] == 'get_client_data'){
    if(!$allowed) exit;
    $id     = mysqli_real_escape_string($conn, $_POST['id']);
    $q_cli  = mysqli_query($conn, "SELECT * FROM clients WHERE client_id = '$id'");
    $client = mysqli_fetch_assoc($q_cli);

    $email   = mysqli_real_escape_string($conn, $client['email'] ?? '');
    $company = mysqli_real_escape_string($conn, $client['company_name'] ?? '');
    
    $where_pay = [];
    if(!empty($email)) $where_pay[] = "email='$email'";
    if(!empty($company)) $where_pay[] = "company_name='$company'";
    $where_sql = count($where_pay) > 0 ? implode(" OR ", $where_pay) : "1=0";
    
    $q_pay   = mysqli_query($conn, "SELECT * FROM payments WHERE ($where_sql) ORDER BY payment_date DESC LIMIT 20");

    $history=[]; $last_pay_date=null; $total_all=0; $total_in_period=0; $totals_by_service=[];
    $cs = $client['contract_start'] ?? ''; $ce = $client['contract_end'] ?? '';

    while($r = mysqli_fetch_assoc($q_pay)){
        if(!$last_pay_date) $last_pay_date = $r['payment_date'];
        $total_all += (float)$r['amount'];
        if($cs && $ce && $r['payment_date'] >= $cs && $r['payment_date'] <= $ce)
            $total_in_period += (float)$r['amount'];
        $svc = trim($r['service_type'] ?: 'General');
        if(!isset($totals_by_service[$svc])) $totals_by_service[$svc] = 0;
        $totals_by_service[$svc] += (float)$r['amount'];
        $history[] = [
            'date'   => date('d M Y', strtotime($r['payment_date'])),
            'amount' => number_format($r['amount']),
            'desc'   => $r['payment_id'] ? "#".$r['payment_id'] : "Manual",
            'service'=> $svc,
            'type'   => $r['payment_type'] ?? 'Tunai'
        ];
    }
    arsort($totals_by_service);

    $score=0; $color='red'; $status='Belum Ada Transaksi';
    if(count($history)>0 && $last_pay_date){
        $d = (time()-strtotime($last_pay_date))/(60*60*24);
        if($d<=30){$score=95;$color='green';$status='Excellent (Aktif)';}
        elseif($d<=60){$score=70;$color='yellow';$status='Fair (Perlu Follow-up)';}
        else{$score=40;$color='red';$status='Poor (Jarang Transaksi)';}
    }

    // Parse legal_docs
    $legal_raw  = $client['legal_docs'] ?? '';
    $legal_docs = [];
    if($legal_raw){ $parsed = json_decode($legal_raw, true); if(is_array($parsed)) $legal_docs = $parsed; }

    // Parse services
    $srv_raw = $client['services_data'] ?? '';
    $services_data = [];
    if($srv_raw){ $p = json_decode($srv_raw, true); if(is_array($p)) $services_data = $p; }

    // Parse credentials
    $cred_raw = $client['credentials_data'] ?? '';
    $creds_data = [];
    if($cred_raw){ $p = json_decode($cred_raw, true); if(is_array($p)) $creds_data = $p; }

    echo json_encode([
        'data'             => $client,
        'history'          => $is_super ? $history : [],
        'score'            => $is_super ? $score : 0,
        'score_color'      => $is_super ? $color : 'gray',
        'score_status'     => $is_super ? $status : 'Restricted',
        'total_all'        => $is_super ? $total_all : 0,
        'total_in_period'  => $is_super ? $total_in_period : 0,
        'totals_by_service'=> $is_super ? $totals_by_service : [],
        'contract_start'   => $cs,
        'contract_end'     => $ce,
        'legal_docs'       => $legal_docs,
        'services_data'    => $services_data,
        'credentials_data' => $creds_data,
    ]);
    exit;
}

// 4b. AJAX: ADD LEGAL DOC
if(isset($_POST['action']) && $_POST['action'] == 'add_legal_doc'){
    if(!$allowed){ echo json_encode(['ok'=>false,'msg'=>'Access Denied']); exit; }
    $id      = mysqli_real_escape_string($conn, $_POST['client_id'] ?? '');
    $dtype   = mysqli_real_escape_string($conn, $_POST['doc_type'] ?? 'Other');
    $dname   = mysqli_real_escape_string($conn, $_POST['doc_name'] ?? '');
    $dlink   = trim($_POST['doc_link'] ?? '');

    // Fetch existing
    $q_cur   = mysqli_query($conn, "SELECT legal_docs FROM clients WHERE client_id='$id'");
    $r_cur   = mysqli_fetch_assoc($q_cur);
    $docs    = [];
    if(!empty($r_cur['legal_docs'])){ $p = json_decode($r_cur['legal_docs'],true); if(is_array($p)) $docs=$p; }

    $doc = [
        'id'    => uniqid('doc_'),
        'type'  => $dtype,
        'name'  => $dname ?: $dtype.' '.date('d/m/Y'),
        'date'  => date('Y-m-d'),
        'link'  => '',
        'file'  => '',
    ];

    // Handle file upload
    if(isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK){
        $docs_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/client_docs/';
        if(!is_dir($docs_dir)) mkdir($docs_dir, 0755, true);
        $ext  = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','zip'];
        if(!in_array($ext, $allowed_ext)){ echo json_encode(['ok'=>false,'msg'=>'Format tidak didukung!']); exit; }
        if($_FILES['doc_file']['size'] > 10*1024*1024){ echo json_encode(['ok'=>false,'msg'=>'Max ukuran file 10MB!']); exit; }
        $fname    = 'doc_'.$id.'_'.uniqid().'.'.$ext;
        move_uploaded_file($_FILES['doc_file']['tmp_name'], $docs_dir.$fname);
        $doc['file'] = '/uploads/client_docs/'.$fname;
        $doc['name'] = $doc['name'] ?: $_FILES['doc_file']['name'];
    } elseif($dlink !== '') {
        if(!preg_match("~^(?:f|ht)tps?://~i", $dlink)) $dlink = 'https://'.$dlink;
        $doc['link'] = $dlink;
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Upload file atau masukkan link!']); exit;
    }

    $docs[] = $doc;
    $json   = mysqli_real_escape_string($conn, json_encode($docs, JSON_UNESCAPED_UNICODE));
    mysqli_query($conn, "UPDATE clients SET legal_docs='$json' WHERE client_id='$id'");
    echo json_encode(['ok'=>true,'doc'=>$doc]);
    exit;
}

// 4c. AJAX: DELETE LEGAL DOC
if(isset($_POST['action']) && $_POST['action'] == 'delete_legal_doc'){
    if(!$allowed){ echo json_encode(['ok'=>false,'msg'=>'Access Denied']); exit; }
    $id     = mysqli_real_escape_string($conn, $_POST['client_id'] ?? '');
    $doc_id = $_POST['doc_id'] ?? '';

    $q_cur  = mysqli_query($conn, "SELECT legal_docs FROM clients WHERE client_id='$id'");
    $r_cur  = mysqli_fetch_assoc($q_cur);
    $docs   = [];
    if(!empty($r_cur['legal_docs'])){ $p = json_decode($r_cur['legal_docs'],true); if(is_array($p)) $docs=$p; }

    foreach($docs as $i => $d){
        if($d['id'] === $doc_id){
            // Delete physical file if exists
            if(!empty($d['file'])){
                $fp = $_SERVER['DOCUMENT_ROOT'].$d['file'];
                if(file_exists($fp)) unlink($fp);
            }
            array_splice($docs, $i, 1);
            break;
        }
    }
    $json = mysqli_real_escape_string($conn, json_encode($docs, JSON_UNESCAPED_UNICODE));
    mysqli_query($conn, "UPDATE clients SET legal_docs='$json' WHERE client_id='$id'");
    echo json_encode(['ok'=>true]);
    exit;
}

// 5. SAVE CLIENT
if(isset($_POST['save_client'])){
    if(!$allowed){ $_SESSION['popup']=['type'=>'error','msg'=>'Access Denied!']; header("Location: index.php"); exit; }
    try {
        $is_edit    = $_POST['is_edit_mode'];
        $id         = mysqli_real_escape_string($conn, $_POST['client_id']);
        $name       = mysqli_real_escape_string($conn, $_POST['company_name']);
        $city       = mysqli_real_escape_string($conn, $_POST['city']);
        $sector     = mysqli_real_escape_string($conn, $_POST['sector']);
        $start      = $_POST['contract_start'] ?? '';
        $end        = $_POST['contract_end'] ?? '';
        $services   = $_POST['contract_type'] ?? '';
        $pic_name   = mysqli_real_escape_string($conn, $_POST['pic_name']);
        $pic_pos    = mysqli_real_escape_string($conn, $_POST['pic_position']);
        $wa         = mysqli_real_escape_string($conn, $_POST['whatsapp']);
        $email      = mysqli_real_escape_string($conn, $_POST['email']);
        $phone      = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $l_soc      = mysqli_real_escape_string($conn, $_POST['links']);
        $l_drive    = mysqli_real_escape_string($conn, $_POST['drive_link']);
        $l_dvt      = mysqli_real_escape_string($conn, $_POST['drive_text']);
        $l_plan     = mysqli_real_escape_string($conn, $_POST['link_planner']);
        $l_design   = mysqli_real_escape_string($conn, $_POST['link_design']);
        $l_artikel  = mysqli_real_escape_string($conn, $_POST['link_artikel'] ?? '');
        $l_thumb    = mysqli_real_escape_string($conn, $_POST['link_thumbnail'] ?? '');
        $l_other    = mysqli_real_escape_string($conn, $_POST['link_other'] ?? '');
        $c_ig       = mysqli_real_escape_string($conn, $_POST['cred_instagram']);
        $c_tt       = mysqli_real_escape_string($conn, $_POST['cred_tiktok']);
        $c_yt       = mysqli_real_escape_string($conn, $_POST['cred_youtube']);
        $notes      = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');

        // Handle Services JSON
        $svc_arr = [];
        $svc_types_for_old = [];
        if(isset($_POST['svc_type']) && is_array($_POST['svc_type'])){
            for($i=0; $i<count($_POST['svc_type']); $i++){
                $svc_t = $_POST['svc_type'][$i];
                $svc_arr[] = [
                    'id'       => uniqid('svc_'),
                    'type'     => $svc_t,
                    'start'    => $_POST['svc_start'][$i] ?? '',
                    'end'      => $_POST['svc_end'][$i] ?? '',
                    'status'   => $_POST['svc_status'][$i] ?? 'Active',
                    'price'    => $_POST['svc_price'][$i] ?? '',
                    'keywords' => $_POST['svc_keywords'][$i] ?? '',
                    'notes'    => $_POST['svc_notes'][$i] ?? ''
                ];
                if(!in_array($svc_t, $svc_types_for_old)) $svc_types_for_old[] = $svc_t;
            }
        }
        // Auto-update contract_type from services for backward compat
        $services = mysqli_real_escape_string($conn, implode(', ', $svc_types_for_old));
        // Auto-update contract_start/end from earliest/latest service dates
        $start_dates = array_filter(array_column($svc_arr, 'start'));
        $end_dates   = array_filter(array_column($svc_arr, 'end'));
        if($start_dates) $start = min($start_dates); 
        if($end_dates)   $end   = max($end_dates);
        // Handle client status
        $cli_status = in_array($_POST['client_status'] ?? 'Active', ['Active','Inactive']) ? $_POST['client_status'] : 'Active';
        $services_json = mysqli_real_escape_string($conn, json_encode($svc_arr, JSON_UNESCAPED_UNICODE));

        // Handle Vault JSON
        $cred_arr = [
            'domain_registrar' => $_POST['vault_domain'] ?? '',
            'domain_expiry'    => $_POST['vault_expiry'] ?? '',
            'server_ip'        => $_POST['vault_server'] ?? '',
            'cpanel_login'     => $_POST['vault_cpanel'] ?? '',
            'wp_login'         => $_POST['vault_wp'] ?? ''
        ];
        $creds_json = mysqli_real_escape_string($conn, json_encode($cred_arr, JSON_UNESCAPED_UNICODE));

        $logo_sql_upd = "";
        $logo_ins     = "NULL";
        if(isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK){
            $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            if(!in_array($ext,['jpg','jpeg','png','gif','webp','svg'])) throw new Exception("Format logo tidak didukung!");
            if($_FILES['logo_file']['size'] > 2*1024*1024) throw new Exception("Ukuran logo max 2MB!");
            if($is_edit=="1"){
                $q_old = mysqli_query($conn,"SELECT logo_path FROM clients WHERE client_id='$id'");
                $r_old = mysqli_fetch_assoc($q_old);
                if(!empty($r_old['logo_path'])){
                    $old_fp = $_SERVER['DOCUMENT_ROOT'].$r_old['logo_path'];
                    if(file_exists($old_fp)) unlink($old_fp);
                }
            }
            $fname = 'logo_'.$id.'_'.time().'.'.$ext;
            move_uploaded_file($_FILES['logo_file']['tmp_name'], $upload_dir.$fname);
            $lp           = mysqli_real_escape_string($conn, '/uploads/client_logos/'.$fname);
            $logo_sql_upd = ", logo_path='$lp'";
            $logo_ins     = "'$lp'";
        }

        if($is_edit=="1"){
            mysqli_query($conn,"UPDATE clients SET
                company_name='$name',city='$city',sector='$sector',status='$cli_status',
                contract_start='$start',contract_end='$end',contract_type='$services',
                pic_name='$pic_name',pic_position='$pic_pos',
                whatsapp='$wa',email='$email',phone='$phone',
                link_social='$l_soc',link_drive='$l_drive',address='$l_dvt',
                link_planner='$l_plan',link_design='$l_design',
                link_artikel='$l_artikel',link_thumbnail='$l_thumb',link_other='$l_other',
                cred_instagram='$c_ig',cred_tiktok='$c_tt',cred_youtube='$c_yt',
                notes='$notes', services_data='$services_json', credentials_data='$creds_json' $logo_sql_upd
                WHERE client_id='$id'");
            $msg = "Client Updated!";
        } else {
            mysqli_query($conn,"INSERT INTO clients
                (client_id,company_name,city,sector,contract_start,contract_end,contract_type,
                 pic_name,pic_position,whatsapp,email,phone,link_social,link_drive,address,
                 link_planner,link_design,link_artikel,link_thumbnail,link_other,
                 cred_instagram,cred_tiktok,cred_youtube,notes,logo_path,status,services_data,credentials_data)
                VALUES
                ('$id','$name','$city','$sector','$start','$end','$services',
                 '$pic_name','$pic_pos','$wa','$email','$phone','$l_soc','$l_drive','$l_dvt',
                 '$l_plan','$l_design','$l_artikel','$l_thumb','$l_other',
                 '$c_ig','$c_tt','$c_yt','$notes',$logo_ins,'$cli_status','$services_json','$creds_json')");
            $msg = "New Client Added!";
        }
        $_SESSION['popup']=['type'=>'success','msg'=>$msg];
        header("Location: index.php"); exit;
    } catch(Exception $e){
        $err = ($e->getCode()==1062) ? "ID/Email Already Exists!" : $e->getMessage();
        $_SESSION['popup']=['type'=>'error','msg'=>$err];
        header("Location: index.php"); exit;
    }
}

// 6. VIEW DATA
if($allowed){
    $q_id    = mysqli_query($conn,"SELECT MAX(client_id) as max_id FROM clients");
    $r_id    = mysqli_fetch_assoc($q_id);
    $auto_id = str_pad((int)$r_id['max_id']+1,4,"0",STR_PAD_LEFT);

    $search     = $_GET['q'] ?? '';
    $se         = mysqli_real_escape_string($conn, $search);
    $result     = mysqli_query($conn,"SELECT * FROM clients WHERE company_name LIKE '%$se%' OR client_id LIKE '%$se%' ORDER BY client_id DESC");

    $stat_total   = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as n FROM clients"))['n'];
    $stat_year    = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as n FROM clients WHERE YEAR(created_at)=YEAR(NOW())"))['n'];
    $stat_lastmon = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as n FROM clients WHERE YEAR(created_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND MONTH(created_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH))"))['n'];
    $stat_thismon = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as n FROM clients WHERE YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW())"))['n'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - HVM</title>
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    <style>
/* =========================================
   CLIENTS - UPGRADED v2 (STAT CARDS + LOGO EDIT)
   ========================================= */
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap');

:root {
    --bg-dark:     #050505;
    --card-bg:     rgba(20,20,20,0.6);
    --card-border: rgba(255,255,255,0.08);
    --neon-main:   #a1ff5a;
    --neon-sec:    #4efdc4;
    --neon-red:    #ff5a5a;
    --neon-orange: #ff9f43;
    --neon-purple: #c084fc;
    --grad-main:   linear-gradient(135deg, var(--neon-main), var(--neon-sec));
    --text-white:  #ffffff;
    --text-muted:  #a0a0a0;
}

* { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat',sans-serif; }
body { background:var(--bg-dark); color:var(--text-white); min-height:100vh; overflow-x:hidden; }

/* AMBIENT GLOW */
.ambient-glow { position:fixed; border-radius:50%; filter:blur(120px); opacity:0.15; z-index:-1; animation:floatGlow 10s infinite alternate; }
.glow-1 { top:-100px; left:-100px; width:600px; height:600px; background:var(--neon-main); }
.glow-2 { bottom:-100px; right:-100px; width:600px; height:600px; background:var(--neon-sec); }
@keyframes floatGlow { from{transform:scale(1);}to{transform:scale(1.1);} }

/* SCROLLBAR */
::-webkit-scrollbar { width:6px; height:6px; }
::-webkit-scrollbar-track { background:#0a0a0a; }
::-webkit-scrollbar-thumb { background:#333; border-radius:10px; border:1px solid var(--neon-main); }
::-webkit-scrollbar-thumb:hover { background:var(--neon-main); }

/* LAYOUT */
.dashboard-wrapper { display:flex; width:100%; min-height:100vh; }
.sidebar { width:260px; background:rgba(10,10,10,0.85); border-right:1px solid var(--card-border); backdrop-filter:blur(20px); padding:30px 20px; display:flex; flex-direction:column; position:fixed; height:100vh; z-index:100; }
.main-content { margin-left:260px; padding:40px; width:calc(100% - 260px); display:flex; flex-direction:column; min-height:100vh; }

/* SIDEBAR */
.brand { font-size:1.5rem; font-weight:800; margin-bottom:50px; letter-spacing:1px; }
.nav-links a { display:flex; align-items:center; padding:12px 15px; color:var(--text-muted); text-decoration:none; margin-bottom:5px; border-radius:10px; transition:0.3s; font-weight:600; font-size:0.9rem; }
.nav-links a:hover { color:#fff; background:rgba(255,255,255,0.05); }
.nav-links a.active { background:var(--grad-main); color:#000; box-shadow:0 0 15px rgba(161,255,90,0.3); }
.btn-doc-save { transition:0.3s; }
.btn-doc-save:hover { background:var(--neon-main); color:#000; box-shadow:0 0 15px rgba(161,255,90,0.4); }

/* MULTI-SERVICE CARDS (VIEW MODE) */
.ms-card {
    background: rgba(20, 20, 20, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: 0.2s ease;
    position: relative;
    overflow: hidden;
}
.ms-card::before {
    content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
    background: var(--card-color, var(--neon-main));
}
.ms-card:hover { background: rgba(30, 30, 30, 1); border-color: rgba(255,255,255,0.1); }
.ms-header { display: flex; justify-content: space-between; align-items: flex-start; }
.ms-title { font-size: 1.1rem; font-weight: 700; color: var(--neon-main); margin-top:2px; }
.ms-status { padding: 4px 10px; border-radius: 4px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.ms-status.active { background: rgba(161, 255, 90, 0.1); color: var(--neon-main); border: 1px solid rgba(161, 255, 90, 0.2); }
.ms-status.inactive { background: rgba(255, 90, 90, 0.1); color: var(--neon-red); border: 1px solid rgba(255, 90, 90, 0.2); }
.ms-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 0.85rem; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.03); margin-top: 4px; }
.ms-detail div { display: flex; flex-direction: row; align-items: center; gap: 6px; }
.ms-detail-label { font-size: 0.75rem; color: #777; font-weight: 500; }
.ms-detail-val { font-size: 0.8rem; color: #ddd; font-weight: 600; }
.ms-keywords { font-size: 0.75rem; padding: 10px; background: rgba(255,255,255,0.02); border-radius: 6px; border: 1px solid rgba(255,255,255,0.03); margin-top: 4px; }

/* SCORE RING */
.score-card { display:flex; align-items:center; gap:20px; background:rgba(255,255,255,0.03); border-radius:15px; padding:20px; margin-bottom:20px; border:1px solid rgba(255,255,255,0.05); }
.logout-area { margin-top:auto; }
.btn-logout { color:var(--neon-red) !important; text-decoration:none; display:flex; align-items:center; gap:10px; font-weight:600; }

/* HEADLINE */
.page-headline { margin-bottom:24px; animation:slideDown 0.6s ease; }
.page-headline h1 { font-size:2.2rem; font-weight:800; margin-bottom:5px; background:linear-gradient(180deg,#fff,#aaa); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.page-headline p { color:var(--text-muted); font-size:0.9rem; }

/* =====================
   STAT CARDS ROW
   ===================== */
.stat-cards-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
    animation: fadeIn 0.7s ease;
}

.stat-card {
    background: rgba(14,14,14,0.7);
    border: 1px solid var(--card-border);
    border-radius: 18px;
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    position: relative;
    overflow: hidden;
    transition: 0.3s;
    backdrop-filter: blur(10px);
}
.stat-card:hover {
    border-color: rgba(255,255,255,0.15);
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
}
.stat-card-active {
    border-color: rgba(192,132,252,0.25);
    box-shadow: 0 0 24px rgba(192,132,252,0.06);
}
.stat-card-active:hover {
    border-color: rgba(192,132,252,0.4);
}

.stat-icon {
    width: 46px; height: 46px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.stat-info { flex: 1; min-width: 0; }
.stat-num {
    font-size: 2rem;
    font-weight: 900;
    color: var(--neon-main);
    line-height: 1;
    margin-bottom: 4px;
}
.stat-label {
    font-size: 0.72rem;
    color: #666;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stat-deco {
    position: absolute;
    right: 16px; bottom: 10px;
    font-size: 2.2rem;
    font-weight: 900;
    color: rgba(255,255,255,0.04);
    letter-spacing: -1px;
    pointer-events: none;
    user-select: none;
}

/* ACTION BAR */
.action-area { background:var(--card-bg); border:1px solid var(--card-border); backdrop-filter:blur(10px); border-radius:16px; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:28px; animation:fadeIn 0.9s ease; }
.search-glass { flex:1; background:transparent; border:none; border-bottom:1px solid var(--card-border); color:#fff; padding:8px; min-width:200px; outline:none; font-size:0.9rem; transition:0.3s; }
.search-glass:focus { border-color:var(--neon-main); }
.btn-neon { background:var(--grad-main); color:#000; padding:12px 25px; border-radius:50px; font-weight:800; border:none; cursor:pointer; transition:0.3s; display:flex; align-items:center; gap:8px; font-size:0.85rem; box-shadow:0 0 15px rgba(161,255,90,0.2); }
.btn-neon:hover { transform:translateY(-2px); box-shadow:0 5px 25px rgba(161,255,90,0.4); }

/* CLIENT GRID */
.client-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:22px; animation:fadeIn 0.8s ease; }
.glass-card { background:rgba(14,14,14,0.5); backdrop-filter:blur(20px); border:1px solid var(--card-border); border-radius:20px; padding:24px; display:flex; flex-direction:column; position:relative; overflow:hidden; transition:0.3s; box-shadow:0 8px 24px rgba(0,0,0,0.3); min-height:250px; }
.glass-card:hover { border-color:rgba(161,255,90,0.35); transform:translateY(-4px); box-shadow:0 12px 40px rgba(0,0,0,0.5); }

/* Card Logo */
.card-logo-wrap { display:flex; align-items:center; gap:13px; margin-bottom:13px; }
.card-logo { width:50px; height:50px; border-radius:11px; object-fit:contain; background:#111; border:1px solid rgba(255,255,255,0.08); padding:4px; flex-shrink:0; }
.card-logo-placeholder { width:50px; height:50px; border-radius:11px; background:linear-gradient(135deg,rgba(161,255,90,0.12),rgba(78,253,196,0.08)); border:1px solid rgba(161,255,90,0.2); display:flex; align-items:center; justify-content:center; font-size:1rem; font-weight:900; color:var(--neon-main); flex-shrink:0; letter-spacing:1px; }
.card-logo-info { flex:1; overflow:hidden; }
.client-id { font-size:0.7rem; color:var(--neon-sec); letter-spacing:1px; margin-bottom:2px; font-weight:700; font-family:'Courier New',monospace; }
.client-name { font-size:1.1rem; font-weight:800; color:#fff; line-height:1.25; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.card-city { font-size:0.75rem; color:#555; margin-bottom:11px; display:flex; align-items:center; gap:5px; }
.card-city i { color:var(--neon-sec); font-size:0.7rem; }
.badge-container { display:flex; flex-wrap:wrap; gap:4px; margin-bottom:12px; }
.service-badge { font-size:0.65rem; padding:3px 8px; border-radius:6px; background:rgba(78,253,196,0.06); color:var(--neon-sec); border:1px solid rgba(78,253,196,0.2); font-weight:600; text-transform:uppercase; }
.social-row { display:flex; gap:8px; margin-bottom:18px; }
.social-btn { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.04); color:#777; border:1px solid var(--card-border); transition:0.3s; text-decoration:none; font-size:0.82rem; }
.social-btn:hover { color:#fff; border-color:var(--neon-main); background:rgba(161,255,90,0.12); box-shadow:0 0 8px rgba(161,255,90,0.3); transform:scale(1.1); }
.card-actions { margin-top:auto; display:flex; gap:8px; }
.btn-view,.btn-edit { flex:1; padding:9px; border-radius:10px; font-weight:700; cursor:pointer; transition:0.3s; border:1px solid var(--card-border); font-size:0.8rem; background:rgba(255,255,255,0.03); color:#888; display:flex; align-items:center; justify-content:center; gap:5px; }
.btn-view:hover { background:var(--neon-sec); color:#000; border-color:transparent; }
.btn-edit:hover { background:var(--neon-orange); color:#000; border-color:transparent; }

/* MODAL */
.modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.92); z-index:100000; display:none; justify-content:center; align-items:center; backdrop-filter:blur(12px); }
.modal-overlay.active { display:flex; animation:fadeIn 0.25s; }
.modal-content { background:#080808; border:1px solid rgba(161,255,90,0.25); width:820px; max-width:96%; border-radius:24px; box-shadow:0 0 80px rgba(161,255,90,0.08); max-height:92vh; overflow:hidden; display:flex; flex-direction:column; }

/* Modal Header */
.modal-header { padding:18px 26px; border-bottom:1px solid var(--card-border); display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.012); }
.modal-header-left { display:flex; align-items:center; gap:13px; }
.modal-logo-wrap { flex-shrink:0; }
.modal-logo-img { width:44px; height:44px; border-radius:10px; object-fit:contain; background:#111; border:1px solid rgba(255,255,255,0.08); padding:3px; }
.modal-logo-placeholder { width:44px; height:44px; border-radius:10px; background:linear-gradient(135deg,rgba(161,255,90,0.12),rgba(78,253,196,0.08)); border:1px solid rgba(161,255,90,0.25); display:flex; align-items:center; justify-content:center; font-size:0.85rem; font-weight:900; color:var(--neon-main); letter-spacing:1px; }
.modal-title { font-size:1.25rem; color:#fff; font-weight:800; }
.close-modal { background:none; border:none; color:#555; font-size:1.8rem; cursor:pointer; transition:0.3s; line-height:1; }
.close-modal:hover { color:var(--neon-red); transform:rotate(90deg); }

/* Tabs */
.modal-tabs { display:flex; border-bottom:1px solid var(--card-border); background:rgba(0,0,0,0.35); padding:0 18px; gap:2px; }
.tab-link { padding:13px 15px; cursor:pointer; color:#666; font-weight:600; font-size:0.82rem; transition:0.3s; border-bottom:3px solid transparent; display:flex; align-items:center; gap:6px; white-space:nowrap; }
.tab-link i { font-size:0.78rem; }
.tab-link:hover { color:#bbb; }
.tab-link.active { color:var(--neon-main); border-bottom-color:var(--neon-main); }
.modal-body-scroll { padding:26px 28px; overflow-y:auto; flex:1; }
.tab-pane { display:none; animation:fadeIn 0.3s; }
.tab-pane.active { display:block; }

/* =====================
   LOGO UPLOAD
   ===================== */
.logo-upload-section { margin-bottom:22px; }
.logo-upload-title { font-size:0.75rem; font-weight:700; color:#777; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
.logo-upload-title i { color:var(--neon-sec); }

/* Current logo strip */
.current-logo-wrap { display:flex; align-items:center; gap:12px; padding:10px 14px; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:10px; margin-bottom:10px; }
.current-logo-img { width:44px; height:44px; border-radius:8px; object-fit:contain; background:#111; border:1px solid rgba(255,255,255,0.08); padding:3px; flex-shrink:0; }
.current-logo-label { font-size:0.75rem; color:#666; }

.logo-upload-area { border:2px dashed rgba(255,255,255,0.08); border-radius:12px; padding:18px; text-align:center; cursor:pointer; transition:0.3s; min-height:80px; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.01); }
.logo-upload-area:hover,.logo-upload-area.drag-over { border-color:var(--neon-main); background:rgba(161,255,90,0.03); }
.logo-upload-prompt { display:flex; flex-direction:column; align-items:center; gap:5px; color:#555; pointer-events:none; }
.logo-upload-prompt i { font-size:1.6rem; color:#333; }
.logo-upload-prompt span { font-size:0.82rem; font-weight:600; color:#555; }
.logo-upload-prompt small { font-size:0.7rem; color:#333; }
.logo-preview-wrap { position:relative; display:inline-flex; }
.logo-preview-img { width:68px; height:68px; object-fit:contain; border-radius:10px; border:1px solid rgba(255,255,255,0.08); background:#111; padding:4px; }
.logo-remove-btn { position:absolute; top:-8px; right:-8px; width:22px; height:22px; border-radius:50%; background:var(--neon-red); border:none; color:#fff; font-size:0.62rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s; z-index:1; }
.logo-remove-btn:hover { transform:scale(1.15); }

/* FORMS */
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.form-group { margin-bottom:13px; position:relative; }
.form-group.full { grid-column:span 2; }
.form-group label { display:block; color:#777; margin-bottom:6px; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; }
.form-input { width:100%; padding:10px 13px; background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.07); border-radius:10px; color:#fff; outline:none; transition:0.3s; font-size:0.88rem; font-family:inherit; }
.form-input:focus { border-color:var(--neon-main); background:rgba(255,255,255,0.04); }
.form-input[readonly] { background:rgba(0,0,0,0.35); border:1px dashed rgba(255,255,255,0.05); color:#555; cursor:default; }
.form-textarea { min-height:95px; resize:vertical; line-height:1.6; }

.input-link-btn { position:absolute; right:9px; top:32px; background:var(--neon-sec); color:#000; width:27px; height:27px; border-radius:50%; display:none; align-items:center; justify-content:center; font-size:0.72rem; cursor:pointer; transition:0.3s; box-shadow:0 0 8px rgba(78,253,196,0.3); text-decoration:none; }
.input-link-btn:hover { transform:scale(1.1); }
.form-group:hover .input-link-btn.show { display:flex; }

.service-options { display:flex; gap:7px; flex-wrap:wrap; margin-top:4px; }
.service-check { display:none; }
.service-label { padding:6px 13px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:50px; cursor:pointer; font-size:0.76rem; color:#888; transition:0.3s; user-select:none; }
.service-check:checked + .service-label { background:rgba(161,255,90,0.1); border-color:var(--neon-main); color:#fff; font-weight:700; box-shadow:0 0 8px rgba(161,255,90,0.15); }

.detail-section-title { font-size:0.75rem; color:#aaa; font-weight:900; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:13px; border-bottom:1px dashed rgba(255,255,255,0.06); padding-bottom:6px; }

/* =====================
   SEO & CONTENT MONITORING SECTION
   ===================== */
.section-seo-monitoring {
    background: rgba(161,255,90,0.02);
    border: 1px solid rgba(161,255,90,0.08);
    border-left: 3px solid var(--neon-main);
    border-radius: 12px;
    padding: 16px 18px;
    margin-bottom: 6px;
}
.section-seo-monitoring .detail-section-title {
    color: var(--neon-main);
    border-bottom-color: rgba(161,255,90,0.12);
    margin-bottom: 14px;
}
.section-seo-monitoring .form-group {
    margin-bottom: 10px;
}
.section-seo-monitoring .form-group:last-child {
    margin-bottom: 0;
}

/* HISTORY */
.score-card { background:rgba(255,255,255,0.02); border:1px solid var(--card-border); border-radius:15px; padding:18px; display:flex; align-items:center; gap:20px; margin-bottom:18px; }
.score-circle { width:82px; height:82px; border-radius:50%; border:5px solid; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:800; flex-shrink:0; }
.score-circle.green  { border-color:var(--neon-main);   color:var(--neon-main);   box-shadow:0 0 20px rgba(161,255,90,0.18); }
.score-circle.yellow { border-color:var(--neon-orange); color:var(--neon-orange); box-shadow:0 0 20px rgba(255,159,67,0.18); }
.score-circle.red    { border-color:var(--neon-red);    color:var(--neon-red);    box-shadow:0 0 20px rgba(255,90,90,0.18); }
.score-circle.gray   { border-color:#333; color:#555; }
.score-info h4 { font-size:0.95rem; margin-bottom:4px; color:#fff; }
.score-info p  { font-size:0.8rem; color:#888; line-height:1.5; }

.payment-summary-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
.pay-sum-card { background:rgba(255,255,255,0.02); border:1px solid var(--card-border); border-radius:12px; padding:14px 16px; transition:0.2s; }
.pay-sum-card.total  { border-left:3px solid var(--neon-main); }
.pay-sum-card.period { border-left:3px solid var(--neon-sec); }
.pay-sum-label { font-size:0.72rem; color:#888; font-weight:700; margin-bottom:7px; display:flex; align-items:center; gap:5px; text-transform:uppercase; letter-spacing:0.5px; }
.pay-sum-card.total  .pay-sum-label i { color:var(--neon-main); }
.pay-sum-card.period .pay-sum-label i { color:var(--neon-sec); }
.pay-sum-val { font-size:1.2rem; font-weight:800; }
.pay-sum-card.total  .pay-sum-val { color:var(--neon-main); }
.pay-sum-card.period .pay-sum-val { color:var(--neon-sec); }
.pay-sum-sub { font-size:0.7rem; color:#555; margin-top:3px; }

.breakdown-wrap { background:rgba(255,255,255,0.015); border:1px solid var(--card-border); border-radius:13px; padding:15px 17px; margin-bottom:18px; }
.breakdown-list { display:flex; flex-direction:column; gap:10px; margin-top:8px; }
.breakdown-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:5px; }
.breakdown-svc { font-size:0.8rem; font-weight:700; }
.breakdown-amt { font-size:0.78rem; color:#888; }
.breakdown-amt small { font-size:0.7rem; color:#555; }
.breakdown-bar-bg { height:4px; background:rgba(255,255,255,0.04); border-radius:10px; overflow:hidden; }
.breakdown-bar-fill { height:100%; border-radius:10px; transition:width 0.8s cubic-bezier(0.4,0,0.2,1); }

.history-list { display:flex; flex-direction:column; gap:7px; }
.history-item { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; background:rgba(255,255,255,0.015); border-radius:9px; border:1px solid rgba(255,255,255,0.05); transition:0.2s; }
.history-item:hover { background:rgba(255,255,255,0.03); border-color:rgba(161,255,90,0.18); }
.hist-desc  { font-weight:700; color:#fff; font-size:0.88rem; margin-bottom:2px; display:block; }
.hist-svc   { font-size:0.68rem; color:var(--neon-sec); margin-left:4px; font-weight:600; text-transform:uppercase; background:rgba(78,253,196,0.07); padding:2px 6px; border-radius:4px; }
.hist-date  { font-size:0.7rem; color:#555; }
.hist-amount{ font-weight:700; color:var(--neon-main); font-size:0.9rem; }
.hist-type  { font-size:0.68rem; color:#444; text-align:right; margin-top:2px; }

/* FORBIDDEN */
.forbidden-box { display:flex; flex-direction:column; justify-content:center; align-items:center; height:70vh; text-align:center; border:1px solid var(--card-border); background:rgba(255,255,255,0.015); border-radius:24px; }
.forbidden-icon { font-size:4rem; color:var(--neon-red); margin-bottom:20px; animation:pulse 1.5s infinite; }
.forbidden-text { color:#fff; font-size:1.5rem; font-weight:800; margin-bottom:10px; }
.forbidden-sub  { color:#888; margin-bottom:30px; font-size:0.9rem; }

/* POPUP */
.popup { position:fixed; top:28px; right:28px; z-index:100001; background:#0a0a0a; border-left:4px solid var(--neon-main); padding:16px 24px; border-radius:12px; color:#fff; display:flex; align-items:center; gap:13px; transform:translateX(160%); transition:0.4s cubic-bezier(0.4,0,0.2,1); box-shadow:0 10px 40px rgba(0,0,0,0.6); border:1px solid var(--card-border); max-width:300px; }
.popup.show { transform:translateX(0); }
.popup.error { border-left-color:var(--neon-red); }
.popup.error i { color:var(--neon-red); }
.popup i { font-size:1.3rem; color:var(--neon-main); flex-shrink:0; }

/* =====================
   LEGAL DOCS TAB
   ===================== */
.legal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.btn-add-doc {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(161,255,90,0.08);
    border: 1px solid rgba(161,255,90,0.25);
    color: var(--neon-main);
    padding: 7px 15px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: 0.25s;
    font-family: inherit;
    letter-spacing: 0.3px;
}
.btn-add-doc:hover {
    background: rgba(161,255,90,0.15);
    border-color: var(--neon-main);
    box-shadow: 0 0 12px rgba(161,255,90,0.15);
}

/* Add doc form */
.add-doc-form {
    background: rgba(10,10,10,0.6);
    border: 1px solid rgba(161,255,90,0.15);
    border-radius: 14px;
    margin-bottom: 18px;
    overflow: hidden;
    animation: fadeIn 0.2s ease;
}
.add-doc-form-inner { padding: 18px 20px; }
.add-doc-row {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 4px;
}
.add-doc-or {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 14px 0 12px;
    font-size: 0.73rem;
    color: #555;
    font-weight: 600;
}
.add-doc-or em { color: #666; font-style: normal; }
.add-doc-or-line { flex: 1; height: 1px; background: rgba(255,255,255,0.05); }

.doc-upload-zone {
    flex: 1.2;
    border: 2px dashed rgba(255,255,255,0.07);
    border-radius: 10px;
    padding: 14px 12px;
    text-align: center;
    cursor: pointer;
    transition: 0.25s;
    background: rgba(255,255,255,0.01);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    min-width: 0;
}
.doc-upload-zone:hover,
.doc-upload-zone.drag-over {
    border-color: var(--neon-main);
    background: rgba(161,255,90,0.03);
}
.doc-upload-zone.has-file {
    border-color: var(--neon-sec);
    background: rgba(78,253,196,0.04);
}
.doc-upload-zone i {
    font-size: 1.4rem;
    color: #333;
    margin-bottom: 2px;
}
.doc-upload-zone.has-file i { color: var(--neon-sec); }
.doc-upload-zone span {
    font-size: 0.76rem;
    color: #666;
    font-weight: 600;
    word-break: break-all;
}
.doc-upload-zone.has-file span { color: var(--neon-sec); }
.doc-upload-zone small {
    font-size: 0.65rem;
    color: #333;
}

.doc-or-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 0 2px;
    margin-top: 22px;
}
.doc-or-divider span {
    font-size: 0.7rem;
    color: #444;
    font-weight: 700;
    background: #0a0a0a;
    padding: 4px 8px;
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.05);
}

.add-doc-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid rgba(255,255,255,0.04);
}
.btn-doc-cancel {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.08);
    color: #666;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s;
    font-family: inherit;
}
.btn-doc-cancel:hover { border-color: #555; color: #aaa; }

.btn-doc-save {
    background: var(--grad-main);
    border: none;
    color: #000;
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 800;
    cursor: pointer;
    transition: 0.2s;
    display: flex;
    align-items: center;
    gap: 7px;
    font-family: inherit;
    box-shadow: 0 0 12px rgba(161,255,90,0.15);
}
.btn-doc-save:hover { transform: translateY(-1px); box-shadow: 0 4px 18px rgba(161,255,90,0.3); }
.btn-doc-save:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* Docs list */
.legal-docs-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.legal-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 40px 20px;
    color: #333;
    font-size: 0.82rem;
    font-weight: 600;
}
.legal-empty i { font-size: 2.2rem; color: #222; }

.legal-doc-item {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 16px;
    background: rgba(14,14,14,0.7);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    transition: 0.25s;
    animation: fadeIn 0.25s ease;
}
.legal-doc-item:hover {
    border-color: rgba(161,255,90,0.2);
    background: rgba(20,20,20,0.8);
    box-shadow: 0 4px 18px rgba(0,0,0,0.3);
}

.doc-icon-wrap {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    border: 1px solid;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
    transition: 0.2s;
}
.legal-doc-item:hover .doc-icon-wrap {
    transform: scale(1.05);
}

.doc-info { flex: 1; min-width: 0; }
.doc-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.doc-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.doc-type-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 5px;
    border: 1px solid;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.doc-date, .doc-source {
    font-size: 0.7rem;
    color: #555;
    display: flex;
    align-items: center;
    gap: 4px;
}
.doc-date i, .doc-source i { font-size: 0.62rem; }

.doc-actions {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 0;
}
.doc-btn-open {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(78,253,196,0.07);
    border: 1px solid rgba(78,253,196,0.2);
    color: var(--neon-sec);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    text-decoration: none;
    transition: 0.2s;
}
.doc-btn-open:hover {
    background: rgba(78,253,196,0.15);
    border-color: var(--neon-sec);
    box-shadow: 0 0 8px rgba(78,253,196,0.2);
    transform: scale(1.08);
}
.doc-btn-del {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(255,90,90,0.05);
    border: 1px solid rgba(255,90,90,0.15);
    color: #ff5a5a99;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    cursor: pointer;
    transition: 0.2s;
    font-family: inherit;
}
.doc-btn-del:hover {
    background: rgba(255,90,90,0.15);
    border-color: var(--neon-red);
    color: var(--neon-red);
    box-shadow: 0 0 8px rgba(255,90,90,0.2);
    transform: scale(1.08);
}

/* ANIMATIONS */
@keyframes slideDown { from{opacity:0;transform:translateY(-20px);}to{opacity:1;transform:translateY(0);} }
@keyframes fadeIn    { from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);} }
@keyframes pulse     { 0%{transform:scale(1);opacity:1;}50%{transform:scale(1.1);opacity:0.7;}100%{transform:scale(1);opacity:1;} }

/* MOBILE */
@media (max-width: 900px) {
    .stat-cards-row { grid-template-columns:1fr 1fr; }
}
@media (max-width: 768px) {
    .sidebar { display:none; }
    .main-content { margin-left:0; width:100%; padding:20px; }
    .stat-cards-row { grid-template-columns:1fr 1fr; gap:12px; }
    .stat-num { font-size:1.6rem; }
    .client-grid { grid-template-columns:1fr; }
    .form-grid { grid-template-columns:1fr; }
    .form-group.full { grid-column:span 1; }
    .modal-content { width:100%; height:100vh; max-height:100vh; border-radius:0; }
    .action-area { flex-direction:column; align-items:stretch; }
    .search-glass { width:100%; }
    .modal-tabs { overflow-x:auto; }
    .tab-link { white-space:nowrap; }
    .history-item { flex-direction:column; align-items:flex-start; gap:6px; }
    .hist-right { text-align:left; width:100%; }
    .payment-summary-grid { grid-template-columns:1fr; }
}
@media (max-width: 480px) {
    .stat-cards-row { grid-template-columns:1fr; }
}
    
/* =========================================
   ADDITIONS FOR MULTI-SERVICE & VAULT
   ========================================= */
.svc-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 1fr auto; gap: 10px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 12px; border-radius: 12px; margin-bottom: 10px; align-items: center; }
.svc-row .form-group { margin-bottom: 0; }
.btn-remove-svc { background: rgba(255,90,90,0.1); color: var(--neon-red); border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; }
.btn-remove-svc:hover { background: var(--neon-red); color: #fff; }
.btn-add-svc { background: rgba(161,255,90,0.1); color: var(--neon-main); border: 1px solid rgba(161,255,90,0.2); padding: 8px 15px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; margin-top: 5px; }
.btn-add-svc:hover { background: rgba(161,255,90,0.2); }

.vault-container { background: rgba(14,14,14,0.7); border: 1px solid rgba(161,255,90,0.15); border-radius: 14px; padding: 20px; }
.vault-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px; }
.vault-header i { font-size: 1.5rem; color: var(--neon-main); }
.vault-header h3 { font-size: 1.1rem; font-weight: 800; color: #fff; }

.vault-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.vault-input { width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px dashed rgba(255,255,255,0.1); border-radius: 8px; color: var(--neon-sec); font-family: 'Courier New', Courier, monospace; font-size: 0.9rem; transition: 0.3s; }
.vault-input:focus { border-color: var(--neon-main); }
.vault-input::placeholder { color: rgba(255,255,255,0.2); }

/* Multi-Service View Card */
.ms-card { background: rgba(255,255,255,0.02); border: 1px solid var(--card-border); border-left: 4px solid var(--neon-sec); padding: 15px; border-radius: 12px; margin-bottom: 10px; }
.ms-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.ms-title { font-weight: 800; font-size: 1.1rem; color: #fff; }
.ms-status { font-size: 0.7rem; padding: 3px 8px; border-radius: 5px; font-weight: 700; text-transform: uppercase; }
.ms-status.active { background: rgba(161,255,90,0.1); color: var(--neon-main); border: 1px solid rgba(161,255,90,0.3); }
.ms-status.inactive { background: rgba(255,90,90,0.1); color: var(--neon-red); border: 1px solid rgba(255,90,90,0.3); }
.ms-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.8rem; color: #aaa; }
.ms-detail strong { color: #fff; }
.ms-keywords { margin-top: 10px; padding: 10px; background: rgba(0,0,0,0.4); border-radius: 8px; font-size: 0.8rem; color: var(--neon-sec); border: 1px solid rgba(78,253,196,0.1); }
    </style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<div class="ambient-glow glow-1"></div>
<div class="ambient-glow glow-2"></div>

<div class="dashboard-wrapper">
    <?php include '../sidebar.php'; ?>

    <main class="main-content">
        <div class="page-headline">
            <h1>Clients Database</h1>
            <p>Manage partnership, contracts, and payment history.</p>
        </div>

        <?php if(!$allowed): ?>
        <div class="forbidden-box">
            <div class="forbidden-icon"><i class="fas fa-lock"></i></div>
            <div class="forbidden-text">ACCESS DENIED</div>
            <div class="forbidden-sub">Halaman ini hanya untuk Admin.</div>
            <a href="/dashboard/" class="btn-neon" style="text-decoration:none;">KEMBALI KE DASHBOARD</a>
        </div>
        <?php else: ?>

        <!-- STAT CARDS -->
        <div class="stat-cards-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(161,255,90,0.08);color:var(--neon-main);"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-num"><?php echo $stat_total; ?></div>
                    <div class="stat-label">Total Client</div>
                </div>
                <div class="stat-deco">ALL</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(78,253,196,0.08);color:var(--neon-sec);"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-info">
                    <div class="stat-num" style="color:var(--neon-sec);"><?php echo $stat_year; ?></div>
                    <div class="stat-label">Tahun <?php echo date('Y'); ?></div>
                </div>
                <div class="stat-deco"><?php echo date('Y'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(255,159,67,0.08);color:var(--neon-orange);"><i class="fas fa-history"></i></div>
                <div class="stat-info">
                    <div class="stat-num" style="color:var(--neon-orange);"><?php echo $stat_lastmon; ?></div>
                    <div class="stat-label">Bulan Lalu &middot; <?php echo date('M', strtotime('first day of last month')); ?></div>
                </div>
                <div class="stat-deco"><?php echo strtoupper(date('M', strtotime('first day of last month'))); ?></div>
            </div>
            <div class="stat-card stat-card-active">
                <div class="stat-icon" style="background:rgba(192,132,252,0.1);color:var(--neon-purple);"><i class="fas fa-bolt"></i></div>
                <div class="stat-info">
                    <div class="stat-num" style="color:var(--neon-purple);"><?php echo $stat_thismon; ?></div>
                    <div class="stat-label">Bulan Ini &middot; <?php echo date('M'); ?></div>
                </div>
                <div class="stat-deco" style="color:rgba(192,132,252,0.12);"><?php echo strtoupper(date('M')); ?></div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-area">
            <form method="GET" style="flex:1;display:flex;">
                <input type="text" name="q" class="search-glass" placeholder="Search ID / Company name..." value="<?php echo htmlspecialchars($search); ?>">
            </form>
            <button class="btn-neon" onclick="openModal()"><i class="fas fa-plus"></i> New Client</button>
        </div>

        <!-- CLIENT GRID -->
        <div class="client-grid">
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <div class="glass-card">
                <div class="card-logo-wrap">
                    <?php if(!empty($row['logo_path'])): ?>
                        <img src="<?php echo htmlspecialchars($row['logo_path']); ?>" class="card-logo" alt="logo">
                    <?php else: ?>
                        <div class="card-logo-placeholder"><?php echo strtoupper(substr($row['company_name'],0,2)); ?></div>
                    <?php endif; ?>
                    <div class="card-logo-info">
                        <div class="client-id">ID: #<?php echo $row['client_id']; ?></div>
                        <h2 class="client-name"><?php echo htmlspecialchars($row['company_name']); ?></h2>
                    </div>
                </div>
                <div class="badge-container">
                    <?php if(!empty($row['contract_type'])): foreach(explode(',',$row['contract_type']) as $srv) echo "<span class='service-badge'>".htmlspecialchars(trim($srv))."</span>"; endif; ?>
                </div>
                <?php if(!empty($row['city'])): ?>
                <div class="card-city"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['city']); ?></div>
                <?php endif; ?>
                <div class="social-row">
                    <?php if(!empty($row['whatsapp'])): ?><a href="https://wa.me/<?php echo formatWA($row['whatsapp']); ?>" target="_blank" class="social-btn wa" title="WhatsApp"><i class="fab fa-whatsapp"></i></a><?php endif; ?>
                    <?php if(!empty($row['email'])): ?><a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" class="social-btn" title="Email"><i class="fas fa-envelope"></i></a><?php endif; ?>
                    <?php if(!empty($row['link_drive'])): ?><a href="<?php echo checkUrl($row['link_drive']); ?>" target="_blank" class="social-btn" title="Drive"><i class="fab fa-google-drive"></i></a><?php endif; ?>
                    <?php if(!empty($row['link_social'])): ?><a href="<?php echo checkUrl($row['link_social']); ?>" target="_blank" class="social-btn" title="Social"><i class="fas fa-globe"></i></a><?php endif; ?>
                </div>
                <div class="card-actions">
                    <button class="btn-view" onclick='viewDetail("<?php echo $row['client_id']; ?>")'><i class="fas fa-chart-pie"></i> View</button>
                    <button class="btn-edit" onclick='editDetail("<?php echo $row['client_id']; ?>")'><i class="fas fa-edit"></i> Edit</button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- MODAL -->
<?php if($allowed): ?>
<div class="modal-overlay" id="clientModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-logo-wrap">
                    <div id="modalLogoPlaceholder" class="modal-logo-placeholder">??</div>
                    <img id="modalLogoImg" class="modal-logo-img" src="" alt="logo" style="display:none;">
                </div>
                <h2 class="modal-title" id="modalTitle">Client Data</h2>
            </div>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>

        <div class="modal-tabs" id="modalTabs" style="display:none;">
            <div class="tab-link active" onclick="switchTab('info')"><i class="fas fa-user"></i> Profile</div>
            <div class="tab-link" onclick="switchTab('services')"><i class="fas fa-layer-group"></i> Services</div>
            <div class="tab-link" onclick="switchTab('vault')"><i class="fas fa-shield-alt"></i> Vault</div>
            <div class="tab-link" onclick="switchTab('creds')"><i class="fas fa-link"></i> Resources</div>
            <div class="tab-link" onclick="switchTab('legal')"><i class="fas fa-file-contract"></i> Dokumen</div>
            <?php if($is_super): ?><div class="tab-link" onclick="switchTab('history')"><i class="fas fa-history"></i> History</div><?php endif; ?>
        </div>

        <div class="modal-body-scroll">
            <form method="POST" id="clientForm" enctype="multipart/form-data">
                <input type="hidden" name="is_edit_mode" id="is_edit_mode" value="0">

                <!-- TAB INFO -->
                <div id="tab_info" class="tab-pane active">
                    <!-- LOGO UPLOAD -->
                    <div id="logoUploadSection" class="logo-upload-section" style="display:none;">
                        <div class="logo-upload-title"><i class="fas fa-image"></i> Logo Perusahaan</div>
                        <div id="currentLogoWrap" class="current-logo-wrap" style="display:none;">
                            <img id="currentLogoPreview" src="" alt="current" class="current-logo-img">
                            <div class="current-logo-label">Logo saat ini — upload baru untuk mengganti</div>
                        </div>
                        <div class="logo-upload-area" id="logoDropArea">
                            <div id="logoPreviewWrap" class="logo-preview-wrap" style="display:none;">
                                <img id="logoPreview" src="" alt="preview" class="logo-preview-img">
                                <button type="button" class="logo-remove-btn" onclick="removeLogo(event)"><i class="fas fa-times"></i></button>
                            </div>
                            <div id="logoUploadPrompt" class="logo-upload-prompt">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span id="logoPromptText">Klik atau drag logo di sini</span>
                                <small>PNG, JPG, SVG, WebP &bull; Max 2MB</small>
                            </div>
                            <input type="file" name="logo_file" id="logoInput" accept="image/*" style="display:none;" onchange="previewLogo(this)">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group"><label>Client ID</label><input type="text" name="client_id" id="f_id" class="form-input" readonly></div>
                        <div class="form-group"><label>Company Name</label><input type="text" name="company_name" id="f_name" class="form-input" required></div>
                        <div class="form-group"><label>Sector / Industri</label><input type="text" name="sector" id="f_sector" class="form-input" required></div>
                        <div class="form-group"><label>City / Address</label><input type="text" name="city" id="f_city" class="form-input" required></div>
                        <div class="form-group"><label><i class="fas fa-circle" style="color:var(--neon-main);margin-right:5px;"></i>Status Klien</label>
                            <select name="client_status" id="f_status" class="form-input fa-select" style="cursor:pointer; font-family:'Inter', 'Font Awesome 5 Free'; font-weight:900;">
                                <option value="Active">&#xf058; Aktif</option>
                                <option value="Inactive">&#xf057; Non-Aktif</option>
                            </select>
                        </div>
                        <input type="hidden" name="contract_type" id="f_contract_type" value="">
                        <input type="hidden" name="contract_start" id="f_start" value="">
                        <input type="hidden" name="contract_end" id="f_end" value="">
                        <div class="form-group"><label>PIC Name</label><input type="text" name="pic_name" id="f_pic" class="form-input" required></div>
                        <div class="form-group"><label>PIC Position</label><input type="text" name="pic_position" id="f_pos" class="form-input"></div>
                        <div class="form-group"><label>WhatsApp</label><input type="text" name="whatsapp" id="f_wa" class="form-input" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" id="f_email" class="form-input"></div>
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="f_phone" class="form-input"></div>
                    </div>
                </div>

                <!-- TAB RESOURCES -->
                <div id="tab_creds" class="tab-pane">

                    <div class="detail-section-title">PROJECT LINKS</div>
                    <div class="form-group"><label>Planner (Spreadsheet)</label><input type="text" name="link_planner" id="f_plan" class="form-input"><a href="#" target="_blank" class="input-link-btn" id="btn_plan"><i class="fas fa-external-link-alt"></i></a></div>
                    <div class="form-group"><label>Design (Canva / Figma)</label><input type="text" name="link_design" id="f_design" class="form-input"><a href="#" target="_blank" class="input-link-btn" id="btn_design"><i class="fas fa-external-link-alt"></i></a></div>
                    <div class="form-group"><label>Website / Socmed Link</label><input type="text" name="links" id="f_links" class="form-input"><a href="#" target="_blank" class="input-link-btn" id="btn_links"><i class="fas fa-external-link-alt"></i></a></div>
                    <div class="form-group"><label>Drive Link</label><input type="text" name="drive_link" id="f_drive" class="form-input"><a href="#" target="_blank" class="input-link-btn" id="btn_drive"><i class="fas fa-external-link-alt"></i></a></div>
                    <input type="hidden" name="drive_text" id="f_drive_text">

                    <div class="detail-section-title" style="margin-top:22px;">SEO &amp; CONTENT MONITORING</div>
                    <div class="form-group">
                        <label><i class="fas fa-chart-line" style="color:var(--neon-main);margin-right:5px;"></i>Artikel Monitoring (SEO)</label>
                        <input type="text" name="link_artikel" id="f_artikel" class="form-input" placeholder="Link spreadsheet / dashboard monitoring artikel">
                        <a href="#" target="_blank" class="input-link-btn" id="btn_artikel"><i class="fas fa-external-link-alt"></i></a>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-images" style="color:var(--neon-orange);margin-right:5px;"></i>Thumbnail Artikel</label>
                        <input type="text" name="link_thumbnail" id="f_thumbnail" class="form-input" placeholder="Link folder thumbnail (Drive / Canva / Figma)">
                        <a href="#" target="_blank" class="input-link-btn" id="btn_thumbnail"><i class="fas fa-external-link-alt"></i></a>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-link" style="color:var(--neon-purple);margin-right:5px;"></i>Other Link</label>
                        <input type="text" name="link_other" id="f_other" class="form-input" placeholder="Link lainnya (opsional)">
                        <a href="#" target="_blank" class="input-link-btn" id="btn_other"><i class="fas fa-external-link-alt"></i></a>
                    </div>

                    <div class="detail-section-title" style="margin-top:22px;">CREDENTIALS (Optional)</div>
                    <div class="form-group"><label>Instagram Creds</label><input type="text" name="cred_instagram" id="f_ig" class="form-input" placeholder="Username | Password"></div>
                    <div class="form-group"><label>TikTok Creds</label><input type="text" name="cred_tiktok" id="f_tt" class="form-input" placeholder="Username | Password"></div>
                    <div class="form-group"><label>YouTube Creds</label><input type="text" name="cred_youtube" id="f_yt" class="form-input" placeholder="Username | Password"></div>

                    <div class="detail-section-title" style="margin-top:22px;">CATATAN / NOTES</div>
                    <div class="form-group">
                        <label>Internal Notes</label>
                        <textarea name="notes" id="f_notes" class="form-input form-textarea" placeholder="Catatan internal, brief, instruksi khusus, reminder..."></textarea>
                    </div>
                </div>

                <!-- TAB SERVICES LIFECYCLE -->
                <div id="tab_services" class="tab-pane">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                        <div class="detail-section-title" style="margin-bottom:0;"><i class="fas fa-layer-group" style="color:var(--neon-sec);margin-right:6px;"></i>SERVICE LIFECYCLE</div>
                        <button type="button" class="btn-add-svc" id="btnAddService" onclick="addServiceRow()">
                            <i class="fas fa-plus"></i> Tambah Layanan
                        </button>
                    </div>

                    <!-- View Mode: cards (shown when viewing) -->
                    <div id="servicesViewContainer">
                        <div class="legal-empty" id="servicesViewEmpty"><i class="fas fa-boxes"></i><span>Belum ada data layanan tersimpan.</span></div>
                    </div>

                    <!-- Edit Mode: dynamic form rows (shown when editing) -->
                    <div id="servicesEditContainer" style="display:none;">
                        <div id="serviceRows"></div>
                        <p style="font-size:0.75rem;color:#555;margin-top:10px;"><i class="fas fa-info-circle"></i> Klik "Tambah Layanan" untuk menambah layanan baru. Setiap layanan memiliki periode & riwayat sendiri.</p>
                    </div>
                </div>

                <!-- TAB VAULT -->
                <div id="tab_vault" class="tab-pane">
                    <div class="vault-container">
                        <div class="vault-header">
                            <i class="fas fa-shield-alt"></i>
                            <div>
                                <h3>Credential & Access Vault</h3>
                                <p style="font-size:0.75rem;color:#666;margin-top:3px;">Data sensitif teknis. Dapat dibagikan ke Intern tanpa data finansial.</p>
                            </div>
                        </div>
                        <div class="vault-grid">
                            <div class="form-group">
                                <label><i class="fas fa-globe" style="color:var(--neon-main);margin-right:5px;"></i>Domain Registrar</label>
                                <input type="text" name="vault_domain" id="vault_domain" class="vault-input form-input" placeholder="Namecheap / GoDaddy / Niagahoster...">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar-times" style="color:var(--neon-red);margin-right:5px;"></i>Domain Expiry Date</label>
                                <input type="date" name="vault_expiry" id="vault_expiry" class="vault-input form-input">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-server" style="color:var(--neon-orange);margin-right:5px;"></i>Server IP / Hosting</label>
                                <input type="text" name="vault_server" id="vault_server" class="vault-input form-input" placeholder="103.xxx.xxx.xxx">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-terminal" style="color:var(--neon-purple);margin-right:5px;"></i>CPanel Login (user:pass@url)</label>
                                <input type="text" name="vault_cpanel" id="vault_cpanel" class="vault-input form-input" placeholder="admin:P@ssw0rd@cpanel.domain.com">
                            </div>
                            <div class="form-group full">
                                <label><i class="fab fa-wordpress" style="color:#3fcf8e;margin-right:5px;"></i>WP Admin (user:pass@url/wp-admin)</label>
                                <input type="text" name="vault_wp" id="vault_wp" class="vault-input form-input" placeholder="adminWP:P@ss@domain.com/wp-admin">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB LEGAL DOCS -->
                <div id="tab_legal" class="tab-pane">
                    <div class="legal-header">
                        <div class="detail-section-title" style="margin-bottom:0;">DOKUMEN LEGAL</div>
                        <button type="button" class="btn-add-doc" id="btnShowAddDoc" onclick="toggleAddDocForm()">
                            <i class="fas fa-plus"></i> Tambah Dokumen
                        </button>
                    </div>

                    <!-- Add doc form -->
                    <div class="add-doc-form" id="addDocForm" style="display:none;">
                        <div class="add-doc-form-inner">
                            <div class="add-doc-row">
                                <div class="form-group" style="margin-bottom:0;flex:1;">
                                    <label>Tipe Dokumen</label>
                                    <select id="ad_type" class="form-input">
                                        <option value="MoU">MoU / Kontrak</option>
                                        <option value="Proposal">Proposal</option>
                                        <option value="Invoice">Invoice</option>
                                        <option value="Addendum">Addendum</option>
                                        <option value="Kwitansi">Kwitansi</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;flex:2;">
                                    <label>Nama / Keterangan</label>
                                    <input type="text" id="ad_name" class="form-input" placeholder="Contoh: MoU Q1 2025, Invoice Mei...">
                                </div>
                            </div>
                            <div class="add-doc-or">
                                <div class="add-doc-or-line"></div>
                                <span>Upload File <em>atau</em> Paste Link</span>
                                <div class="add-doc-or-line"></div>
                            </div>
                            <div class="add-doc-row">
                                <div class="doc-upload-zone" id="docDropZone" onclick="document.getElementById('ad_file').click()">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span id="docUploadLabel">Klik atau drag file di sini</span>
                                    <small>PDF, DOC, XLS, JPG, ZIP &bull; Max 10MB</small>
                                    <input type="file" id="ad_file" style="display:none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" onchange="onDocFileChange(this)">
                                </div>
                                <div class="doc-or-divider"><span>atau</span></div>
                                <div class="form-group" style="margin-bottom:0;flex:1;align-self:center;">
                                    <label>Link Dokumen (Google Drive, dst)</label>
                                    <input type="text" id="ad_link" class="form-input" placeholder="https://drive.google.com/...">
                                </div>
                            </div>
                            <div class="add-doc-actions">
                                <button type="button" class="btn-doc-cancel" onclick="toggleAddDocForm()">Batal</button>
                                <button type="button" class="btn-doc-save" onclick="submitAddDoc()"><i class="fas fa-save"></i> Simpan Dokumen</button>
                            </div>
                        </div>
                    </div>

                    <!-- Docs list -->
                    <div id="legalDocsList" class="legal-docs-list">
                        <div class="legal-empty" id="legalEmpty">
                            <i class="fas fa-folder-open"></i>
                            <span>Belum ada dokumen legal tersimpan.</span>
                        </div>
                    </div>
                </div>

                <!-- TAB HISTORY -->
                <div id="tab_history" class="tab-pane">
                    <?php if($is_super): ?>
                    <div class="score-card">
                        <div class="score-circle" id="scoreValue">0%</div>
                        <div class="score-info">
                            <h4>Reliability Score</h4>
                            <p id="scoreStatus">Menganalisa...</p>
                        </div>
                    </div>
                    <div class="payment-summary-grid">
                        <div class="pay-sum-card total">
                            <div class="pay-sum-label"><i class="fas fa-wallet"></i> Total Semua Pembayaran</div>
                            <div class="pay-sum-val" id="sumTotalAll">Rp 0</div>
                        </div>
                        <div class="pay-sum-card period">
                            <div class="pay-sum-label"><i class="fas fa-calendar-check"></i> Dalam Periode Kontrak</div>
                            <div class="pay-sum-val" id="sumPeriod">Rp 0</div>
                            <div class="pay-sum-sub" id="sumPeriodRange">-</div>
                        </div>
                    </div>
                    <div class="breakdown-wrap">
                        <div class="detail-section-title">BREAKDOWN PER LAYANAN</div>
                        <div id="breakdownList" class="breakdown-list"></div>
                    </div>
                    <h4 style="color:#fff;margin:18px 0 10px;border-bottom:1px solid #1a1a1a;padding-bottom:8px;font-size:0.82rem;letter-spacing:1.5px;font-weight:800;">RIWAYAT TRANSAKSI</h4>
                    <div class="history-list" id="historyList"></div>
                    <?php else: ?>
                    <div style="text-align:center;padding:50px 20px;color:#444;">
                        <i class="fas fa-lock" style="font-size:2rem;display:block;margin-bottom:12px;color:#2a2a2a;"></i>
                        Data history hanya untuk Super Admin.
                    </div>
                    <?php endif; ?>
                </div>

                <div id="btnContainer" style="margin-top:20px;">
                    <button type="submit" name="save_client" class="btn-neon" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> Save Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- POPUP -->
<div id="popup" class="popup"><i class="fas fa-check-circle"></i> <span id="popupMsg">Success</span></div>

<script>
const modal = document.getElementById('clientModal');
const form  = document.getElementById('clientForm');

function closeModal(){ modal.classList.remove('active'); }

function openModal(){
    form.reset();
    document.getElementById('modalTabs').style.display = 'none';
    switchTab('info');
    document.getElementById('modalTitle').innerText = 'Add New Client';
    document.getElementById('btnContainer').style.display = 'block';
    document.getElementById('is_edit_mode').value = '0';
    document.getElementById('f_id').value = '<?php echo $auto_id; ?>';
    enableForm(true);
    toggleLinks(false);
    setModalLogo(null, '??');
    document.getElementById('logoUploadSection').style.display = 'block';
    document.getElementById('currentLogoWrap').style.display = 'none';
    document.getElementById('logoPromptText').innerText = 'Klik atau drag logo di sini';
    resetLogoPreview();
    modal.classList.add('active');
}

function fetchData(id, mode){
    const fd = new FormData();
    fd.append('action','get_client_data'); fd.append('id', id);
    fetch('index.php',{method:'POST',body:fd})
    .then(r => r.json())
    .then(res => {
        const d = res.data;
        // Fill all fields
        const set = (elId, val) => { const e=document.getElementById(elId); if(e) e.value=val||''; };
        set('f_id',        d.client_id);
        set('f_name',      d.company_name);
        set('f_sector',    d.sector);
        set('f_city',      d.city);
        set('f_start',     d.contract_start);
        set('f_end',       d.contract_end);
        set('f_pic',       d.pic_name);
        set('f_pos',       d.pic_position);
        set('f_wa',        d.whatsapp);
        set('f_email',     d.email);
        set('f_phone',     d.phone);
        set('f_links',     d.link_social);
        set('f_drive',     d.link_drive);
        set('f_plan',      d.link_planner);
        set('f_design',    d.link_design);
        set('f_artikel',   d.link_artikel);
        set('f_thumbnail', d.link_thumbnail);
        set('f_other',     d.link_other);
        set('f_ig',        d.cred_instagram);
        set('f_tt',        d.cred_tiktok);
        set('f_yt',        d.cred_youtube);
        set('f_notes',     d.notes);

        // Link buttons
        setupLinkBtn('btn_plan',      d.link_planner);
        setupLinkBtn('btn_design',    d.link_design);
        setupLinkBtn('btn_links',     d.link_social);
        setupLinkBtn('btn_drive',     d.link_drive);
        setupLinkBtn('btn_artikel',   d.link_artikel);
        setupLinkBtn('btn_thumbnail', d.link_thumbnail);
        setupLinkBtn('btn_other',     d.link_other);

        // Services
        document.querySelectorAll('.service-check').forEach(c => c.checked = false);
        if(d.contract_type) d.contract_type.split(', ').forEach(s => {
            const chk = document.querySelector(`input[value="${s.trim()}"]`);
            if(chk) chk.checked = true;
        });

        // Modal logo (header)
        setModalLogo(d.logo_path, d.company_name);

        // Logo upload section
        if(mode === 'edit'){
            document.getElementById('logoUploadSection').style.display = 'block';
            const clw = document.getElementById('currentLogoWrap');
            const clp = document.getElementById('currentLogoPreview');
            if(d.logo_path && d.logo_path.trim() !== ''){
                clp.src = d.logo_path;
                clw.style.display = 'flex';
                document.getElementById('logoPromptText').innerText = 'Upload logo baru untuk mengganti';
            } else {
                clw.style.display = 'none';
                document.getElementById('logoPromptText').innerText = 'Klik atau drag logo di sini';
            }
            resetLogoPreview();
        } else {
            document.getElementById('logoUploadSection').style.display = 'none';
            document.getElementById('currentLogoWrap').style.display = 'none';
        }

        // History / score
        const sc = document.getElementById('scoreValue');
        if(sc){ sc.innerText = res.score+'%'; sc.className='score-circle '+res.score_color; }
        const ss = document.getElementById('scoreStatus');
        if(ss) ss.innerText = res.score_status;

        const fmt = n => 'Rp '+Number(n||0).toLocaleString('id-ID');
        const el  = i => document.getElementById(i);
        if(el('sumTotalAll'))   el('sumTotalAll').innerText   = fmt(res.total_all);
        if(el('sumPeriod'))     el('sumPeriod').innerText     = fmt(res.total_in_period);
        if(el('sumPeriodRange') && res.contract_start && res.contract_end)
            el('sumPeriodRange').innerText = fmtDate(res.contract_start)+' – '+fmtDate(res.contract_end);

        // Breakdown
        const bList = el('breakdownList');
        if(bList){
            const cols = {'Web Dev':'#a1ff5a','SEO':'#4efdc4','Social Media':'#ff9f43','Branding':'#c084fc','Content Creator':'#f87171','General':'#6b7280'};
            const tot  = res.total_all||1;
            let bHtml  = '';
            for(const [svc,amt] of Object.entries(res.totals_by_service||{})){
                const pct = Math.round((amt/tot)*100);
                const col = cols[svc]||'#8888ff';
                bHtml += `<div class="breakdown-item">
                    <div class="breakdown-top">
                        <span class="breakdown-svc" style="color:${col}">${svc}</span>
                        <span class="breakdown-amt">${fmt(amt)} <small>(${pct}%)</small></span>
                    </div>
                    <div class="breakdown-bar-bg"><div class="breakdown-bar-fill" style="width:${pct}%;background:${col};"></div></div>
                </div>`;
            }
            bList.innerHTML = bHtml||'<div style="color:#444;font-size:0.82rem;padding:4px 0;">Belum ada data.</div>';
        }

        // History list
        const hList = el('historyList');
        if(hList){
            if(!res.history||res.history.length===0){
                hList.innerHTML='<div style="color:#555;text-align:center;padding:24px;">Belum ada riwayat transaksi.</div>';
            } else {
                hList.innerHTML = res.history.map(h=>`
                <div class="history-item">
                    <div class="hist-left">
                        <span class="hist-desc">${h.desc} <span class="hist-svc">${h.service}</span></span>
                        <span class="hist-date">${h.date}</span>
                    </div>
                    <div class="hist-right">
                        <div class="hist-amount">+ Rp ${h.amount}</div>
                        <div class="hist-type">${h.type}</div>
                    </div>
                </div>`).join('');
            }
        }

        modal.classList.add('active');
        if(mode==='view'){
            document.getElementById('modalTitle').innerText = d.company_name||'Detail Client';
            document.getElementById('btnContainer').style.display = 'none';
            document.getElementById('modalTabs').style.display = 'flex';
            switchTab('info');
            enableForm(false);
            toggleLinks(true);
        } else {
            document.getElementById('modalTitle').innerText = 'Edit: '+(d.company_name||'Client');
            document.getElementById('btnContainer').style.display = 'block';
            document.getElementById('modalTabs').style.display = 'flex';
            document.getElementById('is_edit_mode').value = '1';
            enableForm(true);
            toggleLinks(false);
        }

        // Render legal docs
        renderLegalDocs(res.legal_docs || []);
        // Store current client id for doc actions
        window._currentClientId = d.client_id;
        // Reset add doc form
        resetAddDocForm();

        // === RENDER SERVICES ===
        renderServicesView(res.services_data || []);
        if(mode === 'edit') {
            populateServicesEdit(res.services_data || []);
            // Set status dropdown
            const statusEl = document.getElementById('f_status');
            if(statusEl) statusEl.value = d.status || 'Active';
        } else {
            document.getElementById('servicesEditContainer').style.display = 'none';
            document.getElementById('servicesViewContainer').style.display = 'block';
            document.getElementById('btnAddService').style.display = 'none';
        }

        // === RENDER VAULT ===
        const crd = res.credentials_data || {};
        const sv = (id, val) => { const e=document.getElementById(id); if(e) e.value=val||''; };
        sv('vault_domain',  crd.domain_registrar);
        sv('vault_expiry',  crd.domain_expiry);
        sv('vault_server',  crd.server_ip);
        sv('vault_cpanel',  crd.cpanel_login);
        sv('vault_wp',      crd.wp_login);
    })
    .catch(err=>{ console.error(err); showPopup('error','Gagal memuat data.'); });
}

function viewDetail(id){ fetchData(id,'view'); }
function editDetail(id){ fetchData(id,'edit'); }

// LOGO
function setModalLogo(logoPath, name){
    const ph=document.getElementById('modalLogoPlaceholder');
    const img=document.getElementById('modalLogoImg');
    if(logoPath&&logoPath.trim()!==''){
        img.src=logoPath; img.style.display='block'; ph.style.display='none';
    } else {
        img.style.display='none'; ph.style.display='flex';
        ph.innerText=(name||'??').substring(0,2).toUpperCase();
    }
}

function previewLogo(input){
    if(input.files&&input.files[0]){
        const reader=new FileReader();
        reader.onload=e=>{
            document.getElementById('logoPreview').src=e.target.result;
            document.getElementById('logoPreviewWrap').style.display='flex';
            document.getElementById('logoUploadPrompt').style.display='none';
            const img=document.getElementById('modalLogoImg');
            img.src=e.target.result; img.style.display='block';
            document.getElementById('modalLogoPlaceholder').style.display='none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeLogo(e){
    if(e) e.stopPropagation();
    document.getElementById('logoInput').value='';
    resetLogoPreview();
}

function resetLogoPreview(){
    document.getElementById('logoPreviewWrap').style.display='none';
    document.getElementById('logoUploadPrompt').style.display='flex';
    document.getElementById('logoPreview').src='';
}



function setupLinkBtn(btnId,url){
    const btn=document.getElementById(btnId); if(!btn) return;
    if(url&&url.trim()!==''){ btn.classList.add('show'); btn.href=(!url.match(/^https?:\/\//i)?'https://':'')+url; }
    else btn.classList.remove('show');
}
function toggleLinks(show){
    if(!show) document.querySelectorAll('.input-link-btn').forEach(b=>b.classList.remove('show'));
    else {
        // Re-trigger setup for view mode so buttons show
        ['f_plan','f_design','f_links','f_drive','f_artikel','f_thumbnail','f_other'].forEach(fid => {
            const btnMap = {f_plan:'btn_plan',f_design:'btn_design',f_links:'btn_links',f_drive:'btn_drive',f_artikel:'btn_artikel',f_thumbnail:'btn_thumbnail',f_other:'btn_other'};
            const inp = document.getElementById(fid);
            if(inp) setupLinkBtn(btnMap[fid], inp.value);
        });
    }
}

// TABS
function switchTab(tab){
    document.querySelectorAll('.tab-link').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
    const map={info:0,services:1,vault:2,creds:3,legal:4,history:5};
    const tabs=document.querySelectorAll('.tab-link');
    if(tabs[map[tab]]!==undefined) tabs[map[tab]].classList.add('active');
    const pane=document.getElementById('tab_'+tab);
    if(pane) pane.classList.add('active');
}

// FORM ENABLE
function enableForm(status){
    form.querySelectorAll('input,textarea,select').forEach(i=>{
        if(i.id==='f_id') return;
        if(i.type==='checkbox'||i.type==='radio') i.disabled=!status;
        else if(i.type==='file') i.disabled=!status;
        else i.readOnly=!status;
    });
}

// DATE FORMAT
function fmtDate(d){ if(!d) return '-'; return new Date(d).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}); }

// POPUP
function showPopup(type,msg){
    const p=document.getElementById('popup');
    document.getElementById('popupMsg').innerText=msg;
    p.className='popup '+type;
    p.querySelector('i').className=type==='error'?'fas fa-exclamation-triangle':'fas fa-check-circle';
    p.classList.add('show');
    setTimeout(()=>p.classList.remove('show'),3500);
}

<?php if(isset($_SESSION['popup'])): ?>
showPopup("<?php echo $_SESSION['popup']['type']; ?>","<?php echo addslashes($_SESSION['popup']['msg']); ?>");
<?php unset($_SESSION['popup']); ?>
<?php endif; ?>

// =====================
// LEGAL DOCS
// =====================
const docTypeIcon = {
    'MoU':      {icon:'fa-handshake',      color:'var(--neon-main)'},
    'Proposal': {icon:'fa-file-alt',       color:'var(--neon-sec)'},
    'Invoice':  {icon:'fa-file-invoice',   color:'var(--neon-orange)'},
    'Addendum': {icon:'fa-file-signature', color:'var(--neon-purple)'},
    'Kwitansi': {icon:'fa-receipt',        color:'#f87171'},
    'Other':    {icon:'fa-file',           color:'#6b7280'},
};

function renderLegalDocs(docs){
    const list  = document.getElementById('legalDocsList');
    const empty = document.getElementById('legalEmpty');
    if(!list) return;
    // Remove old items
    list.querySelectorAll('.legal-doc-item').forEach(el=>el.remove());

    if(!docs || docs.length === 0){
        if(empty) empty.style.display = 'flex';
        return;
    }
    if(empty) empty.style.display = 'none';

    docs.forEach(doc => {
        const ti = docTypeIcon[doc.type] || docTypeIcon['Other'];
        const href = doc.file ? doc.file : (doc.link || '#');
        const isLink = !!doc.link && !doc.file;
        const dateStr = doc.date ? new Date(doc.date).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) : '-';
        const item = document.createElement('div');
        item.className = 'legal-doc-item';
        item.dataset.id = doc.id;
        item.innerHTML = `
            <div class="doc-icon-wrap" style="color:${ti.color};border-color:${ti.color}20;background:${ti.color}0d;">
                <i class="fas ${ti.icon}"></i>
            </div>
            <div class="doc-info">
                <div class="doc-name">${escHtml(doc.name)}</div>
                <div class="doc-meta">
                    <span class="doc-type-badge" style="color:${ti.color};border-color:${ti.color}30;background:${ti.color}10;">${escHtml(doc.type)}</span>
                    <span class="doc-date"><i class="fas fa-calendar-alt"></i> ${dateStr}</span>
                    ${isLink ? '<span class="doc-source"><i class="fas fa-link"></i> Link</span>' : '<span class="doc-source"><i class="fas fa-paperclip"></i> File</span>'}
                </div>
            </div>
            <div class="doc-actions">
                <a href="${escHtml(href)}" target="_blank" class="doc-btn-open" title="Buka Dokumen"><i class="fas fa-external-link-alt"></i></a>
                <button type="button" class="doc-btn-del" title="Hapus" onclick="deleteDoc('${escHtml(doc.id)}')"><i class="fas fa-trash-alt"></i></button>
            </div>`;
        list.appendChild(item);
    });
}

function escHtml(s){ const d=document.createElement('div');d.appendChild(document.createTextNode(s||''));return d.innerHTML; }

function toggleAddDocForm(){
    const f = document.getElementById('addDocForm');
    f.style.display = (f.style.display === 'none' || f.style.display === '') ? 'block' : 'none';
    if(f.style.display === 'none') resetAddDocForm();
}

function resetAddDocForm(){
    const ids = ['ad_type','ad_name','ad_link'];
    ids.forEach(i=>{ const e=document.getElementById(i); if(e) e.value=''; });
    const adType = document.getElementById('ad_type');
    if(adType) adType.value = 'MoU';
    const adFile = document.getElementById('ad_file');
    if(adFile) adFile.value = '';
    const lbl = document.getElementById('docUploadLabel');
    if(lbl) lbl.innerText = 'Klik atau drag file di sini';
    const dz = document.getElementById('docDropZone');
    if(dz) dz.classList.remove('has-file');
    const af = document.getElementById('addDocForm');
    if(af) af.style.display = 'none';
}

function onDocFileChange(input){
    const lbl = document.getElementById('docUploadLabel');
    const dz  = document.getElementById('docDropZone');
    if(input.files && input.files[0]){
        lbl.innerText = input.files[0].name;
        dz.classList.add('has-file');
        document.getElementById('ad_link').value = '';
    } else {
        lbl.innerText = 'Klik atau drag file di sini';
        dz.classList.remove('has-file');
    }
}

// Drag & drop for doc zone
document.addEventListener('DOMContentLoaded',()=>{
    const da = document.getElementById('logoDropArea');
    if(da){
        ['dragenter','dragover'].forEach(e=>da.addEventListener(e,ev=>{ev.preventDefault();da.classList.add('drag-over');}));
        ['dragleave','drop'].forEach(e=>da.addEventListener(e,ev=>{ev.preventDefault();da.classList.remove('drag-over');}));
        da.addEventListener('click',()=>document.getElementById('logoInput').click());
        da.addEventListener('drop',ev=>{
            const f=ev.dataTransfer.files[0];
            if(f){ const dt=new DataTransfer(); dt.items.add(f); document.getElementById('logoInput').files=dt.files; previewLogo(document.getElementById('logoInput')); }
        });
    }

    const dz = document.getElementById('docDropZone');
    if(dz){
        ['dragenter','dragover'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.add('drag-over');}));
        ['dragleave','drop'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.remove('drag-over');}));
        dz.addEventListener('drop',ev=>{
            ev.preventDefault(); dz.classList.remove('drag-over');
            const f = ev.dataTransfer.files[0];
            if(f){ const dt=new DataTransfer(); dt.items.add(f); const inp=document.getElementById('ad_file'); inp.files=dt.files; onDocFileChange(inp); }
        });
    }
});

function submitAddDoc(){
    const cid  = window._currentClientId;
    if(!cid){ showPopup('error','Client tidak terdeteksi.'); return; }
    const fd   = new FormData();
    fd.append('action',     'add_legal_doc');
    fd.append('client_id',  cid);
    fd.append('doc_type',   document.getElementById('ad_type').value);
    fd.append('doc_name',   document.getElementById('ad_name').value);
    fd.append('doc_link',   document.getElementById('ad_link').value);
    const fileInp = document.getElementById('ad_file');
    if(fileInp && fileInp.files[0]) fd.append('doc_file', fileInp.files[0]);

    const btn = document.querySelector('.btn-doc-save');
    if(btn){ btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Menyimpan...'; }

    fetch('index.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(res=>{
        if(btn){ btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Simpan Dokumen'; }
        if(res.ok){
            showPopup('success','Dokumen berhasil disimpan!');
            resetAddDocForm();
            // Re-fetch to refresh list
            fetchData(cid, document.getElementById('is_edit_mode').value==='1'?'edit':'view');
            // Stay on legal tab after refetch - override switchTab after fetch
            setTimeout(()=>switchTab('legal'),300);
        } else {
            showPopup('error', res.msg || 'Gagal menyimpan dokumen.');
        }
    })
    .catch(()=>{ if(btn){ btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Simpan Dokumen'; } showPopup('error','Koneksi error.'); });
}

function deleteDoc(docId){
    if(!confirm('Hapus dokumen ini?')) return;
    const cid = window._currentClientId;
    if(!cid) return;
    const fd = new FormData();
    fd.append('action',    'delete_legal_doc');
    fd.append('client_id', cid);
    fd.append('doc_id',    docId);
    fetch('index.php',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(res=>{
        if(res.ok){
            showPopup('success','Dokumen dihapus.');
            fetchData(cid, document.getElementById('is_edit_mode').value==='1'?'edit':'view');
            setTimeout(()=>switchTab('legal'),300);
        } else {
            showPopup('error', res.msg||'Gagal menghapus.');
        }
    })
    .catch(()=>showPopup('error','Koneksi error.'));
}


// =====================
// MULTI-SERVICE LIFECYCLE
// =====================
const svcColors = {
    'Web Dev':        '#a1ff5a',
    'SEO':            '#4efdc4',
    'Social Media':   '#ff9f43',
    'Branding':       '#c084fc',
    'Content Creator':'#f87171',
    'Ads':            '#60a5fa',
    'Other':          '#6b7280',
};

function renderServicesView(svcs) {
    const container = document.getElementById('servicesViewContainer');
    const emptyEl = document.getElementById('servicesViewEmpty');
    // Clear old
    container.querySelectorAll('.ms-card').forEach(e => e.remove());
    if (!svcs || svcs.length === 0) {
        if(emptyEl) emptyEl.style.display = 'flex';
        return;
    }
    if(emptyEl) emptyEl.style.display = 'none';
    const today = new Date();
    svcs.forEach(s => {
        const isActive = (s.status === 'Active');
        const statusClass = isActive ? 'active' : 'inactive';
        const statusLabel = isActive ? 'Active' : 'Inactive';
        const color = svcColors[s.type] || '#888';
        const kwHtml = s.keywords ? `<div class="ms-keywords"><i class="fas fa-search" style="margin-right:5px;opacity:0.6;"></i><strong>Keywords:</strong><br>${s.keywords.split(',').map(k=>`<span style="display:inline-block;background:rgba(78,253,196,0.08);border:1px solid rgba(78,253,196,0.15);border-radius:4px;padding:2px 8px;margin:3px 4px 0 0;font-size:0.75rem;">${k.trim()}</span>`).join('')}</div>` : '';
        const notesHtml = s.notes ? `<div style="margin-top:4px;padding:16px;background:rgba(255,255,255,0.02);border-radius:12px;border:1px solid rgba(255,255,255,0.05);"><div style="font-size:0.7rem;color:var(--neon-main);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;display:flex;align-items:center;gap:6px;"><i class="fas fa-sticky-note"></i> Catatan Khusus</div><div style="font-size:0.85rem;color:#ddd;line-height:1.6;">${s.notes}</div></div>` : '';
        const daysLeft = s.end ? Math.ceil((new Date(s.end) - today) / 86400000) : null;
        const daysHtml = daysLeft !== null && daysLeft >= 0 && daysLeft <= 30
            ? `<span style="font-size:0.65rem;padding:4px 10px;background:${daysLeft<=7?'rgba(255,90,90,0.15)':daysLeft<=14?'rgba(255,159,67,0.15)':'rgba(245,197,24,0.1)'};color:${daysLeft<=7?'#ff5a5a':daysLeft<=14?'#ff9f43':'#f5c518'};border-radius:20px;font-weight:800;margin-left:12px;letter-spacing:1px;border:1px solid ${daysLeft<=7?'rgba(255,90,90,0.3)':daysLeft<=14?'rgba(255,159,67,0.3)':'rgba(245,197,24,0.3)'};">⚠ H-${daysLeft}</span>` : '';
        const card = document.createElement('div');
        card.className = 'ms-card';
        card.style.setProperty('--card-color', color);
        card.innerHTML = `
            <div class="ms-header">
                <div>
                    <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1px;font-weight:700;">${s.type}</div>
                    <div class="ms-title">${s.type} Service${daysHtml}</div>
                </div>
                <span class="ms-status ${statusClass}">${statusLabel}</span>
            </div>
            <div class="ms-detail">
                <div><span class="ms-detail-label">Mulai</span><span class="ms-detail-val">${s.start ? new Date(s.start).toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'}) : '-'}</span></div>
                <div><span class="ms-detail-label">Berakhir</span><span class="ms-detail-val">${s.end ? new Date(s.end).toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'}) : '-'}</span></div>
                ${s.price ? `<div><span class="ms-detail-label">Harga/Bulan</span><span class="ms-detail-val">Rp ${parseInt(s.price).toLocaleString('id-ID')}</span></div>` : ''}
            </div>
            ${kwHtml}
            ${notesHtml}
        `;
        container.appendChild(card);
    });
}

function populateServicesEdit(svcs) {
    document.getElementById('servicesViewContainer').style.display = 'none';
    document.getElementById('servicesEditContainer').style.display = 'block';
    document.getElementById('btnAddService').style.display = 'inline-flex';
    const rowsEl = document.getElementById('serviceRows');
    rowsEl.innerHTML = '';
    if (svcs && svcs.length > 0) {
        svcs.forEach(s => addServiceRow(s));
    }
}

const SVC_META = {
    'Web Dev':        {icon:'fa-code',            color:'#a1ff5a', bg:'rgba(161,255,90,0.06)'},
    'SEO':            {icon:'fa-search',          color:'#4efdc4', bg:'rgba(78,253,196,0.06)'},
    'Social Media':   {icon:'fa-hashtag',         color:'#ff9f43', bg:'rgba(255,159,67,0.06)'},
    'Branding':       {icon:'fa-paint-brush',     color:'#c084fc', bg:'rgba(192,132,252,0.06)'},
    'Content Creator':{icon:'fa-video',           color:'#f87171', bg:'rgba(248,113,113,0.06)'},
    'Ads':            {icon:'fa-bullhorn',        color:'#60a5fa', bg:'rgba(96,165,250,0.06)'},
    'Other':          {icon:'fa-cube',            color:'#6b7280', bg:'rgba(107,114,128,0.06)'},
};

let _svcRowCount = 0;
function addServiceRow(data) {
    _svcRowCount++;
    const i = _svcRowCount;
    const rowsEl = document.getElementById('serviceRows');
    if(!rowsEl) return;

    const svcTypes = ['Web Dev','SEO','Social Media','Branding','Content Creator','Ads','Other'];
    // Premium icon dropdown options
    const opts = svcTypes.map(t => {
        const m = SVC_META[t] || SVC_META['Other'];
        return `<option value="${t}" ${data && data.type===t?'selected':''}>${t}</option>`;
    }).join('');

    const row = document.createElement('div');
    row.className = 'svc-row';
    row.id = `svc-row-${i}`;
    row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:16px;margin-bottom:12px;position:relative;';

    const curType = (data && data.type) ? data.type : 'Web Dev';
    const curMeta = SVC_META[curType] || SVC_META['Other'];

    row.innerHTML = `
        <div style="grid-column:1/-1;display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid rgba(255,255,255,0.05);padding-bottom:10px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:${curMeta.bg};color:${curMeta.color};display:flex;align-items:center;justify-content:center;">
                    <i class="fas ${curMeta.icon}"></i>
                </div>
                <span class="svc-type-label" style="font-size:0.82rem;font-weight:800;color:var(--neon-main);">${curType}</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <select name="svc_status[]" class="form-input" style="background:transparent;border:1px solid rgba(255,255,255,0.06);color:#888;font-size:0.75rem;padding:4px 10px;width:auto;cursor:pointer;border-radius:20px;">
                    <option value="Active" ${!data||data.status==='Active'?'selected':''}>Active</option>
                    <option value="Inactive" ${data&&data.status==='Inactive'?'selected':''}>Inactive</option>
                </select>
                <button type="button" class="btn-remove-svc" onclick="this.closest('.svc-row').remove()" title="Hapus">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <div class="form-group" style="margin:0;">
            <label><i class="fas fa-layer-group" style="color:#888;margin-right:4px;"></i>Tipe Layanan</label>
            <select name="svc_type[]" class="form-input svc-type-select" style="height:38px;" onchange="updateSvcRowStyle(this)">
                ${opts}
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label><i class="fas fa-calendar-plus" style="color:var(--neon-sec);margin-right:4px;"></i>Tanggal Mulai</label>
            <input type="date" name="svc_start[]" class="form-input" value="${data&&data.start?data.start:''}">
        </div>
        <div class="form-group" style="margin:0;">
            <label><i class="fas fa-calendar-check" style="color:var(--neon-red);margin-right:4px;"></i>Tanggal Selesai</label>
            <input type="date" name="svc_end[]" class="form-input" value="${data&&data.end?data.end:''}">
        </div>
        <div class="form-group" style="margin:0;">
            <label><i class="fas fa-tag" style="color:var(--neon-main);margin-right:4px;"></i>Harga Deal</label>
            <input type="text" name="svc_price[]" class="form-input" placeholder="Rp 5.000.000/bln" value="${data&&data.price?data.price:''}">
        </div>
        <div class="form-group" style="margin:0;grid-column:span 2;">
            <label><i class="fas fa-search" style="color:var(--neon-sec);margin-right:4px;"></i>Top Keywords SEO (pisah koma, opsional)</label>
            <input type="text" name="svc_keywords[]" class="form-input" placeholder="keyword 1, keyword 2, keyword 3..." value="${data&&data.keywords?data.keywords:''}">
        </div>
        <div class="form-group" style="margin:0;grid-column:1/-1;">
            <label><i class="fas fa-sticky-note" style="color:var(--neon-orange);margin-right:4px;"></i>Catatan Internal Layanan ini</label>
            <textarea name="svc_notes[]" class="form-input" rows="2" style="resize:vertical;min-height:58px;" placeholder="Brief, instruksi khusus, reminder untuk layanan ini...">${data&&data.notes?data.notes.replace(/</g,'&lt;'):''}</textarea>
        </div>
    `;
    rowsEl.appendChild(row);
}

function updateSvcRowStyle(selectEl) {
    const row = selectEl.closest('.svc-row');
    if(!row) return;
    const t = selectEl.value;
    const m = SVC_META[t] || SVC_META['Other'];
    row.querySelector('.svc-icon-badge').style.background = m.bg;
    row.querySelector('.svc-icon-badge').style.borderColor = m.color+'44';
    row.querySelector('.svc-icon-badge i').className = `fas ${m.icon}`;
    row.querySelector('.svc-icon-badge i').style.color = m.color;
    row.querySelector('.svc-type-label').textContent = t;
    row.querySelector('.svc-type-label').style.color = m.color;
}

window.onclick=e=>{ if(e.target===modal) closeModal(); };

</script>
<?php endif; ?>
</body>
</html>
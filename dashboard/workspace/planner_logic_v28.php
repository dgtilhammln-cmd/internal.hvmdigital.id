<?php
// 1. CONFIG & ERROR HANDLING
ini_set('display_errors', 0);
session_start();
date_default_timezone_set('Asia/Jakarta');

// Koneksi Database
$root = $_SERVER['DOCUMENT_ROOT'];
if (file_exists($root . '/includes/db_connect.php')) include_once $root . '/includes/db_connect.php';
elseif (file_exists('../../includes/db_connect.php')) include_once '../../includes/db_connect.php';

if(!isset($_SESSION['admin'])) exit;

// 2. GET PARAMETERS
$mode = $_GET['mode'] ?? 'month';
$dateInput = $_GET['date'] ?? date('Y-m-d');
$timestamp = strtotime($dateInput);

$year = date('Y', $timestamp);
$month = date('n', $timestamp);
$today = date('Y-m-d');

// 3. FETCH EVENTS
if($mode == 'month') {
    $start_q = date('Y-m-01', $timestamp);
    $end_q = date('Y-m-t', $timestamp);
} elseif($mode == 'week') {
    if(date('w', $timestamp) == 0) $start_q = date('Y-m-d', $timestamp);
    else $start_q = date('Y-m-d', strtotime('last sunday', $timestamp));
    $end_q = date('Y-m-d', strtotime($start_q . ' +6 days'));
} else {
    $start_q = $dateInput; $end_q = $dateInput;
}

$events = [];
$check = mysqli_query($conn, "SHOW TABLES LIKE 'events'");
if(mysqli_num_rows($check) > 0) {
    // Select kolom detail juga
    $q = mysqli_query($conn, "SELECT * FROM events WHERE event_date BETWEEN '$start_q' AND '$end_q' ORDER BY time_start ASC");
    while($row = mysqli_fetch_assoc($q)){
        $events[$row['event_date']][] = $row;
    }
}

// 4. FETCH HARI LIBUR NASIONAL INDONESIA (WITH SESSION CACHE UNTUK SPEED)
$holidays = [];
$cache_key = "holidays_{$year}_{$month}";
if(isset($_SESSION[$cache_key])) {
    $holidays = $_SESSION[$cache_key];
} else {
    $holiday_api_url = "https://api.harilibur.co.id/api?month=$month&year=$year";
    $holiday_ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
    $holiday_raw = @file_get_contents($holiday_api_url, false, $holiday_ctx);
    if($holiday_raw) {
        $holiday_data = json_decode($holiday_raw, true);
        if(is_array($holiday_data)) {
            foreach($holiday_data as $h) {
                if(!empty($h['holiday_date']) && !empty($h['holiday_name'])) {
                    $holidays[$h['holiday_date']] = htmlspecialchars($h['holiday_name'], ENT_QUOTES);
                }
            }
            // Save to cache for the entire session
            $_SESSION[$cache_key] = $holidays;
        }
    }
}

// --- INJECT CLIENT SERVICE DEADLINES ---
$q_clients_deadlines = mysqli_query($conn, "SELECT company_name, services_data FROM clients WHERE status='Active' AND services_data IS NOT NULL AND services_data != '' AND services_data != '[]'");
if ($q_clients_deadlines) {
    while ($cl = mysqli_fetch_assoc($q_clients_deadlines)) {
        $svcs = json_decode($cl['services_data'], true);
        if (!is_array($svcs)) continue;
        foreach ($svcs as $svc) {
            if (empty($svc['end']) || ($svc['status'] ?? 'Active') !== 'Active') continue;
            $endDate = $svc['end'];
            // Cek apakah tanggal berakhir ada di rentang kalender saat ini
            if ($endDate >= $start_q && $endDate <= $end_q) {
                $type = $svc['type'] ?? 'Layanan';
                $notes = htmlspecialchars($svc['notes'] ?? '-', ENT_QUOTES);
                // Hapus newline agar tidak merusak JS function onClick
                $notes = str_replace(["\r", "\n"], ' ', $notes);
                
                $events[$endDate][] = [
                    'title'      => 'Exp: ' . $type . ' - ' . $cl['company_name'],
                    'color'      => 'red',
                    'detail'     => "Layanan $type untuk klien " . $cl['company_name'] . " berakhir hari ini.<br>Harga: " . ($svc['price'] ?? '-') . "<br>Catatan: " . $notes,
                    'time_start' => '00:00'
                ];
            }
        }
    }
}

// 4. RENDER VIEWS

// --- VIEW: MONTH ---
if($mode == 'month') {
    echo '<div class="cal-grid-month">';
    
    // Header Hari (INDONESIA)
    $days = ['MNG', 'SEN', 'SLS', 'RBU', 'KMS', 'JMT', 'SBT'];
    foreach($days as $day) echo "<div class='cal-day-header'>$day</div>";

    $firstDayIndex = date('w', strtotime("$year-$month-01"));
    for($i=0; $i<$firstDayIndex; $i++) echo "<div class='cal-day-empty'></div>";

    $daysInMonth = date('t', $timestamp);
    for($d=1; $d<=$daysInMonth; $d++) {
        $currentDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($d, 2, '0', STR_PAD_LEFT);
        $isToday = ($currentDate == $today) ? 'today' : '';
        $dayOfWeek = date('w', strtotime($currentDate));
        $isSunday = ($dayOfWeek == 0) ? 'is-sunday' : '';

        $dayEvents = $events[$currentDate] ?? [];
        $isHoliday = isset($holidays[$currentDate]);
        $holidayName = $isHoliday ? htmlspecialchars($holidays[$currentDate], ENT_QUOTES) : '';
        
        // Build list of categorized dots for this cell
        $dots = [];

        // 1. Libur Hari Besar (Red)
        if ($isHoliday) {
            $dots[] = [
                'type'   => 'holiday',
                'color'  => '#ff5a5a',
                'shadow' => 'rgba(255,90,90,0.8)',
                'title'  => 'Libur: ' . $holidays[$currentDate]
            ];
        }

        // 2. Events: Meeting (Green) vs Masa Berakhir (Yellow)
        foreach ($dayEvents as $ev) {
            $title = $ev['title'] ?? '';
            $color = strtolower($ev['color'] ?? '');
            
            $isExp = (strpos($title, 'Exp:') !== false || 
                      strpos($title, 'berakhir') !== false || 
                      strpos($title, 'Expired') !== false || 
                      $color === 'red' || 
                      $color === 'yellow' || 
                      $color === 'orange');

            if ($isExp) {
                // Kuning = Masa Berakhir / Expired
                $dots[] = [
                    'type'   => 'exp',
                    'color'  => '#ffb900',
                    'shadow' => 'rgba(255,185,0,0.8)',
                    'title'  => $title
                ];
            } else {
                // Hijau = Meeting / Kunjungan
                $dots[] = [
                    'type'   => 'meeting',
                    'color'  => '#a1ff5a',
                    'shadow' => 'rgba(161,255,90,0.8)',
                    'title'  => $title
                ];
            }
        }

        $dotCount = count($dots);
        $dotsHtml = '';
        if ($dotCount > 0) {
            $dotsHtml .= "<div class='cal-dots-wrap' style='display:flex; gap:3px; align-items:center; margin-top:auto; height:12px;'>";
            if ($dotCount <= 4) {
                foreach ($dots as $dt) {
                    $c = $dt['color'];
                    $sh = $dt['shadow'];
                    $tt = htmlspecialchars($dt['title'], ENT_QUOTES);
                    $dotsHtml .= "<span class='cal-dot' title='$tt' style='width:6px; height:6px; border-radius:50%; background:$c; box-shadow:0 0 5px $sh; display:inline-block;'></span>";
                }
            } else {
                $hasGreen = false; $hasYellow = false; $hasRed = false;
                foreach ($dots as $dt) {
                    if ($dt['type'] === 'meeting') $hasGreen = true;
                    if ($dt['type'] === 'exp') $hasYellow = true;
                    if ($dt['type'] === 'holiday') $hasRed = true;
                }
                $pillColor = $hasRed ? '#ff5a5a' : ($hasYellow ? '#ffb900' : '#a1ff5a');
                $dotsHtml .= "<span class='cal-dot-pill' style='font-size:0.6rem; background:rgba(255,255,255,0.15); color:#ffffff; padding:1px 5px; border-radius:6px; font-weight:800; display:inline-flex; align-items:center; gap:3px;'><i class='fas fa-circle' style='font-size:0.4rem; color:$pillColor;'></i> $dotCount</span>";
            }
            $dotsHtml .= "</div>";
        } else {
            $dotsHtml .= "<div style='height:12px;'></div>";
        }

        $isHoliday = isset($holidays[$currentDate]) ? 'is-holiday' : '';
        $holidayDot = isset($holidays[$currentDate]) ? "<span style='font-size:0.55rem; color:#aaaaaa;' title='".htmlspecialchars($holidays[$currentDate], ENT_QUOTES)."'><i class='fas fa-star'></i></span>" : "";

        $dateNice = date('j M Y', strtotime($currentDate));
        $hasEvents = count($dayEvents) > 0;
        $jsonEvAttr = $hasEvents ? htmlspecialchars(json_encode(array_values($dayEvents)), ENT_QUOTES, 'UTF-8') : '';
        $clickAttr = $hasEvents ? "onclick=\"handleDayClick('$currentDate', $jsonEvAttr)\"" : "onclick=\"openEventModal('$currentDate')\"";

        echo "<div class='cal-day-cell $isToday $isSunday $isHoliday' data-date='$currentDate' data-date-nice='$dateNice' data-events='$jsonEvAttr' data-holiday='$holidayName' $clickAttr>
                <div style='display:flex; justify-content:space-between; align-items:center; width:100%;'>
                    <div class='cal-day-num'>$d</div>
                    $holidayDot
                </div>
                $dotsHtml
              </div>";
    }
    echo '</div>';
}

// --- VIEW: WEEK ---
elseif($mode == 'week') {
    if(date('w', $timestamp) == 0) $startOfWeek = $timestamp;
    else $startOfWeek = strtotime('last sunday', $timestamp);

    echo '<div class="cal-grid-week">';
    for($i=0; $i<7; $i++) {
        $currTs = strtotime("+$i days", $startOfWeek);
        $dateStr = date('Y-m-d', $currTs);
        $dayName = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'][date('w', $currTs)];
        $dayNum = date('d', $currTs);
        $isToday = ($dateStr == $today) ? 'today' : '';

        echo "<div class='cal-week-col' onclick=\"openEventModal('$dateStr')\">";
        echo "<div class='cal-week-header $isToday' style='padding:10px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.03);'>";
        echo "<div style='font-size:0.7rem; color:#888;'>$dayName</div>";
        echo "<div style='font-size:1.1rem; font-weight:800; color:#fff;'>$dayNum</div>";
        echo "</div>";
        
        echo "<div class='cal-week-body' style='padding:8px; min-height:100px;'>";
        if(isset($events[$dateStr])) {
            foreach($events[$dateStr] as $ev) {
                $safeTitle = htmlspecialchars($ev['title'], ENT_QUOTES);
                $safeDesc = htmlspecialchars($ev['detail'] ?? 'Belum ada detail.', ENT_QUOTES);
                $evId2 = isset($ev['id']) ? intval($ev['id']) : 0;
                echo "<div class='cal-event' onclick=\"event.stopPropagation(); showEventDetail('$safeTitle', '$dateStr', '{$ev['time_start']}', '$safeDesc', 'white', $evId2)\" style='margin-bottom:6px; padding:6px 8px; background:rgba(255,255,255,0.08); border-left:3px solid #ffffff; border-radius:6px; color:#ffffff; font-size:0.75rem;'>
                        <div style='font-size:0.6rem; opacity:0.7;'>{$ev['time_start']}</div>
                        <div style='font-weight:700; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;'>{$ev['title']}</div>
                      </div>";
            }
        }
        echo "</div></div>";
    }
    echo '</div>';
}

// --- VIEW: DAY ---
elseif($mode == 'day') {
    $dateStr = date('Y-m-d', $timestamp);
    $dayIndo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w', $timestamp)];
    $dayNameFull = $dayIndo . ", " . date('d F Y', $timestamp);

    echo '<div class="cal-grid-day">';
    echo "<h3 style='text-align:center; color:#fff; margin-bottom:20px; font-weight:800; letter-spacing:1px;'>$dayNameFull</h3>";
    
    if(isset($events[$dateStr])) {
        foreach($events[$dateStr] as $ev) {
            $safeTitle = htmlspecialchars($ev['title'], ENT_QUOTES);
            $safeDesc = htmlspecialchars($ev['detail'] ?? 'Belum ada detail.', ENT_QUOTES);
            $evId3 = isset($ev['id']) ? intval($ev['id']) : 0;
            echo "<div class='cal-hour-row' onclick=\"showEventDetail('$safeTitle', '$dateStr', '{$ev['time_start']}', '$safeDesc', 'white', $evId3)\" style='display:flex; gap:15px; padding:15px; border-bottom:1px solid rgba(255,255,255,0.05); align-items:center; cursor:pointer;'>
                    <div class='cal-time' style='width:60px; font-weight:700; color:#ffffff;'>{$ev['time_start']}</div>
                    <div class='cal-task-area' style='flex:1; background:rgba(255,255,255,0.03); padding:10px; border-radius:8px; border-left:3px solid #ffffff;'>
                        <div style='font-weight:700; color:#fff; font-size:1rem;'>{$ev['title']}</div>
                    </div>
                  </div>";
        }
    } else {
        echo "<div style='text-align:center; padding:40px; color:#666;'>
                Belum ada meeting.<br>
                <button onclick=\"openEventModal('$dateStr')\" style='background:#ffffff; border:none; color:#000; padding:8px 20px; border-radius:20px; cursor:pointer; margin-top:12px; font-weight:bold;'>+ Tambah Meeting</button>
              </div>";
    }
    echo '</div>';
}
?>
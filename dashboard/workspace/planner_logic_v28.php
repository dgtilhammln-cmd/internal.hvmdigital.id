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
                $events[$endDate][] = [
                    'title'      => 'DEADLINE: ' . $cl['company_name'] . ' (' . ($svc['type'] ?? 'Layanan') . ')',
                    'color'      => 'red',
                    'detail'     => "Layanan " . ($svc['type'] ?? 'Layanan') . " untuk klien " . $cl['company_name'] . " berakhir hari ini.\nHarga: " . ($svc['price'] ?? '-') . "\nCatatan: " . ($svc['notes'] ?? '-'),
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
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    foreach($days as $day) echo "<div class='cal-day-header'>$day</div>";

    $firstDayIndex = date('w', strtotime("$year-$month-01"));
    for($i=0; $i<$firstDayIndex; $i++) echo "<div></div>";

    $daysInMonth = date('t', $timestamp);
    for($d=1; $d<=$daysInMonth; $d++) {
        $currentDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($d, 2, '0', STR_PAD_LEFT);
        $isToday = ($currentDate == $today) ? 'today' : '';
        $dayOfWeek = date('w', strtotime($currentDate));
        $isSunday = ($dayOfWeek == 0) ? 'is-sunday' : '';

        // Render Events
        $eventHtml = '';
        if(isset($events[$currentDate])) {
            foreach($events[$currentDate] as $ev) {
                $color = $ev['color'] ?? 'blue';
                $title = (strlen($ev['title']) > 15) ? substr($ev['title'],0,12).'..' : $ev['title'];
                
                // Encode data for JS
                $safeTitle = htmlspecialchars($ev['title'], ENT_QUOTES);
                $safeDesc = htmlspecialchars($ev['detail'] ?? 'Belum ada detail.', ENT_QUOTES);
                $safeTime = $ev['time_start'];
                
                // CLICK EVENT CHIP
                $eventHtml .= "<div class='cal-event $color' onclick=\"event.stopPropagation(); showEventDetail('$safeTitle', '$currentDate', '$safeTime', '$safeDesc', '$color')\">$title</div>";
            }
        }

        // CLICK CELL (ADD)
        echo "<div class='cal-day-cell $isToday $isSunday' data-date='$currentDate' onclick=\"openEventModal('$currentDate')\">
                <div class='cal-day-num'>$d</div>
                <div class='holiday-label-container' style='font-size:0.6rem; color:var(--neon-red); font-weight:700; margin-bottom:2px; line-height:1; min-height:0px;'></div>
                $eventHtml
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
        echo "<div class='cal-week-header $isToday' style='padding:10px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.03);'>";
        echo "<div style='font-size:0.7rem; color:#666;'>$dayName</div>";
        echo "<div style='font-size:1.2rem; font-weight:800; color:".(date('w',$currTs)==0?'var(--neon-red)':'#fff').";'>$dayNum</div>";
        echo "</div>";
        
        echo "<div class='cal-week-body' style='padding:10px; min-height:100px;'>";
        if(isset($events[$dateStr])) {
            foreach($events[$dateStr] as $ev) {
                $color = $ev['color'] ?? 'blue';
                $safeTitle = htmlspecialchars($ev['title'], ENT_QUOTES);
                $safeDesc = htmlspecialchars($ev['detail'] ?? 'Belum ada detail.', ENT_QUOTES);
                
                echo "<div class='cal-event $color' onclick=\"event.stopPropagation(); showEventDetail('$safeTitle', '$dateStr', '{$ev['time_start']}', '$safeDesc', '$color')\" style='margin-bottom:5px; padding:8px;'>
                        <div style='font-size:0.6rem; opacity:0.8;'>{$ev['time_start']}</div>
                        <div>{$ev['title']}</div>
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
            $color = $ev['color'] ?? 'blue';
            $safeTitle = htmlspecialchars($ev['title'], ENT_QUOTES);
            $safeDesc = htmlspecialchars($ev['detail'] ?? 'Belum ada detail.', ENT_QUOTES);
            $borderCol = ($color == 'blue') ? '#4efdc4' : (($color == 'purple') ? '#a55eea' : '#a1ff5a');
            
            echo "<div class='cal-hour-row' onclick=\"showEventDetail('$safeTitle', '$dateStr', '{$ev['time_start']}', '$safeDesc', '$color')\" style='display:flex; gap:15px; padding:15px; border-bottom:1px solid rgba(255,255,255,0.05); align-items:center; cursor:pointer;'>
                    <div class='cal-time' style='width:60px; font-weight:700; color:$borderCol;'>{$ev['time_start']}</div>
                    <div class='cal-task-area' style='flex:1; background:rgba(255,255,255,0.02); padding:10px; border-radius:8px; border-left:3px solid $borderCol;'>
                        <div style='font-weight:700; color:#fff; font-size:1rem;'>{$ev['title']}</div>
                    </div>
                  </div>";
        }
    } else {
        echo "<div style='text-align:center; padding:40px; color:#555;'>
                No events.<br>
                <button onclick=\"openEventModal('$dateStr')\" style='background:none; border:1px solid #a1ff5a; color:#a1ff5a; padding:8px 20px; border-radius:20px; cursor:pointer; margin-top:10px; font-weight:bold;'>+ Add Event</button>
              </div>";
    }
    echo '</div>';
}
?>
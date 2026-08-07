<?php
/* ==========================================================
   TITAN EMAIL ENGINE - TRACKING ANALYTICS
   ========================================================== */
require_once __DIR__ . '/../config/database.php';

// Ambil Data Tracking
$q_track = "SELECT t.*, con.name, con.email, c.subject
            FROM email_tracking t
            JOIN email_queue q ON t.queue_id = q.id
            JOIN email_contacts con ON q.contact_id = con.id
            JOIN email_campaigns c ON q.campaign_id = c.id
            ORDER BY t.clicked_at DESC LIMIT 50";
$res_track = mysqli_query($conn, $q_track);

$total_clicks = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM email_tracking"))['total'];
?>

<div class="page-headline">
    <h1>Link Engagement</h1>
    <p>Track every interaction and identify your warmest leads.</p>
</div>

<div class="glass-card" style="background: linear-gradient(135deg, rgba(161, 255, 90, 0.1) 0%, rgba(15,15,15,0.7) 100%); margin-bottom: 30px;">
    <div style="display: flex; align-items: center; gap: 20px;">
        <div style="font-size: 3rem; color: var(--primary-green);"><i class="fas fa-mouse-pointer"></i></div>
        <div>
            <h4 style="margin: 0; color: #888; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 2px;">Total Link Interactions</h4>
            <h1 style="margin: 0; font-size: 2.5rem; font-weight: 800;"><?= $total_clicks ?> <small style="font-size: 1rem; color: #444;">Clicks</small></h1>
        </div>
    </div>
</div>

<h2 style="font-weight: 800; font-size: 1.2rem; margin-bottom: 20px;"><i class="fas fa-history" style="color: var(--primary-green);"></i> Recent Activity</h2>

<div class="asset-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
    <?php if(mysqli_num_rows($res_track) > 0): ?>
        <?php while($row = mysqli_fetch_assoc($res_track)): ?>
            <div class="glass-card" style="padding: 20px; border: 1px solid rgba(161, 255, 90, 0.2);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <span style="font-size: 0.6rem; font-weight: 800; background: rgba(161, 255, 90, 0.1); color: var(--primary-green); padding: 4px 10px; border-radius: 50px;">CLICK DETECTED</span>
                    <small style="color: #555; font-size: 0.7rem;"><?= date('H:i | d M', strtotime($row['clicked_at'])) ?></small>
                </div>
                <h3 style="margin: 0; font-size: 1rem; color: #fff;"><?= $row['name'] ?></h3>
                <p style="margin: 5px 0; font-size: 0.8rem; color: #777;"><?= $row['email'] ?></p>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); font-size: 0.75rem; color: #aaa;">
                    Campaign: <b><?= substr($row['subject'], 0, 30) ?>...</b>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="grid-column: span 3; padding: 50px; text-align: center; opacity: 0.3;">
            <i class="fas fa-chart-bar fa-4x mb-3"></i>
            <h3>No data activity yet.</h3>
        </div>
    <?php endif; ?>
</div>
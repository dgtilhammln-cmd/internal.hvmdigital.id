<?php
include 'includes/db_connect.php';
$q = mysqli_query($conn, "SELECT company_name, services_data FROM clients WHERE company_name LIKE '%alatrumah%'");
while($r = mysqli_fetch_assoc($q)) {
    echo $r['company_name'] . " -> " . $r['services_data'] . "\n";
}

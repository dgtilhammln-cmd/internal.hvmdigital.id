<?php
include_once __DIR__ . '/../includes/db_connect.php';

$res = mysqli_query($conn, "SELECT payment_date, amount FROM payments ORDER BY payment_date DESC LIMIT 10");
echo "Payments:\n";
while($row = mysqli_fetch_assoc($res)) {
    echo $row['payment_date'] . ' - ' . $row['amount'] . "\n";
}

$res2 = mysqli_query($conn, "SELECT created_at FROM clients ORDER BY created_at DESC LIMIT 10");
echo "\nClients:\n";
while($row = mysqli_fetch_assoc($res2)) {
    echo $row['created_at'] . "\n";
}

<?php
require 'includes/db_connect.php';

// Add new columns
mysqli_query($conn, "ALTER TABLE prospects ADD COLUMN tier ENUM('B2B Kecil','B2B Menengah','B2B Besar') DEFAULT 'B2B Kecil' AFTER catatan");
mysqli_query($conn, "ALTER TABLE prospects ADD COLUMN is_synced TINYINT(1) DEFAULT 0 AFTER status");

// Since we can't alter an ENUM easily if data violates it, we first map the data
// Wait, we can modify the ENUM to include both old and new, then update, then remove old.
mysqli_query($conn, "ALTER TABLE prospects MODIFY COLUMN status ENUM('Cold','Warm','Hot','Closed','Prospecting','Follow Up','Negotiation','Deal','Lost') DEFAULT 'Prospecting'");

// Map old statuses to new pipeline statuses
mysqli_query($conn, "UPDATE prospects SET status = 'Prospecting' WHERE status = 'Cold'");
mysqli_query($conn, "UPDATE prospects SET status = 'Follow Up' WHERE status = 'Warm'");
mysqli_query($conn, "UPDATE prospects SET status = 'Negotiation' WHERE status = 'Hot'");
mysqli_query($conn, "UPDATE prospects SET status = 'Deal' WHERE status = 'Closed'");

// Clean up ENUM
mysqli_query($conn, "ALTER TABLE prospects MODIFY COLUMN status ENUM('Prospecting','Follow Up','Negotiation','Deal','Lost') DEFAULT 'Prospecting'");

echo "Migration completed.";

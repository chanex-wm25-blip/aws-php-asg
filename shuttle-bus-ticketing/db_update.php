<?php
require 'config.php';

$query1 = "ALTER TABLE tickets ADD COLUMN status ENUM('pending', 'confirmed', 'done', 'cancelled') DEFAULT 'pending'";
if ($conn->query($query1)) {
    echo "Successfully added status column to RDS!<br>";
} else {
    echo "Column notice: " . $conn->error . "<br>";
}

$query2 = "UPDATE tickets SET status = 'pending' WHERE status IS NULL OR status = ''";
if ($conn->query($query2)) {
    echo "Successfully updated existing tickets to pending!";
} else {
    echo "Update error: " . $conn->error;
}
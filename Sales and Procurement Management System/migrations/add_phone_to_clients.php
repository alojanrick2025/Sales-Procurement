<?php
/**
 * Migration: Add phone column to clients table
 * Run this once to add phone support to the clients table
 */

require_once __DIR__ . '/../config.php';

// Only allow admin access
if (!isAdminLoggedIn()) {
    die("Access denied. Admin login required.");
}

$conn = getDBConnection();

// Check if phone column already exists
$checkColumn = $conn->query("SHOW COLUMNS FROM clients LIKE 'phone'");
$columnExists = $checkColumn->num_rows > 0;

if (!$columnExists) {
    // Add phone column after city
    $sql = "ALTER TABLE clients ADD COLUMN phone VARCHAR(20) NULL AFTER city";
    
    if ($conn->query($sql) === TRUE) {
        echo "Phone column added successfully to clients table!<br>";
    } else {
        echo "Error adding phone column: " . $conn->error . "<br>";
    }
} else {
    echo "Phone column already exists in clients table.<br>";
}

$conn->close();
echo "<a href='clients.php'>Go to Clients List</a>";
?>




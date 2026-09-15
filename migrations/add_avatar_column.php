<?php
/**
 * Migration: Add avatar column to users table
 * Run this once to add avatar support to the users table
 */

require_once __DIR__ . '/../config.php';

// Only allow admin access
if (!isAdminLoggedIn()) {
    die("Access denied. Admin login required.");
}

$conn = getDBConnection();

// Check if avatar column already exists
$checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
$columnExists = $checkColumn->num_rows > 0;

if (!$columnExists) {
    // Add avatar column after password
    $sql = "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER password";
    
    if ($conn->query($sql) === TRUE) {
        echo "Avatar column added successfully after password column!<br>";
    } else {
        echo "Error adding avatar column: " . $conn->error . "<br>";
    }
} else {
    echo "Avatar column already exists.<br>";
    echo "If you need to reposition it, you may need to drop and recreate it manually.<br>";
}

$conn->close();
echo "<a href='profile.php'>Go to User List</a>";
?>


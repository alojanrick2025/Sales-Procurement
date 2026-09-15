<?php
header('Content-Type: text/plain');
require_once 'config.php';

echo "Connecting to database...\n";
$conn = getDBConnection();

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = file_get_contents('database.sql');
if (!$sql) {
    die("Error: Could not read database.sql file\n");
}

echo "Building tables...\n";
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    echo "SUCCESS: All tables created!\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
$conn->close();
?>

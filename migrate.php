<?php
require_once 'config.php';
$conn = getDBConnection();
if ($conn->connect_error) die("Connection failed");

$sql = file_get_contents('database.sql');
if ($conn->multi_query($sql)) {
    while ($conn->more_results() && $conn->next_result());
    echo "SUCCESS: Database Built!";
} else {
    echo "ERROR";
}
?>

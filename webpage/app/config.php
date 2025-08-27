<?php
// Database connection details
$host = 'localhost';  // Database host
$db_username = 'wokki20_chat_root';   // Database username
$db_password = 'pM00/kOb34=j';       // Database password
$dbname = 'wokki20_chat'; // Database name


// Create a connection
$mysqli = new mysqli($host, $db_username, $db_password, $dbname);

// Check the connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

?>

<?php
$host = 'db';
$db_username = 'root';
$db_password = 'dev';
$dbname = 'wokki_chat';
$mail_password = '';
$allowedOrigin = 'https://localhost:8443';
$allowedReferer = 'localhost';
$mobile_hmac_secret = 'same-as-app-secret';

$mysqli = new mysqli($host, $db_username, $db_password, $dbname);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

?>

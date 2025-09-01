<?php
include '../app/config.php';
session_start();
$access_token = $_COOKIE['access_token'];

if (!$access_token) {
    header('Location: /login?redirect=/connections/chat');
    exit;
}

$stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ?");
$stmt->bind_param("s", $access_token);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
}
$stmt->close();

if (!$user_id) {
    header('Location: /login?redirect=/developer/bots');
    exit;
}

$unlinkStmt = $mysqli->prepare("DELETE FROM user_connections WHERE user_id = ? AND connection_name = 'Chat'");
$unlinkStmt->bind_param("i", $user_id);
$unlinkStmt->execute();
$unlinkStmt->close();

header("Location: /settings/connections");
?>

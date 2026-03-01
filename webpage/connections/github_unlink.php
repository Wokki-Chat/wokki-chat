<?php
include '../app/config.php';
session_start();
$access_token = $_COOKIE['access_token'];

if (!$access_token) {
    header('Location: /login?redirect=/settings/connections');
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
    header('Location: /login?redirect=/settings/connections');
    exit;
}

$unlinkStmt = $mysqli->prepare("DELETE FROM user_connections WHERE user_id = ? AND connection_name = 'GitHub'");
$unlinkStmt->bind_param("i", $user_id);
$unlinkStmt->execute();
$unlinkStmt->close();

$deleteGitHubWidget = $mysqli->prepare("DELETE FROM profile_widgets WHERE user_id = ? AND widget_name = 'GitHub'");
$deleteGitHubWidget->bind_param("i", $user_id);
$deleteGitHubWidget->execute();
$deleteGitHubWidget->close();

header("Location: /settings/connections");
?>

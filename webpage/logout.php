<?php
include 'app/config.php';

$from = $_GET['from'] ?? '/login';

$access_token = $_COOKIE['access_token'];
header("Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if ($access_token) {
    $stmt = $mysqli->prepare("DELETE FROM user_tokens WHERE access_token = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("si", $access_token, $user_id);
    $stmt->execute();
    $stmt->close();
    setcookie('access_token', '', time() - 3600, '/');
    setcookie('dm_active_user', '', time() - 3600, '/');
    if (isset($_COOKIE['theme']) && str_starts_with($_COOKIE['theme'], 'chat_')) {
        setcookie('theme', 'dark', 0, '/');
    }

    header('Location: ' . $from);
    exit;
} else {
    header('Location: ' . $from);
    exit;
}


?>
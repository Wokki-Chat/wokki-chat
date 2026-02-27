<?php
include '../app/config.php';
include './config.php';
session_start();
$access_token = $_COOKIE['access_token'];

if (!$access_token) {
    header('Location: /login?redirect=/connections/github');
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
    header('Location: /login?redirect=/connections/github');
    exit;
}

$CLIENT_ID = $GITHUB_CLIENT_ID;
$CLIENT_SECRET = $GITHUB_CLIENT_SECRET;
$REDIRECT_URI = $GITHUB_REDIRECT_URI;

if (!isset($_GET['code']) && !isset($_SESSION['spotify_access_token'])) {
    $state = substr(bin2hex(random_bytes(5)), 0, 10);

    $authUrl = "https://github.com/login/oauth/authorize?" . http_build_query([
        'response_type' => 'code',
        'client_id' => $CLIENT_ID,
        'redirect_uri' => $REDIRECT_URI,
        'scope' => 'read:user,public_repo',
        'state' => $state
    ]);

    echo "<script>
        window.location.href = '$authUrl';
    </script>";
    exit;
}

if (isset($_GET['code'])) {
    $postData = http_build_query([
        'client_id' => $CLIENT_ID,
        'client_secret' => $CLIENT_SECRET,
        'code' => $_GET['code'],
        'redirect_uri' => $REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ]);

    $ch = curl_init("https://github.com/login/oauth/access_token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!isset($data['access_token'])) {
        header("Location: /home");
        exit;
    }

    $_SESSION['github_access_token'] = $data['access_token'];
    $refreshToken = $data['refresh_token'] ?? null;
    $widget_name = 'GitHub';

    $widgetStmt = $mysqli->prepare("INSERT INTO profile_widgets (user_id, widget_name, widget_access_token, widget_refresh_token) VALUES (?, ?, ?, ?)");
    $widgetStmt->bind_param("isss", $user_id, $widget_name, $data['access_token'], $refreshToken);
    $widgetStmt->execute();
    $widgetStmt->close();

    $apiUrl = "https://api.github.com/user";

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $_SESSION['github_access_token'],
        "User-Agent: Wokki Chat"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode !== 200 || isset($data['error'])) {
        header("Location: /home");
        exit;
    }

    $connection_user_id = $data['id'];
    $connection_user_name = $data['login'];
    $connection_user_url = $data['html_url'];
    $connection_name = "GitHub";
    $connection_user_image = $data['avatar_url'] ?? null;

    $stmt = $mysqli->prepare("
        INSERT INTO user_connections (connection_name, user_id, connection_user_id, connection_user_name, connection_user_url, connection_user_image)
        SELECT ?, ?, ?, ?, ?, ?
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM user_connections WHERE user_id = ? AND connection_user_id = ? AND connection_user_name = ?
        )
    ");
    $stmt->bind_param(
        "sissssiss",
        $connection_name,
        $user_id,
        $connection_user_id,
        $connection_user_name,
        $connection_user_url,
        $connection_user_image,
        $user_id,
        $connection_user_id,
        $connection_user_name
    );
    $stmt->execute();
    $stmt->close();

    $_SESSION['github_access_token'] = null;

    header("Location: /settings/connections");
    exit;
}
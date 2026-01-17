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
    header('Location: /login?redirect=/connections/chat');
    exit;
}

$CLIENT_ID = "17671397377916288815";
$CLIENT_SECRET = "5C7IUraIVMX5DxxMdOsWyokSJcQU1uxzkMjB";
$REDIRECT_URI = "https://chat.wokki20.nl/connections/chat";

if (!isset($_GET['code']) && !isset($_SESSION['chat_access_token'])) {
    $state = substr(bin2hex(random_bytes(5)), 0, 10);

    $authUrl = "https://chat.jonazwetsloot.nl/oauth/authorize?" . http_build_query([
        'response_type' => 'code',
        'client_id' => $CLIENT_ID,
        'redirect_uri' => $REDIRECT_URI,
        'scope' => 'profile',
        'force_login' => true,
    ]);

    echo "<script>
        window.location.href = '$authUrl';
    </script>";
    exit;
}

if (isset($_GET['code'])) {
    $postData = [
        'grant_type' => 'authorization_code',
        'code' => $_GET['code'],
        'redirect_uri' => $REDIRECT_URI,
        'client_id' => $CLIENT_ID,
        'client_secret' => $CLIENT_SECRET
    ];

    $ch = curl_init("https://chat.jonazwetsloot.nl/oauth/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!isset($data['access_token'])) {
        header("Location: /home");
    }

    $_SESSION['chat_access_token'] = $data['access_token'];

    header("Location: $REDIRECT_URI");
    exit;
}

if (!isset($_SESSION['chat_access_token'])) {
    die("Not logged in. Please log in first.");
}

$apiUrl = "https://chat.jonazwetsloot.nl/oauth/userinfo";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $_SESSION['chat_access_token']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode !== 200 || isset($data['error'])) {
    header("Location: /home");
}

$connection_user_id = null;
$connection_user_name = $data['name'];
$connection_user_url = $data['profile'];
$connection_name = "Chat";
$connection_user_image = $data['picture'];

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

$stmt = $mysqli->prepare("SELECT premium, premium_expires_at, added_chat_account_once FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $premium = $row['premium'];
    $premium_expires_at = $row['premium_expires_at'];
    $already_added_chat_account_once = $row['added_chat_account_once'] === 1;
}
$stmt->close();

if ((!$premium || ($premium_expires_at !== null && $premium_expires_at <= time())) && !$already_added_chat_account_once) {
    $stmt = $mysqli->prepare("UPDATE users SET premium = 1, premium_expires_at = DATE_ADD(NOW(), INTERVAL 1 MONTH), premium_know = 0, added_chat_account_once = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

$_SESSION['chat_access_token'] = null;

header("Location: /settings/connections");
?>

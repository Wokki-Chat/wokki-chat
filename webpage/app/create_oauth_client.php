<?php
include 'config.php';

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 0');
}

header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

function generateUUIDv4() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'description' => 'Method not allowed', 'return_code' => 1]);
    exit;
}

if (isset($_SERVER['HTTP_ORIGIN'])) {
    if ($_SERVER['HTTP_ORIGIN'] !== $allowedOrigin) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'description' => 'Forbidden: Invalid Origin', 'return_code' => 38]);
        exit;
    }
} elseif (isset($_SERVER['HTTP_REFERER'])) {
    $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    if ($referer !== $allowedReferer) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'description' => 'Forbidden: Invalid Referer', 'return_code' => 39]);
        exit;
    }
} else {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'description' => 'Forbidden: No Origin or Referer', 'return_code' => 40]);
    exit;
}

$headers = getallheaders();
if (!isset($headers['Authorization']) || !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'description' => 'Unauthorized: Missing or invalid Authorization header', 'return_code' => 41]);
    exit;
}

$access_token = $matches[1];

$stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ? AND access_token_expires_at > NOW()");
$stmt->bind_param("s", $access_token);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row || !isset($row['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'description' => 'Unauthorized: Invalid or expired access token', 'return_code' => 42]);
    exit;
}

$user_id = $row['user_id'];

$bot_id = $_POST['bot_id'] ?? null;
$name = $_POST['name'] ?? null;
$redirect_uris = $_POST['redirect_uris'] ?? null;

if (!$bot_id || !$name || !$redirect_uris) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'description' => 'Bad Request: bot_id, name and redirect_uris are required', 'return_code' => 43]);
    exit;
}

$stmt = $mysqli->prepare("SELECT id FROM bots WHERE id = ? AND created_by = ?");
$stmt->bind_param("si", $bot_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bot = $result->fetch_assoc();
$stmt->close();

if (!$bot) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'description' => 'Forbidden: Bot not found or you do not own it', 'return_code' => 44]);
    exit;
}

if (strlen($name) < 3) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'description' => 'name must be at least 3 characters', 'return_code' => 45]);
    exit;
}

if (strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'description' => 'name cannot be longer than 100 characters', 'return_code' => 46]);
    exit;
}

$uris = is_array($redirect_uris) ? $redirect_uris : explode(',', $redirect_uris);
$uris = array_map('trim', $uris);

foreach ($uris as $uri) {
    if (!filter_var($uri, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'description' => 'Bad Request: Invalid redirect_uri: ' . $uri, 'return_code' => 47]);
        exit;
    }
}

$stmt = $mysqli->prepare("SELECT id FROM oauth_clients WHERE bot_id = ?");
$stmt->bind_param("s", $bot_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'description' => 'This bot already has an OAuth client', 'return_code' => 48]);
    exit;
}

$client_id = generateUUIDv4();
$client_secret_raw = bin2hex(random_bytes(32));
$client_secret_hashed = password_hash($client_secret_raw, PASSWORD_BCRYPT);
$oauth_client_id = generateUUIDv4();

$stmt = $mysqli->prepare("INSERT INTO oauth_clients (id, name, client_id, client_secret, bot_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $oauth_client_id, $name, $client_id, $client_secret_hashed, $bot_id);
$stmt->execute();
$stmt->close();

foreach ($uris as $uri) {
    $uri_id = generateUUIDv4();
    $stmt = $mysqli->prepare("INSERT INTO oauth_client_redirect_uris (id, client_id, uri) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $uri_id, $client_id, $uri);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    'status' => 'success',
    'description' => 'OAuth client created',
    'return_code' => 0,
    'client_id' => $client_id,
    'client_secret' => $client_secret_raw
]);
exit;
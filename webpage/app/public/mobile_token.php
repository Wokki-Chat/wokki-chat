<?php
ob_start();
include 'config.php';

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (isset($_SERVER['HTTP_ORIGIN'])) {
	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Max-Age: 0');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	header('Access-Control-Allow-Methods: POST, OPTIONS');
	header('Access-Control-Allow-Headers: Content-Type, X-App-Signature, X-App-Timestamp, X-Device-ID');
	http_response_code(204);
	exit;
}

header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

define('APP_HMAC_SECRET', $mobile_hmac_secret);
define('SIGNATURE_WINDOW', 300);
define('RATE_LIMIT_WINDOW', 900);
define('RATE_LIMIT_MAX', 10);

function generate_token(int $bytes = 32): string {
	return bin2hex(random_bytes($bytes));
}

function json_error(string $error, string $description, int $code = 400): never {
	$junk = ob_get_clean();
	http_response_code($code);
	echo json_encode(['error' => $error, 'error_description' => $description, 'debug_output' => $junk]);
	exit;
}

function verify_app_signature(string $raw_body, string $device_id): void {
	$signature = $_SERVER['HTTP_X_APP_SIGNATURE'] ?? '';
	$timestamp = $_SERVER['HTTP_X_APP_TIMESTAMP'] ?? '';

	if (!$signature || !$timestamp) {
		json_error('invalid_request', 'Missing app signature', 401);
	}

	if (!ctype_digit($timestamp)) {
		json_error('invalid_request', 'Invalid timestamp', 401);
	}

	$ts = (int) $timestamp;
	if (abs(time() - $ts) > SIGNATURE_WINDOW) {
		json_error('invalid_request', 'Request expired', 401);
	}

	$expected = hash_hmac('sha256', $timestamp . '.' . $device_id . '.' . $raw_body, APP_HMAC_SECRET);
	if (!hash_equals($expected, $signature)) {
		json_error('invalid_request', 'Invalid app signature', 401);
	}
}

function check_rate_limit(string $ip): void {
	global $mysqli;

	$window = RATE_LIMIT_WINDOW;
	$stmt = $mysqli->prepare("SELECT COUNT(*) FROM mobile_token_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
	$stmt->bind_param("si", $ip, $window);
	$stmt->execute();
	$count = $stmt->get_result()->fetch_row()[0];
	$stmt->close();

	if ($count >= RATE_LIMIT_MAX) {
		json_error('rate_limited', 'Too many requests, try again later', 429);
	}

	$stmt = $mysqli->prepare("INSERT INTO mobile_token_attempts (ip, attempted_at) VALUES (?, NOW())");
	$stmt->bind_param("s", $ip);
	$stmt->execute();
	$stmt->close();
}

function validate_device_id(string $device_id): bool {
	return strlen($device_id) >= 16 && strlen($device_id) <= 64 && ctype_alnum(str_replace(['-', '_'], '', $device_id));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	json_error('invalid_request', 'Only POST method is allowed', 405);
}

$raw_body = file_get_contents('php://input');
$body = json_decode($raw_body, true) ?? [];

$device_id = $_SERVER['HTTP_X_DEVICE_ID'] ?? '';

if (!$device_id || !validate_device_id($device_id)) {
	json_error('invalid_request', 'Missing or invalid device ID', 401);
}

verify_app_signature($raw_body, $device_id);

$ip = $_SERVER['REMOTE_ADDR'];
check_rate_limit($ip);

$grant_type = $body['grant_type'] ?? null;
$client_id = $body['client_id'] ?? null;

if (!$grant_type || !$client_id) {
	json_error('invalid_request', 'Missing grant_type or client_id');
}

$stmt = $mysqli->prepare("SELECT * FROM oauth_clients WHERE client_id = ? AND is_active = 1 AND revoked_at IS NULL AND allow_password_grant = 1");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$client) {
	json_error('unauthorized_client', 'This client is not authorized for password grant', 401);
}

if ($grant_type === 'password') {
	$email = $body['email'] ?? null;
	$password = $body['password'] ?? null;

	if (!$email || !$password) {
		json_error('invalid_request', 'Missing email or password');
	}

	$stmt = $mysqli->prepare("SELECT id, password_hash FROM users WHERE email = ? AND email_verified = 1");
	$stmt->bind_param("s", $email);
	$stmt->execute();
	$user = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$user || !password_verify($password, $user['password_hash'])) {
		json_error('invalid_grant', 'Invalid email or password', 401);
	}

	$access_token = generate_token(32);
	$refresh_token = generate_token(32);
	$access_expires = date('Y-m-d H:i:s', time() + 3600);
	$refresh_expires = date('Y-m-d H:i:s', time() + 86400 * 30);

	$stmt = $mysqli->prepare("INSERT INTO user_tokens (user_id, access_token, refresh_token, access_token_expires_at, refresh_token_expires_at, created_at, client_id, device_id, scopes) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, '')");
	$stmt->bind_param("sssssss", $user['id'], $access_token, $refresh_token, $access_expires, $refresh_expires, $client['id'], $device_id);
	$stmt->execute();
	$stmt->close();

	ob_end_clean();
	echo json_encode([
		'access_token' => $access_token,
		'token_type' => 'Bearer',
		'expires_in' => 3600,
		'refresh_token' => $refresh_token,
	]);
	exit;
}

if ($grant_type === 'refresh_token') {
	$refresh_token = $body['refresh_token'] ?? null;

	if (!$refresh_token) {
		json_error('invalid_request', 'Missing refresh_token');
	}

	$stmt = $mysqli->prepare("SELECT * FROM user_tokens WHERE refresh_token = ? AND client_id = ? AND device_id = ? AND refresh_token_expires_at > NOW()");
	$stmt->bind_param("sss", $refresh_token, $client['id'], $device_id);
	$stmt->execute();
	$token_row = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$token_row) {
		json_error('invalid_grant', 'Invalid or expired refresh token', 401);
	}

	$stmt = $mysqli->prepare("DELETE FROM user_tokens WHERE id = ?");
	$stmt->bind_param("i", $token_row['id']);
	$stmt->execute();
	$stmt->close();

	$new_access_token = generate_token(32);
	$new_refresh_token = generate_token(32);
	$access_expires = date('Y-m-d H:i:s', time() + 3600);
	$refresh_expires = date('Y-m-d H:i:s', time() + 86400 * 30);

	$stmt = $mysqli->prepare("INSERT INTO user_tokens (user_id, access_token, refresh_token, access_token_expires_at, refresh_token_expires_at, created_at, client_id, device_id, scopes) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)");
	$stmt->bind_param("ssssssss", $token_row['user_id'], $new_access_token, $new_refresh_token, $access_expires, $refresh_expires, $client['id'], $device_id, $token_row['scopes']);
	$stmt->execute();
	$stmt->close();

	ob_end_clean();
	echo json_encode([
		'access_token' => $new_access_token,
		'token_type' => 'Bearer',
		'expires_in' => 3600,
		'refresh_token' => $new_refresh_token,
	]);
	exit;
}

json_error('unsupported_grant_type', 'Grant type not supported');
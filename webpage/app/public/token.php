<?php
include 'config.php';
include 'allowed_scopes.php';

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (isset($_SERVER['HTTP_ORIGIN'])) {
	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Max-Age: 0');
}

header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

function generateUUIDv4() {
	$data = random_bytes(16);
	$data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
	$data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function generate_token(int $bytes = 32): string {
	return bin2hex(random_bytes($bytes));
}

function json_error($error, $description = null, $code = 400) {
	http_response_code($code);
	$response = ['error' => $error];
	if ($description) {
		$response['error_description'] = $description;
	}
	echo json_encode($response);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	json_error('invalid_request', 'Only POST method is allowed', 405);
}

$grant_type = $_POST['grant_type'] ?? null;
$client_id = $_POST['client_id'] ?? null;
$client_secret = $_POST['client_secret'] ?? null;

if (!$grant_type || !$client_id || !$client_secret) {
	json_error('invalid_request', 'Missing required parameters');
}

$stmt = $mysqli->prepare("SELECT * FROM oauth_clients WHERE client_id = ? AND is_active = 1 AND revoked_at IS NULL");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$stmt->close();

if (!$client || !password_verify($client_secret, $client['client_secret'])) {
	json_error('invalid_client', 'Invalid client credentials', 401);
}

if ($grant_type === 'authorization_code') {
	$code = $_POST['code'] ?? null;
	$redirect_uri = $_POST['redirect_uri'] ?? null;

	if (!$code || !$redirect_uri) {
		json_error('invalid_request', 'Missing code or redirect_uri');
	}

	$code_hash = hash('sha256', $code);

	$stmt = $mysqli->prepare("SELECT * FROM oauth_authorization_codes WHERE code = ? AND client_id = ? AND used_at IS NULL AND expires_at > NOW()");
	$stmt->bind_param("ss", $code_hash, $client_id);
	$stmt->execute();
	$result = $stmt->get_result();
	$auth_code = $result->fetch_assoc();
	$stmt->close();

	if (!$auth_code) {
		json_error('invalid_grant', 'Invalid, expired, or already used authorization code');
	}

	if ($auth_code['redirect_uri'] !== $redirect_uri) {
		json_error('invalid_grant', 'Redirect URI mismatch');
	}

	$requested_scopes = array_filter(explode(' ', $auth_code['scopes'] ?? ''));
	if (!validate_scopes($requested_scopes)) {
		json_error('invalid_scope', 'One or more requested scopes are invalid');
	}
	$final_scopes = implode(' ', expand_scopes($requested_scopes));

	$stmt = $mysqli->prepare("UPDATE oauth_authorization_codes SET used_at = NOW() WHERE id = ?");
	$stmt->bind_param("s", $auth_code['id']);
	$stmt->execute();
	$stmt->close();

	$access_token = generate_token(32);
	$refresh_token = generate_token(32);
	$access_expires = date('Y-m-d H:i:s', time() + 3600);
	$refresh_expires = date('Y-m-d H:i:s', time() + 86400 * 30);

	$stmt = $mysqli->prepare("INSERT INTO user_tokens (user_id, access_token, refresh_token, access_token_expires_at, refresh_token_expires_at, created_at, client_id, scopes) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
	$stmt->bind_param("sssssss", $auth_code['user_id'], $access_token, $refresh_token, $access_expires, $refresh_expires, $client['id'], $final_scopes);
	$stmt->execute();
	$stmt->close();

	echo json_encode([
		'access_token' => $access_token,
		'token_type' => 'Bearer',
		'expires_in' => 3600,
		'refresh_token' => $refresh_token,
		'scope' => $final_scopes
	]);
	exit;

} elseif ($grant_type === 'refresh_token') {
	$refresh_token = $_POST['refresh_token'] ?? null;

	if (!$refresh_token) {
		json_error('invalid_request', 'Missing refresh_token');
	}

	$stmt = $mysqli->prepare("SELECT * FROM user_tokens WHERE refresh_token = ? AND client_id = ? AND refresh_token_expires_at > NOW()");
	$stmt->bind_param("si", $refresh_token, $client['id']);
	$stmt->execute();
	$result = $stmt->get_result();
	$token_row = $result->fetch_assoc();
	$stmt->close();

	if (!$token_row) {
		json_error('invalid_grant', 'Invalid or expired refresh token');
	}

	$stmt = $mysqli->prepare("DELETE FROM user_tokens WHERE id = ?");
	$stmt->bind_param("s", $token_row['id']);
	$stmt->execute();
	$stmt->close();

	$new_access_token = generate_token(32);
	$new_refresh_token = generate_token(32);
	$access_expires = date('Y-m-d H:i:s', time() + 3600);
	$refresh_expires = date('Y-m-d H:i:s', time() + 86400 * 30);

	$stmt = $mysqli->prepare("INSERT INTO user_tokens (user_id, access_token, refresh_token, access_token_expires_at, refresh_token_expires_at, created_at, client_id, scopes) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
	$stmt->bind_param("ssssssi", $token_row['user_id'], $new_access_token, $new_refresh_token, $access_expires, $refresh_expires, $client['id'], $token_row['scopes']);
	$stmt->execute();
	$stmt->close();

	echo json_encode([
		'access_token' => $new_access_token,
		'token_type' => 'Bearer',
		'expires_in' => 3600,
		'refresh_token' => $new_refresh_token,
		'scope' => $token_row['scopes'] ?? ''
	]);
	exit;

} else {
	json_error('unsupported_grant_type', 'Grant type not supported');
}
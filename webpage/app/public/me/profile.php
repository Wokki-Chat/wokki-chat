<?php
include '../config.php';
include '../_scopes.php';

error_reporting(-1);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

if (isset($_SERVER['HTTP_ORIGIN'])) {
	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Max-Age: 0');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	header('Access-Control-Allow-Methods: GET, OPTIONS');
	header('Access-Control-Allow-Headers: Content-Type, Authorization');
	http_response_code(204);
	exit;
}

header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

function json_error($error, $description = null, $code = 400) {
	http_response_code($code);
	$response = ['error' => $error];
	if ($description) {
		$response['error_description'] = $description;
	}
	echo json_encode($response);
	exit;
}

function getPremiumStatus($user_id, $mysqli) {
	$stmt = $mysqli->prepare("SELECT premium, premium_expires_at FROM users WHERE id = ?");
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	$user = $result->fetch_assoc();
	$stmt->close();

	return $user['premium'] == 1 && (
		is_null($user['premium_expires_at']) || strtotime($user['premium_expires_at']) > time()
	);
}

function getConnections($user_id, $mysqli) {
	$stmt = $mysqli->prepare("SELECT * FROM user_connections WHERE user_id = ?");
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$result = $stmt->get_result();

	$connections = [];
	while ($row = $result->fetch_assoc()) {
		$connections[] = [
			'id' => $row['id'],
			'connection_type' => $row['connection_name'],
			'connection_name' => $row['connection_user_name'],
			'connection_user_url' => $row['connection_user_url'],
		];
	}

	return $connections;
}

function getTags($user_id, $mysqli) {
	$stmt = $mysqli->prepare("SELECT * FROM tags WHERE user_id = ?");
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$result = $stmt->get_result();

	$tags = [];
	while ($row = $result->fetch_assoc()) {
		$tags[] = [
			'tag_icon' => $row['tag_icon'],
			'tag_name' => $row['tag_name'],
			'created_at' => $row['created_at'] ?? null,
		];
	}

	return $tags;
}

function getUserWidgets($mysqli, $user_id) {
	$stmt = $mysqli->prepare("
		SELECT widget_name, widget_access_token, widget_refresh_token,
		       show_on_profile, widget_access_token_valid_until
		FROM profile_widgets
		WHERE user_id = ?
	");
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	$widgets = $result->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	if (empty($widgets)) {
		return [];
	}

	$stmt = $mysqli->prepare("
		SELECT connection_user_name, connection_name
		FROM user_connections
		WHERE user_id = ?
	");
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	$connections = $result->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$connection_map = [];
	foreach ($connections as $conn) {
		$connection_map[$conn['connection_name']] = $conn['connection_user_name'];
	}

	$widget_result = [];

	foreach ($widgets as $widget) {
		if ($widget['show_on_profile'] != 1) {
			continue;
		}

		if ($widget['widget_name'] === 'GitHub') {
			try {
				$github_username = $connection_map['GitHub'] ?? null;
				$widget_token = $widget['widget_access_token'] ?? null;

				if (!$github_username || !$widget_token) {
					continue;
				}

				$graphql_query = [
					'query' => '{
					  user(login: "' . addslashes($github_username) . '") {
					    contributionsCollection {
					      contributionCalendar {
					        totalContributions
					        weeks {
					          contributionDays {
					            date
					            contributionCount
					            color
					          }
					        }
					      }
					    }
					  }
					}'
				];

				$ch = curl_init('https://api.github.com/graphql');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($graphql_query));
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Authorization: Bearer ' . $widget_token,
					'Content-Type: application/json',
					'User-Agent: PHP-App'
				]);

				$response = curl_exec($ch);
				$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);

				if ($status === 200) {
					$widget_result['GitHub'] = json_decode($response, true);
				} else {
					$widget_result['GitHub'] = ['error' => "GitHub API returned status $status"];
				}
			} catch (Exception $e) {
				$widget_result['GitHub'] = ['error' => $e->getMessage()];
			}
		} else {
			$widget_result[$widget['widget_name']] = true;
		}
	}

	return $widget_result;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	json_error('invalid_request', 'Only GET method is allowed', 405);
}

$headers = getallheaders();
$headers = array_change_key_case($headers, CASE_LOWER);

if (
	!isset($headers['authorization']) ||
	!preg_match('/Bearer\s(\S+)/', $headers['authorization'], $matches)
) {
	json_error('unauthorized', 'Missing or invalid Authorization header', 401);
}

$access_token = $matches[1];

if (!verify_scope('user:read:profile', $access_token, $mysqli)) {
	json_error('insufficient_scope', 'Missing required scope user:read:profile', 403);
}

$stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ? AND access_token_expires_at > NOW()");
$stmt->bind_param("s", $access_token);
$stmt->execute();
$result = $stmt->get_result();
$token_row = $result->fetch_assoc();
$stmt->close();

if (!$token_row) {
	json_error('unauthorized', 'Invalid or expired access token', 401);
}

$requesting_user_id = $token_row['user_id'];
$requested_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $requesting_user_id;

$stmt = $mysqli->prepare("SELECT id, username, email, status, profile_picture, created_at, bio, profile_color_primary, profile_color_accent, nickname, profile_banner, is_developer, is_staff FROM users WHERE id = ?");
$stmt->bind_param("i", $requested_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_row = $result->fetch_assoc();
$stmt->close();

if (!$user_row) {
	json_error('not_found', 'User not found', 404);
}

$premium = getPremiumStatus($requested_user_id, $mysqli);
$can_read_email = verify_scope('user:read:email', $access_token, $mysqli);

$user = [
	'id' => $user_row['id'],
	'username' => $user_row['username'],
	'display_name' => $user_row['nickname'],
	'email' => $can_read_email ? $user_row['email'] : null,
	'bio' => $user_row['bio'],
	'status' => $user_row['status'],
	'avatar' => 'https://chat.wokki20.nl' . $user_row['profile_picture'],
	'banner' => $user_row['profile_banner'],
	'accent_color' => $premium ? $user_row['profile_color_accent'] : null,
	'primary_color' => $premium ? $user_row['profile_color_primary'] : null,
	'premium' => $premium,
	'staff' => $user_row['is_staff'] == 1,
	'developer' => $user_row['is_developer'] == 1,
	'bot' => false,
	'tags' => getTags($requested_user_id, $mysqli),
	'connections' => getConnections($requested_user_id, $mysqli),
	'created_at' => $user_row['created_at'],
];

while (ob_get_level() > 0) {
	ob_end_clean();
}

ob_start();

echo json_encode([
	'status' => 'success',
	'description' => 'User fetched successfully',
	'user' => $user,
	'return_code' => 30
]);
echo "\n";

ob_flush();
flush();

$widgets = getUserWidgets($mysqli, $requested_user_id);
echo json_encode([
	'status' => 'success',
	'description' => 'Widgets fetched successfully',
	'widgets' => empty($widgets) ? new stdClass() : $widgets,
	'return_code' => 31
]);
flush();
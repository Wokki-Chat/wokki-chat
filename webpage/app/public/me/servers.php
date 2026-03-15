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

if (!verify_scope('servers:read', $access_token, $mysqli)) {
	json_error('insufficient_scope', 'Missing required scope servers:read', 403);
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

$user_id = $token_row['user_id'];

$stmt = $mysqli->prepare("SELECT server_id, position, joined_at FROM server_members WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_servers = [];
while ($row = $result->fetch_assoc()) {
    $user_servers[] = $row;
}
$stmt->close();

$servers = [];
if (!empty($user_servers)) {
    $server_ids = array_column($user_servers, 'server_id');
    $member_data = array_column($user_servers, null, 'server_id');
    
    $placeholders = str_repeat('?,', count($server_ids) - 1) . '?';
    
    $stmt = $mysqli->prepare("SELECT id, name, description, image, created_by, created_at, server_type FROM servers WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('s', count($server_ids)), ...$server_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['position'] = $member_data[$row['id']]['position'];
        $row['joined_at'] = $member_data[$row['id']]['joined_at'];
        $row['image'] = 'https://chat.wokki20.nl' . $row['image'];
        $servers[] = $row;
    }
    $stmt->close();
    
    $stmt = $mysqli->prepare("SELECT channel_group_id, channel_group_name, channel_group_created_at, channel_group_updated_at, channel_group_index, server_id FROM channel_groups WHERE server_id IN ($placeholders) ORDER BY channel_group_index");
    $stmt->bind_param(str_repeat('s', count($server_ids)), ...$server_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $channel_groups_by_server = [];
    $all_group_ids = [];
    while ($row = $result->fetch_assoc()) {
        $group_data = [
            'id' => $row['channel_group_id'],
            'name' => $row['channel_group_name'],
            'created_at' => $row['channel_group_created_at'],
            'updated_at' => $row['channel_group_updated_at'],
            'index' => $row['channel_group_index'],
            'server_id' => $row['server_id']
        ];
        $channel_groups_by_server[$row['server_id']][] = $group_data;
        $all_group_ids[] = $row['channel_group_id'];
    }
    $stmt->close();
    
    if (!empty($all_group_ids)) {
        $group_placeholders = str_repeat('?,', count($all_group_ids) - 1) . '?';
        $stmt = $mysqli->prepare("SELECT channel_id, channel_name, channel_type, channel_created_at, channel_updated_at, is_default, channel_index, server_id, channel_group_id FROM channels WHERE channel_group_id IN ($group_placeholders) ORDER BY channel_index");
        $stmt->bind_param(str_repeat('s', count($all_group_ids)), ...$all_group_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $channels_by_group = [];
        while ($row = $result->fetch_assoc()) {
            $channel_data = [
                'id' => $row['channel_id'],
                'name' => $row['channel_name'],
                'type' => $row['channel_type'],
                'created_at' => $row['channel_created_at'],
                'updated_at' => $row['channel_updated_at'],
                'is_default' => $row['is_default'],
                'index' => $row['channel_index'],
                'group_id' => $row['channel_group_id']
            ];
            $channels_by_group[$row['channel_group_id']][] = $channel_data;
        }
        $stmt->close();
        
        foreach ($servers as &$server) {
            if (isset($channel_groups_by_server[$server['id']])) {
                foreach ($channel_groups_by_server[$server['id']] as &$group) {
                    $group['channels'] = $channels_by_group[$group['id']] ?? [];
                    unset($group['server_id']);
                }
                $server['channel_groups'] = $channel_groups_by_server[$server['id']];
            } else {
                $server['channel_groups'] = [];
            }
        }
    } else {
        foreach ($servers as &$server) {
            $server['channel_groups'] = [];
        }
    }
}

echo json_encode($servers);
<?php
include 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/src/Exception.php';
require $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/src/PHPMailer.php';
require $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/src/SMTP.php';

$mail = new PHPMailer();

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
header('Content-Type: application/json');

function generateUUIDv4() {
    $data = random_bytes(16);

    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);

    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $allowedOrigin = 'https://chat.wokki20.nl';

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        if ($_SERVER['HTTP_ORIGIN'] !== $allowedOrigin) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Origin',
                'return_code' => 38
            ]);
            exit;
        }
    } else if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        if ($referer !== 'chat.wokki20.nl') {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Referer',
                'return_code' => 39
            ]);
            exit;
        }
    } else {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'description' => 'Forbidden: No Origin or Referer',
            'return_code' => 40
        ]);
        exit;
    }

    $headers = getallheaders();
    if (
        !isset($headers['Authorization']) ||
        !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)
    )
    {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'description' => 'Unauthorized: Missing or invalid Authorization header',
            'return_code' => 41
        ]);
        exit;
    }

    $access_token = $matches[1];

    $stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ?");
    $stmt->bind_param("s", $access_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row || !isset($row['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'description' => 'Unauthorized: Invalid access token',
            'return_code' => 42
        ]);
        exit;
    }

    $user_id = $row['user_id'];

    if (!isset($_POST['action'])) {
        echo json_encode([
            'status' => 'error',
            'description' => 'Missing required fields',
            'missing_fields' => ['action'],
            'return_code' => 44
        ]);
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'create_category') {
        if (!isset($_POST['category_name']) || !isset($_POST['server_id'])) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Missing required fields',
                'missing_fields' => ['category_name', 'server_id'],
                'return_code' => 45
            ]);
            exit;
        }

        $category_name = $_POST['category_name'];
        $server_id = $_POST['server_id'];

        $stmt = $mysqli->prepare("SELECT created_by, server_type FROM servers WHERE id = ?");
        $stmt->bind_param("s", $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row || !isset($row['created_by'])) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Server not found',
                'return_code' => 47
            ]);
            exit;
        }

        if ($row['created_by'] !== $user_id && $row['server_type'] !== 'normal') {
            echo json_encode([
                'status' => 'error',
                'description' => 'You do not have permission to create a category for this server',
                'return_code' => 48
            ]);
            exit;
        }
        $category_id_new = generateUUIDv4();

        $stmt = $mysqli->prepare("INSERT INTO channel_groups (channel_group_id, channel_group_name, server_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $category_id_new, $category_name, $server_id);
        $stmt->execute();
        $stmt->close();

        $category_new_raw = [
            'id' => $category_id_new,
            'name' => $category_name
        ];

        echo json_encode([
            'status' => 'success',
            'description' => 'Category added',
            'new_category' => $category_new_raw
        ]);
        exit;

    } else if ($action === 'create_channel') {
        if (!isset($_POST['channel_name']) || !isset($_POST['server_id']) || !isset($_POST['channel_category_id'])) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Missing required fields',
                'missing_fields' => ['category_name', 'server_id', 'channel_category_id'],
                'return_code' => 50
            ]);
            exit;
        }

        $channel_name = $_POST['channel_name'];
        $server_id = $_POST['server_id'];
        $channel_category_id = $_POST['channel_category_id'];
        $channel_type = $_POST['channel_type'] ?? 'text';

        if ($channel_type !== 'text' && $channel_type !== 'voice') {
            echo json_encode([
                'status' => 'error',
                'description' => 'Invalid channel type',
                'return_code' => 50
            ]);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT created_by, server_type FROM servers WHERE id = ?");
        $stmt->bind_param("s", $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row || !isset($row['created_by'])) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Server not found',
                'return_code' => 51
            ]);
            exit;
        }

        if ($row['created_by'] !== $user_id && $row['server_type'] !== 'normal') {
            echo json_encode([
                'status' => 'error',
                'description' => 'You do not have permission to create a category for this server',
                'return_code' => 52
            ]);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT channel_group_id FROM channel_groups WHERE channel_group_id = ? AND server_id = ?");
        $stmt->bind_param("ss", $channel_category_id, $server_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $category_exists = $row && isset($row['channel_group_id']);

        if (!$category_exists) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Category not found in server',
                'return_code' => 54
            ]);
            exit;
        }

        $channel_id_new = generateUUIDv4();
        
        $stmt = $mysqli->prepare("INSERT INTO channels (channel_id, channel_name, channel_type, channel_group_id, server_id, is_default) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sssss", $channel_id_new, $channel_name, $channel_type, $channel_category_id, $server_id);
        $success = $stmt->execute();
        $stmt->close();

        if (!$success) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Failed to update channels in database',
                'return_code' => 56
            ]);
            exit;
        }

        $channel_new_raw = [
            'channel_id' => $channel_id_new,
            'channel_name' => $channel_name,
            'channel_type' => $channel_type,
        ];

        echo json_encode([
            'status' => 'success',
            'description' => 'Channel created and saved',
            'channel' => $channel_new_raw,
            'return_code' => 57
        ]);
        exit;
    } else if ($action === 'leave_server') {
        if (!isset($_POST['server_id'])) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Missing required fields',
                'missing_fields' => ['server_id'],
                'return_code' => 58
            ]);
            exit;
        }

        $server_id = $_POST['server_id'];
        $is_bot = isset($_POST['bot_id']);
        $leaver_id = $is_bot ? $_POST['bot_id'] : $user_id;
        $id_key = $is_bot ? 'bot_id' : 'user_id';

        $bindTypes = $is_bot ? "ss" : "si";

        $stmt = $mysqli->prepare("SELECT * FROM server_members WHERE server_id = ? AND $id_key = ?");
        $stmt->bind_param($bindTypes, $server_id, $leaver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Server not found or user not in server',
                'return_code' => 59
            ]);
            exit;
        }
        $stmt->close();

        $stmt = $mysqli->prepare("DELETE FROM server_members WHERE server_id = ? AND $id_key = ?");
        $stmt->bind_param($bindTypes, $server_id, $leaver_id);
        $success1 = $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("DELETE FROM user_server_roles WHERE server_id = ? AND $id_key = ?");
        $stmt->bind_param($bindTypes, $server_id, $leaver_id);
        $success2 = $stmt->execute();
        $stmt->close();

        if (!$success1 || !$success2) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Failed to leave server',
                'return_code' => 60
            ]);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'description' => ($is_bot ? 'Bot' : 'User') . ' left server',
            'return_code' => 62
        ]);
        exit;


    } else if ($action === 'create_invite') {
        if (!isset($_POST['server_id']) || !isset($_POST['expires_in'])) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Missing required fields',
                'missing_fields' => ['server_id', 'expires_in'],
                'return_code' => 63
            ]);
            exit;
        }

        $server_id = $_POST['server_id'];
        $expires_in = strtolower(trim($_POST['expires_in']));

        $valid_options = [
            '1 hour' => '+1 hour',
            '12 hours' => '+12 hours',
            '1 day' => '+1 day',
            '7 days' => '+7 days',
            '30 days' => '+30 days',
            'never' => null
        ];

        if (!array_key_exists($expires_in, $valid_options)) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Invalid expires_in value',
                'valid_options' => array_keys($valid_options),
                'return_code' => 64
            ]);
            exit;
        }

        if ($valid_options[$expires_in] === null) {
            $expires_at = '0000-00-00 00:00:00';
        } else {
            $date = new DateTime("now", new DateTimeZone("UTC"));
            $date->modify($valid_options[$expires_in]);
            $expires_at = $date->format("Y-m-d H:i:s");
        }

        $invite_code = bin2hex(random_bytes(6));

        $stmt = $mysqli->prepare("INSERT INTO invites (server_id, code, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $server_id, $invite_code, $expires_at);
        $success = $stmt->execute();
        $stmt->close();

        if (!$success) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Failed to create invite in database',
                'return_code' => 65
            ]);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'description' => 'Invite created',
            'url' => 'https://chat.wokki20.nl/invite/' . $invite_code,
            'return_code' => 66
        ]);
        exit;

    } else if ($action === 'edit_channel_index') {
        if (
            !isset($_POST['server_id']) ||
            !isset($_POST['channel_id']) ||
            !isset($_POST['index']) ||
            !isset($_POST['channel_group'])
        ) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Missing required fields',
                'missing_fields' => ['server_id', 'channel_id', 'index'],
                'return_code' => 67
            ]);
            exit;
        }

        $server_id = $_POST['server_id'];
        $channel_id = $_POST['channel_id'];
        $index = intval($_POST['index']);
        $channel_group = $_POST['channel_group'];
        $other_channel_indexes = isset($_POST['other_channel_indexes'])
            ? json_decode($_POST['other_channel_indexes'], true)
            : [];

        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare("UPDATE channels SET channel_index = ?, channel_group_id = ? WHERE server_id = ? AND channel_id = ?");
        $stmt->bind_param("isss", $index, $channel_group, $server_id, $channel_id);
        $success = $stmt->execute();
        $stmt->close();

        if (!$success) {
            $mysqli->rollback();
            echo json_encode([
                'status' => 'error',
                'description' => 'Failed to update channel index in database',
                'return_code' => 68
            ]);
            exit;
        }

        if (is_array($other_channel_indexes)) {
            $stmt = $mysqli->prepare("UPDATE channels SET channel_index = ? WHERE server_id = ? AND channel_id = ?");

            foreach ($other_channel_indexes as $other_channel_id => $other_index) {
                $other_index = intval($other_index);
                $stmt->bind_param("iss", $other_index, $server_id, $other_channel_id);
                if (!$stmt->execute()) {
                    $stmt->close();
                    $mysqli->rollback();
                    echo json_encode([
                        'status' => 'error',
                        'description' => 'Failed to update other channel indexes',
                        'return_code' => 70
                    ]);
                    exit;
                }
            }

            $stmt->close();
        }

        $mysqli->commit();

        echo json_encode([
            'status' => 'success',
            'description' => 'Channel indexes updated',
            'return_code' => 69
        ]);
        exit;
        }   else if ($action === 'edit_channel_group') {
        if (
            !isset($_POST['server_id']) ||
            !isset($_POST['group_id']) ||
            !isset($_POST['index'])
        ) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Missing required fields',
                'missing_fields' => ['server_id', 'group_id', 'index'],
                'return_code' => 71
            ]);
            exit;
        }

        $server_id = $_POST['server_id'];
        $channel_group_id = $_POST['group_id'];
        $channel_group_index = intval($_POST['index']);

        $other_channel_group_indexes = isset($_POST['other_group_indexes'])
            ? json_decode($_POST['other_group_indexes'], true)
            : [];

        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare("
            UPDATE channel_groups 
            SET channel_group_index = ?
            WHERE server_id = ? AND channel_group_id = ?
        ");
        $stmt->bind_param(
            "iss",
            $channel_group_index,
            $server_id,
            $channel_group_id
        );
        $success = $stmt->execute();
        $stmt->close();

        if (!$success) {
            $mysqli->rollback();
            echo json_encode([
                'status' => 'error',
                'description' => 'Failed to update channel group in database',
                'return_code' => 72
            ]);
            exit;
        }

        if (is_array($other_channel_group_indexes)) {
            $stmt = $mysqli->prepare("UPDATE channel_groups SET channel_group_index = ? WHERE server_id = ? AND channel_group_id = ?");
            
            foreach ($other_channel_group_indexes as $other_group_id => $other_index) {
                $other_index = intval($other_index);
                $stmt->bind_param("iss", $other_index, $server_id, $other_group_id);
                if (!$stmt->execute()) {
                    $stmt->close();
                    $mysqli->rollback();
                    echo json_encode([
                        'status' => 'error',
                        'description' => 'Failed to update other channel group indexes',
                        'return_code' => 73
                    ]);
                    exit;
                }
            }

            $stmt->close();
        }

        $mysqli->commit();

        echo json_encode([
            'status' => 'success',
            'description' => 'Channel groups updated',
            'return_code' => 74
        ]);
        exit;
    } else if ($action === 'edit_server_positioning') {
        if (
            !isset($_POST['server_id']) ||
            !isset($_POST['position']) ||
            !isset($_POST['other_positions'])
        ) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Missing required fields',
                'missing_fields' => ['server_id', 'position', 'other_positions'],
                'return_code' => 75
            ]);
            exit;
        }

        $server_id = $_POST['server_id'];
        $position = intval($_POST['position']);
        $other_positions = json_decode($_POST['other_positions'], true);

        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare("
            UPDATE server_members 
            SET position = ?
            WHERE server_id = ? AND user_id = ?
        ");
        $stmt->bind_param(
            "iss",
            $position,
            $server_id,
            $user_id
        );
        $success = $stmt->execute();
        $stmt->close();

        if (!$success) {
            $mysqli->rollback();
            echo json_encode([
                'status' => 'error',
                'description' => 'Failed to update position in database',
                'return_code' => 76
            ]);
            exit;
        }

        if (is_array($other_positions)) {
            $stmt = $mysqli->prepare("UPDATE server_members SET position = ? WHERE server_id = ? AND user_id = ?");
            
            foreach ($other_positions as $other_server_id => $other_position) {
                $other_position = intval($other_position);
                $stmt->bind_param("iss", $other_position, $other_server_id, $user_id);
                if (!$stmt->execute()) {
                    $stmt->close();
                    $mysqli->rollback();
                    echo json_encode([
                        'status' => 'error',
                        'description' => 'Failed to update other positions',
                        'return_code' => 77
                    ]);
                    exit;
                }
            }

            $stmt->close();
        }

        $mysqli->commit();

        echo json_encode([
            'status' => 'success',
            'description' => 'Position updated',
            'return_code' => 78
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'error',
        'description' => 'Action not supported',
        'supported_actions' => ['create_category', 'create_channel', 'leave_server', 'create_invite'],
        'return_code' => 46
    ]);
    exit;

}
else {
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 43
    ]);
}

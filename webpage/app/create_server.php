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

function saveImageAsWebp($imageResource, $savePath) {
    imagewebp($imageResource, $savePath, 80);
    imagedestroy($imageResource);
}

function getInitials($firstName, $lastName = '') {
    $initials = strtoupper($firstName[0] ?? '');
    if ($lastName) {
        $initials .= strtoupper($lastName[0]);
    }
    return $initials ?: 'NA';
}

function getBackgroundColor($userId) {
    $hash = md5($userId);
    $r = hexdec(substr($hash, 0, 2));
    $g = hexdec(substr($hash, 2, 2));
    $b = hexdec(substr($hash, 4, 2));
    $r = max(50, min(205, $r));
    $g = max(50, min(205, $g));
    $b = max(50, min(205, $b));
    if (abs($r - $g) < 15 && abs($g - $b) < 15 && abs($r - $b) < 15) {
        $r = ($r + 40) % 206;
        $g = ($g + 80) % 206;
        $b = ($b + 120) % 206;
    }
    return [$r, $g, $b];
}

function getTextColor($r, $g, $b) {
    $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
    return $brightness > 125 ? [0, 0, 0] : [255, 255, 255];
}

function createAvatarImage($userId, $firstName, $lastName = '') {
    $width = 100;
    $height = 100;
    $im = imagecreatetruecolor($width, $height);

    list($r, $g, $b) = getBackgroundColor($userId);
    $bgColor = imagecolorallocate($im, $r, $g, $b);
    imagefilledrectangle($im, 0, 0, $width, $height, $bgColor);

    $initials = getInitials($firstName, $lastName);
    list($tr, $tg, $tb) = getTextColor($r, $g, $b);
    $textColor = imagecolorallocate($im, $tr, $tg, $tb);

    $fontFile = __DIR__ . '/assets/fonts/Inter_24pt-SemiBold.ttf';
    $fontSize = 40;
    $bbox = imagettfbbox($fontSize, 0, $fontFile, $initials);
    $textWidth = $bbox[2] - $bbox[0];
    $textHeight = $bbox[1] - $bbox[7];
    $x = (int)(($width - $textWidth) / 2);
    $y = (int)(($height + $textHeight) / 2);
    imagettftext($im, $fontSize, 0, $x, $y, $textColor, $fontFile, $initials);

    return $im;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $allowedOrigin = 'https://chat.wokki20.nl';

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        if ($_SERVER['HTTP_ORIGIN'] !== $allowedOrigin) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Origin',
                'return_code' => 27
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
                'return_code' => 28
            ]);
            exit;
        }
    } else {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'description' => 'Forbidden: No Origin or Referer',
            'return_code' => 29
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
            'return_code' => 32
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
            'return_code' => 33
        ]);
        exit;
    }

    $user_id = $row['user_id'];

    if (!isset($_POST['server_name'])) {
        echo json_encode([
            'status' => 'error',
            'description' => 'Missing required fields',
            'missing_fields' => ['server_name'],
            'return_code' => 34
        ]);
        exit;
    }

    $server_name = $_POST['server_name'];

    if (mb_strlen($server_name) > 50) {
        echo json_encode([
            'status' => 'error',
            'description' => 'Server name must be 50 characters or fewer',
            'return_code' => 38
        ]);
        exit;
    }


    $server_id = generateUUIDv4();

    $channel_group_id = generateUUIDv4();
    $channel_group_name = 'Text channels';

    $channel_general_id = generateUUIDv4();
    $channel_general_name = 'general';
    $channel_general_type = 'text';
    $channel_general_group_id = $channel_group_id;
    
    $general_role_id = generateUUIDv4();

    $general_role_name = 'General';
    $role_color = '#ffffff';

    $image_path = '/uploads/servers/default.webp'; 
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/servers/';
    $filename = $server_id;

    if (isset($_FILES['image'])) {
        $tmpName = $_FILES['image']['tmp_name'];
        $imgInfo = getimagesize($tmpName);

        switch ($imgInfo['mime']) {
            case 'image/jpeg':
                $srcImage = imagecreatefromjpeg($tmpName);
                $filename .= '.webp';
                $savePath = $uploadDir . $filename;
                imagewebp($srcImage, $savePath, 80);

                $lowImage = imagescale($srcImage, 50, 50);
                $lowPath = $uploadDir . $server_id . '-low.webp';
                imagewebp($lowImage, $lowPath, 80);
                imagedestroy($lowImage);
                imagedestroy($srcImage);
                break;

            case 'image/png':
                $srcImage = imagecreatefrompng($tmpName);
                $filename .= '.webp';
                $savePath = $uploadDir . $filename;
                imagewebp($srcImage, $savePath, 80);

                $lowImage = imagescale($srcImage, 50, 50);
                $lowPath = $uploadDir . $server_id . '-low.webp';
                imagewebp($lowImage, $lowPath, 80);
                imagedestroy($lowImage);
                imagedestroy($srcImage);
                break;

            case 'image/gif':
                $filename .= '.gif';
                $savePath = $uploadDir . $filename;
                if (!move_uploaded_file($tmpName, $savePath)) {
                    http_response_code(500);
                    echo json_encode([
                        'status' => 'error',
                        'description' => 'Failed to save GIF image',
                        'return_code' => 40
                    ]);
                    exit;
                }
                break;

            default:
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'description' => 'Unsupported image type',
                    'return_code' => 37
                ]);
                exit;
        }

        $image_path = '/uploads/servers/' . $filename;

    } else {
        $serverNameParts = explode(' ', $server_name);
        $firstName = $serverNameParts[0];
        $lastName = count($serverNameParts) > 1 ? $serverNameParts[1] : '';

        $avatarImg = createAvatarImage($user_id, $firstName, $lastName);
        $filename = $server_id . '.webp';
        $savePath = $uploadDir . $filename;
        imagewebp($avatarImg, $savePath, 80);

        $lowImage = imagescale($avatarImg, 50, 50);
        $lowPath = $uploadDir . $server_id . '-low.webp';
        imagewebp($lowImage, $lowPath, 80);
        imagedestroy($lowImage);
        imagedestroy($avatarImg);

        $image_path = '/uploads/servers/' . $filename;
    }

    $stmt = $mysqli->prepare("INSERT INTO servers (id, name, created_by, image) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $server_id, $server_name, $user_id, $image_path);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO channel_groups (channel_group_id, channel_group_name, server_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $channel_group_id, $channel_group_name, $server_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO channels (channel_id, channel_name, channel_type, channel_group_id, server_id, is_default) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("sssss", $channel_general_id, $channel_general_name, $channel_general_type, $channel_group_id, $server_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO server_roles (role_id, role_name, role_color, server_id, add_on_join) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param("ssss", $general_role_id, $general_role_name, $role_color, $server_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO role_permissions (role_id, send_messages, view_channels, manage_channels, manage_server, manage_roles, kick_members, ban_members, mute_members, manage_groups, read_message_history) VALUES (?, 1, 1, 0, 0, 0, 0, 0, 0, 0, 1)");
    $stmt->bind_param("s", $general_role_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO server_members (server_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("si", $server_id, $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO user_server_roles (user_id, server_id, role_id) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $server_id, $general_role_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'server_id' => $server_id,
        'return_code' => 35
    ]);

} else {
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 31
    ]);
}

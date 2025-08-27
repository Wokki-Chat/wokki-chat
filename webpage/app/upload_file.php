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

function sanitizeFileName($name) {
    return preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
}

function generateSafeName($originalName) {
    $ext = strrchr($originalName, '.');
    if ($ext === false) {
        $ext = ''; 
    }
    $uuid = generateUUIDv4();

    return $uuid . $ext;
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

    $maxFiles = 10;
    $maxFileSize = 25 * 1024 * 1024;

    $fileArray = $_FILES['files'];

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'video/mp4' => 'mp4',
        'application/octet-stream' => 'bin',
        'application/pdf' => 'pdf'
    ];


    if (!is_array($fileArray['name'])) {
        echo json_encode([
            'status' => 'error',
            'description' => 'Only multiple file uploads are allowed',
            'return_code' => 45
        ]);
        exit;
    }

    $fileCount = count($fileArray['name']);

    if ($fileCount > $maxFiles) {
        echo json_encode([
            'status' => 'error',
            'description' => 'Too many files uploaded (max 10)',
            'return_code' => 46
        ]);
        exit;
    }

    $uploadDir = realpath(__DIR__ . '/../uploads/messages');
    if ($uploadDir === false) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'description' => 'Upload directory not found',
            'return_code' => 50
        ]);
        exit;
    }

    $filesInfo = [];

    for ($i = 0; $i < $fileCount; $i++) {
        if ($fileArray['error'][$i] !== UPLOAD_ERR_OK) {
            echo json_encode([
                'status' => 'error',
                'description' => "Error uploading file {$fileArray['name'][$i]}",
                'return_code' => 47
            ]);
            exit;
        }

        if ($fileArray['size'][$i] > $maxFileSize) {
            echo json_encode([
                'status' => 'error',
                'description' => "File too large: {$fileArray['name'][$i]} (max 25MB)",
                'return_code' => 48
            ]);
            exit;
        }

        $tmpPath = $fileArray['tmp_name'][$i];
        $originalName = basename($fileArray['name'][$i]);
        $safeName = generateSafeName($originalName);

        $mimeType = mime_content_type($tmpPath);

        if (!array_key_exists($mimeType, $allowedMimes)) {
            echo json_encode([
                'status' => 'error',
                'description' => "File type not allowed: {$originalName} (detected type: {$mimeType})",
                'return_code' => 49
            ]);
            exit;
        }

        $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

        if (in_array($mimeType, $imageMimes)) {
            if ($fileArray['size'][$i] > 10 * 1024 * 1024) { // only compress if over 10MB
                if ($mimeType === 'image/jpeg') {
                    $image = imagecreatefromjpeg($tmpPath);
                    imagejpeg($image, $targetPath, 75);
                    imagedestroy($image);
                } elseif ($mimeType === 'image/png') {
                    $image = imagecreatefrompng($tmpPath);
                    imagepng($image, $targetPath, 6);
                    imagedestroy($image);
                } elseif ($mimeType === 'image/webp') {
                    $image = imagecreatefromwebp($tmpPath);
                    imagewebp($image, $targetPath, 75);
                    imagedestroy($image);
                } elseif ($mimeType === 'image/gif') {
                    move_uploaded_file($tmpPath, $targetPath);
                }
            } else {
                move_uploaded_file($tmpPath, $targetPath);
            }
        } else {
            move_uploaded_file($tmpPath, $targetPath);
        }


        $filesInfo[] = [
            'original_name' => $originalName,
            'saved_name' => $safeName,
            'mime_type' => $mimeType,
            'size' => $fileArray['size'][$i]
        ];

        $stmtInsert = $mysqli->prepare("INSERT INTO assets (saved_name, user_id) VALUES (?, ?)");
        $stmtInsert->bind_param("si", $safeName, $user_id);
        $stmtInsert->execute();
        $stmtInsert->close();
    }

    echo json_encode([
        'status' => 'success',
        'files' => $filesInfo,
        'return_code' => 0
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 43
    ]);
}

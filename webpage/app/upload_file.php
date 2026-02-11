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
    error_log("========== FILE UPLOAD REQUEST START ==========");
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'NOT SET'));
    error_log("Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'NOT SET'));

    $allowedOrigin = 'https://chat.wokki20.nl';

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        if ($_SERVER['HTTP_ORIGIN'] !== $allowedOrigin) {
            error_log("ERROR: Invalid origin - " . $_SERVER['HTTP_ORIGIN']);
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Origin',
                'return_code' => 38
            ]);
            exit;
        }
        error_log("Origin check passed");
    } else if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        if ($referer !== 'chat.wokki20.nl') {
            error_log("ERROR: Invalid referer - " . $referer);
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Referer',
                'return_code' => 39
            ]);
            exit;
        }
        error_log("Referer check passed");
    } else {
        error_log("ERROR: No Origin or Referer header");
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
        error_log("ERROR: Missing or invalid Authorization header");
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'description' => 'Unauthorized: Missing or invalid Authorization header',
            'return_code' => 41
        ]);
        exit;
    }

    $access_token = $matches[1];
    error_log("Access token found: " . substr($access_token, 0, 10) . "...");

    $stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ?");
    $stmt->bind_param("s", $access_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row || !isset($row['user_id'])) {
        error_log("ERROR: Invalid access token");
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'description' => 'Unauthorized: Invalid access token',
            'return_code' => 42
        ]);
        exit;
    }

    $user_id = $row['user_id'];
    error_log("User authenticated - User ID: " . $user_id);

    $maxFiles = 10;
    $maxFileSize = 25 * 1024 * 1024;

    $fileArray = $_FILES['files'];
    error_log("Files received - Count: " . (is_array($fileArray['name']) ? count($fileArray['name']) : 'SINGLE FILE'));

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
        error_log("ERROR: Single file upload attempted");
        echo json_encode([
            'status' => 'error',
            'description' => 'Only multiple file uploads are allowed',
            'return_code' => 45
        ]);
        exit;
    }

    $fileCount = count($fileArray['name']);
    error_log("File count validated: " . $fileCount);

    if ($fileCount > $maxFiles) {
        error_log("ERROR: Too many files - " . $fileCount);
        echo json_encode([
            'status' => 'error',
            'description' => 'Too many files uploaded (max 10)',
            'return_code' => 46
        ]);
        exit;
    }

    $uploadDir = realpath(__DIR__ . '/../uploads/messages');
    error_log("Upload directory resolved: " . ($uploadDir === false ? 'FAILED' : $uploadDir));
    
    if ($uploadDir === false) {
        error_log("ERROR: Upload directory not found - Path: " . __DIR__ . '/../uploads/messages');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'description' => 'Upload directory not found',
            'return_code' => 50
        ]);
        exit;
    }

    error_log("Directory exists: " . (is_dir($uploadDir) ? 'YES' : 'NO'));
    error_log("Directory writable: " . (is_writable($uploadDir) ? 'YES' : 'NO'));
    error_log("Directory permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4));
    
    if (!is_writable($uploadDir)) {
        error_log("ERROR: Upload directory is not writable");
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'description' => 'Upload directory is not writable',
            'return_code' => 52
        ]);
        exit;
    }

    $filesInfo = [];
    error_log("Starting file processing loop...");

    for ($i = 0; $i < $fileCount; $i++) {
        error_log("--- Processing file " . ($i + 1) . " of " . $fileCount . " ---");
        error_log("Original name: " . $fileArray['name'][$i]);
        error_log("Temp path: " . $fileArray['tmp_name'][$i]);
        error_log("File size: " . $fileArray['size'][$i] . " bytes");
        error_log("Upload error code: " . $fileArray['error'][$i]);
        
        if ($fileArray['error'][$i] !== UPLOAD_ERR_OK) {
            error_log("ERROR: Upload error for file " . $fileArray['name'][$i] . " - Error code: " . $fileArray['error'][$i]);
            echo json_encode([
                'status' => 'error',
                'description' => "Error uploading file {$fileArray['name'][$i]} (error code: {$fileArray['error'][$i]})",
                'return_code' => 47
            ]);
            exit;
        }

        if ($fileArray['size'][$i] > $maxFileSize) {
            error_log("ERROR: File too large - " . $fileArray['name'][$i] . " (" . $fileArray['size'][$i] . " bytes)");
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
        
        error_log("Generated safe name: " . $safeName);
        error_log("Temp file exists: " . (file_exists($tmpPath) ? 'YES' : 'NO'));
        
        if (file_exists($tmpPath)) {
            error_log("Temp file size on disk: " . filesize($tmpPath) . " bytes");
        }

        $mimeType = mime_content_type($tmpPath);
        error_log("Detected MIME type: " . $mimeType);

        if (!array_key_exists($mimeType, $allowedMimes)) {
            error_log("ERROR: File type not allowed - " . $mimeType);
            echo json_encode([
                'status' => 'error',
                'description' => "File type not allowed: {$originalName} (detected type: {$mimeType})",
                'return_code' => 49
            ]);
            exit;
        }

        $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
        
        error_log("Target path: " . $targetPath);
        error_log("Target directory writable: " . (is_writable(dirname($targetPath)) ? 'YES' : 'NO'));
        
        $uploadSuccess = false;

        if (in_array($mimeType, $imageMimes)) {
            error_log("Processing as image file");
            
            if ($mimeType === 'image/jpeg') {
                error_log("Creating JPEG image from temp file");
                $image = @imagecreatefromjpeg($tmpPath);
                if ($image !== false) {
                    error_log("JPEG image created successfully, saving to: " . $targetPath);
                    $uploadSuccess = imagejpeg($image, $targetPath, 75);
                    error_log("imagejpeg() result: " . ($uploadSuccess ? 'SUCCESS' : 'FAILED'));
                    imagedestroy($image);
                } else {
                    error_log("ERROR: Failed to create JPEG image from temp file");
                }
            } elseif ($mimeType === 'image/png') {
                error_log("Creating PNG image from temp file");
                $image = @imagecreatefrompng($tmpPath);
                if ($image !== false) {
                    error_log("PNG image created successfully, saving to: " . $targetPath);
                    $uploadSuccess = imagepng($image, $targetPath, 6);
                    error_log("imagepng() result: " . ($uploadSuccess ? 'SUCCESS' : 'FAILED'));
                    imagedestroy($image);
                } else {
                    error_log("ERROR: Failed to create PNG image from temp file");
                }
            } elseif ($mimeType === 'image/webp') {
                error_log("Creating WebP image from temp file");
                $image = @imagecreatefromwebp($tmpPath);
                if ($image !== false) {
                    error_log("WebP image created successfully, saving to: " . $targetPath);
                    $uploadSuccess = imagewebp($image, $targetPath, 75);
                    error_log("imagewebp() result: " . ($uploadSuccess ? 'SUCCESS' : 'FAILED'));
                    imagedestroy($image);
                } else {
                    error_log("ERROR: Failed to create WebP image from temp file");
                }
            } elseif ($mimeType === 'image/gif') {
                error_log("Moving GIF file directly (no reprocessing)");
                $uploadSuccess = move_uploaded_file($tmpPath, $targetPath);
                error_log("move_uploaded_file() result: " . ($uploadSuccess ? 'SUCCESS' : 'FAILED'));
            }
        } else {
            error_log("Processing as non-image file (direct move)");
            $uploadSuccess = move_uploaded_file($tmpPath, $targetPath);
            error_log("move_uploaded_file() result: " . ($uploadSuccess ? 'SUCCESS' : 'FAILED'));
        }

        error_log("Upload success flag: " . ($uploadSuccess ? 'TRUE' : 'FALSE'));
        error_log("Target file exists: " . (file_exists($targetPath) ? 'YES' : 'NO'));
        
        if (file_exists($targetPath)) {
            error_log("Target file size: " . filesize($targetPath) . " bytes");
            error_log("Target file permissions: " . substr(sprintf('%o', fileperms($targetPath)), -4));
            error_log("Target file readable: " . (is_readable($targetPath) ? 'YES' : 'NO'));
        }
        
        if (!$uploadSuccess || !file_exists($targetPath)) {
            error_log("ERROR: Upload failed for " . $originalName);
            error_log("Upload success: " . ($uploadSuccess ? 'TRUE' : 'FALSE'));
            error_log("File exists: " . (file_exists($targetPath) ? 'YES' : 'NO'));
            
            $lastError = error_get_last();
            if ($lastError) {
                error_log("Last PHP error: " . json_encode($lastError));
            }
            
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'description' => "Failed to save file: {$originalName}. Check server error logs for details.",
                'return_code' => 51
            ]);
            exit;
        }

        error_log("File successfully saved: " . $safeName);

        $filesInfo[] = [
            'original_name' => $originalName,
            'saved_name' => $safeName,
            'mime_type' => $mimeType,
            'size' => $fileArray['size'][$i]
        ];

        error_log("Inserting asset record into database");
        $stmtInsert = $mysqli->prepare("INSERT INTO assets (saved_name, user_id) VALUES (?, ?)");
        $stmtInsert->bind_param("si", $safeName, $user_id);
        $insertResult = $stmtInsert->execute();
        error_log("Database insert result: " . ($insertResult ? 'SUCCESS' : 'FAILED'));
        
        if (!$insertResult) {
            error_log("Database insert error: " . $stmtInsert->error);
        }
        
        $stmtInsert->close();
    }

    error_log("All files processed successfully");
    error_log("Files info: " . json_encode($filesInfo));
    error_log("========== FILE UPLOAD REQUEST END ==========");

    echo json_encode([
        'status' => 'success',
        'files' => $filesInfo,
        'return_code' => 0
    ]);
} else {
    error_log("ERROR: Invalid request method - " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 43
    ]);
}
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

function isDangerousExtension($filename) {
    $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phps',
        'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'vbe',
        'js', 'jse', 'ws', 'wsf', 'wsh', 'msi', 'jar',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash',
        'asp', 'aspx', 'jsp', 'csh', 'ksh',
        'dll', 'so', 'dylib', 'app',
        'htaccess', 'htpasswd', 'ini', 'config'
    ];
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (in_array($ext, $dangerousExtensions)) {
        return true;
    }
    
    $basename = strtolower(basename($filename));
    if (strpos($basename, '.php') !== false || 
        strpos($basename, '.phtml') !== false ||
        strpos($basename, '.htaccess') !== false) {
        return true;
    }
    
    return false;
}

function scanFileForMalware($filepath, $mimeType) {
    $archiveMimes = [
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/gzip',
        'application/x-tar',
        'application/x-gzip',
        'application/octet-stream'
    ];
    
    if (in_array($mimeType, $archiveMimes)) {
        return false;
    }
    
    $fileContent = file_get_contents($filepath, false, null, 0, 1024 * 1024);
    
    if ($fileContent === false) {
        return 'Cannot read file';
    }
    
    $maliciousPatterns = [
        '/<\?php/i',
        '/<%/i',
        '/<script/i',
        '/eval\s*\(/i',
        '/base64_decode/i',
        '/exec\s*\(/i',
        '/shell_exec/i',
        '/system\s*\(/i',
        '/passthru/i',
        '/proc_open/i',
        '/popen/i',
        '/curl_exec/i',
        '/curl_multi_exec/i',
        '/parse_ini_file/i',
        '/show_source/i',
        '/file_get_contents.*php:\/\//i',
        '/fsockopen/i',
        '/assert\s*\(/i',
        '/preg_replace.*\/e/i',
        '/create_function/i',
        '/include\s*\(/i',
        '/require\s*\(/i',
        '/\\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES)/i',
        '/move_uploaded_file/i',
        '/chmod\s*\(/i',
        '/chown\s*\(/i',
        '/symlink\s*\(/i',
        '/link\s*\(/i',
        '/unlink\s*\(/i',
        '/rmdir\s*\(/i',
        '/mkdir\s*\(/i',
        '/fopen\s*\(.*[\'"]w/i',
        '/file_put_contents/i',
    ];
    
    foreach ($maliciousPatterns as $pattern) {
        if (preg_match($pattern, $fileContent)) {
            return 'Malicious code detected';
        }
    }
    
    $binarySignatures = [
        "\x4D\x5A" => 'Windows executable (EXE/DLL)',
        "\x7F\x45\x4C\x46" => 'Linux executable (ELF)',
        "\xCF\xFA\xED\xFE" => 'macOS executable (Mach-O)',
        "\xFE\xED\xFA\xCF" => 'macOS executable (Mach-O)',
    ];
    
    foreach ($binarySignatures as $signature => $description) {
        if (strpos($fileContent, $signature) === 0) {
            return "Executable file detected: $description";
        }
    }
    
    if (preg_match('/\x00/', substr($fileContent, 0, 100))) {
        $mime = $mimeType;
        $textMimes = ['text/', 'application/json', 'application/xml'];
        $isTextType = false;
        foreach ($textMimes as $textMime) {
            if (strpos($mime, $textMime) === 0) {
                $isTextType = true;
                break;
            }
        }
        if ($isTextType) {
            return 'Null byte detected in text file';
        }
    }
    
    return false;
}

function validateFileSignature($filepath, $mimeType) {
    $handle = fopen($filepath, 'rb');
    if (!$handle) {
        return false;
    }
    
    $header = fread($handle, 12);
    fclose($handle);
    
    $signatures = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89\x50\x4E\x47"],
        'image/gif' => ["\x47\x49\x46\x38\x37\x61", "\x47\x49\x46\x38\x39\x61"],
        'image/webp' => ["\x52\x49\x46\x46"],
        'application/pdf' => ["\x25\x50\x44\x46"],
        'application/zip' => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"],
        'application/x-zip-compressed' => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"],
        'application/octet-stream' => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"],
        'application/x-rar-compressed' => ["\x52\x61\x72\x21"],
        'application/x-7z-compressed' => ["\x37\x7A\xBC\xAF\x27\x1C"],
        'application/gzip' => ["\x1F\x8B"],
        'application/x-tar' => ["\x75\x73\x74\x61\x72"],
    ];
    
    if (isset($signatures[$mimeType])) {
        foreach ($signatures[$mimeType] as $signature) {
            if (strpos($header, $signature) === 0) {
                return true;
            }
        }
        return false;
    }
    
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        if ($referer !== $allowedReferer) {
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
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds HTML form MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension'
            ];
            
            $errorMsg = isset($errorMessages[$fileArray['error'][$i]]) 
                ? $errorMessages[$fileArray['error'][$i]] 
                : 'Unknown upload error';
            
            echo json_encode([
                'status' => 'error',
                'description' => "Error uploading file {$fileArray['name'][$i]}: {$errorMsg}",
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
        
        if (isDangerousExtension($originalName)) {
            echo json_encode([
                'status' => 'error',
                'description' => "Dangerous file type not allowed: {$originalName}",
                'return_code' => 51
            ]);
            exit;
        }
        
        $safeName = generateSafeName($originalName);
        $mimeType = mime_content_type($tmpPath);
        
        $malwareScan = scanFileForMalware($tmpPath, $mimeType);
        if ($malwareScan !== false) {
            echo json_encode([
                'status' => 'error',
                'description' => "Security threat detected in {$originalName}: {$malwareScan}",
                'return_code' => 52
            ]);
            exit;
        }
        
        if (!validateFileSignature($tmpPath, $mimeType)) {
            echo json_encode([
                'status' => 'error',
                'description' => "File signature mismatch for {$originalName}",
                'return_code' => 53
            ]);
            exit;
        }

        $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

        if (in_array($mimeType, $imageMimes)) {
            if ($mimeType === 'image/jpeg') {
                $image = imagecreatefromjpeg($tmpPath);
                if ($image === false) {
                    echo json_encode([
                        'status' => 'error',
                        'description' => "Invalid or corrupt JPEG: {$originalName}",
                        'return_code' => 54
                    ]);
                    exit;
                }
                imagejpeg($image, $targetPath, 75);
                imagedestroy($image);
            } elseif ($mimeType === 'image/png') {
                $image = imagecreatefrompng($tmpPath);
                if ($image === false) {
                    echo json_encode([
                        'status' => 'error',
                        'description' => "Invalid or corrupt PNG: {$originalName}",
                        'return_code' => 54
                    ]);
                    exit;
                }
                imagepng($image, $targetPath, 6);
                imagedestroy($image);
            } elseif ($mimeType === 'image/webp') {
                $image = imagecreatefromwebp($tmpPath);
                if ($image === false) {
                    echo json_encode([
                        'status' => 'error',
                        'description' => "Invalid or corrupt WebP: {$originalName}",
                        'return_code' => 54
                    ]);
                    exit;
                }
                imagewebp($image, $targetPath, 75);
                imagedestroy($image);
            } elseif ($mimeType === 'image/gif') {
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

        $stmtInsert = $mysqli->prepare("INSERT INTO assets (saved_name, original_name, mime_type, user_id) VALUES (?, ?, ?, ?)");
        $stmtInsert->bind_param("sssi", $safeName, $originalName, $mimeType, $user_id);
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
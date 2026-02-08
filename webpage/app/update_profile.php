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

function generateSafeName($originalName) {
    $ext = strrchr($originalName, '.');
    if ($ext === false) {
        $ext = '';
    }
    $uuid = generateUUIDv4();
    return $uuid . $ext;
}

function compressImage($source, $destination, $mime) {
    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, 60);
        imagedestroy($image);
        return true;
    }

    if ($mime === 'image/png') {
        $image = imagecreatefrompng($source);
        imagepng($image, $destination, 6);
        imagedestroy($image);
        return true;
    }

    if ($mime === 'image/webp') {
        $image = imagecreatefromwebp($source);
        imagewebp($image, $destination, 60);
        imagedestroy($image);
        return true;
    }

    return false;
}

function resizeAndCompressImage($src, $dest, $mime, $maxW, $maxH) {
    [$width, $height] = getimagesize($src);

    if ($width > $maxW || $height > $maxH) {
        $ratio = min($maxW / $width, $maxH / $height);
        $newW = (int)($width * $ratio);
        $newH = (int)($height * $ratio);
    } else {
        $newW = $width;
        $newH = $height;
    }

    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        $srcImg = imagecreatefromjpeg($src);
    } elseif ($mime === 'image/png') {
        $srcImg = imagecreatefrompng($src);
    } elseif ($mime === 'image/webp') {
        $srcImg = imagecreatefromwebp($src);
    } else {
        return false;
    }

    $dstImg = imagecreatetruecolor($newW, $newH);

    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
    }

    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $width, $height);

    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        $result = imagejpeg($dstImg, $dest, 85);
    } elseif ($mime === 'image/png') {
        $result = imagepng($dstImg, $dest, 6);
    } else {
        $result = imagewebp($dstImg, $dest, 85);
    }

    imagedestroy($srcImg);
    imagedestroy($dstImg);

    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowedOrigin = 'https://chat.wokki20.nl';

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        if ($_SERVER['HTTP_ORIGIN'] !== $allowedOrigin) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Origin',
                'return_code' => 5
            ]);
            exit;
        }
    } elseif (isset($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        if ($referer !== 'chat.wokki20.nl') {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Referer',
                'return_code' => 6
            ]);
            exit;
        }
    } else {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'description' => 'Forbidden: No Origin or Referer',
            'return_code' => 7
        ]);
        exit;
    }

    $headers = getallheaders();
    if (
        !isset($headers['Authorization']) ||
        !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)
    ) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'description' => 'Unauthorized: Missing or invalid Authorization header',
            'return_code' => 27
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
            'return_code' => 26
        ]);
        exit;
    }

    $user_id = $row['user_id'];

    $stmt = $mysqli->prepare("SELECT premium, premium_expires_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $hasPremium = $user['premium'] == 1 && (
        is_null($user['premium_expires_at']) || strtotime($user['premium_expires_at']) > time()
    );

    $profilePictureSuccess = null;

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $fileType = mime_content_type($file['tmp_name']);
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

        if ($hasPremium) {
            $allowedTypes[] = 'image/gif';
        }

        if (!in_array($fileType, $allowedTypes)) {
            $profilePictureSuccess = false;
        } else {
            $stmt = $mysqli->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current = $result->fetch_assoc();
            $stmt->close();

            if (!empty($current['profile_picture'])) {
                $oldPath = $_SERVER['DOCUMENT_ROOT'] . $current['profile_picture'];
                if (file_exists($oldPath) && $current['profile_picture'] !== "/uploads/profile-pictures/default-profile.png") {
                    unlink($oldPath);
                }
            }

            $safeFileName = generateSafeName($file['name']);
            $uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profile-pictures/' . $safeFileName;

            if ($fileType === 'image/gif') {
                if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $profilePictureSuccess = false;
                } else {
                    $path = '/uploads/profile-pictures/' . $safeFileName;
                    $stmt = $mysqli->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->bind_param("si", $path, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $profilePictureSuccess = true;
                }
            } else {
                if (!resizeAndCompressImage($file['tmp_name'], $uploadPath, $fileType, 400, 400)) {
                    $profilePictureSuccess = false;
                } else {
                    $path = '/uploads/profile-pictures/' . $safeFileName;
                    $stmt = $mysqli->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->bind_param("si", $path, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $profilePictureSuccess = true;
                }
            }
        }
    }
    $displayNameSuccess = null;
    if (isset($_POST['display_name'])) {
        $display_name = $_POST['display_name'];
        if ($display_name === '' || (strlen($display_name) >= 3 && strlen($display_name) <= 20)) {
            $stmt = $mysqli->prepare("UPDATE users SET nickname = ? WHERE id = ?");
            $stmt->bind_param("si", $display_name, $user_id);
            $stmt->execute();
            $stmt->close();
            $displayNameSuccess = true;
        } else {
            $displayNameSuccess = false;
        }
    }
    $profileColorsSuccess = null;
    if ($hasPremium === true) {
        if (isset($_POST['profile_color_primary']) && preg_match('/^#([A-Fa-f0-9]{6})$/', $_POST['profile_color_primary'])) {
            $stmt = $mysqli->prepare("UPDATE users SET profile_color_primary = ? WHERE id = ?");
            $stmt->bind_param("si", $_POST['profile_color_primary'], $user_id);
            $stmt->execute();
            $stmt->close();
            $profileColorsSuccess = true;
        }

        if (isset($_POST['profile_color_accent']) && preg_match('/^#([A-Fa-f0-9]{6})$/', $_POST['profile_color_accent'])) {
            $stmt = $mysqli->prepare("UPDATE users SET profile_color_accent = ? WHERE id = ?");
            $stmt->bind_param("si", $_POST['profile_color_accent'], $user_id);
            $stmt->execute();
            $stmt->close();
            $profileColorsSuccess = true;
        }
    }
    $bioSuccess = null;
    if (isset($_POST['bio']) && !empty($_POST['bio'])) {
        $bio = $_POST['bio'];
        if (strlen($bio) <= 200) {
            $stmt = $mysqli->prepare("UPDATE users SET bio = ? WHERE id = ?");
            $stmt->bind_param("si", $bio, $user_id);
            $stmt->execute();
            $stmt->close();
            $bioSuccess = true;
        } else {
            $bioSuccess = false;
        }
    }
    $bannerSuccess = null;
    if (isset($_FILES['banner_picture']) && $_FILES['banner_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['banner_picture'];
        $fileType = mime_content_type($file['tmp_name']);
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

        if ($hasPremium) {
            $allowedTypes[] = 'image/gif';
        }

        if (!in_array($fileType, $allowedTypes)) {
            $bannerSuccess = false;
        } else {
            $stmt = $mysqli->prepare("SELECT profile_banner FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current = $result->fetch_assoc();
            $stmt->close();

            if (!empty($current['profile_banner'])) {
                $oldPath = $_SERVER['DOCUMENT_ROOT'] . $current['profile_banner'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $safeFileName = generateSafeName($file['name']);
            $uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/banners/' . $safeFileName;

            if ($fileType === 'image/gif') {
                if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $bannerSuccess = false;
                } else {
                    $path = '/uploads/banners/' . $safeFileName;
                    $stmt = $mysqli->prepare("UPDATE users SET profile_banner = ? WHERE id = ?");
                    $stmt->bind_param("si", $path, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $bannerSuccess = true;
                }
            } else {
                if (!resizeAndCompressImage($file['tmp_name'], $uploadPath, $fileType, 1280, 720)) {
                    $bannerSuccess = false;
                } else {
                    $path = '/uploads/banners/' . $safeFileName;
                    $stmt = $mysqli->prepare("UPDATE users SET profile_banner = ? WHERE id = ?");
                    $stmt->bind_param("si", $path, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $bannerSuccess = true;
                }
            }
        }
    }

    if (isset($_POST['banner_picture']) && $_POST['banner_picture'] === 'null') {
        $stmt = $mysqli->prepare("SELECT profile_banner FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current = $result->fetch_assoc();
        $stmt->close();

        if (!empty($current['profile_banner'])) {
            $oldPath = $_SERVER['DOCUMENT_ROOT'] . $current['profile_banner'];
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }

            $stmt = $mysqli->prepare("UPDATE users SET profile_banner = NULL WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }

        $bannerSuccess = true;
    }

    echo json_encode([
        'status' => 'success',
        'description' => 'Profile updated successfully',
        'return_code' => 28,
        'profile_picture_success' => $profilePictureSuccess,
        'display_name_success' => $displayNameSuccess,
        'profile_colors_success' => $profileColorsSuccess,
        'bio_success' => $bioSuccess,
        'banner_success' => $bannerSuccess
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 18
    ]);
}

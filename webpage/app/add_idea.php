<?php
include 'config.php';
include 'github_service.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/src/Exception.php';
require $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/src/PHPMailer.php';
require $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer/src/SMTP.php';

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

function createTextImage($text, $width = 500, $height = 300) {
    $image = imagecreatetruecolor($width, $height);
    $bgColor = imagecolorallocate($image, 25, 25, 25);
    $textColor = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

    $fontFile = $_SERVER['DOCUMENT_ROOT'] . '/app/assets/fonts/Inter_24pt-SemiBold.ttf';
    $fontSize = 24;
    $margin = 20;
    $y = $margin + $fontSize;

    $lines = explode("\n", wordwrap($text, 40));
    foreach ($lines as $line) {
        imagettftext($image, $fontSize, 0, $margin, $y, $textColor, $fontFile, $line);
        $y += $fontSize + 8;
        if ($y > $height - $margin) break;
    }

    return $image;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    if (!isset($headers['Authorization']) || !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'description' => 'Unauthorized: Missing or invalid Authorization header', 'return_code' => 32]);
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
        echo json_encode(['status' => 'error', 'description' => 'Unauthorized: Invalid access token', 'return_code' => 33]);
        exit;
    }

    $user_id = $row['user_id'];
    $stmt = $mysqli->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_row = $result->fetch_assoc();
    $stmt->close();

    if (!isset($_POST['title']) || !isset($_POST['description'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'description' => 'Bad Request: Missing title or description']);
        exit;
    }

    $title = substr(strip_tags($_POST['title']), 0, 50);
    $description = substr(strip_tags($_POST['description']), 0, 500);

    $idea_id = generateUUIDv4();
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/ideas/';
    $filename = $idea_id;

    if (isset($_FILES['image']) && $_FILES['image']['tmp_name'] && file_exists($_FILES['image']['tmp_name'])) {
        $tmpName = $_FILES['image']['tmp_name'];
        $imgInfo = getimagesize($tmpName);
        switch ($imgInfo['mime']) {
            case 'image/jpeg': $srcImage = imagecreatefromjpeg($tmpName); break;
            case 'image/png': $srcImage = imagecreatefrompng($tmpName); break;
            default:
                http_response_code(400);
                echo json_encode(['status' => 'error', 'description' => 'Unsupported image type', 'return_code' => 37]);
                exit;
        }
    } else {
        $srcImage = createTextImage($description);
    }

    $filename .= '.webp';
    $savePath = $uploadDir . $filename;
    imagewebp($srcImage, $savePath, 80);
    imagedestroy($srcImage);

    $image_path = '/uploads/ideas/' . $filename;
    $created_at = date('Y-m-d H:i:s');

    $stmt = $mysqli->prepare("INSERT INTO ideas (id, title, description, image_path, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $idea_id, $title, $description, $image_path, $user_id, $created_at);
    $stmt->execute();
    $stmt->close();

    $github = new GitHubService();
    $github->ensureIdeaLabelExists();

    $username = $user_row['username'] ?? 'Unknown';
    $profile_picture = !empty($user_row['profile_picture']) ? "https://chat.wokki20.nl" . $user_row['profile_picture'] : '';
    $site_url = $allowedOrigin;

    $issueBody = "### Submitted by {$username}\n";
    if ($profile_picture) {
        $issueBody .= "![Profile Picture]({$profile_picture})\n";
    }
    $issueBody .= "\n---\n\n" . $description . "\n\n";
    $issueBody .= "---\n*[View idea]({$site_url}/ideas?idea={$idea_id})*";

    $issue = $github->createIssue($title, $issueBody);

    if ($issue['number'] && $issue['node_id']) {
        $issue_number = $issue['number'];
        $issue_node_id = $issue['node_id'];
        $project_item_id = $github->addIssueToProject($issue_node_id, 'voting');

        $stmt = $mysqli->prepare("UPDATE ideas SET github_issue_number = ?, github_issue_node_id = ?, github_project_item_id = ? WHERE id = ?");
        $stmt->bind_param("isss", $issue_number, $issue_node_id, $project_item_id, $idea_id);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['status' => 'success', 'description' => 'Idea added successfully']);
} else {
    echo json_encode(['status' => 'error', 'description' => 'Invalid request method', 'return_code' => 31]);
}

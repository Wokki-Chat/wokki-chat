<?php
include 'config.php';
include 'github_service.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        if ($_SERVER['HTTP_ORIGIN'] !== $allowedOrigin) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'description' => 'Forbidden: Invalid Origin', 'return_code' => 27]);
            exit;
        }
    } else if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        if ($referer !== $allowedReferer) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'description' => 'Forbidden: Invalid Referer', 'return_code' => 28]);
            exit;
        }
    } else {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'description' => 'Forbidden: No Origin or Referer', 'return_code' => 29]);
        exit;
    }

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

    $stmt = $mysqli->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_row = $result->fetch_assoc();
    $stmt->close();

    if (!$user_row) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'description' => 'User not found', 'return_code' => 34]);
        exit;
    }

    $username = $user_row['username'];

    $type = isset($_POST['type']) ? $_POST['type'] : 'idea';
    if (!in_array($type, ['idea', 'bug', 'api_request'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'description' => 'Invalid type', 'return_code' => 35]);
        exit;
    }

    $title = isset($_POST['title']) ? $_POST['title'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';

    if (empty($title) || empty($description)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'description' => 'Title and description are required', 'return_code' => 36]);
        exit;
    }

    $title = substr($title, 0, 50);
    $description = substr($description, 0, 2000);

    $image_path = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileSize = $_FILES['image']['size'];
        $fileType = $_FILES['image']['type'];

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($fileType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'description' => 'Invalid file type', 'return_code' => 37]);
            exit;
        }

        if ($fileSize > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'description' => 'File too large', 'return_code' => 38]);
            exit;
        }

        $imageResource = null;
        if ($fileType === 'image/jpeg') {
            $imageResource = imagecreatefromjpeg($fileTmpPath);
        } elseif ($fileType === 'image/png') {
            $imageResource = imagecreatefrompng($fileTmpPath);
        } elseif ($fileType === 'image/gif') {
            $imageResource = imagecreatefromgif($fileTmpPath);
        } elseif ($fileType === 'image/webp') {
            $imageResource = imagecreatefromwebp($fileTmpPath);
        }

        if ($imageResource === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'description' => 'Failed to process image', 'return_code' => 39]);
            exit;
        }

        $width = imagesx($imageResource);
        $height = imagesy($imageResource);

        if ($width < 50 || $height < 50) {
            imagedestroy($imageResource);
            http_response_code(400);
            echo json_encode(['status' => 'error', 'description' => 'Image too small', 'return_code' => 40]);
            exit;
        }

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/uploads/ideas/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newFileName = generateUUIDv4() . '.webp';
        $savePath = $uploadDir . $newFileName;
        saveImageAsWebp($imageResource, $savePath);

        $image_path = '/assets/uploads/ideas/' . $newFileName;
    }

    $idea_id = generateUUIDv4();
    $is_private = ($type === 'api_request') ? 1 : 0;

    $github_body = "**Submitted by:** {$username}\n\n";
    
    if ($type === 'bug') {
        $github_body .= "## Bug Report\n\n";
        $github_body .= "**What is the issue:**\n" . (isset($_POST['bug_issue']) ? $_POST['bug_issue'] : 'N/A') . "\n\n";
        $github_body .= "**What did you expect would happen:**\n" . (isset($_POST['bug_expected']) ? $_POST['bug_expected'] : 'N/A') . "\n\n";
        $github_body .= "**What happened:**\n" . (isset($_POST['bug_actual']) ? $_POST['bug_actual'] : 'N/A') . "\n\n";
        $github_body .= "**Extra information:**\n" . (isset($_POST['bug_extra']) ? $_POST['bug_extra'] : 'N/A') . "\n\n";
    } elseif ($type === 'api_request') {
        $github_body .= "## API Endpoint Request\n\n";
        $github_body .= "**Endpoint:**\n" . (isset($_POST['api_endpoint']) ? $_POST['api_endpoint'] : 'N/A') . "\n\n";
        $github_body .= "**Purpose:**\n" . (isset($_POST['api_purpose']) ? $_POST['api_purpose'] : 'N/A') . "\n\n";
        $github_body .= "**Expected behavior:**\n{$description}\n\n";
        $github_body .= "**Note:** This is a private request visible only to the submitter and developers.\n\n";
    } else {
        $github_body .= $description . "\n\n";
    }

    if ($image_path) {
        $github_body .= "**Attached image:** https://chat.wokki20.nl{$image_path}\n\n";
    }

    $github_body .= "---\n*Submitted via Wokki Chat Ideas Portal*";

    try {
        $github = new GitHubService();
        $github->ensureLabelsExist();

        $issue_data = $github->createIssue($title, $github_body, $type);
        $github_issue_number = $issue_data['number'];
        $github_issue_node_id = $issue_data['node_id'];

        if ($github_issue_node_id) {
            $github_project_item_id = $github->addIssueToProject($github_issue_node_id, 'voting');
        } else {
            $github_project_item_id = null;
        }
    } catch (Exception $e) {
        error_log("GitHub error: " . $e->getMessage());
        $github_issue_number = null;
        $github_issue_node_id = null;
        $github_project_item_id = null;
    }

    $stmt = $mysqli->prepare("INSERT INTO ideas (id, user_id, title, description, image_path, status, type, is_private, github_issue_number, github_issue_node_id, github_project_item_id) VALUES (?, ?, ?, ?, ?, 'voting', ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissssisis", $idea_id, $user_id, $title, $description, $image_path, $type, $is_private, $github_issue_number, $github_issue_node_id, $github_project_item_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success', 'description' => 'Submission created successfully', 'idea_id' => $idea_id, 'return_code' => 0]);

} else {
    echo json_encode(['status' => 'error', 'description' => 'Invalid request method', 'return_code' => 31]);
}
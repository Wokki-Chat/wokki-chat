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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        if ($_SERVER['HTTP_ORIGIN'] !== $allowedOrigin) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'description' => 'Forbidden: Invalid Origin', 'return_code' => 38]);
            exit;
        }
    } elseif (isset($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        if ($referer !== $allowedReferer) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'description' => 'Forbidden: Invalid Referer', 'return_code' => 39]);
            exit;
        }
    } else {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'description' => 'Forbidden: No Origin or Referer', 'return_code' => 40]);
        exit;
    }

    $headers = getallheaders();
    if (!isset($headers['Authorization']) || !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'description' => 'Unauthorized: Missing or invalid Authorization header', 'return_code' => 41]);
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
        echo json_encode(['status' => 'error', 'description' => 'Unauthorized: Invalid access token', 'return_code' => 42]);
        exit;
    }

    $user_id = $row['user_id'];

    $stmt = $mysqli->prepare("SELECT is_developer FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row || !isset($row['is_developer']) || !$row['is_developer'] == 1) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'description' => 'Unauthorized: User is not a developer', 'return_code' => 42]);
        exit;
    }

    $idea_id = $_POST['idea_id'];

    $stmt = $mysqli->prepare("SELECT status, github_project_item_id, github_issue_node_id FROM ideas WHERE id = ?");
    $stmt->bind_param("s", $idea_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $idea_row = $result->fetch_assoc();
    $stmt->close();

    $status_map = ['implemented' => 'planned', 'planned' => 'voting'];
    $new_status = $status_map[$idea_row['status'] ?? ''] ?? null;

    $stmt = $mysqli->prepare("
        UPDATE ideas 
        SET status = CASE 
            WHEN status = 'implemented' THEN 'planned'
            WHEN status = 'planned' THEN 'voting'
            ELSE status
        END
        WHERE id = ?
    ");
    $stmt->bind_param("s", $idea_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0 && $new_status) {
        $github = new GitHubService();
        $project_item_id = $idea_row['github_project_item_id'];

        if (!$project_item_id && $idea_row['github_issue_node_id']) {
            $project_item_id = $github->getProjectItemIdForIssue($idea_row['github_issue_node_id']);
            if ($project_item_id) {
                $stmt = $mysqli->prepare("UPDATE ideas SET github_project_item_id = ? WHERE id = ?");
                $stmt->bind_param("ss", $project_item_id, $idea_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        if ($project_item_id) {
            $github->setProjectItemStatus($project_item_id, $new_status);
        }

        echo json_encode(['status' => 'success', 'description' => 'Idea status updated successfully', 'idea_id' => $idea_id, 'return_code' => 0]);
    } else {
        echo json_encode(['status' => 'error', 'description' => 'Idea not found or no valid status transition', 'idea_id' => $idea_id, 'return_code' => 46]);
    }

} else {
    echo json_encode(['status' => 'error', 'description' => 'Invalid request method', 'return_code' => 43]);
}
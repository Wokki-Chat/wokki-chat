<?php
include 'config.php';
include 'github_service.php';

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

    $stmt = $mysqli->prepare("SELECT username, profile_picture, is_developer FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_row = $result->fetch_assoc();
    $stmt->close();

    if (!$user_row) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'description' => 'Forbidden: User not found', 'return_code' => 43]);
        exit;
    }

    if (!isset($_POST['idea_id']) || !isset($_POST['reply'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'description' => 'Bad Request: Missing idea_id or reply']);
        exit;
    }

    $idea_id = $_POST['idea_id'];
    $reply_text = trim($_POST['reply']);
    $reply_text = substr($reply_text, 0, 2000);

    if (empty($reply_text)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'description' => 'Bad Request: Reply cannot be empty']);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT github_issue_number, user_id FROM ideas WHERE id = ?");
    $stmt->bind_param("s", $idea_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $idea_row = $result->fetch_assoc();
    $stmt->close();

    if (!$idea_row || !$idea_row['github_issue_number']) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'description' => 'Not Found: Idea has no linked GitHub issue', 'return_code' => 44]);
        exit;
    }

    $username = $user_row['username'];
    $profile_picture = !empty($user_row['profile_picture']) ? "https://chat.wokki20.nl" . $user_row['profile_picture'] : '';

    $is_idea_author = ($idea_row['user_id'] == $user_id);

    if ($is_idea_author && $user_row['is_developer'] != 1) {
        $comment_body = "### User Response from **{$username}**\n";
        if ($profile_picture) {
            $comment_body .= "![{$username}]({$profile_picture})\n\n";
        }
        $comment_body .= "---\n\n" . $reply_text;
    } else {
        if (!isset($user_row['is_developer']) || $user_row['is_developer'] != 1) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'description' => 'Forbidden: User is not a developer', 'return_code' => 43]);
            exit;
        }
        $comment_body = "### 👨‍💻 Developer Response from **{$username}**\n";
        if ($profile_picture) {
            $comment_body .= "![{$username}]({$profile_picture})\n\n";
        }
        $comment_body .= "---\n\n" . $reply_text;
    }

    $github = new GitHubService();
    $success = $github->createComment((int)$idea_row['github_issue_number'], $comment_body);

    if ($success) {
        echo json_encode([
            'status' => 'success',
            'description' => 'Reply posted successfully',
            'return_code' => 0
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'description' => 'Failed to post reply to GitHub',
            'return_code' => 45
        ]);
    }

} else {
    echo json_encode(['status' => 'error', 'description' => 'Invalid request method', 'return_code' => 43]);
}
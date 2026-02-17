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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['idea_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'description' => 'Bad Request: Missing idea_id']);
        exit;
    }

    $idea_id = $_GET['idea_id'];

    $stmt = $mysqli->prepare("SELECT github_issue_number FROM ideas WHERE id = ?");
    $stmt->bind_param("s", $idea_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $idea_row = $result->fetch_assoc();
    $stmt->close();

    if (!$idea_row) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'description' => 'Not Found: Idea not found']);
        exit;
    }

    if (!$idea_row['github_issue_number']) {
        echo json_encode([
            'status' => 'success',
            'comments' => [],
            'assignees' => []
        ]);
        exit;
    }

    $github = new GitHubService();
    $issue_number = (int)$idea_row['github_issue_number'];

    $comments = $github->getIssueComments($issue_number);
    $assignees = $github->getIssueAssignees($issue_number);

    if (!empty($assignees)) {
        $assignees_json = json_encode($assignees);
        $stmt = $mysqli->prepare("UPDATE ideas SET github_assignees = ? WHERE id = ?");
        $stmt->bind_param("ss", $assignees_json, $idea_id);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'status' => 'success',
        'comments' => $comments,
        'assignees' => $assignees
    ]);

} else {
    echo json_encode(['status' => 'error', 'description' => 'Invalid request method', 'return_code' => 43]);
}
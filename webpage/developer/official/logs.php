<?php
include '../../app/config.php';

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

    $stmt = $mysqli->prepare("SELECT is_developer FROM users WHERE id = ? AND is_developer = 1 AND is_staff = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        if ($result->fetch_assoc()['is_developer'] == 1) {
            $logFile = __DIR__ . "/../../_private/logs/logs.txt";

            if (file_exists($logFile)) {
                header("Content-Type: text/plain");
                readfile($logFile);
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'description' => 'File not found',
                    'return_code' => 35
                ]);
                exit;
            }
        } else {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: User is not a developer',
                'return_code' => 34
            ]);
            exit;
        }
    } else {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'description' => 'Forbidden: User is not a developer',
            'return_code' => 34
        ]);
        exit;
    }

} else {
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 31
    ]);
}

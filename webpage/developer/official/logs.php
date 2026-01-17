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

function investigateUser($mysqli, $user_id) {
    $stmt = $mysqli->prepare("UPDATE users SET needs_investigation = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $headers = getallheaders();
    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        $access_token = $matches[1];
    } elseif (isset($_COOKIE['access_token'])) {
        $access_token = $_COOKIE['access_token'];
    }

    if (!$access_token) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'description' => 'Unauthorized: Missing or invalid Authorization header or cookie',
            'return_code' => 32
        ]);
        exit;
    }

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
                $linesToShow = 100;
                if (isset($_GET['lines']) && is_numeric($_GET['lines'])) {
                    $linesToShow = (int) $_GET['lines'];
                }

                $file = new SplFileObject($logFile, 'r');
                $file->seek(PHP_INT_MAX);
                $totalLines = $file->key() + 1;

                $startLine = max(0, $totalLines - $linesToShow);
                $file->seek($startLine);

                $hideWorkerPid = isset($_GET['hide_worker_pid']);
                $hideTimestamp = isset($_GET['hide_timestamp']);
                $hideFileName = isset($_GET['hide_file_name']);
                $hideType = isset($_GET['hide_type']);
                $hideMessage = isset($_GET['hide_message']);

                header("Content-Type: text/plain");

                while (!$file->eof()) {
                    $line = $file->current();
                    
                    if (preg_match('/^\[Worker Name: (.*?)\] \[(.*?)\] \[(.*?):(\d+)\] \[(.*?)\] -> (.*)$/', $line, $matches)) {
                        list(, $workerPid, $timestamp, $fileName, $lineNum, $type, $message) = $matches;

                        $parts = [];
                        if (!$hideWorkerPid) $parts[] = "[Worker Name: $workerPid]";
                        if (!$hideTimestamp) $parts[] = "[$timestamp]";
                        if (!$hideFileName) $parts[] = "[$fileName:$lineNum]";
                        if (!$hideType) $parts[] = "[$type]";
                        if (!$hideMessage) $parts[] = "-> $message";

                        echo implode(' ', $parts) . PHP_EOL;
                    } else {
                        echo $line;
                    }

                    $file->next();
                }
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
            header("Content-Type: text/plain");
            echo "
            You are not a developer, you cannot access this page.
            Your account will be investigated. You will be banned if found guilty.
            You can appeal your investigation here: info@wokki20.nl
            ";
            investigateUser($mysqli, $user_id);
            exit;
        }
    } else {
            header("Content-Type: text/plain");
            echo "
            You are not a developer, you cannot access this page.
            Your account will be investigated. You will be banned if found guilty.
            You can appeal your investigation here: info@wokki20.nl
            ";
            investigateUser($mysqli, $user_id);
            exit;
    }

} else {
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 31
    ]);
}

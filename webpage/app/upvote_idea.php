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

function saveImageAsWebp($imageResource, $savePath) {
    imagewebp($imageResource, $savePath, 80);
    imagedestroy($imageResource);
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $allowedOrigin = 'https://chat.wokki20.nl';

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        if ($_SERVER['HTTP_ORIGIN'] !== $allowedOrigin) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Origin',
                'return_code' => 27
            ]);
            exit;
        }
    } else if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        if ($referer !== 'chat.wokki20.nl') {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Referer',
                'return_code' => 28
            ]);
            exit;
        }
    } else {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'description' => 'Forbidden: No Origin or Referer',
            'return_code' => 29
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
    
    if (isset($_POST['idea_id'])) {
        $idea_id = $_POST['idea_id'];

        $stmt = $mysqli->prepare("SELECT id FROM ideas WHERE id = ?");
        $stmt->bind_param("s", $idea_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row || !isset($row['id'])) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'description' => 'Not Found: Idea not found',
                'return_code' => 34
            ]);
            exit;
        }

        $idea_id = $row['id'];

        $stmt = $mysqli->prepare("SELECT id FROM idea_votes WHERE idea_id = ? AND user_id = ?");
        $stmt->bind_param("ss", $idea_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row || !isset($row['id'])) {
            $stmt = $mysqli->prepare("INSERT INTO idea_votes (idea_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ss", $idea_id, $user_id);

            $stmt2 = $mysqli->prepare("SELECT COUNT(*) AS votes FROM idea_votes WHERE idea_id = ?");
            $stmt2->bind_param("s", $idea_id);
            $stmt->execute();
            $stmt->close();
            $stmt2->execute();
            $result = $stmt2->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $votes = $row['votes'];
            }
            $stmt2->close();
            echo json_encode([
                'status' => 'success',
                'description' => 'Idea upvoted',
                'voted' => 1,
                'votes' => $votes,
                'return_code' => 0
            ]);
        } else {
            $stmt = $mysqli->prepare("DELETE FROM idea_votes WHERE idea_id = ? AND user_id = ?");
            $stmt->bind_param("ss", $idea_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $stmt2 = $mysqli->prepare("SELECT COUNT(*) AS votes FROM idea_votes WHERE idea_id = ?");
            $stmt2->bind_param("s", $idea_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $votes = $row['votes'];
            }
            $stmt2->close();
            echo json_encode([
                'status' => 'success',
                'description' => 'Idea unvoted',
                'voted' => 0,
                'votes' => $votes,
                'return_code' => 0
            ]);
        }
    }

} else {
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 31
    ]);
}

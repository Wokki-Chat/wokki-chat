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

function getPriceForKudo($kudoId) {
    $kudoItems = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/app/assets/kudos/items.json'), true);
    foreach ($kudoItems as $kudoItem) {
        if ($kudoItem['id'] === $kudoId) {
            return $kudoItem['price'];
        }
    }
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

    if (!isset($_POST['kudo_id'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'description' => 'Bad request: Missing kudo_id',
            'return_code' => 30
        ]);
        exit;
    }

    $kudo_id = $_POST['kudo_id'];

    if ($kudo_id == 1) {
        $price = getPriceForKudo(1);

        $kudosStmt = $mysqli->prepare("SELECT id, kudo_amount FROM Kudos WHERE user_id = ? ORDER BY id ASC");
        $kudosStmt->bind_param("i", $user_id);
        $kudosStmt->execute();
        $kudosResult = $kudosStmt->get_result();
        $kudos = $kudosResult->fetch_all(MYSQLI_ASSOC);
        $kudosStmt->close();

        $remaining = $price;

        foreach ($kudos as $kudo) {
            if ($remaining <= 0) break;

            $kudoId = $kudo['id'];
            $amount = $kudo['kudo_amount'];

            if ($amount >= $remaining) {
                if ($amount == $remaining) {
                    $deleteStmt = $mysqli->prepare("DELETE FROM Kudos WHERE id = ?");
                    $deleteStmt->bind_param("i", $kudoId);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                } else {
                    $updateStmt = $mysqli->prepare("UPDATE Kudos SET kudo_amount = kudo_amount - ? WHERE id = ?");
                    $updateStmt->bind_param("ii", $remaining, $kudoId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
                $remaining = 0;

                $userStmtPremium = $mysqli->prepare("SELECT premium, premium_expires_at FROM users WHERE id = ?");
                $userStmtPremium->bind_param("i", $user_id);
                $userStmtPremium->execute();
                $userResultPremium = $userStmtPremium->get_result();
                $userPremium = $userResultPremium->fetch_assoc();
                $userStmtPremium->close();

                if ($userPremium['premium'] == 0) {
                    $updatePremiumStmt = $mysqli->prepare("UPDATE users SET premium = 1, premium_expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY), premium_know = 0 WHERE id = ?");
                    $updatePremiumStmt->bind_param("i", $user_id);
                    $updatePremiumStmt->execute();
                    $updatePremiumStmt->close();
                } else {
                    if ($userPremium['premium_expires_at'] !== null) {
                        $updatePremiumStmt = $mysqli->prepare("UPDATE users SET premium_expires_at = DATE_ADD(premium_expires_at, INTERVAL 30 DAY), premium_know = 0 WHERE id = ?");
                        $updatePremiumStmt->bind_param("i", $user_id);
                        $updatePremiumStmt->execute();
                        $updatePremiumStmt->close();
                    }
                }

            } else {
                $deleteStmt = $mysqli->prepare("DELETE FROM Kudos WHERE id = ?");
                $deleteStmt->bind_param("i", $kudoId);
                $deleteStmt->execute();
                $deleteStmt->close();
                $remaining -= $amount;
            }
        }

        if ($remaining > 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'description' => 'Bad request: Not enough kudos',
                'return_code' => 30
            ]);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'description' => 'Kudos successfully spent',
            'return_code' => 0
        ]);
    } else if ($kudo_id == 2) {
        $price = getPriceForKudo(2);

        $kudosStmt = $mysqli->prepare("SELECT id, kudo_amount FROM Kudos WHERE user_id = ? ORDER BY id ASC");
        $kudosStmt->bind_param("i", $user_id);
        $kudosStmt->execute();
        $kudosResult = $kudosStmt->get_result();
        $kudos = $kudosResult->fetch_all(MYSQLI_ASSOC);
        $kudosStmt->close();

        $remaining = $price;

        foreach ($kudos as $kudo) {
            if ($remaining <= 0) break;

            $kudoId = $kudo['id'];
            $amount = $kudo['kudo_amount'];

            if ($amount >= $remaining) {
                if ($amount == $remaining) {
                    $deleteStmt = $mysqli->prepare("DELETE FROM Kudos WHERE id = ?");
                    $deleteStmt->bind_param("i", $kudoId);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                } else {
                    $updateStmt = $mysqli->prepare("UPDATE Kudos SET kudo_amount = kudo_amount - ? WHERE id = ?");
                    $updateStmt->bind_param("ii", $remaining, $kudoId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
                $remaining = 0;

                $userStmtPremium = $mysqli->prepare("SELECT premium, premium_expires_at FROM users WHERE id = ?");
                $userStmtPremium->bind_param("i", $user_id);
                $userStmtPremium->execute();
                $userResultPremium = $userStmtPremium->get_result();
                $userPremium = $userResultPremium->fetch_assoc();
                $userStmtPremium->close();

                if ($userPremium['premium'] == 0) {
                    $updatePremiumStmt = $mysqli->prepare("UPDATE users SET premium = 1, premium_expires_at = DATE_ADD(NOW(), INTERVAL 1 YEAR), premium_know = 0 WHERE id = ?");
                    $updatePremiumStmt->bind_param("i", $user_id);
                    $updatePremiumStmt->execute();
                    $updatePremiumStmt->close();
                } else {
                    if ($userPremium['premium_expires_at'] !== null) {
                        $updatePremiumStmt = $mysqli->prepare("UPDATE users SET premium_expires_at = DATE_ADD(premium_expires_at, INTERVAL 1 YEAR), premium_know = 0 WHERE id = ?");
                        $updatePremiumStmt->bind_param("i", $user_id);
                        $updatePremiumStmt->execute();
                        $updatePremiumStmt->close();
                    }
                }

            } else {
                $deleteStmt = $mysqli->prepare("DELETE FROM Kudos WHERE id = ?");
                $deleteStmt->bind_param("i", $kudoId);
                $deleteStmt->execute();
                $deleteStmt->close();
                $remaining -= $amount;
            }
        }

        if ($remaining > 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'description' => 'Bad request: Not enough kudos',
                'return_code' => 30
            ]);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'description' => 'Kudos successfully spent',
            'return_code' => 0
        ]);        
    }

    
} else {
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 31
    ]);
}

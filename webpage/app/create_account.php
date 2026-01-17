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

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function generateAccessToken() {
    return bin2hex(random_bytes(32));
}

function generateRefreshToken() {
    return bin2hex(random_bytes(64));
}

function getTokenExpirationTime($tokenType = 'access') {
    if ($tokenType === 'access') {
        return 60 * 60 * 24 * 30;
    } else {
        return 60 * 60 * 24 * 60;
    }
}


function registerUser($mysqli, $username, $email, $password, $mail_password) {
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        return [
            'status' => 'error',
            'description' => 'User with this email or username already exists',
            'return_code' => 0
        ];
    };

    $password_hash = hashPassword($password);
    
    try {
        $stmt = $mysqli->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $password_hash);
        $stmt->execute();
        $user_id = $stmt->insert_id;
        $stmt->close();

        $activatecode = bin2hex(random_bytes(16));

        $stmt = $mysqli->prepare("INSERT INTO email_verification_codes (user_id, code, expiry_date) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->bind_param("is", $user_id, $activatecode);
        $stmt->execute();
        $stmt->close();

        if (!sendVerificationEmail($email, $activatecode, $user_id, $mail_password)) {
            return [
                'status' => 'error',
                'description' => 'Failed to send verification email',
                'return_code' => 1
            ];
        }

        $access_token = generateAccessToken();
        $refresh_token = generateRefreshToken();
        $access_token_expiration = date('Y-m-d H:i:s', time() + getTokenExpirationTime('access'));
        $refresh_token_expiration = date('Y-m-d H:i:s', time() + getTokenExpirationTime('refresh'));

        $stmt = $mysqli->prepare("INSERT INTO user_tokens (user_id, access_token, refresh_token, access_token_expires_at, refresh_token_expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $access_token, $refresh_token, $access_token_expiration, $refresh_token_expiration);
        $stmt->execute();
        $stmt->close();

        return [
            'status' => 'success',
            'description' => 'User registered successfully',
            'data' => [
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email,
                'access_token' => $access_token,
                'refresh_token' => $refresh_token,
                'access_token_expiration' => $access_token_expiration,
                'refresh_token_expiration' => $refresh_token_expiration
            ],
            'return_code' => 2
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Database error: ' . $e->getMessage(),
            'return_code' => 18
        ];
    }

}

function sendVerificationEmail($email, $activatecode, $user_id, $mail_password) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'mail.wokki20.nl';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@wokki20.nl';
        $mail->Password = $mail_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        
        $mail->setFrom('noreply@wokki20.nl', 'wokki20 Chat');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Verify your account';

        $htmlTemplate = file_get_contents('assets/email_activate_template.html');
        $activationLink = 'https://chat.wokki20.nl/app/activate_account?activatecode=' . $activatecode . '&user_id=' . $user_id;
        $htmlTemplate = str_replace('{{activate_link}}', $activationLink, $htmlTemplate);
        $htmlTemplate = str_replace('{{support_link}}', 'mailto:info@wokki20.nl', $htmlTemplate);

        $mail->Body = $htmlTemplate;

        if (!$mail->send()) {
            throw new Exception('Failed to send verification email');
        }

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => $e->getMessage(),
            'return_code' => 3
        ];
    }

    return [
        'status' => 'success',
        'description' => 'Verification email sent successfully',
        'return_code' => 4
    ];
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
    } else if (isset($_SERVER['HTTP_REFERER'])) {
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

    if (isset($_POST['username'], $_POST['email'], $_POST['password'])) {
        $create_username = $mysqli->real_escape_string($_POST['username']);
        $create_email = $mysqli->real_escape_string($_POST['email']);
        $create_password = $mysqli->real_escape_string($_POST['password']);
        
        if (empty($create_username) || empty($create_email) || empty($create_password)) {
            echo json_encode([
                'status' => 'error',
                'description' => 'All fields are required',
                'return_code' => 7
            ]);
            exit;
        }

        if (strlen($create_password) < 8) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Password must be at least 8 characters long',
                'return_code' => 8
            ]);
            exit;
        }
        if (preg_match('/^\s*$/', $create_password)) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Password cannot contain only spaces',
                'return_code' => 9
            ]);
            exit;
        }
        if (preg_match('/[^\x20-\x7E]/', $create_password)) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Password contains invalid characters',
                'return_code' => 10
            ]);
            exit;
        }
        if (preg_match('/[^\x20-\x7E]/', $create_username)) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Username contains invalid characters',
                'return_code' => 11
            ]);
            exit;
        }
        if (preg_match('/^\s*$/', $create_username)) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Username cannot contain only spaces',
                'return_code' => 12
            ]);
            exit;
        }
        if (preg_match('/[^a-zA-Z0-9\- _]/', $create_username)) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Username can only contain letters, numbers, hyphens, spaces, and underscores',
                'return_code' => 13
            ]);
            exit;
        }
        if (strlen($create_username) < 3) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Username must be at least 3 characters long',
                'return_code' => 14
            ]);
            exit;
        }

        if (preg_match('/\n|\r/', $create_username)) {
            echo json_encode([
                'status' => 'error',
                'description' => 'Username cannot contain newlines',
                'return_code' => 15
            ]);
            exit;
        }

        $response = registerUser($mysqli, $create_username, $create_email, $create_password, $mail_password);

        echo json_encode($response);
    } else {
        echo json_encode([
            'status' => 'error',
            'description' => 'Missing required fields',
            'missing_fields' => array_diff(['username', 'email', 'password'], array_keys($_POST)),
            'return_code' => 16
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 17
    ]);
}
?>

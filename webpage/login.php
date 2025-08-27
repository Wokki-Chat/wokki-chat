<?php
include 'app/config.php';

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


header('Content-Type: text/html; charset=utf-8');


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

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login?return_code=23');
    exit;
}

if ((!isset($_POST['email']) || !isset($_POST['password'])) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: login?return_code=24');
    exit;
}

if (!isset($_POST['email']) || !isset($_POST['password']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $redirect = $_GET['redirect'] ?? '/home';
    echo '

        <!DOCTYPE html>
        <html lang="en" class="login dark">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login</title>
            <link rel="stylesheet" href="assets/styles/main.css">
            <link rel="icon" type="image/x-icon" href="favicon.ico">
        </head>
        <body>
            <form action="login" method="post" class="login-form">
                <div class="login-form-content">
                    <h2>Login</h2>
                    <div class="input-container">
                        <label for="email">Email:</label>
                        <input type="email" name="email" placeholder="johndoe@example.com" required class="input-text-dark-bg w270" minlength="3">
                    </div>
                    <div class="input-container">
                        <label for="password">Password:</label>
                        <input type="password" name="password" placeholder="myVeryStrongPassword123" required class="input-text-dark-bg w270" minlength="8">
                    </div>
                    <input type="hidden" name="redirect" value="' . $redirect . '">
                    <button type="submit" class="button-primary-filled">Login</button>
                    <p>Don\'t have an account? <a href="register" class="link">Register</a></p>
                    <p class="error-message">An unknown error occurred, please try again</p>
                </div>
            </form>
            <script>
                const returnCode = new URLSearchParams(window.location.search).get(\'return_code\');
                const errorMessage = document.querySelector(\'.error-message\');

                if (returnCode === \'23\') {
                    errorMessage.textContent = \'The request could not be processed.\';
                    errorMessage.style.display = \'block\';
                } else if (returnCode === \'24\') {
                    errorMessage.textContent = \'The request is invalid or missing required information.\';
                    errorMessage.style.display = \'block\';
                } else if (returnCode === \'25\') {
                    errorMessage.textContent = \'The email address or password entered is incorrect.\';
                    errorMessage.style.display = \'block\';
                } else if (returnCode === \'26\') {
                    errorMessage.textContent = \'Please verify your email address before attempting to sign in.\';
                    errorMessage.style.display = \'block\';
                }

            </script>
        </body>
        </html>
    ';
    exit;
}

if (isset($_POST['email']) && isset($_POST['password']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $redirect = $_POST['redirect'] ?? '/home';

    $stmt = $mysqli->prepare("SELECT password_hash, id, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $password_hash = $row['password_hash'];
        $user_id = $row['id'];
        $email_verified = $row['email_verified'];

        $stmt->close();

        if (password_verify($password, $password_hash)) {
            if ($email_verified === 1) {
                $access_token = generateAccessToken();
                $refresh_token = generateRefreshToken();
                $access_token_expiration = date('Y-m-d H:i:s', time() + getTokenExpirationTime('access'));
                $refresh_token_expiration = date('Y-m-d H:i:s', time() + getTokenExpirationTime('refresh'));

                $stmt = $mysqli->prepare("INSERT INTO user_tokens (user_id, access_token, refresh_token, access_token_expires_at, refresh_token_expires_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user_id, $access_token, $refresh_token, $access_token_expiration, $refresh_token_expiration);
                $stmt->execute();
                $stmt->close();

                session_start();
                $_SESSION['access_token'] = $access_token;
                $_SESSION['refresh_token'] = $refresh_token;
                $_SESSION['access_token_expires_at'] = $access_token_expiration;
                $_SESSION['refresh_token_expires_at'] = $refresh_token_expiration;

                setcookie('access_token', $access_token, [
                    'expires' => time() + getTokenExpirationTime('access'),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                setcookie('refresh_token', $refresh_token, [
                    'expires' => time() + getTokenExpirationTime('refresh'),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                setcookie('access_token_expires_at', $access_token_expiration, [
                    'expires' => time() + getTokenExpirationTime('access'),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                setcookie('refresh_token_expires_at', $refresh_token_expiration, [
                    'expires' => time() + getTokenExpirationTime('refresh'),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);

                echo '
                <script>
                    window.location.href = \'' . $redirect . '\
                </script>
                ';

                header('Location: ' . $redirect);
                exit;
            } else {
                header('Location: login?return_code=26');
                exit;
            }
        } else {
            header('Location: login?return_code=25');
            exit;
        }
    } else {
        header('Location: login?return_code=25');
        exit;
    }
}

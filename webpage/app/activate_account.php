<?php
include 'config.php';

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


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: /login?return_code=19');
    exit;
}

if (isset($_GET['activatecode']) && isset($_GET['user_id'])) {
    $activatecode = $_GET['activatecode'];
    $user_id = $_GET['user_id'];

    $stmt = $mysqli->prepare("SELECT id FROM email_verification_codes WHERE user_id = ? AND code = ? AND expiry_date > NOW()");
    $stmt->bind_param("is", $user_id, $activatecode);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $stmt = $mysqli->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("DELETE FROM email_verification_codes WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        header('Location: /login?return_code=20');
        exit;
    } else {
        $stmt->close();
        header('Location: /login?return_code=21');
        exit;
    }
} else {
    header('Location: /login?return_code=19');
    exit;
}
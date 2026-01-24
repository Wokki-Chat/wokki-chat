<?php
include '../../../app/config.php';
include '../../../global.php';
$access_token = $_COOKIE['access_token'];

if (!$access_token) {
    header('Location: /login?redirect=/developer/docs');
    exit;
}

header("Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ?");
$stmt->bind_param("s", $access_token);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
}
$stmt->close();

if (!$user_id) {
    header('Location: /login?redirect=/developer/docs');
    exit;
}

$stmt = $mysqli->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $username = $row['username'];
    $profile_picture = $row['profile_picture'];
}
$stmt->close();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

$serving_file = null;

if (isset($segments[0], $segments[1]) && $segments[0] === 'developer' && $segments[1] === 'docs') {
    if (!empty($segments[2]) && $segments[2] === 'python') {
        $sub_path = implode('/', array_slice($segments, 3));
        $decoded = urldecode($sub_path);
        $decoded = ltrim($decoded, '/');

        $full_path = '../../python-docs-raw/' . $decoded;

        if (is_file($full_path)) {
            $serving_file = $full_path;
        }
    }
}

if ($serving_file) {
    $file_contents = file_get_contents($serving_file);

    $theme_parts = explode(' ', $theme);
    $theme_name = $theme_parts[0];

    $file_contents = str_replace('{php-var{username}}', htmlspecialchars($username), $file_contents);
    $file_contents = str_replace('{php-var{profile_picture}}', $profile_picture, $file_contents);
    $file_contents = str_replace('{php-var{theme}}', htmlspecialchars($theme_name), $file_contents);

    header('Content-Type: text/html');
    echo $file_contents;
    exit;
}

$file_contents = file_get_contents('../../python-docs-raw/wokkichat.html');
header('Content-Type: text/html');

$theme_parts = explode(' ', $theme);
$theme_name = $theme_parts[0];

$file_contents = str_replace('{php-var{username}}', htmlspecialchars($username), $file_contents);
$file_contents = str_replace('{php-var{profile_picture}}', $profile_picture, $file_contents);
$file_contents = str_replace('{php-var{theme}}', htmlspecialchars($theme_name), $file_contents);

echo $file_contents;
exit;
?>
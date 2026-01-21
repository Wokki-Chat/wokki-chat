<?php
include '../app/config.php';
include '../global.php';
$access_token = $_COOKIE['access_token'];

if (!$access_token) {
    header('Location: /login?redirect=/developer/bots');
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
    header('Location: /login?redirect=/developer/bots');
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
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wokki Chat Developer Portal</title>
    <link rel="stylesheet" href="../assets/styles/developer/main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="../assets/images/logo-purple.png" alt="Wokki Chat Logo">
            <p>For Developers</p>
        </div>
        <div class="top-bar-profile" id="top-bar-profile">
            <img draggable="false" class="top-bar-profile-picture" src="<?php echo $profile_picture; ?>">
            <p class="top-bar-username"><?php echo $username; ?></p>
        </div>
    </div>
    <div class="sidebar">
        <a class="sidebar-item active" href="/developer/portal">
            <span class="material-symbols-rounded sidebar-item-icon">home</span>
            <span class="sidebar-item-text">Portal</span>
        </a>
        <a class="sidebar-item" href="/developer/bots">
            <span class="material-symbols-rounded sidebar-item-icon">smart_toy</span>
            <span class="sidebar-item-text">Bots</span>
        </a>
        <a class="sidebar-item" href="/developer/docs">
            <span class="material-symbols-rounded sidebar-item-icon">book_2</span>
            <span class="sidebar-item-text">Documentation</span>
        </a>
    </div>
    <div class="content" id="app">
        <h1 class="content-title">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
        <p class="content-description">Wokki Chat provides lots of documentation and features for developers to make the most out of Wokki Chat.</p>
        <wchat-allowed-scripts value="developer/portal.js;"></wchat-allowed-scripts>
        <wchat-data id="access-token" value="<?php echo htmlspecialchars($access_token); ?>"></wchat-data>
        <wchat-data id="page" value="/developer/portal"></wchat-data>
    </div>
    <script src="../assets/js/developer/portal.js"></script>
    <script src="../assets/js/load_scripts.js"></script>
    <script type="module" data-swup-ignore-script>
        import Swup from "https://unpkg.com/swup@4?module";
        import SwupPreloadPlugin from "https://unpkg.com/@swup/preload-plugin@3?module";
        import SwupScriptsPlugin from "https://unpkg.com/@swup/scripts-plugin@2?module";

        window.swup = new Swup({
            containers: ["#app"],
            cache: false,
            plugins: [
                new SwupPreloadPlugin(),
                new SwupScriptsPlugin({
                    body: true,
                    head: false,
                })
            ]
        });
    </script>
</body>
</html>
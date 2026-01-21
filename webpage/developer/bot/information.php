<?php
include '../../app/config.php';
include '../../global.php';
$access_token = $_COOKIE['access_token'];

if (!$access_token) {
    header('Location: /login?redirect=/developer/bots');
    exit;
}

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

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

$bot_id = $_GET['bot_id'] ?? null;

if (!$bot_id) {
    header('Location: /developer/bots');
    exit;
}

$botsStmt = $mysqli->prepare("SELECT name, profile_picture, bot_token, bio FROM bots WHERE id = ?");
$botsStmt->bind_param("s", $bot_id);
$botsStmt->execute();
$botsResult = $botsStmt->get_result();
$bot = $botsResult->fetch_assoc();
$botsStmt->close();

if (!$bot) {
    header('Location: /developer/bots');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wokki Chat Developer Portal - Bot Information</title>
    <link rel="stylesheet" href="/assets/styles/developer/main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="/assets/images/logo-purple.png" alt="Wokki Chat Logo">
            <p>For Developers</p>
        </div>
        <div class="top-bar-profile" id="top-bar-profile">
            <img draggable="false" class="top-bar-profile-picture" src="<?php echo $profile_picture; ?>">
            <p class="top-bar-username"><?php echo htmlspecialchars($username); ?></p>
        </div>
    </div>
    <div class="sidebar">
        <a class="sidebar-back-button" href="/developer/bots">
            <span class="material-symbols-rounded sidebar-item-icon">arrow_back</span>
            <span class="sidebar-item-text">Back to Bots</span>
        </a>
        <a class="sidebar-item" href="/developer/bot/<?php echo $bot_id; ?>/information">
            <span class="material-symbols-rounded sidebar-item-icon">info</span>
            <span class="sidebar-item-text">Bot Information</span>
        </a>
    </div>
    <div class="content" id="app">
        <h1 class="content-title"><?php echo htmlspecialchars($bot['name']); ?></h1>
        <p class="content-description">Here you'll find the general information regarding your bot.</p>
        <div class="developer-bot-profile">
            <div class="developer-bot-profile-picture-preview-container">
                <img src="<?php echo $bot['profile_picture']; ?>" alt="Profile Picture" class="profile-picture-preview">
                <span class="hover-text material-symbols-rounded">upload</span>
            </div>
            <div class="developer-bot-profile-info">
                <div class="developer-bot-profile-info-container">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" class="input-text-dark-bg w270" value="<?php echo htmlspecialchars($bot['name']); ?>">
                    <br>
                    <label for="bio">Bio:</label>
                    <input type="text" id="bio" name="bio" class="input-text-dark-bg w270" value="<?php echo htmlspecialchars($bot['bio']); ?>" placeholder="Give your bot a short bio">
                    <br>
                    <label for="bot-token">Bot Token:</label>
                    <input type="password" id="bot-token" name="bot-token" class="input-text-dark-bg w270" value="<?php echo $bot['bot_token']; ?>" readonly>
                    <div class="bot-token-buttons">
                        <button class="button-primary-filled" id="show-btn">Show Token</button>
                        <button class="button-primary-filled" id="copy-btn">Copy Token</button>
                    </div>
                    <br>
                    <label for="bot-invite">Bot Invite:</label>
                    <input type="text" id="bot-invite" name="bot-invite" class="input-text-dark-bg w270" value="https://chat.wokki20.nl/bot/invite/<?php echo $bot_id; ?>" readonly>
                    <div class="bot-token-buttons">
                        <button class="button-primary-filled" id="copy-invite-btn">Copy Invite</button>
                    </div>
                </div>
                <button class="button-primary-filled" id="save-bot-button">Save Bot</button>
            </div>
        </div>
        <wchat-allowed-scripts value="developer/bot_info.js;"></wchat-allowed-scripts>
        <wchat-data id="access-token" value="<?php echo htmlspecialchars($access_token); ?>"></wchat-data>
        <wchat-data id="user-id" value="<?php echo $user_id; ?>"></wchat-data>
        <wchat-data id="bot-id" value="<?php echo $bot_id; ?>"></wchat-data>
        <wchat-data id="orignal-bot-name" value="<?php echo htmlspecialchars($bot['name']); ?>"></wchat-data>
        <wchat-data id="orignal-bot-bio" value="<?php echo htmlspecialchars($bot['bio']); ?>"></wchat-data>
        <wchat-data id="page" value="/developer/bot/<?php echo $bot_id; ?>/information"></wchat-data>
    </div>
    <script src="/assets/js/developer/bot_info.js"></script>
    <script src="/assets/js/load_scripts.js"></script>
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
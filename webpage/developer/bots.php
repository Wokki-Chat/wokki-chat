<?php
include '../app/config.php';
include '../global.php';
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

$botsStmt = $mysqli->prepare("SELECT id, name, profile_picture FROM bots WHERE created_by = ?");
$botsStmt->bind_param("i", $user_id);
$botsStmt->execute();
$botsResult = $botsStmt->get_result();
$bots = $botsResult->fetch_all(MYSQLI_ASSOC);
$botsStmt->close();

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
        <div class="top-bar-profile-dropdown">
            <a class="top-bar-profile-dropdown-item" href="/logout">
                <span class="material-symbols-rounded top-bar-profile-dropdown-item-icon">logout</span>
                <p class="top-bar-profile-dropdown-item-text">Logout</p>
            </a>
        </div>
    </div>
    <div class="sidebar">
        <a class="sidebar-item" href="/developer/portal">
            <span class="material-symbols-rounded sidebar-item-icon">home</span>
            <span class="sidebar-item-text">Portal</span>
        </a>
        <a class="sidebar-item active" href="/developer/bots">
            <span class="material-symbols-rounded sidebar-item-icon">smart_toy</span>
            <span class="sidebar-item-text">Bots</span>
        </a>
        <a class="sidebar-item" href="/developer/docs">
            <span class="material-symbols-rounded sidebar-item-icon">book_2</span>
            <span class="sidebar-item-text">Documentation</span>
        </a>
    </div>
    <div class="content" id="app">
        <h1 class="content-title">Bots</h1>
        <p class="content-description">Manage and create bots to enhance your Wokki Chat experience.</p>
        <h3>Your Bots:</h3>
        <div class="developer-bots">
            <?php
            foreach ($bots as $bot) {
                echo '
                <div class="developer-bot" onclick="window.location.href = \'/developer/bot/' . $bot['id'] . '\' ">
                    <img class="developer-bot-profile-picture" src="' . $bot['profile_picture'] . '" alt="' . $bot['name'] . '">
                    <p class="developer-bot-name">' . $bot['name'] . '</p>
                </div>';
            }       
            ?>
            <div class="developer-bot" id="add-bot">
                <div class="add-bot-container"><span class="material-symbols-rounded add-bot">add</span></div>
                <p class="developer-bot-name">Create New Bot</p>
            </div>
        </div>
        <wchat-allowed-scripts value="developer/bots.js;"></wchat-allowed-scripts>
        <wchat-data id="access-token" value="<?php echo htmlspecialchars($access_token); ?>"></wchat-data>
        <wchat-data id="user-id" value="<?php echo $user_id; ?>"></wchat-data>
    </div>
    <script src="../assets/js/developer/bots.js"></script>
    <script src="../assets/js/load_scripts.js"></script>
    <script type="module" data-swup-ignore-script>
        import Swup from "https://unpkg.com/swup@4?module";
        import SwupPreloadPlugin from "https://unpkg.com/@swup/preload-plugin@3?module";
        import SwupScriptsPlugin from "https://unpkg.com/@swup/scripts-plugin@2?module";

        window.swup = new Swup({
            containers: ["#app"],
            cache: true,
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
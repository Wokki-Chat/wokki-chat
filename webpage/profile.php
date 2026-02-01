<?php
include 'app/config.php';
include 'global.php';

if (!isset($_COOKIE['access_token'])) {
    header('Location: login');
    exit;
}
$access_token = $_COOKIE['access_token'];

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
    header('Location: login');
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

$profile_user_name = null;
if (isset($segments[0]) && $segments[0] === 'profile' && !empty($segments[1])) {
    $profile_user_name = substr(urldecode($segments[1]), 1);
}

if (!$profile_user_name) {
    header('Location: /home');
    exit;
}

$stmt = $mysqli->prepare("SELECT username, profile_picture, premium, premium_expires_at, premium_know FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $my_username = $row['username'];
    $my_profile_picture = $row['profile_picture'];
    $premium = $row['premium'];
    $premium_expires_at = $row['premium_expires_at'];
    $premium_know = $row['premium_know'];
}
$stmt->close();

$serverStmt = $mysqli->prepare("
    SELECT s.*
    FROM servers s
    INNER JOIN server_members sm ON sm.server_id = s.id
    WHERE sm.user_id = ?
");
$serverStmt->bind_param("i", $user_id);
$serverStmt->execute();
$serverResult = $serverStmt->get_result();
$serverStmt->close();
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wokki Chat - Profile</title>
    <link rel="stylesheet" href="/assets/styles/main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="/assets/js/call_reconnect.js"></script>
</head>
<body>
    <div class="server-bar">
        <div class="server-bar-dms">
            <div class="server-bar-item active">
                <img src="/assets/images/monochrome-logo-purple-background.png">
            </div>
        </div>
        <div class="divider"></div>
        <div class="server-bar-channels">
            <?php
            if ($serverResult->num_rows > 0) {
                while ($serverRow = $serverResult->fetch_assoc()) {
                    $lowImage = preg_replace('/\.(webp|gif)$/', '-low.$1', $serverRow['image']);

                    echo '<a class="server-bar-item" id="server-bar-item-server" href="/server/'.$serverRow['id'].'" data-server-id="'.$serverRow['id'].'">
                        <img src="'.$lowImage.'" loading="lazy" decoding="async" width="47" height="47" draggable="false" />
                        <p class="tooltip">'.$serverRow['name'].'</p>
                    </a>';
                }
            }
            ?>
        </div>
        <div class="server-bar-options">    
            <div class="server-bar-option">
                <div class="server-bar-option-icon" onclick="openCreateServerModal()">
                    <span class="material-symbols-rounded">add_circle</span>
                </div>
                <p class="tooltip">create server</p>
            </div>
        </div>
    </div>
    <main id="app">
        <wchat-data id="access-token" value="<?php echo htmlspecialchars($access_token); ?>"></wchat-data>
    </main>
    <script src="/assets/js/socket.js" data-swup-ignore-script></script>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js" data-swup-ignore-script></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js" data-swup-ignore-script></script>
    <script src="/assets/js/create_server.js" data-swup-ignore-script></script>
    <script src="/assets/js/globalFunctions.js" data-swup-ignore-script></script>
    <script src="/assets/js/notifiers.js" data-swup-ignore-script></script>
</body>
</html>
<?php
session_start();
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

$stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $profile_user_name);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profile_user_id = $row['id'];
} else {
    header('Location: /home');
    exit;
}
$stmt->close();

$stmt = $mysqli->prepare("SELECT username, profile_picture, premium, premium_expires_at, premium_know FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $username = $row['username'];
    $profile_picture = $row['profile_picture'];
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

$friendsStmt = $mysqli->prepare("
    SELECT f1.friend_id
    FROM friends f1
    JOIN friends f2 ON f1.friend_id = f2.user_id AND f2.friend_id = f1.user_id
    WHERE f1.user_id = ?
");
$friendsStmt->bind_param("i", $user_id);
$friendsStmt->execute();
$friendsResult = $friendsStmt->get_result();

$friendsList = [];

while ($row = $friendsResult->fetch_assoc()) {
    $friendId = $row['friend_id'];

    $userStmt = $mysqli->prepare("SELECT username, profile_picture, status, premium FROM users WHERE id = ?");
    $userStmt->bind_param("i", $friendId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();

    if ($userData = $userResult->fetch_assoc()) {
        $friendsList[] = [
            'id' => $friendId,
            'username' => $userData['username'],
            'profile_picture' => $userData['profile_picture'],
            'status' => $userData['status'], 
            'premium' => $userData['premium']
        ];
    }

    $userStmt->close();
}

$friendsStmt->close();

$premium_popup = false;

if ($premium && !$premium_know && ($premium_expires_at > time() || $premium_expires_at === null)) {
    $premium_know = true;
    $stmt = $mysqli->prepare("UPDATE users SET premium_know = ? WHERE id = ?");
    $stmt->bind_param("ii", $premium_know, $user_id);
    $stmt->execute();
    $stmt->close();
    $premium_popup = true;
}

$premium_active = $premium && ($premium_expires_at > time() || $premium_expires_at === null);

function formatPremiumExpiration($timestamp) {
    if ($timestamp === null) {
        return "never";
    }
    if (!is_numeric($timestamp)) {
        $timestamp = strtotime($timestamp);
    }
    $now = time();
    $diff = $timestamp - $now;
    if ($diff <= 0) {
        return "0 days";
    }
    $days = ceil($diff / 86400);
    return $days . " days";
}
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
    <link rel="stylesheet" href="https://cdn.wokki20.nl/dynamic/jspt/jspt.css">
    <script src="https://cdn.wokki20.nl/dynamic/jspt/jspt.js"></script>
</head>
<body>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.css"/>
    <script src="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.js"></script>
    <div class="server-bar">
        <div class="server-bar-dms">
            <a class="server-bar-item active" id="server-bar-item-home" href="/home">
                <img src="/assets/images/monochrome-logo-purple-background.png">
            </a>
        </div>
        <div class="divider"></div>
        <div class="server-bar-channels">
            <?php
            if ($serverResult->num_rows > 0) {
                while ($serverRow = $serverResult->fetch_assoc()) {
                    $lowImage = preg_replace('/\.(webp|gif)$/', '-low.$1', $serverRow['image']);

                    echo '<a class="server-bar-item" id="server-bar-item-server" href="/server/'.$serverRow['id'].'" data-server-id="'.$serverRow['id'].'">
                        <img src="'.$lowImage.'" loading="lazy" decoding="async" width="47" height="47" draggable="false"/>
                        <p class="tooltip">'.$serverRow['name'].'</p>
                    </a>';
                }
            }
            ?>
        </div>
        <div class="server-bar-options">    
            <div class="server-bar-option">
                <div class="server-bar-option-icon" id="open-create-server-modal">
                    <span class="material-symbols-rounded">add_circle</span>
                </div>
                <p class="tooltip">create server</p>
            </div>
        </div>
    </div>
    <main id="app">
        <div class="channel-bar">
        </div>

        <div class="top-bar">
            <div class="top-bar-left">
                <span class="material-symbols-rounded top-bar-menu" id="top-bar-menu">menu</span>
                
            </div>
        </div>

        <div class="users">
            
        </div>

        <div class="self-info">
            <div class="self-info-left">
                <div class="self-info-profile-status">
                    <img class="self-info-profile-picture" src="<?php echo $profile_picture; ?>">
                    <div class="self-info-status-circle-outer">
                        <div class="self-info-status-circle-inner"></div>
                    </div>
                </div>
                <div class="self-info-status-username">
                    <p class="self-info-username"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="self-info-status">Online</p>
                </div>
            </div>

            <div class="self-info-right" >
                <a class="material-symbols-rounded self-info-right-settings no-underline" href="/settings?from=/home">settings</a>
            </div>
        </div>

        <?php if ($premium_popup): ?>
            <div class="premium-popup">
                <div class="premium-popup-content">
                    <div class="premium-popup-icon">
                        <span class="material-symbols-rounded premium-popup-icon-icon">star</span>
                    </div>
                    <div class="premium-popup-text">
                        <h3>You got upgraded to premium</h3>
                        <p>You unlocked all premium features</p>
                        <p>Premium expires in <?php echo formatPremiumExpiration($premium_expires_at); ?></p>
                    </div>
                    <div class="premium-popup-close">
                        <button class="button-primary-filled" onclick="this.parentElement.parentElement.parentElement.remove();">Okay</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <wchat-allowed-scripts value="profile.js;"></wchat-allowed-scripts>
        <wchat-data id="access-token" value="<?php echo htmlspecialchars($access_token); ?>"></wchat-data>
        <wchat-data id="user-id" value="<?php echo htmlspecialchars($user_id); ?>"></wchat-data>
        <wchat-data id="requested-user-id" value="<?php echo htmlspecialchars($profile_user_id); ?>"></wchat-data>
        <wchat-data id="users-list" value="<?php echo htmlspecialchars(json_encode($friendsList)); ?>"></wchat-data>
        <wchat-data id="last-page" value="<?php 
            $lastPage = $_SESSION['last_page'];
            if ($lastPage === null || $lastPage === '') {
                echo htmlspecialchars('/home');
            } else {
                echo htmlspecialchars($lastPage);
            }
        ?>"></wchat-data>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script src="/assets/js/create_server.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="/assets/js/notifiers.js"></script>
    <script src="/assets/js/globalFunctions.js"></script>
    <script src="/assets/js/profile.js" type="module"></script>
    <script src="/assets/js/load_scripts.js"></script>
</body>
</html>
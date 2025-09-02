<?php
include 'app/config.php';
include 'global.php';

$access_token = $_COOKIE['access_token'];
header("Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!$access_token) {
    header('Location: /login');
    exit;
}

$stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ?");
$stmt->bind_param("s", $access_token);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
} else {
    header('Location: /login');
    exit;
}
$stmt->close();

$userStmt = $mysqli->prepare("SELECT username, premium, premium_expires_at, premium_know, profile_picture, status, is_developer FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
if ($userResult->num_rows > 0) {
    $row = $userResult->fetch_assoc();
    $username = $row['username'];
    $premium = $row['premium'];
    $premium_expires_at = $row['premium_expires_at'];
    $premium_know = $row['premium_know'];
    $profile_picture = $row['profile_picture'];
    $status = $row['status'];
    $is_developer = $row['is_developer'];
} else {
    $username = "Unknown";
    $premium = false;
    $premium_expires_at = null;
    $premium_know = false;
    $profile_picture = "/uploads/profile-pictures/default-profile.png";
    $status = "online";
    $is_developer = false;
}
$userStmt->close();

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
$from = $_GET['from'] ?? '/home';

$active_tab = 'account';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

if (isset($segments[0]) && $segments[0] === 'settings' && !empty($segments[1])) {
    $active_tab = $segments[1]; 
}

$chat_connected = "Not Connected";
$onclickEventChat = "window.location.href = '/connections/chat'";

$connectionsStmt = $mysqli->prepare("SELECT connection_user_id, connection_user_name, connection_user_url, connected_at, connection_user_image, connection_name FROM user_connections WHERE user_id = ?");
$connectionsStmt->bind_param("i", $user_id);
$connectionsStmt->execute();
$connectionsResult = $connectionsStmt->get_result();
if ($connectionsResult->num_rows > 0) {
    $connections = $connectionsResult->fetch_all(MYSQLI_ASSOC);
    foreach ($connections as $index => $connection) {
        if ($connection['connection_name'] === "Chat") {
            $chat_connected = "Connected";
            $onclickEventChat = "openConnectionModal('Chat')";
        }
    }
} else {
    $connections = [];
}
$connectionsStmt->close();

$kudosStmt = $mysqli->prepare("SELECT SUM(kudo_amount) AS total_kudos FROM Kudos WHERE user_id = ?");
$kudosStmt->bind_param("i", $user_id);
$kudosStmt->execute();
$kudosResult = $kudosStmt->get_result();
$row = $kudosResult->fetch_assoc();
$totalKudos = $row['total_kudos'] ?? 0;
$kudosStmt->close();

$kudosJson = file_get_contents('app/assets/kudos/items.json');
$kudosArray = json_decode($kudosJson, true);
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>wokki chat | settings</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
      rel="stylesheet"
    />
      <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/dark.min.css" />
    <link rel="stylesheet" href="/assets/styles/main.css" />
    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
    <script src="/assets/js/call_reconnect.js"></script>

</head>
<body>
    <div class="settings-content">
        <div class="settings-tabs">
            <p class="settings-tab-title">User Settings</p>
            <div class="settings-tab <?php echo ($active_tab === "account") ? "active" : ""; ?>" onclick="window.location.href = '/settings/account?from=' + returnUrl ">
                <span class="material-symbols-rounded">account_circle</span>
                <p>My Account</p>
            </div>
            <div class="settings-tab <?php echo ($active_tab === "appearance") ? "active" : ""; ?>" onclick="window.location.href = '/settings/appearance?from=' + returnUrl ">
                <span class="material-symbols-rounded">format_paint</span>
                <p>Appearance</p>
            </div>
            <div class="settings-tab <?php echo ($active_tab === "connections") ? "active" : ""; ?>" onclick="window.location.href = '/settings/connections?from=' + returnUrl ">
                <span class="material-symbols-rounded">link</span>
                <p>Connections</p>
            </div>
            <div class="settings-tab <?php echo ($active_tab === "kudos") ? "active" : ""; ?>" onclick="window.location.href = '/settings/kudos?from=' + returnUrl ">
                <span class="material-symbols-rounded">poker_chip</span>
                <p>Kudos</p>
            </div>
            <?php if ($is_developer): ?>
            <div class="settings-tab <?php echo ($active_tab === "logs") ? "active" : ""; ?>" onclick="window.location.href = '/settings/logs?from=' + returnUrl ">
                <span class="material-symbols-rounded">contract</span>
                <p>Logs</p>
            </div>
            <?php endif; ?>
        </div>
        <div class="setting-page">
            <div class="close-settings-container" onclick="window.location.href = 'https://chat.wokki20.nl' + returnUrl">
                <div class="close-settings">
                    <span class="material-symbols-rounded">close</span>
                </div>
                <p>Esc</p>
            </div>
            <?php
            if ($active_tab === "account") {
                $settingsHtml = file_get_contents('settings_html/settings_account.html');
                $settingsHtml = str_replace("{{profile_picture_url}}", $profile_picture, $settingsHtml);
                $settingsHtml = str_replace("{{username}}", $username, $settingsHtml);
                $settingsHtml = str_replace("{{status}}", $status, $settingsHtml);
                $settingsHtml = str_replace("{{premium_badge}}", ($premium_active ? '<div class="premium-tag"><img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/tag_premium.svg">PREMIUM</div>' : ''), $settingsHtml);
                echo $settingsHtml;
            }
            ?>
            <?php 
            if ($active_tab === "appearance") {
                $appearanceHtml = file_get_contents('settings_html/settings_appearance.html');
                $appearanceHtml = str_replace("{{light_active}}", ($theme === "light" ? "active" : ""), $appearanceHtml);
                $appearanceHtml = str_replace("{{dark_active}}", ($theme === "dark" ? "active" : ""), $appearanceHtml);
                $appearanceHtml = str_replace("{{night_active}}", ($theme === "night" ? "active" : ""), $appearanceHtml);
                $appearanceHtml = str_replace("{{hidden_1}}", ($chat_connected === "Connected" ? "chat_light_blue" : "hidden_1_disabled"), $appearanceHtml);
                $appearanceHtml = str_replace("{{hidden_1_active}}", ($theme === "chat_light_blue" ? "active" : ""), $appearanceHtml);
                $appearanceHtml = str_replace("{{hidden_2}}", ($chat_connected === "Connected" ? "chat_dark_blue" : "hidden_2_disabled"), $appearanceHtml);
                $appearanceHtml = str_replace("{{hidden_2_active}}", ($theme === "chat_dark_blue" ? "active" : ""), $appearanceHtml);
                $appearanceHtml = str_replace("{{hidden_container_1}}", ($chat_connected === "Connected" ? "" : "chat-account-only"), $appearanceHtml);
                $appearanceHtml = str_replace("{{connect_chat_account_text}}", ($chat_connected === "Connected" ? "Go even further with personalizing, and use one of the familiar Chat themes. Only available for users who have their Chat account connected." : "Please <a href='/settings/connections' class='link'>connect your Chat account</a> to unlock Chat themes!"), $appearanceHtml);
                echo $appearanceHtml;   
            }
            ?>
            <?php 
            if ($active_tab === "connections") {
                $connectionsHtml = file_get_contents('settings_html/settings_connections.html');
                $connectionsHtml = str_replace("{{connection_status.chat}}", $chat_connected, $connectionsHtml);
                $connectionsHtml = str_replace("{{onclick_event.chat}}", $onclickEventChat, $connectionsHtml);
                echo $connectionsHtml;
            }
            ?>
            <?php 
            if ($active_tab === "kudos") {
                $connectionsHtml = file_get_contents('settings_html/settings_kudos.html');
                $connectionsHtml = str_replace("{{kudos_amount}}", $totalKudos, $connectionsHtml);
                $kudosHtml = '';
                if (is_array($kudosArray)) {
                    foreach ($kudosArray as $index => $kudo) {
                        $kudosHtml .= '
                        <div class="kudos-item">
                            <div class="kudos-item-image">
                                <img draggable="false" src="' . $kudo['image'] . '" alt="' . $kudo['name'] . '" />
                                ' . ($kudo['extra_message'] !== "" ? '<p class="kudos-item-extra-message">' . $kudo['extra_message'] . '</p>' : '') . '
                            </div>
                            <div class="kudos-item-name">
                                ' . $kudo['name'] . '
                            </div>
                            <div class="kudos-item-amount">
                                <span class="material-symbols-rounded">poker_chip</span><p class="kudos-item-amount-value">' . number_format($kudo['price'], 0, '.', ',') . '</p>
                            </div>
                            <button class="kudos-item-view-details-button button-primary-filled">View Details</button>
                        </div>
                        ';
                    }
                }
                $connectionsHtml = str_replace("{{kudos_items}}", $kudosHtml, $connectionsHtml);
                echo $connectionsHtml;
            }
            ?>
            <?php 
            if ($active_tab === "logs") {
                if ($is_developer === false) {
                    header('Location: /');
                }
                $logsHtml = file_get_contents('settings_html/settings_logs.html');
                echo $logsHtml;
            }
            ?>
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
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="/assets/js/settings.js"></script>
    <script src="/assets/js/notifiers.js"></script>
    <script src="/assets/js/globalFunctions.js"></script>
    <script>
        // DO NOT TOUCH OR EDIT
        const access_token = "<?php echo $access_token; ?>";

        const user_id = "<?php echo $user_id; ?>";

        const premium = <?php echo json_encode($premium_active); ?>;

        const returnUrl = "<?php echo htmlspecialchars($from, ENT_QUOTES); ?>";

        const active_tab = "<?php echo $active_tab; ?>";

        const connections = <?php echo json_encode($connections); ?>;

        const profile_picture = "<?php echo $profile_picture; ?>";
        const socket = io("https://chat.wokki20.nl", {
            path: "/socket.io",
            transports: ["websocket"],
            query: {
            access_token: access_token
            },
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
            window.location.replace('https://chat.wokki20.nl' + returnUrl);
            event.preventDefault();
            }
        });

        socket.on("user_updated", (user) => {
            if (user.id !== user_id) return;
            document.querySelector(".profile-status-circle-inner").style.backgroundColor = `var(--clr-status-${user.status.toLowerCase()})`;
            document.querySelector(".profile-status").textContent = user.status.charAt(0).toUpperCase() + user.status.slice(1);
        });
    </script>
</body>
</html>
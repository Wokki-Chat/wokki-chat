<?php
session_start();
include 'app/config.php';
include 'global.php';
include 'app/maintenance.php';

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

$userStmt = $mysqli->prepare("SELECT username, premium, premium_expires_at, premium_know, profile_picture, status, is_developer, bio, profile_color_primary, profile_color_accent, created_at, nickname, profile_banner FROM users WHERE id = ?");
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
    $bio = $row['bio'];
    $profile_color_primary = $row['profile_color_primary'];
    $profile_color_accent = $row['profile_color_accent'];
    $created_at = $row['created_at'];
    $display_name = $row['nickname'];
    $banner = $row['profile_banner'];
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

$widgetsStmt = $mysqli->prepare("SELECT widget_name, show_on_profile FROM profile_widgets WHERE user_id = ?");
$widgetsStmt->bind_param("i", $user_id);
$widgetsStmt->execute();
$widgetsResult = $widgetsStmt->get_result();
$widgets = $widgetsResult->num_rows > 0 ? $widgetsResult->fetch_all(MYSQLI_ASSOC) : [];
$widgetsStmt->close();

$chat_connected = "Not Connected";
$onclickEventChat = "window.location.href = '/connections/chat'";

$spotify_connected = "Not Connected";
$onclickEventSpotify = "window.location.href = '/connections/spotify'";

$connectionsStmt = $mysqli->prepare("SELECT connection_user_id, connection_user_name, connection_user_url, connected_at, connection_user_image, connection_name FROM user_connections WHERE user_id = ?");
$connectionsStmt->bind_param("i", $user_id);
$connectionsStmt->execute();
$connectionsResult = $connectionsStmt->get_result();
$connections = $connectionsResult->num_rows > 0 ? $connectionsResult->fetch_all(MYSQLI_ASSOC) : [];

foreach ($connections as $index => &$connection) {
    if ($connection['connection_name'] === "Chat") {
        $chat_connected = "Connected";
        $onclickEventChat = "openConnectionModal('Chat')";
    }
    if ($connection['connection_name'] === "Spotify") {
        $spotify_connected = "Connected";
        $onclickEventSpotify = "openConnectionModal('Spotify')";
        
        $connection['show_on_profile'] = false;
        foreach ($widgets as $widget) {
            if ($widget['widget_name'] === 'Spotify') {
                $connection['show_on_profile'] = $widget['show_on_profile'] == 1;
                break;
            }
        }
    }
}
unset($connection);

$connectionsStmt->close();

$serverStmt = $mysqli->prepare("
    SELECT s.*
    FROM servers s
    INNER JOIN server_members sm ON sm.server_id = s.id
    WHERE sm.user_id = ?
    ORDER BY
        CASE WHEN sm.position IS NULL THEN 1 ELSE 0 END,
        sm.position DESC,
        sm.joined_at DESC,
        sm.id ASC
");
$serverStmt->bind_param("i", $user_id);
$serverStmt->execute();
$serverResult = $serverStmt->get_result();
$serverStmt->close();

$kudosStmt = $mysqli->prepare("SELECT SUM(kudo_amount) AS total_kudos FROM Kudos WHERE user_id = ?");
$kudosStmt->bind_param("i", $user_id);
$kudosStmt->execute();
$kudosResult = $kudosStmt->get_result();
$row = $kudosResult->fetch_assoc();
$totalKudos = $row['total_kudos'] ?? 0;
$kudosStmt->close();

$kudosJson = file_get_contents('app/assets/kudos/items.json');
$kudosArray = json_decode($kudosJson, true);

$bannerUrl = $banner ? $banner : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAsAAAAGMAQMAAADuk4YmAAAAA1BMVEX///+nxBvIAAAAAXRSTlMAQObYZgAAADlJREFUeF7twDEBAAAAwiD7p7bGDlgYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAwAGJrAABgPqdWQAAAABJRU5ErkJggg==';

$_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Wokki Chat - Settings</title>
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
    <meta name="app_page_name" content="Settings">
    <meta name="app_page_icon" content="settings">
    <link rel="stylesheet" href="https://cdn.wokki20.nl/dynamic/jspt/jspt.css">
    <script src="https://cdn.wokki20.nl/dynamic/jspt/jspt.js"></script>
</head>
<body>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.css"/>
    <script src="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.js" defer></script>
    <div class="server-bar">
        <div class="server-bar-dms">
            <a class="server-bar-item" id="server-bar-item-home" href="/home">
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
                        <img src="'.$lowImage.'" loading="lazy" decoding="async" width="47" height="47" draggable="false" />
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
        <?php echo $maintenanceHtml; ?>
        <div class="settings-content">
            <div class="settings-tabs">
                <p class="settings-tab-title">User Settings</p>
                <a class="settings-tab <?php echo ($active_tab === "account") ? "active" : ""; ?> no-underline" href="/settings/account?from=<?php echo $from; ?>">
                    <span class="material-symbols-rounded">account_circle</span>
                    <p>My Account</p>
                </a>
                <a class="settings-tab <?php echo ($active_tab === "appearance") ? "active" : ""; ?> no-underline" href="/settings/appearance?from=<?php echo $from; ?>">
                    <span class="material-symbols-rounded">format_paint</span>
                    <p>Appearance</p>
                </a>
                <a class="settings-tab <?php echo ($active_tab === "connections") ? "active" : ""; ?> no-underline" href="/settings/connections?from=<?php echo $from; ?>">
                    <span class="material-symbols-rounded">link</span>
                    <p>Connections</p>
                </a>
                <a class="settings-tab <?php echo ($active_tab === "kudos") ? "active" : ""; ?> no-underline" href="/settings/kudos?from=<?php echo $from; ?>">
                    <span class="material-symbols-rounded">poker_chip</span>
                    <p>Kudos</p>
                </a>
                <?php if ($is_developer): ?>
                <a class="settings-tab <?php echo ($active_tab === "logs") ? "active" : ""; ?> no-underline" href="/settings/logs?from=<?php echo $from; ?>">
                    <span class="material-symbols-rounded">contract</span>
                    <p>Logs</p>
                </a>
                <?php endif; ?>
            </div>
            <div class="setting-page">
                <div class="settings-sidebar-button">
                    <span class="material-symbols-rounded top-bar-menu" id="settings-sidebar-button">menu</span>
                </div>
                <a class="close-settings-container no-underline" href="https://chat.wokki20.nl<?php echo $from; ?>">
                    <div class="close-settings">
                        <span class="material-symbols-rounded">close</span>
                    </div>
                    <p>Esc</p>
                </a>
                <?php
                if ($active_tab === "account") {
                    $profileStyle = '';

                    if (!empty($profile_color_primary) && !empty($profile_color_accent)) {
                        $color = $profile_color_primary;
                        $r = hexdec(substr($color, 1, 2));
                        $g = hexdec(substr($color, 3, 2));
                        $b = hexdec(substr($color, 5, 2));

                        $r = max(0, $r - $r * 0.1);
                        $g = max(0, $g - $g * 0.1);
                        $b = max(0, $b - $b * 0.1);

                        $darker = sprintf("#%02x%02x%02x", round($r), round($g), round($b));

                        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
                        $lightText = $brightness <= 150 ? 'true' : 'false';

                        $profileStyle = sprintf(
                            'style="background-color: %s; border: 4px solid %s;" data-light-text="%s" data-custom-style="true"',
                            $darker,
                            $profile_color_accent,
                            $lightText
                        );
                    }
                    $containerStyle = (!empty($profile_color_primary) && !empty($profile_color_accent))
                    ? 'background-color: rgba(255, 255, 255, 0.1); border: none;'
                    : '';
                    $createdAtFormatted = '';
                    if (!empty($created_at)) {
                        $date = new DateTime($created_at);
                        $createdAtFormatted = $date->format('M j, Y');
                    }
                    $profileLinkStyle = (!empty($profile_color_primary) && !empty($profile_color_accent))
                    ? sprintf(
                        'data-custom-style="true" style="color: rgb(%d, %d, %d); background-color: rgba(%d, %d, %d, 0.1); border: 1px solid rgba(%d, %d, %d, 0.3);"', 
                        $lightText ? 255 : 0,
                        $lightText ? 255 : 0,
                        $lightText ? 255 : 0,
                        $lightText ? 255 : 0,
                        $lightText ? 255 : 0,
                        $lightText ? 255 : 0,
                        $lightText ? 255 : 0,
                        $lightText ? 255 : 0,
                        $lightText ? 255 : 0
                    )
                    : '';

                    $bannerHtml = '<img draggable="false" class="user-info-profile-popup-banner" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAsAAAAGMAQMAAADuk4YmAAAAA1BMVEX///+nxBvIAAAAAXRSTlMAQObYZgAAADlJREFUeF7twDEBAAAAwiD7p7bGDlgYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAwAGJrAABgPqdWQAAAABJRU5ErkJggg==">';

                    if (!empty($banner)) {
                        $bannerHtml = '<img draggable="false" class="user-info-profile-popup-banner" src="' . $banner . '">';
                    }

                    $settingsHtml = file_get_contents('settings_html/settings_account.html');
                    $settingsHtml = str_replace("{{profile_picture_url}}", $profile_picture, $settingsHtml);
                    $settingsHtml = str_replace("{{username}}", htmlspecialchars($username), $settingsHtml);
                    $settingsHtml = str_replace("{{status}}", htmlspecialchars($status), $settingsHtml);
                    $settingsHtml = str_replace("{{profile_style}}", $profileStyle, $settingsHtml);
                    $settingsHtml = str_replace("{{container_style}}", $containerStyle, $settingsHtml);
                    $settingsHtml = str_replace("{{bio_profile}}", $bio ? htmlspecialchars($bio) : 'You have no bio yet.', $settingsHtml);
                    $settingsHtml = str_replace("{{bio}}", $bio ? htmlspecialchars($bio) : '', $settingsHtml);
                    $settingsHtml = str_replace("{{created_at}}", $createdAtFormatted, $settingsHtml);
                    $settingsHtml = str_replace("{{profile_link_style}}", $profileLinkStyle, $settingsHtml);
                    $settingsHtml = str_replace("{{tags_style}}", ($premium_active ? '' : 'display: none;'), $settingsHtml);
                    $settingsHtml = str_replace("{{display_name}}", htmlspecialchars($display_name), $settingsHtml);
                    $settingsHtml = str_replace("{{profile_color_primary}}", $profile_color_primary, $settingsHtml);
                    $settingsHtml = str_replace("{{profile_color_accent}}", $profile_color_accent, $settingsHtml);
                    $settingsHtml = str_replace("{{banner}}", $bannerHtml, $settingsHtml);
                    $settingsHtml = str_replace("{{banner_url}}", $bannerUrl, $settingsHtml);
                    $settingsHtml = str_replace("{{premium_badge}}", ($premium_active ? '<div class="dm-info-tag"><img draggable="false" class="dm-info-tag-icon" src="/assets/icons/tags/tag_premium.svg"><p class="dm-info-tag-tooltip">Premium</p></div>' : ''), $settingsHtml);
                    echo $settingsHtml;
                }
                ?>
                <?php 
                if ($active_tab === "appearance") {
                    $appearanceHtml = file_get_contents('settings_html/settings_appearance.html');
                    $layout = explode(' ', $theme)[1];
                    $theme = explode(' ', $theme)[0];
                    $appearanceHtml = str_replace("{{light_active}}", ($theme === "light" ? "active" : ""), $appearanceHtml);
                    $appearanceHtml = str_replace("{{dark_active}}", ($theme === "dark" ? "active" : ""), $appearanceHtml);
                    $appearanceHtml = str_replace("{{night_active}}", ($theme === "night" ? "active" : ""), $appearanceHtml);
                    $appearanceHtml = str_replace("{{hidden_1}}", ($chat_connected === "Connected" ? "chat_light_blue" : "hidden_1_disabled"), $appearanceHtml);
                    $appearanceHtml = str_replace("{{hidden_1_active}}", ($theme === "chat_light_blue" ? "active" : ""), $appearanceHtml);
                    $appearanceHtml = str_replace("{{hidden_2}}", ($chat_connected === "Connected" ? "chat_dark_blue" : "hidden_2_disabled"), $appearanceHtml);
                    $appearanceHtml = str_replace("{{hidden_2_active}}", ($theme === "chat_dark_blue" ? "active" : ""), $appearanceHtml);
                    $appearanceHtml = str_replace("{{hidden_container_1}}", ($chat_connected === "Connected" ? "" : "chat-account-only"), $appearanceHtml);
                    $appearanceHtml = str_replace("{{floaty_active_checked}}", ($layout === "floaty" ? "checked" : ""), $appearanceHtml);
                    $appearanceHtml = str_replace("{{compact_active_checked}}", ($layout === "compact" ? "checked" : ""), $appearanceHtml);
                    $appearanceHtml = str_replace("{{connect_chat_account_text}}", ($chat_connected === "Connected" ? "Go even further with personalizing, and use one of the familiar Chat themes. Only available for users who have their Chat account connected." : "Please <a href='/settings/connections' class='link'>connect your Chat account</a> to unlock Chat themes!"), $appearanceHtml);
                    echo $appearanceHtml;   
                }
                ?>
                <?php 
                if ($active_tab === "connections") {
                    $connectionsHtml = file_get_contents('settings_html/settings_connections.html');
                    $connectionsHtml = str_replace("{{connection_status.chat}}", $chat_connected, $connectionsHtml);
                    $connectionsHtml = str_replace("{{onclick_event.chat}}", $onclickEventChat, $connectionsHtml);
                    $connectionsHtml = str_replace("{{connection_status.spotify}}", $spotify_connected, $connectionsHtml);
                    $connectionsHtml = str_replace("{{onclick_event.spotify}}", $onclickEventSpotify, $connectionsHtml);
                    echo $connectionsHtml;
                }
                ?>
                <?php 
                if ($active_tab === "kudos") {
                    $connectionsHtml = file_get_contents('settings_html/settings_kudos.html');
                    $connectionsHtml = str_replace("{{kudos_amount}}", '<div class="kudos-amount"><span class="material-symbols-rounded">poker_chip</span><p class="kudos-amount-value">' . number_format($totalKudos, 0, '.', ','). '</p></div>', $connectionsHtml);
                    $kudosHtml = '';
                    if (is_array($kudosArray)) {
                        foreach ($kudosArray as $index => $kudo) {
                            $kudosHtml .= '
                            <div class="kudos-item" data-id="' . $kudo['id'] . '" id="kudos-item">
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

        <wchat-allowed-scripts value="settings.js;"></wchat-allowed-scripts>
        <wchat-data id="access-token" value="<?php echo htmlspecialchars($access_token); ?>"></wchat-data>
        <wchat-data id="user-id" value="<?php echo htmlspecialchars($user_id); ?>"></wchat-data>
        <wchat-data id="premium" value="<?php echo htmlspecialchars(json_encode($premium_active)); ?>"></wchat-data>
        <wchat-data id="return-url" value="<?php echo htmlspecialchars($from, ENT_QUOTES); ?>"></wchat-data>
        <wchat-data id="active-tab" value="<?php echo htmlspecialchars($active_tab); ?>"></wchat-data>
        <wchat-data id="connections" value="<?php echo htmlspecialchars(json_encode($connections)); ?>"></wchat-data>
        <wchat-data id="kudo-items" value="<?php echo htmlspecialchars(json_encode($kudosArray)); ?>"></wchat-data>
        <wchat-data id="kudos" value="<?php echo htmlspecialchars(json_encode($totalKudos)); ?>"></wchat-data>
        <wchat-data id="profile-picture" value="<?php echo htmlspecialchars($profile_picture); ?>"></wchat-data>
        <wchat-data id="banner-picture" value="<?php echo htmlspecialchars($bannerUrl); ?>"></wchat-data>
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
    <script src="/assets/js/notifiers.js" data-swup-ignore-script></script>
    <script src="/assets/js/globalFunctions.js" data-swup-ignore-script></script>
    <script src="/assets/js/settings.js" type="module"></script>
    <script src="/assets/js/load_scripts.js"></script>
</body>
</html>

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

$userStmt = $mysqli->prepare("SELECT username, premium, premium_expires_at, premium_know, profile_picture, status FROM users WHERE id = ?");
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
} else {
    $username = "Unknown";
    $premium = false;
    $premium_expires_at = null;
    $premium_know = false;
    $profile_picture = "/uploads/profile-pictures/default-profile.png";
    $status = "online";
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
    
    $now = time();
    $diff = $timestamp - $now;
    
    if ($diff <= 0) {
        return "0 days"; // Already expired or due now
    }
    
    $days = ceil($diff / 86400); // 86400 seconds in a day
    
    return $days . " days";
}
$from = $_GET['from'] ?? '/home';

$active_tab = 'account';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

if (isset($segments[0]) && $segments[0] === 'settings' && !empty($segments[1])) {
    $active_tab = $segments[1]; 
}


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
                $settingsHtml = str_replace("{{premium_badge}}", ($premium_active ? '<div class="premium-tag"><span class="material-symbols-rounded">star</span>PREMIUM</div>' : ''), $settingsHtml);
                echo $settingsHtml;
            }
            ?>
            <?php 
            if ($active_tab === "appearance") {
                $appearanceHtml = file_get_contents('settings_html/settings_appearance.html');
                $appearanceHtml = str_replace("{{light_active}}", ($theme === "light" ? "active" : ""), $appearanceHtml);
                $appearanceHtml = str_replace("{{dark_active}}", ($theme === "dark" ? "active" : ""), $appearanceHtml);
                $appearanceHtml = str_replace("{{night_active}}", ($theme === "night" ? "active" : ""), $appearanceHtml);
                echo $appearanceHtml;   
            }
            ?>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="/assets/js/settings.js"></script>
    <script src="/assets/js/notifiers.js"></script>
    <script>
        // DO NOT TOUCH OR EDIT
        const access_token = "<?php echo $access_token; ?>";

        const user_id = "<?php echo $user_id; ?>";

        const premium = <?php echo json_encode($premium_active); ?>;

        const returnUrl = "<?php echo htmlspecialchars($from, ENT_QUOTES); ?>";

        const active_tab = "<?php echo $active_tab; ?>";

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
            window.location.href = 'https://chat.wokki20.nl' + returnUrl;
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
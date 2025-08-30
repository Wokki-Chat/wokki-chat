<?php
include 'app/config.php';

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
    $profile_user_name = substr($segments[1], 1);
}

if (!$profile_user_name) {
    header('Location: /home');
    exit;
}

$stmt = $mysqli->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $my_username = $row['username'];
    $my_profile_picture = $row['profile_picture'];
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


$profileStmt = $mysqli->prepare("
    SELECT 
        u.id,
        u.username,
        u.profile_picture,
        u.status,
        u.premium,
        u.created_at AS u_created_at,
        u.bio,
        u.is_developer,
        u.is_staff,
        t.tag_name,
        t.tag_icon,
        t.tag_description,
        t.created_at
    FROM users u
    LEFT JOIN tags t ON u.id = t.user_id
    WHERE u.username = ? 
      AND u.email_verified = 1
");
$profileStmt->bind_param("s", $profile_user_name);
$profileStmt->execute();
$profileResult = $profileStmt->get_result();
$profileStmt->close();

if ($profileResult->num_rows === 0) {
    header('Location: /home');
    exit;
}

$profile_tags = [];
$profileRow = null;

while ($row = $profileResult->fetch_assoc()) {
    if ($profileRow === null) {
        $profileRow = [
            'id' => $row['id'],
            'username' => $row['username'],
            'profile_picture' => $row['profile_picture'],
            'status' => $row['status'],
            'premium' => $row['premium'],
            'p_created_at' => $row['u_created_at'],
            'bio' => $row['bio'],
            'is_developer' => $row['is_developer'],
            'is_staff' => $row['is_staff'],
            'tags' => []
        ];
    }

    if (!empty($row['tag_name'])) {
        $profileRow['tags'][] = [
            'tag_name' => $row['tag_name'],
            'tag_icon' => $row['tag_icon'],
            'tag_description' => $row['tag_description'],
            'created_at' => $row['created_at']
        ];
    }
}

$profile = $profileRow;

?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>wokki chat</title>
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
                    echo '<a class="server-bar-item" href="/server/'.$serverRow['id'].'">
                        <img src="' . $serverRow['image'] . '">
                        <p class="tooltip">' . $serverRow['name'] . '</p>
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

    <div class="top-bar" style="left: calc(15px + 67px + 15px + 15px); width: calc(100% - 15px - 67px - 15px - 15px - 45px);">
        <div class="top-bar-left">
            
        </div>
    </div>

    <div class="main-content" style="left: calc(15px + 67px + 15px + 15px); width: calc(100% - 15px - 67px - 15px - 15px - 356px - 30px); z-index: -1;">
        <div class="profile-item">
            <div class="profile-item-left">
                <div class="profile-item-status-profile">
                    <img src="<?php echo $profile['profile_picture']; ?>" draggable="false" class="profile-picture-large">
                    <div class="profile-item-status-circle-outer">
                        <div class="profile-item-status-circle-inner" style="background-color: var(--clr-status-<?php echo strtolower($profile['status']); ?>);"></div>
                    </div>
                </div>
            </div>
            <div class="profile-item-right">
                <p class="profile-item-username"><?php echo htmlspecialchars($profile['username']); ?></p>
                <p class="profile-item-status"><?php echo ucfirst($profile['status']); ?></p>
                <div class="profile-item-info">
                    <div class="bio">
                        <p class="profile-item-info-key">Bio:</p>
                        <p class="profile-item-info-bio <?php if (empty($profile['bio'])) { echo 'no-bio'; } ?>"><?php echo htmlspecialchars($profile['bio'] ?? 'This user has no bio yet'); ?></p>
                    </div>
                    <div class="created-at">
                        <p class="profile-item-info-key">Joined on:</p>
                        <p class="profile-item-info-date"><?php echo (new DateTime($profile['p_created_at']))->format('M j, Y'); ?></p>
                    </div>
                    <div class="profile-item-info-tags" <?php if (!$profile['premium'] == 1 && empty($profile['tags']) && !$profile['is_staff'] == 1 && !$profile['is_developer'] == 1) { echo 'style="display: none;"'; } ?>>
                        <p class="profile-item-info-key">Tags:</p>
                        <?php if ($profile['is_staff'] == 1) { ?>
                            <div class="profile-item-info-tag">
                                <img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/tag_staff.svg">
                                <div class="profile-item-info-tag-info">
                                    <p class="profile-item-info-tag-info-name">Staff</p>
                                    <p class="profile-item-info-tag-info-description">This user is an official Wokki Chat staff member.</p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if ($profile['is_developer'] == 1) { ?>
                            <div class="profile-item-info-tag">
                                <img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/tag_developer.svg">
                                <div class="profile-item-info-tag-info">
                                    <p class="profile-item-info-tag-info-name">Developer</p>
                                    <p class="profile-item-info-tag-info-description">This user is an official Wokki Chat developer.</p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if ($profile['premium'] == 1) { ?>
                            <div class="profile-item-info-tag">
                                <img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/tag_premium.svg">
                                <div class="profile-item-info-tag-info">
                                    <p class="profile-item-info-tag-info-name">Premium</p>
                                    <p class="profile-item-info-tag-info-description"><?php echo htmlspecialchars($profile['username']); ?> is a premium user</p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if ($profile['tags']) {
                            foreach ($profile['tags'] as $tag) {
                                if ($tag['tag_name'] == 'staff') { continue; }
                                echo '
                                <div class="profile-item-info-tag">
                                    <img draggable="false" class="profile-item-info-tag-icon" src="/assets/icons/tags/'.$tag['tag_icon'].'.svg">
                                    <div class="profile-item-info-tag-info">
                                        <p class="profile-item-info-tag-info-name">'.ucfirst($tag['tag_name']).'</p>
                                        <p class="profile-item-info-tag-info-description">'.htmlspecialchars($tag['tag_description']).'</p>
                                    </div>
                                </div>
                                ';
                            }   
                        } ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="self-info">
        <div class="self-info-left">
            <div class="self-info-profile-status">
                <img class="self-info-profile-picture" src="<?php echo $my_profile_picture; ?>">
                <div class="self-info-status-circle-outer">
                    <div class="self-info-status-circle-inner"></div>
                </div>
            </div>
            <div class="self-info-status-username">
                <p class="self-info-username"><?php echo $my_username; ?></p>
                <p class="self-info-status">Online</p>
            </div>
        </div>

        <div class="self-info-right">
            <span class="material-symbols-rounded self-info-right-settings" onclick="window.location.href = '/settings?from=/profile/@<?php echo $profile['username']; ?>'">settings</span>
        </div>
    </div>
    <script src="/assets/js/create_server.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="/assets/js/notifiers.js"></script>
    <script>
        // DO NOT TOUCH OR EDIT
        const access_token = "<?php echo $access_token; ?>";
        const user_id = "<?php echo $user_id; ?>";

        const profile_user_id = "<?php echo $profile['id']; ?>";
        
        const socket = io("https://chat.wokki20.nl", {
            path: "/socket.io",
            transports: ["websocket"],
            query: {
                access_token: access_token
            },
        });

        socket.on("user_updated", (user) => {
            if (user.id !== profile_user_id) return;
            document.querySelector(".profile-item-status-circle-inner").style.backgroundColor = `var(--clr-status-${user.status.toLowerCase()})`;
            document.querySelector(".profile-item-status").textContent = user.status.charAt(0).toUpperCase() + user.status.slice(1);
        });
    </script>
</body>
</html>
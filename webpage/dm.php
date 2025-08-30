<?php
include 'app/config.php';
include 'global.php';
$access_token = $_COOKIE['access_token'];

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
}
$stmt->close();

if (!$user_id) {
    header('Location: /login');
    exit;
}

$userStmt = $mysqli->prepare("SELECT username, premium, premium_expires_at, premium_know, profile_picture, status, bio FROM users WHERE id = ?");
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
    $bio = $row['bio'];
} else {
    $username = "Unknown";
    $premium = false;
    $premium_expires_at = null;
    $premium_know = false;
    $profile_picture = "/uploads/profile-pictures/default-profile.png";
    $status = null;
    $bio = null;
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

    $userStmt = $mysqli->prepare("SELECT username, profile_picture, status, premium, premium_expires_at, bio FROM users WHERE id = ?");
    $userStmt->bind_param("i", $friendId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();

    if ($userData = $userResult->fetch_assoc()) {
        $friendsList[] = [
            'id' => $friendId,
            'username' => $userData['username'],
            'profile_picture' => $userData['profile_picture'],
            'status' => $userData['status'], 
            'premium' => $userData['premium'] && ($userData['premium_expires_at'] > time() || $userData['premium_expires_at'] === null),
            'bio' => $userData['bio']
        ];
    }

    $userStmt->close();
}

$friendsList[] = [
    'id' => $user_id,
    'username' => $username,
    'profile_picture' => $profile_picture,
    'status' => $status, 
    'premium' => $premium_active,
    'bio' => $bio
];

$friendsStmt->close();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

$dm_name = null;
if (isset($segments[0]) && $segments[0] === 'dm' && !empty($segments[1])) {
    $dm_name = ltrim(urldecode($segments[1]), '@');
}

if (!$dm_name) {
    header('Location: /home');
    exit;
}

$dmStmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
$dmStmt->bind_param("s", $dm_name);
$dmStmt->execute();
$dmResult = $dmStmt->get_result();

if ($dmResult->num_rows > 0) {
    $dmRow = $dmResult->fetch_assoc();
    $dm_id = $dmRow['id'];
} else {
    header('Location: /home');
    exit;
}

$dmStmt->close();

if ($dm_id === $user_id) {
    header('Location: /home');
    exit;
}

if (!in_array($dm_id, array_column($friendsList, 'id'))) {
    header('Location: /home');
    exit;
}
$dm_info_stmt = $mysqli->prepare("SELECT u.username, u.profile_picture, u.status, u.premium, u.premium_expires_at, u.bio, u.created_at, t.tag_name, t.tag_icon
    FROM users u
    LEFT JOIN tags t ON t.user_id = u.id
    WHERE u.id = ?");
$dm_info_stmt->bind_param("i", $dm_id);
$dm_info_stmt->execute();
$dm_info_result = $dm_info_stmt->get_result();

$dm_info = $dm_info_result->fetch_assoc();
$dm_tags = [];

if ($dm_info) {
    $dm_info_result->data_seek(0);
    while ($row = $dm_info_result->fetch_assoc()) {
        if ($row['tag_name']) {
            $dm_tags[] = [
                'tag_name' => $row['tag_name'],
                'tag_icon' => $row['tag_icon']
            ];
        }
    }
}

$dm_info_result->free();
$dm_info_stmt->close();


setcookie(
    'dm_active_user',
    $dm_name,
    time() + 60 * 60 * 24 * 30,
    '/',
    '',
    true,
    true
);

?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>wokki chat</title>
    <link rel="stylesheet" href="/assets/styles/main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/dark.min.css" />
    <script src="/assets/js/call_reconnect.js"></script>
</head>
<body>
    
    <div class="server-bar">
        <div class="server-bar-dms">
            <div class="server-bar-item active">
                <img draggable="false" src="/assets/images/monochrome-logo-purple-background.png">
            </div>
        </div>
        <div class="divider"></div>
        <div class="server-bar-channels">
            <?php
            if ($serverResult->num_rows > 0) {
                while ($serverRow = $serverResult->fetch_assoc()) {
                    echo '<a class="server-bar-item" href="/server/'.$serverRow['id'].'">
                        <img draggable="false" src="' . $serverRow['image'] . '">
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
    <div class="channel-bar">
        <div class="dm-users">
            <a class="info-profile" href="/friends">
                <span class="material-symbols-rounded channel-bar-channel-icon">group</span>
                <p class="channel-bar-channel-name">friends</p>
            </a>
            <?php
            foreach ($friendsList as $friend) {
                if ($friend['id'] == $user_id) {
                    continue;
                }
                $safeUsername = htmlspecialchars($friend['username'], ENT_QUOTES, 'UTF-8');
                $capitalizedStatus = ucfirst($friend['status']);
                $isPremium = $friend['premium'] == 1;   
                $encodedUsername = urlencode($friend['username']);

                $active = $friend['id'] == $dm_id ? 'active' : '';

                echo '
                <div class="info-profile '.$active.'" data-user-id="'.$friend['id'].'" onclick="window.location.href = \'/dm/@'.$encodedUsername.'\'">
                    <div class="self-info-profile-status" data-user-id="'.$friend['id'].'">
                        <img draggable="false" class="self-info-profile-picture" src="'.$friend['profile_picture'].'" />
                        <div class="self-info-status-circle-outer">
                            <div class="self-info-status-circle-inner '.$friend['status'].'"></div>
                        </div>
                    </div>
                    <div class="self-info-status-username">
                        <div class="self-info-profile-username-container">
                            <p class="self-info-username">'.$safeUsername.'</p>
                        </div>
                        <p class="self-info-status">'.$capitalizedStatus.'</p>
                    </div>
                </div>';
            }
            ?>

        </div>
    </div>

    <div class="top-bar">
        <div class="top-bar-left">
            <div class="top-bar-item dm_with">
                <img draggable="false" src="<?php echo $dm_info['profile_picture']; ?>">
                <p><?php echo $dm_info['username']; ?></p>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="message-container" id="message-container"></div>
        <div class="typing-indicator"></div>
        <div class="input-container-2">
            <div class="file-uploads">
                <div class="file-upload-container"></div>
            </div>
            <div class="textarea-container">
                <div class="message-input-wrapper">
                    <div class="message-input-bg" id="message-input-bg"></div>
                    <div class="message-input" id="message-input" data-placeholder="Type a message..." contenteditable="true"></div>
                </div>
                <div class="options">
                    <div class="option">
                        <span class="material-symbols-rounded option-icon" onclick="document.getElementById('file-input').click();">attach_file</span>
                        <input type="file" id="file-input" accept="image/jpeg,image/png,image/gif,text/plain,audio/mpeg,audio/wav,video/mp4,image/webp,application/pdf" style="display: none;" multiple/>
                    </div>
                </div>
            </div>
        </div>
        <div class="max-message-length">
            <p class="max-characters-left"></p>
        </div>
    </div>

    <div class="users" style="overflow: unset;">
        <div class="dm-info">
            <div class="dm-info-profile-picture-username-status">
                <div class="dm-info-profile-status">
                    <img draggable="false" class="dm-info-profile-picture" src="<?php echo $dm_info['profile_picture']; ?>">
                    <div class="dm-info-status-circle-outer">
                        <div class="dm-info-status-circle-inner <?php echo $dm_info['status']; ?>"></div>
                    </div>
                </div>
                <div class="dm-info-status-username">
                    <p class="dm-info-username"><?php echo htmlspecialchars($dm_info['username']); ?></p>
                    <p class="dm-info-status"><?php echo ucfirst(strtolower($dm_info['status'])); ?></p>
                </div>
            </div>

            <div class="dm-info-bio-created-at">
                <div class="dm-info-tags" <?php if (!$dm_info['premium'] && empty($dm_tags)) { echo 'style="display: none;"'; } ?>>
                    <?php if ($dm_info['premium']) { ?>
                        <div class="dm-info-tag">
                            <img draggable="false" class="dm-info-tag-icon" src="/assets/icons/tags/tag_premium.svg">
                            <p class="dm-info-tag-tooltip">Premium</p>
                        </div>
                    <?php } ?>
                    <?php if ($dm_tags) {
                        foreach ($dm_tags as $tag) {
                            echo '
                            <div class="dm-info-tag">
                                <img draggable="false" class="dm-info-tag-icon" src="/assets/icons/tags/'.$tag['tag_icon'].'.svg">
                                <p class="dm-info-tag-tooltip">'.$tag['tag_name'].'</p>
                            </div>
                            ';
                        }   
                    } ?>
                </div>

                <div class="bio">
                    <p class="dm-info-bio-key">Bio</p>
                    <p class="dm-info-bio"><?php echo htmlspecialchars($dm_info['bio']); ?></p>
                </div>
                <div class="created-at">
                    <p class="dm-info-created-at-key">Joined on</p>
                    <p class="dm-info-created-at-date"><?php echo date('M j, Y', strtotime($dm_info['created_at'])); ?></p>
                </div>
                <a class="link" href="/profile/@<?php echo urlencode($dm_info['username']); ?>">View full profile</a>
            </div>
        </div>
    </div>

    <div class="self-info">
        <div class="self-info-left">
            <div class="self-info-profile-status">
                <img draggable="false" class="self-info-profile-picture" src="<?php echo $profile_picture; ?>">
                <div class="self-info-status-circle-outer">
                    <div class="self-info-status-circle-inner"></div>
                </div>
            </div>
            <div class="self-info-status-username">
                <p class="self-info-username"><?php echo $username; ?></p>
                <p class="self-info-status">Online</p>
            </div>
        </div>

        <div class="self-info-right">
            <span class="material-symbols-rounded self-info-right-settings" onclick="window.location.href = '/settings'">settings</span>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="/assets/js/create_server.js"></script>
    <script src="/assets/js/emojis.js"></script>
    <script src="/assets/js/dm.js"></script>
    <script src="/assets/js/globalFunctions.js"></script>
    <script src="/assets/js/notifiers.js"></script>
    <script>
        // DO NOT TOUCH OR EDIT
        const access_token = "<?php echo $access_token; ?>";
        const user_id = "<?php echo $user_id; ?>";

        const usersList = <?php echo json_encode($friendsList); ?>;

        const dm_id = "<?php echo $dm_id; ?>";

        const premium = <?php echo json_encode($premium_active); ?>;

        const socket = io("https://chat.wokki20.nl", {
            path: "/socket.io",
            transports: ["websocket"],
            query: {
                access_token: access_token
            },
        });

    </script>
</body>
</html>
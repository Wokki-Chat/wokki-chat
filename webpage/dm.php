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
    SELECT u.id, u.username, u.profile_picture, u.status, u.premium, u.premium_expires_at, u.bio,
           'individual' as contact_type, NULL as contact_id, NULL as contact_name
    FROM contact_users cu1
    JOIN contact_users cu2 ON cu1.contact_id = cu2.contact_id AND cu2.user_id != cu1.user_id
    JOIN users u ON cu2.user_id = u.id
    LEFT JOIN contact_requests cr ON cu1.contact_id = cr.contact_id
    WHERE cu1.user_id = ? 
      AND cr.contact_id IS NULL
      AND (SELECT COUNT(*) FROM contact_users WHERE contact_id = cu1.contact_id) = 2
    GROUP BY u.id
");
$friendsStmt->bind_param("i", $user_id);
$friendsStmt->execute();
$friendsResult = $friendsStmt->get_result();

$friendsList = [];
while ($row = $friendsResult->fetch_assoc()) {
    $friendsList[] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'profile_picture' => $row['profile_picture'],
        'status' => $row['status'], 
        'premium' => $row['premium'] && ($row['premium_expires_at'] > time() || $row['premium_expires_at'] === null),
        'bio' => $row['bio'],
        'contact_type' => 'individual'
    ];
}
$friendsStmt->close();

$groupsStmt = $mysqli->prepare("
    SELECT c.contact_id as contact_id, c.contact_name, c.contact_picture,
           'group' as contact_type
    FROM contact_users cu
    JOIN contacts c ON cu.contact_id = c.contact_id
    LEFT JOIN contact_requests cr ON c.contact_id = cr.contact_id
    WHERE cu.user_id = ? 
      AND cr.contact_id IS NULL
      AND c.contact_name IS NOT NULL
      AND (SELECT COUNT(*) FROM contact_users WHERE contact_id = c.contact_id) > 2
    GROUP BY c.contact_id
");
$groupsStmt->bind_param("i", $user_id);
$groupsStmt->execute();
$groupsResult = $groupsStmt->get_result();

while ($row = $groupsResult->fetch_assoc()) {
    $friendsList[] = [
        'id' => $row['contact_id'],
        'username' => $row['contact_name'],
        'profile_picture' => $row['contact_picture'],
        'status' => null,
        'premium' => false,
        'bio' => null,
        'contact_type' => 'group',
        'contact_id' => $row['contact_id']
    ];
}
$groupsStmt->close();

$friendsList[] = [
    'id' => $user_id,
    'username' => $username,
    'profile_picture' => $profile_picture,
    'status' => $status, 
    'bio' => $bio,
    'contact_type' => 'self'
];

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

$is_group = false;
$dm_id = null;
$contact_id = null;

$userLookupStmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
$userLookupStmt->bind_param("s", $dm_name);
$userLookupStmt->execute();
$userLookupResult = $userLookupStmt->get_result();

if ($userLookupResult->num_rows > 0) {
    $userLookupRow = $userLookupResult->fetch_assoc();
    $dm_id = $userLookupRow['id'];
    $is_group = false;
} else {
    $groupLookupStmt = $mysqli->prepare("
        SELECT c.contact_id
        FROM contacts c
        JOIN contact_users cu ON cu.contact_id = c.contact_id
        WHERE c.contact_name = ? AND cu.user_id = ?
    ");
    $groupLookupStmt->bind_param("si", $dm_name, $user_id);
    $groupLookupStmt->execute();
    $groupLookupResult = $groupLookupStmt->get_result();
    
    if ($groupLookupResult->num_rows > 0) {
        $groupLookupRow = $groupLookupResult->fetch_assoc();
        $contact_id = $groupLookupRow['contact_id'];
        $is_group = true;
    }
    
    $groupLookupStmt->close();
}

$userLookupStmt->close();

if (!$is_group && !$dm_id) {
    header('Location: /home');
    exit;
}

if (!$is_group) {
    if ($dm_id === $user_id) {
        header('Location: /home');
        exit;
    }

    $found = false;
    foreach ($friendsList as $friend) {
        if ($friend['contact_type'] === 'individual' && $friend['id'] === $dm_id) {
            $found = true;
            break;
        }
    }

    if (!$found) {
        header('Location: /home');
        exit;
    }

    $contactStmt = $mysqli->prepare("
        SELECT cu1.contact_id
        FROM contact_users cu1
        JOIN contact_users cu2 ON cu1.contact_id = cu2.contact_id
        LEFT JOIN contact_requests cr ON cu1.contact_id = cr.contact_id
        WHERE cu1.user_id = ? 
        AND cu2.user_id = ? 
        AND cr.contact_id IS NULL
        AND (SELECT COUNT(*) FROM contact_users WHERE contact_id = cu1.contact_id) = 2
        LIMIT 1
    ");
    $contactStmt->bind_param("ii", $user_id, $dm_id);
    $contactStmt->execute();
    $contactResult = $contactStmt->get_result();

    if ($contactResult->num_rows > 0) {
        $contactRow = $contactResult->fetch_assoc();
        $contact_id = $contactRow['contact_id'];
    } else {
        header('Location: /home');
        exit;
    }
    $contactStmt->close();
}

function getMembersCount($mysqli, $contact_id) {
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as members_count
        FROM contact_users
        WHERE contact_id = ?
    ");
    $stmt->bind_param("i", $contact_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['members_count'];
}

if ($is_group) {
    $groupInfoStmt = $mysqli->prepare("SELECT contact_name, contact_picture FROM contacts WHERE contact_id = ?");
    $groupInfoStmt->bind_param("i", $contact_id);
    $groupInfoStmt->execute();
    $groupInfoResult = $groupInfoStmt->get_result();
    
    $dm_info = null;
    $dm_tags = [];
    
    if ($groupInfoResult->num_rows > 0) {
        $groupRow = $groupInfoResult->fetch_assoc();
        $dm_info = [
            'username' => $groupRow['contact_name'],
            'profile_picture' => $groupRow['contact_picture'],
            'status' => null,
            'premium' => false,
            'premium_expires_at' => null,
            'bio' => null,
            'created_at' => null,
            'is_group' => true,
            'members_count' => getMembersCount($mysqli, $contact_id),
        ];
    }
    
    $groupInfoStmt->close();
} else {
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
        $dm_info['is_group'] = false;
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
}

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
<html lang="en" class="<?php echo $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wokki Chat - <?php echo $dm_info['username'] ?></title>
    <link rel="stylesheet" href="/assets/styles/main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/dark.min.css" />
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdn.wokki20.nl/dynamic/jspt/jspt.css">
    <script src="https://cdn.wokki20.nl/dynamic/jspt/jspt.js"></script>
</head>
<body>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.css"/>
    <script src="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.js" defer></script>
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
            <div class="dm-users">
                <a class="info-profile" href="/home">
                    <span class="material-symbols-rounded channel-bar-channel-icon">home</span>
                    <p class="channel-bar-channel-name">Home</p>
                </a>
                <?php
                foreach ($friendsList as $friend) {
                    if ($friend['id'] == $user_id) {
                        continue;
                    }
                    $safeUsername = htmlspecialchars($friend['username'], ENT_QUOTES, 'UTF-8');
                    $capitalizedStatus = $friend['is_group'] ? $friend['members_count'] . ' Members' : ucfirst($friend['status']);
                    $isPremium = $friend['premium'] == 1;   
                    $encodedUsername = urlencode($friend['username']);

                    echo '
                    <a class="info-profile '.($friend['username'] == $dm_name ? 'active' : '').'" data-user-id="'.$friend['id'].'" href="/dm/@'.$encodedUsername.'>
                        <div class="self-info-profile-status" data-user-id="'.$friend['id'].'">
                            <img class="self-info-profile-picture" src="'.$friend['profile_picture'].'" />
                            '.($friend['is_group'] ? '<div class="self-info-status-circle-outer">
                                <div class="self-info-status-circle-inner '.$friend['status'].'"></div>
                            </div>' : '').'
                        </div>
                        <div class="self-info-status-username">
                            <div class="self-info-profile-username-container">
                                <p class="self-info-username">'.$safeUsername.'</p>
                            </div>
                            <p class="self-info-status">'.$capitalizedStatus.'</p>
                        </div>
                    </a>';
                }
                ?>
            </div>
        </div>


        <div class="top-bar">
            <div class="top-bar-left">
                <span class="material-symbols-rounded top-bar-menu" id="top-bar-menu">menu</span>
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

        <wchat-allowed-scripts value="dm.js;"></wchat-allowed-scripts>
        <wchat-data id="access-token" value="<?php echo htmlspecialchars($access_token); ?>"></wchat-data>
        <wchat-data id="user-id" value="<?php echo htmlspecialchars($user_id); ?>"></wchat-data>
        <wchat-data id="users-list" value="<?php echo htmlspecialchars(json_encode($friendsList)); ?>"></wchat-data>
        <wchat-data id="contact-id" value="<?php echo htmlspecialchars($contact_id); ?>"></wchat-data>
        <wchat-data id="premium" value="<?php echo htmlspecialchars(json_encode($premium_active)); ?>"></wchat-data>
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
    <script src="/assets/js/dm.js" type="module"></script>
    <script src="/assets/js/load_scripts.js"></script>
</body>
</html>
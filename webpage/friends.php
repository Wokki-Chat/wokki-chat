<?php
include 'app/config.php';
include 'global.php';
$access_token = $_COOKIE['access_token'];

if (!$access_token) {
    header('Location: login');
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
    header('Location: login');
    exit;
}

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
    <title>wokki chat</title>
    <link rel="stylesheet" href="assets/styles/main.css">
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
                <img src="assets/images/monochrome-logo-purple-background.png">
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
    <div class="channel-bar">
        <div class="dm-users">
            <a class="info-profile active" href="/friends">
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

                echo '
                <div class="info-profile" data-user-id="'.$friend['id'].'" onclick="window.location.href = \'/dm/@'.$encodedUsername.'\'">
                    <div class="self-info-profile-status" data-user-id="'.$friend['id'].'">
                        <img class="self-info-profile-picture" src="'.$friend['profile_picture'].'" />
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
            <span class="material-symbols-rounded top-bar-menu" id="top-bar-menu">menu</span>
            <div class="top-bar-item">
                <span class="material-symbols-rounded top-bar-channel-icon">group</span>
                <p class="top-bar-channel-name">friends</p>
            </div>
            <div class="top-bar-item">
                <button class="button-primary-filled" id="add-friend-btn">Add Friend</button>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="pending-friend-requests">
            <p class="pending-friend-requests-title">Pending Friend Requests</p>
            <div class="pending-friend-requests-list" id="pending-friend-requests">
                <p>No pending friend requests</p>
            </div>

        </div>
        <br>
        <div class="outgoing-friend-requests">
            <p class="outgoing-friend-requests-title">Outgoing Friend Requests</p>
            <div class="outgoing-friend-requests-list" id="outgoing-friend-requests">
                <p>No outgoing friend requests</p>
            </div>
        </div>
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
                <p class="self-info-username"><?php echo $username; ?></p>
                <p class="self-info-status">Online</p>
            </div>
        </div>

        <div class="self-info-right">
            <span class="material-symbols-rounded self-info-right-settings" onclick="window.location.href = '/settings'">settings</span>
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
    <script src="/assets/js/create_server.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="/assets/js/friends.js"></script>
    <script src="/assets/js/globalFunctions.js"></script>
    <script src="/assets/js/notifiers.js"></script>
    <script>
        // DO NOT TOUCH OR EDIT
        const access_token = "<?php echo $access_token; ?>";
        const user_id = "<?php echo $user_id; ?>";

        const usersList = <?php echo json_encode($friendsList); ?>;    
        
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
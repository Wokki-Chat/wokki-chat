<?php
include 'app/config.php';

$access_token = $_COOKIE['access_token'];
header("Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!$access_token) {
    header('Location: /login?redirect=' . str_replace('https://chat.wokki20.nl', '', $_SERVER['REQUEST_URI']));
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
    header('Location: /login?redirect=' . str_replace('https://chat.wokki20.nl', '', $_SERVER['REQUEST_URI']));
    exit;
}
$stmt->close();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

$server_id = null;
if (isset($segments[0]) && $segments[0] === 'server' && !empty($segments[1])) {
    $server_id = $segments[1];
}

$channel_id = null;
if (isset($segments[2]) && $segments[2] === 'channel' && !empty($segments[3])) {
    $channel_id = $segments[3];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['server_id']) || !isset($_POST['join_server'])) {
        header('Location: /home');
        exit;
    }

    $join_server_id = $_POST['server_id'];
    $join_server = $_POST['join_server'];

    if ($join_server) {
        if ($join_server_id && $join_server_id === $server_id) {
            $stmt = $mysqli->prepare("SELECT join_without_invite FROM servers WHERE id = ?");
            $stmt->bind_param("s", $join_server_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $join_without_invite = $row['join_without_invite'] == 1;
            }
            $stmt->close();

            if ($join_without_invite) {
                $findUserInServerStmt = $mysqli->prepare("SELECT * FROM server_members WHERE server_id = ? AND user_id = ?");
                $findUserInServerStmt->bind_param("si", $server_id, $user_id);
                $findUserInServerStmt->execute();
                $findUserInServerResult = $findUserInServerStmt->get_result();

                $user_found = false;

                if ($findUserInServerResult->num_rows > 0) {
                    $user_found = true;
                    $findUserInServerStmt->close();
                } else {
                    $findUserInServerStmt->close();
                }

                if (!$user_found) {
                    $stmt = $mysqli->prepare("SELECT role_id FROM server_roles WHERE add_on_join = 1 AND server_id = ?");
                    $stmt->bind_param("s", $server_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $addOnJoinRoles = [];
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $addOnJoinRoles[] = [
                                'role_id' => $row['role_id']
                            ];
                        }
                    }
                    $stmt->close();

                    $joined_at = date('Y-m-d H:i:s');

                    $updateMembersStmt = $mysqli->prepare("INSERT INTO server_members (server_id, user_id, joined_at) VALUES (?, ?, ?)");
                    $updateMembersStmt->bind_param("sis", $server_id, $user_id, $joined_at);
                    $updateMembersStmt->execute();
                    $updateMembersStmt->close();

                    $updateMemberRolesStmt = $mysqli->prepare("INSERT INTO user_server_roles (user_id, server_id, role_id) VALUES (?, ?, ?)");
                    foreach ($addOnJoinRoles as $role) {
                        $updateMemberRolesStmt->bind_param("iss", $user_id, $server_id, $role['role_id']);
                        $updateMemberRolesStmt->execute();
                    }
                    $updateMemberRolesStmt->close();
                }

                header('Location: /server/' . $join_server_id);
                exit;
            } else {
                header('Location: /home');
                exit;
            }
        } else {
            header('Location: /home');
            exit;
        }
    } else {
        header('Location: /home');
        exit;
    }
}

$serverStmt = $mysqli->prepare("
    SELECT s.*
    FROM servers s
    INNER JOIN server_members sm ON sm.server_id = s.id
    WHERE sm.user_id = ?
");
$serverStmt->bind_param("i", $user_id);
$serverStmt->execute();
$result = $serverStmt->get_result();

$user_servers = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $user_servers[$row['id']] = $row;
    }
}

$serverStmt->close();


function getServerInfo($server_id, $mysqli) {
    $stmt = $mysqli->prepare("SELECT * FROM servers WHERE id = ?");
    $stmt->bind_param("s", $server_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }    
}

if (!$server_id || !isset($user_servers[$server_id])) {

    $serverInfo = getServerInfo($server_id, $mysqli);

    $join_without_invite = $serverInfo['join_without_invite'] == 1 ? true : false;

    $channels = [];
    $channel_groups = [];

    if (!empty($serverInfo['channels'])) {
        $channels = json_decode($serverInfo['channels'], true);
    }

    if (!empty($serverInfo['channel_groups'])) {
        $channel_groups = json_decode($serverInfo['channel_groups'], true);
    }

    if (!$channel_id) {
        foreach ($channels as $ch) {
            if (!empty($ch['default'])) {
                $channel_id = $ch['channel_id'];
                break;
            }
        }
        if (!$channel_id && count($channels) > 0) {
            $channel_id = $channels[0]['channel_id'];
        }
    }

    $channel = null;
    foreach ($channels as $ch) {
        if ($ch['channel_id'] == $channel_id) {
            $channel = $ch;
            break;
        }
    }

    if (!$channel) {
        header("Location: /server/$server_id");
        exit;
    }

    $channels_for_markdown = [];
    foreach ($channels as $ch) {
        $channels_for_markdown[] = [
            'name' => $ch['channel_name'],
            'channel_id' => $ch['channel_id']
        ];
    }

    $isServerAdmin = false;
    if ($serverInfo['created_by'] == $user_id) {
        $isServerAdmin = true;
    }
} else {
    $serverInfo = $user_servers[$server_id];

    $channels = [];
    $channel_groups = [];

    if (!empty($serverInfo['channels'])) {
        $channels = json_decode($serverInfo['channels'], true);
    }

    if (!empty($serverInfo['channel_groups'])) {
        $channel_groups = json_decode($serverInfo['channel_groups'], true);
    }

    if (!$channel_id) {
        foreach ($channels as $ch) {
            if (!empty($ch['default'])) {
                $channel_id = $ch['channel_id'];
                break;
            }
        }
        if (!$channel_id && count($channels) > 0) {
            $channel_id = $channels[0]['channel_id'];
        }
    }

    $channel = null;
    foreach ($channels as $ch) {
        if ($ch['channel_id'] == $channel_id) {
            $channel = $ch;
            break;
        }
    }

    if (!$channel) {
        header("Location: /server/$server_id");
        exit;
    }

    $channels_for_markdown = [];
    foreach ($channels as $ch) {
        $channels_for_markdown[] = [
            'name' => $ch['channel_name'],
            'channel_id' => $ch['channel_id']
        ];
    }

    $isServerAdmin = false;
    if ($serverInfo['created_by'] == $user_id) {
        $isServerAdmin = true;
    }
}

$userStmt = $mysqli->prepare("SELECT username, premium, premium_expires_at, premium_know, profile_picture FROM users WHERE id = ?");
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
} else {
    $username = "Unknown";
    $premium = false;
    $premium_expires_at = null;
    $premium_know = false;
    $profile_picture = "/uploads/profile-pictures/default-profile.png";
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

$is_in_server = true;

if (!$server_id || !isset($user_servers[$server_id])) {
    $is_in_server = false;
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>wokki chat</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
      rel="stylesheet"
    />
      <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/dark.min.css" />
    <link rel="stylesheet" href="/assets/styles/main.css" />
    <link rel="prerender" href="/settings">
    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
    <meta name="is_in_server" content="<?php echo $is_in_server ?>">
</head>
<body>
    <div class="server-bar">
        <div class="server-bar-dms">
            <a class="server-bar-item" href="/home">
                <img src="/assets/images/monochrome-logo-purple-background.png" />
            </a>
        </div>
        <div class="divider"></div>
        <div class="server-bar-channels">
            <?php
            foreach ($user_servers as $srv) {
                $activeClass = ($srv['id'] == $server_id) ? 'active' : '';
                echo '<a class="server-bar-item '.$activeClass.'" href="/server/'.$srv['id'].'">
                    <img src="'.$srv['image'].'" />
                    <p class="tooltip">'.$srv['name'].'</p>
                </a>';
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
        <div class="channel-bar-server-info" onclick="document.querySelector('.settings-dropdown').classList.toggle('show');">
            <h2 class="channel-bar-server-name"><?php echo htmlspecialchars($serverInfo['name']); ?></h2>
            <?php if ($is_in_server): ?>
            <span class="material-symbols-rounded channel-bar-server-settings">settings</span>
            <?php endif; ?>
        </div>
        <?php if ($is_in_server): ?>
        <div class="settings-dropdown">
            <div class="settings-dropdown-content">
                <div class="settings-dropdown-item" onclick="showInviteModal();">
                    <p>Invite people</p>
                    <span class="material-symbols-rounded">group_add</span>
                </div>
                <div class="divider"></div>
                <div class="settings-dropdown-item danger" onclick="leaveServer()">
                    <p>Leave server</p>
                    <span class="material-symbols-rounded">logout</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="channel-bar-channels">
            <?php
                $channels_by_group = [];
                foreach ($channel_groups as $group) {
                    $channels_by_group[$group['channel_group_id']] = [];
                }
                foreach ($channels as $ch) {
                    $channels_by_group[$ch['channel_group_id']][] = $ch;
                }

                foreach ($channel_groups as $group) {
                    echo '<div class="channel-group">';
                    echo '<p class="channel-group-name">'.htmlspecialchars($group['channel_group_name']).'<span class="material-symbols-rounded channel-group-expand">expand_more</span></p>';

                    if (!empty($channels_by_group[$group['channel_group_id']])) {
                        foreach ($channels_by_group[$group['channel_group_id']] as $ch) {
                            $isActive = ($ch['channel_id'] == $channel_id);
                            $channelIcon = "tag";
                            if ($ch['channel_type'] === "text") {
                                $channelIcon = "tag";
                            } else if ($ch['channel_type'] === "voice") {
                                $channelIcon = "headset_mic";
                            }

                            echo '<div class="channel-group-content">';
                            echo '<a class="channel-bar-channel '.($isActive ? 'active' : '').'" href="/server/'.$server_id.'/channel/'.$ch['channel_id'].'">';
                            echo '<span class="material-symbols-rounded channel-bar-channel-icon">'.$channelIcon.'</span>';
                            echo '<p class="channel-bar-channel-name">'.htmlspecialchars($ch['channel_name']).'</p>';
                            echo '</a></div>';
                        }
                    } else {
                        echo '<p class="channel-bar-empty">No channels in this group</p>';
                    }
                    echo '</div>';
                }
            ?>
            <?php if ($isServerAdmin): ?>
            <div class="channel-group expanded">
                <p class="channel-group-name">Options<span class="material-symbols-rounded channel-group-expand">expand_more</span></p>
                <div class="channel-group-content">
                    <a class="channel-bar-channel new-channel" onclick="openCreateCategoryModal()">
                        <span class="material-symbols-rounded channel-bar-channel-icon">category</span>
                        <p class="channel-bar-channel-name">New Category</p>
                    </a>
                    <a class="channel-bar-channel new-channel" onclick="openCreateChannelModal()">
                        <span class="material-symbols-rounded channel-bar-channel-icon">tag</span>
                        <p class="channel-bar-channel-name">New Channel</p>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="top-bar">
        <div class="top-bar-left">
            <?php
            $channelIcon = ($channel['channel_type'] === "text") ? "tag" : "tag";
            ?>
            <span class="material-symbols-rounded top-bar-channel-icon"><?php echo $channelIcon; ?></span>
            <p class="top-bar-channel-name"><?php echo htmlspecialchars($channel['channel_name']); ?></p>
        </div>
    </div>

    <div class="main-content">
        <?php if ($is_in_server): ?>
            <?php if ($channel['channel_type'] === "text"): ?>
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
            <?php endif; ?>
            <?php if ($channel['channel_type'] === "voice"): ?>
                <script>
                    openParticipantsPopup(token, roomName);
                </script>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!$is_in_server): ?>
            <?php
                if (isset($_GET['invite'])) {
                    $invite_id = $_GET['invite'];
                    $checkStmt = $mysqli->prepare("SELECT expires_at FROM invites WHERE code = ? AND server_id = ?");
                    $checkStmt->bind_param("ss", $invite_id, $server_id);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result();
                    $invite = $result->fetch_assoc();
                    $expires_at = $invite['expires_at'];
                    if (!$invite) {
                        header('Location: /server/' . $server_id);
                        return;
                    }
                    if ($invite) {
                        if ($expires_at !== '0000-00-00 00:00:00') {
                            $now = new DateTime();
                            $expireDate = new DateTime($expires_at);
                            if ($now > $expireDate) {
                                return;
                            }
                            $findUserInServerStmt = $mysqli->prepare("SELECT user_id FROM server_members WHERE server_id = ? AND user_id = ?");
                            $findUserInServerStmt->bind_param("si", $server_id, $user_id);
                            $findUserInServerStmt->execute();
                            $findUserInServerResult = $findUserInServerStmt->get_result();

                            $user_found = false;

                            if ($findUserInServerResult->num_rows > 0) {
                                $error_message = "You are already a member of this server.";
                                $user_found = true;
                                $findUserInServerStmt->close();
                            } else {
                                $findUserInServerStmt->close();
                            }

                            if (!$user_found) {
                                $stmt = $mysqli->prepare("SELECT role_id FROM server_roles WHERE add_on_join = 1 AND server_id = ?");
                                $stmt->bind_param("s", $server_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $addOnJoinRoles = [];
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $addOnJoinRoles[] = [
                                            'role_id' => $row['role_id']
                                        ];
                                    }
                                }
                                $stmt->close();

                                $joined_at = date('Y-m-d H:i:s');

                                $updateMembersStmt = $mysqli->prepare("INSERT INTO server_members (server_id, user_id, joined_at) VALUES (?, ?, ?)");
                                $updateMembersStmt->bind_param("sis", $server_id, $user_id, $joined_at);
                                $updateMembersStmt->execute();
                                $updateMembersStmt->close();

                                $updateMemberRolesStmt = $mysqli->prepare("INSERT INTO user_server_roles (user_id, server_id, role_id) VALUES (?, ?, ?)");
                                foreach ($addOnJoinRoles as $role) {
                                    $updateMemberRolesStmt->bind_param("iss", $user_id, $server_id, $role['role_id']);
                                    $updateMemberRolesStmt->execute();
                                }
                                $updateMemberRolesStmt->close();
                            }

                            header('Location: /server/' . $server_id);
                            exit;
                        }
                    }
                }

                $description_text = "You are not a member of this server. Please contact a server member with the appropriate permissions to provide you with an invitation.";
                
                if ($join_without_invite) {
                    $description_text = "You are not a member of this server. To gain access to this server, please click the button below to join.";
                }
            ?>
            <div class="no-member">
                <div class="no-member-text">
                    <p class="no-member-title">You are not a member of this server.</p>
                    <p class="no-member-description"><?php echo $description_text; ?></p>
                </div>
                <?php if ($join_without_invite): ?>
                    <form class="no-member-button" action="" method="post">
                        <input type="hidden" name="server_id" value="<?php echo $server_id; ?>">
                        <input type="hidden" name="join_server" value="true">
                        <button class="button-primary-filled" id="join-server-button">Join Server</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="users">
        <div class="online-users" id="online-users">
        </div>
        <div class="offline-users" id="offline-users">
        </div>
    </div>

    <div class="self-info">
        <div class="self-info-left">
            <div class="self-info-profile-status">
                <img class="self-info-profile-picture" src="<?php echo $profile_picture; ?>" />
                <div class="self-info-status-circle-outer">
                    <div class="self-info-status-circle-inner"></div>
                </div>
            </div>
            <div class="self-info-status-username">
                <p class="self-info-username"><?php echo htmlspecialchars($username); ?></p>
                <p class="self-info-status">Online</p>
            </div>
        </div>

        <div class="self-info-right" >
            <span class="material-symbols-rounded self-info-right-settings" onclick="window.location.href = '/settings?from=/server/<?php echo $server_id; ?>/channel/<?php echo $channel_id; ?>' ">settings</span>
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
    <script src="/assets/js/server.js"></script>
    <script src="/assets/js/create_server.js"></script>
    <script src="/assets/js/emojis.js"></script>
    <script src="/assets/js/globalFunctions.js"></script>
    <script src="/assets/js/notifiers.js"></script>

    <?php if ($channel['channel_type'] === "text") echo '<script src="/assets/js/call_reconnect.js"></script>'; ?>
    <script>
        // DO NOT TOUCH OR EDIT
        const access_token = "<?php echo $access_token; ?>";
        const server_id = "<?php echo $server_id; ?>";
        const channel_id = "<?php echo $channel_id; ?>";
        const channel_name = "<?php echo $channel['channel_name']; ?>";
        const channel_type = "<?php echo $channel['channel_type']; ?>";

        const channels = <?php echo json_encode($channels_for_markdown); ?>;
        const channel_groups = <?php echo json_encode($channel_groups); ?>;

        const user_id = "<?php echo $user_id; ?>";

        const premium = <?php echo json_encode($premium_active); ?>;

        const is_in_server = <?php echo json_encode($is_in_server); ?>;

        const socket = io("https://chat.wokki20.nl", {
            path: "/socket.io",
            transports: ["websocket"],
            query: {
                access_token: access_token,
                server_id: server_id
            },
        });
    </script>
</body>
</html>
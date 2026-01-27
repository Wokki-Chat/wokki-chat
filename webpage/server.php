<?php
include 'app/config.php';
include 'global.php';
include 'app/maintenance.php';

$access_token = $_COOKIE['access_token'];

$maxAge = 3600;
$file = __FILE__;
$lastModified = filemtime($file);
$etag = md5_file($file);

header("Cache-Control: public, max-age=$maxAge");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");
header("ETag: \"$etag\"");

if ((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified) ||
    (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === "\"$etag\"")) {
    http_response_code(304);
    exit;
}

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
    ORDER BY
        CASE WHEN sm.position IS NULL THEN 1 ELSE 0 END,
        sm.position DESC,
        sm.joined_at DESC,
        sm.id ASC
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

function getChannels($server_id, $mysqli) {
    $stmt = $mysqli->prepare("SELECT * FROM channels WHERE server_id = ?");
    $stmt->bind_param("s", $server_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $channels = $result->fetch_all(MYSQLI_ASSOC);

    usort($channels, function($a, $b) {
        if ($a['channel_group_id'] !== $b['channel_group_id']) {
            return strcmp($b['channel_group_id'], $a['channel_group_id']);
        }

        $aHasIndex = $a['channel_index'] !== null;
        $bHasIndex = $b['channel_index'] !== null;

        if ($aHasIndex && $bHasIndex) {
            return $b['channel_index'] <=> $a['channel_index'];
        }

        if ($aHasIndex) return 1;
        if ($bHasIndex) return -1;

        return strtotime($b['channel_created_at']) <=> strtotime($a['channel_created_at']);
    });

    return $channels;
}

function getChannelGroups($server_id, $mysqli) {
    $stmt = $mysqli->prepare("SELECT * FROM channel_groups WHERE server_id = ?");
    $stmt->bind_param("s", $server_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $groups = $result->fetch_all(MYSQLI_ASSOC);

    usort($groups, function($a, $b) {
        $aHasIndex = $a['channel_group_index'] !== null;
        $bHasIndex = $b['channel_group_index'] !== null;

        if ($aHasIndex && $bHasIndex) {
            return $b['channel_group_index'] <=> $a['channel_group_index'];
        }

        if ($aHasIndex) return -1;
        if ($bHasIndex) return 1;

        return strtotime($a['channel_group_created_at']) <=> strtotime($b['channel_group_created_at']);
    });

    return $groups;
}

if (!$server_id || !isset($user_servers[$server_id])) {
    $serverInfo = getServerInfo($server_id, $mysqli);
    $join_without_invite = $serverInfo['join_without_invite'] == 1 ? true : false;
    $channels = getChannels($server_id, $mysqli);
    $channel_groups = getChannelGroups($server_id, $mysqli);

    if (!$channel_id) {
        foreach ($channels as $ch) {
            if (isset($ch['is_default']) && $ch['is_default'] == 1) {
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

    if (!$channel && count($channels) > 0) {
        $channel = $channels[0];
        $channel_id = $channel['channel_id'];
    }

    if (!$channel) {
        header("Location: /");
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
    if ($serverInfo['created_by'] == $user_id && $serverInfo['server_type'] == 'normal') {
        $isServerAdmin = true;
    }

} else {
    $serverInfo = $user_servers[$server_id];
    $channels = getChannels($server_id, $mysqli);
    $channel_groups = getChannelGroups($server_id, $mysqli);

    if (!$channel_id) {
        foreach ($channels as $ch) {
            if (isset($ch['is_default']) && $ch['is_default'] == 1) {
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

    if (!$channel && count($channels) > 0) {
        $channel = $channels[0];
        $channel_id = $channel['channel_id'];
    }

    if (!$channel) {
        header("Location: /");
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


$is_in_server = true;

if (!$server_id || !isset($user_servers[$server_id])) {
    $is_in_server = false;
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Wokki Chat</title>
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
    <meta name="app_page_name" content="<?php echo $serverInfo['name'] ?>">
    <meta name="app_page_image" content="https://chat.wokki20.nl/<?php echo $serverInfo['image'] ?>">
    <link rel="stylesheet" href="https://cdn.wokki20.nl/dynamic/jspt/jspt.css">
    <script src="https://cdn.wokki20.nl/dynamic/jspt/jspt.js"></script>
</head>
<body>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.css"/>
    <script src="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.js"></script>
    <div class="server-bar">
        <div class="server-bar-dms">
            <a class="server-bar-item" id="server-bar-item-home" href="/home">
                <img src="/assets/images/monochrome-logo-purple-background.png" />
            </a>
        </div>
        <div class="divider"></div>
        <div class="server-bar-channels" id="server-bar-channels">
            <?php
            foreach ($user_servers as $srv) {
                
                $lowImage = preg_replace('/\.(webp|gif)$/', '-low.$1', $srv['image']);

                echo '<a class="server-bar-item" id="server-bar-item-server" href="/server/'.$srv['id'].'" data-server-id="'.$srv['id'].'">
                    <img src="'.$lowImage.'" loading="lazy" decoding="async" width="47" height="47" draggable="false"/>
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
    <main id="app">
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
                    <div class="settings-dropdown-item" id="invite-people">
                        <p>Invite people</p>
                        <span class="material-symbols-rounded">group_add</span>
                    </div>
                    <div class="divider"></div>
                    <div class="settings-dropdown-item danger" id="leave-server">
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
                        echo '<div class="channel-group" draggable="true" data-channel-group="'.$group['channel_group_id'].'">';
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

                                echo '
                                <div class="channel-group-content">
                                    <a class="channel-bar-channel '.($isActive ? 'active' : '').'" id="channel" movable="'.($isServerAdmin ? 'true' : 'false').'" data-channel-id="'.$ch['channel_id'].'" href="/server/'.$server_id.'/channel/'.$ch['channel_id'].'">
                                        <div class="channel-bar-channel-icon-name">
                                            <span class="material-symbols-rounded channel-bar-channel-icon">'.$channelIcon.'</span>
                                            <p class="channel-bar-channel-name">'.htmlspecialchars($ch['channel_name']).'</p>
                                        </div>
                                        '.($isServerAdmin ? '<span class="material-symbols-rounded channel-settings">settings</span>' : '').'
                                    </a>
                                </div>';
                            }
                        } else {
                            echo '<p class="channel-bar-empty">No channels in this group</p>';
                        }
                        echo '</div>';
                    }
                ?>
                <?php if ($isServerAdmin): ?>
                <div class="channel-group expanded" draggable="false">
                    <p class="channel-group-name">Options<span class="material-symbols-rounded channel-group-expand">expand_more</span></p>
                    <div class="channel-group-content">
                        <a class="channel-bar-channel new-channel" id="new-category">
                            <div class="channel-bar-channel-icon-name">
                                <span class="material-symbols-rounded channel-bar-channel-icon">category</span>
                                <p class="channel-bar-channel-name">New Category</p>
                            </div>
                        </a>
                        <a class="channel-bar-channel new-channel" id="new-channel">
                            <div class="channel-bar-channel-icon-name">
                                <span class="material-symbols-rounded channel-bar-channel-icon">tag</span>
                                <p class="channel-bar-channel-name">New Channel</p>
                            </div>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="top-bar">
            <div class="top-bar-left">
                <span class="material-symbols-rounded top-bar-menu" id="top-bar-menu">menu</span>
                <?php
                $channelIcon = ($channel['channel_type'] === "text") ? "tag" : "tag";
                ?>
                <span class="material-symbols-rounded top-bar-channel-icon"><?php echo $channelIcon; ?></span>
                <p class="top-bar-channel-name"><?php echo htmlspecialchars($channel['channel_name']); ?></p>
            </div>
        </div>

        <div class="main-content">
            <?php echo $maintenanceHtml; ?>
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
                <a class="material-symbols-rounded self-info-right-settings no-underline" href="/settings?from=/server/<?php echo $server_id; ?>/channel/<?php echo $channel_id; ?>">settings</a>
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
        <wchat-allowed-scripts value="server.js;"></wchat-allowed-scripts>
        <wchat-data id="access-token" value="<?php echo htmlspecialchars($access_token); ?>"></wchat-data>
        <wchat-data id="server-id" value="<?php echo htmlspecialchars($server_id); ?>"></wchat-data>
        <wchat-data id="channel-id" value="<?php echo htmlspecialchars($channel_id); ?>"></wchat-data>
        <wchat-data id="channel-name" value="<?php echo htmlspecialchars($channel['channel_name']); ?>"></wchat-data>
        <wchat-data id="channel-type" value="<?php echo htmlspecialchars($channel['channel_type']); ?>"></wchat-data>
        <wchat-data id="channels" value="<?php echo htmlspecialchars(json_encode($channels_for_markdown)); ?>"></wchat-data>
        <wchat-data id="channel-groups" value="<?php echo htmlspecialchars(json_encode($channel_groups)); ?>"></wchat-data>
        <wchat-data id="user-id" value="<?php echo htmlspecialchars($user_id); ?>"></wchat-data>
        <wchat-data id="premium" value="<?php echo htmlspecialchars(json_encode($premium_active)); ?>"></wchat-data>
        <wchat-data id="is-in-server" value="<?php echo htmlspecialchars(json_encode($is_in_server)); ?>"></wchat-data>
        <wchat-data id="username-text" value="<?php echo htmlspecialchars($username); ?>"></wchat-data>
        <wchat-data id="profile-picture-url" value="<?php echo htmlspecialchars($profile_picture); ?>"></wchat-data>
        <script src="/assets/js/emojis.js"></script>
    </main>
    <div id="settings">
    </div>
    <script src="/assets/js/socket.js" data-swup-ignore-script></script>
    <script type="module" data-swup-ignore-script>
        import Swup from "https://unpkg.com/swup@4?module";
        import SwupPreloadPlugin from "https://unpkg.com/@swup/preload-plugin@3?module";
        import SwupScriptsPlugin from "https://unpkg.com/@swup/scripts-plugin@2?module";
        import SwupFragmentPlugin from "https://unpkg.com/@swup/fragment-plugin@1?module";

        window.swup = new Swup({
            containers: ["#app", "#settings"],
            cache: true,
            plugins: [
                new SwupPreloadPlugin(),
                new SwupScriptsPlugin({
                    body: true,
                    head: false,
                }),
                new SwupFragmentPlugin({
                    rules: [
                        {
                            from: "/settings",
                            to: "/settings",
                            containers: ["#settings"]
                        }
                    ]
                })
            ]
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js" data-swup-ignore-script></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js" data-swup-ignore-script></script>
    <script src="/assets/js/create_server.js" data-swup-ignore-script></script>
    <script src="/assets/js/globalFunctions.js" data-swup-ignore-script></script>
    <script src="/assets/js/notifiers.js" data-swup-ignore-script></script>
    <script src="/assets/js/server.js"></script>
    <script src="/assets/js/load_scripts.js"></script>
</body>
</html>
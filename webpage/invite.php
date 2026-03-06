<?php
include 'app/config.php';
include 'global.php';

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
    header('Location: /login');
    exit;
}
$stmt->close();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

$invite_id = null;
if (isset($segments[0]) && $segments[0] === 'invite' && !empty($segments[1])) {
    $invite_id = $segments[1]; 
}
$invite_expired = false;
$error_message = null;
if (!$invite_id) {
    $error_message = "Invite code is missing.";
} else {
    $inviteStmt = $mysqli->prepare("SELECT server_id, expires_at FROM invites WHERE code = ?");
    $inviteStmt->bind_param("s", $invite_id);
    $inviteStmt->execute();
    $inviteResult = $inviteStmt->get_result();

    if ($inviteResult->num_rows > 0) {
        $row = $inviteResult->fetch_assoc();
        $server_id = $row['server_id'];
        $expires_at = $row['expires_at'];

        if ($expires_at !== '0000-00-00 00:00:00') {
            $now = new DateTime();
            $expireDate = new DateTime($expires_at);
            if ($now > $expireDate) {
                $error_message = "This invite link has expired.";
                $invite_expired = true;
            }
        }
    } else {
        $error_message = "Invite code does not exist.";
    }
    $inviteStmt->close();
}

if ($server_id) {
    $stmt = $mysqli->prepare("SELECT name, image, created_at FROM servers WHERE id = ?");
    $stmt->bind_param("s", $server_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $error_message = "Server not found.";
    } else {
        $row = $result->fetch_assoc();
        $server_name = $row['name'];
        $server_image = $row['image'];
        $server_created_at = $row['created_at'];
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error_message) {
    $findUserInServerStmt = $mysqli->prepare("SELECT * FROM server_members WHERE server_id = ? AND user_id = ?");
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
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $theme ?> login">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>wokki chat</title>
    <link rel="stylesheet" href="/assets/styles/main.css" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <meta name="server_name" content="<?php echo htmlspecialchars($server_name) ?>">
    <meta name="server_image" content="<?php echo $server_image ?>">
    <meta name="server_created_at" content="<?php echo $server_created_at ?>">
    <?php if (isset($server_id)): ?>
        <meta name="server_id" content="<?php echo $server_id ?>">
    <?php endif; ?>
    <meta name="invite_expired" content="<?php echo $invite_expired ?>">
</head>
<body>
    <div class="login-form">
        <div class="login-form-content">
            <?php if ($error_message): ?>
                <h2>Error</h2>
                <p class="error-message" style="display: block;"><?= htmlspecialchars($error_message) ?></p>
                <p><a href="/home" class="link">Go back home</a></p>
            <?php else: ?>
                <h2>Join server</h2>
                <p>You have been invited to join <a href="/server/<?= htmlspecialchars($server_id) ?>" class="link"><?= htmlspecialchars($server_name) ?></a></p>
                <form action="" method="post">
                    <button type="submit" class="button-primary-filled">Join server</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="/assets/js/login.js"></script>
</body>
</html>

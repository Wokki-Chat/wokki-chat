<?php
include '../app/config.php';
include '../global.php';
$access_token = $_COOKIE['access_token'];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

$bot_id = null;
foreach ($segments as $key => $segment) {
    if ($segment === 'invite' && isset($segments[$key + 1]) && !empty($segments[$key + 1])) {
        $bot_id = $segments[$key + 1];
        break;
    }
}

if (!$bot_id) {
    header('Location: /home');
    exit;
}
if (!$access_token) {
    header('Location: /login?redirect=/bot/invite/' . $bot_id);
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
    header('Location: /login?redirect=/bot/invite/' . $bot_id);
    exit;
}

$stmt = $mysqli->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $username = $row['username'];
    $profile_picture = $row['profile_picture'];
}
$stmt->close();


$botNameStmt = $mysqli->prepare("SELECT name FROM bots WHERE id = ?");
$botNameStmt->bind_param("i", $bot_id);
$botNameStmt->execute();
$botNameResult = $botNameStmt->get_result();
$botName = $botNameResult->fetch_assoc()['name'];
$botNameStmt->close();

$userServers = [];

$stmt = $mysqli->prepare("SELECT id, name FROM servers WHERE created_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $userServers[$row['id']] = $row['name'];
    }
}
$stmt->close();

$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error_message) {
    if (isset($_POST['server_id'], $_POST['bot_id'])) {
        $server_id = $_POST['server_id'];
        $bot_id = $_POST['bot_id'];

        $checkServerStmt = $mysqli->prepare("SELECT id FROM servers WHERE id = ? AND created_by = ?");
        $checkServerStmt->bind_param("si", $server_id, $user_id);
        $checkServerStmt->execute();
        $serverResult = $checkServerStmt->get_result();
        if ($serverResult->num_rows === 0) {
            $error_message = "You don't own that server.";
            $checkServerStmt->close();
        } else {
            $checkServerStmt->close();

            $findBotStmt = $mysqli->prepare("SELECT * FROM server_members WHERE server_id = ? AND bot_id = ?");
            $findBotStmt->bind_param("ss", $server_id, $bot_id);
            $findBotStmt->execute();
            $findBotResult = $findBotStmt->get_result();

            $bot_found = false;
            if ($findBotResult->num_rows > 0) {
                $error_message = "This bot is already a member of the server.";
                $bot_found = true;
                $findBotStmt->close();
            } else {
                $findBotStmt->close();
            }

            if (!$bot_found) {
                $joined_at = date('Y-m-d H:i:s');
                $insertBotStmt = $mysqli->prepare("INSERT INTO server_members (server_id, bot_id, joined_at) VALUES (?, ?, ?)");
                $insertBotStmt->bind_param("sss", $server_id, $bot_id, $joined_at);
                $insertBotStmt->execute();
                $insertBotStmt->close();

                $roleStmt = $mysqli->prepare("SELECT role_id FROM server_roles WHERE add_on_join = 1 AND server_id = ?");
                $roleStmt->bind_param("s", $server_id);
                $roleStmt->execute();
                $roleResult = $roleStmt->get_result();

                $insertRoleStmt = $mysqli->prepare("INSERT INTO user_server_roles (bot_id, server_id, role_id) VALUES (?, ?, ?)");
                while ($row = $roleResult->fetch_assoc()) {
                    $insertRoleStmt->bind_param("sss", $bot_id, $server_id, $row['role_id']);
                    $insertRoleStmt->execute();
                }
                $insertRoleStmt->close();
                $roleStmt->close();

                header('Location: /server/' . $server_id);
                exit;
            }
        }
    } else {
        $error_message = "Missing server or bot ID.";
    }
}


?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?> login">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>wokki chat</title>
    <link rel="stylesheet" href="/assets/styles/main.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
</head>
<body>
    <div class="login-form">
        <div class="login-form-content">
            <?php if ($error_message): ?>
                <h2>Error</h2>
                <p class="error-message" style="display: block;"><?= htmlspecialchars($error_message) ?></p>
                <p><a href="/home" class="link">Go back home</a></p>
            <?php else: ?>
                <h2>Add bot to your server</h2>
                <p>Select a server to add <strong><?= htmlspecialchars($botName) ?></strong> to:</p>
                <?php if (empty($userServers)): ?>
                    <p>You don't have any servers yet.</p>
                <?php else: ?>
                    <form action="" method="post">
                        <input type="hidden" name="bot_id" value="<?= htmlspecialchars($bot_id) ?>">
                        <label for="server_id">Choose a server:</label><br>
                        <select name="server_id" id="server_id" class="input-text-dark-bg w270">
                            <?php foreach ($userServers as $serverId => $serverName): ?>
                                <option value="<?= htmlspecialchars($serverId) ?>">
                                    <?= htmlspecialchars($serverName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <br><br>
                        <button type="submit" class="button-primary-filled">Add bot</button>
                    </form>
                <?php endif; ?>


            <?php endif; ?>
        </div>
    </div>    
</body>
</html>

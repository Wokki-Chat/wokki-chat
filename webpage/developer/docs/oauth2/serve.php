<?php
include '../../../app/config.php';
include '../../../global.php';
$access_token = $_COOKIE['access_token'] ?? null;

if (empty($access_token)) {
    header('Location: /login?redirect=/developer/docs/oauth2');
    exit;
}

header("Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
    header('Location: /login?redirect=/developer/docs/oauth2');
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
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
	<meta charset="UTF-8">
	<title>Wokki Chat - OAuth 2.0 API Documentation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="/assets/styles/developer/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="/assets/images/logo-purple.png" alt="Wokki Chat Logo">
            <p>For Developers</p>
        </div>
        <div class="top-bar-profile" id="top-bar-profile">
            <img draggable="false" class="top-bar-profile-picture" src="<?php echo $profile_picture; ?>">
            <p class="top-bar-username"><?php echo htmlspecialchars($username); ?></p>
        </div>
    </div>
    <div class="content" id="content">
        <link rel="stylesheet" href="/assets/styles/developer/docsify.css">
        <div id="docsify">
        
        </div>
        <script>
            window.$docsify = {
                basePath: '/developer/oauth2-docs-raw/',
                loadSidebar: 'sidebar.md',
                executeScript: true,
                el: '#docsify',
                plugins: [
                    function(hook) {
                        function scrollToId() {
                            const hash = window.location.hash;
                            const id = new URLSearchParams(hash.split('?')[1] || '').get('id');
                            if (id) {
                                setTimeout(() => {
                                    const el = document.getElementById(id);
                                    if (el) {
                                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    }
                                }, 0);
                            }
                        }

                        hook.doneEach(function() {
                            scrollToId();
                        });

                        window.addEventListener('hashchange', function() {
                            scrollToId();
                        });
                    }
                ]
            };
        </script>
	    <script src="https://unpkg.com/docsify/lib/docsify.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', (event) => {
                document.querySelectorAll('pre code').forEach((block) => {
                    hljs.highlightElement(block);
                });
            });

            const observer = new MutationObserver(() => {
                document.querySelectorAll('pre code:not(.hljs)').forEach((block) => {
                    hljs.highlightElement(block);
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        </script>
        <wchat-allowed-scripts value="/developer/docs/oauth2.js;"></wchat-allowed-scripts>
        <wchat-data id="page" value="/developer/docs"></wchat-data>
    </div>
    <script src="/assets/js/load_scripts.js"></script>
</body>
</html>
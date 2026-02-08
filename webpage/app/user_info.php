<?php
include 'config.php';

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 0');
}

header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');
header('Connection: keep-alive');

function getPremiumStatus($user_id, $mysqli) {
    $stmt = $mysqli->prepare("SELECT premium, premium_expires_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $hasPremium = $user['premium'] == 1 && (
        is_null($user['premium_expires_at']) || strtotime($user['premium_expires_at']) > time()
    );

    return $hasPremium;
}

function getConnections($user_id, $mysqli) {
    $query = "SELECT * FROM user_connections WHERE user_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $connections = [];

    while ($row = $result->fetch_assoc()) {
        $connections[] = [
            "id" => $row["id"],
            "connection_type" => $row["connection_name"],
            "connection_name" => $row["connection_user_name"],
            "connection_user_url" => $row["connection_user_url"],
        ];
    }

    return $connections;
}

function getTags($user_id, $mysqli) {
    $query = "SELECT * FROM tags WHERE user_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tags = [];

    while ($row = $result->fetch_assoc()) {
        $tags[] = [
            "tag_icon" => $row["tag_icon"],
            "tag_name" => $row["tag_name"],
            "created_at" => $row["created_at"] ?? null,
        ];
    }

    return $tags;
}

function getUserWidgets($mysqli, $user_id, $widget_name = null) {
    $stmt = $mysqli->prepare("
        SELECT widget_name, widget_access_token, widget_refresh_token, 
               show_on_profile, widget_access_token_valid_until
        FROM profile_widgets
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $widgets = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($widgets)) {
        return [];
    }
    
    $stmt = $mysqli->prepare("
        SELECT connection_user_name, connection_name 
        FROM user_connections 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $connections = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $connection_map = [];
    foreach ($connections as $conn) {
        $connection_map[$conn['connection_name']] = $conn['connection_user_name'];
    }
    
    $widget_result = [];
    
    foreach ($widgets as $widget) {
        if ($widget_name && $widget['widget_name'] !== $widget_name) {
            continue;
        }
        
        if ($widget['widget_name'] === 'GitHub' && $widget['show_on_profile'] == 1) {
            try {
                $github_username = $connection_map['GitHub'] ?? null;
                $access_token = $widget['widget_access_token'] ?? null;
                
                if (!$github_username || !$access_token) {
                    continue;
                }
                
                $graphql_query = [
                    'query' => <<<GRAPHQL
                    {
                      user(login: "$github_username") {
                        contributionsCollection {
                          contributionCalendar {
                            totalContributions
                            weeks {
                              contributionDays {
                                date
                                contributionCount
                                color
                              }
                            }
                          }
                        }
                      }
                    }
                    GRAPHQL
                ];
                
                $ch = curl_init('https://api.github.com/graphql');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($graphql_query));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $access_token,
                    'Content-Type: application/json',
                    'User-Agent: PHP-App'
                ]);
                
                $response = curl_exec($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($status === 200) {
                    $widget_result['GitHub'] = json_decode($response, true);
                } else {
                    $widget_result['GitHub'] = ['error' => "GitHub API returned status $status"];
                }
            } catch (Exception $e) {
                $widget_result['GitHub'] = ['error' => $e->getMessage()];
            }
        } else {
            $widget_result[$widget['widget_name']] = true;
        }
    }
    
    return $widget_result;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $allowedOrigin = 'https://chat.wokki20.nl';

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        if ($_SERVER['HTTP_ORIGIN'] !== $allowedOrigin) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Origin',
                'return_code' => 5
            ]);
            exit;
        }
    } elseif (isset($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        if ($referer !== 'chat.wokki20.nl') {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'description' => 'Forbidden: Invalid Referer',
                'return_code' => 6
            ]);
            exit;
        }
    } else {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'description' => 'Forbidden: No Origin or Referer',
            'return_code' => 7
        ]);
        exit;
    }

    $headers = getallheaders();
    if (
        !isset($headers['Authorization']) ||
        !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)
    ) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'description' => 'Unauthorized: Missing or invalid Authorization header',
            'return_code' => 27
        ]);
        exit;
    }

    $access_token = $matches[1];

    $stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ?");
    $stmt->bind_param("s", $access_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row || !isset($row['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'description' => 'Unauthorized: Invalid access token',
            'return_code' => 26
        ]);
        exit;
    }

    $user_id = $row['user_id'];

    $requested_user_id = $_GET['user_id'] ?? null;

    $stmt = $mysqli->prepare("SELECT username, status, profile_picture, created_at, bio, profile_color_primary, profile_color_accent, nickname, profile_banner, is_developer, is_staff FROM users WHERE id = ?");
    $stmt->bind_param("i", $requested_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $premium = getPremiumStatus($requested_user_id, $mysqli);

    $user = [
        'id' => $requested_user_id,
        'username' => $row['username'],
        'status' => $row['status'],
        'profile_picture' => $row['profile_picture'],
        'created_at' => $row['created_at'],
        'bio' => $row['bio'],
        'profile_color_primary' => $premium ? $row['profile_color_primary'] : null,
        'profile_color_accent' => $premium ? $row['profile_color_accent'] : null,
        'display_name' => $row['nickname'],
        'profile_banner' => $row['profile_banner'],
        'premium' => $premium,
        'bot' => false,
        'tags' => getTags($requested_user_id, $mysqli),
        'staff' => $row['is_staff'] == 1,
        'developer' => $row['is_developer'] == 1,
        'connections' => getConnections($requested_user_id, $mysqli),
    ];

    echo json_encode([
        'status' => 'success',
        'description' => 'User info fetched successfully',
        'user' => $user,
        'return_code' => 30
    ]);
    echo "\n";
    ob_flush();
    flush();

    $widgets = getUserWidgets($mysqli, $requested_user_id);
    echo json_encode([
        'status' => 'success',
        'description' => 'Widgets fetched successfully',
        'widgets' => empty($widgets) ? new stdClass() : $widgets,
        'return_code' => 31
    ]);
    ob_flush();
    flush();
} else {
    echo json_encode([
        'status' => 'error',
        'description' => 'Invalid request method',
        'return_code' => 18
    ]);
}

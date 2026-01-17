<?php
header('Content-Type: application/json');

$workerName = $_GET['worker'] ?? '';
if (!$workerName) {
    echo json_encode(['error' => 'No worker specified']);
    exit;
}

$heartbeatDir = __DIR__ . '/../_private/heartbeats/';
$heartbeatFile = $heartbeatDir . "worker_$workerName.heartbeat";
$logFile = __DIR__ . '/../_private/logs/logs.txt';

$status = 'offline';
$lastHeartbeat = null;

if (file_exists($heartbeatFile)) {
    $timestamp = (int) file_get_contents($heartbeatFile);
    $lastHeartbeat = $timestamp;
    if (time() - $timestamp <= 10) {
        $status = 'online';
    }
}

$logs = [];
if (file_exists($logFile)) {
    $allLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $allLines = array_reverse($allLines);
    foreach ($allLines as $line) {
        if (strpos($line, "[Worker Name: $workerName]") !== false) {
            $logs[] = $line;
        }
        if (count($logs) >= 5) break;
    }
    $logs = array_reverse($logs);
}

echo json_encode([
    'worker' => $workerName,
    'status' => $status,
    'last_heartbeat' => $lastHeartbeat,
    'logs' => $logs
]);
?>

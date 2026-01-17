<?php
$workerName = $_GET['worker'] ?? '';
if (!$workerName) {
    die('No worker specified');
}

$heartbeatDir = __DIR__ . '/../_private/heartbeats/';
$heartbeatFile = $heartbeatDir . "worker_$workerName.heartbeat";

if (!file_exists($heartbeatFile)) {
    echo "$workerName is offline";
    exit;
}

$timestamp = (int) file_get_contents($heartbeatFile);
$now = time();

if ($now - $timestamp > 10) {
    echo "$workerName is offline";
} else {
    echo "$workerName is online";
}
?>

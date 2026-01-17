<?php
include 'config.php';

$maintenanceHtml = '';

$stmt = $mysqli->prepare("SELECT type, message FROM maintenance LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $message = $row['message'];
    $type = $row['type'];
    $stmt->close();
    $icon = $type == 'warning' ? 'warning' : 'construction';
    $maintenanceHtml = '
    <div class="maintenance '.$type.'">
        <span class="maintenance-type material-symbols-rounded">'.$icon.'</span>
        <p class="maintenance-message">'.$message.'</p>
        <span class="maintenance-close material-symbols-rounded" onclick="sessionStorage.setItem(\'maintenanceClosed\',\'1\'); this.parentElement.remove();">close</span>
    </div>
    <script>
    if(sessionStorage.getItem(\'maintenanceClosed\')) document.querySelector(\'.maintenance\')?.remove();
    </script>';
}
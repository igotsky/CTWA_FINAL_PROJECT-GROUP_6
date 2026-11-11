<?php
// admin/app_count.php - lightweight pending-application counter for polling
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: application/json');
$n = 0;
$res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM memberships WHERE status = 'Pending'");
if ($res) {
    $row = mysqli_fetch_assoc($res);
    $n = (int) ($row['c'] ?? 0);
    mysqli_free_result($res);
}
echo json_encode(['pending' => $n]);
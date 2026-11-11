<?php
// admin/app_count_org.php - lightweight pending-application counter per org for polling
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: application/json');
$orgId = (int) ($_GET['org_id'] ?? 0);
$n = 0;
if ($orgId > 0) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM memberships WHERE organization_id = $orgId AND status = 'Pending'");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $n = (int) ($row['c'] ?? 0);
        mysqli_free_result($res);
    }
}
echo json_encode(['pending' => $n]);
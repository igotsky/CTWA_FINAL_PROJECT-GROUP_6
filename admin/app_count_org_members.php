<?php
// admin/app_count_org_members.php - latest member join timestamp per org for polling
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: application/json');
$orgId = (int) ($_GET['org_id'] ?? 0);
$result = ['latest_join' => 0, 'member_count' => 0];
if ($orgId > 0) {
    $res = mysqli_query($conn, "SELECT MAX(UNIX_TIMESTAMP(applied_at)) AS latest_join, COUNT(*) AS member_count FROM memberships WHERE organization_id = $orgId AND status = 'Approved'");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $result['latest_join'] = (int) ($row['latest_join'] ?? 0);
        $result['member_count'] = (int) ($row['member_count'] ?? 0);
        mysqli_free_result($res);
    }
}
echo json_encode($result);
<?php
// admin/app_count_student.php - total pending applications across all orgs where student is moderator
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: application/json');
$studentId = (int) ($_GET['student_id'] ?? 0);
$n = 0;
if ($studentId > 0) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM memberships m WHERE m.student_id = $studentId AND m.status = 'Approved' AND m.role = 'Moderator' AND EXISTS (SELECT 1 FROM memberships m2 WHERE m2.organization_id = m.organization_id AND m2.status = 'Pending')");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        $n = (int) ($row['c'] ?? 0);
        mysqli_free_result($res);
    }
}
echo json_encode(['pending' => $n]);
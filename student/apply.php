<?php
// student/apply.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireStudent();

$cu = currentUser();

// Get student id
$studentId = 0;
$stmt = mysqli_prepare($conn, 'SELECT id FROM students WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
if ($row) { $studentId = (int) $row['id']; }
mysqli_stmt_close($stmt);

$redirectBase = BASE_URL . 'student/organizations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectBase);
    exit;
}

$orgId = (int) ($_POST['organization_id'] ?? 0);
if ($studentId <= 0 || $orgId <= 0) {
    header('Location: ' . $redirectBase . '?apply_error=' . urlencode('Invalid request.'));
    exit;
}

// Check duplicate pending/approved
$stmt = mysqli_prepare($conn, "SELECT id FROM memberships WHERE student_id = ? AND organization_id = ? AND status IN ('Pending','Approved')");
mysqli_stmt_bind_param($stmt, 'ii', $studentId, $orgId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$duplicate = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($duplicate) {
    header('Location: ' . $redirectBase . '?apply_error=' . urlencode('You already have a pending or approved application for this organization.'));
    exit;
}

$stmt = mysqli_prepare($conn, 'INSERT INTO memberships (student_id, organization_id, status) VALUES (?, ?, "Pending")');
mysqli_stmt_bind_param($stmt, 'ii', $studentId, $orgId);
if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header('Location: ' . $redirectBase . '?applied=1');
    exit;
}
mysqli_stmt_close($stmt);
header('Location: ' . $redirectBase . '?apply_error=' . urlencode('Failed to submit application.'));
exit;

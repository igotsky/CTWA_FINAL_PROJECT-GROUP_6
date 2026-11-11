<?php
// student/apply.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';
requireStudent();

$cu = currentUser();

// Get student row
$student = null;
$stmt = mysqli_prepare($conn, 'SELECT id, course, year_level, gender, section FROM students WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$studentId = $student ? (int)$student['id'] : 0;

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

// Fetch org
$orgStmt = mysqli_prepare($conn, 'SELECT id, name, min_year_level, max_year_level, allowed_gender, max_members, application_deadline, auto_approve FROM organizations WHERE id = ?');
mysqli_stmt_bind_param($orgStmt, 'i', $orgId);
mysqli_stmt_execute($orgStmt);
$orgRes = mysqli_stmt_get_result($orgStmt);
$org = mysqli_fetch_assoc($orgRes);
mysqli_stmt_close($orgStmt);

if (!$org) {
    header('Location: ' . $redirectBase . '?apply_error=' . urlencode('Organization not found.'));
    exit;
}

// Eligibility check
$reasons = checkEligibility($conn, $student, $org);
if (!empty($reasons)) {
    header('Location: ' . $redirectBase . '?apply_error=' . urlencode('Not eligible: ' . implode('; ', $reasons)));
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

// Determine status
$status = 'Pending';
if ((int)$org['auto_approve'] === 1) {
    $approved = countApprovedMembers($conn, $orgId);
    if ($org['max_members'] === null || $approved < (int)$org['max_members']) {
        $status = 'Approved';
    }
}

try {
    $stmt = mysqli_prepare($conn, 'INSERT INTO memberships (student_id, organization_id, status) VALUES (?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'iis', $studentId, $orgId, $status);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        $msg = $status === 'Approved' ? 'Application submitted and auto-approved.' : 'Application submitted successfully. Status: Pending.';
        header('Location: ' . $redirectBase . '?applied=1&msg=' . urlencode($msg));
        exit;
    }
    mysqli_stmt_close($stmt);
    header('Location: ' . $redirectBase . '?apply_error=' . urlencode('Failed to submit application.'));
    exit;
} catch (mysqli_sql_exception $e) {
    header('Location: ' . $redirectBase . '?apply_error=' . urlencode('Failed to submit application.'));
    exit;
}

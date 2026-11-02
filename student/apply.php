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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orgId = (int) ($_POST['organization_id'] ?? 0);
    if ($studentId <= 0 || $orgId <= 0) {
        $error = 'Invalid request.';
    } else {
        // Check duplicate pending/approved
        $stmt = mysqli_prepare($conn, "SELECT id FROM memberships WHERE student_id = ? AND organization_id = ? AND status IN ('Pending','Approved')");
        mysqli_stmt_bind_param($stmt, 'ii', $studentId, $orgId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = 'You already have a pending or approved application for this organization.';
        }
        mysqli_stmt_close($stmt);

        if ($error === '') {
            $stmt = mysqli_prepare($conn, 'INSERT INTO memberships (student_id, organization_id, status) VALUES (?, ?, "Pending")');
            mysqli_stmt_bind_param($stmt, 'ii', $studentId, $orgId);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Application submitted successfully. Status: Pending.';
            } else {
                $error = 'Failed to submit application.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Redirect-ish: show result page
$pageTitle = 'Apply for Membership';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Apply for Membership</h1>
    <?php if ($message !== ''): ?>
        <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
        <p><a href="<?php echo BASE_URL; ?>student/my_applications.php">View My Applications</a></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <p><a href="<?php echo BASE_URL; ?>student/organizations.php">Back to Organizations</a></p>
    <?php endif; ?>
    <?php if ($message === '' && $error === ''): ?>
        <p>Invalid request.</p>
        <p><a href="<?php echo BASE_URL; ?>student/organizations.php">Back to Organizations</a></p>
    <?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

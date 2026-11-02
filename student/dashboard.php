<?php
// student/dashboard.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireStudent();

$cu = currentUser();
// Get student row
$student = null;
$stmt = mysqli_prepare($conn, 'SELECT id, first_name, last_name FROM students WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// Counts
$myApps = 0;
$joined = 0;
if ($student) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM memberships WHERE student_id = " . (int)$student['id']);
    if ($r) { $myApps = (int) mysqli_fetch_assoc($r)['c']; mysqli_free_result($r); }
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM memberships WHERE student_id = " . (int)$student['id'] . " AND status = 'Approved'");
    if ($r) { $joined = (int) mysqli_fetch_assoc($r)['c']; mysqli_free_result($r); }
}

$pageTitle = 'Student Dashboard';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Welcome, <?php echo htmlspecialchars($cu['username']); ?></h1>
    <div class="stats">
        <div class="stat"><div class="num"><?php echo $myApps; ?></div><div>My Applications</div></div>
        <div class="stat"><div class="num"><?php echo $joined; ?></div><div>Joined Organizations</div></div>
    </div>
    <div class="card">
        <p>
            <a href="<?php echo BASE_URL; ?>student/organizations.php">Browse Organizations</a> |
            <a href="<?php echo BASE_URL; ?>student/my_applications.php">My Applications</a> |
            <a href="<?php echo BASE_URL; ?>student/joined.php">Joined Organizations</a> |
            <a href="<?php echo BASE_URL; ?>student/profile.php">Update Profile</a>
        </p>
    </div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

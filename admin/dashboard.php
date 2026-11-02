<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Counts
$studentCount = 0;
$orgCount = 0;
$appCount = 0;
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

$r = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM students');
if ($r) { $studentCount = (int) mysqli_fetch_assoc($r)['c']; mysqli_free_result($r); }
$r = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM organizations');
if ($r) { $orgCount = (int) mysqli_fetch_assoc($r)['c']; mysqli_free_result($r); }
$r = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM memberships');
if ($r) { $appCount = (int) mysqli_fetch_assoc($r)['c']; mysqli_free_result($r); }
$r = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM memberships GROUP BY status");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        if ($row['status'] === 'Pending') $pendingCount = (int) $row['c'];
        if ($row['status'] === 'Approved') $approvedCount = (int) $row['c'];
        if ($row['status'] === 'Rejected') $rejectedCount = (int) $row['c'];
    }
    mysqli_free_result($r);
}

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Admin Dashboard</h1>
    <div class="stats">
        <div class="stat"><div class="num"><?php echo $studentCount; ?></div><div>Students</div></div>
        <div class="stat"><div class="num"><?php echo $orgCount; ?></div><div>Organizations</div></div>
        <div class="stat"><div class="num"><?php echo $appCount; ?></div><div>Applications</div></div>
        <div class="stat"><div class="num"><?php echo $pendingCount; ?></div><div>Pending</div></div>
        <div class="stat"><div class="num"><?php echo $approvedCount; ?></div><div>Approved</div></div>
        <div class="stat"><div class="num"><?php echo $rejectedCount; ?></div><div>Rejected</div></div>
    </div>
    <div class="card">
        <h3>Quick Links</h3>
        <p>
            <a href="<?php echo BASE_URL; ?>admin/organizations.php">Manage Organizations</a> |
            <a href="<?php echo BASE_URL; ?>admin/applications.php">Review Applications</a> |
            <a href="<?php echo BASE_URL; ?>admin/student_records.php">Student Records</a>
        </p>
    </div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
// student/dashboard.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireStudent();

$cu = currentUser();
$student = null;
$stmt = mysqli_prepare($conn, 'SELECT id, first_name, last_name, course, year_level FROM students WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$myApps = 0;
$joined = 0;
$pending = 0;
$recent = [];
if ($student) {
    $sid = (int) $student['id'];
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM memberships WHERE student_id = $sid");
    if ($r) { $myApps = (int) mysqli_fetch_assoc($r)['c']; mysqli_free_result($r); }
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM memberships WHERE student_id = $sid AND status = 'Approved'");
    if ($r) { $joined = (int) mysqli_fetch_assoc($r)['c']; mysqli_free_result($r); }
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM memberships WHERE student_id = $sid AND status = 'Pending'");
    if ($r) { $pending = (int) mysqli_fetch_assoc($r)['c']; mysqli_free_result($r); }

    $stmt = mysqli_prepare($conn, "SELECT m.id, m.status, m.applied_at, o.name AS org_name
            FROM memberships m JOIN organizations o ON m.organization_id = o.id
            WHERE m.student_id = ? ORDER BY m.applied_at DESC LIMIT 5");
    mysqli_stmt_bind_param($stmt, 'i', $sid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $recent[] = $row;
    mysqli_stmt_close($stmt);
}

$pageTitle = 'Student Dashboard';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Welcome, <?php echo htmlspecialchars($student ? $student['first_name'] : $cu['username']); ?></h1>
    <?php if ($student): ?>
        <p class="dashboard-sub"><?php echo htmlspecialchars($student['course'] . ' — Year ' . $student['year_level']); ?></p>
    <?php endif; ?>

    <div class="stats">
        <div class="stat stat-info">
            <div class="stat-head"><span class="stat-icon"><i data-lucide="send"></i></span></div>
            <div class="num"><?php echo $myApps; ?></div>
            <div class="label">My Applications</div>
        </div>
        <div class="stat stat-ok">
            <div class="stat-head"><span class="stat-icon"><i data-lucide="users"></i></span></div>
            <div class="num"><?php echo $joined; ?></div>
            <div class="label">Joined Organizations</div>
        </div>
        <div class="stat stat-warn">
            <div class="stat-head"><span class="stat-icon"><i data-lucide="clock"></i></span></div>
            <div class="num"><?php echo $pending; ?></div>
            <div class="label">Pending</div>
        </div>
    </div>

    <div class="quick-actions">
        <a class="quick-action" href="<?php echo BASE_URL; ?>student/organizations.php"><i data-lucide="building-2"></i>Browse Organizations</a>
        <a class="quick-action" href="<?php echo BASE_URL; ?>student/my_applications.php"><i data-lucide="clipboard-list"></i>My Applications</a>
        <a class="quick-action" href="<?php echo BASE_URL; ?>student/joined.php"><i data-lucide="users"></i>Joined Organizations</a>
        <a class="quick-action" href="<?php echo BASE_URL; ?>student/profile.php"><i data-lucide="user"></i>Update Profile</a>
    </div>

    <div class="dashboard-section">
        <div class="section-head">
            <h2>Recent Applications</h2>
            <a href="<?php echo BASE_URL; ?>student/my_applications.php">View all →</a>
        </div>
        <?php if (empty($recent)): ?>
            <div class="empty-state">You haven't applied to any organizations yet. <a href="<?php echo BASE_URL; ?>student/organizations.php">Browse organizations</a>.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Organization</th><th>Status</th><th>Applied</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $a): ?>
                <tr>
                    <td><?php echo htmlspecialchars($a['org_name']); ?></td>
                    <td><span class="badge <?php echo strtolower($a['status']); ?>"><?php echo htmlspecialchars($a['status']); ?></span></td>
                    <td><?php echo htmlspecialchars($a['applied_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
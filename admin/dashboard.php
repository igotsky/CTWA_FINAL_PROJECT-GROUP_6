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

$recent = [];
$r = mysqli_query($conn, "SELECT m.id, m.status, m.applied_at, s.first_name, s.last_name, o.name AS org_name
        FROM memberships m JOIN students s ON m.student_id = s.id JOIN organizations o ON m.organization_id = o.id
        ORDER BY m.applied_at DESC LIMIT 5");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $recent[] = $row; mysqli_free_result($r); }

$cu = currentUser();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Admin Dashboard</h1>
    <p class="dashboard-sub">Welcome back, <?php echo htmlspecialchars($cu['username']); ?>. Here's an overview of the system.</p>

    <div class="stats">
        <div class="stat stat-info">
            <div class="stat-head"><span class="stat-icon"><i data-lucide="users"></i></span></div>
            <div class="num"><?php echo $studentCount; ?></div>
            <div class="label">Students</div>
        </div>
        <div class="stat stat-info">
            <div class="stat-head"><span class="stat-icon"><i data-lucide="building-2"></i></span></div>
            <div class="num"><?php echo $orgCount; ?></div>
            <div class="label">Organizations</div>
        </div>
        <div class="stat">
            <div class="stat-head"><span class="stat-icon"><i data-lucide="clipboard-list"></i></span></div>
            <div class="num"><?php echo $appCount; ?></div>
            <div class="label">Applications</div>
        </div>
        <div class="stat stat-warn">
            <div class="stat-head"><span class="stat-icon"><i data-lucide="clock"></i></span></div>
            <div class="num"><?php echo $pendingCount; ?></div>
            <div class="label">Pending</div>
        </div>
        <div class="stat stat-ok">
            <div class="stat-head"><span class="stat-icon"><i data-lucide="check-circle"></i></span></div>
            <div class="num"><?php echo $approvedCount; ?></div>
            <div class="label">Approved</div>
        </div>
        <div class="stat stat-bad">
            <div class="stat-head"><span class="stat-icon"><i data-lucide="x-circle"></i></span></div>
            <div class="num"><?php echo $rejectedCount; ?></div>
            <div class="label">Rejected</div>
        </div>
    </div>

    <?php if ($appCount > 0): ?>
    <div class="breakdown">
        <div class="breakdown-label">Application breakdown</div>
        <div class="bar">
            <?php
            $pPct = $appCount ? round($pendingCount / $appCount * 100) : 0;
            $aPct = $appCount ? round($approvedCount / $appCount * 100) : 0;
            $rPct = $appCount ? round($rejectedCount / $appCount * 100) : 0;
            ?>
            <span class="seg-pending" style="width: <?php echo $pPct; ?>%"></span>
            <span class="seg-approved" style="width: <?php echo $aPct; ?>%"></span>
            <span class="seg-rejected" style="width: <?php echo $rPct; ?>%"></span>
        </div>
        <div class="legend">
            <span><i style="background:#f59e0b"></i>Pending <?php echo $pendingCount; ?></span>
            <span><i style="background:#16a34a"></i>Approved <?php echo $approvedCount; ?></span>
            <span><i style="background:#dc2626"></i>Rejected <?php echo $rejectedCount; ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="quick-actions">
        <a class="quick-action" href="<?php echo BASE_URL; ?>admin/organizations.php"><i data-lucide="building-2"></i>Manage Organizations</a>
        <a class="quick-action" href="<?php echo BASE_URL; ?>admin/applications.php"><i data-lucide="clipboard-list"></i>Review Applications</a>
        <a class="quick-action" href="<?php echo BASE_URL; ?>admin/student_records.php"><i data-lucide="users"></i>Student Records</a>
    </div>

    <div class="dashboard-section">
        <div class="section-head">
            <h2>Recent Applications</h2>
            <a href="<?php echo BASE_URL; ?>admin/applications.php">View all →</a>
        </div>
        <?php if (empty($recent)): ?>
            <div class="empty-state">No applications submitted yet.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Student</th><th>Organization</th><th>Status</th><th>Applied</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $a): ?>
                <tr>
                    <td><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></td>
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
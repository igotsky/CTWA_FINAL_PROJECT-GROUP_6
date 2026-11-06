<?php
// student/my_applications.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireStudent();

$cu = currentUser();
$studentId = 0;
$stmt = mysqli_prepare($conn, 'SELECT id FROM students WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
if ($row) { $studentId = (int) $row['id']; }
mysqli_stmt_close($stmt);

$apps = [];
if ($studentId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT m.id, m.applied_at, m.status, o.name AS org_name, o.description
        FROM memberships m JOIN organizations o ON m.organization_id = o.id
        WHERE m.student_id = ? ORDER BY m.applied_at DESC");
    mysqli_stmt_bind_param($stmt, 'i', $studentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $apps[] = $r;
    }
    mysqli_stmt_close($stmt);
}

$pageTitle = 'My Applications';
include __DIR__ . '/../includes/header.php';
?>
    <h1>My Applications</h1>
    <table>
        <thead><tr><th>Organization</th><th>Description</th><th>Applied</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($apps as $a): ?>
            <tr>
                <td><?php echo htmlspecialchars($a['org_name']); ?></td>
                <td><?php echo htmlspecialchars($a['description']); ?></td>
                <td><?php echo htmlspecialchars($a['applied_at']); ?></td>
                <td><span class="badge <?php echo strtolower($a['status']); ?>"><?php echo htmlspecialchars($a['status']); ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($apps)): ?>
            <tr><td colspan="4">No applications yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
// student/joined.php
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

$joined = [];
if ($studentId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT o.name, o.description, m.applied_at
        FROM memberships m JOIN organizations o ON m.organization_id = o.id
        WHERE m.student_id = ? AND m.status = 'Approved' ORDER BY o.name");
    mysqli_stmt_bind_param($stmt, 'i', $studentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $joined[] = $r;
    }
    mysqli_stmt_close($stmt);
}

$pageTitle = 'Joined Organizations';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Joined Organizations</h1>
    <?php if (empty($joined)): ?>
        <p>You have not joined any organizations yet.</p>
    <?php else: ?>
        <?php foreach ($joined as $j): ?>
            <div class="card">
                <h3><?php echo htmlspecialchars($j['name']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($j['description'])); ?></p>
                <small>Approved on: <?php echo htmlspecialchars($j['applied_at']); ?></small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

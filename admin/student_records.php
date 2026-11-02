<?php
// admin/student_records.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$search = trim($_GET['q'] ?? '');

$sql = "SELECT id, student_number, first_name, middle_name, last_name, course, year_level, email, created_at
        FROM students";
$params = [];
$types = '';
if ($search !== '') {
    $sql .= " WHERE student_number LIKE ? OR first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name,' ',last_name) LIKE ?";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like];
    $types = 'sssss';
}
$sql .= ' ORDER BY last_name, first_name';

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$students = [];
while ($row = mysqli_fetch_assoc($res)) {
    $students[] = $row;
}
mysqli_stmt_close($stmt);

// For each student, fetch memberships
foreach ($students as &$st) {
    $st['memberships'] = [];
    $mStmt = mysqli_prepare($conn, "SELECT m.status, m.applied_at, o.name AS org_name
        FROM memberships m JOIN organizations o ON m.organization_id = o.id
        WHERE m.student_id = ? ORDER BY m.applied_at DESC");
    mysqli_stmt_bind_param($mStmt, 'i', $st['id']);
    mysqli_stmt_execute($mStmt);
    $mRes = mysqli_stmt_get_result($mStmt);
    while ($mr = mysqli_fetch_assoc($mRes)) {
        $st['memberships'][] = $mr;
    }
    mysqli_stmt_close($mStmt);
}
unset($st);

$pageTitle = 'Student Records';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Student Records</h1>
    <form method="get" action="">
        <input type="text" name="q" placeholder="Search by name or student number" value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
        <a href="<?php echo BASE_URL; ?>admin/student_records.php">Reset</a>
    </form>
    <br>

    <table>
        <thead><tr><th>Student No.</th><th>Name</th><th>Course/Year</th><th>Email</th><th>Memberships</th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><?php echo htmlspecialchars($s['student_number']); ?></td>
                <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['middle_name'] . ' ' . $s['last_name']); ?></td>
                <td><?php echo htmlspecialchars($s['course'] . ' - ' . $s['year_level']); ?></td>
                <td><?php echo htmlspecialchars($s['email']); ?></td>
                <td>
                    <?php if (empty($s['memberships'])): ?>
                        None
                    <?php else: ?>
                        <?php foreach ($s['memberships'] as $m): ?>
                            <?php echo htmlspecialchars($m['org_name'] . ' (' . $m['status'] . ')'); ?><br>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
            <tr><td colspan="5">No students found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
<?php include __DIR__ . '/../includes/footer.php'; ?>

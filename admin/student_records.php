<?php
// admin/student_records.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, 'SELECT user_id FROM students WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res);
                mysqli_stmt_close($stmt);
                $userId = $row ? (int) $row['user_id'] : 0;

                $stmt = mysqli_prepare($conn, 'DELETE FROM memberships WHERE student_id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare($conn, 'DELETE FROM students WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                if ($userId > 0) {
                    $stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
                    mysqli_stmt_bind_param($stmt, 'i', $userId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }

                mysqli_commit($conn);
                $message = 'Student record deleted.';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = 'Failed to delete student record.';
            }
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $studentNumber = trim($_POST['student_number'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $yearLevel = (int) ($_POST['year_level'] ?? 0);
        $email = trim($_POST['email'] ?? '');

        if ($id <= 0 || $studentNumber === '' || $firstName === '' || $lastName === '' || $course === '' || $email === '' || $yearLevel < 1 || $yearLevel > 6) {
            $error = 'Please fill in all required fields.';
        } else {
            // Check for duplicate student number (excluding the current record)
            $dupStmt = mysqli_prepare($conn, 'SELECT id FROM students WHERE student_number = ? AND id <> ?');
            mysqli_stmt_bind_param($dupStmt, 'si', $studentNumber, $id);
            mysqli_stmt_execute($dupStmt);
            $dupRes = mysqli_stmt_get_result($dupStmt);
            $dupRow = mysqli_fetch_assoc($dupRes);
            mysqli_stmt_close($dupStmt);

            if ($dupRow) {
                $error = 'Student number already exists. Please use a different one.';
            } else {
                $stmt = mysqli_prepare($conn, 'UPDATE students SET student_number = ?, first_name = ?, middle_name = ?, last_name = ?, course = ?, year_level = ?, email = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'sssssisi', $studentNumber, $firstName, $middleName, $lastName, $course, $yearLevel, $email, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Student record updated.';
                } else {
                    $error = 'Failed to update student record.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Edit mode
$editStudent = null;
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, 'SELECT s.id, s.user_id, s.student_number, s.first_name, s.middle_name, s.last_name, s.course, s.year_level, s.email, u.username FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $editStudent = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

$search = trim($_GET['q'] ?? '');

$sql = "SELECT id, student_number, first_name, middle_name, last_name, course, year_level, email, created_at, user_id
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
    <?php if ($message !== ''): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if ($editStudent): ?>
    <div class="card">
        <h3>Edit Student: <?php echo htmlspecialchars($editStudent['username']); ?></h3>
        <form method="post" action="">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo (int) $editStudent['id']; ?>">
            <label>Student Number *</label>
            <input type="text" name="student_number" required value="<?php echo htmlspecialchars($editStudent['student_number']); ?>">
            <label>First Name *</label>
            <input type="text" name="first_name" required value="<?php echo htmlspecialchars($editStudent['first_name']); ?>">
            <label>Middle Name</label>
            <input type="text" name="middle_name" value="<?php echo htmlspecialchars($editStudent['middle_name']); ?>">
            <label>Last Name *</label>
            <input type="text" name="last_name" required value="<?php echo htmlspecialchars($editStudent['last_name']); ?>">
            <label>Course *</label>
            <input type="text" name="course" required value="<?php echo htmlspecialchars($editStudent['course']); ?>">
            <label>Year Level *</label>
            <select name="year_level" required>
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo (int)$editStudent['year_level'] === $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
            <label>Email *</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($editStudent['email']); ?>">
            <br>
            <button type="submit">Save Changes</button>
            <a href="<?php echo BASE_URL; ?>admin/student_records.php" class="btn btn-secondary" style="margin-left:10px;">Cancel</a>
        </form>
    </div>
    <?php endif; ?>

    <form method="get" action="">
        <input type="text" name="q" placeholder="Search by name or student number" value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
        <a href="<?php echo BASE_URL; ?>admin/student_records.php" class="btn btn-secondary">Reset</a>
    </form>
    <br>

    <table>
        <thead><tr><th>Student No.</th><th>Name</th><th>Course/Year</th><th>Email</th><th>Memberships</th><th>Actions</th></tr></thead>
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
                            <?php echo htmlspecialchars($m['org_name']); ?> <span class="badge <?php echo strtolower($m['status']); ?>"><?php echo htmlspecialchars($m['status']); ?></span><br>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo BASE_URL; ?>admin/student_records.php?edit=<?php echo (int) $s['id']; ?>">Edit</a>
                    <form method="post" action="" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                        <button type="button" class="btn-danger" onclick="confirmDelete(this.form, 'Delete this student record and their memberships?');">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
            <tr><td colspan="6">No students found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
<script>
  function confirmDelete(form, text) {
    showConfirm(text, function() { form.submit(); });
  }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

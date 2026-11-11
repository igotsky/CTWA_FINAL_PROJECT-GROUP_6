<?php
// admin/courses.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        if ($name === '') {
            $error = 'Course name is required.';
        } else {
            $codeVal = ($code === '') ? null : $code;
            try {
                $stmt = mysqli_prepare($conn, 'INSERT INTO courses (name, code) VALUES (?, ?)');
                mysqli_stmt_bind_param($stmt, 'ss', $name, $codeVal);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Course added.';
                } else {
                    $error = 'Failed to add course (name may already exist).';
                }
                mysqli_stmt_close($stmt);
            } catch (mysqli_sql_exception $e) {
                $error = 'Failed to add course (name may already exist).';
            }
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        if ($id <= 0 || $name === '') {
            $error = 'Invalid data.';
        } else {
            $codeVal = ($code === '') ? null : $code;
            try {
                $stmt = mysqli_prepare($conn, 'UPDATE courses SET name = ?, code = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'ssi', $name, $codeVal, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Course updated.';
                } else {
                    $error = 'Failed to update course.';
                }
                mysqli_stmt_close($stmt);
            } catch (mysqli_sql_exception $e) {
                $error = 'Failed to update course (name may already exist).';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            // Fetch the course name
            $nameStmt = mysqli_prepare($conn, 'SELECT name FROM courses WHERE id = ?');
            mysqli_stmt_bind_param($nameStmt, 'i', $id);
            mysqli_stmt_execute($nameStmt);
            $nameRes = mysqli_stmt_get_result($nameStmt);
            $nameRow = mysqli_fetch_assoc($nameRes);
            mysqli_stmt_close($nameStmt);
            $courseName = $nameRow ? $nameRow['name'] : null;

            mysqli_begin_transaction($conn);
            try {
                if ($courseName !== null) {
                    $clearStmt = mysqli_prepare($conn, 'UPDATE students SET course = NULL WHERE course = ?');
                    mysqli_stmt_bind_param($clearStmt, 's', $courseName);
                    mysqli_stmt_execute($clearStmt);
                    mysqli_stmt_close($clearStmt);
                }
                $stmt = mysqli_prepare($conn, 'DELETE FROM courses WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Course deleted. Affected students had their course cleared.';
                } else {
                    $error = 'Failed to delete course.';
                }
                mysqli_stmt_close($stmt);
                mysqli_commit($conn);
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = 'Failed to delete course.';
            }
        }
    }
}

$editCourse = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, 'SELECT id, name, code FROM courses WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $editCourse = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

$courses = [];
// Ensure table exists (self-healing)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    code VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$res = mysqli_query($conn, "SELECT c.id, c.name, c.code, c.created_at,
        (SELECT COUNT(*) FROM students s WHERE s.course = c.name) AS student_count
        FROM courses c ORDER BY c.name");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) { $courses[] = $row; }
    mysqli_free_result($res);
}

$pageTitle = 'Manage Courses';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Manage Courses</h1>
    <?php if ($message !== ''): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <h3><?php echo $editCourse ? 'Edit Course' : 'Add Course'; ?></h3>
        <form method="post" action="">
            <input type="hidden" name="action" value="<?php echo $editCourse ? 'update' : 'add'; ?>">
            <?php if ($editCourse): ?>
                <input type="hidden" name="id" value="<?php echo (int) $editCourse['id']; ?>">
            <?php endif; ?>
            <label>Name *</label><br>
            <input type="text" name="name" required value="<?php echo $editCourse ? htmlspecialchars($editCourse['name']) : ''; ?>"><br>
            <label>Code (optional)</label><br>
            <input type="text" name="code" value="<?php echo $editCourse ? htmlspecialchars($editCourse['code'] ?? '') : ''; ?>"><br>
            <input type="submit" value="<?php echo $editCourse ? 'Update' : 'Add'; ?>">
            <?php if ($editCourse): ?>
                <a href="<?php echo BASE_URL; ?>admin/courses.php" style="margin-left:10px;">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <h2>Course List</h2>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Code</th><th>Students</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
            <tr>
                <td><?php echo (int) $c['id']; ?></td>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td><?php echo htmlspecialchars($c['code'] ?? ''); ?></td>
                <td><?php echo (int) $c['student_count']; ?></td>
                <td>
                    <a href="<?php echo BASE_URL; ?>admin/courses.php?edit=<?php echo (int) $c['id']; ?>">Edit</a>
                    <form method="post" action="" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                        <button type="button" class="btn-danger" onclick="confirmDelete(this.form, 'Delete this course?', 'Delete');">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($courses)): ?>
            <tr><td colspan="5">No courses yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
<script>
  function confirmDelete(form, text, label) { showConfirm(text, function() { form.submit(); }, label); }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
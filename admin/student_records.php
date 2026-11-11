<?php
// admin/student_records.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$error = '';
$action = '';
$hadFlash = false;

if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
    $hadFlash = true;
} elseif (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = trim($_GET['msg']);
    $hadFlash = true;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $studentNumber = trim($_POST['student_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $gender = $_POST['gender'] ?? null;
        if (!in_array($gender, ['Male','Female'], true)) $gender = null;
        $course = trim($_POST['course'] ?? '');
        $yearLevel = (int) ($_POST['year_level'] ?? 0);
        $email = trim($_POST['email'] ?? '');

        if ($studentNumber === '' || $password === '' || $firstName === '' || $lastName === '' || $course === '' || $email === '' || $yearLevel < 1 || $yearLevel > 4) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
            $error = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            // check duplicate student number / username
            $dupStmt = mysqli_prepare($conn, 'SELECT id FROM students WHERE student_number = ?');
            mysqli_stmt_bind_param($dupStmt, 's', $studentNumber);
            mysqli_stmt_execute($dupStmt);
            mysqli_stmt_store_result($dupStmt);
            $dup = mysqli_stmt_num_rows($dupStmt) > 0;
            mysqli_stmt_close($dupStmt);

            $uDupStmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ?');
            mysqli_stmt_bind_param($uDupStmt, 's', $studentNumber);
            mysqli_stmt_execute($uDupStmt);
            mysqli_stmt_store_result($uDupStmt);
            $uDup = mysqli_stmt_num_rows($uDupStmt) > 0;
            mysqli_stmt_close($uDupStmt);

            if ($dup || $uDup) {
                $error = 'Student number already exists.';
            } else {
                mysqli_begin_transaction($conn);
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'student';
                    $stmt = mysqli_prepare($conn, 'INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'sss', $studentNumber, $hash, $role);
                    mysqli_stmt_execute($stmt);
                    $userId = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);

                    $stmt = mysqli_prepare($conn, 'INSERT INTO students (user_id, student_number, first_name, middle_name, last_name, gender, course, year_level, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'issssssis', $userId, $studentNumber, $firstName, $middleName, $lastName, $gender, $course, $yearLevel, $email);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    mysqli_commit($conn);
                    $message = 'Student added successfully.';
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = 'Failed to add student.';
                }
            }
        }
    } elseif ($action === 'delete') {
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
    } elseif ($action === 'reg_reject') {
        $id = (int) ($_POST['id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE registration_requests SET status = 'Rejected', processed_at = NOW(), rejection_reason = ? WHERE id = ? AND status = 'Pending'");
            mysqli_stmt_bind_param($stmt, 'si', $reason, $id);
            if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                $message = 'Registration rejected.';
            } else {
                $error = 'Failed to reject (may already be processed).';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'reg_approve') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE registration_requests SET status = 'Approved', processed_at = NOW() WHERE id = ? AND status = 'Pending'");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                $message = 'Registration approved.';
            } else {
                $error = 'Failed to approve (may already be processed).';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $studentNumber = trim($_POST['student_number'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $gender = $_POST['gender'] ?? null;
        if (!in_array($gender, ['Male','Female'], true)) $gender = null;
        $course = trim($_POST['course'] ?? '');
        $yearLevel = (int) ($_POST['year_level'] ?? 0);
        $email = trim($_POST['email'] ?? '');

        if ($id <= 0 || $studentNumber === '' || $firstName === '' || $lastName === '' || $course === '' || $email === '' || $yearLevel < 1 || $yearLevel > 4) {
            $error = 'Please fill in all required fields.';
        } else {
            $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
            if ($newPassword !== '' && strlen($newPassword) < MIN_PASSWORD_LENGTH) {
                $error = 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
            } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
                $error = 'New passwords do not match.';
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
                    mysqli_begin_transaction($conn);
                    try {
                        $stmt = mysqli_prepare($conn, 'UPDATE students SET student_number = ?, first_name = ?, middle_name = ?, last_name = ?, gender = ?, course = ?, year_level = ?, email = ? WHERE id = ?');
                        mysqli_stmt_bind_param($stmt, 'ssssssisi', $studentNumber, $firstName, $middleName, $lastName, $gender, $course, $yearLevel, $email, $id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);

                        if ($newPassword !== '') {
                            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                            // Fetch user_id for this student (editStudent may not be loaded yet)
                            $uidStmt = mysqli_prepare($conn, 'SELECT user_id FROM students WHERE id = ?');
                            mysqli_stmt_bind_param($uidStmt, 'i', $id);
                            mysqli_stmt_execute($uidStmt);
                            $uidRes = mysqli_stmt_get_result($uidStmt);
                            $uidRow = mysqli_fetch_assoc($uidRes);
                            mysqli_stmt_close($uidStmt);
                            $targetUserId = $uidRow ? (int)$uidRow['user_id'] : 0;
                            if ($targetUserId > 0) {
                                $pStmt = mysqli_prepare($conn, 'UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?');
                                mysqli_stmt_bind_param($pStmt, 'si', $hash, $targetUserId);
                                mysqli_stmt_execute($pStmt);
                                mysqli_stmt_close($pStmt);
                            }
                        }

                        mysqli_commit($conn);
                        $message = 'Student record updated.';
                        if ($newPassword !== '') $message .= ' Password has been reset.';
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error = 'Failed to update student record.';
                    }
                }
            }
        }
    }
}

// Redirect to Student List tab after successful add
if ($action === 'add' && $error === '' && $message !== '') {
    $_SESSION['flash_msg'] = $message;
    header('Location: ' . BASE_URL . 'admin/student_records.php?tab=list');
    exit;
}

// Redirect to Student List tab after successful update
if ($action === 'update' && $error === '' && $message !== '') {
    $_SESSION['flash_msg'] = $message;
    header('Location: ' . BASE_URL . 'admin/student_records.php?tab=list');
    exit;
}

// Redirect to Registration Log tab after successful approve/reject
if (($action === 'reg_approve' || $action === 'reg_reject') && $error === '' && $message !== '') {
    $_SESSION['flash_msg'] = $message;
    header('Location: ' . BASE_URL . 'admin/student_records.php?tab=reglog');
    exit;
}

// Edit mode
$editStudent = null;
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, 'SELECT s.id, s.user_id, s.student_number, s.first_name, s.middle_name, s.last_name, s.gender, s.course, s.year_level, s.section, s.email, u.username FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $editStudent = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

$search = trim($_GET['q'] ?? '');
$regSearch = trim($_GET['reg_q'] ?? '');
$logSearch = trim($_GET['log_q'] ?? '');
$logStatus = $_GET['log_status'] ?? 'All';
$sort = $_GET['sort'] ?? 'default';
if (!in_array($sort, ['default', 'asc', 'desc'], true)) $sort = 'default';

if (!in_array($logStatus, ['All','Approved','Rejected'], true)) $logStatus = 'All';

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
if ($sort === 'asc') {
    $sql .= ' ORDER BY student_number ASC';
} elseif ($sort === 'desc') {
    $sql .= ' ORDER BY student_number DESC';
} else {
    $sql .= ' ORDER BY last_name, first_name';
}

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
        WHERE m.student_id = ? AND m.status = 'Approved' ORDER BY m.applied_at DESC");
    mysqli_stmt_bind_param($mStmt, 'i', $st['id']);
    mysqli_stmt_execute($mStmt);
    $mRes = mysqli_stmt_get_result($mStmt);
    while ($mr = mysqli_fetch_assoc($mRes)) {
        $st['memberships'][] = $mr;
    }
    mysqli_stmt_close($mStmt);
}
unset($st);

// Fetch registration requests (auto-create table if missing)
$regRequests = [];
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS registration_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    coe_image VARCHAR(255) DEFAULT NULL,
    status ENUM('Pending','Approved','Rejected','Deleted') NOT NULL DEFAULT 'Pending',
    rejection_reason VARCHAR(255) NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
// Ensure columns exist on older installs (idempotent; ignore errors)
try { mysqli_query($conn, "ALTER TABLE registration_requests ADD COLUMN rejection_reason VARCHAR(255) NULL"); } catch (mysqli_sql_exception $e) { }
try { mysqli_query($conn, "ALTER TABLE registration_requests ADD COLUMN processed_at TIMESTAMP NULL"); } catch (mysqli_sql_exception $e) { }
// Clean up legacy hashed passwords (can't be displayed) — remove those rows
mysqli_query($conn, "DELETE FROM registration_requests WHERE password LIKE '$2y$%'");
$rrRes = mysqli_query($conn, "SELECT id, full_name, email, password, coe_image, status, rejection_reason, processed_at, created_at FROM registration_requests ORDER BY created_at DESC");
if ($rrRes) {
    while ($rr = mysqli_fetch_assoc($rrRes)) { $regRequests[] = $rr; }
    mysqli_free_result($rrRes);
}

// Filtered lists for search
$pendingRegRequests = array_values(array_filter($regRequests, function ($r) {
    return ($r['status'] ?? 'Pending') === 'Pending';
}));
if ($regSearch !== '') {
    $pendingRegRequests = array_values(array_filter($pendingRegRequests, function ($r) use ($regSearch) {
        return stripos($r['full_name'], $regSearch) !== false;
    }));
}
$logRequests = array_values(array_filter($regRequests, function ($r) {
    return in_array($r['status'], ['Approved', 'Rejected'], true);
}));
if ($logStatus !== 'All') {
    $logRequests = array_values(array_filter($logRequests, function ($r) use ($logStatus) {
        return $r['status'] === $logStatus;
    }));
}
if ($logSearch !== '') {
    $logRequests = array_values(array_filter($logRequests, function ($r) use ($logSearch) {
        return stripos($r['full_name'], $logSearch) !== false;
    }));
}

$pageTitle = 'Student Records';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Student Records</h1>
    <?php if ($message !== ''): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if ($hadFlash): ?>
    <script>
      if (window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, '<?php echo BASE_URL; ?>admin/student_records.php');
      }
    </script>
    <?php endif; ?>

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
            <label>Gender</label>
            <select name="gender">
                <option value="">-- Select --</option>
                <option value="Male" <?php echo ($editStudent['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($editStudent['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
            </select>
            <label>Course *</label>
            <select name="course" required>
                <option value="">-- Select --</option>
                <?php
                $courseOpts = [];
                $cr = mysqli_query($conn, 'SELECT name FROM courses ORDER BY name');
                if ($cr) { while ($crrow = mysqli_fetch_assoc($cr)) $courseOpts[] = $crrow['name']; mysqli_free_result($cr); }
                foreach ($courseOpts as $cname):
                ?>
                    <option value="<?php echo htmlspecialchars($cname); ?>" <?php echo $editStudent['course'] === $cname ? 'selected' : ''; ?>><?php echo htmlspecialchars($cname); ?></option>
                <?php endforeach; ?>
                <?php if ($editStudent['course'] !== null && $editStudent['course'] !== '' && !in_array($editStudent['course'], $courseOpts, true)): ?>
                    <option value="<?php echo htmlspecialchars($editStudent['course']); ?>" selected><?php echo htmlspecialchars($editStudent['course']); ?></option>
                <?php endif; ?>
            </select>
            <label>Year Level *</label>
            <select name="year_level" required>
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo (int)$editStudent['year_level'] === $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
            <label>Email *</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($editStudent['email']); ?>">
            <label>New Password (leave blank to keep current)</label><br>
            <div class="password-wrapper">
                <input type="password" name="new_password" id="pwdEditStudent">
                <button type="button" class="password-toggle" data-target="pwdEditStudent" aria-label="Toggle password visibility">
                    <i data-lucide="eye" class="eye-show"></i>
                    <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
                </button>
            </div>
            <label>Confirm New Password</label><br>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="pwdEditConfirm">
                <button type="button" class="password-toggle" data-target="pwdEditConfirm" aria-label="Toggle password visibility">
                    <i data-lucide="eye" class="eye-show"></i>
                    <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
                </button>
            </div>
            <br>
            <button type="submit">Save Changes</button>
            <a href="<?php echo BASE_URL; ?>admin/student_records.php" class="btn btn-secondary" style="margin-left:10px;">Cancel</a>
        </form>
    </div>
    <?php endif; ?>

    <?php
    if (isset($_GET['tab']) && in_array($_GET['tab'], ['list','add','regs','reglog'], true)) {
        $defaultTab = $_GET['tab'];
    } elseif (isset($_GET['edit'])) {
        $defaultTab = 'add';
    } elseif ($action === 'add') {
        $defaultTab = 'add';
    } elseif (in_array($action, ['reg_approve', 'reg_reject'], true)) {
        $defaultTab = 'regs';
    } else {
        $defaultTab = 'list';
    }
    ?>
    <div class="org-tabs">
        <button type="button" class="org-tab <?php echo $defaultTab === 'list' ? 'active' : ''; ?>" data-tab="list">Student List</button>
        <button type="button" class="org-tab <?php echo $defaultTab === 'regs' ? 'active' : ''; ?>" data-tab="regs">Registrations<?php $pendingRegs = 0; foreach ($regRequests as $rr) { if (($rr['status'] ?? 'Pending') === 'Pending') $pendingRegs++; } if ($pendingRegs > 0): ?><span class="tab-badge"><?php echo $pendingRegs; ?></span><?php endif; ?></button>
        <button type="button" class="org-tab <?php echo $defaultTab === 'add' ? 'active' : ''; ?>" data-tab="add">Add Student</button>
        <button type="button" class="org-tab <?php echo $defaultTab === 'reglog' ? 'active' : ''; ?>" data-tab="reglog">Registration Log</button>
    </div>

    <div class="org-tab-panel <?php echo $defaultTab === 'list' ? 'active' : ''; ?>" id="tab-list">
        <form method="get" action="">
            <input type="text" name="q" placeholder="Search by name or student number" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
            <a href="<?php echo BASE_URL; ?>admin/student_records.php" class="btn btn-secondary">Reset</a>
        </form>
        <br>

        <table>
            <thead><tr><th><a href="<?php echo BASE_URL; ?>admin/student_records.php?tab=list<?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>&sort=<?php echo $sort === 'asc' ? 'desc' : ($sort === 'desc' ? 'default' : 'asc'); ?>" class="sort-link<?php echo $sort === 'asc' ? ' sort-asc' : ($sort === 'desc' ? ' sort-desc' : ' sort-default'); ?>" title="Sort by student number">Student No.<?php echo $sort === 'asc' ? ' ▲' : ($sort === 'desc' ? ' ▼' : ' ▶'); ?></a></th><th>Name</th><th>Course/Year</th><th>Email</th><th>Active Memberships</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['student_number']); ?></td>
                    <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['middle_name'] . ' ' . $s['last_name']); ?></td>
                    <td><?php echo htmlspecialchars(($s['course'] ?? '—') . ' - ' . $s['year_level']); ?></td>
                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                    <td>
                        <?php if (empty($s['memberships'])): ?>
                            None
                        <?php else: ?>
                            <?php foreach ($s['memberships'] as $m): ?>
                                <?php echo htmlspecialchars($m['org_name']); ?><br>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>admin/student_records.php?edit=<?php echo (int) $s['id']; ?>">Edit</a>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                            <button type="button" class="btn-danger" onclick="confirmDelete(this.form, 'Delete this student record and their memberships?', 'Delete');">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($students)): ?>
                <tr><td colspan="6">No students found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="org-tab-panel <?php echo $defaultTab === 'add' ? 'active' : ''; ?>" id="tab-add">
        <div class="card">
            <h3>Add Student</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="add">
                <label>Student Number *</label><br>
                <input type="text" name="student_number" required><br>
            <label>Password * (min <?php echo MIN_PASSWORD_LENGTH; ?> chars)</label><br>
            <div class="password-wrapper">
                <input type="password" name="password" id="pwdAddStudent" required>
                <button type="button" class="password-toggle" data-target="pwdAddStudent" aria-label="Toggle password visibility">
                    <i data-lucide="eye" class="eye-show"></i>
                    <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
                </button>
            </div>
            <label>Confirm Password *</label><br>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="pwdAddConfirm" required>
                <button type="button" class="password-toggle" data-target="pwdAddConfirm" aria-label="Toggle password visibility">
                    <i data-lucide="eye" class="eye-show"></i>
                    <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
                </button>
            </div>
            <label>First Name *</label><br>
                <input type="text" name="first_name" required><br>
                <label>Middle Name</label><br>
                <input type="text" name="middle_name"><br>
                <label>Last Name *</label><br>
                <input type="text" name="last_name" required><br>
                <label>Gender</label><br>
                <select name="gender">
                    <option value="">-- Select --</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select><br>
                <label>Course *</label><br>
                <select name="course" required>
                    <option value="">-- Select --</option>
                    <?php
                    $courseOpts = [];
                    $cr = mysqli_query($conn, 'SELECT name FROM courses ORDER BY name');
                    if ($cr) { while ($crrow = mysqli_fetch_assoc($cr)) $courseOpts[] = $crrow['name']; mysqli_free_result($cr); }
                    foreach ($courseOpts as $cname):
                    ?>
                        <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                    <?php endforeach; ?>
                </select><br>
                <label>Year Level *</label><br>
                <select name="year_level" required>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select><br>
                <label>Email *</label><br>
                <input type="email" name="email" required><br>
                <input type="submit" value="Add Student">
            </form>
        </div>
    </div>

    <div class="org-tab-panel <?php echo $defaultTab === 'regs' ? 'active' : ''; ?>" id="tab-regs">
        <h2>Registration Requests</h2>
        <form method="get" action="">
            <input type="hidden" name="tab" value="regs">
            <input type="text" name="reg_q" placeholder="Search by name" value="<?php echo htmlspecialchars($regSearch); ?>">
            <button type="submit">Search</button>
            <a href="<?php echo BASE_URL; ?>admin/student_records.php?tab=regs" class="btn btn-secondary">Reset</a>
        </form>
        <br>
        <?php if (empty($pendingRegRequests)): ?>
            <div class="empty-state">No pending registration requests.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Full Name</th><th>Email</th><th>Password</th><th>Proof (COE)</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($pendingRegRequests as $rr): ?>
                <tr>
                    <td><?php echo htmlspecialchars($rr['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($rr['email']); ?></td>
                    <td><?php echo htmlspecialchars($rr['password']); ?></td>
                    <td>
                        <?php if (!empty($rr['coe_image'])): ?>
                            <a href="<?php echo BASE_URL . htmlspecialchars($rr['coe_image']); ?>" target="_blank">View</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($rr['created_at']); ?></td>
                    <td><span class="badge <?php echo strtolower($rr['status']); ?>"><?php echo htmlspecialchars($rr['status']); ?></span></td>
                    <td>
                        <?php if ($rr['status'] === 'Pending'): ?>
                            <form method="post" action="" style="display:inline;">
                                <input type="hidden" name="action" value="reg_approve">
                                <input type="hidden" name="id" value="<?php echo (int) $rr['id']; ?>">
                                <button type="submit" style="padding:4px 8px; font-size:0.8rem;">Approve</button>
                            </form>
                            <form method="post" action="" style="display:inline;">
                                <input type="hidden" name="action" value="reg_reject">
                                <input type="hidden" name="id" value="<?php echo (int) $rr['id']; ?>">
                                <input type="hidden" name="rejection_reason" value="">
                                <button type="button" class="btn-danger" style="padding:4px 8px; font-size:0.8rem;" onclick="showRejectPanel(this.form);">Reject</button>
                            </form>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="org-tab-panel <?php echo $defaultTab === 'reglog' ? 'active' : ''; ?>" id="tab-reglog">
        <h2>Registration Log</h2>
        <form method="get" action="" style="margin-bottom:12px;">
            <input type="hidden" name="tab" value="reglog">
            <input type="hidden" name="log_q" value="<?php echo htmlspecialchars($logSearch); ?>">
            <label style="display:inline-block; margin:0 8px 0 0; font-weight:400;">Filter by status:</label>
            <select name="log_status" onchange="this.form.submit()" style="padding:4px 6px; font-size:0.9rem;">
                <option value="All" <?php echo $logStatus === 'All' ? 'selected' : ''; ?>>All</option>
                <option value="Approved" <?php echo $logStatus === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="Rejected" <?php echo $logStatus === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </form>
        <form method="get" action="">
            <input type="hidden" name="tab" value="reglog">
            <input type="hidden" name="log_status" value="<?php echo htmlspecialchars($logStatus); ?>">
            <input type="text" name="log_q" placeholder="Search by name" value="<?php echo htmlspecialchars($logSearch); ?>">
            <button type="submit">Search</button>
            <a href="<?php echo BASE_URL; ?>admin/student_records.php?tab=reglog" class="btn btn-secondary">Reset</a>
        </form>
        <br>
        <?php if (empty($logRequests)): ?>
            <div class="empty-state">No approved or rejected registrations yet.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Full Name</th><th>Email</th><th>Password</th><th>Proof (COE)</th><th>Status</th><th>Reason</th><th>Processed</th></tr></thead>
            <tbody>
            <?php foreach ($logRequests as $rr): ?>
                <tr>
                    <td><?php echo htmlspecialchars($rr['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($rr['email']); ?></td>
                    <td><?php echo htmlspecialchars($rr['password']); ?></td>
                    <td>
                        <?php if (!empty($rr['coe_image'])): ?>
                            <a href="<?php echo BASE_URL . htmlspecialchars($rr['coe_image']); ?>" target="_blank">View</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?php echo strtolower($rr['status']); ?>"><?php echo htmlspecialchars($rr['status']); ?></span></td>
                    <td><?php echo !empty($rr['rejection_reason']) ? htmlspecialchars($rr['rejection_reason']) : '—'; ?></td>
                    <td><?php echo !empty($rr['processed_at']) ? htmlspecialchars($rr['processed_at']) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <script>
      (function() {
        var tabs = document.querySelectorAll('.org-tab');
        tabs.forEach(function(tab) {
          tab.addEventListener('click', function() {
            var target = tab.getAttribute('data-tab');
            tabs.forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            document.querySelectorAll('.org-tab-panel').forEach(function(p) { p.classList.remove('active'); });
            document.getElementById('tab-' + target).classList.add('active');
          });
        });
      })();
    </script>
<script>
  (function() {
    // Poll pending registration count; update badge; auto-reload Registrations tab on new submissions
    var badge = document.querySelector('.org-tab[data-tab="regs"] .tab-badge');
    var stored = parseInt(localStorage.getItem('regLastSeen') || '0', 10);
    var initialCount = <?php echo (int) $pendingRegs; ?>;
    if (stored > initialCount) stored = initialCount; // stale guard
    var lastCount = stored || initialCount;
    var prevCount = lastCount;

    function getBadge(n) {
      var btn = document.querySelector('.org-tab[data-tab="regs"]');
      if (!btn) return null;
      var b = btn.querySelector('.tab-badge');
      if (!b && n > 0) {
        b = document.createElement('span');
        b.className = 'tab-badge';
        btn.appendChild(b);
      }
      return b;
    }

    function isRegsTabActive() {
      var p = document.getElementById('tab-regs');
      return p && p.classList.contains('active');
    }

    function poll() {
      fetch('<?php echo BASE_URL; ?>admin/reg_count.php', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          var n = d.pending || 0;
          console.log('poll:', n);
          var b = getBadge(n);
          if (b) {
            if (n > 0) { b.textContent = n; b.style.display = ''; }
            else { b.style.display = 'none'; }
          }
          if (n > prevCount) {
            prevCount = n;
            lastCount = n;
            try { localStorage.setItem('regLastSeen', String(n)); } catch (e) {}
            if (isRegsTabActive()) {
              window.location.reload();
            }
          } else {
            prevCount = n;
            lastCount = n;
            try { localStorage.setItem('regLastSeen', String(n)); } catch (e) {}
          }
        })
        .catch(function(e){ console.error('poll error:', e); });
    }

    setInterval(poll, 1000);
    poll();
  })();

  function confirmDelete(form, text, label) {
    showConfirm(text, function() { form.submit(); }, label);
  }
  function showRejectPanel(form) {
    document.querySelectorAll('.msg-overlay').forEach(function(o) { o.remove(); });
    var overlay = document.createElement('div');
    overlay.className = 'msg-overlay';
    var box = document.createElement('div');
    box.className = 'msg-box';
    box.style.maxWidth = '420px';
    var title = document.createElement('div');
    title.className = 'msg-text';
    title.style.fontWeight = '600';
    title.style.marginBottom = '12px';
    title.textContent = 'Reject Registration';
    box.appendChild(title);

    var label = document.createElement('div');
    label.style.fontSize = '0.85rem';
    label.style.color = 'var(--text-secondary)';
    label.style.marginBottom = '6px';
    label.textContent = 'Reason for rejection:';
    box.appendChild(label);

    var select = document.createElement('select');
    select.style.width = '100%';
    select.style.padding = '8px';
    select.style.marginBottom = '8px';
    var presets = [
      'The attached image was blurry. Please re-register with a clearer photo.',
      'The personal details provided do not match those in the attached image.',
      'You do not appear to be currently enrolled in our school.',
      'The certificate of enrollment is expired or invalid.',
      'The uploaded file is not a valid certificate of enrollment.',
      'Duplicate registration request.',
      'Incomplete information provided.',
      'Other (specify below)'
    ];
    presets.forEach(function(p) {
      var opt = document.createElement('option');
      opt.value = p;
      opt.textContent = p;
      select.appendChild(opt);
    });
    box.appendChild(select);

    var textarea = document.createElement('textarea');
    textarea.placeholder = 'Custom reason...';
    textarea.style.width = '100%';
    textarea.style.minHeight = '60px';
    textarea.style.padding = '8px';
    textarea.style.marginBottom = '12px';
    textarea.style.display = 'none';
    box.appendChild(textarea);

    select.addEventListener('change', function() {
        textarea.style.display = (select.value === 'Other (specify below)') ? 'block' : 'none';
    });

    var actions = document.createElement('div');
    actions.style.display = 'flex';
    actions.style.gap = '8px';
    actions.style.justifyContent = 'flex-end';
    var cancel = document.createElement('button');
    cancel.className = 'msg-ok';
    cancel.style.background = '#fff';
    cancel.style.color = 'var(--text-primary)';
    cancel.style.border = '1px solid var(--border)';
    cancel.textContent = 'Cancel';
    cancel.addEventListener('click', function() {
      if (document.body.contains(overlay)) document.body.removeChild(overlay);
    });
    var ok = document.createElement('button');
    ok.className = 'msg-ok';
    ok.textContent = 'Reject';
    ok.addEventListener('click', function() {
      var reason = (select.value === 'Other (specify below)') ? textarea.value.trim() : select.value;
      if (reason === '' || reason === 'Other (specify below)') {
        reason = textarea.value.trim() || 'Rejected by admin.';
      }
      var hidden = form.querySelector('input[name="rejection_reason"]');
      if (hidden) hidden.value = reason;
      if (document.body.contains(overlay)) document.body.removeChild(overlay);
      form.submit();
    });
    actions.appendChild(cancel);
    actions.appendChild(ok);
    box.appendChild(actions);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
  }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

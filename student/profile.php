<?php
ob_start();
// student/profile.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireStudent();

$cu = currentUser();
$student = null;
$stmt = mysqli_prepare($conn, 'SELECT id, student_number, first_name, middle_name, last_name, gender, course, year_level, email FROM students WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student) {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $error = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            try {
                $stmt = mysqli_prepare($conn, 'UPDATE students SET email = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'si', $email, $student['id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $message = 'Email updated successfully.';
                // refresh student data
                $stmt = mysqli_prepare($conn, 'SELECT id, student_number, first_name, middle_name, last_name, gender, course, year_level, email FROM students WHERE user_id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $student = mysqli_fetch_assoc($res);
                mysqli_stmt_close($stmt);
            } catch (mysqli_sql_exception $e) {
                $error = 'Failed to update email.';
            }
        }
    } elseif ($action === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'Please fill in all password fields.';
        } elseif (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
            $error = 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            $stmt = mysqli_prepare($conn, 'SELECT password FROM users WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $u = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);
            if (!$u || !password_verify($currentPassword, $u['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, 'UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'si', $hash, $cu['id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // Sync session pwd_changed_at with DB to prevent forced re-login
                $stmt = mysqli_prepare($conn, 'SELECT password_changed_at FROM users WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res);
                mysqli_stmt_close($stmt);
                $_SESSION['pwd_changed_at'] = $row['password_changed_at'] ?? null;

                $message = 'Password changed successfully.';
            }
        }
    }
}

$pageTitle = 'Update Profile';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Update Personal Information</h1>
    <?php if ($message !== ''): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if ($student): ?>
    <form method="post" action="">
        <input type="hidden" name="action" value="profile">
        <label>Student Number</label><br>
        <input type="text" value="<?php echo htmlspecialchars($student['student_number']); ?>" disabled><br>
        <label>First Name</label><br>
        <input type="text" value="<?php echo htmlspecialchars($student['first_name']); ?>" disabled><br>
        <label>Middle Name</label><br>
        <input type="text" value="<?php echo htmlspecialchars($student['middle_name']); ?>" disabled><br>
        <label>Last Name</label><br>
        <input type="text" value="<?php echo htmlspecialchars($student['last_name']); ?>" disabled><br>
        <label>Gender</label><br>
        <input type="text" value="<?php echo htmlspecialchars($student['gender'] ?? ''); ?>" disabled><br>
        <label>Course</label><br>
        <input type="text" value="<?php echo htmlspecialchars($student['course'] ?? ''); ?>" disabled><br>
        <label>Year Level</label><br>
        <input type="text" value="<?php echo htmlspecialchars($student['year_level']); ?>" disabled><br>
        <label>Email *</label><br>
        <input type="email" name="email" required value="<?php echo htmlspecialchars($student['email']); ?>"><br>

        <button type="submit" name="update_profile">Update Email</button>
    </form>

    <h3 style="margin-top: 24px;">Change Password (optional)</h3>
    <form method="post" action="">
        <input type="hidden" name="action" value="password">
        <label>Current Password</label><br>
        <div class="password-wrapper">
            <input type="password" name="current_password" id="pwdCurrent">
            <button type="button" class="password-toggle" data-target="pwdCurrent" aria-label="Toggle password visibility">
                <i data-lucide="eye" class="eye-show"></i>
                <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
            </button>
        </div>
        <label>New Password</label><br>
        <div class="password-wrapper">
            <input type="password" name="new_password" id="pwdNew">
            <button type="button" class="password-toggle" data-target="pwdNew" aria-label="Toggle password visibility">
                <i data-lucide="eye" class="eye-show"></i>
                <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
            </button>
        </div>
        <label>Confirm New Password</label><br>
        <div class="password-wrapper">
            <input type="password" name="confirm_password" id="pwdConfirm">
            <button type="button" class="password-toggle" data-target="pwdConfirm" aria-label="Toggle password visibility">
                <i data-lucide="eye" class="eye-show"></i>
                <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
            </button>
        </div>
        <button type="submit" name="change_password">Change Password</button>
    </form>
    <?php else: ?>
        <p>Student record not found.</p>
    <?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

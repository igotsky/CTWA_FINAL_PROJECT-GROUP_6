<?php
// register.php - student registration
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$fieldErrors = [];

$vals = [
    'username'       => '',
    'student_number' => '',
    'first_name'     => '',
    'middle_name'    => '',
    'last_name'     => '',
    'course'         => '',
    'year_level'     => '',
    'email'          => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($vals as $k => $v) {
        $vals[$k] = trim($_POST[$k] ?? '');
    }
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($vals['username'] === '') { $fieldErrors['username'] = 'Username is required.'; $errors[] = $fieldErrors['username']; }
    if ($password === '') { $fieldErrors['password'] = 'Password is required.'; $errors[] = $fieldErrors['password']; }
    elseif (strlen($password) < MIN_PASSWORD_LENGTH) { $fieldErrors['password'] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.'; $errors[] = $fieldErrors['password']; }
    if ($password2 === '') { $fieldErrors['password2'] = 'Please confirm your password.'; $errors[] = $fieldErrors['password2']; }
    elseif ($password !== '' && $password !== $password2) { $fieldErrors['password2'] = 'Passwords do not match.'; $errors[] = $fieldErrors['password2']; }
    if ($vals['student_number'] === '') { $fieldErrors['student_number'] = 'Student number is required.'; $errors[] = $fieldErrors['student_number']; }
    if ($vals['first_name'] === '') { $fieldErrors['first_name'] = 'First name is required.'; $errors[] = $fieldErrors['first_name']; }
    if ($vals['last_name'] === '') { $fieldErrors['last_name'] = 'Last name is required.'; $errors[] = $fieldErrors['last_name']; }
    if ($vals['course'] === '') { $fieldErrors['course'] = 'Course is required.'; $errors[] = $fieldErrors['course']; }
    if ($vals['email'] === '') { $fieldErrors['email'] = 'Email is required.'; $errors[] = $fieldErrors['email']; }
    elseif (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) { $fieldErrors['email'] = 'Invalid email address.'; $errors[] = $fieldErrors['email']; }
    if ($vals['year_level'] === '' || (int)$vals['year_level'] < 1 || (int)$vals['year_level'] > 6) { $fieldErrors['year_level'] = 'Please select a valid year level.'; $errors[] = $fieldErrors['year_level']; }

    if (!isset($fieldErrors['username']) && $vals['username'] !== '') {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ?');
        mysqli_stmt_bind_param($stmt, 's', $vals['username']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) { $fieldErrors['username'] = 'Username already taken.'; $errors[] = $fieldErrors['username']; }
        mysqli_stmt_close($stmt);
    }
    if (!isset($fieldErrors['student_number']) && $vals['student_number'] !== '') {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM students WHERE student_number = ?');
        mysqli_stmt_bind_param($stmt, 's', $vals['student_number']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) { $fieldErrors['student_number'] = 'Student number already registered.'; $errors[] = $fieldErrors['student_number']; }
        mysqli_stmt_close($stmt);
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'student';
            $stmt = mysqli_prepare($conn, 'INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sss', $vals['username'], $hash, $role);
            mysqli_stmt_execute($stmt);
            $userId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'INSERT INTO students (user_id, student_number, first_name, middle_name, last_name, course, year_level, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $yn = (int) $vals['year_level'];
            mysqli_stmt_bind_param($stmt, 'isssssis', $userId, $vals['student_number'], $vals['first_name'], $vals['middle_name'], $vals['last_name'], $vals['course'], $yn, $vals['email']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            foreach ($vals as $k => $v) { $vals[$k] = ''; }
            $success = 'Registration successful. You can now log in.';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

$success = $success ?? '';
function fc($name, $fe) { return isset($fe[$name]) ? 'invalid' : ''; }

$authWide = true;
$pageTitle = 'Register';
include __DIR__ . '/includes/header.php';
?>
    <a href="<?php echo BASE_URL; ?>login.php" class="back-link">← Back to Login</a>
    <h1>Student Registration</h1>
    <?php if (!empty($errors)): ?>
        <div class="alert error">Please correct the highlighted fields below.</div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <form method="post" action="" novalidate>
        <div class="form-columns">
            <div class="form-column">
                <label>Username *</label>
                <input type="text" name="username" required class="<?php echo fc('username', $fieldErrors); ?>" value="<?php echo htmlspecialchars($vals['username']); ?>">
                <?php if (isset($fieldErrors['username'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['username']); ?></small><?php endif; ?>

                <label>Password * (min <?php echo MIN_PASSWORD_LENGTH; ?> chars)</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="pwdReg" required class="<?php echo fc('password', $fieldErrors); ?>">
                    <button type="button" class="password-toggle" data-target="pwdReg" aria-label="Toggle password visibility">
                        <i data-lucide="eye" class="eye-show"></i>
                        <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
                    </button>
                </div>
                <?php if (isset($fieldErrors['password'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['password']); ?></small><?php endif; ?>

                <label>Confirm Password *</label>
                <div class="password-wrapper">
                    <input type="password" name="password2" id="pwdReg2" required class="<?php echo fc('password2', $fieldErrors); ?>">
                    <button type="button" class="password-toggle" data-target="pwdReg2" aria-label="Toggle password visibility">
                        <i data-lucide="eye" class="eye-show"></i>
                        <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
                    </button>
                </div>
                <?php if (isset($fieldErrors['password2'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['password2']); ?></small><?php endif; ?>

                <label>Student Number *</label>
                <input type="text" name="student_number" required class="<?php echo fc('student_number', $fieldErrors); ?>" value="<?php echo htmlspecialchars($vals['student_number']); ?>">
                <?php if (isset($fieldErrors['student_number'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['student_number']); ?></small><?php endif; ?>

                <label>Email *</label>
                <input type="email" name="email" required class="<?php echo fc('email', $fieldErrors); ?>" value="<?php echo htmlspecialchars($vals['email']); ?>">
                <?php if (isset($fieldErrors['email'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['email']); ?></small><?php endif; ?>
            </div>
            <div class="form-column">
                <label>First Name *</label>
                <input type="text" name="first_name" required class="<?php echo fc('first_name', $fieldErrors); ?>" value="<?php echo htmlspecialchars($vals['first_name']); ?>">
                <?php if (isset($fieldErrors['first_name'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['first_name']); ?></small><?php endif; ?>

                <label>Middle Name</label>
                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($vals['middle_name']); ?>">

                <label>Last Name *</label>
                <input type="text" name="last_name" required class="<?php echo fc('last_name', $fieldErrors); ?>" value="<?php echo htmlspecialchars($vals['last_name']); ?>">
                <?php if (isset($fieldErrors['last_name'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['last_name']); ?></small><?php endif; ?>

                <label>Course *</label>
                <input type="text" name="course" required class="<?php echo fc('course', $fieldErrors); ?>" value="<?php echo htmlspecialchars($vals['course']); ?>">
                <?php if (isset($fieldErrors['course'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['course']); ?></small><?php endif; ?>

                <label>Year Level *</label>
                <select name="year_level" required class="<?php echo fc('year_level', $fieldErrors); ?>">
                    <option value="">-- Select --</option>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo (string)$vals['year_level'] === (string)$i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($fieldErrors['year_level'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['year_level']); ?></small><?php endif; ?>
            </div>
        </div>
        <input type="submit" value="Register" style="display:block;margin-left:auto;margin-right:auto;">
    </form>
<?php include __DIR__ . '/includes/footer.php'; ?>

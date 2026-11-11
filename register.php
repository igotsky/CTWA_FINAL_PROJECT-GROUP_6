<?php
// register.php - student registration inquiry
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$fieldErrors = [];

$vals = [
    'first_name'  => '',
    'middle_name' => '',
    'last_name'   => '',
    'email'       => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($vals as $k => $v) {
        $vals[$k] = trim($_POST[$k] ?? '');
    }
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($vals['first_name'] === '') { $fieldErrors['first_name'] = 'First name is required.'; $errors[] = $fieldErrors['first_name']; }
    elseif (!preg_match('/^[A-Za-z\s\-]+$/', $vals['first_name'])) { $fieldErrors['first_name'] = 'First name must contain letters only.'; $errors[] = $fieldErrors['first_name']; }
    if ($vals['last_name'] === '') { $fieldErrors['last_name'] = 'Surname is required.'; $errors[] = $fieldErrors['last_name']; }
    elseif (!preg_match('/^[A-Za-z\s\-]+$/', $vals['last_name'])) { $fieldErrors['last_name'] = 'Surname must contain letters only.'; $errors[] = $fieldErrors['last_name']; }
    if ($vals['middle_name'] !== '' && !preg_match('/^[A-Za-z\s\-]+$/', $vals['middle_name'])) {
        $fieldErrors['middle_name'] = 'Middle name must contain letters only.';
        $errors[] = $fieldErrors['middle_name'];
    }
    if ($vals['email'] === '') { $fieldErrors['email'] = 'Email is required.'; $errors[] = $fieldErrors['email']; }
    elseif (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) { $fieldErrors['email'] = 'Invalid email address.'; $errors[] = $fieldErrors['email']; }
    if ($password === '') { $fieldErrors['password'] = 'Password is required.'; $errors[] = $fieldErrors['password']; }
    elseif (strlen($password) < MIN_PASSWORD_LENGTH) { $fieldErrors['password'] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.'; $errors[] = $fieldErrors['password']; }
    if ($password2 === '') { $fieldErrors['password2'] = 'Please confirm your password.'; $errors[] = $fieldErrors['password2']; }
    elseif ($password !== '' && $password !== $password2) { $fieldErrors['password2'] = 'Passwords do not match.'; $errors[] = $fieldErrors['password2']; }

    // COE image is required
    if (!isset($_FILES['coe_image']) || $_FILES['coe_image']['error'] === UPLOAD_ERR_NO_FILE) {
        $fieldErrors['coe_image'] = 'Please insert the picture of your certificate of enrollment.';
        $errors[] = $fieldErrors['coe_image'];
    }

    // Optional COE image upload (validated above)
    $coePath = null;
    if (empty($fieldErrors['coe_image']) && isset($_FILES['coe_image']) && $_FILES['coe_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['coe_image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $fieldErrors['coe_image'] = 'Failed to upload image.';
            $errors[] = $fieldErrors['coe_image'];
        } else {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!isset($allowed[$mime])) {
                $fieldErrors['coe_image'] = 'Only JPG, PNG, or PDF files are allowed.';
                $errors[] = $fieldErrors['coe_image'];
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $fieldErrors['coe_image'] = 'File must be 5MB or smaller.';
                $errors[] = $fieldErrors['coe_image'];
            } else {
                $uploadDir = __DIR__ . '/uploads/coe/';
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
                $ext = $allowed[$mime];
                $filename = 'coe_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $coePath = 'uploads/coe/' . $filename;
                } else {
                    $fieldErrors['coe_image'] = 'Failed to save image.';
                    $errors[] = $fieldErrors['coe_image'];
                }
            }
        }
    }

    if (empty($errors)) {
        // Ensure table exists
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
        $fullName = trim($vals['first_name'] . ' ' . $vals['middle_name'] . ' ' . $vals['last_name']);
        $stmt = mysqli_prepare($conn, 'INSERT INTO registration_requests (full_name, email, password, coe_image) VALUES (?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'ssss', $fullName, $vals['email'], $password, $coePath);
        try {
            mysqli_stmt_execute($stmt);
        } catch (mysqli_sql_exception $e) {
            $fieldErrors['general'] = 'Failed to submit registration. Please try again.';
            $errors[] = $fieldErrors['general'];
        }
        mysqli_stmt_close($stmt);

        if (empty($errors)) {
            foreach ($vals as $k => $v) { $vals[$k] = ''; }
            $success = true;
        }
    }
}

$success = $success ?? false;
function fc($name, $fe) { return isset($fe[$name]) ? 'invalid' : ''; }

$authWide = true;
$pageTitle = 'Register';
include __DIR__ . '/includes/header.php';
?>
    <h1 style="text-align:center; margin-bottom: 32px;">Student Registration</h1>
    <?php if ($success): ?>
        <div class="alert success">Registration submitted. Please check your email for updates.</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert error">Please correct the highlighted fields below.</div>
    <?php endif; ?>
    <form method="post" action="" novalidate enctype="multipart/form-data">
        <div class="form-columns">
            <div class="form-column">
                <label>First Name *</label>
                <input type="text" name="first_name" required class="<?php echo fc('first_name', $fieldErrors); ?>" value="<?php echo htmlspecialchars($vals['first_name']); ?>" pattern="[A-Za-z\s-]+" title="Letters only">
                <?php if (isset($fieldErrors['first_name'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['first_name']); ?></small><?php endif; ?>

                <label>Middle Name</label>
                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($vals['middle_name']); ?>" pattern="[A-Za-z\s-]+" title="Letters only">

                <label>Surname *</label>
                <input type="text" name="last_name" required class="<?php echo fc('last_name', $fieldErrors); ?>" value="<?php echo htmlspecialchars($vals['last_name']); ?>" pattern="[A-Za-z\s-]+" title="Letters only">
                <?php if (isset($fieldErrors['last_name'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['last_name']); ?></small><?php endif; ?>
            </div>
            <div class="form-column">
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

                <label>Email *</label>
                <input type="email" name="email" required class="<?php echo fc('email', $fieldErrors); ?>" value="<?php echo htmlspecialchars($vals['email']); ?>">
                <?php if (isset($fieldErrors['email'])): ?><small class="field-error"><?php echo htmlspecialchars($fieldErrors['email']); ?></small><?php endif; ?>
            </div>
        </div>
        <div style="text-align:center; margin: 12px 0 24px 0;">
            <p style="font-size:0.85rem; color:var(--text-secondary); margin:12px 0 4px;">Please insert the picture of your certificate of enrollment.</p>
            <input type="file" name="coe_image" id="coeImage" accept="image/*,.pdf" required style="display:none;">
            <label for="coeImage" class="btn btn-cancel" style="display:inline-block; margin:0 auto; cursor:pointer;">Choose File</label>
            <div id="coeFileName" style="font-size:0.85rem; color:var(--text-secondary); margin-top:6px; text-align:center;">No file chosen</div>
            <?php if (isset($fieldErrors['coe_image'])): ?><small class="field-error" style="text-align:center; display:block;"><?php echo htmlspecialchars($fieldErrors['coe_image']); ?></small><?php endif; ?>
        </div>
        <input type="submit" value="Register" style="display:block; margin-left:auto; margin-right:auto; margin-top:16px;">
    </form>
    <p style="text-align:center; margin-top:12px; color:var(--text-secondary);">Already have an account? <a href="<?php echo BASE_URL; ?>login.php" style="color:var(--text-primary); text-decoration:underline;">Login here</a>.</p>
<script>
  (function() {
    var input = document.getElementById('coeImage');
    var label = document.getElementById('coeFileName');
    if (input && label) {
      input.addEventListener('change', function() {
        label.textContent = (input.files && input.files.length > 0) ? input.files[0].name : 'No file chosen';
      });
    }
  })();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
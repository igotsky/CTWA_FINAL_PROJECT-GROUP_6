<?php
// login.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$invalidUser = false;
$invalidPass = false;
$redirectUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
        if ($username === '') $invalidUser = true;
        if ($password === '') $invalidPass = true;
    } else {
        $user = loginUser($conn, $username, $password);
        if ($user) {
            $redirectUrl = ($user['role'] === 'admin')
                ? BASE_URL . 'admin/dashboard.php'
                : BASE_URL . 'student/dashboard.php';
        } else {
            $error = 'Invalid username or password.';
            $invalidUser = true;
            $invalidPass = true;
        }
    }
} else {
    $username = '';
}

if ($redirectUrl !== null) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logging in...</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
  <meta http-equiv="refresh" content="1;url=<?php echo $redirectUrl; ?>">
</head>
<body>
  <div class="loading-overlay active">
    <div class="loading-spinner"></div>
    <div class="loading-text">Logging in...</div>
  </div>
  <script>setTimeout(function(){window.location.href='<?php echo $redirectUrl; ?>';},900);</script>
</body>
</html>
    <?php
    exit;
}

$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
?>
    <h1>Login</h1>
    <?php if ($error !== ''): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <label>Username</label>
        <input type="text" name="username" required class="<?php echo $invalidUser ? 'invalid' : ''; ?>" value="<?php echo htmlspecialchars($username ?? ''); ?>">
        <label>Password</label>
        <div class="password-wrapper">
            <input type="password" name="password" id="pwdLogin" required class="<?php echo $invalidPass ? 'invalid' : ''; ?>">
            <button type="button" class="password-toggle" data-target="pwdLogin" aria-label="Toggle password visibility">
                <i data-lucide="eye" class="eye-show"></i>
                <i data-lucide="eye-off" class="eye-hide" style="display:none;"></i>
            </button>
        </div>
        <input type="submit" value="Login">
    </form>
    <p>No account? <a href="<?php echo BASE_URL; ?>register.php">Register here</a>.</p>
<?php include __DIR__ . '/includes/footer.php'; ?>
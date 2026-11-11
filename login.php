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
<?php if (isset($_GET['forgot']) && isset($_GET['msg'])): ?>
<script>
  (function(){
    var t = <?php echo json_encode($_GET['type'] ?? 'success'); ?>;
    showMessage(<?php echo json_encode($_GET['msg']); ?>, t);
  })();
</script>
<?php endif; ?>
    <h1 style="text-align:center;">Login</h1>
    <?php if ($error !== ''): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <label>Student Number</label>
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
    <p style="text-align:center; color:var(--text-secondary); font-size:0.85rem; margin-top:8px;">
      <a href="#" id="forgotLink">Forgot password?</a>
    </p>
    <p style="text-align:center; color:var(--text-secondary);">No account? <a href="<?php echo BASE_URL; ?>register.php" style="color:var(--text-primary); text-decoration:underline;">Register here</a>.</p>

    <div id="forgotModal" class="msg-overlay" style="display:none;">
      <div class="msg-box">
        <div class="msg-text" style="margin-bottom:12px;">Reset your password</div>
        <form method="post" action="<?php echo BASE_URL; ?>forgot_password.php">
          <input type="text" name="student_number" placeholder="Student Number" required style="width:100%; margin-bottom:8px;">
          <div style="display:flex; gap:8px; justify-content:flex-end;">
            <button type="button" class="msg-ok" style="background:#fff; color:var(--text-primary); border:1px solid var(--border);" onclick="document.getElementById('forgotModal').style.display='none';">Cancel</button>
            <button type="submit" class="msg-ok">Submit</button>
          </div>
        </form>
      </div>
    </div>
    <script>
      (function() {
        var link = document.getElementById('forgotLink');
        var modal = document.getElementById('forgotModal');
        if (link && modal) {
          link.addEventListener('click', function(e) { e.preventDefault(); modal.style.display = 'flex'; });
          modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.style.display = 'none';
          });
        }
      })();
    </script>
<?php include __DIR__ . '/includes/footer.php'; ?>
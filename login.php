<?php
// login.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$invalidUser = false;
$invalidPass = false;
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
            if ($user['role'] === 'admin') {
                header('Location: ' . BASE_URL . 'admin/dashboard.php');
            } else {
                header('Location: ' . BASE_URL . 'student/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password.';
            $invalidUser = true;
            $invalidPass = true;
        }
    }
} else {
    $username = '';
}

$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
?>
    <h1>Login</h1>
    <?php if ($error !== ''): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <label>Username</label><br>
        <input type="text" name="username" required class="<?php echo $invalidUser ? 'invalid' : ''; ?>" value="<?php echo htmlspecialchars($username ?? ''); ?>"><br>
        <label>Password</label><br>
        <input type="password" name="password" required class="<?php echo $invalidPass ? 'invalid' : ''; ?>"><br>
        <input type="submit" value="Login">
    </form>
    <p>No account? <a href="<?php echo BASE_URL; ?>register.php">Register here</a>.</p>
<?php include __DIR__ . '/includes/footer.php'; ?>

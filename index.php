<?php
// index.php - landing page
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
startSession();

if (isLoggedIn()) {
    $cu = currentUser();
    if ($cu['role'] === 'admin') {
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'student/dashboard.php');
    }
    exit;
}

$pageTitle = 'Home';
include __DIR__ . '/includes/header.php';
?>
    <h1>Welcome to <?php echo SITE_NAME; ?></h1>
    <p>A centralized system for managing student organization memberships.</p>
    <p>
        <a href="<?php echo BASE_URL; ?>login.php">Login</a> |
        <a href="<?php echo BASE_URL; ?>register.php">Register as Student</a>
    </p>
<?php include __DIR__ . '/includes/footer.php'; ?>

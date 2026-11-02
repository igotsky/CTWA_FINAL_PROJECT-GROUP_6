<?php
// includes/header.php
// Shared page header + navigation. Expects $pageTitle to be set before include.

require_once __DIR__ . '/auth.php';
startSession();
$cu = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a class="brand" href="<?php echo BASE_URL; ?>index.php"><?php echo SITE_NAME; ?></a>
            <nav>
                <?php if (isLoggedIn()): ?>
                    <?php if ($cu['role'] === 'admin'): ?>
                        <a href="<?php echo BASE_URL; ?>admin/dashboard.php">Dashboard</a>
                        <a href="<?php echo BASE_URL; ?>admin/organizations.php">Organizations</a>
                        <a href="<?php echo BASE_URL; ?>admin/applications.php">Applications</a>
                        <a href="<?php echo BASE_URL; ?>admin/student_records.php">Students</a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>student/dashboard.php">Dashboard</a>
                        <a href="<?php echo BASE_URL; ?>student/organizations.php">Organizations</a>
                        <a href="<?php echo BASE_URL; ?>student/my_applications.php">My Applications</a>
                        <a href="<?php echo BASE_URL; ?>student/joined.php">Joined</a>
                        <a href="<?php echo BASE_URL; ?>student/profile.php">Profile</a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>logout.php">Logout (<?php echo htmlspecialchars($cu['username']); ?>)</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>login.php">Login</a>
                    <a href="<?php echo BASE_URL; ?>register.php">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container">
<?php
// includes/footer.php content is separate; this file only opens main.

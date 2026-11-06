<?php
// includes/header.php
// Shared page header + navigation. Expects $pageTitle to be set before include.

require_once __DIR__ . '/auth.php';
startSession();
$cu = currentUser();

$currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
function isActive($needle) {
    global $currentPath;
    return (strpos($currentPath, $needle) !== false) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script>
      function showMessage(text, type) {
        var overlay = document.createElement('div');
        overlay.className = 'msg-overlay';
        var box = document.createElement('div');
        box.className = 'msg-box';
        var msg = document.createElement('div');
        msg.className = 'msg-text';
        msg.textContent = text;
        var ok = document.createElement('button');
        ok.className = 'msg-ok';
        ok.textContent = 'OK';
        ok.addEventListener('click', function() {
          if (document.body.contains(overlay)) document.body.removeChild(overlay);
        });
        box.appendChild(msg);
        box.appendChild(ok);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
        if (type === 'success') {
          setTimeout(function() {
            if (document.body.contains(overlay)) document.body.removeChild(overlay);
          }, 3000);
        }
      }
      function showConfirm(text, onConfirm) {
        var overlay = document.createElement('div');
        overlay.className = 'msg-overlay';
        var box = document.createElement('div');
        box.className = 'msg-box';
        var msg = document.createElement('div');
        msg.className = 'msg-text';
        msg.textContent = text;
        var actions = document.createElement('div');
        actions.style.marginTop = '16px';
        actions.style.display = 'flex';
        actions.style.gap = '8px';
        actions.style.justifyContent = 'center';
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
        ok.textContent = 'Delete';
        ok.addEventListener('click', function() {
          if (document.body.contains(overlay)) document.body.removeChild(overlay);
          if (typeof onConfirm === 'function') onConfirm();
        });
        actions.appendChild(cancel);
        actions.appendChild(ok);
        box.appendChild(msg);
        box.appendChild(actions);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
      }
    </script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php if (isLoggedIn()): ?>
<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i data-lucide="graduation-cap"></i>
            <span><?php echo SITE_NAME; ?></span>
        </div>
        <nav class="sidebar-nav">
            <?php if ($cu['role'] === 'admin'): ?>
                <a class="nav-item <?php echo isActive('admin/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>admin/dashboard.php"><i data-lucide="layout-dashboard"></i><span>Dashboard</span></a>
                <a class="nav-item <?php echo isActive('admin/organizations.php'); ?>" href="<?php echo BASE_URL; ?>admin/organizations.php"><i data-lucide="building-2"></i><span>Organizations</span></a>
                <a class="nav-item <?php echo isActive('admin/applications.php'); ?>" href="<?php echo BASE_URL; ?>admin/applications.php"><i data-lucide="clipboard-list"></i><span>Applications</span></a>
                <a class="nav-item <?php echo isActive('admin/student_records.php'); ?>" href="<?php echo BASE_URL; ?>admin/student_records.php"><i data-lucide="users"></i><span>Student Records</span></a>
            <?php else: ?>
                <a class="nav-item <?php echo isActive('student/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>student/dashboard.php"><i data-lucide="layout-dashboard"></i><span>Dashboard</span></a>
                <a class="nav-item <?php echo isActive('student/organizations.php'); ?>" href="<?php echo BASE_URL; ?>student/organizations.php"><i data-lucide="building-2"></i><span>Organizations</span></a>
                <a class="nav-item <?php echo isActive('student/my_applications.php'); ?>" href="<?php echo BASE_URL; ?>student/my_applications.php"><i data-lucide="clipboard-list"></i><span>My Applications</span></a>
                <a class="nav-item <?php echo isActive('student/joined.php'); ?>" href="<?php echo BASE_URL; ?>student/joined.php"><i data-lucide="users"></i><span>Joined</span></a>
                <a class="nav-item <?php echo isActive('student/profile.php'); ?>" href="<?php echo BASE_URL; ?>student/profile.php"><i data-lucide="user"></i><span>Profile</span></a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <i data-lucide="user-circle"></i>
                <span><?php echo htmlspecialchars($cu['username']); ?></span>
            </div>
            <a class="nav-item logout" href="<?php echo BASE_URL; ?>logout.php"><i data-lucide="log-out"></i><span>Logout</span></a>
        </div>
    </aside>
    <div class="main-area">
        <header class="top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu"><i data-lucide="menu"></i></button>
            </div>
            <div class="header-right">
                <i data-lucide="bell"></i>
                <span class="current-date"><?php echo date('l, M d, Y'); ?></span>
            </div>
        </header>
        <main class="content">
<?php else: ?>
<div class="auth-layout">
    <div class="auth-brand">
        <div class="auth-brand-inner">
            <i data-lucide="graduation-cap"></i>
            <h1><?php echo SITE_NAME; ?></h1>
            <p>Student Organization Membership System</p>
        </div>
    </div>
    <div class="auth-panel<?php echo !empty($authWide) ? ' auth-panel-wide' : ''; ?>">
        <main class="content auth-content<?php echo !empty($authWide) ? ' auth-content-wide' : ''; ?>">
<?php endif; ?>

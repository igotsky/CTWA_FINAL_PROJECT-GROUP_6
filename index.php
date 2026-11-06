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

header('Location: ' . BASE_URL . 'login.php');
exit;

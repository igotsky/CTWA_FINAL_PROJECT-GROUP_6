<?php
// forgot_password.php - simple demo: validate student number, show message
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$msg = 'Please submit the form.';
$type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sn = trim($_POST['student_number'] ?? '');
    if ($sn === '') {
        $msg = 'Please enter your student number.';
        $type = 'error';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM students WHERE student_number = ?');
        mysqli_stmt_bind_param($stmt, 's', $sn);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        if ($row) {
            $msg = 'A link has been sent to your email. Please check your inbox.';
            $type = 'success';
        } else {
            $msg = 'Student number not found in the system.';
            $type = 'error';
        }
    }
    header('Location: ' . BASE_URL . 'login.php?forgot=1&msg=' . urlencode($msg) . '&type=' . $type);
    exit;
}

header('Location: ' . BASE_URL . 'login.php');
exit;
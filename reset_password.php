<?php
// reset_password.php - backend for forgot-password flow (JSON responses)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'verify_student') {
    $sn = trim($_POST['student_number'] ?? '');
    if ($sn === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter your student number.']);
        exit;
    }
    $stmt = mysqli_prepare($conn, 'SELECT s.user_id FROM students s WHERE s.student_number = ?');
    mysqli_stmt_bind_param($stmt, 's', $sn);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Student number not found in the system.']);
        exit;
    }
    startSession();
    $_SESSION['reset_user_id'] = (int) $row['user_id'];
    echo json_encode(['success' => true, 'message' => 'Student number verified.']);
    exit;
}

if ($action === 'reset') {
    startSession();
    if (!isset($_SESSION['reset_user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }
    $userId = (int) $_SESSION['reset_user_id'];
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < MIN_PASSWORD_LENGTH) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.']);
        exit;
    }
    if ($newPass !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, 'UPDATE users SET password = ?, password_changed_at = CURRENT_TIMESTAMP WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $hash, $userId);
    if (mysqli_stmt_execute($stmt)) {
        unset($_SESSION['reset_user_id']);
        echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);

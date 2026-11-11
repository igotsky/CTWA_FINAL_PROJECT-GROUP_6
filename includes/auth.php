<?php
ob_start();
// includes/auth.php
// Session and authentication helpers.

require_once __DIR__ . '/../config/config.php';

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    // Obtain a DB connection
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $conn = $GLOBALS['conn'];
    } else {
        require_once __DIR__ . '/../includes/db.php';
        // $conn is now in local scope from the include
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return true; // can't verify, allow
        }
    }
    $stmt = mysqli_prepare($conn, 'SELECT password_changed_at FROM users WHERE id = ?');
    if (!$stmt) {
        return true;
    }
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    $current = $row ? ($row['password_changed_at'] ?? null) : null;

    if (!array_key_exists('pwd_changed_at', $_SESSION)) {
        // Legacy session (created before this feature): force re-login.
        logoutUser();
        return false;
    }
    if ($_SESSION['pwd_changed_at'] !== $current) {
        // Password changed since login -> force re-login
        logoutUser();
        return false;
    }
    return true;
}

function currentUser() {
    startSession();
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role'     => $_SESSION['role'] ?? null,
    ];
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (currentUser()['role'] !== 'admin') {
        header('Location: ' . BASE_URL . 'student/dashboard.php');
        exit;
    }
}

function requireStudent() {
    requireLogin();
    if (currentUser()['role'] !== 'student') {
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
        exit;
    }
}

function loginUser($conn, $username, $password) {
    $stmt = mysqli_prepare($conn, 'SELECT id, username, password, role, password_changed_at FROM users WHERE username = ?');
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($user && password_verify($password, $user['password'])) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['user_id']        = $user['id'];
        $_SESSION['username']       = $user['username'];
        $_SESSION['role']           = $user['role'];
        $_SESSION['pwd_changed_at'] = $user['password_changed_at'] ?? null;
        return $user;
    }
    return false;
}

function logoutUser() {
    startSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

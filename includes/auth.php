<?php
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
    return isset($_SESSION['user_id']);
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
    $stmt = mysqli_prepare($conn, 'SELECT id, username, password, role FROM users WHERE username = ?');
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($user && password_verify($password, $user['password'])) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
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

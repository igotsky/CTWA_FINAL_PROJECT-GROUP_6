<?php
// includes/db.php
// Creates the MySQL connection used by all pages.

require_once __DIR__ . '/../config/config.php';

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Use UTF-8 for proper character handling.
mysqli_set_charset($conn, 'utf8mb4');

// Ensure an administrator account exists (auto-seed on first run).
// This avoids requiring a manual SQL insert for the initial admin login.
$usersTableCheck = mysqli_query($conn, "SELECT 1 FROM users LIMIT 1");
if ($usersTableCheck !== false) {
    $countResult = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users");
    $countRow = mysqli_fetch_assoc($countResult);
    if ((int) $countRow['c'] === 0) {
        $adminUser = 'admin';
        $adminPass = password_hash('Admin123!', PASSWORD_DEFAULT);
        $adminRole = 'admin';
        $seedStmt = mysqli_prepare($conn, 'INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($seedStmt, 'sss', $adminUser, $adminPass, $adminRole);
        mysqli_stmt_execute($seedStmt);
        mysqli_stmt_close($seedStmt);
    }
    mysqli_free_result($usersTableCheck);
    mysqli_free_result($countResult);
}

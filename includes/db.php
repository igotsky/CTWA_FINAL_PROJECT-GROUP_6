<?php
// includes/db.php
// Creates the MySQL connection used by all pages.

require_once __DIR__ . '/../config/config.php';

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Ensure password_changed_at column exists (idempotent; ignore errors)
try { mysqli_query($conn, "ALTER TABLE users ADD COLUMN password_changed_at TIMESTAMP NULL"); } catch (mysqli_sql_exception $e) { /* already exists */ }

// Ensure app tables exist (self-healing for installs that haven't run migrations)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    code VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS org_allowed_courses (
    organization_id INT NOT NULL,
    course VARCHAR(80) NOT NULL,
    PRIMARY KEY (organization_id, course),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS registration_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    coe_image VARCHAR(255) DEFAULT NULL,
    status ENUM('Pending','Approved','Rejected','Deleted') NOT NULL DEFAULT 'Pending',
    rejection_reason VARCHAR(255) NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS org_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    author_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES students(id) ON DELETE CASCADE
)");

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

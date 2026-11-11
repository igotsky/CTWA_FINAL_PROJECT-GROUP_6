-- database/campushub.sql
-- Run this in phpMyAdmin (Import) to create the student_org_system database and tables.
-- The initial administrator account (username: admin, password: Admin123!) is
-- created automatically by the application on first run (see includes/db.php).

CREATE DATABASE IF NOT EXISTS student_org_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE student_org_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    password_changed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_number VARCHAR(30) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male','Female') NULL,
    course VARCHAR(80) NULL,
    year_level TINYINT NOT NULL,
    section VARCHAR(20) NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    min_year_level TINYINT NULL,
    max_year_level TINYINT NULL,
    allowed_gender ENUM('Any','Male','Female') NOT NULL DEFAULT 'Any',
    max_members INT NULL,
    application_deadline DATE NULL,
    auto_approve TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS org_allowed_courses (
    organization_id INT NOT NULL,
    course VARCHAR(80) NOT NULL,
    PRIMARY KEY (organization_id, course),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS registration_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    coe_image VARCHAR(255) DEFAULT NULL,
    status ENUM('Pending','Approved','Rejected','Deleted') NOT NULL DEFAULT 'Pending',
    rejection_reason VARCHAR(255) NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    organization_id INT NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
    role ENUM('Member','Moderator') NOT NULL DEFAULT 'Member',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

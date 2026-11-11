-- database/migration_org_requirements.sql
-- Run this ONCE on an existing database to add org requirements + course presets + student gender/section + registration requests.
-- Safe to re-run? Columns use ADD COLUMN; will error if already present. Run only once.

USE student_org_system;

-- Student profile additions
ALTER TABLE students
  ADD COLUMN gender ENUM('Male','Female','Other') NULL AFTER last_name,
  ADD COLUMN section VARCHAR(20) NULL AFTER year_level;

-- Organization requirements / capacity / deadline / auto-approve
ALTER TABLE organizations
  ADD COLUMN min_year_level TINYINT NULL,
  ADD COLUMN max_year_level TINYINT NULL,
  ADD COLUMN allowed_gender ENUM('Any','Male','Female') NOT NULL DEFAULT 'Any',
  ADD COLUMN max_members INT NULL,
  ADD COLUMN application_deadline DATE NULL,
  ADD COLUMN auto_approve TINYINT(1) NOT NULL DEFAULT 0;

-- Course allow-list (empty = all courses allowed)
CREATE TABLE IF NOT EXISTS org_allowed_courses (
  organization_id INT NOT NULL,
  course VARCHAR(80) NOT NULL,
  PRIMARY KEY (organization_id, course),
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

-- Courses preset table
CREATE TABLE IF NOT EXISTS courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  code VARCHAR(20) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cancelled status for memberships
ALTER TABLE memberships
  MODIFY status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending';

-- Allow NULL course (so deleting a course clears it from students)
ALTER TABLE students MODIFY course VARCHAR(80) NULL;

-- Registration requests
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

-- Password change tracking (for session invalidation)
ALTER TABLE users ADD COLUMN password_changed_at TIMESTAMP NULL;
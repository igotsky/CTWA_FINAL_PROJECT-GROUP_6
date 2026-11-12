# CampusHub – Student Organization Membership System

---

## SYSTEM DESCRIPTION

***CampusHub***

- A PHP and MySQL web application for student organization membership.
- Students can submit registration requests, browse organizations, check eligibility, and apply for membership.
- Administrators manage student records, organizations, courses, and applications.
- Approved organization moderators can help manage their organization's applications, members, and announcements.

**In short:** CampusHub manages the process from student registration and organization applications through membership review.

---

## TECHNOLOGIES USED

- HTML and CSS – Structure and style the website pages.
- JavaScript – Adds page interactions, confirmation dialogs, tabs, modals, and live count updates.
- PHP – Handles login, validation, eligibility checks, and system operations.
- MySQL – Stores accounts, students, organizations, courses, applications, and related records.
- XAMPP – Provides the local Apache and MySQL environment.

**In short:** The website combines an interactive frontend with PHP processing and MySQL data storage.

---

## MAIN FEATURES

***Administrator***

- Review student registration requests and manage student records.
- Add, edit, and delete organizations and courses.
- Review and update membership applications.
- Manage organization members and assign moderators.
- View dashboard statistics and pending request counts.

**In short:** Administrators manage the records and decisions that keep the membership process organized.

***Student***

- Submit a registration request with a COE document.
- Browse organizations and view eligibility requirements.
- Apply for membership and check application status.
- View joined organizations and announcements.
- Update profile information and manage a password.
- Manage an organization when assigned as its moderator.

**In short:** Students use CampusHub to find organizations, apply, and participate in their memberships.

---

## DATABASE OVERVIEW

The `student_org_system` database stores user accounts, student records, organizations, course restrictions, registration requests, memberships, and announcements. Related tables use primary and foreign keys to connect accounts, students, organizations, and membership records.

**In short:** The database keeps the information needed to connect students with organizations and track their requests.

---

## SECURITY AND DATA HANDLING

- Pages check whether a user is logged in and has the correct role.
- Login verifies account credentials using PHP password verification.
- Registration validates required details, email format, password length and confirmation, and COE file type and size.
- Organization applications are checked against course, year level, gender, deadline, and member capacity rules.
- The project uses prepared statements for database operations and escapes displayed values in many pages.

**In short:** CampusHub checks user roles and validates important information during registration and organization applications.

---

## PROJECT STRUCTURE

- `admin/` – Administrator dashboards and management pages.
- `student/` – Student pages and moderator tools.
- `assets/css/` – Shared website styling.
- `assets/js/` – JavaScript asset folder; the current `main.js` is a placeholder, while active scripts are embedded in several PHP pages.
- `config/` – Database and site configuration.
- `database/` – Database creation and migration SQL files.
- `includes/` – Shared database, authentication, eligibility, header, and footer code.

**In short:** The project groups the pages and shared code by their role in the system.

---

## PRESENTATION DEMONSTRATION

- Submit a student registration request and show its pending status.
- Browse organizations and demonstrate an eligibility check.
- Submit an application and show it in the administrator or moderator review page.
- Approve or reject the application and show the updated status.
- Point out the related records in MySQL and the JavaScript count or confirmation interactions.

**In short:** Demonstrate a complete action from the user interface through PHP processing to the database and review result.

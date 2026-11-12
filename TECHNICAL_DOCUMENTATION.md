# CampusHub – Technical Documentation

---

## SYSTEM OVERVIEW

- HTML and CSS build the pages and layouts.
- JavaScript adds interface interactions.
- PHP handles requests, validation, access checks, and database operations.
- MySQL stores the system records.

**In short:** CampusHub connects user actions on the website to PHP processes and MySQL records.

---

## 1. PHP BACKEND LOGIC AND STRUCTURE

- Shared files in `includes/` provide the database connection, authentication, eligibility checks, and common page layout.
- `requireAdmin()` and `requireStudent()` protect pages according to the signed-in user's role.
- PHP validates registration details and processes student, organization, course, and membership updates.
- `checkEligibility()` checks course, year level, gender, application deadline, and organization capacity before an application is accepted.
- Administrators and authorized organization moderators can review applications.

**In short:** PHP applies the system rules and controls which actions each user can perform.

---

## 2. MYSQL DATABASE DESIGN AND INTEGRATION

**Database:** `student_org_system`

Main records are stored in these tables:

- `users` – Login accounts and roles.
- `students` – Student details linked to user accounts.
- `organizations` – Organization details and eligibility settings.
- `memberships` – Student applications and membership status.
- `registration_requests` – Student registration submissions awaiting review.
- `courses` and `org_allowed_courses` – Available courses and organization course restrictions.
- `org_announcements` – Announcements linked to organizations and their student authors.

The SQL file defines the main account, student, organization, course restriction, registration request, and membership tables. `includes/db.php` also creates supporting course and announcement tables when the application connects. Primary keys identify records; foreign keys connect related records and support deletion rules. Unique fields prevent duplicate usernames, student numbers, organization names, and course names.

**In short:** MySQL stores the system's records and relationships, allowing PHP pages to retrieve and update connected information.

---

## 3. DATA HANDLING AND SECURITY

- Role checks restrict administrator and student pages.
- Login uses `password_verify()` to check account password hashes, and successful login regenerates the session ID.
- Registration checks required names, email format, minimum password length, password confirmation, and the COE upload type and size.
- Eligibility checks run before membership applications are recorded.
- The source uses prepared statements for many data operations and escapes many values before displaying them.

**In short:** The system checks user roles, validates submitted information, and verifies account passwords during login.

---

## 4. JAVASCRIPT FUNCTIONALITY AND INTERACTIVITY

- Organization management tabs switch panels without loading a new page.
- Confirmation dialogs help prevent accidental approvals, rejections, removals, and deletions.
- Modals show organization members and allow announcement editing.
- `fetch()` sends application decisions and announcement deletions to PHP handlers.
- Periodic requests update pending registration, application, and member count badges; some pages reload when new requests arrive.
- `assets/js/main.js` is currently a placeholder. The active JavaScript is embedded in PHP pages and shared header/footer files.

**In short:** JavaScript provides tabs, dialogs, modals, background requests, and automatic count updates in the website.

---

## 5. HTML/CSS STRUCTURE AND USER EXPERIENCE

- Administrator and student pages are grouped in separate folders.
- Shared headers and footers provide common navigation and page layout.
- `assets/css/style.css` contains the shared colors, forms, buttons, dashboards, tables, and navigation styles.
- The stylesheet includes a mobile layout adjustment for registration form columns and visible focus styles for buttons.

**In short:** Shared layouts and styles keep pages consistent, with responsive behavior for the registration form.

---

## 6. SYSTEM FUNCTIONALITY AND COMPLETENESS

1. **Registration request:** A student submits personal details and a COE file. The request is stored as pending.
2. **Administrator review:** An administrator reviews the request and can add the student record and account. Approving a request alone does not automatically create the account.
3. **Organization application:** A student browses organizations. PHP checks eligibility before creating a membership record.
4. **Application review:** An administrator or authorized moderator updates the application status. Capacity is checked before approval.
5. **Membership management:** Approved members can view joined organizations and announcements. Moderators can manage their organization's applications, members, and announcements.

**In short:** The system covers the core registration, application, review, and membership workflows.

---

## 7. PRESENTATION DEMONSTRATION

To show frontend and backend integration:

1. Submit a student registration request and explain the validation and pending database record.
2. Show an organization and its eligibility requirements.
3. Submit an application and explain the PHP eligibility check and membership record.
4. Review the application as an administrator or moderator and update its status.
5. Show the refreshed status or count badge and identify the related MySQL tables.

**In short:** Follow one request from the page, through PHP validation and MySQL storage, to the final review result.

---

## IMPORTANT FILES

- `config/config.php` – Database and site settings.
- `includes/db.php` – MySQL connection and supporting table setup.
- `includes/auth.php` – Login sessions and role checks.
- `includes/eligibility.php` – Organization application rules.
- `admin/` – Student, organization, course, and application management.
- `student/` – Organization browsing, applications, memberships, and moderator tools.
- `assets/css/style.css` – Shared styling.

**In short:** These files connect configuration, access control, eligibility, database work, and the user-facing pages.

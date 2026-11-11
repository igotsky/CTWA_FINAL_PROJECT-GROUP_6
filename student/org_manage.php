<?php
// student/org_manage.php - Moderator management for their organization
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireStudent();

// Prevent caching of POST responses to avoid "Confirm form resubmission"
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$cu = currentUser();
$studentId = 0;
$stmt = mysqli_prepare($conn, 'SELECT id FROM students WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
if ($row) { $studentId = (int) $row['id']; }
mysqli_stmt_close($stmt);

$orgId = (int) ($_GET['org_id'] ?? 0);
$tab = $_GET['tab'] ?? 'overview';

// Flash message handling
$message = '';
$error = '';
$hadFlash = false;
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
    $hadFlash = true;
} elseif (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = trim($_GET['msg']);
    $hadFlash = true;
}

// Verify student is a moderator of this org
$isModerator = false;
if ($studentId > 0 && $orgId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT role FROM memberships WHERE student_id = ? AND organization_id = ? AND status = 'Approved'");
    mysqli_stmt_bind_param($stmt, 'ii', $studentId, $orgId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if ($row && $row['role'] === 'Moderator') {
        $isModerator = true;
    }
}

if (!$isModerator) {
    header('Location: ' . BASE_URL . 'student/joined.php?error=' . urlencode('Not authorized to manage this organization.'));
    exit;
}

// Fetch org details
$org = null;
$stmt = mysqli_prepare($conn, 'SELECT id, name, description, min_year_level, max_year_level, allowed_gender, max_members, application_deadline, auto_approve FROM organizations WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $orgId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$org = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$org) {
    header('Location: ' . BASE_URL . 'student/joined.php?error=' . urlencode('Organization not found.'));
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Announcements CRUD
    if ($action === 'ann_create') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($title === '' || $body === '') {
            $error = 'Title and body are required.';
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO org_announcements (organization_id, author_id, title, body) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'iiss', $orgId, $studentId, $title, $body);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_msg'] = 'Announcement created.';
            } else {
                $error = 'Failed to create announcement.';
            }
            mysqli_stmt_close($stmt);
        }
        // PRG: redirect to clear POST data
        header('Location: ' . BASE_URL . 'student/org_manage.php?org_id=' . $orgId . '&tab=announcements');
        exit;
    } elseif ($action === 'ann_update') {
        $annId = (int) ($_POST['ann_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($annId > 0 && $title !== '' && $body !== '') {
            $stmt = mysqli_prepare($conn, 'UPDATE org_announcements SET title = ?, body = ? WHERE id = ? AND organization_id = ?');
            mysqli_stmt_bind_param($stmt, 'ssii', $title, $body, $annId, $orgId);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_msg'] = 'Announcement updated.';
            } else {
                $error = 'Failed to update announcement.';
            }
            mysqli_stmt_close($stmt);
        }
        // PRG: redirect to clear POST data
        header('Location: ' . BASE_URL . 'student/org_manage.php?org_id=' . $orgId . '&tab=announcements');
        exit;
    } elseif ($action === 'ann_delete') {
        $annId = (int) ($_POST['ann_id'] ?? 0);
        if ($annId > 0) {
            $stmt = mysqli_prepare($conn, 'DELETE FROM org_announcements WHERE id = ? AND organization_id = ?');
            mysqli_stmt_bind_param($stmt, 'ii', $annId, $orgId);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_msg'] = 'Announcement deleted.';
            } else {
                $error = 'Failed to delete announcement.';
            }
            mysqli_stmt_close($stmt);
        }
        // PRG: redirect to clear POST data
        header('Location: ' . BASE_URL . 'student/org_manage.php?org_id=' . $orgId . '&tab=announcements');
        exit;
    }

    // Member management
    elseif ($action === 'member_promote') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        if ($memberId > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE memberships SET role = 'Moderator' WHERE student_id = ? AND organization_id = ? AND status = 'Approved'");
            mysqli_stmt_bind_param($stmt, 'ii', $memberId, $orgId);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_msg'] = 'Member promoted to Moderator.';
            } else {
                $error = 'Failed to promote member.';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'member_demote') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        if ($memberId > 0) {
            // Prevent demoting the last moderator
            $checkStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM memberships WHERE organization_id = ? AND role = 'Moderator' AND status = 'Approved'");
            mysqli_stmt_bind_param($checkStmt, 'i', $orgId);
            mysqli_stmt_execute($checkStmt);
            $checkRes = mysqli_stmt_get_result($checkStmt);
            $checkRow = mysqli_fetch_assoc($checkRes);
            mysqli_stmt_close($checkStmt);
            if ((int)$checkRow['c'] <= 1) {
                $error = 'Cannot demote the only moderator.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE memberships SET role = 'Member' WHERE student_id = ? AND organization_id = ? AND status = 'Approved'");
                mysqli_stmt_bind_param($stmt, 'ii', $memberId, $orgId);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['flash_msg'] = 'Moderator demoted to Member.';
                } else {
                    $error = 'Failed to demote moderator.';
                }
                mysqli_stmt_close($stmt);
            }
        }
        // PRG: redirect to clear POST data
        header('Location: ' . BASE_URL . 'student/org_manage.php?org_id=' . $orgId . '&tab=members');
        exit;
    } elseif ($action === 'member_remove') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        if ($memberId > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE memberships SET status = 'Cancelled' WHERE student_id = ? AND organization_id = ? AND status = 'Approved'");
            mysqli_stmt_bind_param($stmt, 'ii', $memberId, $orgId);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_msg'] = 'Member removed from organization.';
            } else {
                $error = 'Failed to remove member.';
            }
            mysqli_stmt_close($stmt);
        }
        // PRG: redirect to clear POST data
        header('Location: ' . BASE_URL . 'student/org_manage.php?org_id=' . $orgId . '&tab=members');
        exit;
    }

    // Application management
    elseif ($action === 'app_approve') {
        $appId = (int) ($_POST['app_id'] ?? 0);
        if ($appId > 0) {
            // Check capacity
            $oStmt = mysqli_prepare($conn, 'SELECT max_members FROM organizations WHERE id = ?');
            mysqli_stmt_bind_param($oStmt, 'i', $orgId);
            mysqli_stmt_execute($oStmt);
            $oRes = mysqli_stmt_get_result($oStmt);
            $oRow = mysqli_fetch_assoc($oRes);
            mysqli_stmt_close($oStmt);
            $maxMembers = $oRow ? $oRow['max_members'] : null;

            if ($maxMembers !== null) {
                $cStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM memberships WHERE organization_id = ? AND status = 'Approved'");
                mysqli_stmt_bind_param($cStmt, 'i', $orgId);
                mysqli_stmt_execute($cStmt);
                $cRes = mysqli_stmt_get_result($cStmt);
                $cRow = mysqli_fetch_assoc($cRes);
                mysqli_stmt_close($cStmt);
                if ((int)$cRow['c'] >= (int)$maxMembers) {
                    $error = 'Cannot approve: organization is at full capacity.';
                }
            }

            if ($error === '') {
                $stmt = mysqli_prepare($conn, "UPDATE memberships SET status = 'Approved', role = 'Member' WHERE id = ? AND organization_id = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $appId, $orgId);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['flash_msg'] = 'Application approved.';
                } else {
                    $error = 'Failed to approve application.';
                }
                mysqli_stmt_close($stmt);
            }
        }
        // PRG: redirect to clear POST data
        header('Location: ' . BASE_URL . 'student/org_manage.php?org_id=' . $orgId . '&tab=applications');
        exit;
    } elseif ($action === 'app_reject') {
        $appId = (int) ($_POST['app_id'] ?? 0);
        if ($appId > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE memberships SET status = 'Rejected' WHERE id = ? AND organization_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $appId, $orgId);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['flash_msg'] = 'Application rejected.';
            } else {
                $error = 'Failed to reject application.';
            }
            mysqli_stmt_close($stmt);
        }
        // PRG: redirect to clear POST data
        header('Location: ' . BASE_URL . 'student/org_manage.php?org_id=' . $orgId . '&tab=applications');
        exit;
    }

    // Org settings update
    elseif ($action === 'update_settings') {
        $description = trim($_POST['description'] ?? '');
        $minYear = (int) ($_POST['min_year_level'] ?? 0);
        $maxYear = (int) ($_POST['max_year_level'] ?? 0);
        $allowedGender = $_POST['allowed_gender'] ?? 'Any';
        $maxMembers = $_POST['max_members'] ?? '';
        $deadline = $_POST['application_deadline'] ?? '';
        $autoApprove = isset($_POST['auto_approve']) ? 1 : 0;

        if ($minYear < 1) $minYear = null;
        if ($maxYear < 1 || $maxYear > 4) $maxYear = null;
        if ($maxMembers === '' || (int)$maxMembers <= 0) $maxMembers = null;
        if ($deadline === '') $deadline = null;
        if (!in_array($allowedGender, ['Any', 'Male', 'Female'], true)) $allowedGender = 'Any';

        $stmt = mysqli_prepare($conn, 'UPDATE organizations SET description = ?, min_year_level = ?, max_year_level = ?, allowed_gender = ?, max_members = ?, application_deadline = ?, auto_approve = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'siissisi', $description, $minYear, $maxYear, $allowedGender, $maxMembers, $deadline, $autoApprove, $orgId);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_msg'] = 'Organization settings updated.';
            // Refresh org data
            $stmt = mysqli_prepare($conn, 'SELECT id, name, description, min_year_level, max_year_level, allowed_gender, max_members, application_deadline, auto_approve FROM organizations WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $orgId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $org = mysqli_fetch_assoc($res);
        } else {
            $error = 'Failed to update settings.';
        }
        mysqli_stmt_close($stmt);
        // PRG: redirect to clear POST data
        header('Location: ' . BASE_URL . 'student/org_manage.php?org_id=' . $orgId . '&tab=settings');
        exit;
    }
}

// Fetch data for tabs
$members = [];
$pendingApps = [];
$announcements = [];

if ($studentId > 0 && $orgId > 0) {
    // Members
    $stmt = mysqli_prepare($conn, "SELECT m.student_id, m.role, m.applied_at, s.student_number, s.first_name, s.middle_name, s.last_name, s.course, s.year_level
        FROM memberships m JOIN students s ON m.student_id = s.id
        WHERE m.organization_id = ? AND m.status = 'Approved' ORDER BY m.role DESC, s.last_name, s.first_name");
    mysqli_stmt_bind_param($stmt, 'i', $orgId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $members[] = $r;
    }
    mysqli_stmt_close($stmt);

    // Pending applications
    $stmt = mysqli_prepare($conn, "SELECT m.id, m.applied_at, s.student_number, s.first_name, s.middle_name, s.last_name, s.course, s.year_level, s.gender
        FROM memberships m JOIN students s ON m.student_id = s.id
        WHERE m.organization_id = ? AND m.status = 'Pending' ORDER BY m.applied_at");
    mysqli_stmt_bind_param($stmt, 'i', $orgId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) { $pendingApps[] = $r; }
    mysqli_stmt_close($stmt);

    // Announcements
    $stmt = mysqli_prepare($conn, "SELECT a.*, s.first_name, s.last_name FROM org_announcements a JOIN students s ON a.author_id = s.id WHERE a.organization_id = ? ORDER BY a.created_at DESC");
    mysqli_stmt_bind_param($stmt, 'i', $orgId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) { $announcements[] = $r; }
    mysqli_stmt_close($stmt);
}

$pageTitle = 'Manage: ' . htmlspecialchars($org['name']);
include __DIR__ . '/../includes/header.php';
?>
    <h1><?php echo htmlspecialchars($org['name']); ?></h1>
    <p style="color:var(--text-secondary);">Moderator Dashboard</p>

    <?php if ($message !== ''): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="org-tabs">
        <button type="button" class="org-tab <?php echo $tab === 'overview' ? 'active' : ''; ?>" data-tab="overview" tabindex="-1">Overview</button>
        <button type="button" class="org-tab <?php echo $tab === 'members' ? 'active' : ''; ?>" data-tab="members" tabindex="-1">Members<span class="tab-badge" id="membersBadge" style="display:none;"></span></button>
        <button type="button" class="org-tab <?php echo $tab === 'applications' ? 'active' : ''; ?>" data-tab="applications" tabindex="-1">Applications<span class="tab-badge" id="appsBadge" style="display:none;"></span></button>
        <button type="button" class="org-tab <?php echo $tab === 'announcements' ? 'active' : ''; ?>" data-tab="announcements" tabindex="-1">Announcements</button>
        <button type="button" class="org-tab <?php echo $tab === 'settings' ? 'active' : ''; ?>" data-tab="settings" tabindex="-1">Settings</button>
    </div>

    <div class="org-tab-panel <?php echo $tab === 'overview' ? 'active' : ''; ?>" id="tab-overview">
        <div class="card">
            <h3>Organization Details</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($org['name']); ?></p>
            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($org['description'] ?? '—')); ?></p>
            <p><strong>Year Range:</strong> <?php echo ($org['min_year_level'] ?? '—') . ' - ' . ($org['max_year_level'] ?? '—'); ?></p>
            <p><strong>Allowed Gender:</strong> <?php echo htmlspecialchars($org['allowed_gender'] ?? 'Any'); ?></p>
            <p><strong>Max Members:</strong> <?php echo $org['max_members'] ?? 'Unlimited'; ?></p>
            <p><strong>Application Deadline:</strong> <?php echo $org['application_deadline'] ?? 'None'; ?></p>
            <p><strong>Auto-approve:</strong> <?php echo (int)$org['auto_approve'] === 1 ? 'Yes' : 'No'; ?></p>
            <hr>
            <p><strong>Total Members:</strong> <?php echo count($members); ?></p>
            <p><strong>Pending Applications:</strong> <?php echo count($pendingApps); ?></p>
            <p><strong>Announcements:</strong> <?php echo count($announcements); ?></p>
        </div>
    </div>

    <div class="org-tab-panel <?php echo $tab === 'members' ? 'active' : ''; ?>" id="tab-members">
        <h3>Approved Members</h3>
        <?php if (empty($members)): ?>
            <p>No members yet.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Student No.</th><th>Name</th><th>Course/Year</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td><?php echo htmlspecialchars($m['student_number']); ?></td>
                    <td><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['middle_name'] . ' ' . $m['last_name']); ?></td>
                    <td><?php echo htmlspecialchars(($m['course'] ?? '—') . ' - ' . $m['year_level']); ?></td>
                    <td><span class="badge <?php echo strtolower($m['role']); ?>"><?php echo htmlspecialchars($m['role']); ?></span></td>
                    <td><?php echo htmlspecialchars($m['applied_at']); ?></td>
                    <td>
                        <?php if ((int)$m['student_id'] === $studentId): ?>
                            <span style="color:var(--text-secondary);">You</span>
                        <?php else: ?>
                            <?php if ($m['role'] === 'Moderator'): ?>
                                <form method="post" action="" style="display:inline;" autocomplete="off">
                                    <input type="hidden" name="action" value="member_demote">
                                    <input type="hidden" name="member_id" value="<?php echo (int) $m['student_id']; ?>">
                                    <button type="submit" class="btn-secondary" style="padding:4px 8px; font-size:0.8rem;">Demote</button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="btn-danger" style="padding:4px 8px; font-size:0.8rem;" data-member-id="<?php echo (int) $m['student_id']; ?>" data-member-name="<?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>" onclick="confirmRemoveMember(this);">Remove</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="org-tab-panel <?php echo $tab === 'applications' ? 'active' : ''; ?>" id="tab-applications">
        <h3>Pending Applications</h3>
        <?php if (empty($pendingApps)): ?>
            <p>No pending applications.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Student No.</th><th>Name</th><th>Course/Year</th><th>Gender</th><th>Applied</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pendingApps as $a): ?>
                <tr>
                    <td><?php echo htmlspecialchars($a['student_number']); ?></td>
                    <td><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['middle_name'] . ' ' . $a['last_name']); ?></td>
                    <td><?php echo htmlspecialchars(($a['course'] ?? '—') . ' - ' . $a['year_level']); ?></td>
                    <td><?php echo htmlspecialchars($a['gender'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($a['applied_at']); ?></td>
                    <td>
                        <button type="button" class="btn-secondary" style="padding:4px 8px; font-size:0.8rem;" onclick="appAction(<?php echo (int) $a['id']; ?>, 'approve')">Approve</button>
                        <button type="button" class="btn-danger" style="padding:4px 8px; font-size:0.8rem;" onclick="appAction(<?php echo (int) $a['id']; ?>, 'reject')">Reject</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="org-tab-panel <?php echo $tab === 'announcements' ? 'active' : ''; ?>" id="tab-announcements">
        <h3>Announcements</h3>
        <form method="post" action="" style="margin-bottom:20px; padding:16px; background:var(--card-bg); border-radius:var(--radius); border:1px solid var(--border);" autocomplete="off">
            <input type="hidden" name="action" value="ann_create">
            <label>Title *</label><br>
            <input type="text" name="title" required maxlength="150" style="width:100%; margin-bottom:8px;"><br>
            <label>Body *</label><br>
            <textarea name="body" required rows="4" style="width:100%; margin-bottom:8px;"></textarea><br>
            <button type="submit">Create Announcement</button>
        </form>

        <?php if (empty($announcements)): ?>
            <p>No announcements yet.</p>
        <?php else: ?>
            <?php foreach ($announcements as $a): ?>
            <div class="card" style="margin-bottom:12px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                    <h4 style="margin:0;"><?php echo htmlspecialchars($a['title']); ?></h4>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn-secondary" style="padding:4px 8px; font-size:0.75rem;" data-ann-id="<?php echo (int) $a['id']; ?>" data-ann-title="<?php echo htmlspecialchars($a['title']); ?>" data-ann-body="<?php echo htmlspecialchars($a['body']); ?>" onclick="editAnnouncement(this)">Edit</button>
                        <button type="button" class="btn-danger" style="padding:4px 8px; font-size:0.75rem;" data-ann-id="<?php echo (int) $a['id']; ?>" onclick="confirmDeleteAnnouncement(this)">Delete</button>
                    </div>
                </div>
                <p style="margin:8px 0;"><?php echo nl2br(htmlspecialchars($a['body'])); ?></p>
                <small style="color:var(--text-secondary);">By <?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?> · <?php echo htmlspecialchars($a['created_at']); ?></small>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="org-tab-panel <?php echo $tab === 'settings' ? 'active' : ''; ?>" id="tab-settings">
        <h3>Organization Settings</h3>
        <form method="post" action="" autocomplete="off">
            <input type="hidden" name="action" value="update_settings">
            <div class="card">
                <label>Description</label><br>
                <textarea name="description" rows="4" style="width:100%;"><?php echo htmlspecialchars($org['description'] ?? ''); ?></textarea><br><br>

                <label>Min Year Level</label><br>
                <select name="min_year_level" style="width:100%; max-width:200px;">
                    <option value="">— Any —</option>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($org['min_year_level'] ?? '') == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select><br><br>

                <label>Max Year Level</label><br>
                <select name="max_year_level" style="width:100%; max-width:200px;">
                    <option value="">— Any —</option>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($org['max_year_level'] ?? '') == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select><br><br>

                <label>Allowed Gender</label><br>
                <select name="allowed_gender" style="width:100%; max-width:200px;">
                    <option value="Any" <?php echo ($org['allowed_gender'] ?? 'Any') === 'Any' ? 'selected' : ''; ?>>Any</option>
                    <option value="Male" <?php echo ($org['allowed_gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($org['allowed_gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                </select><br><br>

                <label>Max Members (0 = unlimited)</label><br>
                <input type="number" name="max_members" min="0" value="<?php echo $org['max_members'] ?? ''; ?>" style="width:100%; max-width:200px;"><br><br>

                <label>Application Deadline</label><br>
                <input type="date" name="application_deadline" value="<?php echo $org['application_deadline'] ?? ''; ?>" style="width:100%; max-width:200px;"><br><br>

                <div style="display:flex; align-items:center; gap:8px; margin-top:16px;">
                    <input type="checkbox" name="auto_approve" value="1" <?php echo (int)($org['auto_approve'] ?? 0) === 1 ? 'checked' : ''; ?>>
                    <label>Auto-approve applications (if under capacity)</label>
                </div>

                <button type="submit">Save Settings</button>
            </div>
        </form>
    </div>

    <a href="<?php echo BASE_URL; ?>student/joined.php" class="btn btn-secondary" style="margin-top:20px; display:inline-block;">Back to Joined Organizations</a>

    <!-- Hidden form for member removal -->
    <form id="removeMemberForm" method="post" action="" style="display:none;" autocomplete="off">
        <input type="hidden" name="action" value="member_remove">
        <input type="hidden" name="member_id" id="removeMemberId">
    </form>

    <script>
      // Tab switching
      (function() {
        var tabs = document.querySelectorAll('.org-tab');
        var orgId = <?php echo (int) $orgId; ?>;
        var storageKey = 'orgMembersLastSeen_' + orgId;

        // Initialize members badge (red dot for new members)
        function updateMembersBadge() {
          var badge = document.getElementById('membersBadge');
          if (!badge) return;
          var lastSeen = parseInt(localStorage.getItem(storageKey) || '0', 10);
          fetch('<?php echo BASE_URL; ?>admin/app_count_org_members.php?org_id=' + orgId, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              var latestJoin = d.latest_join || 0;
              if (latestJoin > lastSeen) {
                badge.style.display = 'inline-block';
                badge.classList.add('dot');
                badge.textContent = '';
              } else {
                badge.style.display = 'none';
                badge.classList.remove('dot');
              }
            })
            .catch(function(e) { console.error('members badge fetch error:', e); });
        }

        // Initialize badges from server-side counts
        var initialAppsCount = <?php echo count($pendingApps); ?>;
        var lastAppsCount = initialAppsCount;

        function updateBadge(badgeId, count, isDot) {
          var badge = document.getElementById(badgeId);
          if (!badge) return;
          if (count > 0) {
            badge.style.display = 'inline-block';
            badge.textContent = isDot ? '' : count;
            badge.classList.toggle('dot', isDot);
          } else {
            badge.style.display = 'none';
            badge.classList.remove('dot');
          }
        }

        // Initialize apps badge (counter for pending applications) - sync with per-org endpoint
        var prevAppsCount = initialAppsCount;
        function updateAppsBadge() {
          var badge = document.getElementById('appsBadge');
          if (!badge) return;
          fetch('<?php echo BASE_URL; ?>admin/app_count_org.php?org_id=' + orgId, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              var count = d.pending || 0;
              updateBadge('appsBadge', count, false);
              if (count > prevAppsCount) {
                prevAppsCount = count;
                lastAppsCount = count;
                try { localStorage.setItem('appsLastSeen_' + orgId, String(count)); } catch (e) {}
                var appsTab = document.querySelector('.org-tab[data-tab="applications"]');
                var appsPanel = document.getElementById('tab-applications');
                if (appsTab && appsPanel && appsPanel.classList.contains('active')) {
                  window.location.reload();
                }
              } else {
                prevAppsCount = count;
                lastAppsCount = count;
                try { localStorage.setItem('appsLastSeen_' + orgId, String(count)); } catch (e) {}
              }
            })
            .catch(function(e) { console.error('apps badge fetch error:', e); });
        }

        // Initialize announcements badge
        function updateAnnouncementsBadge() {
          updateBadge('announcementsBadge', initialAnnouncementsCount, false);
        }

        tabs.forEach(function(tab) {
          tab.addEventListener('click', function(e) {
            e.stopImmediatePropagation();
            e.preventDefault();
            var target = tab.getAttribute('data-tab');
            tabs.forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            document.querySelectorAll('.org-tab-panel').forEach(function(p) { p.classList.remove('active'); });
            document.getElementById('tab-' + target).classList.add('active');

            // If members tab clicked, update last seen timestamp
            if (target === 'members') {
              localStorage.setItem(storageKey, Math.floor(Date.now() / 1000).toString());
              updateMembersBadge();
            }
          });
        });

        // Initial badge updates
        updateMembersBadge();
        updateBadge('appsBadge', initialAppsCount, false);

        // Clean up browser history to prevent "Confirm form resubmission" dialogs
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.href);
        }

        // Poll apps badge every 5 seconds
        setInterval(updateAppsBadge, 5000);
        // Poll members badge every 10 seconds
        setInterval(updateMembersBadge, 10000);
      })();

      // Custom confirmation for member removal
      function confirmRemoveMember(btn) {
        var memberId = btn.getAttribute('data-member-id');
        var memberName = btn.getAttribute('data-member-name');
        showConfirm('Remove ' + memberName + ' from this organization?', function() {
          document.getElementById('removeMemberId').value = memberId;
          document.getElementById('removeMemberForm').submit();
        }, 'Remove');
      }

      // Announcement edit modal
      function editAnnouncement(btn) {
        var annId = btn.getAttribute('data-ann-id');
        var annTitle = btn.getAttribute('data-ann-title');
        var annBody = btn.getAttribute('data-ann-body');
        document.querySelectorAll('.msg-overlay').forEach(function(o) { o.remove(); });
        var overlay = document.createElement('div');
        overlay.className = 'msg-overlay';
        var box = document.createElement('div');
        box.className = 'msg-box';
        box.style.maxWidth = '500px';
        var h4 = document.createElement('h4');
        h4.textContent = 'Edit Announcement';
        h4.style.margin = '0 0 16px';
        box.appendChild(h4);

        var form = document.createElement('form');
        form.method = 'post';
        var inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'action';
        inputAction.value = 'ann_update';
        form.appendChild(inputAction);
        var inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'ann_id';
        inputId.value = annId;
        form.appendChild(inputId);

        var labelTitle = document.createElement('label');
        labelTitle.textContent = 'Title *';
        form.appendChild(labelTitle);
        var inputTitle = document.createElement('input');
        inputTitle.type = 'text';
        inputTitle.name = 'title';
        inputTitle.required = true;
        inputTitle.maxLength = 150;
        inputTitle.value = annTitle;
        inputTitle.style.width = '100%';
        inputTitle.style.marginBottom = '8px';
        form.appendChild(inputTitle);

        var labelBody = document.createElement('label');
        labelBody.textContent = 'Body *';
        form.appendChild(labelBody);
        var textarea = document.createElement('textarea');
        textarea.name = 'body';
        textarea.required = true;
        textarea.rows = 4;
        textarea.value = annBody;
        textarea.style.width = '100%';
        textarea.style.marginBottom = '16px';
        form.appendChild(textarea);

        var actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.gap = '8px';
        actions.style.justifyContent = 'flex-end';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'msg-ok';
        cancel.style.background = '#fff';
        cancel.style.color = 'var(--text-primary)';
        cancel.style.border = '1px solid var(--border)';
        cancel.textContent = 'Cancel';
        cancel.addEventListener('click', function() {
          if (document.body.contains(overlay)) document.body.removeChild(overlay);
        });
        var submit = document.createElement('button');
        submit.type = 'submit';
        submit.className = 'msg-ok';
        submit.textContent = 'Save';
        actions.appendChild(cancel);
        actions.appendChild(submit);
        form.appendChild(actions);

        box.appendChild(form);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
      }

      // Delete announcement via AJAX
      function confirmDeleteAnnouncement(btn) {
        var annId = btn.getAttribute('data-ann-id');
        showConfirm('Delete this announcement?', function() {
          var formData = new FormData();
          formData.append('action', 'ann_delete');
          formData.append('ann_id', annId);
          fetch('<?php echo BASE_URL; ?>student/org_manage.php?org_id=<?php echo (int) $orgId; ?>&tab=announcements', {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'manual',
            body: formData
          })
          .then(function() {
            window.location.href = '<?php echo BASE_URL; ?>student/org_manage.php?org_id=<?php echo (int) $orgId; ?>&tab=announcements';
          })
          .catch(function(e) { console.error('ann delete error:', e); });
        }, 'Delete');
      }

      // Application approve/reject via AJAX
      function appAction(appId, action) {
        var actionText = action === 'approve' ? 'approve' : 'reject';
        showConfirm('Are you sure you want to ' + actionText + ' this application?', function() {
          var formData = new FormData();
          formData.append('action', action === 'approve' ? 'app_approve' : 'app_reject');
          formData.append('app_id', appId);
          fetch('<?php echo BASE_URL; ?>student/org_manage.php?org_id=<?php echo (int) $orgId; ?>&tab=applications', {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'manual',
            body: formData
          })
          .then(function() {
            window.location.href = '<?php echo BASE_URL; ?>student/org_manage.php?org_id=<?php echo (int) $orgId; ?>&tab=applications';
          })
          .catch(function(e) { console.error('app action error:', e); });
        }, 'Confirm');
      }
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
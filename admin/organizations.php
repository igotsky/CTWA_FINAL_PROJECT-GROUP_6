<?php
// admin/organizations.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';
requireAdmin();

// Ensure org_allowed_courses table exists (self-healing)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS org_allowed_courses (
    organization_id INT NOT NULL,
    course VARCHAR(80) NOT NULL,
    PRIMARY KEY (organization_id, course),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
)");

$message = '';
$error = '';

if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = trim($_GET['msg']);
}

$action = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $minYear = trim($_POST['min_year_level'] ?? '');
        $maxYear = trim($_POST['max_year_level'] ?? '');
        $allowedGender = $_POST['allowed_gender'] ?? 'Any';
        $maxMembers = trim($_POST['max_members'] ?? '');
        $deadline = trim($_POST['application_deadline'] ?? '');
        $autoApprove = isset($_POST['auto_approve']) ? 1 : 0;
        $allowedCourses = $_POST['allowed_courses'] ?? [];

        if ($name === '') {
            $error = 'Organization name is required.';
        } else {
            $minY = $minYear === '' ? null : (int)$minYear;
            $maxY = $maxYear === '' ? null : (int)$maxYear;
            $maxM = $maxMembers === '' ? null : (int)$maxMembers;
            $dl = $deadline === '' ? null : $deadline;
            if (!in_array($allowedGender, ['Any','Male','Female'], true)) $allowedGender = 'Any';

            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, 'INSERT INTO organizations (name, description, min_year_level, max_year_level, allowed_gender, max_members, application_deadline, auto_approve) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'ssiisisi', $name, $desc, $minY, $maxY, $allowedGender, $maxM, $dl, $autoApprove);
                mysqli_stmt_execute($stmt);
                $newId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                mysqli_query($conn, 'DELETE FROM org_allowed_courses WHERE organization_id = ' . (int)$newId);
                foreach ($allowedCourses as $c) {
                    $c = trim($c);
                    if ($c === '') continue;
                    $cs = mysqli_prepare($conn, 'INSERT IGNORE INTO org_allowed_courses (organization_id, course) VALUES (?, ?)');
                    mysqli_stmt_bind_param($cs, 'is', $newId, $c);
                    mysqli_stmt_execute($cs);
                    mysqli_stmt_close($cs);
                }
                mysqli_commit($conn);
                $message = 'Organization added.';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = 'Failed to add organization (name may already exist).';
            }
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $minYear = trim($_POST['min_year_level'] ?? '');
        $maxYear = trim($_POST['max_year_level'] ?? '');
        $allowedGender = $_POST['allowed_gender'] ?? 'Any';
        $maxMembers = trim($_POST['max_members'] ?? '');
        $deadline = trim($_POST['application_deadline'] ?? '');
        $autoApprove = isset($_POST['auto_approve']) ? 1 : 0;
        $allowedCourses = $_POST['allowed_courses'] ?? [];
        $confirmRemove = isset($_POST['confirm_remove']) && $_POST['confirm_remove'] === '1';

        if ($id <= 0 || $name === '') {
            $error = 'Invalid data.';
        } else {
            $minY = $minYear === '' ? null : (int)$minYear;
            $maxY = $maxYear === '' ? null : (int)$maxYear;
            $maxM = $maxMembers === '' ? null : (int)$maxMembers;
            $dl = $deadline === '' ? null : $deadline;
            if (!in_array($allowedGender, ['Any','Male','Female'], true)) $allowedGender = 'Any';

            // Fetch currently approved members with student data
            $affected = [];
            $memStmt = mysqli_prepare($conn, "SELECT m.id, s.first_name, s.last_name, s.course, s.year_level, s.gender
                    FROM memberships m JOIN students s ON m.student_id = s.id
                    WHERE m.organization_id = ? AND m.status = 'Approved'");
            mysqli_stmt_bind_param($memStmt, 'i', $id);
            mysqli_stmt_execute($memStmt);
            $memRes = mysqli_stmt_get_result($memStmt);
            while ($mrow = mysqli_fetch_assoc($memRes)) {
                $reasons = [];
                if ($minY !== null && (int)$mrow['year_level'] < $minY) $reasons[] = 'x';
                if ($maxY !== null && (int)$mrow['year_level'] > $maxY) $reasons[] = 'x';
                if ($allowedGender !== 'Any' && $mrow['gender'] !== $allowedGender) $reasons[] = 'x';
                if (!empty($allowedCourses) && !in_array($mrow['course'], $allowedCourses, true)) $reasons[] = 'x';
                if ($dl !== null && date('Y-m-d') > $dl) $reasons[] = 'x';
                if (!empty($reasons)) {
                    $affected[] = $mrow;
                }
            }
            mysqli_stmt_close($memStmt);

            if (!empty($affected) && !$confirmRemove) {
                // Render confirmation screen
                $pageTitle = 'Confirm Changes';
                include __DIR__ . '/../includes/header.php';
                ?>
                <h1>Confirm Organization Update</h1>
                <div class="alert error">
                    The following <?php echo count($affected); ?> member(s) will be removed because they no longer meet the eligibility requirements:
                </div>
                <ul>
                <?php foreach ($affected as $m): ?>
                    <li><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></li>
                <?php endforeach; ?>
                </ul>
                <form method="post" action="">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="confirm_remove" value="1">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
                    <input type="hidden" name="description" value="<?php echo htmlspecialchars($desc); ?>">
                    <input type="hidden" name="min_year_level" value="<?php echo $minY !== null ? (int)$minY : ''; ?>">
                    <input type="hidden" name="max_year_level" value="<?php echo $maxY !== null ? (int)$maxY : ''; ?>">
                    <input type="hidden" name="allowed_gender" value="<?php echo htmlspecialchars($allowedGender); ?>">
                    <input type="hidden" name="max_members" value="<?php echo $maxM !== null ? (int)$maxM : ''; ?>">
                    <input type="hidden" name="application_deadline" value="<?php echo htmlspecialchars($dl ?? ''); ?>">
                    <?php if ($autoApprove): ?><input type="hidden" name="auto_approve" value="1"><?php endif; ?>
                    <?php foreach ($allowedCourses as $c): ?>
                        <input type="hidden" name="allowed_courses[]" value="<?php echo htmlspecialchars($c); ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="btn-danger">Confirm &amp; Apply</button>
                    <a href="<?php echo BASE_URL; ?>admin/organizations.php?edit=<?php echo (int)$id; ?>" class="btn btn-secondary" style="margin-left:10px;">Cancel</a>
                </form>
                <?php include __DIR__ . '/../includes/footer.php'; ?>
                <?php exit; ?>
                <?php
            }

            // Apply update
            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, 'UPDATE organizations SET name = ?, description = ?, min_year_level = ?, max_year_level = ?, allowed_gender = ?, max_members = ?, application_deadline = ?, auto_approve = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'ssiisisii', $name, $desc, $minY, $maxY, $allowedGender, $maxM, $dl, $autoApprove, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_query($conn, 'DELETE FROM org_allowed_courses WHERE organization_id = ' . (int)$id);
                foreach ($allowedCourses as $c) {
                    $c = trim($c);
                    if ($c === '') continue;
                    $cs = mysqli_prepare($conn, 'INSERT IGNORE INTO org_allowed_courses (organization_id, course) VALUES (?, ?)');
                    mysqli_stmt_bind_param($cs, 'is', $id, $c);
                    mysqli_stmt_execute($cs);
                    mysqli_stmt_close($cs);
                }

                // Remove affected members if confirmed
                $removed = 0;
                if ($confirmRemove) {
                    $memStmt2 = mysqli_prepare($conn, "SELECT m.id, s.course, s.year_level, s.gender
                            FROM memberships m JOIN students s ON m.student_id = s.id
                            WHERE m.organization_id = ? AND m.status = 'Approved'");
                    mysqli_stmt_bind_param($memStmt2, 'i', $id);
                    mysqli_stmt_execute($memStmt2);
                    $memRes2 = mysqli_stmt_get_result($memStmt2);
                    while ($mrow = mysqli_fetch_assoc($memRes2)) {
                        $ineligible = false;
                        if ($minY !== null && (int)$mrow['year_level'] < $minY) $ineligible = true;
                        if ($maxY !== null && (int)$mrow['year_level'] > $maxY) $ineligible = true;
                        if ($allowedGender !== 'Any' && $mrow['gender'] !== $allowedGender) $ineligible = true;
                        if (!empty($allowedCourses) && !in_array($mrow['course'], $allowedCourses, true)) $ineligible = true;
                        if ($dl !== null && date('Y-m-d') > $dl) $ineligible = true;
                        if ($ineligible) {
                            $rm = mysqli_prepare($conn, "UPDATE memberships SET status = 'Cancelled' WHERE id = ?");
                            mysqli_stmt_bind_param($rm, 'i', $mrow['id']);
                            mysqli_stmt_execute($rm);
                            mysqli_stmt_close($rm);
                            $removed++;
                        }
                    }
                    mysqli_stmt_close($memStmt2);
                }

                mysqli_commit($conn);
                $message = 'Organization updated.';
                if ($removed > 0) $message .= ' ' . $removed . ' member(s) removed due to new eligibility rules.';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = 'Failed to update organization.';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, 'DELETE FROM organizations WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Organization deleted.';
            } else {
                $error = 'Failed to delete organization.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Redirect to list tab after successful add/update
if (($action === 'add' || $action === 'update') && $error === '' && $message !== '') {
    header('Location: ' . BASE_URL . 'admin/organizations.php?msg=' . urlencode($message));
    exit;
}

// Edit mode
$editOrg = null;
$editAllowedCourses = [];
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, 'SELECT id, name, description, min_year_level, max_year_level, allowed_gender, max_members, application_deadline, auto_approve FROM organizations WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $editOrg = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($editOrg) {
        $acs = mysqli_prepare($conn, 'SELECT course FROM org_allowed_courses WHERE organization_id = ?');
        mysqli_stmt_bind_param($acs, 'i', $editId);
        mysqli_stmt_execute($acs);
        $acr = mysqli_stmt_get_result($acs);
        while ($ar = mysqli_fetch_assoc($acr)) { $editAllowedCourses[] = $ar['course']; }
        mysqli_stmt_close($acs);
    }
}

// List
$orgs = [];
$res = mysqli_query($conn, "SELECT o.id, o.name, o.description, o.created_at,
        o.min_year_level, o.max_year_level, o.allowed_gender,
        o.max_members, o.application_deadline, o.auto_approve,
        (SELECT COUNT(*) FROM memberships m WHERE m.organization_id = o.id AND m.status = 'Approved') AS member_count
        FROM organizations o ORDER BY name");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) { $orgs[] = $row; }
    mysqli_free_result($res);
}

// List moderators
$mods = [];
$modSearch = trim($_GET['mod_search'] ?? '');
$modWhere = '';
$modParams = [];
$modParamTypes = '';
if ($modSearch !== '') {
    $modWhere = "WHERE m.role = 'Moderator' AND m.status = 'Approved' AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.middle_name LIKE ? OR s.student_number LIKE ?)";
    $like = '%' . $modSearch . '%';
    $modParams = [$like, $like, $like, $like];
    $modParamTypes = 'ssss';
} else {
    $modWhere = "WHERE m.role = 'Moderator' AND m.status = 'Approved'";
}
$modSql = "SELECT m.id, m.student_id, m.role, m.applied_at, o.id AS org_id, o.name AS org_name,
                  s.student_number, s.first_name, s.middle_name, s.last_name, s.course, s.year_level
           FROM memberships m
           JOIN students s ON m.student_id = s.id
           JOIN organizations o ON m.organization_id = o.id
           " . $modWhere . " ORDER BY s.last_name, s.first_name";
$modStmt = mysqli_prepare($conn, $modSql);
if (!empty($modParams)) {
    mysqli_stmt_bind_param($modStmt, $modParamTypes, ...$modParams);
}
mysqli_stmt_execute($modStmt);
$modRes = mysqli_stmt_get_result($modStmt);
while ($mr = mysqli_fetch_assoc($modRes)) { $mods[] = $mr; }
mysqli_stmt_close($modStmt);

$pageTitle = 'Manage Organizations';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Manage Organizations</h1>
    <?php if ($message !== ''): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php $defaultTab = $_GET['tab'] ?? ($editOrg ? 'add' : 'list'); ?>
    <div class="org-tabs">
        <button type="button" class="org-tab <?php echo $defaultTab === 'list' ? 'active' : ''; ?>" data-tab="list">Organization List</button>
        <button type="button" class="org-tab <?php echo $defaultTab === 'add' ? 'active' : ''; ?>" data-tab="add">Add Organization</button>
        <button type="button" class="org-tab <?php echo $defaultTab === 'mods' ? 'active' : ''; ?>" data-tab="mods">Moderators</button>
    </div>

    <div class="org-tab-panel <?php echo $defaultTab === 'list' ? 'active' : ''; ?>" id="tab-list">
        <h2>Organization List</h2>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Requirements</th><th>Members</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($orgs as $o): ?>
                <tr>
                    <td><?php echo (int) $o['id']; ?></td>
                    <td><?php echo htmlspecialchars($o['name']); ?></td>
                    <td><?php echo htmlspecialchars($o['description']); ?></td>
                    <td style="font-size:0.8rem; color:var(--text-secondary);">
                        <?php
                        $reqs = [];
                        if ($o['min_year_level'] !== null || $o['max_year_level'] !== null) {
                            $mn = $o['min_year_level'] !== null ? (int)$o['min_year_level'] : 1;
                            $mx = $o['max_year_level'] !== null ? (int)$o['max_year_level'] : 4;
                            $reqs[] = 'Yr ' . $mn . '-' . $mx;
                        }
                        if (!empty($o['allowed_gender']) && $o['allowed_gender'] !== 'Any') $reqs[] = $o['allowed_gender'];
                        $ac = getOrgAllowedCourses($conn, (int)$o['id']);
                        if (!empty($ac)) $reqs[] = implode(', ', $ac);
                        if (!empty($o['application_deadline'])) $reqs[] = 'Due ' . $o['application_deadline'];
                        if ($o['max_members'] !== null) $reqs[] = 'Cap ' . (int)$o['max_members'];
                        $summary = $reqs ? implode(' · ', $reqs) : 'Anyone';
                        if ((int)$o['auto_approve'] === 1) $summary .= ' · Auto-approve';
                        echo htmlspecialchars($summary);
                        ?>
                    </td>
                    <td><?php echo (int) $o['member_count']; ?><?php echo $o['max_members'] !== null ? '/' . (int)$o['max_members'] : ''; ?></td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>admin/org_members.php?org_id=<?php echo (int) $o['id']; ?>">Members</a> |
                        <a href="<?php echo BASE_URL; ?>admin/organizations.php?edit=<?php echo (int) $o['id']; ?>">Edit</a>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $o['id']; ?>">
                            <button type="button" class="btn-danger" onclick="confirmDelete(this.form, 'Delete this organization?', 'Delete');">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orgs)): ?>
                <tr><td colspan="6">No organizations yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="org-tab-panel <?php echo $defaultTab === 'add' ? 'active' : ''; ?>" id="tab-add">
        <div class="card">
            <h3><?php echo $editOrg ? 'Edit Organization' : 'Add Organization'; ?></h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="<?php echo $editOrg ? 'update' : 'add'; ?>">
                <?php if ($editOrg): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $editOrg['id']; ?>">
                <?php endif; ?>
                <label>Name *</label><br>
                <input type="text" name="name" required value="<?php echo $editOrg ? htmlspecialchars($editOrg['name']) : ''; ?>"><br>
                <label>Description</label><br>
                <textarea name="description" rows="3"><?php echo $editOrg ? htmlspecialchars($editOrg['description']) : ''; ?></textarea><br>

                <h4 style="margin-top:16px;">Eligibility Requirements</h4>
                <label>Min Year Level (blank = any)</label><br>
                <input type="number" name="min_year_level" min="1" max="4" value="<?php echo $editOrg && $editOrg['min_year_level'] !== null ? (int)$editOrg['min_year_level'] : ''; ?>"><br>
                <label>Max Year Level (blank = any)</label><br>
                <input type="number" name="max_year_level" min="1" max="4" value="<?php echo $editOrg && $editOrg['max_year_level'] !== null ? (int)$editOrg['max_year_level'] : ''; ?>"><br>
                <label>Allowed Gender</label><br>
                <select name="allowed_gender">
                    <?php $ag = $editOrg ? ($editOrg['allowed_gender'] ?? 'Any') : 'Any'; ?>
                    <option value="Any" <?php echo $ag === 'Any' ? 'selected' : ''; ?>>Any</option>
                    <option value="Male" <?php echo $ag === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $ag === 'Female' ? 'selected' : ''; ?>>Female</option>
                </select><br>
                <label>Allowed Courses (none checked = all)</label><br>
                <?php
                $allCourses = [];
                $cr = mysqli_query($conn, 'SELECT name FROM courses ORDER BY name');
                if ($cr) { while ($crrow = mysqli_fetch_assoc($cr)) $allCourses[] = $crrow['name']; mysqli_free_result($cr); }
                foreach ($allCourses as $cname):
                ?>
                    <label style="display:inline-block; margin-right:12px; font-weight:400;">
                        <input type="checkbox" name="allowed_courses[]" value="<?php echo htmlspecialchars($cname); ?>" <?php echo in_array($cname, $editAllowedCourses, true) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($cname); ?>
                    </label>
                <?php endforeach; ?>
                <?php if (empty($allCourses)): ?><span style="color:var(--text-secondary); font-size:0.85rem;">No courses yet. <a href="<?php echo BASE_URL; ?>admin/courses.php">Add courses</a>.</span><?php endif; ?>
                <br>

                <h4 style="margin-top:16px;">Capacity &amp; Deadline</h4>
                <label>Max Members (blank = unlimited)</label><br>
                <input type="number" name="max_members" min="1" value="<?php echo $editOrg && $editOrg['max_members'] !== null ? (int)$editOrg['max_members'] : ''; ?>"><br>
                <label>Application Deadline (blank = none)</label><br>
                <input type="date" name="application_deadline" value="<?php echo $editOrg ? htmlspecialchars($editOrg['application_deadline'] ?? '') : ''; ?>"><br>
                <label style="display:inline-flex; align-items:center; gap:4px; margin-top:8px; font-weight:400; text-align:left;">
                    <input type="checkbox" name="auto_approve" value="1" style="margin:0;" <?php echo ($editOrg && (int)$editOrg['auto_approve'] === 1) ? 'checked' : ''; ?>>Auto-approve applications (if capacity not full)
                </label>
                <br><br>

                <div style="display:flex; gap:10px; max-width:480px; margin-top:8px;">
                    <button type="submit" style="flex:1; margin-top:0;"><?php echo $editOrg ? 'Update' : 'Add'; ?></button>
                    <?php if ($editOrg): ?>
                        <a href="<?php echo BASE_URL; ?>admin/organizations.php" class="btn btn-cancel" style="flex:1; margin-top:0; text-align:center;">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="org-tab-panel <?php echo $defaultTab === 'mods' ? 'active' : ''; ?>" id="tab-mods">
        <h2>Organization Moderators</h2>
        <form method="get" action="" style="margin-bottom:16px; display:flex; gap:8px; align-items:center;">
            <input type="hidden" name="tab" value="mods">
            <input type="text" name="mod_search" placeholder="Search by name or student number..." value="<?php echo htmlspecialchars($modSearch); ?>" style="flex:1; min-width:300px; height:38px; padding:6px 12px; margin:0;">
            <div style="display:flex; align-items:center; gap:8px;">
                <button type="submit" style="padding:8px 16px; height:38px; margin:0;">Search</button>
                <?php if ($modSearch !== ''): ?>
                    <a href="<?php echo BASE_URL; ?>admin/organizations.php?tab=mods" class="btn btn-secondary" style="padding:8px 16px; height:38px; margin:0; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; background:#fff; color:var(--text-primary); border:1px solid var(--border);">Clear</a>
                <?php endif; ?>
            </div>
        </form>
        <table>
            <thead><tr><th>Student No.</th><th>Name</th><th>Course/Year</th><th>Organization</th><th>Approved</th></tr></thead>
            <tbody>
            <?php if (empty($mods)): ?>
                <tr><td colspan="5">No moderators found.</td></tr>
            <?php else: ?>
                <?php foreach ($mods as $m): ?>
                <tr>
                    <td><?php echo htmlspecialchars($m['student_number']); ?></td>
                    <td><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['middle_name'] . ' ' . $m['last_name']); ?></td>
                    <td><?php echo htmlspecialchars(($m['course'] ?? '—') . ' - ' . $m['year_level']); ?></td>
                    <td><?php echo htmlspecialchars($m['org_name']); ?></td>
                    <td><?php echo htmlspecialchars($m['applied_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
      (function() {
        var tabs = document.querySelectorAll('.org-tab');
        tabs.forEach(function(tab) {
          tab.addEventListener('click', function() {
            var target = tab.getAttribute('data-tab');
            tabs.forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            document.querySelectorAll('.org-tab-panel').forEach(function(p) { p.classList.remove('active'); });
            document.getElementById('tab-' + target).classList.add('active');
          });
        });
      })();
    </script>
    <script>
      function confirmDelete(form, text, label) { showConfirm(text, function() { form.submit(); }, label); }
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
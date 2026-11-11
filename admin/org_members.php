<?php
// admin/org_members.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';
requireAdmin();

$orgId = (int) ($_GET['org_id'] ?? 0);
$org = null;
if ($orgId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT id, name, description, max_members FROM organizations WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $orgId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $org = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

$message = '';
$error = '';

$roleFilter = $_GET['role'] ?? 'All';
if (!in_array($roleFilter, ['All','Member','Moderator'], true)) $roleFilter = 'All';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $org) {
    $action = $_POST['action'] ?? '';
    $memId  = (int) ($_POST['id'] ?? 0);
    if ($action === 'remove' && $memId > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE memberships SET status = 'Cancelled' WHERE id = ? AND organization_id = ? AND status = 'Approved'");
        mysqli_stmt_bind_param($stmt, 'ii', $memId, $orgId);
        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
            $message = 'Member removed from organization.';
        } else {
            $error = 'Failed to remove member.';
        }
        mysqli_stmt_close($stmt);
    } elseif ($action === 'role' && $memId > 0) {
        $role = $_POST['role'] ?? 'Member';
        if (!in_array($role, ['Member','Moderator'], true)) $role = 'Member';
        try {
            $stmt = mysqli_prepare($conn, "UPDATE memberships SET role = ? WHERE id = ? AND organization_id = ? AND status = 'Approved'");
            mysqli_stmt_bind_param($stmt, 'sii', $role, $memId, $orgId);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Member role updated.';
            } else {
                $error = 'Failed to update role.';
            }
            mysqli_stmt_close($stmt);
        } catch (mysqli_sql_exception $e) {
            $error = 'Failed to update role.';
        }
    }
}

$members = [];
$approved = 0;
$modCount = 0;
if ($org) {
    // Unfiltered counts
    $cntStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM memberships WHERE organization_id = ? AND status = 'Approved'");
    mysqli_stmt_bind_param($cntStmt, 'i', $orgId);
    mysqli_stmt_execute($cntStmt);
    $cntRes = mysqli_stmt_get_result($cntStmt);
    $cntRow = mysqli_fetch_assoc($cntRes);
    $approved = (int)($cntRow['c'] ?? 0);
    mysqli_stmt_close($cntStmt);

    $modStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM memberships WHERE organization_id = ? AND status = 'Approved' AND role = 'Moderator'");
    mysqli_stmt_bind_param($modStmt, 'i', $orgId);
    mysqli_stmt_execute($modStmt);
    $modRes = mysqli_stmt_get_result($modStmt);
    $modRow = mysqli_fetch_assoc($modRes);
    $modCount = (int)($modRow['c'] ?? 0);
    mysqli_stmt_close($modStmt);

    $sql = "SELECT m.id, m.applied_at, m.role, s.student_number, s.first_name, s.middle_name, s.last_name, s.course, s.year_level, s.section, s.email
            FROM memberships m JOIN students s ON m.student_id = s.id
            WHERE m.organization_id = ? AND m.status = 'Approved'";
    $params = [$orgId];
    $types = 'i';
    if (in_array($roleFilter, ['Member','Moderator'], true)) {
        $sql .= " AND m.role = ?";
        $params[] = $roleFilter;
        $types .= 's';
    }
    $sql .= " ORDER BY s.last_name, s.first_name";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) { $members[] = $r; }
    mysqli_stmt_close($stmt);
}

$pageTitle = 'Organization Members';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Members of <?php echo $org ? htmlspecialchars($org['name']) : '—'; ?></h1>
    <?php if ($message !== ''): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if (!$org): ?>
        <p>Organization not found.</p>
    <?php else: ?>
        <p style="color:var(--text-secondary); font-size:0.9rem;">
            <?php echo $approved; ?> member<?php echo $approved === 1 ? '' : 's'; ?>
            <?php if ($org['max_members'] !== null): ?> / <?php echo (int)$org['max_members']; ?> capacity<?php endif; ?>
            · <?php echo $modCount; ?> moderator<?php echo $modCount === 1 ? '' : 's'; ?>
        </p>

        <form method="get" action="" style="margin-bottom:12px;">
            <input type="hidden" name="org_id" value="<?php echo (int)$orgId; ?>">
            <label style="display:inline-block; margin:0 8px 0 0; font-weight:400;">Filter by role:</label>
            <select name="role" onchange="this.form.submit()" style="padding:4px 6px; font-size:0.9rem;">
                <option value="All" <?php echo $roleFilter === 'All' ? 'selected' : ''; ?>>All</option>
                <option value="Moderator" <?php echo $roleFilter === 'Moderator' ? 'selected' : ''; ?>>Moderator</option>
                <option value="Member" <?php echo $roleFilter === 'Member' ? 'selected' : ''; ?>>Member</option>
            </select>
        </form>

        <?php if (empty($members)): ?>
            <div class="empty-state">No members match this filter.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Student No.</th><th>Name</th><th>Course/Year</th><th>Section</th><th>Email</th><th>Role</th><th>Approved</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td><?php echo htmlspecialchars($m['student_number']); ?></td>
                    <td><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['middle_name'] . ' ' . $m['last_name']); ?></td>
                    <td><?php echo htmlspecialchars(($m['course'] ?? '—') . ' - ' . $m['year_level']); ?></td>
                    <td><?php echo htmlspecialchars($m['section'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($m['email']); ?></td>
                    <td>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                            <select name="role" onchange="this.form.submit()" style="padding:4px 6px; font-size:0.85rem;">
                                <option value="Member" <?php echo $m['role'] === 'Member' ? 'selected' : ''; ?>>Member</option>
                                <option value="Moderator" <?php echo $m['role'] === 'Moderator' ? 'selected' : ''; ?>>Moderator</option>
                            </select>
                        </form>
                    </td>
                    <td><?php echo htmlspecialchars($m['applied_at']); ?></td>
                    <td>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                            <button type="button" class="btn-danger" onclick="confirmRemove(this.form);">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <br>
        <a href="<?php echo BASE_URL; ?>admin/organizations.php" class="btn btn-secondary">Back to Organizations</a>
    <?php endif; ?>
<script>
  function confirmRemove(form) {
    showConfirm('Remove this member from the organization?', function() { form.submit(); }, 'Remove');
  }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
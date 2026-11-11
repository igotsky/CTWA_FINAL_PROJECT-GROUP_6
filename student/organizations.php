<?php
// student/organizations.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/eligibility.php';
requireStudent();

$cu = currentUser();

// Get student row
$student = null;
$stmt = mysqli_prepare($conn, 'SELECT id, first_name, last_name, course, year_level, gender, section FROM students WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

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

$pageTitle = 'Available Organizations';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Available Organizations</h1>
    <?php if (empty($orgs)): ?>
        <p>No organizations available yet.</p>
    <?php endif; ?>
    <?php foreach ($orgs as $o): ?>
        <?php
        $reasons = $student ? checkEligibility($conn, $student, $o) : [];
        // Deadline is shown as a separate "Closed" badge; don't double-count as "Not eligible"
        $eligibilityReasons = array_values(array_filter($reasons, function ($r) {
            return $r !== 'Application deadline has passed';
        }));
        $eligible = empty($eligibilityReasons);
        $full = ($o['max_members'] !== null) && ((int)$o['member_count'] >= (int)$o['max_members']);
        $closed = !empty($o['application_deadline']) && date('Y-m-d') > $o['application_deadline'];
        ?>
        <div class="card">
            <h3><?php echo htmlspecialchars($o['name']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($o['description'])); ?></p>
            <p style="font-size:0.85rem; color:var(--text-secondary); margin:4px 0;">
                <strong>Members:</strong> <?php echo (int)$o['member_count']; ?><?php echo $o['max_members'] !== null ? '/' . (int)$o['max_members'] : ''; ?>
                <?php if ($closed): ?> · <span class="badge rejected">Closed</span><?php endif; ?>
                <?php if ($full && !$closed): ?> · <span class="badge rejected">Full</span><?php endif; ?>
                <?php if (!$eligible): ?> · <span class="badge pending">Not eligible</span><?php endif; ?>
            </p>
            <?php if (!$eligible): ?>
                <p style="font-size:0.8rem; color:var(--text-secondary); margin:4px 0;">
                    <?php echo htmlspecialchars(implode('; ', $eligibilityReasons)); ?>
                </p>
            <?php endif; ?>
            <?php if ($eligible && !$full && !$closed): ?>
                <form method="post" action="<?php echo BASE_URL; ?>student/apply.php">
                    <input type="hidden" name="organization_id" value="<?php echo (int) $o['id']; ?>">
                    <button type="submit">Apply for Membership</button>
                </form>
            <?php else: ?>
                <button type="button" disabled style="opacity:0.5; cursor:not-allowed;">Apply for Membership</button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php
$showApplied = isset($_GET['applied']);
$applyError = trim($_GET['apply_error'] ?? '');
$applyMsg = trim($_GET['msg'] ?? '');
if ($showApplied || $applyError !== ''): ?>
<script>
  (function() {
    <?php if ($applyError !== ''): ?>
    showMessage(<?php echo json_encode($applyError); ?>, 'error');
    <?php else: ?>
    showMessage(<?php echo json_encode($applyMsg !== '' ? $applyMsg : 'Application submitted successfully.'); ?>, 'success');
    <?php endif; ?>
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  })();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
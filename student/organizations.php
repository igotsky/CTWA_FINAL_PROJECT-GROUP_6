<?php
// student/organizations.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireStudent();

$orgs = [];
$res = mysqli_query($conn, 'SELECT id, name, description, created_at FROM organizations ORDER BY name');
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $orgs[] = $row;
    }
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
        <div class="card">
            <h3><?php echo htmlspecialchars($o['name']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($o['description'])); ?></p>
            <form method="post" action="<?php echo BASE_URL; ?>student/apply.php">
                <input type="hidden" name="organization_id" value="<?php echo (int) $o['id']; ?>">
                <button type="submit">Apply for Membership</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php
$showApplied = isset($_GET['applied']);
$applyError = trim($_GET['apply_error'] ?? '');
if ($showApplied || $applyError !== ''): ?>
<script>
  (function() {
    <?php if ($showApplied): ?>
    showMessage('Application submitted successfully. Status: Pending.', 'success');
    <?php else: ?>
    showMessage(<?php echo json_encode($applyError); ?>, 'error');
    <?php endif; ?>
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  })();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>

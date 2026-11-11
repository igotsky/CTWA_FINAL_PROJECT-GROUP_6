<?php
// admin/applications.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($appId > 0 && in_array($status, ['Approved', 'Rejected', 'Pending'], true)) {
        // Block updates to cancelled applications
        $curStmt = mysqli_prepare($conn, 'SELECT status FROM memberships WHERE id = ?');
        mysqli_stmt_bind_param($curStmt, 'i', $appId);
        mysqli_stmt_execute($curStmt);
        $curRes = mysqli_stmt_get_result($curStmt);
        $curRow = mysqli_fetch_assoc($curRes);
        mysqli_stmt_close($curStmt);
        if ($curRow && $curRow['status'] === 'Cancelled') {
            $error = 'Cancelled applications cannot be updated.';
            $status = null;
        }

        // If approving, check capacity
        if ($status === 'Approved') {
            $stmt = mysqli_prepare($conn, 'SELECT organization_id FROM memberships WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $appId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);
            $orgId = $row ? (int)$row['organization_id'] : 0;

            if ($orgId > 0) {
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
                    $approved = (int)($cRow['c'] ?? 0);
                    // exclude current if already approved
                    $curStmt = mysqli_prepare($conn, "SELECT status FROM memberships WHERE id = ?");
                    mysqli_stmt_bind_param($curStmt, 'i', $appId);
                    mysqli_stmt_execute($curStmt);
                    $curRes = mysqli_stmt_get_result($curStmt);
                    $curRow = mysqli_fetch_assoc($curRes);
                    mysqli_stmt_close($curStmt);
                    if ($curRow && $curRow['status'] === 'Approved') $approved--;

                    if ($approved >= (int)$maxMembers) {
                        $error = 'Cannot approve: organization is at full capacity.';
                        $status = null;
                    }
                }
            }
        }

        if ($status !== null) {
            $stmt = mysqli_prepare($conn, 'UPDATE memberships SET status = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $status, $appId);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Application status updated to ' . $status . '.';
            } else {
                $error = 'Failed to update status.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$filter = $_GET['status'] ?? 'All';

$sql = "SELECT m.id, m.applied_at, m.status,
               s.student_number, s.first_name, s.middle_name, s.last_name, s.course, s.year_level,
               o.name AS org_name
        FROM memberships m
        JOIN students s ON m.student_id = s.id
        JOIN organizations o ON m.organization_id = o.id";
$params = [];
$types = '';
if ($filter !== 'All' && in_array($filter, ['Pending','Approved','Rejected'], true)) {
    $sql .= ' WHERE m.status = ?';
    $params[] = $filter;
    $types .= 's';
}
$sql .= ' ORDER BY m.applied_at DESC';

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$applications = [];
while ($row = mysqli_fetch_assoc($res)) {
    $applications[] = $row;
}
mysqli_stmt_close($stmt);

// Count pending applications for polling badge
$pendingApps = 0;
$res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM memberships WHERE status = 'Pending'");
if ($res) {
    $row = mysqli_fetch_assoc($res);
    $pendingApps = (int) ($row['c'] ?? 0);
    mysqli_free_result($res);
}

$pageTitle = 'Membership Applications';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Membership Applications</h1>
    <?php if ($message !== ''): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="get" action="">
        <label>Filter by status:</label>
        <select name="status" onchange="this.form.submit()">
            <?php foreach (['All','Pending','Approved','Rejected'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $filter === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <br>

    <table>
        <thead><tr><th>ID</th><th>Student</th><th>Student No.</th><th>Course/Year</th><th>Organization</th><th>Applied</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($applications as $a): ?>
            <tr>
                <td><?php echo (int) $a['id']; ?></td>
                <td><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['middle_name'] . ' ' . $a['last_name']); ?></td>
                <td><?php echo htmlspecialchars($a['student_number']); ?></td>
                 <td><?php echo htmlspecialchars(($a['course'] ?? '—') . ' - ' . $a['year_level']); ?></td>
                <td><?php echo htmlspecialchars($a['org_name']); ?></td>
                <td><?php echo htmlspecialchars($a['applied_at']); ?></td>
                <td><span class="badge <?php echo strtolower($a['status']); ?>"><?php echo htmlspecialchars($a['status']); ?></span></td>
                <td>
                    <?php if ($a['status'] === 'Cancelled'): ?>
                        <span class="badge cancelled">Cancelled</span>
                    <?php else: ?>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                            <select name="status">
                                <option value="Pending" <?php echo $a['status']==='Pending'?'selected':''; ?>>Pending</option>
                                <option value="Approved" <?php echo $a['status']==='Approved'?'selected':''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $a['status']==='Rejected'?'selected':''; ?>>Rejected</option>
                            </select>
                            <button type="submit">Update</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($applications)): ?>
            <tr><td colspan="8">No applications found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <script>
      (function() {
        // Poll pending application count; auto-reload page on new submissions
        var stored = parseInt(localStorage.getItem('appLastSeen') || '0', 10);
        var initialCount = <?php echo (int) $pendingApps; ?>;
        if (stored > initialCount) stored = initialCount;
        var lastCount = stored || initialCount;
        var prevCount = lastCount;

        function poll() {
          fetch('<?php echo BASE_URL; ?>admin/app_count.php', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              var n = d.pending || 0;
              console.log('app poll:', n);
              if (n > prevCount) {
                prevCount = n;
                lastCount = n;
                try { localStorage.setItem('appLastSeen', String(n)); } catch (e) {}
                window.location.reload();
              } else {
                prevCount = n;
                lastCount = n;
                try { localStorage.setItem('appLastSeen', String(n)); } catch (e) {}
              }
            })
            .catch(function(e){ console.error('app poll error:', e); });
        }

        setInterval(poll, 1000);
        poll();
      })();
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

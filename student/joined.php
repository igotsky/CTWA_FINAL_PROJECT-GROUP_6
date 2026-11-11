<?php
// student/joined.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireStudent();

$cu = currentUser();
$studentId = 0;
$stmt = mysqli_prepare($conn, 'SELECT id FROM students WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $cu['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
if ($row) { $studentId = (int) $row['id']; }
mysqli_stmt_close($stmt);

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $studentId > 0) {
    $action = $_POST['action'] ?? '';
    $appId  = (int) ($_POST['id'] ?? 0);
    if ($action === 'leave' && $appId > 0) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM memberships WHERE id = ? AND student_id = ? AND status = 'Approved'");
        mysqli_stmt_bind_param($stmt, 'ii', $appId, $studentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        if (!$row) {
            $error = 'Membership not found.';
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE memberships SET status = 'Cancelled' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $appId);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'You have left the organization.';
            } else {
                $error = 'Failed to leave organization.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch joined orgs with role
$joined = [];
if ($studentId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT m.id AS membership_id, m.role, o.id AS org_id, o.name, o.description, m.applied_at
        FROM memberships m JOIN organizations o ON m.organization_id = o.id
        WHERE m.student_id = ? AND m.status = 'Approved' ORDER BY o.name");
    mysqli_stmt_bind_param($stmt, 'i', $studentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $joined[] = $r;
    }
    mysqli_stmt_close($stmt);
}

// For each org, fetch members, announcements, and pending apps count (for moderators)
$totalPendingApps = 0;
foreach ($joined as &$j) {
    $orgId = $j['org_id'];
    // Members
    $j['members'] = [];
    $mStmt = mysqli_prepare($conn, "SELECT m.student_id, m.role, s.student_number, s.first_name, s.middle_name, s.last_name, s.course, s.year_level
        FROM memberships m JOIN students s ON m.student_id = s.id
        WHERE m.organization_id = ? AND m.status = 'Approved' ORDER BY m.role DESC, s.last_name, s.first_name");
    mysqli_stmt_bind_param($mStmt, 'i', $orgId);
    mysqli_stmt_execute($mStmt);
    $mRes = mysqli_stmt_get_result($mStmt);
    while ($mr = mysqli_fetch_assoc($mRes)) { $j['members'][] = $mr; }
    mysqli_stmt_close($mStmt);

    // Announcements (latest 5)
    $j['announcements'] = [];
    $aStmt = mysqli_prepare($conn, "SELECT a.*, s.first_name, s.last_name FROM org_announcements a JOIN students s ON a.author_id = s.id WHERE a.organization_id = ? ORDER BY a.created_at DESC LIMIT 5");
    mysqli_stmt_bind_param($aStmt, 'i', $orgId);
    mysqli_stmt_execute($aStmt);
    $aRes = mysqli_stmt_get_result($aStmt);
    while ($ar = mysqli_fetch_assoc($aRes)) { $j['announcements'][] = $ar; }
    mysqli_stmt_close($aStmt);

    // Pending applications count (for moderators)
    $j['pending_apps_count'] = 0;
    if ($j['role'] === 'Moderator') {
        $pStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM memberships WHERE organization_id = ? AND status = 'Pending'");
        mysqli_stmt_bind_param($pStmt, 'i', $orgId);
        mysqli_stmt_execute($pStmt);
        $pRes = mysqli_stmt_get_result($pStmt);
        $pRow = mysqli_fetch_assoc($pRes);
        $j['pending_apps_count'] = (int) ($pRow['c'] ?? 0);
        mysqli_stmt_close($pStmt);
        $totalPendingApps += $j['pending_apps_count'];
    }
}
unset($j);

$pageTitle = 'Joined Organizations';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Joined Organizations</h1>
    <?php if ($message !== ''): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (empty($joined)): ?>
        <p>You have not joined any organizations yet.</p>
    <?php else: ?>
        <?php foreach ($joined as $j): ?>
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <h3 style="margin:0;"><?php echo htmlspecialchars($j['name']); ?></h3>
                    <span class="badge <?php echo strtolower($j['role']); ?>"><?php echo htmlspecialchars($j['role']); ?></span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($j['description'])); ?></p>
                <small>Approved on: <?php echo htmlspecialchars($j['applied_at']); ?></small>

                <!-- Announcements -->
                <?php if (!empty($j['announcements'])): ?>
                <div style="margin-top:16px; padding-top:12px; border-top:1px solid var(--border);">
                    <h4 style="margin:0 0 8px; font-size:0.9rem;">Announcements</h4>
                    <?php foreach ($j['announcements'] as $a): ?>
                    <div style="padding:8px; background:var(--card-bg); border-radius:var(--radius-sm); margin-bottom:8px; border:1px solid var(--border);">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px;">
                            <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                            <small style="color:var(--text-secondary);"><?php echo htmlspecialchars($a['created_at']); ?></small>
                        </div>
                        <p style="margin:4px 0; font-size:0.9rem;"><?php echo nl2br(htmlspecialchars($a['body'])); ?></p>
                        <small style="color:var(--text-secondary);">By <?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                    <!-- View Members -->
                    <button type="button" class="btn-secondary" data-org-id="<?php echo (int) $j['org_id']; ?>" data-org-name="<?php echo htmlspecialchars($j['name']); ?>" data-members='<?php echo json_encode($j['members'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>' onclick="showMembersModal(this);">View Members (<?php echo count($j['members']); ?>)</button>

                    <!-- Manage (for moderators) -->
                    <?php if ($j['role'] === 'Moderator'): ?>
                        <a href="<?php echo BASE_URL; ?>student/org_manage.php?org_id=<?php echo (int) $j['org_id']; ?>" class="btn" style="background:var(--btn-primary); color:white; border-color:var(--btn-primary);">Manage Organization<?php if ($j['pending_apps_count'] > 0): ?><span class="tab-badge" id="manageBadge_<?php echo (int) $j['org_id']; ?>"><?php echo $j['pending_apps_count']; ?></span><?php else: ?><span class="tab-badge" id="manageBadge_<?php echo (int) $j['org_id']; ?>" style="display:none;"></span><?php endif; ?></a>
                    <?php endif; ?>

                    <!-- Leave -->
                    <form method="post" action="" style="display:inline;">
                        <input type="hidden" name="action" value="leave">
                        <input type="hidden" name="id" value="<?php echo (int) $j['membership_id']; ?>">
                        <button type="button" class="btn-danger" onclick="confirmLeaveOrg(this.form);">Leave</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Members Modal -->
    <div id="membersModal" class="msg-overlay" style="display:none;">
        <div class="msg-box" style="max-width:600px; max-height:80vh; overflow:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 id="membersModalTitle" style="margin:0;"></h3>
                <button type="button" class="msg-ok" style="background:#fff; color:var(--text-primary); border:1px solid var(--border);" onclick="document.getElementById('membersModal').style.display='none';">Close</button>
            </div>
            <div id="membersModalBody"></div>
        </div>
    </div>

    <script>
      function confirmLeaveOrg(form) {
        showConfirm('Leave this organization?', function() { form.submit(); });
      }

      function showMembersModal(btn) {
        var modal = document.getElementById('membersModal');
        var title = document.getElementById('membersModalTitle');
        var body = document.getElementById('membersModalBody');

        var orgId = btn.getAttribute('data-org-id');
        var orgName = btn.getAttribute('data-org-name');
        var members = JSON.parse(btn.getAttribute('data-members') || '[]');

        title.textContent = 'Members of ' + orgName;

        if (!members || members.length === 0) {
            body.innerHTML = '<p style="text-align:center; color:var(--text-secondary);">No members yet.</p>';
        } else {
            var html = '<table style="width:100%; font-size:0.9rem;">';
            html += '<thead><tr><th style="text-align:left;">Student No.</th><th style="text-align:left;">Name</th><th style="text-align:left;">Course/Year</th><th style="text-align:left;">Role</th></tr></thead>';
            html += '<tbody>';
            members.forEach(function(m) {
                html += '<tr>';
                html += '<td>' + escapeHtml(m.student_number) + '</td>';
                html += '<td>' + escapeHtml(m.first_name + ' ' + m.middle_name + ' ' + m.last_name) + '</td>';
                html += '<td>' + escapeHtml((m.course || '—') + ' - ' + m.year_level) + '</td>';
                html += '<td><span class="badge ' + m.role.toLowerCase() + '">' + escapeHtml(m.role) + '</span></td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            body.innerHTML = html;
        }
        modal.style.display = 'flex';
      }

      function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }

      // Close modal on overlay click
      document.getElementById('membersModal').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
      });

      // Sync moderator badges with pending application counts
      (function() {
        var moderatorOrgs = {};
        <?php foreach ($joined as $j): ?>
            <?php if ($j['role'] === 'Moderator'): ?>
        moderatorOrgs[<?php echo (int) $j['org_id']; ?>] = { badgeId: 'manageBadge_<?php echo (int) $j['org_id']; ?>', count: <?php echo (int) $j['pending_apps_count']; ?> };
            <?php endif; ?>
        <?php endforeach; ?>

        var totalBadge = document.getElementById('joinedTotalBadge');
        var initialTotal = <?php echo (int) $totalPendingApps; ?>;
        if (totalBadge) {
          if (initialTotal > 0) {
            totalBadge.style.display = 'inline-block';
            totalBadge.textContent = initialTotal;
            totalBadge.classList.remove('dot');
          } else {
            totalBadge.style.display = 'none';
          }
        }

        function updateBadges() {
          var total = 0;
          var promises = Object.keys(moderatorOrgs).map(function(orgId) {
            return fetch('<?php echo BASE_URL; ?>admin/app_count_org.php?org_id=' + orgId, { credentials: 'same-origin' })
              .then(function(r) { return r.json(); })
              .then(function(d) {
                var count = d.pending || 0;
                var info = moderatorOrgs[orgId];
                info.count = count;
                total += count;
                var badge = document.getElementById(info.badgeId);
                if (badge) {
                  if (count > 0) {
                    badge.style.display = 'inline-block';
                    badge.textContent = count;
                    badge.classList.remove('dot');
                  } else {
                    badge.style.display = 'none';
                  }
                }
              })
              .catch(function(e) { console.error('app count fetch error for org ' + orgId + ':', e); });
          });

          Promise.all(promises).then(function() {
            if (totalBadge) {
              if (total > 0) {
                totalBadge.style.display = 'inline-block';
                totalBadge.textContent = total;
                totalBadge.classList.remove('dot');
              } else {
                totalBadge.style.display = 'none';
              }
            }
          });
        }

        // Poll every 30 seconds for updates
        setInterval(updateBadges, 30000);
      })();
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>